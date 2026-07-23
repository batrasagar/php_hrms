<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
blockCompliance(); // raw device punches are not compliance-scoped
requirePermission('report_punchlog.view');
require_once __DIR__ . '/../../includes/punch_source.php';
require_once __DIR__ . '/../../services/AdmsSyncService.php';
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

// ── Optional live sync from the ADMS API before we read the shards ───────────
// Tops up punches made since the last sync cron (all company devices, or the
// selected one) so the report isn't stale. Writes into the same shards we read.
$syncResult = null;
if (!empty($_GET['sync']) && $_GET['sync'] === '1' && $fCompany && can('punch_sync.view')) {
    $syncResult = (new AdmsSyncService($db))->sync($fCompany, $fFrom, $fTo, $fSN);
}

// Employee-code → name for this company, fetched once rather than per punch row.
$codeToName = [];
if ($fCompany) {
    $en = $db->prepare("SELECT EmployeeCode, Name FROM tblEmployee WHERE CompanyId = ?");
    $en->execute([$fCompany]);
    foreach ($en->fetchAll() as $r) $codeToName[(string)$r['EmployeeCode']] = (string)$r['Name'];
}

// ── Gather punches from the local shards and roll them up device → date → punch ─
// Every level's counts (punches / distinct employees) tally EVERY row; the punch
// rows kept for the innermost drilldown are capped per date so a busy month
// doesn't bloat the DOM. Exports still stream the full, uncapped log.
const DATE_CAP = 1000;
$devSummary = [];   // serial => [count, emps(set), first, last, dates => [date => [count, emps, punches[], truncated]]]
$scanned    = false;

if ($fCompany) {
    $fromDt = $fFrom . ' 00:00:00';
    $toDt   = $fTo   . ' 23:59:59';
    // Newest shard first + newest punch first, so the per-date cap keeps recent rows.
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
                $devSummary[$sn] = ['count' => 0, 'emps' => [], 'first' => null, 'last' => null, 'dates' => []];
            }
            $d    = &$devSummary[$sn];
            $code = (string)($r['EmpCode'] ?? '');
            $eid  = (string)($r['EnrollId'] ?? '');
            $pt   = (string)$r['PunchTime'];
            $date = substr($pt, 0, 10);
            $ekey = $code !== '' ? 'c:' . $code : 'e:' . $eid;

            $d['count']++;
            $d['emps'][$ekey] = true;
            if ($d['first'] === null || $pt < $d['first']) $d['first'] = $pt;
            if ($d['last']  === null || $pt > $d['last'])  $d['last']  = $pt;

            if (!isset($d['dates'][$date])) {
                $d['dates'][$date] = ['count' => 0, 'emps' => [], 'punches' => [], 'truncated' => false];
            }
            $da = &$d['dates'][$date];
            $da['count']++;
            $da['emps'][$ekey] = true;
            if (count($da['punches']) < DATE_CAP) {
                $da['punches'][] = [
                    'code' => $code,
                    'name' => $code !== '' ? ($codeToName[$code] ?? '') : '',
                    'eid'  => $eid,
                    'time' => substr($pt, 11, 8),   // HH:MM:SS — the date heads the group
                    'type' => (int)($r['PunchType'] ?? 0),
                ];
            } else {
                $da['truncated'] = true;
            }
            unset($da, $d);
        }
    }
    ksort($devSummary);
    foreach ($devSummary as &$d) krsort($d['dates']); // newest date first within each device
    unset($d);
}

