<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requirePermission('report_ot.view');
$pageTitle  = 'OT Report';
$activePage = 'report_ot';
require_once __DIR__ . '/../../includes/header.php';

$db   = getDb();
$user = currentUser();

// Company comes from the global topbar switcher
$fCompany = activeCompanyId($db, $user);

$fFrom    = trim($_GET['from'] ?? date('Y-m-01'));
$fTo      = trim($_GET['to']   ?? date('Y-m-t'));
$fDept    = trim($_GET['dept'] ?? '');
$fContractor = trim($_GET['contractor'] ?? '');

$where  = [];
$params = [];
if ($user['role'] === 'user')          { $where[] = 'ot.CompanyId = ?'; $params[] = $fCompany; }
elseif (in_array($user['role'], ['admin','operator'], true))     { $where[] = 'c.AdminId = ?';    $params[] = $user['scope_id']; }
if ($fCompany && $user['role'] !== 'user') { $where[] = 'ot.CompanyId = ?'; $params[] = $fCompany; }
if ($fDept)       { $where[] = 'e.Department = ?';  $params[] = $fDept; }
if ($fContractor) { $where[] = 'e.Contractor = ?';  $params[] = $fContractor; }
$where[] = 'ot.OTDate >= ?'; $params[] = $fFrom;
$where[] = 'ot.OTDate <= ?'; $params[] = $fTo;
if (isCompliance()) $where[] = 'e.Compliance = 1';   // compliance role → only compliance employees
$wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare(
    "SELECT ot.*, e.EmployeeCode, e.Name AS EmpName, e.Department, e.Contractor,
            c.Name AS CompanyName
     FROM tblOvertime ot
     JOIN tblEmployee e ON e.id = ot.EmployeeId
     JOIN tblCompany c ON c.id = ot.CompanyId
     $wsql ORDER BY ot.OTDate DESC, ISNULL(e.Sr), e.Sr, e.Name"
);
$stmt->execute($params);
$records = $stmt->fetchAll();

$totalHours = array_sum(array_column($records, 'OTHours'));

$scopeJoin   = $user['role'] === 'superadmin' ? '' : ($user['role'] === 'user' ? 'WHERE e.CompanyId=' . (int)$fCompany : 'JOIN tblCompany c ON c.id=e.CompanyId AND c.AdminId=' . $user['scope_id']);
$scopeJoin  .= complianceEmpFilter('e');
$depts       = array_filter(array_column($db->query("SELECT DISTINCT Department FROM tblEmployee e $scopeJoin ORDER BY Department")->fetchAll(), 'Department'));
$contractors = array_filter(array_column($db->query("SELECT DISTINCT Contractor FROM tblEmployee e $scopeJoin ORDER BY Contractor")->fetchAll(), 'Contractor'));
?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="company" value="<?= (int)$fCompany ?>">
      <div class="col-6 col-sm-4 col-md-2"><label class="form-label small mb-1">Department</label>
        <select name="dept" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($depts as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>" <?= $fDept===$d?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-sm-4 col-md-2"><label class="form-label small mb-1">Contractor</label>
        <select name="contractor" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($contractors as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>" <?= $fContractor===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-sm-4 col-md-2"><label class="form-label small mb-1">From</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($fFrom) ?>">
      </div>
      <div class="col-6 col-sm-4 col-md-2"><label class="form-label small mb-1">To</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($fTo) ?>">
      </div>
      <div class="col-12 col-sm-auto d-flex gap-1">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Filter</button>
        <button type="button" class="btn btn-outline-success btn-sm" onclick="window.print()"><i class="bi bi-printer"></i></button>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-4">
    <div class="card text-center p-3">
      <div class="fs-4 fw-bold text-primary"><?= number_format($totalHours, 2) ?></div>
      <div class="text-muted small">Total OT Hours</div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="card text-center p-3">
      <div class="fs-4 fw-bold"><?= count($records) ?></div>
      <div class="text-muted small">OT Entries</div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="card text-center p-3">
      <div class="fs-4 fw-bold"><?= count(array_unique(array_column($records, 'EmployeeId'))) ?></div>
      <div class="text-muted small">Employees with OT</div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold">OT Records — <?= $fFrom ?> to <?= $fTo ?></div>
  <div class="card-body p-0">
    <table class="table table-hover table-sm mb-0" id="tblOT">
      <thead class="table-light">
        <tr><th>Date</th><th>Code</th><th>Name</th><th>Department</th><th>Contractor</th><th>Company</th><th>OT Hours</th><th>Reason</th></tr>
      </thead>
      <tbody>
      <?php foreach ($records as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['OTDate']) ?></td>
        <td><code class="small"><?= htmlspecialchars($r['EmployeeCode'] ?: '—') ?></code></td>
        <td><?= htmlspecialchars($r['EmpName']) ?></td>
        <td class="small"><?= htmlspecialchars($r['Department'] ?? '—') ?></td>
        <td class="small"><?= htmlspecialchars($r['Contractor'] ?? '—') ?></td>
        <td class="small"><?= htmlspecialchars($r['CompanyName']) ?></td>
        <td class="fw-semibold text-primary"><?= number_format($r['OTHours'], 2) ?></td>
        <td class="small"><?= htmlspecialchars($r['Reason'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$extraJs = '<script>$(()=>{$("#tblOT").DataTable({order:[[0,"desc"]],pageLength:50,language:{emptyTable:"No overtime records found for the selected period."}});});</script>';
require_once __DIR__ . '/../../includes/footer.php';
