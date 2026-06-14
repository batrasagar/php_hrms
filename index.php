<?php
define('BASE_URL', ($_SERVER['HTTP_HOST'] ?? '') === 'hr.attnlog.in' ? '' : '/php_hrms');
require_once __DIR__ . '/config/db.php';
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/includes/header.php';

$db   = getDb();
$role = $user['role'];
?>

<?php if ($role === 'superadmin'): ?>
<?php
$pendingCount  = $db->query("SELECT COUNT(*) FROM tblUser WHERE Status='pending'")->fetchColumn();
$totalAdmins   = $db->query("SELECT COUNT(*) FROM tblUser WHERE Role='admin'")->fetchColumn();
$totalUsers    = $db->query("SELECT COUNT(*) FROM tblUser WHERE Role='user'")->fetchColumn();
$totalCompanies = $db->query("SELECT COUNT(*) FROM tblCompany WHERE IsActive=1")->fetchColumn();
?>
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <a href="modules/approvals/index.php" class="text-decoration-none">
      <div class="card text-center p-3 <?= $pendingCount > 0 ? 'border-warning border' : '' ?>">
        <div class="fs-2 <?= $pendingCount > 0 ? 'text-warning' : 'text-secondary' ?>"><i class="bi bi-person-check"></i></div>
        <div class="fs-4 fw-bold text-dark"><?= $pendingCount ?></div>
        <div class="text-muted small">Pending Approvals</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card text-center p-3">
      <div class="fs-2 text-primary"><i class="bi bi-person-badge"></i></div>
      <div class="fs-4 fw-bold"><?= $totalAdmins ?></div>
      <div class="text-muted small">Tenant Admins</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card text-center p-3">
      <div class="fs-2 text-info"><i class="bi bi-buildings"></i></div>
      <div class="fs-4 fw-bold"><?= $totalCompanies ?></div>
      <div class="text-muted small">Active Companies</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card text-center p-3">
      <div class="fs-2 text-secondary"><i class="bi bi-people"></i></div>
      <div class="fs-4 fw-bold"><?= $totalUsers ?></div>
      <div class="text-muted small">Staff Users</div>
    </div>
  </div>
</div>

<?php if ($pendingCount > 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-2">
  <i class="bi bi-exclamation-triangle-fill fs-5"></i>
  <span><?= $pendingCount ?> signup request(s) awaiting your approval.</span>
  <a href="modules/approvals/index.php" class="btn btn-warning btn-sm ms-auto">Review Now</a>
</div>
<?php endif; ?>

<!-- Recent signups -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold d-flex justify-content-between">
    <span>Recent Tenant Registrations</span>
    <a href="modules/approvals/index.php" class="btn btn-sm btn-outline-primary">View All</a>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover table-sm mb-0">
      <thead class="table-light">
        <tr><th>Name</th><th>Email</th><th>Status</th><th>Company Limit</th><th>Registered</th></tr>
      </thead>
      <tbody>
      <?php
      $recent = $db->query(
          "SELECT Name, Email, Status, CompanyLimit, CreatedAt FROM tblUser
           WHERE Role='admin' ORDER BY CreatedAt DESC LIMIT 20"
      )->fetchAll();
      foreach ($recent as $r):
          $badge = match($r['Status']) {
              'active'   => 'bg-success',
              'pending'  => 'bg-warning text-dark',
              'rejected' => 'bg-danger',
              default    => 'bg-secondary',
          };
      ?>
      <tr>
        <td><?= htmlspecialchars($r['Name']) ?></td>
        <td><?= htmlspecialchars($r['Email']) ?></td>
        <td><span class="badge <?= $badge ?>"><?= ucfirst($r['Status']) ?></span></td>
        <td><?= $r['CompanyLimit'] == -1 ? 'Unlimited' : $r['CompanyLimit'] ?></td>
        <td class="small text-muted"><?= htmlspecialchars(substr($r['CreatedAt'], 0, 10)) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($recent)): ?>
      <tr><td colspan="5" class="text-center text-muted py-3">No tenant registrations yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($role === 'admin'): ?>
<?php
$myCompanies = $db->prepare(
    "SELECT id, Name, IsActive, CreatedAt FROM tblCompany WHERE AdminId = ? ORDER BY Name"
);
$myCompanies->execute([$user['id']]);
$companies = $myCompanies->fetchAll();
$companyCount = count($companies);
$limit = $user['company_limit'];

