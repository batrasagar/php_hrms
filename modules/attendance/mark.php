<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
requirePermission('mark_ot_abs.view');

$db   = getDb();
$user = currentUser();
$msg  = ''; $msgType = 'success';

// Company comes from the global topbar switcher
$fCompany = activeCompanyId($db, $user);

// ── POST: mark OT / Absent across a date range ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_mark'])) {
    requirePermission('mark_ot_abs.edit');
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $cid    = (int)($_POST['company_id'] ?? 0);
    $mode   = ($_POST['mode'] ?? 'ot') === 'absent' ? 'absent' : 'ot';
    $from   = trim($_POST['from_date'] ?? '');
    $to     = trim($_POST['to_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $hours  = (float)($_POST['ot_hours'] ?? 0);
    $skip   = !empty($_POST['skip_off']);
    $remove = !empty($_POST['remove']);
    $ids    = array_values(array_filter(array_map('intval', (array)($_POST['emp_ids'] ?? []))));

    // Access check
    $ok = ($user['role'] === 'superadmin');
    if (!$ok && $cid) {
        $c = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $c->execute([$cid, $user['scope_id']]);
        $ok = (bool)$c->fetch();
    }

    if (!$ok)                               { $msg = 'Access denied.'; $msgType = 'danger'; }
    elseif (!$from)                         { $msg = 'Select a from-date.'; $msgType = 'danger'; }
    elseif (!$ids)                          { $msg = 'Select at least one employee.'; $msgType = 'danger'; }
    elseif ($mode === 'ot' && !$remove && $hours <= 0) { $msg = 'Enter OT hours (or tick Remove).'; $msgType = 'danger'; }
    else {
        if (!$to || $to < $from) $to = $from;

        // Capped date list
        $dates = [];
        for ($cur = strtotime($from), $end = strtotime($to); $cur <= $end && count($dates) < 90; $cur = strtotime('+1 day', $cur)) {
            $dates[] = date('Y-m-d', $cur);
        }

        // Employees of this company (+ week-off day + code)
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $es = $db->prepare("SELECT id, EmployeeCode, WeekdayNo FROM tblEmployee WHERE CompanyId=? AND id IN ($ph)");
        $es->execute(array_merge([$cid], $ids));
        $emps = $es->fetchAll();

        // Company holidays in range (for skip)
        $holis = [];
        if ($skip) {
            $hs = $db->prepare("SELECT HolidayDate FROM tblHoliday WHERE CompanyId=? AND HolidayDate BETWEEN ? AND ?");
            $hs->execute([$cid, $from, $to]);
            foreach ($hs->fetchAll() as $h) { $holis[$h['HolidayDate']] = true; }
        }

        // Prepared statements per mode
        $otIns  = $db->prepare("INSERT INTO tblOvertime (CompanyId, EmployeeId, OTDate, OTHours, Reason, CreatedBy)
                                VALUES (?,?,?,?,?,?)
                                ON DUPLICATE KEY UPDATE OTHours=VALUES(OTHours), Reason=VALUES(Reason)");
        $otDel  = $db->prepare("DELETE FROM tblOvertime WHERE EmployeeId=? AND OTDate=?");
        $absIns = $db->prepare("INSERT INTO tblPunchLogCorrection (CompanyId, EmpCode, tDate, AttStatus, Reason, CorrectedBy, CorrectedAt)
                                VALUES (?,?,?, 'A', ?, ?, NOW())
                                ON DUPLICATE KEY UPDATE AttStatus='A', Reason=VALUES(Reason), CorrectedBy=VALUES(CorrectedBy), CorrectedAt=NOW()");
        $absDel = $db->prepare("UPDATE tblPunchLogCorrection SET AttStatus=NULL WHERE CompanyId=? AND EmpCode=? AND tDate=? AND AttStatus='A'");

        $count = 0; $skipped = 0;
        foreach ($emps as $e) {
            $eid = (int)$e['id']; $code = $e['EmployeeCode']; $wo = $e['WeekdayNo'];
            foreach ($dates as $d) {
                if ($skip) {
                    if (isset($holis[$d])) { $skipped++; continue; }
                    if ($wo !== null && (int)date('w', strtotime($d)) === (int)$wo) { $skipped++; continue; }
                }
                if ($mode === 'ot') {
                    if ($remove)      { $otDel->execute([$eid, $d]); }
                    else              { $otIns->execute([$cid, $eid, $d, $hours, $reason ?: null, $user['id']]); }
                } else {
                    if ($remove)      { $absDel->execute([$cid, $code, $d]); }
                    else              { $absIns->execute([$cid, $code, $d, $reason ?: null, $user['id']]); }
                }
                $count++;
            }
        }

        $verb  = $remove ? 'Removed' : ($mode === 'ot' ? 'Marked OT for' : 'Marked absent for');
        $unit  = $count === 1 ? 'entry' : 'entries';
        $msg   = "$verb $count $unit" . ($skipped ? " ($skipped week-off/holiday date(s) skipped)" : '') . '.';
    }

    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>$msgType==='success','message'=>$msg,'errors'=>[$msg]]); exit; }
    header("Location: mark.php?company=$cid&msg=" . urlencode($msg) . "&mt=$msgType"); exit;
}
if (isset($_GET['msg'])) { $msg = $_GET['msg']; $msgType = in_array($_GET['mt'] ?? '', ['success','danger']) ? $_GET['mt'] : 'success'; }

