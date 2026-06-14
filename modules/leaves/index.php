<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();
$msg  = '';

if ($user['role'] === 'superadmin') {
    $companiesDd = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['id']]);
    $companiesDd = $stmt->fetchAll();
}

$fCompany = (int)($_GET['company'] ?? ($companiesDd[0]['id'] ?? 0));
$fDate    = trim($_GET['date'] ?? date('Y-m-d'));

// Delete record
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $db->prepare("DELETE FROM tblLeave WHERE id=?")->execute([$did]);
    header("Location: index.php?company=$fCompany&date=" . urlencode($fDate)); exit;
}

// Save bulk leave
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emp_ids'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $companyId = (int)($_POST['company_id'] ?? 0);
    $leaveDate = trim($_POST['leave_date'] ?? '');
    $ids       = $_POST['emp_ids']      ?? [];
    $types     = $_POST['leave_types']  ?? [];
    $codes     = $_POST['leave_codes']  ?? [];
    $reasons   = $_POST['reasons']      ?? [];

    if ($user['role'] !== 'superadmin') {
        $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $chk->execute([$companyId, $user['id']]);
        if (!$chk->fetch()) { header('Location: index.php'); exit; }
    }

    // Code → LeaveTypeId map
    $ltMap = [];
    $s = $db->prepare("SELECT id, Code FROM tblLeaveType WHERE CompanyId=? AND IsActive=1");
    $s->execute([$companyId]);
    foreach ($s->fetchAll() as $lt) { $ltMap[$lt['Code']] = $lt['id']; }

    $year = (int)substr($leaveDate, 0, 4);

    $saved = 0;
    foreach ($ids as $i => $eid) {
        $eid   = (int)$eid;
        $type  = $types[$i] ?? 'none';
        $code  = trim($codes[$i] ?? '');
        $rsn   = trim($reasons[$i] ?? '');
        if (!$eid) continue;

        if ($type !== 'none') {
            $ltId = $code ? ($ltMap[$code] ?? null) : null;
            $db->prepare(
                "INSERT INTO tblLeave (CompanyId, EmployeeId, LeaveDate, LeaveType, LeaveTypeId, LeaveCode, Reason, CreatedBy)
                 VALUES (?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE LeaveType=?, LeaveTypeId=?, LeaveCode=?, Reason=?"
            )->execute([$companyId, $eid, $leaveDate, $type, $ltId, $code ?: null, $rsn ?: null, $user['id'],
                        $type, $ltId, $code ?: null, $rsn ?: null]);
            $saved++;
        } else {
            // Removing leave — get old code to update balance
            $old = $db->prepare("SELECT LeaveTypeId FROM tblLeave WHERE EmployeeId=? AND LeaveDate=?");
            $old->execute([$eid, $leaveDate]);
            $db->prepare("DELETE FROM tblLeave WHERE EmployeeId=? AND LeaveDate=?")->execute([$eid, $leaveDate]);
        }
    }

    // Recalculate Used in tblLeaveBalance for this employee+year from tblLeave
    // (simpler than delta tracking)
    if ($year && $companyId) {
        $empIds = array_map('intval', $ids);
        $phE = implode(',', array_fill(0, count($empIds), '?'));
        $recalc = $db->prepare(
            "SELECT EmployeeId, LeaveTypeId,
                    SUM(CASE WHEN LeaveType='full_day' THEN 1.0 ELSE 0.5 END) AS UsedDays
             FROM tblLeave
             WHERE CompanyId=? AND YEAR(LeaveDate)=? AND LeaveTypeId IS NOT NULL
               AND EmployeeId IN ($phE)
             GROUP BY EmployeeId, LeaveTypeId"
        );
        $recalc->execute(array_merge([$companyId, $year], $empIds));
        $updBal = $db->prepare(
            "INSERT INTO tblLeaveBalance (EmployeeId, CompanyId, LeaveTypeId, Year, Used)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE Used=?"
        );
        foreach ($recalc->fetchAll() as $r) {
            $updBal->execute([$r['EmployeeId'], $companyId, $r['LeaveTypeId'], $year, $r['UsedDays'], $r['UsedDays']]);
        }
    }

    $successMsg = "Leave marked for $saved employee(s).";
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$successMsg]); exit; }
    header("Location: index.php?company=$companyId&date=" . urlencode($leaveDate) . "&saved=$saved");
    exit;
}
if (isset($_GET['saved'])) $msg = 'Leave marked for ' . (int)$_GET['saved'] . ' employee(s).';

