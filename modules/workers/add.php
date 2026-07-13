<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db     = getDb();
$user   = currentUser();
$errors = [];
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$rec = [
    'CompanyId' => 0, 'Department' => '', 'EmployeeCode' => '', 'Name' => '',
    'Activity' => '', 'EmpType' => '', 'Mobile' => '', 'Address' => '',
    'AadharNo' => '', 'WageType' => 'daily', 'WageRate' => '', 'ShiftId' => 0,
    'Status' => 'active',
];

// Companies in scope
if ($user['role'] === 'superadmin') {
    $companies = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['scope_id']]);
    $companies = $stmt->fetchAll();
}
$companyIds = array_column($companies, 'id');
$companyName = [];
foreach ($companies as $c) $companyName[(int)$c['id']] = $c['Name'];
$multiCo = count($companies) > 1;

// Shifts in scope (for the timings dropdown)
if ($companyIds) {
    $in = implode(',', array_fill(0, count($companyIds), '?'));
    $sq = $db->prepare("SELECT id, CompanyId, ShiftName, ArrivalTime, DepartureTime
                        FROM tblShift WHERE CompanyId IN ($in) AND IsActive=1 ORDER BY ShiftName");
    $sq->execute($companyIds);
    $shifts = $sq->fetchAll();
} else {
    $shifts = [];
}

if ($editId) {
    if ($user['role'] === 'superadmin') {
        $q = $db->prepare("SELECT * FROM tblWageWorker WHERE id=?");
        $q->execute([$editId]);
    } else {
        $q = $db->prepare("SELECT w.* FROM tblWageWorker w
            JOIN tblCompany c ON c.id=w.CompanyId AND c.AdminId=? WHERE w.id=?");
        $q->execute([$user['scope_id'], $editId]);
    }
    $f = $q->fetch();
    if (!$f) { header('Location: index.php'); exit; }
    $rec = $f;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();

    $companyId = (int)($_POST['company_id'] ?? 0);
    $dept      = trim($_POST['department'] ?? '');
    $code      = trim($_POST['emp_code']   ?? '');
    $name      = trim($_POST['name']       ?? '');
    $activity  = trim($_POST['activity']   ?? '');
    $empType   = trim($_POST['emp_type']   ?? '');
    $mobile    = trim($_POST['mobile']     ?? '');
    $address   = trim($_POST['address']    ?? '');
    $aadhaar   = trim($_POST['aadhaar']    ?? '');
    $wageType  = trim($_POST['wage_type']  ?? 'daily');
    $wageRate  = (float)($_POST['wage_rate'] ?? 0);
    $shiftId   = (int)($_POST['shift_id']  ?? 0);
    $status    = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if (!$companyId) $errors[] = 'Please select a company.';
    if (!$name)      $errors[] = 'Worker name is required.';
    if (!in_array($wageType, ['hourly','daily','monthly','piece_rate'])) $wageType = 'daily';
    if ($aadhaar !== '' && !preg_match('/^\d{12}$/', preg_replace('/\s+/', '', $aadhaar))) {
        $errors[] = 'Aadhaar must be 12 digits.';
    }
    $aadhaar = preg_replace('/\s+/', '', $aadhaar);

    // Company must be in this admin's scope
    if (!$errors && !in_array($companyId, $companyIds)) $errors[] = 'Invalid company.';
    // Shift, if chosen, must belong to the selected company
    if (!$errors && $shiftId) {
        $ok = false;
        foreach ($shifts as $s) { if ((int)$s['id'] === $shiftId && (int)$s['CompanyId'] === $companyId) { $ok = true; break; } }
        if (!$ok) $shiftId = 0;
    }

    if (!$errors) {
        if ($editId) {
            $db->prepare("UPDATE tblWageWorker SET
                CompanyId=?, Department=?, EmployeeCode=?, Name=?, Activity=?, EmpType=?,
                Mobile=?, Address=?, AadharNo=?, WageType=?, WageRate=?, ShiftId=?, Status=?
                WHERE id=?")
               ->execute([$companyId, $dept, $code, $name, $activity, $empType,
                          $mobile, $address, $aadhaar, $wageType, $wageRate,
                          $shiftId ?: null, $status, $editId]);
            $_SESSION['flash'] = 'Worker updated.';
        } else {
            $db->prepare("INSERT INTO tblWageWorker
                (CompanyId, Department, EmployeeCode, Name, Activity, EmpType,
                 Mobile, Address, AadharNo, WageType, WageRate, ShiftId, Status, CreatedBy)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$companyId, $dept, $code, $name, $activity, $empType,
                          $mobile, $address, $aadhaar, $wageType, $wageRate,
                          $shiftId ?: null, $status, $user['id']]);
            $_SESSION['flash'] = 'Worker added.';
        }
        // Stay on the form for the "add another" flow unless editing.
        $redirect = $editId ? 'index.php' : ('add.php?company=' . $companyId);
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>$redirect]); exit; }
        header('Location: ' . $redirect); exit;
    }
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>$errors]); exit; }
    $rec = [
        'CompanyId' => $companyId, 'Department' => $dept, 'EmployeeCode' => $code, 'Name' => $name,
        'Activity' => $activity, 'EmpType' => $empType, 'Mobile' => $mobile, 'Address' => $address,
        'AadharNo' => $aadhaar, 'WageType' => $wageType, 'WageRate' => $wageRate, 'ShiftId' => $shiftId,
        'Status' => $status,
    ];
}

