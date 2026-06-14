<?php
require_once __DIR__ . '/ShardManager.php';

/**
 * Stage 2 of pipeline: tblPunchLog → tblAttendance_YYMM
 *
 * Reads pending punch logs for a company+date, applies corrections,
 * resolves shift rules, and writes one row per employee per day.
 */
class AttendanceProcessor
{
    private PDO          $db;
    private ShardManager $shard;
    private array        $shiftCache   = [];
    private array        $holidayCache = [];

    public function __construct(PDO $db, ShardManager $shard)
    {
        $this->db    = $db;
        $this->shard = $shard;
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Process all pending punch logs up to $endDate for one company.
     * Returns count of attendance rows written.
     */
    public function processCompany(int $companyId, string $endDate = ''): int
    {
        if (!$endDate) $endDate = date('Y-m-d');

        $endTs = strtotime($endDate);
        $dates = [];

        // Scan the last 3 months of PunchLog shards for pending work
        for ($i = 0; $i <= 2; $i++) {
            $ts  = strtotime("-{$i} month", $endTs);
            $ym  = ShardManager::ym((int)date('Y', $ts), (int)date('n', $ts));
            $tbl = $this->shard->tbl('PunchLog', $ym);

            $stmt = $this->db->prepare("
                SELECT DISTINCT DATE(PunchTime) AS d
                FROM `{$tbl}`
                WHERE CompanyId = ? AND IsProcessed = 0 AND DATE(PunchTime) <= ?
                ORDER BY d
            ");
            $stmt->execute([$companyId, $endDate]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $d) {
                $dates[$d] = $d;
            }
        }

        ksort($dates);
        $total = 0;
        foreach ($dates as $date) {
            $total += $this->processDate($companyId, $date);
        }
        return $total;
    }

    /**
     * Process a single date for a company.
     * Returns count of attendance rows written.
     */
    public function processDate(int $companyId, string $date): int
    {
        $ym      = ShardManager::ym((int)date('Y', strtotime($date)), (int)date('n', strtotime($date)));
        $attnTbl = $this->shard->tbl('Attendance', $ym);
        $weekDay = (int)date('N', strtotime($date)); // 1=Mon..7=Sun

        // --- Load data for this company+date ---
        $punches     = $this->loadPunches($companyId, $date);
        $corrections = $this->loadCorrections($companyId, $date);
        $holidays    = $this->isHoliday($companyId, $date);
        $employees   = $this->loadEmployees($companyId);

        $inserts = [];

        foreach ($employees as $emp) {
            $empCode = $emp['EmployeeCode'];
            $shiftNo = (int)($emp['ShiftNo'] ?? 0);
            $shift   = $this->getShift($shiftNo, $companyId);
            $corr    = $corrections[$empCode] ?? null;
            $pList   = $punches[$empCode] ?? [];

            // Resolve IN / OUT
            $timeIn = $timeOut = null;
            $isManual    = 0;
            $forcedStatus = null;

            if ($corr) {
                $timeIn       = $corr['InTime'];
                $timeOut      = $corr['OutTime'];
                $forcedStatus = $corr['AttStatus'] ?: null;
                $isManual     = 1;
            } elseif ($pList) {
                // First punch = IN, last punch = OUT (if >1 punch)
                $timeIn  = date('H:i:s', strtotime($pList[0]['PunchTime']));
                $last    = end($pList);
                $timeOut = count($pList) > 1 ? date('H:i:s', strtotime($last['PunchTime'])) : null;
            }

            $isWO = $this->isWeekOff($emp, $weekDay);

            [$attStatus, $totalMins, $ot, $shortTime] = $this->computeStatus(
                $timeIn, $timeOut, $shift, $isWO, $holidays, $forcedStatus, (bool)$emp['OT']
            );

            $inserts[] = [
                $companyId, $empCode, $date, $weekDay, $attStatus,
                $timeIn, $timeOut, $totalMins,
                $shiftNo, $isWO ? 1 : 0, $ot, $shortTime, $isManual,
            ];
        }

        // Batch REPLACE INTO
        $written = 0;
        foreach (array_chunk($inserts, 200) as $batch) {
            $ph  = implode(',', array_fill(0, count($batch),
                '(?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())'));
            $sql = "REPLACE INTO `{$attnTbl}`
                (CompanyId, EmpCode, tDate, WeekDay, AttStatus, TimeIn, TimeOut, TotalMins,
                 ShiftNo, WO, OT, ShortTime, IsManual, ProcessedAt)
                VALUES {$ph}";
            $flat = array_merge(...$batch);
            $this->db->prepare($sql)->execute($flat);
            $written += count($batch);
        }

        // Mark punch logs done for this date in the correct shard
        $punchTbl = $this->shard->tbl('PunchLog', $ym);
        $this->db->prepare("
            UPDATE `{$punchTbl}` SET IsProcessed=1
            WHERE CompanyId=? AND DATE(PunchTime)=? AND IsProcessed=0
        ")->execute([$companyId, $date]);

        return $written;
    }

    // ── Loaders ───────────────────────────────────────────────────────────────

    private function loadPunches(int $companyId, string $date): array
    {
        $ym  = ShardManager::ym((int)date('Y', strtotime($date)), (int)date('n', strtotime($date)));
        $tbl = $this->shard->tbl('PunchLog', $ym);

        $stmt = $this->db->prepare("
            SELECT EmpCode, PunchTime, PunchType
            FROM `{$tbl}`
            WHERE CompanyId = ? AND DATE(PunchTime) = ?
            ORDER BY EmpCode, PunchTime
        ");
        $stmt->execute([$companyId, $date]);
        $byEmp = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byEmp[$row['EmpCode']][] = $row;
        }
        return $byEmp;
    }

    private function loadCorrections(int $companyId, string $date): array
    {
        $stmt = $this->db->prepare("
            SELECT EmpCode, InTime, OutTime, AttStatus
            FROM tblPunchLogCorrection
            WHERE CompanyId = ? AND tDate = ?
        ");
        $stmt->execute([$companyId, $date]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[$r['EmpCode']] = $r;
        }
        return $out;
    }

    private function loadEmployees(int $companyId): array
    {
        $stmt = $this->db->prepare("
            SELECT EmployeeCode, ShiftNo, WeekdayNo, OT
            FROM tblEmployee
            WHERE CompanyId = ? AND Status = 'active'
        ");
        $stmt->execute([$companyId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
            $out[$e['EmployeeCode']] = $e;
        }
        return $out;
    }

    private function getShift(int $shiftNo, int $companyId): array
    {
        $key = "{$companyId}_{$shiftNo}";
        if (isset($this->shiftCache[$key])) return $this->shiftCache[$key];

        $stmt = $this->db->prepare("
            SELECT ArrivalTime, DepartureTime, HrsP, HrsHlf
            FROM tblShift
            WHERE CompanyId = ? AND id = ? AND IsActive = 1
            LIMIT 1
        ");
        $stmt->execute([$companyId, $shiftNo]);
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$shift) {
            // Try company-level first shift as fallback
            $stmt2 = $this->db->prepare("
                SELECT ArrivalTime, DepartureTime, HrsP, HrsHlf
                FROM tblShift WHERE CompanyId = ? AND IsActive=1 ORDER BY id LIMIT 1
            ");
            $stmt2->execute([$companyId]);
            $shift = $stmt2->fetch(PDO::FETCH_ASSOC)
                ?: ['ArrivalTime' => '09:00:00', 'DepartureTime' => '18:00:00', 'HrsP' => 8, 'HrsHlf' => 4];
        }

        $shift['shiftMins'] = (float)$shift['HrsP']   * 60;
        $shift['halfMins']  = (float)$shift['HrsHlf'] * 60;
        $this->shiftCache[$key] = $shift;
        return $shift;
    }

    private function isHoliday(int $companyId, string $date): bool
    {
        $key = "{$companyId}_{$date}";
        if (!isset($this->holidayCache[$key])) {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM tblHoliday
                WHERE CompanyId = ? AND HolidayDate = ?
            ");
            $stmt->execute([$companyId, $date]);
            $this->holidayCache[$key] = (bool)$stmt->fetchColumn();
        }
        return $this->holidayCache[$key];
    }

    // ── Computation ───────────────────────────────────────────────────────────

    private function isWeekOff(array $emp, int $weekDay): bool
    {
        $woNo = (int)($emp['WeekdayNo'] ?? 0); // 0=Sun, 1=Mon .. 6=Sat
        // Map to PHP date('N'): Sun=0 → 7, Mon=1 → 1 .. Sat=6 → 6
        $woN  = $woNo === 0 ? 7 : $woNo;
        return $weekDay === $woN;
    }

    /**
     * Returns [attStatus, totalMins, otMins, shortMins]
     */
    private function computeStatus(
        ?string $in, ?string $out, array $shift,
        bool $isWO, bool $isHoliday, ?string $forced, bool $hasOT
    ): array {
        if ($forced) {
            $mins = ($in && $out) ? $this->diffMins($in, $out) : 0;
            return [$forced, $mins, 0, 0];
        }

        if ($isHoliday && !$in) return ['PH', 0, 0, 0];
        if ($isWO  && !$in)     return ['WO', 0, 0, 0];
        if (!$in)               return ['A',  0, 0, 0];

        $totalMins = $out ? $this->diffMins($in, $out) : 0;
        $shiftMins = (float)$shift['shiftMins'];
        $halfMins  = (float)$shift['halfMins'];

        if ($totalMins >= $shiftMins) {
            $attStatus = $isHoliday ? 'WOP' : ($isWO ? 'WOP' : 'P');
            $shortTime = 0;
        } elseif ($totalMins >= $halfMins) {
            $attStatus = 'HD';
            $shortTime = (int)($shiftMins - $totalMins);
        } elseif ($totalMins > 0) {
            $attStatus = 'A';
            $shortTime = $totalMins; // partial — still absent for pay purposes
        } else {
            $attStatus = 'A';
            $shortTime = 0;
        }

        $otMins = 0;
        if ($hasOT && $out && $totalMins > ($shiftMins + 30)) {
            $otMins = $totalMins - (int)$shiftMins;
        }

        return [$attStatus, $totalMins, $otMins, $shortTime];
    }

    private function diffMins(string $in, string $out): int
    {
        $base = '1970-01-01 ';
        $i    = strtotime($base . $in);
        $o    = strtotime($base . $out);
        if ($o <= $i) $o += 86400; // overnight shift
        return (int)(($o - $i) / 60);
    }
}
