<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
$pageTitle  = 'Joining / Exit Report';
$activePage = 'report_joinleft';
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
    $stmt->execute([$user['scope_id']]);
    $companiesDd = $stmt->fetchAll();
    $fCompany    = (int)($_GET['company'] ?? 0);
}

$fType       = ($_GET['type'] ?? 'joined') === 'left' ? 'left' : 'joined';
$fFrom       = trim($_GET['from']       ?? date('Y-m-01'));
$fTo         = trim($_GET['to']         ?? date('Y-m-t'));
$fDept       = trim($_GET['dept']       ?? '');
$fContractor = trim($_GET['contractor'] ?? '');
$fQ          = trim($_GET['q']          ?? '');

$dateCol = $fType === 'left' ? 'DOL' : 'JoinDate';

$where  = [];
$params = [];
if ($user['role'] === 'user')      { $where[] = 'e.CompanyId = ?'; $params[] = $fCompany; }
elseif (in_array($user['role'], ['admin','operator'], true)) { $where[] = 'c.AdminId = ?';    $params[] = $user['scope_id']; }
if ($fCompany && $user['role'] !== 'user') { $where[] = 'e.CompanyId = ?'; $params[] = $fCompany; }
$where[] = "e.$dateCol IS NOT NULL";
$where[] = "e.$dateCol >= ?"; $params[] = $fFrom;
$where[] = "e.$dateCol <= ?"; $params[] = $fTo;
if ($fDept)       { $where[] = 'e.Department = ?'; $params[] = $fDept; }
if ($fContractor) { $where[] = 'e.Contractor = ?'; $params[] = $fContractor; }
if ($fQ) {
    $where[] = '(e.EmployeeCode LIKE ? OR e.Name LIKE ?)';
    $params[] = "%$fQ%"; $params[] = "%$fQ%";
}
$wsql = 'WHERE ' . implode(' AND ', $where);

$stmt = $db->prepare(
    "SELECT e.EmployeeCode, e.Name, e.FatherName, e.Department, e.Contractor,
            e.JoinDate, e.DOL, e.Status, c.Name AS CompanyName
     FROM tblEmployee e
     JOIN tblCompany c ON c.id = e.CompanyId
     $wsql ORDER BY e.$dateCol DESC, ISNULL(e.Sr), e.Sr, e.Name"
);
$stmt->execute($params);
$records = $stmt->fetchAll();

$scopeJoin   = $user['role'] === 'superadmin' ? '' : 'JOIN tblCompany c ON c.id=e.CompanyId AND c.AdminId=' . $user['scope_id'];
$depts       = array_filter(array_column($db->query("SELECT DISTINCT Department FROM tblEmployee e $scopeJoin ORDER BY Department")->fetchAll(), 'Department'));
$contractors = array_filter(array_column($db->query("SELECT DISTINCT Contractor FROM tblEmployee e $scopeJoin ORDER BY Contractor")->fetchAll(), 'Contractor'));

$statusBadge = ['active' => 'bg-success', 'pending' => 'bg-warning text-dark', 'rejected' => 'bg-danger'];
?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-6 col-sm-4 col-md-2"><label class="form-label small mb-1">Report</label>
        <select name="type" class="form-select form-select-sm">
          <option value="joined" <?= $fType==='joined'?'selected':'' ?>>Joined</option>
          <option value="left"   <?= $fType==='left'  ?'selected':'' ?>>Left / Exited</option>
        </select>
      </div>
      <?php if ($user['role'] !== 'user'): ?>
      <div class="col-6 col-sm-4 col-md-2"><label class="form-label small mb-1">Company</label>
        <select name="company" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($companiesDd as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?><input type="hidden" name="company" value="<?= $fCompany ?>"><?php endif; ?>
      <div class="col-6 col-sm-4 col-md-2"><label class="form-label small mb-1">From (<?= $fType==='left'?'Exit':'Join' ?>)</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($fFrom) ?>">
      </div>
      <div class="col-6 col-sm-4 col-md-2"><label class="form-label small mb-1">To</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($fTo) ?>">
      </div>
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
      <div class="col-6 col-sm-4 col-md-3"><label class="form-label small mb-1">Emp Code / Name</label>
        <input type="text" name="q" class="form-control form-control-sm" value="<?= htmlspecialchars($fQ) ?>" placeholder="Search…">
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
      <div class="fs-4 fw-bold <?= $fType==='left'?'text-danger':'text-success' ?>"><?= count($records) ?></div>
      <div class="text-muted small"><?= $fType==='left' ? 'Employees Left' : 'Employees Joined' ?></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card text-center p-3">
      <div class="fs-4 fw-bold"><?= count(array_unique(array_filter(array_column($records, 'Department')))) ?></div>
      <div class="text-muted small">Departments</div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold">
    <?= $fType==='left' ? 'Employees Left' : 'Employees Joined' ?> — <?= htmlspecialchars($fFrom) ?> to <?= htmlspecialchars($fTo) ?>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover table-sm mb-0" id="tblJoinLeft">
      <thead class="table-light">
        <tr>
          <th>Code</th><th>Name</th><th>Father</th><th>Department</th><th>Contractor</th>
          <th>Company</th><th>Join Date</th><th>Exit Date</th><th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($records as $r): ?>
      <tr>
        <td><code class="small"><?= htmlspecialchars($r['EmployeeCode'] ?: '—') ?></code></td>
        <td><?= htmlspecialchars($r['Name']) ?></td>
        <td class="small"><?= htmlspecialchars($r['FatherName'] ?? '—') ?></td>
        <td class="small"><?= htmlspecialchars($r['Department'] ?? '—') ?></td>
        <td class="small"><?= htmlspecialchars($r['Contractor'] ?? '—') ?></td>
        <td class="small"><?= htmlspecialchars($r['CompanyName']) ?></td>
        <td class="small"><?= $r['JoinDate'] ? htmlspecialchars($r['JoinDate']) : '—' ?></td>
        <td class="small"><?= $r['DOL'] ? htmlspecialchars($r['DOL']) : '—' ?></td>
        <td><span class="badge <?= $statusBadge[$r['Status']] ?? 'bg-secondary' ?>"><?= htmlspecialchars(ucfirst($r['Status'] ?? '')) ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$orderCol = $fType === 'left' ? 7 : 6;
$extraJs = '<script>$(()=>{$("#tblJoinLeft").DataTable({order:[[' . $orderCol . ',"desc"]],pageLength:50,language:{emptyTable:"No employees found for the selected filters."}});});</script>';
require_once __DIR__ . '/../../includes/footer.php';
