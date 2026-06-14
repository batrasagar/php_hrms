<?php
define('BASE_URL', ($_SERVER['HTTP_HOST'] ?? '') === 'hr.attnlog.in' ? '' : '/php_hrms');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/smtp_helper.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php'); exit;
}

// Clear OTP state on request (user switches to password tab)
if (isset($_GET['reset'])) {
    unset($_SESSION['otp_email']);
    header('Location: ' . BASE_URL . '/login.php'); exit;
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

$db->exec("CREATE TABLE IF NOT EXISTS tblEmailOtp (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(150) NOT NULL,
    otp        CHAR(6)      NOT NULL,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email)
)");

if (rand(1, 20) === 1) {
    $db->exec("DELETE FROM tblLoginAttempts WHERE AttemptAt < NOW() - INTERVAL " . BF_WINDOW_MINS . " MINUTE");
    $db->exec("DELETE FROM tblEmailOtp WHERE expires_at < NOW()");
}

$ip          = substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
$windowStart = date('Y-m-d H:i:s', strtotime('-' . BF_WINDOW_MINS . ' minutes'));

$cntStmt = $db->prepare("SELECT COUNT(*), MIN(AttemptAt) FROM tblLoginAttempts WHERE IpAddress=? AND AttemptAt >= ?");
$cntStmt->execute([$ip, $windowStart]);
[$attemptCount, $oldestAttempt] = $cntStmt->fetch(PDO::FETCH_NUM);
$attemptCount = (int)$attemptCount;

$isLocked = $attemptCount >= BF_MAX_ATTEMPTS;
$minsLeft = 0;
if ($isLocked && $oldestAttempt) {
    $unlockTs = strtotime($oldestAttempt) + BF_WINDOW_MINS * 60;
    $minsLeft = max(1, (int)ceil(($unlockTs - time()) / 60));
}

