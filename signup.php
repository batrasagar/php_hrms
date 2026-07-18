<?php
define('BASE_URL', preg_match('#(^|\.)hr\.attnlog\.in$#', $_SERVER['HTTP_HOST'] ?? '') ? '' : '/php_hrms');
require_once __DIR__ . '/config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php'); exit;
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']     ?? '');
    $email   = trim($_POST['email']    ?? '');
    $pass    = $_POST['password']      ?? '';
    $confirm = $_POST['confirm']       ?? '';

    if (!$name)                          $errors[] = 'Full name is required.';
    if (!$email)                         $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($pass) < 6)              $errors[] = 'Password must be at least 6 characters.';
    elseif ($pass !== $confirm)          $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $db   = getDb();
        $exists = $db->prepare("SELECT id FROM tblUser WHERE Email = ?");
        $exists->execute([$email]);
        if ($exists->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $db->prepare(
                "INSERT INTO tblUser (Name, Email, Password, Role, Status, CompanyLimit)
                 VALUES (?, ?, ?, 'admin', 'pending', 1)"
            )->execute([$name, $email, $hash]);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>HRMS — Request Access</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  body {
    min-height: 100vh;
    display: flex; align-items: center; justify-content: center; padding: 16px;
    background: linear-gradient(135deg, #e8f0fe 0%, #f0f4ff 50%, #e3f2fd 100%);
  }
  .card { width: 100%; max-width: 440px; border-radius: 14px; }
</style>
</head>
<body>
<div class="card shadow-lg p-4">
  <div class="text-center mb-4">
    <div class="fs-2 fw-bold text-primary"><i class="bi bi-building me-2"></i>HRMS</div>
    <div class="text-muted small">Request Account Access</div>
  </div>

  <?php if ($success): ?>
  <div class="alert alert-success text-center">
    <i class="bi bi-check-circle-fill me-2"></i>
    <strong>Request submitted!</strong><br>
    <span class="small">Your account is pending approval. You will be notified once it is reviewed.</span>
  </div>
  <a href="login.php" class="btn btn-outline-primary w-100">Back to Login</a>

  <?php else: ?>
  <?php foreach ($errors as $e): ?>
  <div class="alert alert-danger py-2"><?= htmlspecialchars($e) ?></div>
  <?php endforeach; ?>
  <form method="POST" autocomplete="off">
    <div class="mb-3">
      <label class="form-label">Full Name</label>
      <input type="text" name="name" class="form-control" required autofocus
             value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input type="email" name="email" class="form-control" required
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required>
      <div class="form-text">Minimum 6 characters.</div>
    </div>
    <div class="mb-4">
      <label class="form-label">Confirm Password</label>
      <input type="password" name="confirm" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-primary w-100 mb-3">Submit Request</button>
    <div class="text-center">
      <a href="login.php" class="text-decoration-none small">Already have an account? <strong>Login</strong></a>
    </div>
  </form>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
