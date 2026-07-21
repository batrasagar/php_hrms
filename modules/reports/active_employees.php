<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
requirePermission('report_active.view');
$pageTitle  = 'Active Employees Report';
$activePage = 'report_active';
require_once __DIR__ . '/../../includes/header.php';

$db   = getDb();
$user = currentUser();

// Company comes from the global topbar switcher
$fCompany    = activeCompanyId($db, $user);
$fDept       = trim($_GET['dept']        ?? '');
$fContractor = trim($_GET['contractor']  ?? '');
$fStatus     = trim($_GET['status']      ?? 'active');

$where  = [];
$params = [];
if ($user['role'] !== 'superadmin') { $where[] = 'c.AdminId = ?'; $params[] = $user['scope_id']; }
if ($fCompany)    { $where[] = 'e.CompanyId = ?';  $params[] = $fCompany; }
if ($fDept)       { $where[] = 'e.Department = ?'; $params[] = $fDept; }
if ($fContractor) { $where[] = 'e.Contractor = ?'; $params[] = $fContractor; }
if ($fStatus)     { $where[] = 'e.Status = ?';     $params[] = $fStatus; }
if (complianceScoped()) $where[] = 'e.Compliance = 1';   // compliance scope → only compliance employees
$wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare(
    "SELECT e.*, c.Name AS CompanyName FROM tblEmployee e
     JOIN tblCompany c ON c.id=e.CompanyId $wsql ORDER BY c.Name, e.Department, ISNULL(e.Sr), e.Sr, e.Name"
);
$stmt->execute($params);
$employees = $stmt->fetchAll();

$scopeJoin   = $user['role'] === 'superadmin' ? '' : 'JOIN tblCompany c ON c.id=e.CompanyId AND c.AdminId=' . $user['scope_id'];
$scopeJoin  .= complianceEmpFilter('e');
$depts       = employeeFilterValues($db, (int)$fCompany, 'Department');
$contractors = employeeFilterValues($db, (int)$fCompany, 'Contractor');
?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="company" value="<?= (int)$fCompany ?>">
      <div class="col-6 col-sm-4 col-md-2">
        <label class="form-label small mb-1">Department</label>
        <select name="dept" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($depts as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>" <?= $fDept===$d?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-sm-4 col-md-2">
        <label class="form-label small mb-1">Contractor</label>
        <select name="contractor" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($contractors as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>" <?= $fContractor===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-sm-4 col-md-2">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <option value="active"     <?= $fStatus==='active'    ?'selected':'' ?>>Active</option>
          <option value="inactive"   <?= $fStatus==='inactive'  ?'selected':'' ?>>Inactive</option>
          <option value="terminated" <?= $fStatus==='terminated'?'selected':'' ?>>Terminated</option>
        </select>
      </div>
      <div class="col-6 col-sm-auto d-flex gap-1 align-items-end">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Filter</button>
        <a href="active_employees.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        <button type="button" class="btn btn-outline-success btn-sm"
                onclick="excelFromDataTable('#tblActive','active_employees','Active Employees',[0])">
          <i class="bi bi-file-earmark-excel"></i> Excel</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i></button>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold">
    Employee List — <?= count($employees) ?> records
  </div>
  <div class="card-body p-0">
    <table class="table table-hover table-sm align-middle mb-0" id="tblActive">
      <thead class="table-light">
        <tr>
          <th>#</th><th>Code</th><th>Name</th><th>Department</th>
          <th>Contractor</th><th>Designation</th><th>Company</th>
          <th>Join Date</th><th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($employees as $i => $e):
        $badge = match($e['Status']) { 'active'=>'bg-success','inactive'=>'bg-warning text-dark','terminated'=>'bg-danger', default=>'bg-secondary' };
      ?>
      <tr>
        <td class="text-muted"><?= $i+1 ?></td>
        <td><code class="small"><?= htmlspecialchars($e['EmployeeCode'] ?: '—') ?></code></td>
        <td><?= htmlspecialchars($e['Name']) ?></td>
        <td><?= htmlspecialchars($e['Department'] ?? '—') ?></td>
        <td><?= htmlspecialchars($e['Contractor'] ?? '—') ?></td>
        <td><?= htmlspecialchars($e['Designation'] ?? '—') ?></td>
        <td class="small"><?= htmlspecialchars($e['CompanyName']) ?></td>
        <td class="small"><?= $e['JoinDate'] ?: '—' ?></td>
        <td><span class="badge <?= $badge ?>"><?= ucfirst($e['Status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$extraJs = '<script>$(()=>{$("#tblActive").DataTable({order:[[6,"asc"],[3,"asc"],[2,"asc"]],pageLength:50,language:{emptyTable:"No employees match the selected filters."}});});</script>';
require_once __DIR__ . '/../../includes/footer.php';
