<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
blockCompliance(); // compliance role has no access to the punch log
requirePermission('punchlog.view');
require_once __DIR__ . '/../../includes/punch_source.php';
require_once __DIR__ . '/../../services/ShardManager.php';
require_once __DIR__ . '/../../services/AdmsSyncService.php';
$pageTitle  = 'Punch Log';
$activePage = 'punchlog';
require_once __DIR__ . '/../../includes/header.php';

$db = getDb();

$fSN   = trim($_GET['sn']   ?? '');
$fEId  = trim($_GET['eid']  ?? '');
$fFrom = trim($_GET['from'] ?? date('Y-m-d', strtotime('-7 days')));
$fTo   = trim($_GET['to']   ?? date('Y-m-d'));
$doSync = !empty($_GET['sync']) && $_GET['sync'] === '1';

// ── Scope devices to this user ────────────────────────────────────────────────
if ($user['role'] === 'superadmin') {
    $devices = $db->query(
        "SELECT SerialNumber, Company, LastSyncedAt FROM tblDevices ORDER BY Company, SerialNumber"
    )->fetchAll();
} else {
    $adminId    = (in_array($user['role'], ['admin','operator'], true)) ? $user['scope_id'] : ($user['parent_admin_id'] ?? null);
    $devices    = [];
    if ($adminId) {
        $nameStmt = $db->prepare("SELECT Name FROM tblCompany WHERE AdminId=? AND IsActive=1");
        $nameStmt->execute([$adminId]);
        $companyNames = $nameStmt->fetchAll(PDO::FETCH_COLUMN);
        if ($companyNames) {
            $ph   = implode(',', array_fill(0, count($companyNames), '?'));
            $stmt = $db->prepare(
                "SELECT SerialNumber, Company, LastSyncedAt FROM tblDevices
                 WHERE Company IN ($ph) ORDER BY Company, SerialNumber"
            );
            $stmt->execute($companyNames);
            $devices = $stmt->fetchAll();
        }
    }
}

$accessibleSNs = array_column($devices, 'SerialNumber');
if ($fSN && !in_array($fSN, $accessibleSNs, true)) $fSN = '';

// ── Get LastSyncedAt for selected device ──────────────────────────────────────
$lastSyncedAt = null;
if ($fSN) {
    foreach ($devices as $d) {
        if ($d['SerialNumber'] === $fSN) { $lastSyncedAt = $d['LastSyncedAt'] ?? null; break; }
    }
}

// ── Sync from ADMS API if requested ──────────────────────────────────────────
$syncResult = null;
if ($doSync && $fSN) {
    $syncSvc    = new AdmsSyncService($db);
    $syncResult = $syncSvc->sync(0, $fFrom, $fTo, $fSN);
    // Re-read LastSyncedAt after sync
    $row = $db->prepare("SELECT LastSyncedAt FROM tblDevices WHERE SerialNumber=?");
    $row->execute([$fSN]);
    $lastSyncedAt = $row->fetchColumn() ?: null;
}

