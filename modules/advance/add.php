<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

// Company comes from the global topbar switcher
$fCompany = activeCompanyId($db, $user);
$editId   = (int)($_GET['id'] ?? 0);

function canAccess(PDO $db, array $user, int $companyId): bool {
    if ($user['role'] === 'superadmin') return true;
    $s = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $s->execute([$companyId, $user['scope_id']]);
    return (bool)$s->fetch();
}

// ── Load existing record for edit ─────────────────────────────────────────
$existing = null;
if ($editId) {
    $s = $db->prepare("SELECT * FROM tblEmployeeAdvance WHERE id=?");
    $s->execute([$editId]);
    $existing = $s->fetch();
    if (!$existing || !canAccess($db, $user, (int)$existing['CompanyId'])) {
        $_SESSION['flash'] = 'Record not found or access denied.';
        header('Location: index.php'); exit;
    }
    $fCompany = (int)$existing['CompanyId'];
}

// ── Employees for selected company ────────────────────────────────────────
$empsDd = [];
if ($fCompany) {
    $s = $db->prepare(
        "SELECT id, Name, EmployeeCode FROM tblEmployee
         WHERE CompanyId=? AND Status='active' ORDER BY Name"
    );
    $s->execute([$fCompany]);
    $empsDd = $s->fetchAll();
}

// ── Handle POST ───────────────────────────────────────────────────────────
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $companyId   = (int)($_POST['company_id']   ?? 0);
    $employeeId  = (int)($_POST['employee_id']  ?? 0);
    $advanceDate = trim($_POST['advance_date']   ?? '');
    $amount      = (float)($_POST['amount']      ?? 0);
    $purpose     = trim($_POST['purpose']        ?? '');
    $totalEMI    = max(1, (int)($_POST['total_emi'] ?? 1));
    $emiAmount   = (float)($_POST['emi_amount']  ?? 0);
    $remarks     = trim($_POST['remarks']        ?? '');

    if (!$companyId || !canAccess($db, $user, $companyId)) $errors[] = 'Invalid company.';
    if (!$employeeId)  $errors[] = 'Please select an employee.';
    if (!$advanceDate) $errors[] = 'Advance date is required.';
    if ($amount <= 0)  $errors[] = 'Amount must be greater than zero.';
    if ($emiAmount <= 0) $errors[] = 'EMI amount must be greater than zero.';

    if (!$errors) {
        if ($editId) {
            $db->prepare(
                "UPDATE tblEmployeeAdvance
                 SET CompanyId=?, EmployeeId=?, AdvanceDate=?, Amount=?, Purpose=?,
                     TotalEMI=?, EMIAmount=?, Remarks=?
                 WHERE id=?"
            )->execute([$companyId, $employeeId, $advanceDate, $amount, $purpose,
                        $totalEMI, $emiAmount, $remarks ?: null, $editId]);
            $_SESSION['flash'] = 'Advance updated.';
        } else {
            $db->prepare(
                "INSERT INTO tblEmployeeAdvance
                 (CompanyId, EmployeeId, AdvanceDate, Amount, Purpose, TotalEMI, EMIAmount, Remarks, CreatedBy)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            )->execute([$companyId, $employeeId, $advanceDate, $amount, $purpose,
                        $totalEMI, $emiAmount, $remarks ?: null, $user['id']]);
            $_SESSION['flash'] = 'Advance added successfully.';
        }
        header('Location: index.php?company=' . $companyId);
        exit;
    }
}

