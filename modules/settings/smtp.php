<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/smtp_helper.php';
requireAdmin();
requirePermission('notifications.view');

$pageTitle  = 'SMTP Settings';
$activePage = 'smtp_settings';

// Seed defaults + load current values
getSmtpCfg();
$db  = getDb();
$cfg = $db->query("SELECT SettingKey, SettingValue FROM tblSettings WHERE CompanyId = 0")->fetchAll(PDO::FETCH_KEY_PAIR);

$errors    = [];
$success   = '';
$testLog   = '';
$diagSteps = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('notifications.edit');
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        $fields = ['smtp_host','smtp_port','smtp_user','smtp_from','smtp_from_name','app_url'];
        foreach ($fields as $key) {
            $val = trim($_POST[$key] ?? '');
            if ($key === 'smtp_host' && !$val) { $errors[] = 'SMTP Host is required.'; continue; }
            if ($key === 'smtp_user' && !$val) { $errors[] = 'SMTP Username is required.'; continue; }
            if ($key === 'smtp_from' && !$val) { $errors[] = 'From Email is required.'; continue; }
            $db->prepare("INSERT INTO tblSettings (CompanyId,SettingKey,SettingValue) VALUES (0,?,?)
                          ON DUPLICATE KEY UPDATE SettingValue=VALUES(SettingValue)")
               ->execute([$key, $val]);
            $cfg[$key] = $val;
        }
        $newPass = $_POST['smtp_pass'] ?? '';
        if ($newPass !== '') {
            $db->prepare("INSERT INTO tblSettings (CompanyId,SettingKey,SettingValue) VALUES (0,?,?)
                          ON DUPLICATE KEY UPDATE SettingValue=VALUES(SettingValue)")
               ->execute(['smtp_pass', $newPass]);
            $cfg['smtp_pass'] = $newPass;
        }
        if (!$errors) $success = 'Settings saved successfully.';

    } elseif ($action === 'test') {
        $testTo = trim($_POST['test_email'] ?? '');
        if (!$testTo || !filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid test email address.';
        } else {
            $logFile = tempnam(sys_get_temp_dir(), 'hrms_mail_');
            $prevLog = ini_set('error_log', $logFile);
            $ok = sendSystemMail($testTo, 'HRMS — SMTP Test',
                '<p>This is a test email from <strong>AttnLog HRMS</strong> to verify your SMTP configuration.</p>'
              . '<p style="color:#555;">If you received this, your email settings are working correctly. &#10003;</p>'
            );
            ini_set('error_log', $prevLog);
            $testLog = @file_get_contents($logFile) ?: '';
            @unlink($logFile);
            if ($ok) {
                $success = 'Test email sent to <strong>' . htmlspecialchars($testTo) . '</strong> successfully.';
            } else {
                $errors[] = 'Failed to send. See details below.';
            }
        }

    } elseif ($action === 'test_reset') {
        $testTo = trim($_POST['reset_test_email'] ?? '');
        if (!$testTo || !filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address to test.';
        } else {
            $row = $db->prepare("SELECT id, Name FROM tblUser WHERE Email=? AND IsActive=1 AND Status='active'");
            $row->execute([$testTo]);
            $found = $row->fetch();
            if ($found) {
                $diagSteps[] = ['ok', 'User found: <strong>' . htmlspecialchars($found['Name']) . '</strong>'];
            } else {
                $diagSteps[] = ['fail', 'Email <strong>' . htmlspecialchars($testTo) . '</strong> not found or account not active.'];
                $errors[] = 'Email not found / not active. See diagnostic below.';
            }
            if ($found) {
                $logFile  = tempnam(sys_get_temp_dir(), 'hrms_reset_');
                $prevLog  = ini_set('error_log', $logFile);
                $smtpCfg  = getSmtpCfg();
                $appUrl   = rtrim($smtpCfg['_app_url'] ?? '', '/');
                if (!$appUrl) {
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $appUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(BASE_URL, '/');
                }
                $token    = bin2hex(random_bytes(32));
                $resetUrl = $appUrl . '/modules/users/reset_password.php?token=' . urlencode($token);
                $ok = sendSystemMail($testTo, 'HRMS — Password Reset (Test)',
                    "<p>This is a diagnostic test of the password reset email.</p>"
                  . "<p><a href='{$resetUrl}'>Reset Password (test link)</a></p>"
                );
                ini_set('error_log', $prevLog);
                $resetLog = @file_get_contents($logFile) ?: '';
                @unlink($logFile);
                if ($ok) {
                    $diagSteps[] = ['ok', 'Reset email sent to <strong>' . htmlspecialchars($testTo) . '</strong>.'];
                    $success = 'Diagnostic passed — check your inbox.';
                } else {
                    $diagSteps[] = ['fail', 'sendSystemMail() returned false. Error: <pre class="mb-0 mt-1" style="font-size:11px;">' . htmlspecialchars(trim($resetLog) ?: '(no details)') . '</pre>'];
                    $errors[] = 'SMTP send failed. See diagnostic below.';
                }
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="row g-4" style="max-width:700px;">

  <!-- SMTP Settings Card -->
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom fw-semibold py-3">
        <i class="bi bi-gear me-2"></i>SMTP Configuration
      </div>
      <div class="card-body">

        <?php if ($errors && ($_POST['action'] ?? '') === 'save'): ?>
        <div class="alert alert-danger small py-2">
          <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($success && ($_POST['action'] ?? '') === 'save'): ?>
        <div class="alert alert-success small py-2"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save">
          <div class="row g-3">
            <div class="col-8">
              <label class="form-label fw-semibold">SMTP Host</label>
              <input type="text" name="smtp_host" class="form-control" required
                     value="<?= htmlspecialchars($cfg['smtp_host'] ?? '') ?>"
                     placeholder="smtp.hostinger.com">
            </div>
            <div class="col-4">
              <label class="form-label fw-semibold">Port</label>
              <input type="number" name="smtp_port" class="form-control"
                     value="<?= htmlspecialchars($cfg['smtp_port'] ?? '587') ?>"
                     placeholder="587">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">SMTP Username</label>
              <input type="email" name="smtp_user" class="form-control" required
                     value="<?= htmlspecialchars($cfg['smtp_user'] ?? '') ?>"
                     placeholder="user@yourdomain.com">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">SMTP Password</label>
              <input type="password" name="smtp_pass" class="form-control"
                     placeholder="Leave blank to keep current">
              <?php if (!empty($cfg['smtp_pass'])): ?>
              <div class="form-text text-success"><i class="bi bi-check-circle me-1"></i>Password is set.</div>
              <?php else: ?>
              <div class="form-text text-danger"><i class="bi bi-exclamation-circle me-1"></i>No password saved yet.</div>
              <?php endif; ?>
            </div>
            <div class="col-8">
              <label class="form-label fw-semibold">From Email</label>
              <input type="email" name="smtp_from" class="form-control" required
                     value="<?= htmlspecialchars($cfg['smtp_from'] ?? '') ?>"
                     placeholder="noreply@yourdomain.com">
            </div>
            <div class="col-4">
              <label class="form-label fw-semibold">From Name</label>
              <input type="text" name="smtp_from_name" class="form-control"
                     value="<?= htmlspecialchars($cfg['smtp_from_name'] ?? 'AttnLog HRMS') ?>"
                     placeholder="AttnLog HRMS">
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">App URL</label>
              <input type="url" name="app_url" class="form-control"
                     value="<?= htmlspecialchars($cfg['app_url'] ?? '') ?>"
                     placeholder="https://hr.attnlog.in">
              <div class="form-text">Used in password reset links. Must be your public domain, not localhost.</div>
            </div>
          </div>
          <div class="mt-4">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-floppy me-1"></i>Save Settings
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Test Email Card -->
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom fw-semibold py-3">
        <i class="bi bi-send me-2"></i>Send Test Email
      </div>
      <div class="card-body">
        <?php if (($_POST['action'] ?? '') === 'test'): ?>
          <?php if ($success): ?>
          <div class="alert alert-success small py-2">
            <i class="bi bi-check-circle me-1"></i><?= $success ?>
          </div>
          <?php else: ?>
          <div class="alert alert-danger small py-2">
            <strong>Failed to send test email.</strong>
            <?php if ($testLog): ?>
            <pre class="mt-2 mb-0 small" style="white-space:pre-wrap;font-size:11px;"><?= htmlspecialchars(trim($testLog)) ?></pre>
            <?php else: ?>
            <div class="mt-1">No error details captured. Check the server PHP error log.</div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        <?php endif; ?>
        <form method="POST" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="test">
          <div class="d-flex gap-2 align-items-end">
            <div class="flex-grow-1">
              <label class="form-label fw-semibold">Send test to</label>
              <input type="email" name="test_email" class="form-control" required
                     value="<?= htmlspecialchars($_POST['test_email'] ?? '') ?>"
                     placeholder="you@example.com">
            </div>
            <button type="submit" class="btn btn-outline-primary">
              <i class="bi bi-send me-1"></i>Send Test
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Forgot Password Diagnostic Card -->
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom fw-semibold py-3">
        <i class="bi bi-bug me-2"></i>Diagnose Forgot Password Email
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">Checks if the email exists with Status=active, then tries sending the reset email and shows exactly what failed.</p>

        <?php if (!empty($diagSteps)): ?>
        <ul class="list-group list-group-flush mb-3 small">
          <?php foreach ($diagSteps as [$status, $msg]): ?>
          <li class="list-group-item px-0 d-flex align-items-start gap-2 border-0 py-1">
            <i class="bi bi-<?= $status === 'ok' ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?> mt-1 flex-shrink-0"></i>
            <span><?= $msg ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="test_reset">
          <div class="d-flex gap-2 align-items-end">
            <div class="flex-grow-1">
              <label class="form-label fw-semibold">Email to diagnose</label>
              <input type="email" name="reset_test_email" class="form-control" required
                     value="<?= htmlspecialchars($_POST['reset_test_email'] ?? '') ?>"
                     placeholder="user@example.com">
            </div>
            <button type="submit" class="btn btn-outline-warning">
              <i class="bi bi-bug me-1"></i>Run Diagnostic
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
