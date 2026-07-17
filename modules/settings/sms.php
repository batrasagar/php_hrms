<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sms_helper.php';
requireAdmin();
requirePermission('sms_settings.view');

$pageTitle  = 'SMS Settings';
$activePage = 'sms_settings';

getSmsCfg(); // seed defaults
$db  = getDb();
$cfg = $db->query("SELECT SettingKey, SettingValue FROM tblSettings WHERE CompanyId = 0")->fetchAll(PDO::FETCH_KEY_PAIR);

$errors = []; $success = ''; $testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('sms_settings.edit');
    csrf_verify();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        foreach (['msg91_sender','msg91_template_id','msg91_var','msg91_tpl_ot_entered','msg91_tpl_ot_approved'] as $key) {
            $val = trim($_POST[$key] ?? '');
            $db->prepare("INSERT INTO tblSettings (CompanyId,SettingKey,SettingValue) VALUES (0,?,?)
                          ON DUPLICATE KEY UPDATE SettingValue=VALUES(SettingValue)")->execute([$key, $val]);
            $cfg[$key] = $val;
        }
        $newKey = trim($_POST['msg91_authkey'] ?? '');
        if ($newKey !== '') {
            $db->prepare("INSERT INTO tblSettings (CompanyId,SettingKey,SettingValue) VALUES (0,'msg91_authkey',?)
                          ON DUPLICATE KEY UPDATE SettingValue=VALUES(SettingValue)")->execute([$newKey]);
            $cfg['msg91_authkey'] = $newKey;
        }
        $success = 'SMS settings saved.';

    } elseif ($action === 'test') {
        $to = trim($_POST['test_mobile'] ?? '');
        if (!$to) { $errors[] = 'Enter a mobile number to test.'; }
        else {
            // getSmsCfg is statically cached; re-read fresh in a new request cycle would be needed,
            // so send using the just-saved values already reflected in tblSettings on next load.
            $testResult = sendSms($to, 'HRMS test SMS — your MSG91 configuration is working.');
            if (!empty($testResult['ok'])) $success = 'Test SMS sent to ' . htmlspecialchars($to) . '.';
            else $errors[] = 'Test failed: ' . ($testResult['error'] ?? 'unknown error');
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="row g-4" style="max-width:700px;">
  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom fw-semibold py-3"><i class="bi bi-chat-dots me-2"></i>MSG91 SMS Configuration</div>
      <div class="card-body">
        <?php if ($errors): ?><div class="alert alert-danger small py-2"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success small py-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <form method="POST" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">MSG91 Auth Key</label>
              <input type="password" name="msg91_authkey" class="form-control" placeholder="Leave blank to keep current">
              <?php if (!empty($cfg['msg91_authkey'])): ?>
              <div class="form-text text-success"><i class="bi bi-check-circle me-1"></i>Auth key is set.</div>
              <?php else: ?>
              <div class="form-text text-danger"><i class="bi bi-exclamation-circle me-1"></i>No auth key saved yet.</div>
              <?php endif; ?>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Sender ID</label>
              <input type="text" name="msg91_sender" class="form-control" value="<?= htmlspecialchars($cfg['msg91_sender'] ?? 'HRMSAP') ?>" placeholder="HRMSAP" maxlength="6">
              <div class="form-text">6-char DLT-approved sender.</div>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Variable Name</label>
              <input type="text" name="msg91_var" class="form-control" value="<?= htmlspecialchars($cfg['msg91_var'] ?? 'var') ?>" placeholder="var">
              <div class="form-text">The variable inside your template that receives the message text.</div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Generic Flow Template ID</label>
              <input type="text" name="msg91_template_id" class="form-control" value="<?= htmlspecialchars($cfg['msg91_template_id'] ?? '') ?>" placeholder="e.g. 6512ab...">
              <div class="form-text">Single-variable fallback template, e.g. <code>HRMS: ##var##</code> — used for test SMS and whenever a structured template below is not set.</div>
            </div>

            <div class="col-12"><hr class="my-1"><div class="fw-semibold small text-muted">Overtime notifications (structured templates)</div></div>
            <div class="col-12">
              <label class="form-label fw-semibold">OT Entered — Template ID</label>
              <input type="text" name="msg91_tpl_ot_entered" class="form-control" value="<?= htmlspecialchars($cfg['msg91_tpl_ot_entered'] ?? '') ?>" placeholder="Flow template id">
              <div class="form-text">Variables: <code>##company##</code>, <code>##count##</code>, <code>##hours##</code>, <code>##date##</code>, <code>##status##</code>.<br>
                e.g. <code>HRMS: OT entered for ##count## staff (##hours## hrs) on ##date## at ##company##. ##status##</code></div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">OT Approved — Template ID</label>
              <input type="text" name="msg91_tpl_ot_approved" class="form-control" value="<?= htmlspecialchars($cfg['msg91_tpl_ot_approved'] ?? '') ?>" placeholder="Flow template id">
              <div class="form-text">Variables: <code>##company##</code>, <code>##count##</code>, <code>##hours##</code>.<br>
                e.g. <code>HRMS: OT approved - ##count## entries (##hours## hrs) at ##company##.</code></div>
            </div>
          </div>
          <div class="mt-4"><button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i>Save Settings</button></div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white border-bottom fw-semibold py-3"><i class="bi bi-send me-2"></i>Send Test SMS</div>
      <div class="card-body">
        <form method="POST" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="test">
          <div class="d-flex gap-2 align-items-end">
            <div class="flex-grow-1">
              <label class="form-label fw-semibold">Send test to (mobile)</label>
              <input type="text" name="test_mobile" class="form-control" required value="<?= htmlspecialchars($_POST['test_mobile'] ?? '') ?>" placeholder="10-digit mobile">
            </div>
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-send me-1"></i>Send Test</button>
          </div>
          <div class="form-text mt-2">Save your settings first — the test uses the saved auth key.</div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