// Load leave types for the company
$leaveTypes = [];
if ($fCompany) {
    $s = $db->prepare("SELECT id, Code, Name FROM tblLeaveType WHERE CompanyId=? AND IsActive=1 ORDER BY Code");
    $s->execute([$fCompany]); $leaveTypes = $s->fetchAll();
}

// Load leave balances for the year (for balance display)
$balanceMap = []; // [empId][code] = balance days remaining
$fYear = (int)substr($fDate, 0, 4);
if ($fCompany && !empty($leaveTypes)) {
    $s = $db->prepare(
        "SELECT b.EmployeeId, lt.Code,
                (b.Allocated + b.Adjusted - b.Used) AS Balance
         FROM tblLeaveBalance b
         JOIN tblLeaveType lt ON lt.id = b.LeaveTypeId
         WHERE b.CompanyId=? AND b.Year=?"
    );
    $s->execute([$fCompany, $fYear]);
    foreach ($s->fetchAll() as $r) {
        $balanceMap[$r['EmployeeId']][$r['Code']] = (float)$r['Balance'];
    }
}

// Load employees with existing leave record for the date
$employees = [];
if ($fCompany) {
    if ($user['role'] !== 'superadmin') {
        $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $chk->execute([$fCompany, $user['id']]);
        if (!$chk->fetch()) $fCompany = 0;
    }
    if ($fCompany) {
        $stmt = $db->prepare(
            "SELECT e.id, e.EmployeeCode, e.Name, e.Department,
                    lv.id AS lvId, lv.LeaveType, lv.LeaveCode, lv.Reason
             FROM tblEmployee e
             LEFT JOIN tblLeave lv ON lv.EmployeeId = e.id AND lv.LeaveDate = ?
             WHERE e.CompanyId = ? AND e.Status = 'active'
             ORDER BY e.Department, e.Name"
        );
        $stmt->execute([$fDate, $fCompany]);
        $employees = $stmt->fetchAll();
    }
}

