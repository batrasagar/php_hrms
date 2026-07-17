<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/whatsapp_helper.php';
requireAdmin();
requirePermission('whatsapp_settings.view');

$db   = getDb();
$user = currentUser();
$isSuper = $user['role'] === 'superadmin';

try { $db->query("SELECT 1 FROM tblWhatsappSettings LIMIT 1"); }
catch (PDOException $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

// ── Scope options (which channel row we're editing) ─────────────────────────────
// Company comes from the global topbar switcher; superadmin can additionally
// edit the System Default channel (CompanyId 0).
$activeCo = activeCompanyId($db, $user);
$scopeOptions = $isSuper ? [0 => 'System Default (all companies)'] : [];
if ($activeCo) {
    $cn = $db->prepare("SELECT Name FROM tblCompany WHERE id=?");
    $cn->execute([$activeCo]);
    if ($n = $cn->fetchColumn()) $scopeOptions[$activeCo] = $n;
}
$scopeIds = array_keys($scopeOptions);
$fScope   = $_REQUEST['scope'] ?? '';
$fScope   = ($fScope === '' || !in_array((int)$fScope, $scopeIds, true)) ? ($scopeIds[0] ?? -1) : (int)$fScope;

$msg = ''; $msgType = 'success'; $testResult = null;

// ── POST actions ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($fScope, $scopeIds, true)) {
    requirePermission('whatsapp_settings.edit');
    csrf_verify();
    $action = $_POST['action'] ?? 'save';
    $existing = waGetSettings($db, $fScope) ?: [];

    if ($action === 'save') {
        $provider = in_array($_POST['provider'] ?? '', ['meta','aisensy','gupshup'], true) ? $_POST['provider'] : 'meta';
        // Non-secret fields
        $fields = [
            'MetaPhoneNumberId'      => trim($_POST['meta_phone_number_id'] ?? ''),
            'MetaBusinessId'         => trim($_POST['meta_business_id'] ?? ''),
            'MetaAppId'              => trim($_POST['meta_app_id'] ?? ''),
            'MetaApiVersion'         => trim($_POST['meta_api_version'] ?? '') ?: 'v25.0',
            'MetaWebhookVerifyToken' => trim($_POST['meta_webhook_verify_token'] ?? ''),
            'AisensySourceName'      => trim($_POST['aisensy_source_name'] ?? ''),
            'GupshupSource'          => trim($_POST['gupshup_source'] ?? ''),
            'GupshupAppName'         => trim($_POST['gupshup_app_name'] ?? ''),
        ];
        // Secrets: keep existing unless a new value is supplied
        $secrets = [
            'MetaAccessToken' => trim($_POST['meta_access_token'] ?? ''),
            'MetaAppSecret'   => trim($_POST['meta_app_secret'] ?? ''),
            'AisensyApiKey'   => trim($_POST['aisensy_api_key'] ?? ''),
            'GupshupApiKey'   => trim($_POST['gupshup_api_key'] ?? ''),
        ];
        foreach ($secrets as $k => $v) $fields[$k] = $v !== '' ? $v : ($existing[$k] ?? null);
        $fields['Provider'] = $provider;
        $fields['Enabled']  = isset($_POST['enabled']) ? 1 : 0;

        $cols = array_keys($fields);
        $set  = implode(',', array_map(fn($c) => "`$c`=?", $cols));
        $vals = array_values($fields);
        if ($existing) {
            $db->prepare("UPDATE tblWhatsappSettings SET $set, UpdatedAt=NOW() WHERE CompanyId=?")->execute([...$vals, $fScope]);
        } else {
            $db->prepare("INSERT INTO tblWhatsappSettings (`CompanyId`,`" . implode('`,`',$cols) . "`) VALUES (?" . str_repeat(',?',count($cols)) . ")")
               ->execute([$fScope, ...$vals]);
        }
        // Global scope: also persist the 2FA WhatsApp OTP template (in tblSettings).
        if ($fScope === 0) {
            $db->exec("CREATE TABLE IF NOT EXISTS tblSettings (
                id INT PRIMARY KEY AUTO_INCREMENT, CompanyId INT NOT NULL DEFAULT 0,
                SettingKey VARCHAR(100) NOT NULL, SettingValue VARCHAR(500) NOT NULL DEFAULT '',
                UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_cs (CompanyId, SettingKey))");
            $ins = $db->prepare("INSERT INTO tblSettings (CompanyId,SettingKey,SettingValue) VALUES (0,?,?)
                                 ON DUPLICATE KEY UPDATE SettingValue=VALUES(SettingValue)");
            $ins->execute(['wa_otp_template', trim($_POST['wa_otp_template'] ?? '')]);
            $ins->execute(['wa_otp_lang',     trim($_POST['wa_otp_lang'] ?? '') ?: 'en']);
        }
        $msg = 'WhatsApp settings saved.';

    } elseif ($action === 'revert' && $fScope !== 0) {
        $db->prepare("DELETE FROM tblWhatsappSettings WHERE CompanyId=?")->execute([$fScope]);
        $msg = 'Removed this company\'s channel. The system default is now in effect.';

    } elseif ($action === 'test') {
        $cfg = waActiveFor($db, $fScope);
        if (!$cfg) { $msg = 'No active WhatsApp channel (enable + configure one, then save).'; $msgType = 'danger'; }
        else {
            $r = waSendTemplate($cfg, trim($_POST['test_phone'] ?? ''), trim($_POST['test_template'] ?? 'hello_world') ?: 'hello_world', trim($_POST['test_lang'] ?? 'en_US') ?: 'en_US');
            $testResult = $r; $msg = $r['message']; $msgType = $r['ok'] ? 'success' : 'danger';
        }
    } elseif ($action === 'test_template') {
        $cfg = waActiveFor($db, $fScope);
        if (!$cfg) { $msg = 'No active WhatsApp channel.'; $msgType = 'danger'; }
        else {
            $params = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $_POST['tpl_params'] ?? '')), fn($p)=>$p!==''));
            $btns   = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $_POST['tpl_buttons'] ?? '')), fn($p)=>$p!==''));
            $r = waSendTemplate($cfg, trim($_POST['tpl_phone'] ?? ''), trim($_POST['tpl_name'] ?? ''), trim($_POST['tpl_lang'] ?? 'en') ?: 'en', $params, $btns);
            $testResult = $r; $msg = $r['message']; $msgType = $r['ok'] ? 'success' : 'danger';
        }
    }
}

