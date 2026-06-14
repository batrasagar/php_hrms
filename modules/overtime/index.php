<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();
$msg  = '';

if ($user['role'] === 'superadmin') {
    $companiesDd = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['id']]);
    $companiesDd = $stmt->fetchAll();
}

$fCompany = (int)($_GET['company'] ?? ($companiesDd[0]['id'] ?? 0));
$fDate    = trim($_GET['date'] ?? date('Y-m-d'));

// Delete record
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $db->prepare("DELETE FROM tblOvertime WHERE id=? AND CompanyId IN (SELECT id FROM tblCompany WHERE " .
        ($user['role'] === 'superadmin' ? '1' : 'AdminId=' . $user['id']) . ")")->execute([$did]);
    header("Location: index.php?company=$fCompany&date=" . urlencode($fDate)); exit;
}

// Save bulk OT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emp_ids'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $companyId = (int)($_POST['company_id'] ?? 0);
    $otDate    = trim($_POST['ot_date'] ?? '');

    if ($user['role'] !== 'superadmin') {
        $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $chk->execute([$companyId, $user['id']]);
        if (!$chk->fetch()) { header('Location: index.php'); exit; }
    }

    $ids     = $_POST['emp_ids']  ?? [];
    $hours   = $_POST['ot_hours'] ?? [];
    $reasons = $_POST['reasons']  ?? [];
    $saved   = 0;

    foreach ($ids as $i => $eid) {
        $eid  = (int)$eid;
        $hrs  = (float)($hours[$i] ?? 0);
        $rsn  = trim($reasons[$i] ?? '');
        if (!$eid) continue;

        if ($hrs > 0) {
            $db->prepare(
                "INSERT INTO tblOvertime (CompanyId, EmployeeId, OTDate, OTHours, Reason, CreatedBy)
                 VALUES (?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE OTHours=?, Reason=?"
            )->execute([$companyId, $eid, $otDate, $hrs, $rsn ?: null, $user['id'], $hrs, $rsn ?: null]);
            $saved++;
        } else {
            // 0 hours = delete if exists
            $db->prepare("DELETE FROM tblOvertime WHERE EmployeeId=? AND OTDate=?")->execute([$eid, $otDate]);
        }
    }
    $successMsg = "Saved overtime for $saved employee(s).";
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$successMsg]); exit; }
    header("Location: index.php?company=$companyId&date=" . urlencode($otDate) . "&saved=$saved");
    exit;
}
if (isset($_GET['saved'])) $msg = 'Saved overtime for ' . (int)$_GET['saved'] . ' employee(s).';

// Load employees
$employees = [];
if ($fCompany) {
    if ($user['role'] !== 'superadmin') {
        $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $chk->execute([$fCompany, $user['id']]);
        if (!$chk->fetch()) $fCompany = 0;
    }
    if ($fCompany) {
        $stmt = $db->prepare(
            "SELECT e.id, e.EmployeeCode, e.Name, e.Department,
                    ot.id AS otId, ot.OTHours, ot.Reason
             FROM tblEmployee e
             LEFT JOIN tblOvertime ot ON ot.EmployeeId = e.id AND ot.OTDate = ?
             WHERE e.CompanyId = ? AND e.Status = 'active'
             ORDER BY e.Department, e.Name"
        );
        $stmt->execute([$fDate, $fCompany]);
        $employees = $stmt->fetchAll();
    }
}
$pageTitle  = 'Overtime Entry';
$activePage = 'overtime';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-success py-2"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end" data-filter>
      <div class="col-sm-4">
        <label class="form-label small mb-1">Company</label>
        <select name="company" class="form-select form-select-sm" onchange="$(this.form).trigger('submit')">
          <option value="">— Select Company —</option>
          <?php foreach ($companiesDd as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-3">
        <label class="form-label small mb-1">Date</label>
        <input type="date" name="date" class="form-control form-control-sm"
               value="<?= htmlspecialchars($fDate) ?>" onchange="$(this.form).trigger('submit')">
      </div>
    </form>
  </div>
</div>

<div id="filter-results">
<?php if ($fCompany && !empty($employees)): ?>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">OT Entry — <?= htmlspecialchars($fDate) ?> <small class="text-muted">(enter 0 to remove)</small></span>
    <button form="otForm" type="submit" class="btn btn-success btn-sm"><i class="bi bi-save me-1"></i>Save OT</button>
  </div>
  <div class="card-body p-0">
    <form id="otForm" method="POST" data-ajax>
      <input type="hidden" name="company_id" value="<?= $fCompany ?>">
      <input type="hidden" name="ot_date" value="<?= htmlspecialchars($fDate) ?>">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Code</th><th>Name</th><th>Department</th>
            <th style="width:110px">OT Hours</th>
            <th>Reason</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($employees as $e): ?>
        <tr class="<?= $e['OTHours'] > 0 ? 'table-warning' : '' ?>">
          <td><code class="small"><?= htmlspecialchars($e['EmployeeCode'] ?: '—') ?></code></td>
          <td><?= htmlspecialchars($e['Name']) ?></td>
          <td class="small text-muted"><?= htmlspecialchars($e['Department'] ?? '—') ?></td>
          <input type="hidden" name="emp_ids[]" value="<?= $e['id'] ?>">
          <td>
            <input type="number" name="ot_hours[]" step="0.25" min="0" max="24"
                   class="form-control form-control-sm"
                   value="<?= number_format((float)($e['OTHours'] ?? 0), 2) ?>">
          </td>
          <td>
            <input type="text" name="reasons[]" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($e['Reason'] ?? '') ?>" placeholder="Optional">
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </form>
  </div>
</div>
<?php elseif ($fCompany): ?>
<div class="alert alert-info">No active employees found for the selected company.</div>
<?php else: ?>
<div class="alert alert-info">Select a company and date to start entering overtime.</div>
<?php endif; ?>
</div><!-- /#filter-results -->

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