$error     = '';
$warning   = '';
$otpSent   = !empty($_SESSION['otp_email']);
$activeTab = $otpSent ? 'otp' : 'password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    // ── Password login ────────────────────────────────────────────────────
    if ($action === 'login') {
        $activeTab = 'password';
        if ($isLocked) {
            $error = "Too many failed login attempts. Please wait {$minsLeft} minute(s) before trying again.";
        } else {
            $email = trim($_POST['email'] ?? '');
            $pass  = $_POST['password'] ?? '';
            if ($email && $pass) {
                $stmt = $db->prepare(
                    "SELECT id, Name, Password, Role, Status, CompanyLimit, MachinesLimit, EmpLimit, ParentAdminId, CompanyId
                     FROM tblUser WHERE Email = ? AND IsActive = 1"
                );
                $stmt->execute([$email]);
                $row = $stmt->fetch();

                if ($row && password_verify($pass, $row['Password'])) {
                    $db->prepare("DELETE FROM tblLoginAttempts WHERE IpAddress=?")->execute([$ip]);
                    if ($row['Status'] === 'pending') {
                        $error = 'Your account is pending approval by the administrator.';
                    } elseif ($row['Status'] === 'rejected') {
                        $error = 'Your account registration was rejected. Please contact support.';
                    } else {
                        $_SESSION['user_id']               = $row['id'];
                        $_SESSION['user_name']             = $row['Name'];
                        $_SESSION['user_role']             = $row['Role'];
                        $_SESSION['user_company_limit']    = $row['CompanyLimit'];
                        $_SESSION['user_machines_limit']   = $row['MachinesLimit'];
                        $_SESSION['user_emp_limit']        = $row['EmpLimit'];
                        $_SESSION['user_parent_admin_id']  = $row['ParentAdminId'] ?? 0;
                        $_SESSION['user_company_id']       = $row['CompanyId'] ?? 0;
                        header('Location: index.php'); exit;
                    }
                } else {
                    $db->prepare("INSERT INTO tblLoginAttempts (IpAddress, Email) VALUES (?,?)")->execute([$ip, $email]);
                    $attemptCount++;
                    $remaining = BF_MAX_ATTEMPTS - $attemptCount;
                    if ($remaining <= 0) {
                        $isLocked = true; $minsLeft = BF_WINDOW_MINS;
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

    // ── Send OTP ──────────────────────────────────────────────────────────
    } elseif ($action === 'send_otp') {
        $activeTab = 'otp';
        if ($isLocked) {
            $error = "Too many failed attempts. Please wait {$minsLeft} minute(s) before trying again.";
        } else {
            $email = strtolower(trim($_POST['otp_email'] ?? ''));
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                $stmt = $db->prepare("SELECT id FROM tblUser WHERE Email=? AND IsActive=1 AND Status='active'");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $db->prepare("DELETE FROM tblEmailOtp WHERE email=?")->execute([$email]);
                    $db->prepare("INSERT INTO tblEmailOtp (email,otp,expires_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))")
                       ->execute([$email, $otp]);
                    $ok = sendSystemMail($email, 'HRMS — Your Login OTP',
                        "<p>Hello,</p>"
                      . "<p>Your one-time login code is:</p>"
                      . "<div style='text-align:center;margin:24px 0;'>"
                      .   "<span style='font-size:2.5rem;font-weight:700;letter-spacing:0.3rem;color:#1e2a3a;'>{$otp}</span>"
                      . "</div>"
                      . "<p style='color:#888;font-size:12px;'>This code expires in <strong>10 minutes</strong>. Do not share it with anyone.</p>"
                    );
                    if ($ok) {
                        $_SESSION['otp_email'] = $email;
                        $otpSent = true;
                    } else {
                        $error = 'Could not send OTP email. Please try again or use password login.';
                    }
                } else {
                    usleep(100000); // timing parity to avoid email enumeration
                    $error = 'No active account found with that email.';
                }
            }
        }

    // ── Verify OTP ────────────────────────────────────────────────────────
    } elseif ($action === 'verify_otp') {
        $activeTab = 'otp';
        $otpSent   = true;
        $email     = $_SESSION['otp_email'] ?? '';
        if (!$email) {
            $error   = 'Session expired. Please request a new OTP.';
            $otpSent = false;
        } elseif ($isLocked) {
            $error = "Too many failed attempts. Please wait {$minsLeft} minute(s) before trying again.";
        } else {
            $code = trim(preg_replace('/\D/', '', $_POST['otp_code'] ?? ''));
            $stmt = $db->prepare("SELECT id FROM tblEmailOtp WHERE email=? AND otp=? AND used=0 AND expires_at>NOW()");
            $stmt->execute([$email, $code]);
            if ($stmt->fetch()) {
                $db->prepare("UPDATE tblEmailOtp SET used=1 WHERE email=?")->execute([$email]);
                $db->prepare("DELETE FROM tblLoginAttempts WHERE IpAddress=?")->execute([$ip]);
                $uStmt = $db->prepare("SELECT id,Name,Role,Status,CompanyLimit,MachinesLimit,EmpLimit,ParentAdminId,CompanyId FROM tblUser WHERE Email=? AND IsActive=1");
                $uStmt->execute([$email]);
                $row = $uStmt->fetch();
                if ($row && $row['Status'] === 'active') {
                    unset($_SESSION['otp_email']);
                    $_SESSION['user_id']               = $row['id'];
                    $_SESSION['user_name']             = $row['Name'];
                    $_SESSION['user_role']             = $row['Role'];
                    $_SESSION['user_company_limit']    = $row['CompanyLimit'];
                    $_SESSION['user_machines_limit']   = $row['MachinesLimit'];
                    $_SESSION['user_emp_limit']        = $row['EmpLimit'];
                    $_SESSION['user_parent_admin_id']  = $row['ParentAdminId'] ?? 0;
                    $_SESSION['user_company_id']       = $row['CompanyId'] ?? 0;
                    header('Location: index.php'); exit;
                } else {
                    $error = 'Account not active. Contact your administrator.';
                }
            } else {
                $db->prepare("INSERT INTO tblLoginAttempts (IpAddress, Email) VALUES (?,?)")->execute([$ip, $email]);
                $attemptCount++;
                $remaining = BF_MAX_ATTEMPTS - $attemptCount;
                if ($remaining <= 0) {
                    $isLocked = true; $minsLeft = BF_WINDOW_MINS;
                    $error = "Too many failed attempts. Please wait {$minsLeft} minute(s).";
                } else {
                    $error = 'Invalid or expired OTP.' . ($remaining <= 2 ? " ({$remaining} attempt(s) left)" : '');
                }
            }
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
  .nav-tabs .nav-link { color: #6e6e73; }
  .nav-tabs .nav-link.active { font-weight: 600; color: #0d6efd; }
</style>
</head>
<body>
<div class="card login-card shadow-lg p-4">
  <div class="text-center mb-4">
    <div class="fs-2 fw-bold text-primary"><i class="bi bi-building me-2"></i>HRMS</div>
    <div class="text-muted small">Human Resource Management System</div>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($warning): ?>
  <div class="alert alert-warning py-2 small"><?= htmlspecialchars($warning) ?></div>
  <?php endif; ?>

  <!-- Tab nav -->
  <ul class="nav nav-tabs mb-3" id="loginTabs">
    <li class="nav-item">
      <a class="nav-link <?= $activeTab === 'password' ? 'active' : '' ?>"
         href="<?= BASE_URL ?>/login.php<?= $otpSent ? '?reset=1' : '' ?>"
         id="tabPassword" data-pane="panePassword">
        <i class="bi bi-lock me-1"></i>Password
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $activeTab === 'otp' ? 'active' : '' ?>"
         href="#" id="tabOtp" data-pane="paneOtp">
        <i class="bi bi-envelope me-1"></i>Email OTP
      </a>
    </li>
  </ul>

  <!-- Password pane -->
  <div id="panePassword" <?= $activeTab !== 'password' ? 'class="d-none"' : '' ?>>
    <form method="POST" autocomplete="off">
      <input type="hidden" name="action" value="login">
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required
               <?= $activeTab === 'password' ? 'autofocus' : '' ?>
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100 mb-2" <?= $isLocked ? 'disabled' : '' ?>>
        <i class="bi bi-box-arrow-in-right me-1"></i>Login
      </button>
    </form>
    <div class="text-center small">
      <a href="<?= BASE_URL ?>/modules/users/forgot_password.php" class="text-decoration-none text-muted">
        Forgot password?
      </a>
    </div>
  </div>

  <!-- OTP pane -->
  <div id="paneOtp" <?= $activeTab !== 'otp' ? 'class="d-none"' : '' ?>>
    <?php if (!$otpSent): ?>
    <form method="POST" autocomplete="off">
      <input type="hidden" name="action" value="send_otp">
      <div class="mb-3">
        <label class="form-label">Email Address</label>
        <input type="email" name="otp_email" class="form-control" required
               value="<?= htmlspecialchars($_POST['otp_email'] ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-primary w-100" <?= $isLocked ? 'disabled' : '' ?>>
        <i class="bi bi-send me-1"></i>Send OTP
      </button>
    </form>
    <?php else: ?>
    <p class="small text-muted mb-3">
      OTP sent to <strong><?= htmlspecialchars($_SESSION['otp_email'] ?? '') ?></strong>.
      Check your inbox and spam folder.
    </p>
    <form method="POST" autocomplete="off">
      <input type="hidden" name="action" value="verify_otp">
      <div class="mb-3">
        <label class="form-label">Enter 6-digit OTP</label>
        <input type="text" name="otp_code" class="form-control form-control-lg text-center"
               maxlength="6" pattern="\d{6}" inputmode="numeric" required autofocus
               placeholder="••••••">
      </div>
      <button type="submit" class="btn btn-success w-100 mb-2" <?= $isLocked ? 'disabled' : '' ?>>
        <i class="bi bi-check-circle me-1"></i>Verify OTP
      </button>
    </form>
    <div class="text-center">
      <a href="<?= BASE_URL ?>/login.php?reset=1" class="small text-muted text-decoration-none">
        <i class="bi bi-arrow-repeat me-1"></i>Use a different email
      </a>
    </div>
    <?php endif; ?>
  </div>

  <hr class="my-3">
  <div class="text-center">
    <a href="<?= BASE_URL ?>/signup.php" class="text-decoration-none small">
      Don't have an account? <strong>Request Access</strong>
    </a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  var tabs = document.querySelectorAll('#loginTabs .nav-link');
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function (e) {
      var paneId = this.getAttribute('data-pane');
      // Password tab goes to ?reset=1 when OTP session is active (server handles it)
      <?php if ($otpSent): ?>
      if (this.id === 'tabPassword') return; // let href navigate to ?reset=1
      <?php endif; ?>
      e.preventDefault();
      tabs.forEach(function (t) { t.classList.remove('active'); });
      document.querySelectorAll('#panePassword,#paneOtp').forEach(function (p) { p.classList.add('d-none'); });
      this.classList.add('active');
      document.getElementById(paneId).classList.remove('d-none');
    });
  });
})();
</script>
</body>
</html>
