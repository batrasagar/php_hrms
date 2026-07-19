<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
requirePermission('shift_assign.view');

$db   = getDb();
$user = currentUser();
$msg  = '';

// Company comes from the global topbar switcher
$fCompany = activeCompanyId($db, $user);
$fDept    = trim($_GET['dept'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fCompany) {
    requirePermission('shift_assign.edit');
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $ids      = $_POST['emp_ids']  ?? [];
    $shiftNos = $_POST['shift_no'] ?? [];
    $weekdays = $_POST['weekday']  ?? [];
    $saved = 0;
    foreach ($ids as $i => $id) {
        $id = (int)$id;
        if (!$id) continue;
        if (in_array($user['role'], ['admin','operator'], true)) {
            $chk = $db->prepare("SELECT e.id FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId AND c.AdminId=? WHERE e.id=?");
            $chk->execute([$user['scope_id'], $id]);
            if (!$chk->fetch()) continue;
        }
        $shiftNo = ($shiftNos[$i] ?? '') !== '' ? (int)$shiftNos[$i] : null;
        $weekday = ($weekdays[$i] ?? '') !== '' ? (int)$weekdays[$i] : null;
        $db->prepare("UPDATE tblEmployee SET ShiftNo=?, WeekdayNo=?, UpdatedAt=NOW() WHERE id=?")
           ->execute([$shiftNo, $weekday, $id]);
        $saved++;
    }
    $successMsg = "Saved $saved row(s).";
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$successMsg]); exit; }
    header("Location: assign.php?company=$fCompany&dept=" . urlencode($fDept) . "&saved=$saved");
    exit;
}
if (isset($_GET['saved'])) $msg = 'Saved ' . (int)$_GET['saved'] . ' row(s).';

$shifts    = [];
$employees = [];
$depts     = [];

