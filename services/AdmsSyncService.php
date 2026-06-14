<?php
require_once __DIR__ . '/ShardManager.php';

/**
 * Fetches punch data from the ADMS API for each device and stores it in
 * tblPunchLog_YYMM shards.  Resolves (DeviceSerial, EnrollId) → (CompanyId, EmpCode)
 * via tblDeviceEnrollment with a per-company EnrollId fallback from tblEmployee.
 */
class AdmsSyncService
{
    private PDO          $db;
    private ShardManager $shard;
    private array        $enrollMap   = []; // "serial:enrollId" => {companyId, empCode}
    private array        $fallbackMap = []; // companyId => [enrollId => empCode]

    public function __construct(PDO $db)
    {
        $this->db    = $db;
        $this->shard = new ShardManager($db);
    }

    /**
     * Sync punches from ADMS API into tblPunchLog_YYMM shards.
     * $companyId = 0  → all companies/devices.
     * $serial    = '' → all devices in scope; non-empty → only that device.
     * Returns ['inserted' => N, 'skipped' => N, 'errors' => [...], 'devices' => N]
     */
    public function sync(int $companyId = 0, string $fromDate = '', string $toDate = '', string $serial = ''): array
    {
        if (!$fromDate) $fromDate = date('Y-m-d', strtotime('-1 day'));
        if (!$toDate)   $toDate   = date('Y-m-d');

        $cred = $this->db->query(
            "SELECT * FROM tblAdmsCredentials WHERE IsActive=1 ORDER BY id LIMIT 1"
        )->fetch();
        if (!$cred) {
            return ['inserted' => 0, 'skipped' => 0, 'errors' => ['No active ADMS credential configured.'], 'devices' => 0];
        }

        $devices = $this->getDevices($companyId, $serial);
        if (!$devices) {
            return ['inserted' => 0, 'skipped' => 0, 'errors' => ['No devices found.'], 'devices' => 0];
        }

        $this->buildEnrollMap($devices, $companyId);

        $inserted = 0;
        $skipped  = 0;
        $errors   = [];
        $fromDt   = $fromDate . ' 00:00:00';
        $toDt     = $toDate   . ' 23:59:59';

        foreach ($devices as $dev) {
            $serial       = $dev['SerialNumber'];
            $devCompanyId = (int)($dev['resolved_company_id'] ?? 0);

            $url = rtrim($cred['Endpoint'], '/') . '/api/punchlog.php?SerialNumber=' . urlencode($serial);
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['X-Api-Key: ' . $cred['ApiKey']],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr)       { $errors[] = "{$serial}: {$curlErr}"; continue; }
            if ($httpCode !== 200) { $errors[] = "{$serial}: HTTP {$httpCode}"; continue; }

            $data = json_decode($response, true);
            if (empty($data['success']) || empty($data['data'])) continue;

            // Group punches by YYMM shard
            $byMonth = [];
            foreach ($data['data'] as $punch) {
                $pdt = $punch['PunchDateTime'] ?? '';
                if ($pdt < $fromDt || $pdt > $toDt) { $skipped++; continue; }

                $eid     = (string)($punch['EnrollId'] ?? '');
                $pSerial = (string)($punch['SerialNumber'] ?? $serial);

                // Primary: tblDeviceEnrollment
                $m = $this->enrollMap[$pSerial . ':' . $eid]
                  ?? $this->enrollMap[$serial   . ':' . $eid]
                  ?? null;

                // Fallback: match EnrollId against tblEmployee for device's company
                if (!$m && $devCompanyId && isset($this->fallbackMap[$devCompanyId][$eid])) {
                    $m = ['companyId' => $devCompanyId, 'empCode' => $this->fallbackMap[$devCompanyId][$eid]];
                }

                if (!$m) { $skipped++; continue; }

                $ym = substr($pdt, 2, 2) . substr($pdt, 5, 2);
                $byMonth[$ym][] = [
                    $m['companyId'],
                    $m['empCode'],
                    $eid,
                    $pdt,
                    $this->modeToType((string)($punch['Mode'] ?? '')),
                    $pSerial,
                    $punch['Stamp'] ?? $pdt,
                ];
            }

            foreach ($byMonth as $ym => $punches) {
                $tbl     = $this->shard->tbl('PunchLog', $ym);
                $sqlBase = "INSERT IGNORE INTO `{$tbl}`
                    (CompanyId, EmpCode, EnrollId, PunchTime, PunchType, DeviceSerial, RawStamp, IsProcessed, SyncedAt)
                    VALUES ";

                foreach (array_chunk($punches, 500) as $batch) {
                    $ph   = implode(',', array_fill(0, count($batch), '(?,?,?,?,?,?,?,0,NOW())'));
                    $vals = array_merge(...$batch);
                    $stmt = $this->db->prepare($sqlBase . $ph);
                    $stmt->execute($vals);
                    $inserted += $stmt->rowCount();
                }
            }

            // Track last sync time per device
            try {
                $this->db->prepare("UPDATE tblDevices SET LastSyncedAt=NOW() WHERE SerialNumber=?")
                         ->execute([$serial]);
            } catch (\PDOException $e) { /* column may not exist on older installs */ }
        }

