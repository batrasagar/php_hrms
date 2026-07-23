<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
blockCompliance(); // raw device punches are not compliance-scoped
requirePermission('report_punchlog.view');
require_once __DIR__ . '/../../includes/punch_source.php';
$pageTitle  = 'Device Punch Log';
$activePage = 'report_punchlog';
require_once __DIR__ . '/../../includes/header.php';

$db   = getDb();
$user = currentUser();

// Company comes from the topbar switcher; ?company= override lets print/deep-links pin it.
$fCompany = activeCompanyId($db, $user);
$fSN      = trim($_GET['sn']   ?? '');
$fFrom    = trim($_GET['from'] ?? date('Y-m-01'));
$fTo      = trim($_GET['to']   ?? date('Y-m-d'));

// Company name (devices link to companies by NAME, and it heads the report).
$companyName = '';
if ($fCompany) {
    $cs = $db->prepare("SELECT Name FROM tblCompany WHERE id = ?");
    $cs->execute([$fCompany]);
    $companyName = (string)($cs->fetchColumn() ?: '');
}

// Devices registered to this company — powers the optional narrowing dropdown.
$devices = [];
if ($companyName !== '') {
    $ds = $db->prepare("SELECT SerialNumber FROM tblDevices WHERE Company = ? ORDER BY SerialNumber");
    $ds->execute([$companyName]);
    $devices = $ds->fetchAll(PDO::FETCH_COLUMN);
}
if ($fSN && $devices && !in_array($fSN, $devices, true)) $fSN = ''; // ignore a serial from another company

// Employee-code → name for this company, fetched once rather than per punch row.
$codeToName = [];
if ($fCompany) {
    $en = $db->prepare("SELECT EmployeeCode, Name FROM tblEmployee WHERE CompanyId = ?");
    $en->execute([$fCompany]);
    foreach ($en->fetchAll() as $r) $codeToName[(string)$r['EmployeeCode']] = (string)$r['Name'];
}

// ── Gather punches from the local shards and roll them up per device ──────────
// Counts (punches / distinct employees / first / last) tally EVERY row; the
// detail list kept for drilldown is capped so a busy month doesn't bloat the DOM.
const DETAIL_CAP = 1500;
$devSummary = [];   // serial => [count, emps(set), first, last, detail[], truncated]
$scanned    = false;

if ($fCompany) {
    $fromDt = $fFrom . ' 00:00:00';
    $toDt   = $fTo   . ' 23:59:59';
    // Newest shard first + newest punch first, so the per-device cap keeps recent rows.
    foreach (array_reverse(punchShardsForRange($fFrom, $fTo)) as $tbl) {
        $sql = "SELECT DeviceSerial, EmpCode, EnrollId, PunchTime, PunchType
                  FROM `$tbl`
                 WHERE CompanyId = ? AND PunchTime BETWEEN ? AND ?";
        $args = [$fCompany, $fromDt, $toDt];
        if ($fSN !== '') { $sql .= " AND DeviceSerial = ?"; $args[] = $fSN; }
        $sql .= " ORDER BY DeviceSerial, PunchTime DESC";
        try {
            $st = $db->prepare($sql);
            $st->execute($args);
        } catch (PDOException $e) { continue; } // shard for that month never created
        $scanned = true;

        foreach ($st->fetchAll() as $r) {
            $sn = (string)$r['DeviceSerial'];
            if (!isset($devSummary[$sn])) {
                $devSummary[$sn] = ['count' => 0, 'emps' => [], 'first' => null, 'last' => null,
                                    'detail' => [], 'truncated' => false];
            }
            $d    = &$devSummary[$sn];
            $code = (string)($r['EmpCode'] ?? '');
            $eid  = (string)($r['EnrollId'] ?? '');
            $pt   = (string)$r['PunchTime'];

            $d['count']++;
            $d['emps'][$code !== '' ? 'c:' . $code : 'e:' . $eid] = true;
            if ($d['first'] === null || $pt < $d['first']) $d['first'] = $pt;
            if ($d['last']  === null || $pt > $d['last'])  $d['last']  = $pt;

            if (count($d['detail']) < DETAIL_CAP) {
                $d['detail'][] = [
                    'code' => $code,
                    'name' => $code !== '' ? ($codeToName[$code] ?? '') : '',
                    'eid'  => $eid,
                    'time' => $pt,
                    'type' => (int)($r['PunchType'] ?? 0),
                ];
            } else {
                $d['truncated'] = true;
            }
            unset($d);
        }
    }
    ksort($devSummary);
}

$totalPunches = array_sum(array_column($devSummary, 'count'));
$exportUrl    = 'device_punchlog_export.php?' . http_build_query(
    ['company' => $fCompany, 'sn' => $fSN, 'from' => $fFrom, 'to' => $fTo]);
