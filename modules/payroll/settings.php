<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

if ($user['role'] === 'superadmin') {
    $companies = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $s = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $s->execute([$user['scope_id']]);
    $companies = $s->fetchAll();
}

$fCompany = (int)($_REQUEST['company'] ?? ($companies[0]['id'] ?? 0));
if ($fCompany && in_array($user['role'], ['admin','operator'], true)) {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$fCompany, $user['scope_id']]);
    if (!$chk->fetch()) $fCompany = 0;
}

$defaults = [
    'WorkingDaysPerMonth' => 26, 'PFEmployeeRate' => 12.00, 'PFEmployerRate' => 12.00,
    'PFWageCeiling' => 15000, 'ESIEmployeeRate' => 0.75, 'ESIEmployerRate' => 3.25,
    'ESIWageCeiling' => 21000, 'OTMultiplier' => 1.50,
    'OTApprovalRequired' => 0, 'OTMonthlyCap' => 48, 'OTIncentiveAsBonus' => 1, 'HRManagerMobile' => '',
];

$settings = $defaults;
// Guard: redirect to migrate if payroll tables don't exist yet
try {
    $db->query("SELECT 1 FROM tblPayrollSettings LIMIT 1");
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . '/migrate.php'); exit;
}

$msg = ''; $msgType = 'success';
if ($fCompany) {
    $s = $db->prepare("SELECT * FROM tblPayrollSettings WHERE CompanyId=? LIMIT 1");
    $s->execute([$fCompany]);
    $row = $s->fetch();
    if ($row) $settings = array_merge($defaults, $row);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fCompany) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $settings['WorkingDaysPerMonth'] = max(1, min(31, (int)$_POST['WorkingDaysPerMonth']));
    $settings['PFEmployeeRate']      = max(0, min(100, (float)$_POST['PFEmployeeRate']));
    $settings['PFEmployerRate']      = max(0, min(100, (float)$_POST['PFEmployerRate']));
    $settings['PFWageCeiling']       = max(0, (int)$_POST['PFWageCeiling']);
    $settings['ESIEmployeeRate']     = max(0, min(100, (float)$_POST['ESIEmployeeRate']));
    $settings['ESIEmployerRate']     = max(0, min(100, (float)$_POST['ESIEmployerRate']));
    $settings['ESIWageCeiling']      = max(0, (int)$_POST['ESIWageCeiling']);
    $settings['OTMultiplier']        = max(1, (float)$_POST['OTMultiplier']);
    $settings['OTApprovalRequired']  = isset($_POST['OTApprovalRequired']) ? 1 : 0;
    $settings['OTMonthlyCap']        = max(0, (int)($_POST['OTMonthlyCap'] ?? 48));
    $settings['OTIncentiveAsBonus']  = isset($_POST['OTIncentiveAsBonus']) ? 1 : 0;
    $settings['HRManagerMobile']     = preg_replace('/[^\d+]/', '', trim($_POST['HRManagerMobile'] ?? ''));

    $db->prepare(
        "INSERT INTO tblPayrollSettings (CompanyId,WorkingDaysPerMonth,PFEmployeeRate,PFEmployerRate,
            PFWageCeiling,ESIEmployeeRate,ESIEmployerRate,ESIWageCeiling,OTMultiplier,
            OTApprovalRequired,OTMonthlyCap,OTIncentiveAsBonus,HRManagerMobile)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE WorkingDaysPerMonth=VALUES(WorkingDaysPerMonth),
            PFEmployeeRate=VALUES(PFEmployeeRate), PFEmployerRate=VALUES(PFEmployerRate),
            PFWageCeiling=VALUES(PFWageCeiling), ESIEmployeeRate=VALUES(ESIEmployeeRate),
            ESIEmployerRate=VALUES(ESIEmployerRate), ESIWageCeiling=VALUES(ESIWageCeiling),
            OTMultiplier=VALUES(OTMultiplier), OTApprovalRequired=VALUES(OTApprovalRequired),
            OTMonthlyCap=VALUES(OTMonthlyCap), OTIncentiveAsBonus=VALUES(OTIncentiveAsBonus),
            HRManagerMobile=VALUES(HRManagerMobile)"
    )->execute([
        $fCompany, $settings['WorkingDaysPerMonth'], $settings['PFEmployeeRate'],
        $settings['PFEmployerRate'], $settings['PFWageCeiling'], $settings['ESIEmployeeRate'],
        $settings['ESIEmployerRate'], $settings['ESIWageCeiling'], $settings['OTMultiplier'],
        $settings['OTApprovalRequired'], $settings['OTMonthlyCap'], $settings['OTIncentiveAsBonus'],
        $settings['HRManagerMobile'],
    ]);
    $msg = 'Payroll settings saved.';
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$msg]); exit; }
}