// ── Load current row + active source ────────────────────────────────────────────
$row = in_array($fScope, $scopeIds, true) ? (waGetSettings($db, $fScope) ?: []) : [];
$def = waGetSettings($db, 0);
$defActive = $def && (int)$def['Enabled'] === 1 && waIsUsable($def);
if ($fScope === 0) {
    $activeSource = $defActive ? 'default_self' : 'none';
} else {
    if ($row && (int)($row['Enabled'] ?? 0) === 1 && waIsUsable($row)) $activeSource = 'company';
    elseif ($defActive)                                                 $activeSource = 'default';
    else                                                                $activeSource = 'none';
}
$v = fn($k, $d = '') => htmlspecialchars((string)($row[$k] ?? $d), ENT_QUOTES);
$provider = $row['Provider'] ?? 'meta';

// 2FA WhatsApp OTP template (global setting)
$waOtp = ['tpl' => '', 'lang' => 'en'];
try {
    $s = $db->query("SELECT SettingKey, SettingValue FROM tblSettings WHERE CompanyId=0 AND SettingKey IN ('wa_otp_template','wa_otp_lang')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $waOtp['tpl']  = $s['wa_otp_template'] ?? '';
    $waOtp['lang'] = ($s['wa_otp_lang'] ?? '') ?: 'en';
} catch (\Throwable $e) {}

$pageTitle  = 'WhatsApp Settings';
$activePage = 'whatsapp_settings';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.wa-prov{display:flex;gap:8px;flex-wrap:wrap}
.wa-fields{display:none} .wa-fields.show{display:block}
</style>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0">WhatsApp Settings</h5>
    <div class="text-muted small">Configure WhatsApp Business API for notifications &amp; approvals.</div>
  </div>
  <?php if (count($scopeOptions) > 1): ?>
  <form method="GET">
    <select name="scope" class="form-select form-select-sm" style="min-width:220px" onchange="this.form.submit()">
      <?php foreach ($scopeOptions as $id => $name): ?>
      <option value="<?= $id ?>" <?= $fScope==$id?'selected':'' ?>><?= htmlspecialchars($name) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php endif; ?>
</div>

<?php if (empty($scopeIds)): ?>
<div class="alert alert-warning">You have no companies to configure. Create a company first.</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; exit; endif; ?>

<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="alert <?= $activeSource==='company'||$activeSource==='default_self'?'alert-success':($activeSource==='default'?'alert-info':'alert-warning') ?> py-2" data-no-toast>
  <?php if ($activeSource==='company'): ?>✓ Using <strong>this company's</strong> WhatsApp channel.
  <?php elseif ($activeSource==='default_self'): ?>✓ The <strong>system default</strong> channel is enabled and in effect for companies without their own.
  <?php elseif ($activeSource==='default'): ?>Using the <strong>system default</strong> channel. Define one below to override it for this company.
  <?php else: ?>No WhatsApp channel is active. Configure and enable one below.<?php endif; ?>
</div>

<?php
// Diagnostic status for the channel currently being edited.
$rowEnabled = (int)($row['Enabled'] ?? 0) === 1;
$rowUsable  = $row ? waIsUsable($row) : false;
$missing = [];
if ($row) {
    switch ($row['Provider'] ?? 'meta') {
        case 'aisensy': if (empty($row['AisensyApiKey'])) $missing[] = 'API Key'; break;
        case 'gupshup': if (empty($row['GupshupApiKey'])) $missing[] = 'API Key'; if (empty($row['GupshupSource'])) $missing[] = 'Source number'; break;
        default: if (empty($row['MetaPhoneNumberId'])) $missing[] = 'Phone Number ID'; if (empty($row['MetaAccessToken'])) $missing[] = 'Access Token';
    }
}
?>
<div class="card border-0 shadow-sm mb-3"><div class="card-body py-2 small d-flex flex-wrap gap-3 align-items-center">
  <span><strong>Enabled:</strong> <?= $rowEnabled ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>' ?></span>
  <span><strong>Credentials:</strong> <?= $rowUsable ? '<span class="text-success">complete</span>' : '<span class="text-danger">incomplete'.($missing?' — missing '.htmlspecialchars(implode(', ', $missing)):'').'</span>' ?></span>
  <span><strong>OTP template:</strong> <?= $waOtp['tpl'] !== '' ? '<span class="text-success">'.htmlspecialchars($waOtp['tpl']).'</span>' : '<span class="text-danger">not set</span>' ?></span>
  <?php if ($rowEnabled && !$rowUsable): ?><span class="text-danger w-100">Enabled but it will not send until the missing credential(s) are entered.</span><?php endif; ?>
  <?php if (!$rowEnabled && $rowUsable): ?><span class="text-warning w-100">Credentials look complete — tick <strong>Enable</strong> and Save to activate.</span><?php endif; ?>
</div></div>

<form method="POST" action="whatsapp.php?scope=<?= $fScope ?>" autocomplete="off" style="max-width:820px">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="mb-3">
        <label class="form-label">Provider</label>
        <div class="wa-prov" id="waProv">
          <?php foreach (['meta'=>'Direct Meta (WABA)','aisensy'=>'AiSensy','gupshup'=>'GupShup'] as $pk=>$pl): ?>
          <input type="radio" class="btn-check" name="provider" id="prov_<?= $pk ?>" value="<?= $pk ?>" <?= $provider===$pk?'checked':'' ?> onchange="waShow('<?= $pk ?>')">
          <label class="btn btn-outline-primary btn-sm" for="prov_<?= $pk ?>"><?= $pl ?></label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Meta -->
      <div class="wa-fields <?= $provider==='meta'?'show':'' ?>" data-prov="meta">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Phone Number ID</label>
            <input name="meta_phone_number_id" class="form-control" value="<?= $v('MetaPhoneNumberId') ?>" placeholder="123456789012345"></div>
          <div class="col-md-6"><label class="form-label">Business Account ID</label>
            <input name="meta_business_id" class="form-control" value="<?= $v('MetaBusinessId') ?>"></div>
          <div class="col-12"><label class="form-label">Access Token (WABA)</label>
            <input name="meta_access_token" type="password" class="form-control" placeholder="<?= !empty($row['MetaAccessToken'])?'•••• set — leave blank to keep':'Enter access token' ?>"></div>
          <div class="col-md-6"><label class="form-label">App ID</label>
            <input name="meta_app_id" class="form-control" value="<?= $v('MetaAppId') ?>"></div>
          <div class="col-md-6"><label class="form-label">API Version</label>
            <input name="meta_api_version" class="form-control" value="<?= $v('MetaApiVersion','v25.0') ?>" placeholder="v25.0"></div>
          <div class="col-md-6"><label class="form-label">App Secret</label>
            <input name="meta_app_secret" type="password" class="form-control" placeholder="<?= !empty($row['MetaAppSecret'])?'•••• set — leave blank to keep':'Optional' ?>"></div>
          <div class="col-md-6"><label class="form-label">Webhook Verify Token</label>
            <input name="meta_webhook_verify_token" class="form-control" value="<?= $v('MetaWebhookVerifyToken') ?>" placeholder="Random verification string"></div>
        </div>
      </div>

      <!-- AiSensy -->
      <div class="wa-fields <?= $provider==='aisensy'?'show':'' ?>" data-prov="aisensy">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">API Key</label>
            <input name="aisensy_api_key" type="password" class="form-control" placeholder="<?= !empty($row['AisensyApiKey'])?'•••• set — leave blank to keep':'AiSensy API key' ?>"></div>
          <div class="col-md-6"><label class="form-label">Source Name (Sender)</label>
            <input name="aisensy_source_name" class="form-control" value="<?= $v('AisensySourceName') ?>"></div>
        </div>
      </div>

      <!-- GupShup -->
      <div class="wa-fields <?= $provider==='gupshup'?'show':'' ?>" data-prov="gupshup">
        <div class="row g-3">
          <div class="col-12"><label class="form-label">API Key</label>
            <input name="gupshup_api_key" type="password" class="form-control" placeholder="<?= !empty($row['GupshupApiKey'])?'•••• set — leave blank to keep':'GupShup API key' ?>"></div>
          <div class="col-md-6"><label class="form-label">Source (Sender Number)</label>
            <input name="gupshup_source" class="form-control" value="<?= $v('GupshupSource') ?>" placeholder="e.g. 917834811114"></div>
          <div class="col-md-6"><label class="form-label">App Name</label>
            <input name="gupshup_app_name" class="form-control" value="<?= $v('GupshupAppName') ?>"></div>
        </div>
      </div>

      <?php if ($fScope === 0): ?>
      <hr>
      <div class="fw-semibold mb-2 small">2FA OTP template <span class="text-muted fw-normal">— for WhatsApp login codes</span></div>
      <div class="row g-3">
        <div class="col-md-8"><label class="form-label">OTP Template Name</label>
          <input name="wa_otp_template" class="form-control" value="<?= htmlspecialchars($waOtp['tpl']) ?>" placeholder="e.g. login_otp (approved authentication template)"></div>
        <div class="col-md-4"><label class="form-label">Language</label>
          <input name="wa_otp_lang" class="form-control" value="<?= htmlspecialchars($waOtp['lang']) ?>" placeholder="en"></div>
        <div class="col-12"><div class="form-text">The pre-approved authentication template that carries the login code (sent with the OTP as body + button parameter). Used for 2FA over WhatsApp.</div></div>
      </div>
      <?php endif; ?>

      <div class="form-check form-switch mt-3">
        <input type="checkbox" role="switch" class="form-check-input" id="waEnabled" name="enabled" <?= (int)($row['Enabled'] ?? 0)===1?'checked':'' ?>>
        <label class="form-check-label" for="waEnabled">Enable WhatsApp notifications for this <?= $fScope===0?'default':'company' ?></label>
      </div>
    </div>
    <div class="card-footer d-flex gap-2 align-items-center">
      <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i>Save Settings</button>
      <?php if ($fScope !== 0 && $row): ?>
      <button type="submit" form="waRevert" class="btn btn-outline-danger btn-sm" onclick="return confirm('Remove this company\'s WhatsApp channel and use the system default?')">Revert to system default</button>
      <?php endif; ?>
    </div>
  </div>
</form>
<?php if ($fScope !== 0 && $row): ?>
<form id="waRevert" method="POST" action="whatsapp.php?scope=<?= $fScope ?>" class="d-none"><?= csrf_field() ?><input type="hidden" name="action" value="revert"></form>
<?php endif; ?>

<!-- Send test -->
<div class="card border-0 shadow-sm mt-3" style="max-width:820px">
  <div class="card-header bg-white fw-semibold"><i class="bi bi-send me-2"></i>Send a Test Message</div>
  <div class="card-body">
    <p class="text-muted small mb-2">Sends a <strong>parameter-less</strong> approved template via the active channel (defaults to Meta's <code>hello_world</code>). Save your settings first.</p>
    <form method="POST" action="whatsapp.php?scope=<?= $fScope ?>" class="row g-2 align-items-end">
      <?= csrf_field() ?><input type="hidden" name="action" value="test">
      <div class="col-sm-3"><label class="form-label small">Template</label><input name="test_template" class="form-control form-control-sm" value="hello_world"></div>
      <div class="col-sm-2"><label class="form-label small">Language</label><input name="test_lang" class="form-control form-control-sm" value="en_US"></div>
      <div class="col-sm-4"><label class="form-label small">Recipient phone</label><input name="test_phone" class="form-control form-control-sm" placeholder="91XXXXXXXXXX" required></div>
      <div class="col-sm-3"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-send"></i> Send Test</button></div>
    </form>
  </div>
</div>

<!-- Test a specific template -->
<div class="card border-0 shadow-sm mt-3" style="max-width:820px">
  <div class="card-header bg-white fw-semibold"><i class="bi bi-card-checklist me-2"></i>Test a Template</div>
  <div class="card-body">
    <p class="text-muted small mb-2">Send a pre-approved template with literal values (one parameter per line, in order).</p>
    <form method="POST" action="whatsapp.php?scope=<?= $fScope ?>" class="row g-2">
      <?= csrf_field() ?><input type="hidden" name="action" value="test_template">
      <div class="col-sm-6"><label class="form-label small">Recipient phone</label><input name="tpl_phone" class="form-control form-control-sm" placeholder="91XXXXXXXXXX" required></div>
      <div class="col-sm-3"><label class="form-label small">Language</label><input name="tpl_lang" class="form-control form-control-sm" value="en"></div>
      <div class="col-sm-3"><label class="form-label small">&nbsp;</label><button class="btn btn-primary btn-sm w-100"><i class="bi bi-send"></i> Send Template</button></div>
      <div class="col-12"><label class="form-label small">Approved template name</label><input name="tpl_name" class="form-control form-control-sm" placeholder="e.g. order_confirmation" required></div>
      <div class="col-sm-6"><label class="form-label small">Body parameters (one per line)</label><textarea name="tpl_params" class="form-control form-control-sm" rows="3"></textarea></div>
      <div class="col-sm-6"><label class="form-label small">Button parameters (one per line — e.g. OTP)</label><textarea name="tpl_buttons" class="form-control form-control-sm" rows="3"></textarea></div>
    </form>
  </div>
</div>

<script>
function waShow(p){
  document.querySelectorAll('.wa-fields').forEach(function(el){ el.classList.toggle('show', el.getAttribute('data-prov')===p); });
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
