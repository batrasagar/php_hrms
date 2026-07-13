<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();
$msg  = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

// Delete (scope-checked)
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($user['role'] === 'superadmin') {
        $db->prepare("DELETE FROM tblWageWorker WHERE id=?")->execute([$id]);
    } else {
        $db->prepare("DELETE w FROM tblWageWorker w
            JOIN tblCompany c ON c.id=w.CompanyId AND c.AdminId=? WHERE w.id=?")
           ->execute([$user['scope_id'], $id]);
    }
    $_SESSION['flash'] = 'Worker deleted.';
    header('Location: index.php' . (($co=(int)($_GET['company']??0)) ? "?company=$co" : '')); exit;
}

$fCo = (int)($_GET['company'] ?? 0);

// Companies for the filter + scope guard
if ($user['role'] === 'superadmin') {
    $companiesDd = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
    $where = ['1=1']; $params = [];
} else {
    $stmt2 = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt2->execute([$user['scope_id']]);
    $companiesDd = $stmt2->fetchAll();
    $where = ['c.AdminId = ?']; $params = [$user['scope_id']];
}
if ($fCo) { $where[] = 'w.CompanyId = ?'; $params[] = $fCo; }

$stmt = $db->prepare("SELECT w.*, c.Name AS CompanyName, s.ShiftName
    FROM tblWageWorker w
    JOIN tblCompany c ON c.id = w.CompanyId
    LEFT JOIN tblShift s ON s.id = w.ShiftId
    WHERE " . implode(' AND ', $where) . "
    ORDER BY w.Name");
$stmt->execute($params);
$workers = $stmt->fetchAll();

$wageLabels = ['daily'=>'Per Day','hourly'=>'Per Hour','monthly'=>'Per Month','piece_rate'=>'Piece Rate'];

$pageTitle  = 'Wage Workers';
$activePage = 'workers';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <form method="GET" class="d-flex gap-2 align-items-center" data-filter>
    <select name="company" class="form-select form-select-sm" style="width:200px" onchange="$(this.form).trigger('submit')">
      <option value="">All Companies</option>
      <?php foreach ($companiesDd as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $fCo==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <a href="add.php<?= $fCo ? "?company=$fCo" : '' ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Worker</a>
</div>

<div id="filter-results">
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold">
    Wage Workers <span class="badge bg-secondary"><?= count($workers) ?></span>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover table-sm align-middle mb-0" id="tblWorkers">
      <thead class="table-light">
        <tr>
          <th>Code</th>
          <th>Name</th>
          <th>Department</th>
          <th>Activity</th>
          <th>Type</th>
          <th>Mobile</th>
          <th>Wage</th>
          <th>Shift</th>
          <th>Status</th>
          <th>Company</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($workers as $w): ?>
      <tr>
        <td class="text-muted small"><?= htmlspecialchars($w['EmployeeCode']) ?></td>
        <td class="fw-semibold"><?= htmlspecialchars($w['Name']) ?></td>
        <td class="small"><?= htmlspecialchars($w['Department']) ?></td>
        <td class="small"><?= htmlspecialchars($w['Activity']) ?></td>
        <td class="small"><?= htmlspecialchars($w['EmpType']) ?></td>
        <td class="small"><?= htmlspecialchars($w['Mobile']) ?></td>
        <td class="small">
          <?php if ((float)$w['WageRate'] > 0): ?>
            ₹<?= number_format((float)$w['WageRate'], 2) ?>
            <span class="text-muted">/ <?= $wageLabels[$w['WageType']] ?? $w['WageType'] ?></span>
          <?php else: ?><span class="text-muted">—</span><?php endif; ?>
        </td>
        <td class="small"><?= $w['ShiftName'] ? htmlspecialchars($w['ShiftName']) : '<span class="text-muted">—</span>' ?></td>
        <td><span class="badge <?= $w['Status']==='active'?'bg-success':'bg-secondary' ?>"><?= ucfirst($w['Status']) ?></span></td>
        <td class="small"><?= htmlspecialchars($w['CompanyName']) ?></td>
        <td class="text-nowrap">
          <a href="add.php?id=<?= $w['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
          <a href="?delete=<?= $w['id'] ?>&company=<?= $fCo ?>"
             class="btn btn-sm btn-outline-danger"
             onclick="return confirm('Delete this worker?')"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script>$(()=>{$("#tblWorkers").DataTable({order:[[1,"asc"]],pageLength:25,language:{emptyTable:"No workers yet. <a href='add.php'>Add one</a>."}});});</script>
</div><!-- /#filter-results -->
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
