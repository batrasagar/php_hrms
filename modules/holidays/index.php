<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();
$msg  = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $where = $user['role'] === 'superadmin' ? 'id=?' : 'id=? AND CompanyId IN (SELECT id FROM tblCompany WHERE AdminId=' . $user['scope_id'] . ')';
    $db->prepare("DELETE FROM tblHoliday WHERE $where")->execute([$id]);
    $_SESSION['flash'] = 'Holiday deleted.'; header('Location: index.php'); exit;
}

$fYear = (int)($_GET['year'] ?? date('Y'));
$fCo   = (int)($_GET['company'] ?? 0);

if ($user['role'] === 'superadmin') {
    $companiesDd = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
    $where = ['YEAR(h.HolidayDate) = ?'];
    $params = [$fYear];
    if ($fCo) { $where[] = 'h.CompanyId=?'; $params[] = $fCo; }
    $stmt = $db->prepare("SELECT h.*, c.Name AS CompanyName FROM tblHoliday h
        JOIN tblCompany c ON c.id = h.CompanyId
        WHERE " . implode(' AND ', $where) . " ORDER BY h.HolidayDate");
    $stmt->execute($params);
} else {
    $stmt2 = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt2->execute([$user['scope_id']]);
    $companiesDd = $stmt2->fetchAll();
    $where = ['YEAR(h.HolidayDate) = ?', 'c.AdminId = ?'];
    $params = [$fYear, $user['scope_id']];
    if ($fCo) { $where[] = 'h.CompanyId=?'; $params[] = $fCo; }
    $stmt = $db->prepare("SELECT h.*, c.Name AS CompanyName FROM tblHoliday h
        JOIN tblCompany c ON c.id = h.CompanyId
        WHERE " . implode(' AND ', $where) . " ORDER BY h.HolidayDate");
    $stmt->execute($params);
}
$holidays = $stmt->fetchAll();
$typeLabels = ['national' => 'National', 'optional' => 'Optional', 'restricted' => 'Restricted'];
$typeBadges = ['national' => 'bg-danger', 'optional' => 'bg-warning text-dark', 'restricted' => 'bg-info text-dark'];
$pageTitle  = 'Holiday Master';
$activePage = 'holidays';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <form method="GET" class="d-flex gap-2 align-items-center" data-filter>
    <select name="year" class="form-select form-select-sm" style="width:90px" onchange="$(this.form).trigger('submit')">
      <?php for ($y = date('Y') + 1; $y >= date('Y') - 2; $y--): ?>
      <option <?= $fYear==$y?'selected':'' ?>><?= $y ?></option>
      <?php endfor; ?>
    </select>
    <select name="company" class="form-select form-select-sm" style="width:160px" onchange="$(this.form).trigger('submit')">
      <option value="">All Companies</option>
      <?php foreach ($companiesDd as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $fCo==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <a href="add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Holiday</a>
</div>
<div id="filter-results">
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold">
    Holidays <span class="badge bg-secondary"><?= count($holidays) ?></span>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover table-sm align-middle mb-0" id="tblHolidays">
      <thead class="table-light">
        <tr>
          <th>Date</th>
          <th>Day</th>
          <th>Holiday Name</th>
          <th>Type</th>
          <th>Company</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($holidays as $h): ?>
      <tr>
        <td><?= htmlspecialchars($h['HolidayDate']) ?></td>
        <td class="text-muted small"><?= date('l', strtotime($h['HolidayDate'])) ?></td>
        <td class="fw-semibold"><?= htmlspecialchars($h['Name']) ?></td>
        <td><span class="badge <?= $typeBadges[$h['Type']] ?? 'bg-secondary' ?>"><?= $typeLabels[$h['Type']] ?? $h['Type'] ?></span></td>
        <td class="small"><?= htmlspecialchars($h['CompanyName']) ?></td>
        <td>
          <a href="add.php?id=<?= $h['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
          <a href="?delete=<?= $h['id'] ?>&year=<?= $fYear ?>&company=<?= $fCo ?>"
             class="btn btn-sm btn-outline-danger"
             onclick="return confirm('Delete this holiday?')"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script>$(()=>{$("#tblHolidays").DataTable({order:[[0,"asc"]],pageLength:25,language:{emptyTable:"No holidays for this year. <a href='add.php'>Add one</a>."}});});</script>
</div><!-- /#filter-results -->
<?php
require_once __DIR__ . '/../../includes/footer.php';