$admsConfigured = false;
$admsLabel = '';
try {
    $adms = $db->query("SELECT Label FROM tblAdmsCredentials WHERE IsActive=1 LIMIT 1")->fetch();
    if ($adms) { $admsConfigured = true; $admsLabel = $adms['Label']; }
} catch (Exception $e) {}
?>
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card text-center p-3">
      <div class="fs-2 text-primary"><i class="bi bi-buildings"></i></div>
      <div class="fs-4 fw-bold"><?= $companyCount ?></div>
      <div class="text-muted small">
        My Companies
        <?php if ($limit != -1): ?>
        <span class="badge bg-light text-secondary border"><?= $companyCount ?>/<?= $limit ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <?php if ($admsConfigured): ?>
    <a href="modules/adms_credentials/index.php" class="text-decoration-none">
      <div class="card text-center p-3">
        <div class="fs-2 text-success"><i class="bi bi-plug-fill"></i></div>
        <div class="small fw-semibold text-dark mt-1"><?= htmlspecialchars($admsLabel) ?></div>
        <div class="text-muted small">ADMS Connected</div>
      </div>
    </a>
    <?php else: ?>
    <a href="modules/adms_credentials/index.php" class="text-decoration-none">
      <div class="card text-center p-3 border-warning border">
        <div class="fs-2 text-warning"><i class="bi bi-plug"></i></div>
        <div class="small fw-semibold text-dark mt-1">Not Configured</div>
        <div class="text-muted small">ADMS Credentials</div>
      </div>
    </a>
    <?php endif; ?>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">My Companies</span>
    <?php
    $canAdd = ($limit == -1) || ($companyCount < $limit);
    ?>
    <?php if ($canAdd): ?>
    <a href="modules/companies/add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add Company</a>
    <?php else: ?>
    <span class="text-muted small">Company limit reached (<?= $limit ?>). Contact administrator to increase.</span>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover table-sm mb-0">
      <thead class="table-light">
        <tr><th>Company Name</th><th>Status</th><th>Added</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($companies as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['Name']) ?></td>
        <td><?= $c['IsActive'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
        <td class="small text-muted"><?= htmlspecialchars(substr($c['CreatedAt'], 0, 10)) ?></td>
        <td><a href="modules/companies/add.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($companies)): ?>
      <tr><td colspan="4" class="text-center text-muted py-4">No companies yet. <a href="modules/companies/add.php">Add your first company</a>.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: /* user role */ ?>
<?php
$compId   = $user['company_id'];
$compName = '';
$empCount = 0;
if ($compId) {
    $cr = $db->prepare("SELECT Name FROM tblCompany WHERE id=? LIMIT 1");
    $cr->execute([$compId]);
    $compName = $cr->fetchColumn() ?: '';
    $ec = $db->prepare("SELECT COUNT(*) FROM tblEmployee WHERE CompanyId=? AND Status='active'");
    $ec->execute([$compId]);
    $empCount = (int)$ec->fetchColumn();
}
?>
<?php if ($compName): ?>
<div class="alert alert-info d-flex align-items-center gap-2 mb-4">
  <i class="bi bi-buildings fs-5"></i>
  <span>You are viewing data for <strong><?= htmlspecialchars($compName) ?></strong></span>
</div>
<?php endif; ?>
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <a href="modules/reports/attendance.php" class="text-decoration-none">
      <div class="card text-center p-3">
        <div class="fs-2 text-primary"><i class="bi bi-calendar2-week"></i></div>
        <div class="text-muted small mt-1">Attendance Report</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/reports/monthly_attendance.php" class="text-decoration-none">
      <div class="card text-center p-3">
        <div class="fs-2 text-success"><i class="bi bi-calendar-month"></i></div>
        <div class="text-muted small mt-1">Monthly Attendance</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/employees/index.php" class="text-decoration-none">
      <div class="card text-center p-3">
        <div class="fs-2 text-info"><i class="bi bi-person-vcard"></i></div>
        <div class="fs-5 fw-bold"><?= $empCount ?></div>
        <div class="text-muted small">Active Employees</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/punchlog/view.php" class="text-decoration-none">
      <div class="card text-center p-3">
        <div class="fs-2 text-secondary"><i class="bi bi-clock-history"></i></div>
        <div class="text-muted small mt-1">Punch Log</div>
      </div>
    </a>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
