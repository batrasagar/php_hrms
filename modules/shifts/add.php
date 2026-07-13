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
    'CompanyId'       => 0,
    'ShiftName'       => '',
    'ArrivalTime'     => '09:00',
    'DepartureTime'   => '18:00',
    'MinArrivalTime'  => '',
    'MaxArrivalTime'  => '',
    'MaxDepartureTime'=> '',
    'HrsP'            => '8.00',
    'HrsHlf'          => '4.00',
    'HasLunch'        => 0,
    'LunchOutTime'    => '',
    'LunchInTime'     => '',
    'IsActive'        => 1,
];

if ($user['role'] === 'superadmin') {
    $companies = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['scope_id']]);
    $companies = $stmt->fetchAll();
}

if ($editId) {
    if ($user['role'] === 'superadmin') {
        $q = $db->prepare("SELECT * FROM tblShift WHERE id=?");
        $q->execute([$editId]);
    } else {
        $q = $db->prepare(
            "SELECT s.* FROM tblShift s
             JOIN tblCompany c ON c.id = s.CompanyId AND c.AdminId = ?
             WHERE s.id = ?"
        );
        $q->execute([$user['scope_id'], $editId]);
    }
    $fetched = $q->fetch();
    if (!$fetched) { header('Location: index.php'); exit; }
    $rec = $fetched;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $companyId        = (int)($_POST['company_id']        ?? 0);
    $shiftName        = trim($_POST['shift_name']         ?? '');
    $arrivalTime      = trim($_POST['arrival_time']       ?? '');
    $departureTime    = trim($_POST['departure_time']     ?? '');
    $minArrivalTime   = trim($_POST['min_arrival_time']   ?? '');
    $maxArrivalTime   = trim($_POST['max_arrival_time']   ?? '');
    $maxDepartureTime = trim($_POST['max_departure_time'] ?? '');
    $hrsP             = (float)($_POST['hrs_p']           ?? 8);
    $hrsHlf           = (float)($_POST['hrs_hlf']         ?? 4);
    $hasLunch         = isset($_POST['has_lunch']) ? 1 : 0;
    $lunchOutTime     = trim($_POST['lunch_out_time']     ?? '');
    $lunchInTime      = trim($_POST['lunch_in_time']      ?? '');
    $isActive         = isset($_POST['is_active']) ? 1 : 0;
    if (!$hasLunch) { $lunchOutTime = $lunchInTime = ''; }

    if (!$shiftName)    $errors[] = 'Shift name is required.';
    if (!$companyId)    $errors[] = 'Please select a company.';
    if (!$arrivalTime)  $errors[] = 'Arrival time is required.';
    if (!$departureTime)$errors[] = 'Departure time is required.';

    if (!$errors && $user['role'] !== 'superadmin') {
        $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $chk->execute([$companyId, $user['scope_id']]);
        if (!$chk->fetch()) $errors[] = 'Invalid company selected.';
    }

    if (!$errors) {
        $vals = [
            $companyId, $shiftName, $arrivalTime, $departureTime,
            $minArrivalTime ?: null, $maxArrivalTime ?: null, $maxDepartureTime ?: null,
            $hrsP, $hrsHlf, $hasLunch, $lunchOutTime ?: null, $lunchInTime ?: null, $isActive,
        ];
        if ($editId) {
            $db->prepare(
                "UPDATE tblShift SET CompanyId=?, ShiftName=?, ArrivalTime=?, DepartureTime=?,
                 MinArrivalTime=?, MaxArrivalTime=?, MaxDepartureTime=?, HrsP=?, HrsHlf=?,
                 HasLunch=?, LunchOutTime=?, LunchInTime=?, IsActive=?, UpdatedAt=NOW()
                 WHERE id=?"
            )->execute(array_merge($vals, [$editId]));
            $_SESSION['flash'] = 'Shift updated.';
        } else {
            $db->prepare(
                "INSERT INTO tblShift (CompanyId, ShiftName, ArrivalTime, DepartureTime,
                 MinArrivalTime, MaxArrivalTime, MaxDepartureTime, HrsP, HrsHlf,
                 HasLunch, LunchOutTime, LunchInTime, IsActive)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
            )->execute($vals);
            $_SESSION['flash'] = 'Shift added.';
        }
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$_SESSION['flash'],'redirect'=>'index.php']); exit; }
        header('Location: index.php'); exit;
    }
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>$errors]); exit; }
    $rec = array_merge($rec, [
        'CompanyId'        => $companyId,       'ShiftName'        => $shiftName,
        'ArrivalTime'      => $arrivalTime,      'DepartureTime'    => $departureTime,
        'MinArrivalTime'   => $minArrivalTime,   'MaxArrivalTime'   => $maxArrivalTime,
        'MaxDepartureTime' => $maxDepartureTime, 'HrsP'             => $hrsP,
        'HrsHlf'           => $hrsHlf,           'HasLunch'         => $hasLunch,
        'LunchOutTime'     => $lunchOutTime,     'LunchInTime'      => $lunchInTime,
        'IsActive'         => $isActive,
    ]);
}
$pageTitle  = 'Shift Master';
$activePage = 'shifts';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card border-0 shadow-sm" style="max-width:700px">
  <div class="card-header bg-white fw-semibold"><?= $editId ? 'Edit' : 'Add' ?> Shift</div>
  <div class="card-body">
    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <form method="POST" data-ajax>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label">Company <span class="text-danger">*</span></label>
          <select name="company_id" class="form-select" required>
            <option value="">— Select Company —</option>
            <?php foreach ($companies as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $rec['CompanyId']==$c['id']?'selected':'' ?>>
              <?= htmlspecialchars($c['Name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-sm-8">
          <label class="form-label">Shift Name <span class="text-danger">*</span></label>
          <input type="text" name="shift_name" class="form-control" required
                 value="<?= htmlspecialchars($rec['ShiftName']) ?>" placeholder="e.g. Morning Shift">
        </div>
        <div class="col-sm-4 d-flex align-items-end">
          <div class="form-check form-switch mb-1">
            <input class="form-check-input" type="checkbox" name="is_active" id="isActive"
                   <?= $rec['IsActive'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">Active</label>
          </div>
        </div>

        <div class="col-12"><hr class="my-1"><small class="text-muted fw-semibold">Shift Timings</small></div>

        <div class="col-sm-6">
          <label class="form-label">Arrival Time <span class="text-danger">*</span></label>
          <input type="time" name="arrival_time" class="form-control" required
                 value="<?= htmlspecialchars(substr($rec['ArrivalTime'],0,5)) ?>">
          <div class="form-text">Expected in-time</div>
        </div>
        <div class="col-sm-6">
          <label class="form-label">Departure Time <span class="text-danger">*</span></label>
          <input type="time" name="departure_time" class="form-control" required
                 value="<?= htmlspecialchars(substr($rec['DepartureTime'],0,5)) ?>">
          <div class="form-text">Expected out-time</div>
        </div>
        <div class="col-sm-4">
          <label class="form-label">Min Arrival Time</label>
          <input type="time" name="min_arrival_time" class="form-control"
                 value="<?= htmlspecialchars(substr($rec['MinArrivalTime']??'',0,5)) ?>">
          <div class="form-text">Gate opens at</div>
        </div>
        <div class="col-sm-4">
          <label class="form-label">Max Arrival Time</label>
          <input type="time" name="max_arrival_time" class="form-control"
                 value="<?= htmlspecialchars(substr($rec['MaxArrivalTime']??'',0,5)) ?>">
          <div class="form-text">Late after this</div>
        </div>
        <div class="col-sm-4">
          <label class="form-label">Max Departure Time</label>
          <input type="time" name="max_departure_time" class="form-control"
                 value="<?= htmlspecialchars(substr($rec['MaxDepartureTime']??'',0,5)) ?>">
          <div class="form-text">OT starts after</div>
        </div>

        <div class="col-12"><hr class="my-1"><small class="text-muted fw-semibold">Hours Thresholds</small></div>

        <div class="col-sm-6">
          <label class="form-label">Full Day Hours (HrsP)</label>
          <input type="number" step="0.25" min="0" max="24" name="hrs_p" class="form-control"
                 value="<?= htmlspecialchars($rec['HrsP']) ?>">
          <div class="form-text">Min hours for full-day present</div>
        </div>
        <div class="col-sm-6">
          <label class="form-label">Half Day Hours (HrsHlf)</label>
          <input type="number" step="0.25" min="0" max="24" name="hrs_hlf" class="form-control"
                 value="<?= htmlspecialchars($rec['HrsHlf']) ?>">
          <div class="form-text">Min hours for half-day present</div>
        </div>

        <div class="col-12"><hr class="my-1"><small class="text-muted fw-semibold">Lunch Break</small></div>

        <div class="col-12">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="has_lunch" id="hasLunch"
                   <?= !empty($rec['HasLunch']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="hasLunch">This shift has a lunch break</label>
          </div>
          <div class="form-text">Turn off for continuous / 3-shift rotation (24 hrs) shifts.</div>
        </div>
        <div class="col-sm-6 lunch-field" <?= empty($rec['HasLunch']) ? 'style="display:none"' : '' ?>>
          <label class="form-label">Lunch Out</label>
          <input type="time" name="lunch_out_time" class="form-control"
                 value="<?= htmlspecialchars(substr($rec['LunchOutTime']??'',0,5)) ?>">
          <div class="form-text">Break starts (punch out)</div>
        </div>
        <div class="col-sm-6 lunch-field" <?= empty($rec['HasLunch']) ? 'style="display:none"' : '' ?>>
          <label class="form-label">Lunch In</label>
          <input type="time" name="lunch_in_time" class="form-control"
                 value="<?= htmlspecialchars(substr($rec['LunchInTime']??'',0,5)) ?>">
          <div class="form-text">Break ends (punch back in)</div>
        </div>
      </div>
      <hr class="my-4">
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $editId ? 'Save Changes' : 'Add Shift' ?></button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<script>
$(function(){
  $('#hasLunch').on('change', function(){
    $('.lunch-field').toggle(this.checked);
    if (!this.checked) $('.lunch-field input').val('');
  });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