$pageTitle  = 'Leave Marking';
$activePage = 'leaves';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-success py-2"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end" data-filter>
      <div class="col-sm-4">
        <label class="form-label small mb-1">Company</label>
        <select name="company" class="form-select form-select-sm" onchange="$(this.form).trigger('submit')">
          <option value="">— Select Company —</option>
          <?php foreach ($companiesDd as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-3">
        <label class="form-label small mb-1">Date</label>
        <input type="date" name="date" class="form-control form-control-sm"
               value="<?= htmlspecialchars($fDate) ?>" onchange="$(this.form).trigger('submit')">
      </div>
    </form>
  </div>
</div>

<div id="filter-results">
<?php if ($fCompany && !empty($employees)): ?>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Leave Entry — <?= htmlspecialchars($fDate) ?> <small class="text-muted">(select "Present" to remove leave)</small></span>
    <div class="d-flex gap-2 align-items-center">
      <button type="button" class="btn btn-sm btn-outline-secondary" id="btnMarkAll">Mark All Full Day</button>
      <button form="leaveForm" type="submit" class="btn btn-success btn-sm"><i class="bi bi-save me-1"></i>Save Leaves</button>
    </div>
  </div>
  <div class="card-body p-0">
    <form id="leaveForm" method="POST" data-ajax>
      <input type="hidden" name="company_id" value="<?= $fCompany ?>">
      <input type="hidden" name="leave_date" value="<?= htmlspecialchars($fDate) ?>">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Code</th>
            <th>Name</th>
            <th>Department</th>
            <?php if (!empty($leaveTypes)): ?>
            <th style="width:130px">Leave Code</th>
            <?php endif; ?>
            <th style="width:140px">Leave Type</th>
            <th>Reason</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($employees as $e):
            $curCode = $e['LeaveCode'] ?? '';
            $curType = $e['LeaveType'] ?? '';
        ?>
        <tr class="<?= $curType ? 'table-danger' : '' ?>">
          <td><code class="small"><?= htmlspecialchars($e['EmployeeCode'] ?: '—') ?></code></td>
          <td><?= htmlspecialchars($e['Name']) ?></td>
          <td class="small text-muted"><?= htmlspecialchars($e['Department'] ?? '—') ?></td>
          <input type="hidden" name="emp_ids[]" value="<?= $e['id'] ?>">
          <?php if (!empty($leaveTypes)): ?>
          <td>
            <select name="leave_codes[]" class="form-select form-select-sm leave-code-sel" data-no-ts data-eid="<?= $e['id'] ?>">
              <option value="">— None —</option>
              <?php foreach ($leaveTypes as $lt):
                  $bal = $balanceMap[$e['id']][$lt['Code']] ?? null;
                  $balStr = $bal !== null ? ' (' . number_format($bal, 1) . 'd)' : '';
              ?>
              <option value="<?= htmlspecialchars($lt['Code']) ?>"
                      <?= $curCode === $lt['Code'] ? 'selected' : '' ?>
                      data-bal="<?= $bal !== null ? number_format($bal,1) : '' ?>">
                <?= htmlspecialchars($lt['Code']) ?><?= htmlspecialchars($balStr) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </td>
          <?php endif; ?>
          <td>
            <select name="leave_types[]" class="form-select form-select-sm leave-type-sel" data-no-ts>
              <option value="none"     <?= empty($curType)?'selected':'' ?>>— Present —</option>
              <option value="full_day" <?= $curType==='full_day'?'selected':'' ?>>Full Day</option>
              <option value="half_am"  <?= $curType==='half_am' ?'selected':'' ?>>Half AM</option>
              <option value="half_pm"  <?= $curType==='half_pm' ?'selected':'' ?>>Half PM</option>
            </select>
          </td>
          <td>
            <input type="text" name="reasons[]" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($e['Reason'] ?? '') ?>" placeholder="Optional">
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </form>
  </div>
</div>
<script>
document.getElementById('btnMarkAll')?.addEventListener('click', function(){
  document.querySelectorAll('.leave-type-sel').forEach(sel => sel.value = 'full_day');
});
document.querySelectorAll('.leave-type-sel').forEach(function(sel) {
  sel.addEventListener('change', function(){
    if (this.value === 'none') {
      var tr = this.closest('tr');
      var codeSel = tr && tr.querySelector('.leave-code-sel');
      if (codeSel) codeSel.value = '';
    }
  });
});
document.querySelectorAll('.leave-code-sel').forEach(function(sel) {
  sel.addEventListener('change', function(){
    if (!this.value) return;
    var tr = this.closest('tr');
    var typeSel = tr && tr.querySelector('.leave-type-sel');
    if (typeSel && typeSel.value === 'none') typeSel.value = 'full_day';
    var opt = this.options[this.selectedIndex];
    var bal = parseFloat(opt.dataset.bal);
    if (!isNaN(bal) && bal <= 0) {
      if (!confirm('Balance for ' + this.value + ' is ' + (isNaN(bal)?'?':bal.toFixed(1)) + ' days. Mark anyway?')) {
        this.value = '';
      }
    }
  });
});
</script>
<?php elseif ($fCompany): ?>
<div class="alert alert-info">No active employees found for the selected company.</div>
<?php else: ?>
<div class="alert alert-info">Select a company and date to mark leaves.</div>
<?php endif; ?>
</div><!-- /#filter-results -->

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>
