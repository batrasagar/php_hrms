<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    if ($user['role'] === 'superadmin') {
        $db->prepare("UPDATE tblUser SET IsActive = 1 - IsActive WHERE id = ? AND id != ?")
           ->execute([$id, $user['id']]);
    } else {
        $db->prepare("UPDATE tblUser SET IsActive = 1 - IsActive WHERE id = ? AND ParentAdminId = ?")
           ->execute([$id, $user['id']]);
    }
    header('Location: list.php'); exit;
}

$pageTitle  = 'Users';
$activePage = 'users';
require_once __DIR__ . '/../../includes/header.php';

$msg = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

if ($user['role'] === 'superadmin') {
    $users = $db->query(
        "SELECT u.id, u.Name, u.Email, u.Role, u.IsActive, u.CreatedAt,
                c.Name AS CompanyName, pu.Name AS CreatedByName
         FROM tblUser u
         LEFT JOIN tblCompany c ON c.id = u.CompanyId
         LEFT JOIN tblUser pu ON pu.id = u.ParentAdminId
         ORDER BY u.id DESC"
    )->fetchAll();
} else {
    $stmt = $db->prepare(
        "SELECT u.id, u.Name, u.Email, u.Role, u.IsActive, u.CreatedAt,
                c.Name AS CompanyName
         FROM tblUser u
         LEFT JOIN tblCompany c ON c.id = u.CompanyId
         WHERE u.ParentAdminId = ? OR u.id = ?
         ORDER BY CASE WHEN u.id = ? THEN 0 ELSE 1 END, u.id DESC"
    );
    $stmt->execute([$user['id'], $user['id'], $user['id']]);
    $users = $stmt->fetchAll();
}
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <span><?= count($users) ?> user<?= count($users) !== 1 ? 's' : '' ?></span>
  <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add User</a>
</div>
<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0" id="tblUsers">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Company</th>
          <?php if ($user['role'] === 'superadmin'): ?><th>Created By</th><?php endif; ?>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td class="text-muted small"><?= $u['id'] ?></td>
          <td class="fw-semibold"><?= htmlspecialchars($u['Name']) ?></td>
          <td class="small"><?= htmlspecialchars($u['Email']) ?></td>
          <td>
            <?php
              $roleColor = match($u['Role']) {
                'superadmin' => 'danger',
                'admin'      => 'primary',
                default      => 'secondary',
              };
            ?>
            <span class="badge bg-<?= $roleColor ?>"><?= $u['Role'] ?></span>
          </td>
          <td class="small"><?= $u['CompanyName'] ? htmlspecialchars($u['CompanyName']) : '<span class="text-muted">—</span>' ?></td>
          <?php if ($user['role'] === 'superadmin'): ?>
          <td class="small text-muted"><?= htmlspecialchars($u['CreatedByName'] ?? '—') ?></td>
          <?php endif; ?>
          <td>
            <?php if ($u['id'] === $user['id']): ?>
              <span class="badge bg-<?= $u['IsActive'] ? 'success' : 'secondary' ?>"><?= $u['IsActive'] ? 'Active' : 'Inactive' ?></span>
            <?php else: ?>
              <a href="list.php?toggle=<?= $u['id'] ?>"
                 class="badge text-decoration-none bg-<?= $u['IsActive'] ? 'success' : 'secondary' ?>">
                <?= $u['IsActive'] ? 'Active' : 'Inactive' ?>
              </a>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($u['id'] !== $user['id']): ?>
              <a href="add.php?edit=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil"></i>
              </a>
            <?php else: ?>
              <span class="text-muted small">You</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No users yet. <a href="add.php">Create one</a>.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$extraJs = '<script>$(()=>{$("#tblUsers").DataTable({order:[[0,"desc"]]});});</script>';
require_once __DIR__ . '/../../includes/footer.php';
