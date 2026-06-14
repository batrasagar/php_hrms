<?php
require_once __DIR__ . '/ShardManager.php';

/**
 * Stage 1: tblDeviceLog (AttnLog raw) → tblPunchLog_YYMM (local, enriched, sharded)
 *
 * Resolution: (DeviceSerial, EnrollId) → (CompanyId, EmpCode) via tblDeviceEnrollment.
 * Each punch is written to the shard matching its PunchDateTime month (e.g. tblPunchLog_2606).
 */
class PunchSyncService
{
    private PDO          $db;
    private ShardManager $shard;
    // "serial:enrollId" => {companyId, empCode}
    private array $enrollMap = [];

    public function __construct(PDO $db)
    {
        $this->db    = $db;
        $this->shard = new ShardManager($db);
    }

    /**
     * Sync tblDeviceLog rows into sharded tblPunchLog_YYMM for the given date range.
     * $companyId = 0 → all companies; > 0 → limit to that company's employees only.
     * Returns count of rows inserted.
     */
    public function sync(int $companyId = 0, string $fromDate = '', string $toDate = ''): int
    {
        if (!$fromDate) $fromDate = date('Y-m-d', strtotime('-1 day'));
        if (!$toDate)   $toDate   = date('Y-m-d');

        $stmt = $this->db->prepare("
            SELECT SerialNumber, EnrollId, PunchDateTime, Mode, Stamp
            FROM tblDeviceLog
            WHERE PunchDateTime >= ? AND PunchDateTime < DATE_ADD(?, INTERVAL 1 DAY)
            ORDER BY SerialNumber, EnrollId, PunchDateTime
        ");
        $stmt->execute([$fromDate, $toDate]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$logs) return 0;

        // Unique (SerialNumber, EnrollId) pairs from the batch
        $pairs = [];
        foreach ($logs as $row) {
            $pairs[$row['SerialNumber'] . ':' . $row['EnrollId']] = [
                $row['SerialNumber'], $row['EnrollId']
            ];
        }
        $this->buildEnrollMap(array_values($pairs), $companyId);

        // Group enriched punches by YYMM for shard-routing
        $byMonth = [];
        foreach ($logs as $row) {
            $key = $row['SerialNumber'] . ':' . $row['EnrollId'];
            $m   = $this->enrollMap[$key] ?? null;
            if (!$m) continue;

            // Extract YYMM directly from "YYYY-MM-DD HH:MM:SS"
            $ym = substr($row['PunchDateTime'], 2, 2) . substr($row['PunchDateTime'], 5, 2);

            $byMonth[$ym][] = [
                $m['companyId'],
                $m['empCode'],
                $row['EnrollId'],
                $row['PunchDateTime'],
                $this->modeToType($row['Mode']),
                $row['SerialNumber'],
                $row['Stamp'],
            ];
        }

        $inserted = 0;
        foreach ($byMonth as $ym => $punches) {
            $tbl     = $this->shard->tbl('PunchLog', $ym);
            $sqlBase = "INSERT IGNORE INTO `{$tbl}`
                (CompanyId, EmpCode, EnrollId, PunchTime, PunchType, DeviceSerial, RawStamp, IsProcessed, SyncedAt)
                VALUES ";

            $batch = [];
            $bVals = [];
            foreach ($punches as $p) {
                $batch[] = '(?,?,?,?,?,?,?,0,NOW())';
                array_push($bVals, ...$p);

                if (count($batch) >= 500) {
                    $this->flush($sqlBase, $batch, $bVals);
                    $inserted += count($batch);
                    $batch = $bVals = [];
                }
            }
            if ($batch) {
                $this->flush($sqlBase, $batch, $bVals);
                $inserted += count($batch);
            }
        }

        return $inserted;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function buildEnrollMap(array $pairs, int $filterCompany): void
    {
        if (!$pairs) return;

        $clauses = [];
        $params  = [];
        foreach ($pairs as [$serial, $enrollId]) {
            $clauses[] = '(DeviceSerial=? AND EnrollId=?)';
            $params[]  = $serial;
            $params[]  = $enrollId;
        }

        $extra = $filterCompany > 0 ? ' AND CompanyId = ?' : '';
        if ($filterCompany > 0) $params[] = $filterCompany;

        $sql  = "SELECT DeviceSerial, EnrollId, CompanyId, EmpCode
                 FROM tblDeviceEnrollment
                 WHERE (" . implode(' OR ', $clauses) . ") $extra";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $this->enrollMap = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $e) {
            $key = $e['DeviceSerial'] . ':' . $e['EnrollId'];
            $this->enrollMap[$key] = [
                'companyId' => (int)$e['CompanyId'],
                'empCode'   => $e['EmpCode'],
            ];
        }
    }

    private function modeToType(string $mode): int
    {
        return match (strtolower($mode)) {
            '0', 'in',  'checkin'  => 1,
            '1', 'out', 'checkout' => 2,
            default                => 0,
        };
    }

    private function flush(string $base, array $batch, array $vals): void
    {
        $this->db->prepare($base . implode(',', $batch))->execute($vals);
    }
}
