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

try { $db->query("SELECT 1 FROM tblEmployeePayroll LIMIT 1"); }
catch (PDOException $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

$msg = ''; $msgType = 'success';

// --- SAVE employee payroll config ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fCompany) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $empId    = (int)$_POST['EmployeeId'];
    $wageType = in_array($_POST['WageType']??'', ['monthly','daily','hourly','piece_rate']) ? $_POST['WageType'] : 'monthly';
    $wageRate = max(0, (float)($_POST['WageRate'] ?? 0));
    $hpd      = max(0.5, min(24, (float)($_POST['HoursPerDay'] ?? 8)));
    $otAllow  = isset($_POST['OTAllowed'])  ? 1 : 0;
    $otMult   = isset($_POST['OTMultiplier']) && $_POST['OTMultiplier'] !== '' ? max(1, (float)$_POST['OTMultiplier']) : null;
    $pfApp    = isset($_POST['PFApplicable'])  ? 1 : 0;
    $esiApp   = isset($_POST['ESIApplicable']) ? 1 : 0;
    $tdsApp   = isset($_POST['TDSApplicable']) ? 1 : 0;

    if ($empId) {
        // Verify employee belongs to this company
        $chk = $db->prepare("SELECT id FROM tblEmployee WHERE id=? AND CompanyId=?");
        $chk->execute([$empId, $fCompany]);
        if ($chk->fetch()) {
            $db->prepare(
                "INSERT INTO tblEmployeePayroll (EmployeeId,CompanyId,WageType,WageRate,HoursPerDay,OTAllowed,OTMultiplier,PFApplicable,ESIApplicable,TDSApplicable)
                 VALUES (?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE WageType=VALUES(WageType),WageRate=VALUES(WageRate),
                   HoursPerDay=VALUES(HoursPerDay),OTAllowed=VALUES(OTAllowed),OTMultiplier=VALUES(OTMultiplier),
                   PFApplicable=VALUES(PFApplicable),ESIApplicable=VALUES(ESIApplicable),TDSApplicable=VALUES(TDSApplicable)"
            )->execute([$empId, $fCompany, $wageType, $wageRate, $hpd, $otAllow, $otMult, $pfApp, $esiApp, $tdsApp]);

            // Save component overrides
            $comps = $db->prepare("SELECT id FROM tblPayrollComponent WHERE CompanyId=? AND IsActive=1");
            $comps->execute([$fCompany]);
            foreach ($comps->fetchAll() as $comp) {
                $key = 'comp_' . $comp['id'];
                if (isset($_POST[$key])) {
                    $val = max(0, (float)$_POST[$key]);
                    $db->prepare(
                        "INSERT INTO tblEmployeePayComponent (EmployeeId,CompanyId,ComponentId,Value) VALUES (?,?,?,?)
                         ON DUPLICATE KEY UPDATE Value=VALUES(Value)"
                    )->execute([$empId, $fCompany, $comp['id'], $val]);
                }
            }
            $msg = 'Payroll configuration saved.';
        }
    }
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$msg ?: 'Saved.']); exit; }
    header("Location: employee_setup.php?company=$fCompany&emp=$empId"); exit;
}

// --- Load employees ---
$employees = [];
if ($fCompany) {
    $s = $db->prepare(
        "SELECT e.id, e.EmployeeCode, e.Name, e.Department, e.Designation,
                ep.WageType, ep.WageRate, ep.OTAllowed, ep.PFApplicable, ep.ESIApplicable, ep.TDSApplicable
         FROM tblEmployee e
         LEFT JOIN tblEmployeePayroll ep ON ep.EmployeeId = e.id
         WHERE e.CompanyId = ? AND e.Status = 'active'
         ORDER BY e.Name"
    );
    $s->execute([$fCompany]);
    $employees = $s->fetchAll();
}

$editEmpId = (int)($_GET['emp'] ?? 0);
$editEmp   = null;
$editEpRow = null;
$compComponents = [];
$empCompValues  = [];

if ($editEmpId && $fCompany) {
    $s = $db->prepare("SELECT * FROM tblEmployee WHERE id=? AND CompanyId=?");
    $s->execute([$editEmpId, $fCompany]);
    $editEmp = $s->fetch();

    $s = $db->prepare("SELECT * FROM tblEmployeePayroll WHERE EmployeeId=? LIMIT 1");
    $s->execute([$editEmpId]);
    $editEpRow = $s->fetch() ?: [];

    // Default wage rate from employee basic salary if not set
    if (!($editEpRow['WageRate'] ?? 0)) {
        $editEpRow['WageRate'] = $editEmp['GrossSalary'] ?? $editEmp['BasicSalary'] ?? 0;
    }

    $s = $db->prepare("SELECT * FROM tblPayrollComponent WHERE CompanyId=? AND IsActive=1 ORDER BY Type, SortOrder, id");
    $s->execute([$fCompany]);
    $compComponents = $s->fetchAll();

    $s = $db->prepare("SELECT ComponentId, Value FROM tblEmployeePayComponent WHERE EmployeeId=?");
    $s->execute([$editEmpId]);
    foreach ($s->fetchAll() as $r) $empCompValues[$r['ComponentId']] = $r['Value'];
}