$totalPunches = array_sum(array_column($devSummary, 'count'));
$expArgs      = ['company' => $fCompany, 'sn' => $fSN, 'from' => $fFrom, 'to' => $fTo];
$csvUrl       = 'device_punchlog_export.php?' . http_build_query($expArgs);
$xlsUrl       = 'device_punchlog_export.php?' . http_build_query($expArgs + ['format' => 'xls']);
$syncUrl      = '?' . http_build_query($expArgs + ['sync' => '1']);
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
    <div class="d-flex gap-2">
      <?php if (can('punch_sync.view')): ?>
      <a href="<?= htmlspecialchars($syncUrl) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-arrow-repeat"></i> Sync from API
      </a>
      <?php endif; ?>
      <?php if ($devSummary): ?>
      <a href="<?= htmlspecialchars($xlsUrl) ?>" class="btn btn-sm btn-outline-success">
        <i class="bi bi-file-earmark-excel"></i> Excel
      </a>
      <a href="<?= htmlspecialchars($csvUrl) ?>" class="btn btn-sm btn-outline-success">
        <i class="bi bi-download"></i> CSV
      </a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($syncResult !== null): ?>
  <div class="alert alert-<?= empty($syncResult['errors']) ? 'success' : 'warning' ?> d-flex align-items-start gap-2 rounded-0 mb-0 border-0 border-bottom">
    <i class="bi bi-<?= empty($syncResult['errors']) ? 'check-circle' : 'exclamation-triangle' ?> mt-1"></i>
    <span>
      Sync complete — <strong><?= (int)$syncResult['inserted'] ?></strong> new punch(es) added from
      <?= (int)$syncResult['devices'] ?> device(s).
      <?php if (!empty($syncResult['skipped'])): ?><?= (int)$syncResult['skipped'] ?> skipped (out of range / unmapped).<?php endif; ?>
      <?php foreach ($syncResult['errors'] as $e): ?><br><small class="text-danger"><?= htmlspecialchars($e) ?></small><?php endforeach; ?>
    </span>
  </div>
  <?php endif; ?>

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
        <tr class="drill-row" role="button" data-bs-toggle="collapse" data-bs-target="#<?= $rid ?>"
            aria-expanded="false" style="cursor:pointer">
          <td class="text-muted"><i class="bi bi-chevron-right drill-caret"></i></td>
          <td><code class="small"><?= htmlspecialchars($sn) ?></code></td>
          <td class="text-end"><?= (int)$d['count'] ?></td>
          <td class="text-end"><?= count($d['emps']) ?></td>
          <td class="small"><?= htmlspecialchars(substr((string)$d['first'], 0, 16)) ?></td>
          <td class="small"><?= htmlspecialchars(substr((string)$d['last'], 0, 16)) ?></td>
        </tr>
        <tr>
          <td colspan="6" class="p-0 drill-cell">
            <div class="collapse" id="<?= $rid ?>">
              <div class="ps-4 pe-2 py-2 bg-light-subtle">
                <table class="table table-sm mb-0 align-middle">
                  <thead>
                    <tr class="text-muted small">
                      <th style="width:2rem"></th><th>Date</th>
                      <th class="text-end">Punches</th><th class="text-end">Employees</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php $j = 0; foreach ($d['dates'] as $date => $da): $j++; $did = $rid . '-d-' . $j; ?>
                    <tr class="drill-row" role="button" data-bs-toggle="collapse" data-bs-target="#<?= $did ?>"
                        aria-expanded="false" style="cursor:pointer">
                      <td class="text-muted"><i class="bi bi-chevron-right drill-caret"></i></td>
                      <td><?= htmlspecialchars(date('d-M-Y (D)', strtotime($date))) ?></td>
                      <td class="text-end"><?= (int)$da['count'] ?></td>
                      <td class="text-end"><?= count($da['emps']) ?></td>
                    </tr>
                    <tr>
                      <td colspan="4" class="p-0 drill-cell">
                        <div class="collapse" id="<?= $did ?>">
                          <div class="ps-4 pe-2 py-2 bg-body-secondary">
                            <?php if ($da['truncated']): ?>
                            <div class="small text-muted mb-1">
                              <i class="bi bi-info-circle"></i>
                              Showing the <?= DATE_CAP ?> most recent of <?= (int)$da['count'] ?> punches this day — use Export for the full list.
                            </div>
                            <?php endif; ?>
                            <table class="table table-sm table-borderless mb-0">
                              <thead>
                                <tr class="text-muted small">
                                  <th>Emp Code</th><th>Name</th><th>Enroll ID</th><th>Time</th><th>Type</th>
                                </tr>
                              </thead>
                              <tbody>
                              <?php foreach ($da['punches'] as $l): ?>
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
  .drill-row .drill-caret { transition: transform .15s ease; }
  .drill-row[aria-expanded="true"] .drill-caret { transform: rotate(90deg); }
  /* Collapse-carrier cells: kill the stray line box so a closed row takes no height */
  .drill-cell { line-height: 0; border: 0; }
  .drill-cell .collapse, .drill-cell .collapsing { line-height: normal; }
</style>

<?php
require_once __DIR__ . '/../../includes/footer.php';
