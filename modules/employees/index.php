<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db   = getDb();
$user = currentUser();
$msg  = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

if (isset($_GET['delete']) && in_array($user['role'], ['superadmin','admin','operator'], true)) {
    $id = (int)$_GET['delete'];
    if ($user['role'] === 'superadmin') {
        $db->prepare("DELETE FROM tblEmployee WHERE id=?")->execute([$id]);
    } else {
        $db->prepare(
            "DELETE e FROM tblEmployee e
             JOIN tblCompany c ON c.id = e.CompanyId AND c.AdminId = ?
             WHERE e.id = ?"
        )->execute([$user['scope_id'], $id]);
    }
    $_SESSION['flash'] = 'Employee deleted.';
    header('Location: index.php?' . http_build_query(array_filter($_GET, fn($k) => $k !== 'delete', ARRAY_FILTER_USE_KEY)));
    exit;
}

if (isset($_GET['toggle']) && in_array($user['role'], ['superadmin','admin','operator'], true)) {
    $id = (int)$_GET['toggle'];
    if ($user['role'] === 'superadmin') {
        $db->prepare("UPDATE tblEmployee SET Status = CASE WHEN Status='active' THEN 'inactive' ELSE 'active' END WHERE id=?")
           ->execute([$id]);
    } else {
        $db->prepare(
            "UPDATE tblEmployee e
             JOIN tblCompany c ON c.id = e.CompanyId AND c.AdminId = ?
             SET e.Status = CASE WHEN e.Status='active' THEN 'inactive' ELSE 'active' END
             WHERE e.id = ?"
        )->execute([$user['scope_id'], $id]);
    }
    header('Location: index.php?' . http_build_query(array_filter($_GET, fn($k) => $k !== 'toggle', ARRAY_FILTER_USE_KEY)));
    exit;
}

$fDept       = trim($_GET['dept']        ?? '');
$fContractor = trim($_GET['contractor']  ?? '');
$fStatus     = trim($_GET['status']      ?? '');
$fSearch     = trim($_GET['q']           ?? '');

if ($user['role'] === 'user') {
    $companiesDd = [];
    $fCompany    = $user['company_id'];
} elseif ($user['role'] === 'superadmin') {
    $companiesDd = $db->query(
        "SELECT c.id, c.Name, u.Name AS AdminName FROM tblCompany c
         JOIN tblUser u ON u.id = c.AdminId WHERE c.IsActive=1 ORDER BY u.Name, c.Name"
    )->fetchAll();
    $fCompany = (int)($_GET['company'] ?? 0);
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['scope_id']]);
    $companiesDd = $stmt->fetchAll();
    $fCompany = (int)($_GET['company'] ?? 0);
}

$where  = [];
$params = [];

if ($user['role'] === 'user')         { $where[] = 'e.CompanyId = ?'; $params[] = $fCompany; }
elseif ($user['role'] !== 'superadmin') { $where[] = 'c.AdminId = ?'; $params[] = $user['scope_id']; }
if ($fCompany && $user['role'] !== 'user') { $where[] = 'e.CompanyId = ?'; $params[] = $fCompany; }
if ($fDept)       { $where[] = 'e.Department = ?';   $params[] = $fDept; }
if ($fContractor) { $where[] = 'e.Contractor = ?';   $params[] = $fContractor; }
if ($fStatus)     { $where[] = 'e.Status = ?';       $params[] = $fStatus; }
if ($fSearch) {
    $where[]  = '(e.Name LIKE ? OR e.EmployeeCode LIKE ? OR e.EnrollId LIKE ?)';
    $params[] = "%$fSearch%"; $params[] = "%$fSearch%"; $params[] = "%$fSearch%";
}
if (isCompliance()) { $where[] = 'e.Compliance = 1'; }   // compliance role sees only compliance employees

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$sql = "SELECT e.*, c.Name AS CompanyName FROM tblEmployee e
        JOIN tblCompany c ON c.id = e.CompanyId $whereSQL ORDER BY c.Name, ISNULL(e.Sr), e.Sr, e.Name";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