// ── Load employees ────────────────────────────────────────────────────────────
$employees = [];
if ($fCompany) {
    $s = $db->prepare("SELECT id, Name, EmployeeCode, Department FROM tblEmployee
                       WHERE CompanyId=? AND Status='active'
                       ORDER BY Department, ISNULL(Sr), Sr, Name");
    $s->execute([$fCompany]); $employees = $s->fetchAll();
}

$pageTitle  = 'Mark OT / Absent';
$activePage = 'mark_ot_abs';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> py-2"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<?php if ($fCompany && !empty($employees)): ?>
<form method="POST" action="mark.php?company=<?= $fCompany ?>" data-ajax>
  <input type="hidden" name="do_mark" value="1">
  <input type="hidden" name="company_id" value="<?= $fCompany ?>">
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Marking Details</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Action</label>
            <div class="btn-group w-100" role="group">
              <input type="radio" class="btn-check" name="mode" id="mode_ot" value="ot" checked>
              <label class="btn btn-outline-primary" for="mode_ot"><i class="bi bi-alarm me-1"></i>Overtime</label>
              <input type="radio" class="btn-check" name="mode" id="mode_abs" value="absent">
              <label class="btn btn-outline-danger" for="mode_abs"><i class="bi bi-person-x me-1"></i>Absent</label>
            </div>
          </div>

          <div class="mb-3" id="otHoursWrap">
            <label class="form-label">OT Hours <span class="text-danger">*</span></label>
            <input type="number" name="ot_hours" class="form-control" step="0.25" min="0" max="24" value="1">
            <div class="form-text">Applied to every selected employee for each date in the range.</div>
          </div>

          <div class="alert alert-light border small mb-3 d-none" id="absNote">
            <i class="bi bi-info-circle me-1"></i>Employees are counted <strong>Absent</strong> even if they punched — their punch times remain visible in the attendance report.
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
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="skip_off" id="skipOff" checked>
            <label class="form-check-label" for="skipOff">Skip week-offs &amp; company holidays</label>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remove" id="removeChk">
            <label class="form-check-label" for="removeChk">Remove instead (un-mark)</label>
          </div>
          <div class="mb-3">
            <label class="form-label">Reason</label>
            <input type="text" name="reason" class="form-control" placeholder="Optional">
          </div>
          <button type="submit" class="btn btn-success w-100"><i class="bi bi-save me-1"></i>Apply</button>
          <div class="form-text mt-1">Range capped at 90 days. Existing entries on a date are overwritten.</div>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <span class="fw-semibold">Employees <span class="badge bg-secondary"><?= count($employees) ?></span></span>
          <input type="text" id="empSearch" class="form-control form-control-sm" style="max-width:220px" placeholder="Search code / name…" autocomplete="off">
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
// Toggle OT-hours vs Absent note by mode
function otModeSync(){
  var abs = document.getElementById('mode_abs').checked;
  document.getElementById('otHoursWrap').classList.toggle('d-none', abs);
  document.getElementById('absNote').classList.toggle('d-none', !abs);
  var h = document.querySelector('[name=ot_hours]'); if (h) h.required = !abs;
}
document.getElementById('mode_ot').addEventListener('change', otModeSync);
document.getElementById('mode_abs').addEventListener('change', otModeSync);
otModeSync();

// Select all — only affects visible rows
document.getElementById('chkAll')?.addEventListener('change', function(){
  var on = this.checked;
  document.querySelectorAll('.emp-row').forEach(function(row){
    if (row.style.display !== 'none') { var c = row.querySelector('.emp-chk'); if (c) c.checked = on; }
  });
});
// Live search
document.getElementById('empSearch')?.addEventListener('input', function(){
  var q = this.value.trim().toLowerCase();
  document.querySelectorAll('.emp-row').forEach(function(row){
    row.style.display = (!q || (row.dataset.search || '').indexOf(q) !== -1) ? '' : 'none';
  });
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
<div class="alert alert-info">Select a company to mark OT or absent.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
