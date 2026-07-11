<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();
$msg  = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $where = $user['role'] === 'superadmin' ? 'id=?' : 'id=? AND CompanyId IN (SELECT id FROM tblCompany WHERE AdminId=' . $user['id'] . ')';
    $db->prepare("UPDATE tblShift SET IsActive = 1 - IsActive WHERE $where")->execute([$id]);
    header('Location: index.php'); exit;
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $where = $user['role'] === 'superadmin' ? 'id=?' : 'id=? AND CompanyId IN (SELECT id FROM tblCompany WHERE AdminId=' . $user['id'] . ')';
    $db->prepare("DELETE FROM tblShift WHERE $where")->execute([$id]);
    $_SESSION['flash'] = 'Shift deleted.'; header('Location: index.php'); exit;
}

if ($user['role'] === 'superadmin') {
    $shifts = $db->query(
        "SELECT s.*, c.Name AS CompanyName FROM tblShift s
         JOIN tblCompany c ON c.id = s.CompanyId ORDER BY c.Name, s.ShiftName"
    )->fetchAll();
} else {
    $stmt = $db->prepare(
        "SELECT s.*, c.Name AS CompanyName FROM tblShift s
         JOIN tblCompany c ON c.id = s.CompanyId AND c.AdminId = ?
         ORDER BY c.Name, s.ShiftName"
    );
    $stmt->execute([$user['id']]);
    $shifts = $stmt->fetchAll();
}
$pageTitle  = 'Shift Master';
$activePage = 'shifts';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <span class="text-muted"><?= count($shifts) ?> shift(s)</span>
  <div class="d-flex gap-2">
    <a href="defaults.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-magic"></i> Add Default Shifts</a>
    <a href="add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Shift</a>
  </div>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover table-sm align-middle mb-0" id="tblShifts">
      <thead class="table-light">
        <tr>
          <th>Shift Name</th>
          <th>Company</th>
          <th>Arrival</th>
          <th>Departure</th>
          <th>Min Arrival</th>
          <th>Max Arrival</th>
          <th>Max Depart</th>
          <th>Hrs/Full</th>
          <th>Hrs/Half</th>
          <th>Lunch</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($shifts as $s): ?>
      <tr>
        <td class="fw-semibold"><?= htmlspecialchars($s['ShiftName']) ?></td>
        <td class="small"><?= htmlspecialchars($s['CompanyName']) ?></td>
        <td><?= substr($s['ArrivalTime'],0,5) ?></td>
        <td><?= substr($s['DepartureTime'],0,5) ?></td>
        <td><?= $s['MinArrivalTime']   ? substr($s['MinArrivalTime'],0,5)   : '—' ?></td>
        <td><?= $s['MaxArrivalTime']   ? substr($s['MaxArrivalTime'],0,5)   : '—' ?></td>
        <td><?= $s['MaxDepartureTime'] ? substr($s['MaxDepartureTime'],0,5) : '—' ?></td>
        <td><?= number_format($s['HrsP'],2) ?></td>
        <td><?= number_format($s['HrsHlf'],2) ?></td>
        <td class="small"><?= !empty($s['HasLunch'])
              ? (($s['LunchOutTime'] ? substr($s['LunchOutTime'],0,5) : '?') . '–' . ($s['LunchInTime'] ? substr($s['LunchInTime'],0,5) : '?'))
              : '<span class="text-muted">None</span>' ?></td>
        <td><?= $s['IsActive'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
        <td>
          <a href="add.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
          <a href="?toggle=<?= $s['id'] ?>" class="btn btn-sm <?= $s['IsActive']?'btn-outline-warning':'btn-outline-success' ?>">
            <i class="bi bi-<?= $s['IsActive']?'pause-circle':'play-circle' ?>"></i>
          </a>
          <a href="?delete=<?= $s['id'] ?>" class="btn btn-sm btn-outline-danger"
             onclick="return confirm('Delete this shift?')"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$extraJs = '<script>$(()=>{$("#tblShifts").DataTable({order:[[1,"asc"],[0,"asc"]],language:{emptyTable:"No shifts yet. <a href=\'add.php\'>Add one</a>."}});});</script>';
require_once __DIR__ . '/../../includes/footer.php';