$pageTitle  = 'Employee Payroll Setup';
$activePage = 'payroll_emp_setup';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <form method="GET" class="d-flex gap-2">
    <select name="company" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:180px">
      <?php foreach ($companies as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $c['id']==$fCompany?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if ($editEmp): ?>
<!-- Edit form -->
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-person me-2"></i><?= htmlspecialchars($editEmp['Name']) ?></span>
        <a href="employee_setup.php?company=<?= $fCompany ?>" class="btn btn-sm btn-outline-secondary">← Back</a>
      </div>
      <div class="card-body">
        <form method="POST" action="employee_setup.php?company=<?= $fCompany ?>" data-ajax>
          <input type="hidden" name="EmployeeId" value="<?= $editEmpId ?>">
          <div class="mb-3">
            <label class="form-label">Wage Type</label>
            <select name="WageType" class="form-select" id="wageType" onchange="toggleFields()">
              <?php foreach (['monthly'=>'Monthly Salary','daily'=>'Daily Wage','hourly'=>'Hourly Rate','piece_rate'=>'Piece Rate'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= ($editEpRow['WageType']??'monthly')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label" id="rateLabel">Rate (₹)</label>
            <input type="number" name="WageRate" class="form-control" id="wageRate"
                   value="<?= $editEpRow['WageRate'] ?? 0 ?>" step="0.01" min="0">
            <div class="form-text" id="rateHelp">Monthly gross salary amount</div>
          </div>
          <div class="mb-3" id="hpdRow">
            <label class="form-label">Hours Per Day</label>
            <input type="number" name="HoursPerDay" class="form-control"
                   value="<?= $editEpRow['HoursPerDay'] ?? 8 ?>" step="0.5" min="0.5" max="24">
          </div>
          <hr>
          <div class="mb-2 fw-semibold" style="font-size:13px">OT Settings</div>
          <div class="mb-3 form-check">
            <input type="checkbox" name="OTAllowed" id="chkOT" class="form-check-input"
                   <?= ($editEpRow['OTAllowed'] ?? 1) ? 'checked' : '' ?> onchange="toggleOT()">
            <label class="form-check-label" for="chkOT">OT Allowed</label>
          </div>
          <div class="mb-3" id="otMultRow" <?= ($editEpRow['OTAllowed'] ?? 1) ? '' : 'style="display:none"' ?>>
            <label class="form-label">OT Multiplier <small class="text-muted">(leave blank = use company default)</small></label>
            <input type="number" name="OTMultiplier" class="form-control"
                   value="<?= $editEpRow['OTMultiplier'] ?? '' ?>" step="0.25" min="1" max="5" placeholder="e.g. 1.5">
          </div>
          <hr>
          <div class="mb-2 fw-semibold" style="font-size:13px">Statutory Applicability</div>
          <div class="d-flex gap-4 mb-3">
            <div class="form-check">
              <input type="checkbox" name="PFApplicable" id="chkPF" class="form-check-input" <?= ($editEpRow['PFApplicable'] ?? 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="chkPF">PF</label>
            </div>
            <div class="form-check">
              <input type="checkbox" name="ESIApplicable" id="chkESI" class="form-check-input" <?= ($editEpRow['ESIApplicable'] ?? 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="chkESI">ESI</label>
            </div>
            <div class="form-check">
              <input type="checkbox" name="TDSApplicable" id="chkTDS" class="form-check-input" <?= ($editEpRow['TDSApplicable'] ?? 0) ? 'checked' : '' ?>>
              <label class="form-check-label" for="chkTDS">TDS</label>
            </div>
          </div>
          <button type="submit" class="btn btn-primary w-100"><i class="bi bi-floppy me-1"></i>Save Config</button>
        </form>
      </div>
    </div>
  </div>

  <?php if ($compComponents): ?>
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">Component Override <small class="text-muted">(leave blank = company default)</small></div>
      <div class="card-body">
        <form method="POST" action="employee_setup.php?company=<?= $fCompany ?>" data-ajax>
          <input type="hidden" name="EmployeeId" value="<?= $editEmpId ?>">
          <!-- replicate wage fields as hidden so the main save still works -->
          <input type="hidden" name="WageType" value="<?= htmlspecialchars($editEpRow['WageType'] ?? 'monthly') ?>">
          <input type="hidden" name="WageRate" value="<?= $editEpRow['WageRate'] ?? 0 ?>">
          <input type="hidden" name="HoursPerDay" value="<?= $editEpRow['HoursPerDay'] ?? 8 ?>">
          <?php if ($editEpRow['OTAllowed'] ?? 1): ?><input type="hidden" name="OTAllowed" value="1"><?php endif; ?>
          <input type="hidden" name="OTMultiplier" value="<?= $editEpRow['OTMultiplier'] ?? '' ?>">
          <?php if ($editEpRow['PFApplicable'] ?? 1): ?><input type="hidden" name="PFApplicable" value="1"><?php endif; ?>
          <?php if ($editEpRow['ESIApplicable'] ?? 1): ?><input type="hidden" name="ESIApplicable" value="1"><?php endif; ?>
          <?php if ($editEpRow['TDSApplicable'] ?? 0): ?><input type="hidden" name="TDSApplicable" value="1"><?php endif; ?>

          <?php foreach (['earning'=>'Earnings','deduction'=>'Deductions'] as $type=>$label): ?>
          <?php $filtered = array_filter($compComponents, fn($c)=>$c['Type']===$type); ?>
          <?php if ($filtered): ?>
          <div class="fw-semibold mb-2 <?= $type==='deduction'?'mt-3':'' ?>" style="font-size:13px;color:var(--<?= $type==='earning'?'blue':'danger' ?>)"><?= $label ?></div>
          <div class="row g-2 mb-2">
            <?php foreach ($filtered as $comp): ?>
            <div class="col-6">
              <label class="form-label" style="font-size:12px"><?= htmlspecialchars($comp['Name']) ?>
                <span class="text-muted">(<?= $comp['CalcType']==='fixed'?'₹':'%' ?>)</span>
              </label>
              <input type="number" name="comp_<?= $comp['id'] ?>" class="form-control form-control-sm"
                     placeholder="Default: <?= $comp['DefaultValue'] ?>"
                     value="<?= $empCompValues[$comp['id']] ?? '' ?>" step="0.01" min="0">
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <?php endforeach; ?>

          <button type="submit" class="btn btn-outline-primary btn-sm mt-2">
            <i class="bi bi-floppy me-1"></i>Save Component Overrides
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
const wageLabels = {
  monthly:    ['Monthly Salary (₹)', 'Total gross per month'],
  daily:      ['Daily Rate (₹)',      'Pay per working day'],
  hourly:     ['Hourly Rate (₹)',     'Pay per hour worked'],
  piece_rate: ['Per-Piece Rate (₹)',  'Pay per piece produced'],
};
function toggleFields() {
  const t = document.getElementById('wageType').value;
  document.getElementById('rateLabel').textContent = wageLabels[t][0];
  document.getElementById('rateHelp').textContent  = wageLabels[t][1];
  document.getElementById('hpdRow').style.display  = t === 'piece_rate' ? 'none' : '';
}
function toggleOT() {
  document.getElementById('otMultRow').style.display = document.getElementById('chkOT').checked ? '' : 'none';
}
toggleFields();
</script>

<?php else: ?>
<!-- Employee list -->
<?php if ($fCompany): ?>
<div class="card">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Employee</th><th>Code</th><th>Department</th><th>Wage Type</th><th>Wage Rate</th><th>OT</th><th>PF</th><th>ESI</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($employees as $e): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($e['Name']) ?></td>
          <td class="small text-muted"><?= htmlspecialchars($e['EmployeeCode']) ?></td>
          <td class="small"><?= htmlspecialchars($e['Department'] ?? '—') ?></td>
          <td>
            <?php if ($e['WageType']): ?>
              <span class="badge bg-primary"><?= ucwords(str_replace('_',' ',$e['WageType'])) ?></span>
            <?php else: ?>
              <span class="text-muted small">Not set</span>
            <?php endif; ?>
          </td>
          <td><?= $e['WageRate'] ? '₹'.number_format($e['WageRate'],2) : '<span class="text-muted">—</span>' ?></td>
          <td><?= $e['OTAllowed'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
          <td><?= $e['PFApplicable'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
          <td><?= $e['ESIApplicable'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
          <td>
            <a href="employee_setup.php?company=<?= $fCompany ?>&emp=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-pencil"></i>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$employees): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">No active employees found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="alert alert-warning">Please select a company.</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
