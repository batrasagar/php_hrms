<?php
define('BASE_URL', ($_SERVER['HTTP_HOST'] ?? '') === 'hr.attnlog.in' ? '' : '/php_hrms');
require_once __DIR__ . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php'); exit;
}

// ── Brute-force config ────────────────────────────────────────────────────
const BF_MAX_ATTEMPTS = 5;
const BF_WINDOW_MINS  = 15;

$db = getDb();
$db->exec("CREATE TABLE IF NOT EXISTS tblLoginAttempts (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    IpAddress VARCHAR(45)  NOT NULL,
    Email     VARCHAR(150) NOT NULL DEFAULT '',
    AttemptAt DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (IpAddress, AttemptAt)
) ENGINE=InnoDB");

// Probabilistic cleanup of old rows (5% of requests)
if (rand(1, 20) === 1) {
    $db->exec("DELETE FROM tblLoginAttempts WHERE AttemptAt < NOW() - INTERVAL " . BF_WINDOW_MINS . " MINUTE");
}

$ip          = substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
$windowStart = date('Y-m-d H:i:s', strtotime('-' . BF_WINDOW_MINS . ' minutes'));

$cntStmt = $db->prepare("SELECT COUNT(*), MIN(AttemptAt) FROM tblLoginAttempts WHERE IpAddress=? AND AttemptAt >= ?");
$cntStmt->execute([$ip, $windowStart]);
[$attemptCount, $oldestAttempt] = $cntStmt->fetch(PDO::FETCH_NUM);
$attemptCount = (int)$attemptCount;

$isLocked   = $attemptCount >= BF_MAX_ATTEMPTS;
$minsLeft   = 0;
if ($isLocked && $oldestAttempt) {
    $unlockTs = strtotime($oldestAttempt) + BF_WINDOW_MINS * 60;
    $minsLeft = max(1, (int)ceil(($unlockTs - time()) / 60));
}

$error   = '';
$warning = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isLocked) {
        $error = "Too many failed login attempts. Please wait {$minsLeft} minute(s) before trying again.";
    } else {
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        if ($email && $pass) {
            $stmt = $db->prepare(
                "SELECT id, Name, Password, Role, Status, CompanyLimit, ParentAdminId, CompanyId
                 FROM tblUser WHERE Email = ? AND IsActive = 1"
            );
            $stmt->execute([$email]);
            $row = $stmt->fetch();

            if ($row && password_verify($pass, $row['Password'])) {
                // Success — clear attempts for this IP
                $db->prepare("DELETE FROM tblLoginAttempts WHERE IpAddress=?")->execute([$ip]);

                if ($row['Status'] === 'pending') {
                    $error = 'Your account is pending approval by the administrator.';
                } elseif ($row['Status'] === 'rejected') {
                    $error = 'Your account registration was rejected. Please contact support.';
                } else {
                    $_SESSION['user_id']              = $row['id'];
                    $_SESSION['user_name']            = $row['Name'];
                    $_SESSION['user_role']            = $row['Role'];
                    $_SESSION['user_company_limit']   = $row['CompanyLimit'];
                    $_SESSION['user_parent_admin_id'] = $row['ParentAdminId'] ?? 0;
                    $_SESSION['user_company_id']      = $row['CompanyId'] ?? 0;
                    header('Location: index.php'); exit;
                }
            } else {
                // Failed attempt — record it
                $db->prepare("INSERT INTO tblLoginAttempts (IpAddress, Email) VALUES (?,?)")->execute([$ip, $email]);
                $attemptCount++;
                $remaining = BF_MAX_ATTEMPTS - $attemptCount;
                if ($remaining <= 0) {
                    $isLocked = true;
                    $minsLeft = BF_WINDOW_MINS;
                    $error = "Too many failed login attempts. Please wait {$minsLeft} minute(s) before trying again.";
                } elseif ($remaining <= 2) {
                    $error   = 'Invalid email or password.';
                    $warning = "Warning: {$remaining} attempt(s) remaining before your IP is temporarily locked.";
                } else {
                    $error = 'Invalid email or password.';
                }
            }
        } else {
            $error = 'Please enter your email and password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HRMS — Login</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body {
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center; padding: 16px;
    background: linear-gradient(135deg, #e8f0fe 0%, #f0f4ff 50%, #e3f2fd 100%);
  }
  .login-card { width: 100%; max-width: 400px; border-radius: 14px; }
</style>
</head>
<body>
<div class="card login-card shadow-lg p-4">
  <div class="text-center mb-4">
    <div class="fs-2 fw-bold text-primary"><i class="bi bi-building me-2"></i>HRMS</div>
    <div class="text-muted small">Human Resource Management System</div>
  </div>
  <?php if ($error): ?>
  <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($warning): ?>
  <div class="alert alert-warning py-2"><?= htmlspecialchars($warning) ?></div>
  <?php endif; ?>
  <form method="POST" autocomplete="off">
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" required autofocus
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary w-100 mb-3" <?= $isLocked ? 'disabled' : '' ?>>Login</button>
    <div class="text-center">
      <a href="signup.php" class="text-decoration-none small">Don't have an account? <strong>Request Access</strong></a>
    </div>
  </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