$pageTitle  = 'Payroll Settings';
$activePage = 'payroll_settings';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<form method="GET" class="row g-2 mb-4" style="max-width:400px">
  <div class="col">
    <select name="company" class="form-select" onchange="this.form.submit()">
      <?php foreach ($companies as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $c['id']==$fCompany?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</form>

<?php if ($fCompany): ?>
<form method="POST" action="settings.php?company=<?= $fCompany ?>" data-ajax>
<div class="row g-4">

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-calendar3 me-2 text-primary"></i>General</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Working Days Per Month</label>
          <input type="number" name="WorkingDaysPerMonth" class="form-control" value="<?= $settings['WorkingDaysPerMonth'] ?>" min="1" max="31" required>
          <div class="form-text">Used for per-day rate calculation.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">OT Multiplier</label>
          <input type="number" name="OTMultiplier" class="form-control" value="<?= $settings['OTMultiplier'] ?>" step="0.25" min="1" max="5" required>
          <div class="form-text">1.5 = 1.5× regular rate. Can be overridden per employee.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-shield-check me-2 text-success"></i>PF Settings</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label">Employee Rate (%)</label>
            <input type="number" name="PFEmployeeRate" class="form-control" value="<?= $settings['PFEmployeeRate'] ?>" step="0.01" min="0" max="100" required>
          </div>
          <div class="col-6">
            <label class="form-label">Employer Rate (%)</label>
            <input type="number" name="PFEmployerRate" class="form-control" value="<?= $settings['PFEmployerRate'] ?>" step="0.01" min="0" max="100" required>
          </div>
          <div class="col-12">
            <label class="form-label">Wage Ceiling (₹)</label>
            <input type="number" name="PFWageCeiling" class="form-control" value="<?= $settings['PFWageCeiling'] ?>" min="0" required>
            <div class="form-text">PF is calculated on Basic up to this limit.</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-heart-pulse me-2 text-danger"></i>ESI Settings</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label">Employee Rate (%)</label>
            <input type="number" name="ESIEmployeeRate" class="form-control" value="<?= $settings['ESIEmployeeRate'] ?>" step="0.01" min="0" max="100" required>
          </div>
          <div class="col-6">
            <label class="form-label">Employer Rate (%)</label>
            <input type="number" name="ESIEmployerRate" class="form-control" value="<?= $settings['ESIEmployerRate'] ?>" step="0.01" min="0" max="100" required>
          </div>
          <div class="col-12">
            <label class="form-label">Gross Wage Ceiling (₹)</label>
            <input type="number" name="ESIWageCeiling" class="form-control" value="<?= $settings['ESIWageCeiling'] ?>" min="0" required>
            <div class="form-text">ESI applies only when monthly gross ≤ this limit.</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-alarm me-2 text-warning"></i>Overtime &amp; Incentive</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Monthly OT Cap (hours)</label>
          <input type="number" name="OTMonthlyCap" class="form-control" value="<?= (int)$settings['OTMonthlyCap'] ?>" min="0" max="200">
          <div class="form-text">OT beyond this many hours in a month is paid as a separate incentive line (legal cap, e.g. 48).</div>
        </div>
        <div class="form-check mb-2">
          <input type="checkbox" name="OTIncentiveAsBonus" id="chkIncBonus" class="form-check-input" <?= ($settings['OTIncentiveAsBonus'] ?? 1) ? 'checked' : '' ?>>
          <label class="form-check-label" for="chkIncBonus">Pay OT above the cap as incentive / bonus</label>
        </div>
        <div class="form-check mb-3">
          <input type="checkbox" name="OTApprovalRequired" id="chkOTAppr" class="form-check-input" <?= ($settings['OTApprovalRequired'] ?? 0) ? 'checked' : '' ?>>
          <label class="form-check-label" for="chkOTAppr">Require OT approval before it is counted in payroll</label>
        </div>
        <div class="mb-1">
          <label class="form-label">HR Manager Mobile <small class="text-muted">(for OT SMS)</small></label>
          <input type="text" name="HRManagerMobile" class="form-control" value="<?= htmlspecialchars($settings['HRManagerMobile'] ?? '') ?>" placeholder="10-digit mobile">
          <div class="form-text">SMS is sent here when OT is entered and approved (needs MSG91 configured under <a href="<?= BASE_URL ?>/modules/settings/sms.php">SMS Settings</a>).</div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i>Save Settings</button>
    <a href="components.php?company=<?= $fCompany ?>" class="btn btn-outline-primary ms-2">
      <i class="bi bi-list-columns me-1"></i>Manage Components →
    </a>
  </div>

</div>
</form>
<?php else: ?>
<div class="alert alert-warning">Please select a company above.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