// ── Read punch logs directly from the ADMS API (same source as CSV export) ────
// This shows raw device punches regardless of whether the enroll IDs have been
// mapped to employees; where a mapping exists, the row is enriched with the
// employee name/code, otherwise those columns are left blank.
$logs       = [];
$fetched    = false;
$fetchError = null;
if ($fSN) {
    $cred = null;
    try {
        $cred = $db->query("SELECT * FROM tblAdmsCredentials WHERE IsActive=1 ORDER BY id LIMIT 1")->fetch();
    } catch (Exception $e) { /* handled below */ }

    if (!$cred) {
        $fetchError = 'No active ADMS credential configured.';
    } else {
        $url = rtrim($cred['Endpoint'], '/') . '/api/punchlog.php?SerialNumber=' . urlencode($fSN);
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

        if ($curlErr) {
            $fetchError = $curlErr;
        } elseif ($httpCode !== 200) {
            $fetchError = "ADMS API returned HTTP {$httpCode}.";
        } else {
            $data = json_decode($response, true);
            if (!$data || empty($data['success'])) {
                $fetchError = 'ADMS API error: ' . ($data['error'] ?? $data['message'] ?? 'Unknown');
            } else {
                $fromDt = $fFrom . ' 00:00:00';
                $toDt   = $fTo   . ' 23:59:59';

                // Enrollment map for this device → enrich rows with employee where known
                $enrollMap = [];
                $em = $db->prepare(
                    "SELECT de.EnrollId, de.EmpCode, e.Name AS EmpName
                     FROM tblDeviceEnrollment de
                     LEFT JOIN tblEmployee e ON e.CompanyId = de.CompanyId AND e.EmployeeCode = de.EmpCode
                     WHERE de.DeviceSerial = ?"
                );
                $em->execute([$fSN]);
                foreach ($em->fetchAll() as $r) {
                    $enrollMap[(string)$r['EnrollId']] = ['EmpCode' => $r['EmpCode'], 'EmpName' => $r['EmpName']];
                }

                $modeToType = fn($mode) => match (strtolower((string)$mode)) {
                    '0', 'in',  'checkin'  => 1,
                    '1', 'out', 'checkout' => 2,
                    default                => 0,
                };

                foreach ($data['data'] ?? [] as $r) {
                    $pdt = $r['PunchDateTime'] ?? '';
                    if ($pdt < $fromDt || $pdt > $toDt) continue;
                    $eid = (string)($r['EnrollId'] ?? '');
                    if ($fEId !== '' && $eid !== $fEId) continue;

                    $map = $enrollMap[$eid] ?? null;
                    $logs[] = [
                        'DeviceSerial' => $r['SerialNumber'] ?? $fSN,
                        'EnrollId'     => $eid,
                        'EmpCode'      => $map['EmpCode'] ?? '',
                        'EmpName'      => $map['EmpName'] ?? '',
                        'PunchTime'    => $pdt,
                        'PunchType'    => $modeToType($r['Mode'] ?? ''),
                    ];
                }

                $fetched = true;
            }
        }
    }

    // ── Local shards ─────────────────────────────────────────────────────────
    // Punches bulk-imported from a legacy system exist only in tblPunchLog_YYMM —
    // the ADMS API knows nothing about them (it 404s for those serials), so without
    // this the page is blank for a migrated tenant even though the data is present.
    // Rows already returned by ADMS are skipped, keyed on enroll id + timestamp.
    $seen = [];
    foreach ($logs as $l) $seen[$l['EnrollId'] . '|' . $l['PunchTime']] = true;

    // Code → name for the device's company, fetched once rather than per punch row.
    $codeToName = [];
    $cn = $db->prepare(
        "SELECT e.EmployeeCode, e.Name FROM tblEmployee e
           JOIN tblCompany c ON c.Name = (SELECT Company FROM tblDevices WHERE SerialNumber = ? LIMIT 1)
          WHERE e.CompanyId = c.id"
    );
    try {
        $cn->execute([$fSN]);
        foreach ($cn->fetchAll() as $r) $codeToName[(string)$r['EmployeeCode']] = (string)$r['Name'];
    } catch (PDOException $e) { /* leave names blank */ }

    $shardEnroll = [];
    $em2 = $db->prepare(
        "SELECT de.EnrollId, de.EmpCode, e.Name AS EmpName
           FROM tblDeviceEnrollment de
           LEFT JOIN tblEmployee e ON e.CompanyId = de.CompanyId AND e.EmployeeCode = de.EmpCode
          WHERE de.DeviceSerial = ?"
    );
    $em2->execute([$fSN]);
    foreach ($em2->fetchAll() as $r) {
        $shardEnroll[(string)$r['EnrollId']] = ['EmpCode' => $r['EmpCode'], 'EmpName' => $r['EmpName']];
    }

    foreach (punchShardsForRange($fFrom, $fTo) as $tbl) {
        try {
            $ls = $db->prepare(
                "SELECT EmpCode, EnrollId, PunchTime, PunchType
                   FROM `$tbl`
                  WHERE DeviceSerial = ? AND PunchTime BETWEEN ? AND ?
                  ORDER BY PunchTime DESC"
            );
            $ls->execute([$fSN, $fFrom . ' 00:00:00', $fTo . ' 23:59:59']);
        } catch (PDOException $e) { continue; }     // shard for that month never created

        foreach ($ls->fetchAll() as $r) {
            $eid = (string)($r['EnrollId'] ?? '');
            if ($fEId !== '' && $eid !== $fEId) continue;
            $key = $eid . '|' . $r['PunchTime'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            // The import resolved EmpCode directly, so prefer it over the enroll map.
            $map = $shardEnroll[$eid] ?? null;
            $code = $r['EmpCode'] ?: ($map['EmpCode'] ?? '');
            $name = $map['EmpName'] ?? ($codeToName[$code] ?? '');
            $logs[] = [
                'DeviceSerial' => $fSN,
                'EnrollId'     => $eid,
                'EmpCode'      => $code,
                'EmpName'      => $name,
                'PunchTime'    => $r['PunchTime'],
                'PunchType'    => (int)($r['PunchType'] ?? 0),
            ];
            $fetched = true;
        }
    }

    usort($logs, fn($a, $b) => strcmp($b['PunchTime'], $a['PunchTime']));
    $logs = array_slice($logs, 0, 5000);
    // Local rows answered the query, so an ADMS transport error is no longer fatal.
    if ($logs && $fetchError) $fetchError = null;
}

$punchTypeLabel = [0 => '—', 1 => 'In', 2 => 'Out'];

// Build sync URL preserving current filters
$syncUrl = '?' . http_build_query(['sn' => $fSN, 'eid' => $fEId, 'from' => $fFrom, 'to' => $fTo, 'sync' => '1']);
?>

<!-- Filter Form -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end" data-filter>
      <div class="col-sm-6 col-md-3">
        <label class="form-label">Device <span class="text-danger">*</span></label>
        <select name="sn" class="form-select" required>
          <option value="">— Select Device —</option>
          <?php foreach ($devices as $d): ?>
          <option value="<?= htmlspecialchars($d['SerialNumber']) ?>" <?= $fSN===$d['SerialNumber']?'selected':'' ?>>
            <?= htmlspecialchars($d['Company'] ?: $d['SerialNumber']) ?> — <?= htmlspecialchars($d['SerialNumber']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-6 col-md-2">
        <label class="form-label">Enroll ID</label>
        <input type="text" name="eid" class="form-control" value="<?= htmlspecialchars($fEId) ?>" placeholder="Any">
      </div>
      <div class="col-sm-6 col-md-2">
        <label class="form-label">From Date</label>
        <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($fFrom) ?>">
      </div>
      <div class="col-sm-6 col-md-2">
        <label class="form-label">To Date</label>
        <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($fTo) ?>">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
        <a href="view.php" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div id="filter-results">
<?php if (empty($devices)): ?>
<div class="alert alert-warning" data-no-toast>
  <i class="bi bi-exclamation-triangle me-2"></i>
  No devices found. <a href="<?= BASE_URL ?>/modules/devices/add.php" class="alert-link">Add a device</a> first.
</div>
<?php elseif (!$fSN): ?>
<div class="alert alert-info mb-0">
  <i class="bi bi-info-circle me-2"></i>Select a device and click <strong>Filter</strong> to view punch records.
</div>
<?php else: ?>

<?php if ($fetchError): ?>
<div class="alert alert-danger" data-no-toast>
  <i class="bi bi-exclamation-circle me-2"></i>
  <strong>Database error:</strong> <?= htmlspecialchars($fetchError) ?>
</div>
<?php endif; ?>
<?php if ($syncResult !== null): ?>
<div class="alert alert-<?= empty($syncResult['errors']) ? 'success' : 'warning' ?> d-flex align-items-center gap-2 mb-3">
  <i class="bi bi-<?= empty($syncResult['errors']) ? 'check-circle' : 'exclamation-triangle' ?>"></i>
  <span>
    Sync complete — <strong><?= $syncResult['inserted'] ?></strong> new punch(es) added from ADMS API.
    <?php if ($syncResult['skipped']): ?><?= $syncResult['skipped'] ?> outside date range skipped.<?php endif; ?>
    <?php foreach ($syncResult['errors'] as $e): ?><br><small class="text-danger"><?= htmlspecialchars($e) ?></small><?php endforeach; ?>
  </span>
</div>
<?php endif; ?>

<!-- Results -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="fw-semibold">
      Results <span class="badge bg-secondary"><?= count($logs) ?></span>
      <?php if ($lastSyncedAt): ?>
        <span class="text-muted small ms-2"><i class="bi bi-clock-history"></i> Last synced: <?= htmlspecialchars($lastSyncedAt) ?></span>
      <?php else: ?>
        <span class="text-muted small ms-2"><i class="bi bi-clock-history"></i> Never synced</span>
      <?php endif; ?>
    </span>
    <div class="d-flex gap-2">
      <a href="<?= htmlspecialchars($syncUrl) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-arrow-repeat"></i> Sync from API
      </a>
      <a href="export.php?<?= htmlspecialchars(http_build_query(['sn' => $fSN, 'eid' => $fEId, 'from' => $fFrom, 'to' => $fTo])) ?>"
         class="btn btn-sm btn-outline-success"><i class="bi bi-download"></i> Export CSV</a>
    </div>
  </div>
  <div class="card-body p-0">
    <?php if (empty($logs)): ?>
    <div class="p-4 text-center text-muted">
      <i class="bi bi-inbox fs-3 d-block mb-2"></i>
      No punch records for this period on this device.
    </div>
    <?php else: ?>
    <table class="table table-hover table-sm mb-0" id="tblLog">
      <thead class="table-light">
        <tr>
          <th>Device</th>
          <th>Enroll ID</th>
          <th>Emp Code</th>
          <th>Name</th>
          <th>Punch Time</th>
          <th>Type</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><code class="small"><?= htmlspecialchars($l['DeviceSerial']) ?></code></td>
          <td><?= htmlspecialchars($l['EnrollId']) ?></td>
          <td><?= htmlspecialchars($l['EmpCode'] ?: '—') ?></td>
          <td><?= $l['EmpName'] ? htmlspecialchars($l['EmpName']) : '<span class="text-muted">—</span>' ?></td>
          <td><?= htmlspecialchars($l['PunchTime']) ?></td>
          <td>
            <?php $pt = (int)$l['PunchType']; ?>
            <?php if ($pt === 1): ?>
              <span class="badge bg-success-subtle text-success">In</span>
            <?php elseif ($pt === 2): ?>
              <span class="badge bg-danger-subtle text-danger">Out</span>
            <?php else: ?>
              <span class="text-muted small">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php if ($fSN && $fetched && !empty($logs)): ?>
<script>$(()=>{$("#tblLog").DataTable({order:[[4,"desc"]],pageLength:25,language:{emptyTable:"No records found."}});});</script>
<?php endif; ?>
<?php endif; ?>
</div><!-- /#filter-results -->

<?php
require_once __DIR__ . '/../../includes/footer.php';