if ($user['role'] === 'user') {
    $scopeJoin = 'WHERE e.CompanyId = ' . (int)$fCompany;
    $depts       = array_filter(array_column($db->query("SELECT DISTINCT Department FROM tblEmployee e $scopeJoin ORDER BY Department")->fetchAll(), 'Department'));
    $contractors = array_filter(array_column($db->query("SELECT DISTINCT Contractor FROM tblEmployee e $scopeJoin ORDER BY Contractor")->fetchAll(), 'Contractor'));
} else {
    $scopeJoin   = $user['role'] === 'superadmin' ? '' : 'JOIN tblCompany c ON c.id = e.CompanyId AND c.AdminId = ' . $user['scope_id'];
    $depts       = array_filter(array_column($db->query("SELECT DISTINCT Department FROM tblEmployee e $scopeJoin ORDER BY Department")->fetchAll(), 'Department'));
    $contractors = array_filter(array_column($db->query("SELECT DISTINCT Contractor FROM tblEmployee e $scopeJoin ORDER BY Contractor")->fetchAll(), 'Contractor'));
}
$pageTitle  = 'Employees';
$activePage = 'employees';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end" data-filter>
      <?php if (!in_array($user['role'], ['user','compliance'], true)): ?>
      <div class="col-sm-6 col-md-2">
        <label class="form-label small mb-1">Company</label>
        <select name="company" class="form-select form-select-sm">
          <option value="">All Companies</option>
          <?php foreach ($companiesDd as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>>
            <?= htmlspecialchars($c['Name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?>
      <input type="hidden" name="company" value="<?= $fCompany ?>">
      <?php endif; ?>
      <div class="col-sm-6 col-md-2">
        <label class="form-label small mb-1">Department</label>
        <select name="dept" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($depts as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>" <?= $fDept===$d?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-6 col-md-2">
        <label class="form-label small mb-1">Contractor</label>
        <select name="contractor" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($contractors as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>" <?= $fContractor===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-6 col-md-1">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <option value="active"     <?= $fStatus==='active'    ?'selected':'' ?>>Active</option>
          <option value="inactive"   <?= $fStatus==='inactive'  ?'selected':'' ?>>Inactive</option>
          <option value="terminated" <?= $fStatus==='terminated'?'selected':'' ?>>Terminated</option>
        </select>
      </div>
      <div class="col-sm-6 col-md-3">
        <label class="form-label small mb-1">Search</label>
        <input type="text" name="q" class="form-control form-control-sm"
               value="<?= htmlspecialchars($fSearch) ?>" placeholder="Name / Code / Enroll ID">
      </div>
      <div class="col-12 col-sm-auto">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Filter</button>
        <a href="index.php" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
      </div>
    </form>
  </div>
</div>

<div id="filter-results">
<style>
  /* Non-compliance employees get a subtle dull row background (hover still works) */
  #tblEmployees tbody tr.emp-noncomp { --bs-table-bg: #f0f0ee; }
</style>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Employees <span class="badge bg-secondary"><?= count($employees) ?></span></span>
    <?php if (!in_array($user['role'], ['user','compliance'], true)): ?>
    <div class="d-flex gap-2">
      <a href="import.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-upload"></i> Import</a>
      <a href="add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Employee</a>
    </div>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover table-sm align-middle mb-0" id="tblEmployees">
      <thead class="table-light">
        <tr>
          <th></th>
          <th>Sr</th>
          <th>Code</th>
          <th>Name</th>
          <th>Enroll ID</th>
          <th>Department</th>
          <th>Contractor</th>
          <th>Designation</th>
          <th>Company</th>
          <th>Join Date</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($employees as $e):
        $statusBadge = match($e['Status']) {
            'active'     => 'bg-success',
            'inactive'   => 'bg-warning text-dark',
            'terminated' => 'bg-danger',
            default      => 'bg-secondary',
        };
        $photoSrc = !empty($e['Photo'])
            ? BASE_URL . '/uploads/employees/' . htmlspecialchars($e['Photo'])
            : BASE_URL . '/assets/img/no-photo.png';
      ?>
      <tr<?= empty($e['Compliance']) ? ' class="emp-noncomp"' : '' ?>>
        <td style="width:40px">
          <img src="<?= $photoSrc ?>"
               onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"
               style="width:34px;height:34px;border-radius:50%;object-fit:cover">
          <span style="display:none;width:34px;height:34px;border-radius:50%;background:#dee2e6;align-items:center;justify-content:center">
            <i class="bi bi-person text-muted"></i>
          </span>
        </td>
        <td class="text-muted small text-center"><?= $e['Sr'] !== null ? (int)$e['Sr'] : '—' ?></td>
        <td><code><?= htmlspecialchars($e['EmployeeCode'] ?: '—') ?></code></td>
        <td class="fw-semibold"><?= htmlspecialchars($e['Name']) ?></td>
        <td><code><?= htmlspecialchars($e['EnrollId'] ?: '—') ?></code></td>
        <td><?= htmlspecialchars($e['Department'] ?? '—') ?></td>
        <td><?= htmlspecialchars($e['Contractor'] ?? '—') ?></td>
        <td><?= htmlspecialchars($e['Designation'] ?? '—') ?></td>
        <td class="small"><?= htmlspecialchars($e['CompanyName']) ?></td>
        <td class="small"><?= $e['JoinDate'] ? htmlspecialchars($e['JoinDate']) : '—' ?></td>
        <td><span class="badge <?= $statusBadge ?>"><?= ucfirst($e['Status']) ?></span></td>
        <td>
          <?php if (!in_array($user['role'], ['user','compliance'], true)): ?>
          <a href="add.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
          <a href="index.php?toggle=<?= $e['id'] ?>&<?= htmlspecialchars(http_build_query(array_filter($_GET, fn($k) => $k !== 'toggle', ARRAY_FILTER_USE_KEY))) ?>"
             class="btn btn-sm <?= $e['Status']==='active'?'btn-outline-warning':'btn-outline-success' ?>"
             title="<?= $e['Status']==='active'?'Deactivate':'Activate' ?>">
            <i class="bi bi-<?= $e['Status']==='active'?'pause-circle':'play-circle' ?>"></i>
          </a>
          <a href="index.php?delete=<?= $e['id'] ?>&<?= htmlspecialchars(http_build_query(array_filter($_GET, fn($k) => $k !== 'delete', ARRAY_FILTER_USE_KEY))) ?>"
             class="btn btn-sm btn-outline-danger" title="Delete Employee"
             onclick="return confirm('Permanently delete <?= htmlspecialchars(addslashes($e['Name'])) ?>? This cannot be undone.')">
            <i class="bi bi-trash"></i>
          </a>
          <?php else: ?>
          <span class="text-muted small">View only</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script>$(()=>{$("#tblEmployees").DataTable({order:[[8,"asc"],[1,"asc"],[3,"asc"]],pageLength:25,columnDefs:[{orderable:false,targets:[0,11]}],language:{emptyTable:"No employees found. <a href='add.php'>Add the first one</a>."}});});</script>
</div><!-- /#filter-results -->
<?php
require_once __DIR__ . '/../../includes/footer.php';