if ($fCompany) {
    $s = $db->prepare("SELECT id, ShiftName FROM tblShift WHERE CompanyId=? AND IsActive=1 ORDER BY ShiftName");
    $s->execute([$fCompany]);
    $shifts = $s->fetchAll();

    // Check if cyclic tables exist (M014 may not be run yet)
    $hasCyclic = false;
    try { $db->query("SELECT 1 FROM tblShiftCycle LIMIT 1"); $hasCyclic = true; } catch (PDOException $e) {}

    $where  = ['e.CompanyId = ?'];
    $params = [$fCompany];
    if (in_array($user['role'], ['admin','operator'], true)) { $where[] = 'c.AdminId = ?'; $params[] = $user['scope_id']; }
    if ($fDept) { $where[] = 'e.Department = ?'; $params[] = $fDept; }
    $wsql = 'WHERE ' . implode(' AND ', $where);

    $cycleJoin = $hasCyclic
        ? "LEFT JOIN tblEmployeeShiftCycle esc ON esc.EmployeeId = e.id AND esc.IsActive = 1
           LEFT JOIN tblShiftCycle sc ON sc.id = esc.CycleId"
        : '';
    $cycleCols = $hasCyclic ? ", sc.Name AS CycleName" : ", NULL AS CycleName";

    $stmt = $db->prepare(
        "SELECT e.id, e.Name, e.EmployeeCode, e.Department, e.ShiftNo, e.WeekdayNo,
                s.ShiftName AS CurrentShift $cycleCols
         FROM tblEmployee e
         JOIN tblCompany c ON c.id = e.CompanyId
         LEFT JOIN tblShift s ON s.id = e.ShiftNo
         $cycleJoin
         $wsql AND e.Status = 'active'
         ORDER BY e.Department, ISNULL(e.Sr), e.Sr, e.Name"
    );
    $stmt->execute($params);
    $employees = $stmt->fetchAll();

    $cid = (int)$fCompany;
    $scopeExtra = in_array($user['role'], ['admin','operator'], true) ? "AND c.AdminId={$user['scope_id']}" : '';
    $depts = array_filter(array_column(
        $db->query("SELECT DISTINCT e.Department FROM tblEmployee e
                    JOIN tblCompany c ON c.id=e.CompanyId
                    WHERE e.CompanyId=$cid $scopeExtra AND e.Department IS NOT NULL
                    ORDER BY e.Department")->fetchAll(),
        'Department'
    ));
}

$weekdayLabels = ['0'=>'Sunday','1'=>'Monday','2'=>'Tuesday','3'=>'Wednesday','4'=>'Thursday','5'=>'Friday','6'=>'Saturday'];

$pageTitle  = 'Shift Assignment';
$activePage = 'shift_assign';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-success py-2"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="company" value="<?= (int)$fCompany ?>">
      <div class="col-sm-3">
        <label class="form-label small mb-1">Department</label>
        <select name="dept" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All Departments</option>
          <?php foreach ($depts as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>" <?= $fDept===$d?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-4">
        <label class="form-label small mb-1">Search</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" id="assignSearch" class="form-control" placeholder="Code or name&hellip;" autocomplete="off">
          <button class="btn btn-outline-secondary" type="button" id="assignSearchClear" title="Clear search"><i class="bi bi-x-lg"></i></button>
        </div>
      </div>
      <div class="col-auto">
        <span class="text-muted small">
          <span id="assignCount"><?= count($employees) ?></span> of <?= count($employees) ?> employee(s)
        </span>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Shift &amp; Week Off Assignment</span>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <?php if (!empty($employees)): ?>
      <div class="input-group input-group-sm" style="width:auto">
        <span class="input-group-text">Set WO for selected</span>
        <select id="bulkWO" class="form-select form-select-sm" style="max-width:130px">
          <option value="">— None —</option>
          <?php foreach ($weekdayLabels as $val => $label): ?>
          <option value="<?= $val ?>"><?= $label ?></option>
          <?php endforeach; ?>
        </select>
        <button type="button" id="applyWO" class="btn btn-outline-primary">Apply</button>
      </div>
      <?php endif; ?>
      <a href="cyclic.php?company=<?= $fCompany ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-repeat me-1"></i>Cyclic Shifts
      </a>
      <button form="assignForm" type="submit" class="btn btn-success btn-sm">
        <i class="bi bi-save me-1"></i>Save Changes
      </button>
    </div>
  </div>
  <div class="card-body p-0" style="overflow-x:auto">
    <form id="assignForm" method="POST" action="assign.php?company=<?= $fCompany ?>&dept=<?= urlencode($fDept) ?>" data-ajax>
    <?php if (empty($employees)): ?>
    <div class="p-4 text-center text-muted">No active employees for this company.</div>
    <?php else: ?>
    <style>
      #tblAssign td { padding:3px 6px !important; vertical-align:middle; }
      #tblAssign .form-select {
        padding:2px 24px 2px 6px !important; border:1px solid #dee2e6 !important;
        border-radius:4px !important; height:auto !important; min-height:unset !important;
        font-size:13px !important;
      }
    </style>
    <table id="tblAssign" class="table table-sm table-bordered mb-0" style="min-width:650px">
      <thead class="table-light">
        <tr>
          <th style="width:34px" class="text-center"><input type="checkbox" id="chkAll" class="form-check-input"></th>
          <th style="min-width:130px">Name</th>
          <th style="min-width:90px">Code</th>
          <th style="min-width:100px">Department</th>
          <th style="min-width:160px">Shift</th>
          <th style="min-width:140px">Week Off</th>
        </tr>
      </thead>
      <tbody>
      <?php
      $prevDept = null;
      foreach ($employees as $e):
          if ($e['Department'] !== $prevDept):
              $prevDept = $e['Department'];
      ?>
      <tr class="table-light dept-row">
        <td colspan="6" class="small fw-semibold text-muted py-1 px-2">
          <?= htmlspecialchars($e['Department'] ?? '— No Department —') ?>
        </td>
      </tr>
      <?php endif; ?>
      <tr class="emp-row" data-search="<?= htmlspecialchars(strtolower(($e['EmployeeCode'] ?? '') . ' ' . $e['Name'])) ?>">
        <input type="hidden" name="emp_ids[]" value="<?= $e['id'] ?>">
        <td class="text-center"><input type="checkbox" class="form-check-input row-chk"></td>
        <td class="fw-semibold">
          <?= htmlspecialchars($e['Name']) ?>
          <?php if ($e['CycleName']): ?>
          <span class="badge bg-info-subtle text-info ms-1" title="On cyclic shift: <?= htmlspecialchars($e['CycleName']) ?>">
            <i class="bi bi-arrow-repeat"></i> <?= htmlspecialchars($e['CycleName']) ?>
          </span>
          <?php endif; ?>
        </td>
        <td class="small text-muted"><?= htmlspecialchars($e['EmployeeCode'] ?: '—') ?></td>
        <td class="small"><?= htmlspecialchars($e['Department'] ?? '—') ?></td>
        <td>
          <select name="shift_no[]" class="form-select form-select-sm">
            <option value="">— None —</option>
            <?php foreach ($shifts as $sh): ?>
            <option value="<?= $sh['id'] ?>" <?= (int)$e['ShiftNo']===$sh['id']?'selected':'' ?>>
              <?= htmlspecialchars($sh['ShiftName']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </td>
        <td>
          <select name="weekday[]" class="form-select form-select-sm">
            <option value="">— None —</option>
            <?php foreach ($weekdayLabels as $val => $label): ?>
            <option value="<?= $val ?>" <?= (string)$e['WeekdayNo']===(string)$val?'selected':'' ?>>
              <?= $label ?>
            </option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
    </form>
  </div>
</div>
<script>
$(function(){
  // ── Live filter by employee code / name ────────────────────────────────────
  // Rows are hidden, never removed, so every row's selects still post and no
  // unsaved shift/week-off edit is lost while searching.
  function applyAssignSearch() {
    var q = ($('#assignSearch').val() || '').trim().toLowerCase();
    var shown = 0;
    $('#tblAssign tbody tr.emp-row').each(function(){
      var hit = !q || (this.getAttribute('data-search') || '').indexOf(q) !== -1;
      this.style.display = hit ? '' : 'none';
      // A hidden row must not stay ticked, or a bulk apply would silently reach
      // employees the user can no longer see.
      if (!hit) $(this).find('.row-chk').prop('checked', false);
      if (hit) shown++;
    });
    // Hide a department heading once none of its employees are visible.
    $('#tblAssign tbody tr.dept-row').each(function(){
      var visible = false;
      for (var n = this.nextElementSibling; n && !n.classList.contains('dept-row'); n = n.nextElementSibling) {
        if (n.classList.contains('emp-row') && n.style.display !== 'none') { visible = true; break; }
      }
      this.style.display = visible ? '' : 'none';
    });
    $('#assignCount').text(shown);
    $('#chkAll').prop('checked', false);
  }

  $('#assignSearch').on('input', applyAssignSearch);
  // Enter would submit the surrounding GET filter form and drop the term.
  $('#assignSearch').on('keydown', function(e){
    if (e.key === 'Enter') { e.preventDefault(); }
    if (e.key === 'Escape' && this.value) { e.preventDefault(); this.value = ''; applyAssignSearch(); }
  });
  $('#assignSearchClear').on('click', function(){
    $('#assignSearch').val(''); applyAssignSearch(); $('#assignSearch').focus();
  });

  // Select-all applies to the rows actually on screen, not the filtered-out ones.
  $('#chkAll').on('change', function(){
    var on = this.checked;
    $('#tblAssign tbody tr.emp-row').each(function(){
      if (this.style.display !== 'none') $(this).find('.row-chk').prop('checked', on);
    });
  });

  $('#applyWO').on('click', function(){
    var wo = $('#bulkWO').val(), n = 0;
    $('.row-chk:checked').each(function(){
      $(this).closest('tr').find('select[name="weekday[]"]').val(wo);
      n++;
    });
    if (!n) { alert('Tick at least one employee first.'); return; }
  });
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
