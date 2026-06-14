<?php
define('BASE_URL', ($_SERVER['HTTP_HOST'] ?? '') === 'hr.attnlog.in' ? '' : '/php_hrms');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/smtp_helper.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php'); exit;
}

$db = getDb();
$db->exec("CREATE TABLE IF NOT EXISTS tblPasswordResets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(150) NOT NULL,
    token      CHAR(64)     NOT NULL,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_token (token),
    INDEX idx_email (email)
)");

if (rand(1, 20) === 1) {
    $db->exec("DELETE FROM tblPasswordResets WHERE expires_at < NOW()");
}

$message = '';
$isError = false;
$sent    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $db->prepare("SELECT id FROM tblUser WHERE Email=? AND IsActive=1 AND Status='active'");
        $stmt->execute([$email]);
        $userRow = $stmt->fetch();
        if ($userRow) {
            $token = bin2hex(random_bytes(32));
            $db->prepare("UPDATE tblPasswordResets SET used=1 WHERE email=?")->execute([$email]);
            $db->prepare("INSERT INTO tblPasswordResets (email,token,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))")
               ->execute([$email, $token]);

            $cfg    = getSmtpCfg();
            $appUrl = rtrim($cfg['_app_url'] ?? '', '/');
            if (!$appUrl) {
                $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $appUrl  = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(BASE_URL, '/');
            }
            $resetUrl = $appUrl . '/modules/users/reset_password.php?token=' . urlencode($token);

            $mailSent = sendSystemMail($email, 'HRMS — Password Reset Request',
                "<p>Hello,</p>"
              . "<p>A password reset was requested for your HRMS account.</p>"
              . "<p>Click the button below to set a new password (valid for <strong>30 minutes</strong>):</p>"
              . "<p style='text-align:center;margin:28px 0;'>"
              .   "<a href='{$resetUrl}' style='background:#1e2a3a;color:#fff;padding:12px 28px;"
              .       "text-decoration:none;border-radius:8px;font-weight:600;display:inline-block;'>"
              .     "Reset Password"
              .   "</a>"
              . "</p>"
              . "<p style='font-size:12px;color:#888;'>Or copy this link:<br>"
              .   "<a href='{$resetUrl}' style='color:#1e2a3a;'>{$resetUrl}</a></p>"
              . "<p style='color:#aaa;font-size:11px;'>If you did not request this, no action is needed.</p>"
            );
            if ($mailSent) {
                $sent    = true;
                $message = 'Reset link sent! Check your inbox (and spam folder).';
            } else {
                $isError = true;
                $message = 'Could not send the reset email. Please try again later or contact support.';
            }
        } else {
            $isError = true;
            $message = 'No active account found with that email address.';
        }
    } else {
        $isError = true;
        $message = 'Please enter a valid email address.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HRMS — Forgot Password</title>
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
    <h5 class="mt-2 fw-normal text-muted">Forgot Password</h5>
  </div>

  <?php if ($message): ?>
  <div class="alert <?= $isError ? 'alert-danger' : 'alert-success' ?> small py-2"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php if (!$sent): ?>
  <p class="text-muted small mb-3">Enter your registered email and we'll send you a link to reset your password.</p>
  <form method="POST" autocomplete="off">
    <div class="mb-3">
      <label class="form-label fw-semibold">Email Address</label>
      <input type="email" name="email" class="form-control" required autofocus
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>
    <button type="submit" class="btn btn-primary w-100">
      <i class="bi bi-envelope me-1"></i>Send Reset Link
    </button>
  </form>
  <?php endif; ?>

  <div class="text-center mt-3">
    <a href="<?= BASE_URL ?>/login.php" class="small text-muted text-decoration-none">
      <i class="bi bi-arrow-left me-1"></i>Back to Login
    </a>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
