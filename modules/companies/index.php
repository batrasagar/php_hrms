<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
requirePermission('companies.view');

$db   = getDb();
$user = currentUser();
$msg  = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

if (isset($_GET['toggle'])) {
    requirePermission('companies.edit');
    $id = (int)$_GET['toggle'];
    $where = $user['role'] === 'superadmin' ? "id=?" : "id=? AND AdminId={$user['id']}";
    $db->prepare("UPDATE tblCompany SET IsActive = 1 - IsActive WHERE $where")->execute([$id]);
    header('Location: index.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_company_id'])) {
    requirePermission('companies.edit');
    requireSuperAdmin();
    csrf_verify();
    $id = (int)$_POST['delete_company_id'];
    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM tblEmployee WHERE CompanyId = ?")->execute([$id]);
        $db->prepare("DELETE FROM tblCompany  WHERE id = ?")->execute([$id]);
        $db->commit();
        $_SESSION['flash'] = 'Company and all its employees deleted.';
    } catch (\Throwable $e) {
        $db->rollBack();
        $_SESSION['flash'] = 'Delete failed: ' . $e->getMessage();
    }
    header('Location: index.php'); exit;
}

if ($user['role'] === 'superadmin') {
    $companies = $db->query(
        "SELECT c.*, u.Name AS AdminName, u.Email AS AdminEmail
         FROM tblCompany c JOIN tblUser u ON u.id = c.AdminId
         ORDER BY c.CreatedAt DESC"
    )->fetchAll();
} else {
    $stmt = $db->prepare(
        "SELECT *, ? AS AdminName, ? AS AdminEmail FROM tblCompany
         WHERE AdminId = ? ORDER BY CreatedAt DESC"
    );
    $stmt->execute([$user['name'], '', $user['id']]);
    $companies = $stmt->fetchAll();
}

$limit      = $user['company_limit'];
$ownCount   = $user['role'] === 'superadmin' ? 0 : count($companies);
$canAdd     = $user['role'] === 'superadmin' || $limit == -1 || $ownCount < $limit;
$pageTitle  = 'Companies';
$activePage = 'companies';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <span><?= count($companies) ?> company/companies</span>
  <?php if ($canAdd): ?>
  <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Company</a>
  <?php else: ?>
  <span class="text-muted small">Limit reached (<?= $limit ?>). Contact super admin to extend.</span>
  <?php endif; ?>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0" id="tblCompanies">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Name</th>
          <?php if ($user['role'] === 'superadmin'): ?><th>Owner</th><?php endif; ?>
          <th>Phone</th>
          <th>Email</th>
          <th>Status</th>
          <th>Added</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($companies as $c): ?>
      <tr>
        <td><?= $c['id'] ?></td>
        <td class="fw-semibold"><?= htmlspecialchars($c['Name']) ?></td>
        <?php if ($user['role'] === 'superadmin'): ?>
        <td class="small"><?= htmlspecialchars($c['AdminName']) ?><br>
          <span class="text-muted"><?= htmlspecialchars($c['AdminEmail']) ?></span></td>
        <?php endif; ?>
        <td><?= htmlspecialchars($c['Phone'] ?? '—') ?></td>
        <td><?= htmlspecialchars($c['Email'] ?? '—') ?></td>
        <td>
          <?= $c['IsActive']
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-secondary">Inactive</span>' ?>
        </td>
        <td class="small text-muted"><?= htmlspecialchars(substr($c['CreatedAt'], 0, 10)) ?></td>
        <td>
          <a href="add.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
          <a href="index.php?toggle=<?= $c['id'] ?>"
             class="btn btn-sm <?= $c['IsActive'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
            <i class="bi bi-<?= $c['IsActive'] ? 'pause-circle' : 'play-circle' ?>"></i>
          </a>
          <?php if ($user['role'] === 'superadmin'): ?>
          <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="delete_company_id" value="<?= $c['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger"
                    onclick="return confirm('Delete <?= addslashes(htmlspecialchars($c['Name'])) ?> and ALL its employees? This cannot be undone.')">
              <i class="bi bi-trash"></i>
            </button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$extraJs = '<script>$(()=>{$("#tblCompanies").DataTable({order:[[0,"desc"]],language:{emptyTable:"No companies yet. <a href=\'add.php\'>Add your first company</a>."}});});</script>';
require_once __DIR__ . '/../../includes/footer.php';