        return [
            'inserted' => $inserted,
            'skipped'  => $skipped,
            'errors'   => $errors,
            'devices'  => count($devices),
        ];
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function getDevices(int $companyId, string $serial = ''): array
    {
        if ($serial !== '') {
            $stmt = $this->db->prepare(
                "SELECT d.SerialNumber, d.Company, c.id AS resolved_company_id
                 FROM tblDevices d
                 LEFT JOIN tblCompany c ON c.Name = d.Company AND c.IsActive=1
                 WHERE d.SerialNumber = ?"
            );
            $stmt->execute([$serial]);
        } elseif ($companyId > 0) {
            $coName = $this->db->prepare("SELECT Name FROM tblCompany WHERE id=?");
            $coName->execute([$companyId]);
            $name = $coName->fetchColumn() ?: '__none__';

            $stmt = $this->db->prepare(
                "SELECT d.SerialNumber, d.Company, c.id AS resolved_company_id
                 FROM tblDevices d
                 LEFT JOIN tblCompany c ON c.Name = d.Company AND c.IsActive=1
                 WHERE d.Company = ?
                 ORDER BY d.SerialNumber"
            );
            $stmt->execute([$name]);
        } else {
            $stmt = $this->db->query(
                "SELECT d.SerialNumber, d.Company, c.id AS resolved_company_id
                 FROM tblDevices d
                 LEFT JOIN tblCompany c ON c.Name = d.Company AND c.IsActive=1
                 ORDER BY d.SerialNumber"
            );
        }
        return $stmt->fetchAll();
    }

    private function buildEnrollMap(array $devices, int $filterCompany): void
    {
        $serials = array_values(array_unique(array_column($devices, 'SerialNumber')));
        if (!$serials) return;

        $ph    = implode(',', array_fill(0, count($serials), '?'));
        $extra = $filterCompany > 0 ? ' AND CompanyId=?' : '';
        $params = $serials;
        if ($filterCompany > 0) $params[] = $filterCompany;

        $stmt = $this->db->prepare(
            "SELECT DeviceSerial, EnrollId, CompanyId, EmpCode
             FROM tblDeviceEnrollment WHERE DeviceSerial IN ($ph) $extra"
        );
        $stmt->execute($params);

        $this->enrollMap = [];
        foreach ($stmt->fetchAll() as $e) {
            $this->enrollMap[$e['DeviceSerial'] . ':' . $e['EnrollId']] = [
                'companyId' => (int)$e['CompanyId'],
                'empCode'   => $e['EmpCode'],
            ];
        }

        // Per-company EnrollId → EmpCode fallback from tblEmployee
        $coIds = array_values(array_unique(array_filter(array_column($devices, 'resolved_company_id'))));
        if ($filterCompany > 0) $coIds = [$filterCompany];

        foreach ($coIds as $cid) {
            if (!$cid) continue;
            $es = $this->db->prepare(
                "SELECT EmployeeCode, EnrollId FROM tblEmployee
                 WHERE CompanyId=? AND EnrollId IS NOT NULL AND EnrollId != '' AND Status='active'"
            );
            $es->execute([(int)$cid]);
            foreach ($es->fetchAll() as $e) {
                $this->fallbackMap[(int)$cid][(string)$e['EnrollId']] = $e['EmployeeCode'];
            }
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
}