$v = $existing ?? [];
$pageTitle  = $editId ? 'Edit Advance' : 'New Advance';
$activePage = 'advance';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-lg-7 col-xl-6">

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
  <ul class="mb-0 ps-3">
    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
    <i class="bi bi-cash-coin text-primary"></i>
    <?= $editId ? 'Edit Advance' : 'New Employee Advance' ?>
  </div>
  <div class="card-body">
    <form method="POST" action="add.php<?= $editId ? '?id='.$editId : '' ?>">
      <?= csrf_field() ?? '' ?>

      <!-- Company comes from the global topbar switcher -->
      <input type="hidden" name="company_id" value="<?= (int)$fCompany ?>">

      <!-- Employee -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Employee <span class="text-danger">*</span></label>
        <select name="employee_id" class="form-select" required <?= !$fCompany ? 'disabled' : '' ?>>
          <option value="">— Select Employee —</option>
          <?php foreach ($empsDd as $e): ?>
          <option value="<?= $e['id'] ?>"
            <?= (isset($v['EmployeeId']) && $v['EmployeeId']==$e['id']) || (isset($_POST['employee_id']) && $_POST['employee_id']==$e['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars(($e['EmployeeCode'] ? $e['EmployeeCode'].' — ' : '') . $e['Name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <?php if (!$fCompany): ?>
        <div class="form-text">Select a company from the topbar switcher first.</div>
        <?php endif; ?>
      </div>

      <!-- Advance Date & Amount -->
      <div class="row g-3 mb-3">
        <div class="col-sm-6">
          <label class="form-label fw-semibold">Advance Date <span class="text-danger">*</span></label>
          <input type="date" name="advance_date" class="form-control" required
                 value="<?= htmlspecialchars($v['AdvanceDate'] ?? ($_POST['advance_date'] ?? date('Y-m-d'))) ?>">
        </div>
        <div class="col-sm-6">
          <label class="form-label fw-semibold">Advance Amount (&#8377;) <span class="text-danger">*</span></label>
          <input type="number" name="amount" id="inpAmount" class="form-control" required
                 min="1" step="0.01"
                 value="<?= htmlspecialchars($v['Amount'] ?? ($_POST['amount'] ?? '')) ?>"
                 placeholder="0.00">
        </div>
      </div>

      <!-- Purpose -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Purpose</label>
        <input type="text" name="purpose" class="form-control"
               value="<?= htmlspecialchars($v['Purpose'] ?? ($_POST['purpose'] ?? '')) ?>"
               placeholder="e.g. Medical emergency, Home loan, etc.">
      </div>

      <!-- EMI Settings -->
      <div class="card bg-light border-0 p-3 mb-3">
        <div class="fw-semibold mb-3 small text-uppercase text-muted">Recovery / EMI</div>
        <div class="row g-3">
          <div class="col-sm-6">
            <label class="form-label fw-semibold">Total EMIs <span class="text-danger">*</span></label>
            <input type="number" name="total_emi" id="inpTotalEMI" class="form-control" required
                   min="1" step="1"
                   value="<?= htmlspecialchars($v['TotalEMI'] ?? ($_POST['total_emi'] ?? 1)) ?>"
                   placeholder="e.g. 6">
            <div class="form-text">Number of monthly instalments</div>
          </div>
          <div class="col-sm-6">
            <label class="form-label fw-semibold">EMI Amount (&#8377;) <span class="text-danger">*</span></label>
            <input type="number" name="emi_amount" id="inpEMIAmount" class="form-control" required
                   min="0.01" step="0.01"
                   value="<?= htmlspecialchars($v['EMIAmount'] ?? ($_POST['emi_amount'] ?? '')) ?>"
                   placeholder="Auto-calculated">
            <div class="form-text" id="emiHint"></div>
          </div>
        </div>
        <?php if ($editId && isset($v['EMIPaid'])): ?>
        <div class="mt-3">
          <div class="d-flex justify-content-between small text-muted mb-1">
            <span>Recovery progress</span>
            <span><?= $v['EMIPaid'] ?> / <?= $v['TotalEMI'] ?> EMIs paid</span>
          </div>
          <div class="progress" style="height:6px">
            <?php $pct = $v['TotalEMI']>0 ? round($v['EMIPaid']/$v['TotalEMI']*100) : 0; ?>
            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
          </div>
          <div class="small text-muted mt-1">
            Recovered: &#8377;<?= number_format($v['EMIPaid'] * $v['EMIAmount'], 2) ?> &nbsp;|&nbsp;
            Outstanding: &#8377;<?= number_format(max(0, $v['Amount'] - $v['EMIPaid'] * $v['EMIAmount']), 2) ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Remarks -->
      <div class="mb-4">
        <label class="form-label fw-semibold">Remarks</label>
        <textarea name="remarks" class="form-control" rows="2"
                  placeholder="Internal notes (optional)"><?= htmlspecialchars($v['Remarks'] ?? ($_POST['remarks'] ?? '')) ?></textarea>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-1"></i><?= $editId ? 'Save Changes' : 'Add Advance' ?>
        </button>
        <a href="index.php?company=<?= $fCompany ?>" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>

</div><!-- /col -->
</div><!-- /row -->

<?php
$extraJs = <<<'JS'
<script>
(function () {
  var inpAmt  = document.getElementById('inpAmount');
  var inpEMI  = document.getElementById('inpTotalEMI');
  var inpEmiA = document.getElementById('inpEMIAmount');
  var hint    = document.getElementById('emiHint');

  function calcEMI() {
    var amt  = parseFloat(inpAmt.value)  || 0;
    var n    = parseInt(inpEMI.value, 10) || 1;
    if (amt > 0 && n > 0) {
      var per = Math.ceil(amt / n * 100) / 100;
      if (!inpEmiA.dataset.manuallySet) {
        inpEmiA.value = per.toFixed(2);
      }
      var total = per * n;
      hint.textContent = per.toFixed(2) + ' × ' + n + ' = ₹' + total.toFixed(2)
        + (total > amt ? ' (last EMI adjusted)' : '');
    } else {
      hint.textContent = '';
    }
  }

  inpEmiA.addEventListener('input', function () {
    inpEmiA.dataset.manuallySet = '1';
  });
  inpAmt.addEventListener('input',  function () { delete inpEmiA.dataset.manuallySet; calcEMI(); });
  inpEMI.addEventListener('input',  function () { delete inpEmiA.dataset.manuallySet; calcEMI(); });

  calcEMI();
})();
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
