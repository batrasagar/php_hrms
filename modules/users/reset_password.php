<?php
define('BASE_URL', preg_match('#(^|\.)hr\.attnlog\.in$#', $_SERVER['HTTP_HOST'] ?? '') ? '' : '/php_hrms');
require_once __DIR__ . '/../../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$db    = getDb();
$token = trim($_GET['token'] ?? '');
$error = '';
$done  = false;

if (!$token) {
    header('Location: ' . BASE_URL . '/login.php'); exit;
}

$stmt = $db->prepare("SELECT email FROM tblPasswordResets WHERE token=? AND used=0 AND expires_at>NOW()");
$stmt->execute([$token]);
$resetRow = $stmt->fetch();

if (!$resetRow) {
    $error = 'This reset link is invalid or has expired. Please request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $resetRow) {
    $pass1 = $_POST['password']  ?? '';
    $pass2 = $_POST['password2'] ?? '';
    if (strlen($pass1) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass1 !== $pass2) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($pass1, PASSWORD_DEFAULT);
        $db->prepare("UPDATE tblUser SET Password=? WHERE Email=? AND IsActive=1")
           ->execute([$hash, $resetRow['email']]);
        $db->prepare("UPDATE tblPasswordResets SET used=1 WHERE token=?")->execute([$token]);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HRMS — Reset Password</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body {
    background: linear-gradient(135deg, #e8f0fe 0%, #f0f4ff 50%, #e3f2fd 100%);
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center; padding: 16px;
  }
  .card { width: 100%; max-width: 420px; border-radius: 16px; border: none; }
</style>
</head>
<body>
<div class="card shadow-lg p-4">
  <div class="text-center mb-4">
    <div class="fs-2 fw-bold text-primary"><i class="bi bi-building me-2"></i>HRMS</div>
    <h5 class="mt-2 fw-normal text-muted">Reset Password</h5>
  </div>

  <?php if ($done): ?>
  <div class="alert alert-success small py-2">
    <i class="bi bi-check-circle me-1"></i>Password updated successfully.
  </div>
  <div class="text-center mt-3">
    <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary">
      <i class="bi bi-box-arrow-in-right me-1"></i>Login Now
    </a>
  </div>

  <?php elseif ($error && !$resetRow): ?>
  <div class="alert alert-danger small py-2"><?= htmlspecialchars($error) ?></div>
  <div class="text-center mt-3">
    <a href="<?= BASE_URL ?>/modules/users/forgot_password.php" class="small text-muted text-decoration-none">
      Request a new reset link
    </a>
  </div>

  <?php else: ?>
  <?php if ($error): ?>
  <div class="alert alert-danger small py-2"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST" autocomplete="off">
    <div class="mb-3">
      <label class="form-label fw-semibold">New Password</label>
      <input type="password" name="password" class="form-control" required minlength="8" autofocus>
      <div class="form-text">At least 8 characters.</div>
    </div>
    <div class="mb-3">
      <label class="form-label fw-semibold">Confirm Password</label>
      <input type="password" name="password2" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary w-100">
      <i class="bi bi-lock me-1"></i>Set New Password
    </button>
  </form>
  <?php endif; ?>

  <?php if (!$done): ?>
  <div class="text-center mt-3">
    <a href="<?= BASE_URL ?>/login.php" class="small text-muted text-decoration-none">
      <i class="bi bi-arrow-left me-1"></i>Back to Login
    </a>
  </div>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
