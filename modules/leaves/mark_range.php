<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/hrms_settings.php';
requireAdmin();
requirePermission('leave_range.view');

$db   = getDb();
$user = currentUser();
$msg  = ''; $msgType = 'success';

// Company comes from the global topbar switcher
$fCompany = activeCompanyId($db, $user);

// ── POST: mark leave across a date range ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_mark'])) {
    requirePermission('leave_range.edit');
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $cid    = (int)($_POST['company_id'] ?? 0);
    $code   = trim($_POST['leave_code'] ?? '');
    $type   = $_POST['leave_type'] ?? 'full_day';
    $from   = trim($_POST['from_date'] ?? '');
    $to     = trim($_POST['to_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $skip   = !empty($_POST['skip_off']);
    $ids    = array_values(array_filter(array_map('intval', (array)($_POST['emp_ids'] ?? []))));

    if (!in_array($type, ['full_day','half_am','half_pm'], true)) $type = 'full_day';

    // Access check
    $ok = ($user['role'] === 'superadmin');
    if (!$ok && $cid) {
        $c = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $c->execute([$cid, $user['scope_id']]);
        $ok = (bool)$c->fetch();
    }

    if (!$ok)            { $msg = 'Access denied.'; $msgType = 'danger'; }
    elseif (!$code)      { $msg = 'Select a leave code.'; $msgType = 'danger'; }
    elseif (!$from)      { $msg = 'Select a from-date.'; $msgType = 'danger'; }
    elseif (!$ids)       { $msg = 'Select at least one employee.'; $msgType = 'danger'; }
    else {
        if (!$to || $to < $from) $to = $from;

        // Resolve leave code → type id for this company
        $ltId = null;
        $lt = $db->prepare("SELECT id FROM tblLeaveType WHERE CompanyId=? AND Code=? AND IsActive=1");
        $lt->execute([$cid, $code]);
        $ltId = $lt->fetchColumn() ?: null;

        // Capped date list
        $dates = [];
        for ($cur = strtotime($from), $end = strtotime($to); $cur <= $end && count($dates) < 90; $cur = strtotime('+1 day', $cur)) {
            $dates[] = date('Y-m-d', $cur);
        }

        // Keep only employees of this company; capture their week-off day
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $es = $db->prepare("SELECT id, WeekdayNo FROM tblEmployee WHERE CompanyId=? AND id IN ($ph)");
        $es->execute(array_merge([$cid], $ids));
        $empWo = [];
        foreach ($es->fetchAll() as $r) { $empWo[(int)$r['id']] = $r['WeekdayNo']; }

        // Company holidays in range (for skip)
        $holis = [];
        if ($skip) {
            $hs = $db->prepare("SELECT HolidayDate FROM tblHoliday WHERE CompanyId=? AND HolidayDate BETWEEN ? AND ?");
            $hs->execute([$cid, $from, $to]);
            foreach ($hs->fetchAll() as $h) { $holis[$h['HolidayDate']] = true; }
        }

        $ins = $db->prepare(
            "INSERT INTO tblLeave (CompanyId, EmployeeId, LeaveDate, LeaveType, LeaveTypeId, LeaveCode, Reason, CreatedBy)
             VALUES (?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE LeaveType=VALUES(LeaveType), LeaveTypeId=VALUES(LeaveTypeId),
                                     LeaveCode=VALUES(LeaveCode), Reason=VALUES(Reason)"
        );
        $allowNeg = hrmsAllowNegativeLeave($db, $cid);
        $dayVal   = $type === 'full_day' ? 1.0 : 0.5;
        $balYear  = (int)substr($from, 0, 4);
        $marked = 0; $skipped = 0; $balSkipped = 0;
        foreach ($empWo as $eid => $wo) {
            // Dates this employee will actually get (after week-off/holiday skipping)
            $md = [];
            foreach ($dates as $d) {
                if ($skip) {
                    if (isset($holis[$d])) { $skipped++; continue; }
                    if ($wo !== null && (int)date('w', strtotime($d)) === (int)$wo) { $skipped++; continue; }
                }
                $md[] = $d;
            }
            if (!$md) continue;
            // Negative-balance gate (only for a resolved leave type)
            if ($ltId && !$allowNeg &&
                hrmsLeaveBalanceAfter($db, $cid, $eid, (int)$ltId, $balYear, '1000-01-01', count($md) * $dayVal) < 0) {
                $balSkipped++;
                continue;
            }
            foreach ($md as $d) {
                $ins->execute([$cid, $eid, $d, $type, $ltId, $code, $reason ?: null, $user['id']]);
                $marked++;
            }
        }

        // Recalc Used balances for affected employees, per year touched. The helper resets
        // categories that dropped to zero, keeping stored Used authoritative after overwrites.
        $years  = array_unique(array_map(fn($d) => (int)substr($d, 0, 4), $dates));
        $empIds = array_keys($empWo);
        foreach ($years as $yr) {
            hrmsRecalcLeaveUsed($db, $cid, $empIds, $yr);
        }

        $msg = "Leave marked: $marked entr" . ($marked === 1 ? 'y' : 'ies') .
               ($skipped ? " ($skipped week-off/holiday date(s) skipped)" : '') .
               ($balSkipped ? " — $balSkipped employee(s) skipped for insufficient balance" : '') . '.';
        if ($balSkipped && !$marked) $msgType = 'danger';
    }

    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=> $msgType==='success','message'=>$msg,'errors'=>[$msg]]); exit; }
    header("Location: mark_range.php?company=$cid&msg=" . urlencode($msg) . "&mt=$msgType"); exit;
}
if (isset($_GET['msg'])) { $msg = $_GET['msg']; $msgType = in_array($_GET['mt'] ?? '', ['success','danger']) ? $_GET['mt'] : 'success'; }