// Preselect company from ?company= (kept after each add)
if (!$editId && !$rec['CompanyId']) {
    $pre = (int)($_GET['company'] ?? 0);
    if ($pre && in_array($pre, $companyIds)) $rec['CompanyId'] = $pre;
    elseif (count($companies) === 1)         $rec['CompanyId'] = (int)$companies[0]['id'];
}

$flash = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$pageTitle  = 'Wage Workers';
$activePage = 'workers';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card border-0 shadow-sm" style="max-width:760px">
  <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
    <span><?= $editId ? 'Edit' : 'Add' ?> Wage Worker</span>
    <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-ul"></i> All Workers</a>
  </div>
  <div class="card-body">
    <?php if ($flash): ?>
      <div class="alert alert-success py-2" data-no-toast><?= htmlspecialchars($flash) ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger py-2" data-no-toast><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="POST" data-ajax autocomplete="off">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Company <span class="text-danger">*</span></label>
          <select name="company_id" class="form-select" required>
            <option value="">— Select —</option>
            <?php foreach ($companies as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $rec['CompanyId']==$c['id']?'selected':'' ?>>
              <?= htmlspecialchars($c['Name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Department</label>
          <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($rec['Department']) ?>" placeholder="e.g. Production">
        </div>

        <div class="col-md-4">
          <label class="form-label">Emp Code</label>
          <input type="text" name="emp_code" class="form-control" value="<?= htmlspecialchars($rec['EmployeeCode']) ?>" placeholder="W-001">
        </div>
        <div class="col-md-8">
          <label class="form-label">Worker Name <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($rec['Name']) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Activity</label>
          <input type="text" name="activity" class="form-control" value="<?= htmlspecialchars($rec['Activity']) ?>" placeholder="e.g. Loading, Welding">
        </div>
        <div class="col-md-6">
          <label class="form-label">Emp Type</label>
          <input type="text" name="emp_type" class="form-control" value="<?= htmlspecialchars($rec['EmpType']) ?>" placeholder="e.g. Daily-wage, Casual, Contract" list="empTypeList">
          <datalist id="empTypeList">
            <option value="Daily-wage"><option value="Casual"><option value="Contract"><option value="Seasonal">
          </datalist>
        </div>

        <div class="col-md-6">
          <label class="form-label">Mobile</label>
          <input type="tel" name="mobile" class="form-control" value="<?= htmlspecialchars($rec['Mobile']) ?>" placeholder="10-digit" maxlength="15">
        </div>
        <div class="col-md-6">
          <label class="form-label">Aadhaar</label>
          <input type="text" name="aadhaar" class="form-control" value="<?= htmlspecialchars($rec['AadharNo']) ?>" placeholder="12-digit" inputmode="numeric" maxlength="14">
        </div>

        <div class="col-12">
          <label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($rec['Address']) ?></textarea>
        </div>

        <div class="col-md-4">
          <label class="form-label">Wage Basis</label>
          <select name="wage_type" class="form-select">
            <?php foreach (['daily'=>'Per Day','hourly'=>'Per Hour','monthly'=>'Per Month','piece_rate'=>'Piece Rate'] as $k=>$lbl): ?>
            <option value="<?= $k ?>" <?= $rec['WageType']===$k?'selected':'' ?>><?= $lbl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Wage Amount (₹)</label>
          <input type="number" step="0.01" min="0" name="wage_rate" class="form-control" value="<?= htmlspecialchars($rec['WageRate']) ?>" placeholder="0.00">
        </div>
        <div class="col-md-4">
          <label class="form-label">Timings (Shift)</label>
          <select name="shift_id" class="form-select">
            <option value="">— No shift —</option>
            <?php foreach ($shifts as $s): ?>
            <option value="<?= $s['id'] ?>" <?= (int)$rec['ShiftId']===(int)$s['id']?'selected':'' ?>>
              <?= $multiCo ? htmlspecialchars($companyName[(int)$s['CompanyId']] ?? '') . ' — ' : '' ?><?= htmlspecialchars($s['ShiftName']) ?> (<?= substr($s['ArrivalTime'],0,5) ?>–<?= substr($s['DepartureTime'],0,5) ?>)
            </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Sets working hours for wage-slip calculation.</div>
        </div>

        <?php if ($editId): ?>
        <div class="col-md-4">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="active"   <?= $rec['Status']==='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $rec['Status']==='inactive'?'selected':'' ?>>Inactive</option>
          </select>
        </div>
        <?php endif; ?>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary"><?= $editId ? 'Save Changes' : 'Add Worker' ?></button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
