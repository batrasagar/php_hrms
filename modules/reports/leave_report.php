<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
$pageTitle  = 'Leave Report';
$activePage = 'report_leave';
require_once __DIR__ . '/../../includes/header.php';

$db   = getDb();
$user = currentUser();

if ($user['role'] === 'user') {
    $companiesDd = [];
    $fCompany    = $user['company_id'];
} elseif ($user['role'] === 'superadmin') {
    $companiesDd = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
    $fCompany    = (int)($_GET['company'] ?? 0);
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['id']]);
    $companiesDd = $stmt->fetchAll();
    $fCompany    = (int)($_GET['company'] ?? 0);
}

$fFrom       = trim($_GET['from']        ?? date('Y-m-01'));
$fTo         = trim($_GET['to']          ?? date('Y-m-t'));
$fDept       = trim($_GET['dept']        ?? '');
$fContractor = trim($_GET['contractor']  ?? '');

$where  = [];
$params = [];
if ($user['role'] === 'user')          { $where[] = 'lv.CompanyId = ?'; $params[] = $fCompany; }
elseif ($user['role'] === 'admin')     { $where[] = 'c.AdminId = ?';    $params[] = $user['id']; }
if ($fCompany && $user['role'] !== 'user') { $where[] = 'lv.CompanyId = ?'; $params[] = $fCompany; }
if ($fDept)       { $where[] = 'e.Department = ?'; $params[] = $fDept; }
if ($fContractor) { $where[] = 'e.Contractor = ?'; $params[] = $fContractor; }
$where[] = 'lv.LeaveDate >= ?'; $params[] = $fFrom;
$where[] = 'lv.LeaveDate <= ?'; $params[] = $fTo;
$wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare(
    "SELECT lv.*, e.EmployeeCode, e.Name AS EmpName, e.Department, e.Contractor,
            c.Name AS CompanyName
     FROM tblLeave lv
     JOIN tblEmployee e ON e.id = lv.EmployeeId
     JOIN tblCompany c ON c.id = lv.CompanyId
     $wsql ORDER BY lv.LeaveDate DESC, ISNULL(e.Sr), e.Sr, e.Name"
);
$stmt->execute($params);
$records = $stmt->fetchAll();

$fullDay  = count(array_filter($records, fn($r) => $r['LeaveType']==='full_day'));
$halfAm   = count(array_filter($records, fn($r) => $r['LeaveType']==='half_am'));
$halfPm   = count(array_filter($records, fn($r) => $r['LeaveType']==='half_pm'));

$scopeJoin   = $user['role'] === 'superadmin' ? '' : 'JOIN tblCompany c ON c.id=e.CompanyId AND c.AdminId=' . $user['id'];
$depts       = array_filter(array_column($db->query("SELECT DISTINCT Department FROM tblEmployee e $scopeJoin ORDER BY Department")->fetchAll(), 'Department'));
$contractors = array_filter(array_column($db->query("SELECT DISTINCT Contractor FROM tblEmployee e $scopeJoin ORDER BY Contractor")->fetchAll(), 'Contractor'));
$typeLabels  = ['full_day' => 'Full Day', 'half_am' => 'Half AM', 'half_pm' => 'Half PM'];
$typeBadges  = ['full_day' => 'bg-danger', 'half_am' => 'bg-warning text-dark', 'half_pm' => 'bg-warning text-dark'];
?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <?php if ($user['role'] !== 'user'): ?>
      <div class="col-12 col-sm-6 col-md-3"><label class="form-label small mb-1">Company</label>
        <select name="company" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($companiesDd as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?><input type="hidden" name="company" value="<?= $fCompany ?>"><?php endif; ?>
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
  <div class="col-6 col-md-3">
    <div class="card text-center p-3">
      <div class="fs-4 fw-bold text-danger"><?= $fullDay ?></div>
      <div class="text-muted small">Full Day Leaves</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center p-3">
      <div class="fs-4 fw-bold text-warning"><?= $halfAm + $halfPm ?></div>
      <div class="text-muted small">Half Day Leaves</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center p-3">
      <div class="fs-4 fw-bold"><?= count($records) ?></div>
      <div class="text-muted small">Total Entries</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center p-3">
      <div class="fs-4 fw-bold"><?= count(array_unique(array_column($records, 'EmployeeId'))) ?></div>
      <div class="text-muted small">Employees on Leave</div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold">Leave Records — <?= $fFrom ?> to <?= $fTo ?></div>
  <div class="card-body p-0">
    <table class="table table-hover table-sm mb-0" id="tblLeaves">
      <thead class="table-light">
        <tr><th>Date</th><th>Day</th><th>Code</th><th>Name</th><th>Department</th><th>Contractor</th><th>Company</th><th>Type</th><th>Reason</th></tr>
      </thead>
      <tbody>
      <?php foreach ($records as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['LeaveDate']) ?></td>
        <td class="small text-muted"><?= date('D', strtotime($r['LeaveDate'])) ?></td>
        <td><code class="small"><?= htmlspecialchars($r['EmployeeCode'] ?: '—') ?></code></td>
        <td><?= htmlspecialchars($r['EmpName']) ?></td>
        <td class="small"><?= htmlspecialchars($r['Department'] ?? '—') ?></td>
        <td class="small"><?= htmlspecialchars($r['Contractor'] ?? '—') ?></td>
        <td class="small"><?= htmlspecialchars($r['CompanyName']) ?></td>
        <td><span class="badge <?= $typeBadges[$r['LeaveType']] ?? 'bg-secondary' ?>"><?= $typeLabels[$r['LeaveType']] ?? $r['LeaveType'] ?></span></td>
        <td class="small"><?= htmlspecialchars($r['Reason'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$extraJs = '<script>$(()=>{$("#tblLeaves").DataTable({order:[[0,"desc"]],pageLength:50,language:{emptyTable:"No leave records found for the selected period."}});});</script>';
require_once __DIR__ . '/../../includes/footer.php';