// ── Load leave types + employees ──────────────────────────────────────────────
$leaveTypes = [];
$employees  = [];
if ($fCompany) {
    $s = $db->prepare("SELECT id, Code, Name FROM tblLeaveType WHERE CompanyId=? AND IsActive=1 ORDER BY Code");
    $s->execute([$fCompany]); $leaveTypes = $s->fetchAll();

    $s = $db->prepare(
        "SELECT id, Name, EmployeeCode, Department FROM tblEmployee
         WHERE CompanyId=? AND Status='active'
         ORDER BY Department, ISNULL(Sr), Sr, Name"
    );
    $s->execute([$fCompany]); $employees = $s->fetchAll();
}

$pageTitle  = 'Mark Leave (Range)';
$activePage = 'leave_range';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> py-2"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<?php if ($fCompany && empty($leaveTypes)): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>No leave types for this company. <a href="types.php?company=<?= $fCompany ?>" class="alert-link">Add leave types</a> first.</div>
<?php elseif ($fCompany && !empty($employees)): ?>
<form method="POST" action="mark_range.php?company=<?= $fCompany ?>" data-ajax>
  <input type="hidden" name="do_mark" value="1">
  <input type="hidden" name="company_id" value="<?= $fCompany ?>">
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Leave Details</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Leave Code <span class="text-danger">*</span></label>
            <select name="leave_code" class="form-select" required>
              <option value="">— Select —</option>
              <?php foreach ($leaveTypes as $lt): ?>
              <option value="<?= htmlspecialchars($lt['Code']) ?>"><?= htmlspecialchars($lt['Code'] . ' — ' . $lt['Name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Day Type</label>
            <select name="leave_type" class="form-select">
              <option value="full_day">Full Day</option>
              <option value="half_am">Half AM</option>
              <option value="half_pm">Half PM</option>
            </select>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label">From <span class="text-danger">*</span></label>
              <input type="date" name="from_date" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-6">
              <label class="form-label">To</label>
              <input type="date" name="to_date" class="form-control">
            </div>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="skip_off" id="skipOff" checked>
            <label class="form-check-label" for="skipOff">Skip week-offs &amp; company holidays</label>
          </div>
          <div class="mb-3">
            <label class="form-label">Reason</label>
            <input type="text" name="reason" class="form-control" placeholder="Optional">
          </div>
          <button type="submit" class="btn btn-success w-100"><i class="bi bi-save me-1"></i>Mark Leave</button>
          <div class="form-text mt-1">Existing leave on a date is overwritten. Range capped at 90 days.</div>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <span class="fw-semibold">Employees <span class="badge bg-secondary"><?= count($employees) ?></span></span>
          <input type="text" id="empSearch" class="form-control form-control-sm" style="max-width:220px"
                 placeholder="Search code / name…" autocomplete="off">
          <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="chkAll">
            <label class="form-check-label small" for="chkAll">Select all</label>
          </div>
        </div>
        <div class="card-body" style="max-height:520px;overflow-y:auto">
          <?php
          $prevD = null;
          foreach ($employees as $e):
              if ($e['Department'] !== $prevD):
                  $prevD = $e['Department'];
          ?>
          <div class="small fw-semibold text-muted mt-2 mb-1 border-bottom pb-1 dept-hdr"><?= htmlspecialchars($e['Department'] ?: '— No Department —') ?></div>
          <?php endif; ?>
          <div class="form-check emp-row" data-search="<?= htmlspecialchars(strtolower($e['Name'] . ' ' . ($e['EmployeeCode'] ?: ''))) ?>">
            <input class="form-check-input emp-chk" type="checkbox" name="emp_ids[]" value="<?= $e['id'] ?>" id="e<?= $e['id'] ?>">
            <label class="form-check-label" for="e<?= $e['id'] ?>">
              <?= htmlspecialchars($e['Name']) ?> <span class="text-muted small">(<?= htmlspecialchars($e['EmployeeCode'] ?: '—') ?>)</span>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</form>
<script>
// Select all — only affects rows currently visible (respects the search filter)
document.getElementById('chkAll')?.addEventListener('change', function(){
  var on = this.checked;
  document.querySelectorAll('.emp-row').forEach(function(row){
    if (row.style.display !== 'none') { var c = row.querySelector('.emp-chk'); if (c) c.checked = on; }
  });
});
// Live search by code / name
document.getElementById('empSearch')?.addEventListener('input', function(){
  var q = this.value.trim().toLowerCase();
  document.querySelectorAll('.emp-row').forEach(function(row){
    row.style.display = (!q || (row.dataset.search || '').indexOf(q) !== -1) ? '' : 'none';
  });
  // Hide department headers that have no visible members
  document.querySelectorAll('.dept-hdr').forEach(function(hdr){
    var vis = false, n = hdr.nextElementSibling;
    while (n && !n.classList.contains('dept-hdr')) {
      if (n.classList.contains('emp-row') && n.style.display !== 'none') { vis = true; break; }
      n = n.nextElementSibling;
    }
    hdr.style.display = vis ? '' : 'none';
  });
});
</script>
<?php elseif ($fCompany): ?>
<div class="alert alert-info">No active employees for this company.</div>
<?php else: ?>
<div class="alert alert-info">Select a company from the topbar switcher to mark leave.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
