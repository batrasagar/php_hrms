<?php
$pageTitle  = 'My Profile';
$activePage = 'profile';
define('BASE_URL', ($_SERVER['HTTP_HOST'] ?? '') === 'hr.attnlog.in' ? '' : '/php_hrms');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db   = getDb();
$user = currentUser();

// Load email from DB
$row = $db->prepare("SELECT Email FROM tblUser WHERE id = ? LIMIT 1");
$row->execute([$user['id']]);
$dbUser = $row->fetch() ?: [];

$pwdError   = '';
$pwdSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_pwd') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $current = $_POST['current_pwd']  ?? '';
    $newPwd  = $_POST['new_pwd']      ?? '';
    $confirm = $_POST['confirm_pwd']  ?? '';

    $stmt = $db->prepare("SELECT Password FROM tblUser WHERE id = ?");
    $stmt->execute([$user['id']]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $pwdError = 'Current password is incorrect.';
    } elseif (strlen($newPwd) < 6) {
        $pwdError = 'New password must be at least 6 characters.';
    } elseif ($newPwd !== $confirm) {
        $pwdError = 'New passwords do not match.';
    } else {
        $db->prepare("UPDATE tblUser SET Password = ? WHERE id = ?")
           ->execute([password_hash($newPwd, PASSWORD_BCRYPT), $user['id']]);
        $pwdSuccess = true;
    }
    if ($isAjax) {
        if ($pwdError) {
            header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>[$pwdError]]); exit;
        }
        header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>'Password changed successfully.']); exit;
    }
}
require_once __DIR__ . '/../../includes/header.php';
?>

<div style="max-width:560px; margin:0 auto;">

  <!-- User Info Card -->
  <div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-3">
      <div style="width:44px;height:44px;border-radius:50%;background:var(--blue);color:#fff;font-size:18px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;text-transform:uppercase;">
        <?= mb_substr($user['name'], 0, 1) ?>
      </div>
      <div>
        <div class="fw-semibold fs-6"><?= htmlspecialchars($user['name']) ?></div>
        <div class="text-muted" style="font-size:12px;"><?= htmlspecialchars($dbUser['Email'] ?? '') ?></div>
      </div>
      <span class="badge bg-primary ms-auto text-capitalize"><?= htmlspecialchars($user['role']) ?></span>
    </div>
  </div>

  <?php if (in_array($user['role'], ['admin','superadmin'])): ?>
  <!-- My Companies -->
  <div class="card mb-3">
    <div class="card-body d-flex align-items-center justify-content-between">
      <div>
        <div class="fw-semibold"><i class="bi bi-buildings me-2 text-primary"></i>My Companies</div>
        <div class="text-muted" style="font-size:12px;margin-top:2px;">Manage companies under your account</div>
      </div>
      <a href="<?= BASE_URL ?>/modules/companies/index.php" class="btn btn-outline-primary btn-sm">Open &rarr;</a>
    </div>
  </div>
  <!-- Manage Users -->
  <div class="card mb-4">
    <div class="card-body d-flex align-items-center justify-content-between">
      <div>
        <div class="fw-semibold"><i class="bi bi-people me-2 text-success"></i>Manage Users</div>
        <div class="text-muted" style="font-size:12px;margin-top:2px;">Create and manage user accounts for your companies</div>
      </div>
      <a href="<?= BASE_URL ?>/modules/users/list.php" class="btn btn-outline-success btn-sm">Open &rarr;</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Change Password -->
  <div class="card mb-4">
    <div class="card-header">Change Password</div>
    <div class="card-body">
      <?php if ($pwdSuccess): ?>
      <div class="alert alert-success mb-3">Password changed successfully.</div>
      <?php endif; ?>
      <?php if ($pwdError): ?>
      <div class="alert alert-danger mb-3"><?= htmlspecialchars($pwdError) ?></div>
      <?php endif; ?>
      <form method="POST" autocomplete="off" data-ajax>
        <input type="hidden" name="action" value="change_pwd">
        <div class="mb-3">
          <label class="form-label">Current Password</label>
          <input type="password" name="current_pwd" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">New Password</label>
          <input type="password" name="new_pwd" class="form-control" required minlength="6">
        </div>
        <div class="mb-3">
          <label class="form-label">Confirm New Password</label>
          <input type="password" name="confirm_pwd" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Update Password</button>
      </form>
    </div>
  </div>

  <!-- Logout -->
  <div class="card">
    <div class="card-body d-flex align-items-center justify-content-between">
      <div>
        <div class="fw-semibold"><i class="bi bi-box-arrow-right me-2 text-danger"></i>Sign Out</div>
        <div class="text-muted" style="font-size:12px;margin-top:2px;">Sign out from this device</div>
      </div>
      <a href="<?= BASE_URL ?>/logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
