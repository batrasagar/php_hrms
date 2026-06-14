<?php
require_once __DIR__ . '/ShardManager.php';

/**
 * Stage 3: tblAttendance_YYMM → tblMonthlyAttendance_YYMM
 *
 * Reads all attendance rows for the month and builds:
 *  - D01..D31   status codes per day
 *  - Summary    TotP, TotA, TotHD, TotWO, TotPH, TotL, TotOT, TotShort, WorkDays
 *  - DayData    JSON { "1": {in,out,tot,ot,sts,sh}, ... }
 */
class MonthlyProcessor
{
    public function __construct(
        private PDO          $db,
        private ShardManager $shard
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Process one month for one company.
     * Pass $yr as 4-digit year (2026) or 2-digit (26) — both work.
     * Returns count of rows written.
     */
    public function processMonth(int $companyId, int $yr, int $mnth): int
    {
        $yr2    = $yr % 100; // store as 2-digit
        $ym     = sprintf('%02d%02d', $yr2, $mnth);
        $attnTbl  = $this->shard->tbl('Attendance',        $ym);
        $monthTbl = $this->shard->tbl('MonthlyAttendance', $ym);

        // Load all attendance rows for this company+month
        $stmt = $this->db->prepare("
            SELECT EmpCode, tDate, AttStatus, TimeIn, TimeOut,
                   TotalMins, OT, ShortTime, ShiftNo
            FROM `{$attnTbl}`
            WHERE CompanyId = ?
            ORDER BY EmpCode, tDate
        ");
        $stmt->execute([$companyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$rows) return 0;

        // Group by employee
        $byEmp = [];
        foreach ($rows as $r) {
            $byEmp[$r['EmpCode']][] = $r;
        }

        // Build insert batches
        $written = 0;
        foreach ($byEmp as $empCode => $days) {
            $this->writeEmployee($companyId, $empCode, $yr2, $mnth, $days, $monthTbl);
            $written++;
        }
        return $written;
    }

    /**
     * Re-process a single employee's month (e.g. after correction).
     */
    public function processEmployee(int $companyId, string $empCode, int $yr, int $mnth): void
    {
        $yr2      = $yr % 100;
        $ym       = sprintf('%02d%02d', $yr2, $mnth);
        $attnTbl  = $this->shard->tbl('Attendance',        $ym);
        $monthTbl = $this->shard->tbl('MonthlyAttendance', $ym);

        $stmt = $this->db->prepare("
            SELECT EmpCode, tDate, AttStatus, TimeIn, TimeOut,
                   TotalMins, OT, ShortTime, ShiftNo
            FROM `{$attnTbl}`
            WHERE CompanyId = ? AND EmpCode = ?
            ORDER BY tDate
        ");
        $stmt->execute([$companyId, $empCode]);
        $days = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->writeEmployee($companyId, $empCode, $yr2, $mnth, $days, $monthTbl);
    }

    // ── Core Logic ────────────────────────────────────────────────────────────

    private function writeEmployee(
        int $companyId, string $empCode, int $yr2, int $mnth,
        array $days, string $monthTbl
    ): void {
        // Day buckets: index 1..31
        $d       = array_fill(1, 31, '');
        $dayData = [];

        $totP = $totA = $totHD = $totWO = $totPH = $totL = 0;
        $totOT = $totShort = 0;
        $workDays = 0.0;

        foreach ($days as $row) {
            $day = (int)date('j', strtotime($row['tDate']));
            $sts = $row['AttStatus'];
            $d[$day] = $sts;

            // Accumulate summaries
            switch ($sts) {
                case 'P':
                case 'WOP':  $totP++;    $workDays += 1.0; break;
                case 'HD':   $totHD++;   $workDays += 0.5; break;
                case 'WO':   $totWO++;                     break;
                case 'PH':   $totPH++;                     break;
                case 'L':
                case 'SL':
                case 'CO':   $totL++;                      break;
                default:     $totA++;                      break;
            }
            $totOT    += (int)$row['OT'];
            $totShort += (int)$row['ShortTime'];

            // DayData JSON — only store days that have punch data or non-trivial status
            if ($row['TimeIn'] || $row['TimeOut'] || in_array($sts, ['P','HD','WOP'])) {
                $dayData[(string)$day] = [
                    'in'  => $row['TimeIn']   ?: '',
                    'out' => $row['TimeOut']  ?: '',
                    'tot' => (int)$row['TotalMins'],
                    'ot'  => (int)$row['OT'],
                    'sts' => $sts,
                    'sh'  => (int)$row['ShiftNo'],
                ];
            }
        }

        // Build parameterised REPLACE INTO
        // 4 base cols + 31 day cols + 10 summary cols + DayData
        $dCols = '';
        $dPhs  = '';
        $dVals = [];
        for ($i = 1; $i <= 31; $i++) {
            $dCols .= sprintf(', `D%02d`', $i);
            $dPhs  .= ', ?';
            $dVals[] = $d[$i];
        }

        $sql = "REPLACE INTO `{$monthTbl}`
            (`CompanyId`, `EmpCode`, `Yr`, `Mnth`
             {$dCols},
             `TotP`, `TotA`, `TotHD`, `TotWO`, `TotPH`, `TotL`,
             `TotOT`, `TotShort`, `WorkDays`, `DayData`, `ProcessedAt`)
            VALUES (?, ?, ?, ?
             {$dPhs},
             ?, ?, ?, ?, ?, ?,
             ?, ?, ?, ?, NOW())";

        $params = array_merge(
            [$companyId, $empCode, $yr2, $mnth],
            $dVals,
            [$totP, $totA, $totHD, $totWO, $totPH, $totL,
             $totOT, $totShort, $workDays,
             $dayData ? json_encode($dayData, JSON_UNESCAPED_UNICODE) : null]
        );

        $this->db->prepare($sql)->execute($params);
    }
}