?>

<!-- Filter Form -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end" data-filter>
      <input type="hidden" name="company" value="<?= (int)$fCompany ?>">
      <div class="col-sm-6 col-md-3">
        <label class="form-label">Device</label>
        <select name="sn" class="form-select">
          <option value="">— All devices —</option>
          <?php foreach ($devices as $sn): ?>
          <option value="<?= htmlspecialchars($sn) ?>" <?= $fSN===$sn?'selected':'' ?>><?= htmlspecialchars($sn) ?></option>
          <?php endforeach; ?>
        </select>
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
        <a href="device_punchlog.php" class="btn btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<?php if (!$fCompany): ?>
<div class="alert alert-warning" data-no-toast>
  <i class="bi bi-exclamation-triangle me-2"></i>No company selected. Pick a company from the top bar.
</div>
<?php else: ?>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="fw-semibold">
      <?= htmlspecialchars($companyName ?: ('Company #' . $fCompany)) ?>
      <span class="text-muted small ms-2"><?= htmlspecialchars($fFrom) ?> → <?= htmlspecialchars($fTo) ?></span>
      <span class="badge bg-secondary ms-2"><?= count($devSummary) ?> device<?= count($devSummary)===1?'':'s' ?></span>
      <span class="badge bg-primary-subtle text-primary"><?= (int)$totalPunches ?> punches</span>
    </span>
    <?php if ($devSummary): ?>
    <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-sm btn-outline-success">
      <i class="bi bi-download"></i> Export CSV
    </a>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <?php if (empty($devSummary)): ?>
    <div class="p-4 text-center text-muted">
      <i class="bi bi-inbox fs-3 d-block mb-2"></i>
      No punch records for this company in the selected period.
    </div>
    <?php else: ?>
    <table class="table table-hover table-sm mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:2rem"></th>
          <th>Device</th>
          <th class="text-end">Punches</th>
          <th class="text-end">Employees</th>
          <th>First Punch</th>
          <th>Last Punch</th>
        </tr>
      </thead>
      <tbody>
      <?php $i = 0; foreach ($devSummary as $sn => $d): $i++; $rid = 'dev-' . $i; ?>
        <tr class="dev-row" role="button" data-bs-toggle="collapse" data-bs-target="#<?= $rid ?>"
            aria-expanded="false" style="cursor:pointer">
          <td class="text-muted"><i class="bi bi-chevron-right dev-caret"></i></td>
          <td><code class="small"><?= htmlspecialchars($sn) ?></code></td>
          <td class="text-end"><?= (int)$d['count'] ?></td>
          <td class="text-end"><?= count($d['emps']) ?></td>
          <td class="small"><?= htmlspecialchars(substr((string)$d['first'], 0, 16)) ?></td>
          <td class="small"><?= htmlspecialchars(substr((string)$d['last'], 0, 16)) ?></td>
        </tr>
        <tr class="dev-detail-row">
          <td colspan="6" class="p-0">
            <div class="collapse" id="<?= $rid ?>">
              <div class="p-2 bg-light-subtle">
                <?php if ($d['truncated']): ?>
                <div class="small text-muted mb-1">
                  <i class="bi bi-info-circle"></i>
                  Showing the <?= DETAIL_CAP ?> most recent of <?= (int)$d['count'] ?> punches — use Export CSV for the full list.
                </div>
                <?php endif; ?>
                <table class="table table-sm table-borderless mb-0">
                  <thead>
                    <tr class="text-muted small">
                      <th>Emp Code</th><th>Name</th><th>Enroll ID</th><th>Punch Time</th><th>Type</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($d['detail'] as $l): ?>
                    <tr>
                      <td><?= htmlspecialchars($l['code'] ?: '—') ?></td>
                      <td><?= $l['name'] ? htmlspecialchars($l['name']) : '<span class="text-muted">—</span>' ?></td>
                      <td><?= htmlspecialchars($l['eid'] ?: '—') ?></td>
                      <td class="small"><?= htmlspecialchars($l['time']) ?></td>
                      <td>
                        <?php if ($l['type'] === 1): ?>
                          <span class="badge bg-success-subtle text-success">In</span>
                        <?php elseif ($l['type'] === 2): ?>
                          <span class="badge bg-danger-subtle text-danger">Out</span>
                        <?php else: ?>
                          <span class="text-muted small">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<style>
  .dev-row .dev-caret { transition: transform .15s ease; }
  .dev-row[aria-expanded="true"] .dev-caret { transform: rotate(90deg); }
</style>

<?php
require_once __DIR__ . '/../../includes/footer.php';
