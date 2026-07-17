<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
$pageTitle  = 'Attendance Report';
$activePage = 'report_attendance';

$db   = getDb();
$user = currentUser();

// Company comes from the global topbar switcher — filter data moved to ajax/attendance_data.php
$fCompany = activeCompanyId($db, $user);

$fFrom       = trim($_GET['from']       ?? date('Y-m-d'));
$fTo         = trim($_GET['to']         ?? date('Y-m-d'));
$fDept       = trim($_GET['dept']       ?? '');
$fContractor = trim($_GET['contractor'] ?? '');

$scopeJoin   = $user['role'] === 'superadmin' ? '' : 'JOIN tblCompany c ON c.id=e.CompanyId AND c.AdminId=' . $user['scope_id'];
$scopeJoin  .= complianceEmpFilter('e');   // compliance role → only compliance employees
$depts       = array_filter(array_column($db->query("SELECT DISTINCT Department FROM tblEmployee e $scopeJoin ORDER BY Department")->fetchAll(), 'Department'));
$contractors = array_filter(array_column($db->query("SELECT DISTINCT Contractor FROM tblEmployee e $scopeJoin ORDER BY Contractor")->fetchAll(), 'Contractor'));

$dataUrl   = BASE_URL . '/ajax/attendance_data.php';
$actionUrl = BASE_URL . '/ajax/attendance_action.php';
$autoload  = (int)$fCompany;
$canEdit   = in_array($user['role'], ['admin','superadmin','operator','compliance'], true) ? 1 : 0;

// Server-side option lists for the edit modal so the Leave-code and Shift
// dropdowns are populated on page load, independent of the JS/ajax path.
$leaveTypesDd = [];
$shiftsDd     = [];
if ($canEdit && $fCompany) {
    $lt = $db->prepare("SELECT Code, Name FROM tblLeaveType WHERE CompanyId=? AND IsActive=1 ORDER BY Code");
    $lt->execute([$fCompany]);
    $leaveTypesDd = $lt->fetchAll();
    $sh = $db->prepare("SELECT id, ShiftName FROM tblShift WHERE CompanyId=? AND IsActive=1 ORDER BY ShiftName");
    $sh->execute([$fCompany]);
    $shiftsDd = $sh->fetchAll();
}

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form id="attForm" method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="company" value="<?= (int)$fCompany ?>">
      <div class="col-6 col-sm-4 col-md-2"><label class="form-label small mb-1">From</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($fFrom) ?>">
      </div>
      <div class="col-6 col-sm-4 col-md-2"><label class="form-label small mb-1">To</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($fTo) ?>">
      </div>
      <div class="col-6 col-sm-4 col-md-2"><label class="form-label small mb-1">Department</label>
        <select name="dept" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($depts as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>" <?= $fDept===$d?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-sm-4 col-md-2"><label class="form-label small mb-1">Contractor</label>
        <select name="contractor" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($contractors as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>" <?= $fContractor===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-sm-auto d-flex gap-1 flex-wrap">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Load</button>
        <a id="btnPrint" href="#" target="_blank" class="btn btn-outline-success btn-sm d-none"><i class="bi bi-printer-fill"></i> Print</a>
        <a id="btnGrid"  href="#" target="_blank" class="btn btn-outline-secondary btn-sm d-none"><i class="bi bi-grid-3x3"></i> Status Grid</a>
      </div>
    </form>
  </div>
</div>

<div id="attSearchBar" class="mb-2 d-none">
  <div class="input-group input-group-sm" style="max-width:300px">
    <span class="input-group-text"><i class="bi bi-search"></i></span>
    <input type="text" id="attSearch" class="form-control" placeholder="Search code / name…" autocomplete="off">
  </div>
</div>

<div id="filter-results">
  <div class="alert alert-info">Select a company and date range, then click <strong>Load</strong>.</div>
</div>

<?php if ($canEdit): ?>
<!-- ── Cell action modal ─────────────────────────────────────────────────────── -->
<div class="modal fade" id="attActionModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title" id="aa_title">Edit day</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="small text-muted mb-2" id="aa_sub"></div>

        <div class="btn-group btn-group-sm w-100 mb-3" role="group">
          <input type="radio" class="btn-check" name="aa_act" id="aa_act_leave" value="leave" checked>
          <label class="btn btn-outline-primary" for="aa_act_leave">Leave</label>
          <input type="radio" class="btn-check" name="aa_act" id="aa_act_manual" value="manual_time">
          <label class="btn btn-outline-primary" for="aa_act_manual">Time</label>
          <input type="radio" class="btn-check" name="aa_act" id="aa_act_comp" value="comp_off">
          <label class="btn btn-outline-primary" for="aa_act_comp">Comp Off</label>
          <input type="radio" class="btn-check" name="aa_act" id="aa_act_wo" value="week_off">
          <label class="btn btn-outline-primary" for="aa_act_wo">Week Off</label>
          <input type="radio" class="btn-check" name="aa_act" id="aa_act_shift" value="shift">
          <label class="btn btn-outline-primary" for="aa_act_shift">Shift</label>
        </div>

        <!-- Leave -->
        <div class="aa-pane" data-pane="leave">
          <div class="mb-2">
            <label class="form-label small mb-1">Leave Code</label>
            <select id="aa_leave_code" class="form-select form-select-sm">
              <?php foreach ($leaveTypesDd as $lt): ?>
              <option value="<?= htmlspecialchars($lt['Code']) ?>"><?= htmlspecialchars($lt['Code'].' — '.$lt['Name']) ?></option>
              <?php endforeach; ?>
              <?php if (!$leaveTypesDd): ?><option value="">(no leave types defined)</option><?php endif; ?>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label small mb-1">Day Type</label>
            <select id="aa_leave_daytype" class="form-select form-select-sm">
              <option value="full_day">Full Day</option>
              <option value="half_am">Half AM</option>
              <option value="half_pm">Half PM</option>
            </select>
          </div>
        </div>

        <!-- Manual time -->
        <div class="aa-pane" data-pane="manual_time" hidden>
          <div class="row g-2 mb-2">
            <div class="col-4"><label class="form-label small mb-1">In</label>
              <input type="time" id="aa_in" class="form-control form-control-sm"></div>
            <div class="col-4"><label class="form-label small mb-1">Out</label>
              <input type="time" id="aa_out" class="form-control form-control-sm"></div>
            <div class="col-4"><label class="form-label small mb-1">Force</label>
              <select id="aa_force" class="form-select form-select-sm" title="Leave as Auto to compute from In/Out">
                <option value="">Auto</option>
                <?php foreach (['P','A','HD','WO','PH','OD'] as $s): ?>
                <option value="<?= $s ?>"><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="form-text">Set In/Out times, or force a status. Reflects in this grid immediately.</div>
        </div>

        <!-- Comp off -->
        <div class="aa-pane" data-pane="comp_off" hidden>
          <div class="small">Mark this day as a <span class="badge" style="background:#0dcaf0;color:#053d47">Comp Off</span> taken (shows CO in the grid).</div>
        </div>

        <!-- Week off -->
        <div class="aa-pane" data-pane="week_off" hidden>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="aa_wo_mode" id="aa_wo_date" value="date" checked>
            <label class="form-check-label small" for="aa_wo_date">Mark <strong id="aa_wo_dateLbl">this date</strong> as week-off (one-time)</label>
          </div>
          <div class="form-check d-flex align-items-center gap-2">
            <input class="form-check-input" type="radio" name="aa_wo_mode" id="aa_wo_recurring" value="recurring">
            <label class="form-check-label small" for="aa_wo_recurring">Change weekly off to</label>
            <select id="aa_wo_weekday" class="form-select form-select-sm" style="width:auto">
              <?php foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $i => $wn): ?>
              <option value="<?= $i ?>"><?= $wn ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Shift -->
        <div class="aa-pane" data-pane="shift" hidden>
          <div class="mb-2">
            <label class="form-label small mb-1">Shift</label>
            <select id="aa_shift_id" class="form-select form-select-sm">
              <option value="">— No shift —</option>
              <?php foreach ($shiftsDd as $s): ?>
              <option value="<?= (int)$s['id'] ?>"><?= htmlspecialchars($s['ShiftName']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="aa_shift_mode" id="aa_shift_day" value="day" checked>
            <label class="form-check-label small" for="aa_shift_day">Mark for <strong id="aa_shift_dateLbl">this date</strong> only</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="aa_shift_mode" id="aa_shift_onwards" value="onwards">
            <label class="form-check-label small" for="aa_shift_onwards">Change <strong id="aa_shift_name">this employee</strong>'s shift from now onwards</label>
          </div>
        </div>

        <div class="mt-2">
          <input type="text" id="aa_reason" class="form-control form-control-sm" placeholder="Reason (optional)">
        </div>
      </div>
      <div class="modal-footer py-2 d-flex justify-content-between">
        <button type="button" class="btn btn-outline-danger btn-sm" id="aa_clear"><i class="bi bi-eraser"></i> Clear cell</button>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary btn-sm" id="aa_save">Save</button>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function(){
  var ACTION_URL = '<?= $actionUrl ?>';
  var ctx = {};
  function coVal(){ return $('#attForm').find('[name=company]').val() || ''; }
  function modal(){ return bootstrap.Modal.getOrCreateInstance(document.getElementById('attActionModal')); }
  function showPane(which){
    document.querySelectorAll('#attActionModal .aa-pane').forEach(function(p){ p.hidden = (p.dataset.pane !== which); });
  }

  $(document).on('dblclick', '#tblAttendance td.att-cell.editable', function(){
    var d = this.dataset;
    ctx = { emp:d.emp, code:d.code, name:d.name, date:d.date, type:d.type };
    $('#aa_title').text('Edit — ' + d.name);
    $('#aa_sub').html('<code>'+(d.code||'—')+'</code> &middot; '+d.date+(d.type ? ' &middot; currently <b>'+d.type+'</b>' : ''));
    // Selects are pre-populated server-side. Only re-render from ajax data when it
    // exists (adds leave balances / refreshes shifts) — never blank them otherwise.
    var lts = (window.__attLastData && window.__attLastData.leaveTypes) || [];
    var bals = (window.__attLastData && window.__attLastData.leaveBalances && window.__attLastData.leaveBalances[d.emp]) || {};
    if (lts.length) {
      var opts = '';
      lts.forEach(function(lt){
        var b = bals[lt.Code];
        var bl = (b === undefined || b === null) ? '' : ' (bal ' + (Number.isInteger(b) ? b : b.toFixed(1)) + ')';
        opts += '<option value="'+lt.Code+'">'+lt.Code+' — '+lt.Name+bl+'</option>';
      });
      $('#aa_leave_code').html(opts);
    }
    var shs = (window.__attLastData && window.__attLastData.shifts) || [];
    var curShift = '';
    ((window.__attLastData && window.__attLastData.employees) || []).forEach(function(e){ if (String(e.id) === String(d.emp)) curShift = e.shiftNo; });
    if (shs.length) {
      var sopts = '<option value="">— No shift —</option>';
      shs.forEach(function(s){ sopts += '<option value="'+s.id+'">'+s.ShiftName+'</option>'; });
      $('#aa_shift_id').html(sopts);
    }
    $('#aa_shift_id').val(curShift ? String(curShift) : '');
    $('#aa_shift_name').text(d.name);
    $('#aa_shift_dateLbl').text(d.date);
    $('input[name=aa_shift_mode][value=day]').prop('checked', true);
    var wd = new Date(d.date + 'T00:00:00').getDay();
    $('#aa_wo_weekday').val(String(wd));
    $('#aa_wo_dateLbl').text(d.date);
    $('#aa_reason').val(''); $('#aa_in').val(d.in||''); $('#aa_out').val(d.out||''); $('#aa_force').val('');
    $('input[name=aa_act][value=leave]').prop('checked', true);
    $('input[name=aa_wo_mode][value=date]').prop('checked', true);
    showPane('leave');
    modal().show();
  });

  $('input[name=aa_act]').on('change', function(){ showPane(this.value); });

  function post(data){
    data.company = coVal(); data.emp_id = ctx.emp; data.date = ctx.date; data.reason = $('#aa_reason').val();
    $('#aa_save,#aa_clear').prop('disabled', true);
    $.post(ACTION_URL, data)
      .done(function(resp){
        if (resp && resp.success){
          showToast(resp.message || 'Saved.', 'success');
          modal().hide();
          if (window.__attReload) window.__attReload();
        } else {
          showToast((resp && resp.errors && resp.errors[0]) || 'Failed.', 'danger');
        }
      })
      .fail(function(){ showToast('Server error.', 'danger'); })
      .always(function(){ $('#aa_save,#aa_clear').prop('disabled', false); });
  }

  $('#aa_save').on('click', function(){
    var act = $('input[name=aa_act]:checked').val();
    if (act === 'leave')            post({ action:'leave', code:$('#aa_leave_code').val(), day_type:$('#aa_leave_daytype').val() });
    else if (act === 'manual_time') post({ action:'manual_time', in_time:$('#aa_in').val(), out_time:$('#aa_out').val(), force_status:$('#aa_force').val() });
    else if (act === 'comp_off')    post({ action:'comp_off' });
    else if (act === 'week_off'){
      var mode = $('input[name=aa_wo_mode]:checked').val();
      if (mode === 'recurring') post({ action:'week_off_recurring', weekday:$('#aa_wo_weekday').val() });
      else                      post({ action:'week_off_date' });
    }
    else if (act === 'shift')       post({ action:'shift_change', shift_id:$('#aa_shift_id').val(), mode:$('input[name=aa_shift_mode]:checked').val() });
  });
  $('#aa_clear').on('click', function(){ post({ action:'clear' }); });
});
</script>
<?php endif; ?>

<?php $extraJs = <<<JS
<style>
#att-loader {
  display:none; position:fixed; inset:0;
  background:rgba(255,255,255,.88); z-index:9999;
  align-items:center; justify-content:center; flex-direction:column; gap:14px;
}
#att-loader.show { display:flex; }
#att-loader .sp {
  width:48px; height:48px;
  border:5px solid #dee2e6; border-top-color:#0d6efd;
  border-radius:50%; animation:attSpin .8s linear infinite;
}
#att-loader p { font-size:14px; color:#555; margin:0; }
@keyframes attSpin { to { transform:rotate(360deg); } }
#tblAttendance td.att-cell { padding:2px 0 !important; vertical-align:middle; position:relative; }
#tblAttendance td.att-cell.editable { cursor:pointer; user-select:none; -webkit-user-select:none; }
#tblAttendance td.att-cell.editable:hover { outline:2px solid #0d6efd; outline-offset:-2px; }
.aa-dot { position:absolute; top:0; right:1px; color:#fd7e14; font-size:8px; line-height:1; }
#tblAttendance tfoot td    { font-size:10px; padding:2px 3px !important; }
</style>
<div id="att-loader"><div class="sp"></div><p>Loading attendance data&hellip;</p></div>
<script>
(function () {
  var DATA_URL  = '$dataUrl';
  var AUTOLOAD  = $autoload;
  var CAN_EDIT  = $canEdit;
  var lastData  = null;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function renderCell(c) {
    switch (c.type) {
      case 'SUN': return ['<span style="font-size:9px">S</span>', '#f0f0f0', 'text-muted'];
      case 'HOL': return ['<span style="font-size:9px" title="'+esc(c.holName)+'">H</span>', '#e8f5e9', 'text-success'];
      case 'L':   return ['<span style="font-size:9px;font-weight:700">L</span>', '#ffcccc', 'text-danger'];
      case 'CO':  return ['<span style="font-size:9px;font-weight:700">CO</span>', '#cff4fc', 'text-info'];
      case 'WO':  return ['<span style="font-size:9px">WO</span>', '#e2e3e5', 'text-muted'];
      case 'HL':
        return ['<span style="font-size:9px;font-weight:700">HL</span><div style="font-size:7px;color:#856404">'+(c.lvSub||'')+'</div>',
                '#fff3cd', 'text-warning'];
      case 'P':
      case 'HP': {
        var cls = c.type === 'P' ? 'text-success' : 'text-primary';
        var sft = c.shift ? '<span style="float:right;font-size:7px;color:#888;font-weight:normal">'+esc(c.shift)+'</span>' : '';
        var tot = c.tot  ? '<div style="font-size:7px;color:#444">'+esc(c.tot)+(c.ot?'<br><b style="color:#e65100">+'+esc(c.ot)+'</b>':'')+'</div>' : '';
        return ['<b style="font-size:9px">'+c.type+'</b>'+sft
               +'<div style="font-size:10px;line-height:1.3;color:#333;clear:both">'+esc(c.in)+'</div>'
               +'<div style="font-size:10px;line-height:1.3;color:#555">'+(c.out||'—')+'</div>'+tot,
               '#d4edda', cls];
      }
      case 'A':
        if (c.absPunch && c.in) {
          // Marked absent but punches exist — show A with the (muted) punch times.
          return ['<b style="font-size:9px">A</b>'
                 +'<div style="font-size:9px;line-height:1.2;color:#b08">'+esc(c.in)+'</div>'
                 +'<div style="font-size:9px;line-height:1.2;color:#c39">'+(c.out||'—')+'</div>',
                 '#ffe0e6', 'text-danger'];
        }
        return ['<span style="font-size:9px">A</span>', '#fff0f0', 'text-danger'];
      default:  return ['', '', ''];
    }
  }

  function statCard(val, cls, label) {
    return '<div class="col-6 col-sm-4 col-md-2"><div class="card border-0 shadow-sm text-center py-2">'
         + '<div class="fs-4 fw-bold '+cls+'">'+val+'</div>'
         + '<div class="small text-muted">'+label+'</div></div></div>';
  }

  function render(data) {
    var html = '';
    lastData = data; window.__attLastData = data;

    if (data.notice) html += '<div class="alert alert-warning py-2 small">'+esc(data.notice)+'</div>';
    (data.errors||[]).forEach(function(e){ html += '<div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>'+esc(e)+'</div>'; });

    if (!data.employees || !data.employees.length) {
      html += '<div class="alert alert-info">No employees found for the selected filters.</div>';
      $('#filter-results').html(html);
      updatePrintBtns(false);
      return;
    }

    html += '<div class="alert alert-info small py-2">Legend: '
          + '<span class="badge bg-success">P</span> Present &nbsp;'
          + '<span class="badge bg-primary">HP</span> Half-Present &nbsp;'
          + '<span class="badge bg-danger">A</span> Absent &nbsp;'
          + '<span class="badge" style="background:#dc3545">L</span> Full Leave &nbsp;'
          + '<span class="badge text-dark" style="background:#6edff6">CO</span> Comp Off &nbsp;'
          + '<span class="badge text-dark" style="background:#e2e3e5">WO</span> Week Off &nbsp;'
          + '<span class="badge bg-warning text-dark">HL</span> Half Leave &nbsp;'
          + '<span class="badge bg-light text-muted border">H</span> Holiday &nbsp;'
          + '<span class="badge bg-secondary">S</span> Sunday &nbsp;'
          + '<span style="color:#e65100;font-weight:600">+Xm</span> Overtime</div>';

    var minW = 210 + data.dates.length * 40;
    html += '<div class="card border-0 shadow-sm" style="overflow-x:auto">';
    html += '<div class="card-header bg-white fw-semibold">Attendance &mdash; '+esc(data.fFrom)+' to '+esc(data.fTo)+'</div>';
    html += '<table id="tblAttendance" class="table table-sm table-bordered mb-0" style="min-width:'+minW+'px">';

    // thead
    html += '<thead class="table-light"><tr>'
          + '<th style="min-width:80px">Code</th><th style="min-width:130px">Name</th>';
    data.dates.forEach(function(d){
      html += '<th class="text-center" style="min-width:40px;background:'+d.bg+'" title="'+esc(d.isHol?d.holName:d.dayName)+'">'
            + '<div>'+esc(d.dayNum)+'</div>'
            + '<div class="text-muted" style="font-size:9px">'+esc(d.dayLetter)+'</div></th>';
    });
    html += '<th class="text-center" style="min-width:28px" title="Full Present">P</th>'
          + '<th class="text-center" style="min-width:28px" title="Half Present">HP</th>'
          + '<th class="text-center" style="min-width:28px" title="Absent">A</th>'
          + '<th class="text-center" style="min-width:28px" title="Full Leave">L</th>'
          + '<th class="text-center" style="min-width:28px" title="Comp Off">CO</th>'
          + '<th class="text-center" style="min-width:28px" title="Half Leave">HL</th>'
          + '</tr></thead>';

    // tbody
    html += '<tbody>';
    data.employees.forEach(function(emp){
      var srch = ((emp.code||'')+' '+(emp.name||'')).toLowerCase();
      html += '<tr data-search="'+esc(srch)+'"><td class="small"><code>'+esc(emp.code||'—')+'</code></td><td class="small">'+esc(emp.name)
            + (emp.fatherName ? '<div style="font-size:8px;color:#888;line-height:1.15">S/o '+esc(emp.fatherName)+'</div>' : '')
            + (emp.designation ? '<div style="font-size:8px;color:#888;line-height:1.15">'+esc(emp.designation)+'</div>' : '')
            + '</td>';
      data.dates.forEach(function(d){
        var cd = emp.days[d.date]||{type:''};
        var r  = renderCell(cd);
        var mark = cd.corr ? '<span class="aa-dot" title="Manually edited">&#9679;</span>' : '';
        var cls  = 'text-center att-cell ' + r[2] + (CAN_EDIT ? ' editable' : '');
        var attrs = CAN_EDIT ? (' data-emp="'+emp.id+'" data-code="'+esc(emp.code||'')+'" data-name="'+esc(emp.name)+'" data-date="'+d.date+'" data-type="'+esc(cd.type||'')+'" data-in="'+esc(cd.in||'')+'" data-out="'+esc(cd.out||'')+'"') : '';
        html += '<td class="'+cls+'" style="background:'+r[1]+'"'+attrs+'>'+r[0]+mark+'</td>';
      });
      var s = emp.summary;
      html += '<td class="text-center fw-semibold text-success">'+s.P+'</td>'
            + '<td class="text-center fw-semibold text-primary">'+(s.HP||'—')+'</td>'
            + '<td class="text-center fw-semibold text-danger">'+s.A+'</td>'
            + '<td class="text-center fw-semibold" style="color:#c0392b">'+(s.L||'—')+'</td>'
            + '<td class="text-center fw-semibold text-info">'+(s.CO||'—')+'</td>'
            + '<td class="text-center fw-semibold text-warning">'+(s.HL||'—')+'</td></tr>';
    });
    html += '</tbody>';

    // tfoot
    html += '<tfoot><tr class="table-dark"><td colspan="2" class="fw-semibold text-center">Daily Total</td>';
    data.dates.forEach(function(d){
      var dt = data.dayTotals[d.date]||{P:0,HP:0,A:0,L:0,HL:0,CO:0};
      var bg = d.isSun ? 'background:#444' : (d.isHol ? 'background:#2e7d32' : '');
      html += '<td class="text-center" style="'+bg+'">';
      if      (d.isSun) html += '<span style="color:#aaa;font-size:9px">S</span>';
      else if (d.isHol) html += '<span style="color:#a5d6a7;font-size:9px">H</span>';
      else if (d.isFut) html += '<span style="color:#888;font-size:9px">—</span>';
      else {
        if (dt.P)  html += '<div style="color:#a5d6a7;font-size:9px;line-height:1.1">P:'+dt.P+'</div>';
        if (dt.HP) html += '<div style="color:#90caf9;font-size:9px;line-height:1.1">HP:'+dt.HP+'</div>';
        if (dt.A)  html += '<div style="color:#ef9a9a;font-size:9px;line-height:1.1">A:'+dt.A+'</div>';
        if (dt.L)  html += '<div style="color:#ef9a9a;font-size:9px;line-height:1.1">L:'+dt.L+'</div>';
        if (dt.CO) html += '<div style="color:#4dd0e1;font-size:9px;line-height:1.1">CO:'+dt.CO+'</div>';
        if (dt.HL) html += '<div style="color:#ffe082;font-size:9px;line-height:1.1">HL:'+dt.HL+'</div>';
      }
      html += '</td>';
    });
    var g = data.grand;
    html += '<td class="text-center text-success fw-bold">'+g.P+'</td>'
          + '<td class="text-center text-primary fw-bold">'+(g.HP||'—')+'</td>'
          + '<td class="text-center text-danger fw-bold">'+g.A+'</td>'
          + '<td class="text-center fw-bold" style="color:#ef9a9a">'+(g.L||'—')+'</td>'
          + '<td class="text-center fw-bold" style="color:#4dd0e1">'+(g.CO||'—')+'</td>'
          + '<td class="text-center text-warning fw-bold">'+(g.HL||'—')+'</td>'
          + '</tr></tfoot></table></div>';

    // Summary stats
    var hpBadge = g.HP ? ' <small class="fs-6 text-primary">+'+g.HP+'HP</small>' : '';
    var hlBadge = g.HL ? ' <small class="fs-6 text-warning">+'+g.HL+'HL</small>' : '';
    html += '<div class="row g-2 mt-2">'
          + statCard(data.totalEmps,   'text-secondary', 'Employees')
          + statCard(data.workingDays, 'text-muted',     'Working Days')
          + '<div class="col-6 col-sm-4 col-md-2"><div class="card border-0 shadow-sm text-center py-2">'
          +   '<div class="fs-4 fw-bold text-success">'+g.P+hpBadge+'</div>'
          +   '<div class="small text-muted">Present <span class="badge bg-success-subtle text-success">'+data.pctP+'%</span></div>'
          + '</div></div>'
          + '<div class="col-6 col-sm-4 col-md-2"><div class="card border-0 shadow-sm text-center py-2">'
          +   '<div class="fs-4 fw-bold text-danger">'+g.A+'</div>'
          +   '<div class="small text-muted">Absent <span class="badge bg-danger-subtle text-danger">'+data.pctA+'%</span></div>'
          + '</div></div>'
          + '<div class="col-6 col-sm-4 col-md-2"><div class="card border-0 shadow-sm text-center py-2">'
          +   '<div class="fs-4 fw-bold" style="color:#c0392b">'+g.L+hlBadge+'</div>'
          +   '<div class="small text-muted">On Leave</div>'
          + '</div></div>'
          + statCard(data.holidayCount, 'text-muted', 'Holidays')
          + '</div>';

    $('#filter-results').html(html);
    updatePrintBtns(true);
    applyAttSearch();
  }

  function updatePrintBtns(show) {
    if (!show) { $('#btnPrint,#btnGrid').addClass('d-none'); $('#attSearchBar').addClass('d-none'); return; }
    var qs = $('#attForm').serialize();
    $('#btnPrint').attr('href', 'attendance_print.php?'+qs).removeClass('d-none');
    $('#btnGrid').attr('href',  'attendance_print_simple.php?'+qs).removeClass('d-none');
    $('#attSearchBar').removeClass('d-none');
  }

  // Live filter of the attendance grid by employee code / name.
  function applyAttSearch() {
    var q = ($('#attSearch').val() || '').trim().toLowerCase();
    $('#tblAttendance tbody tr').each(function() {
      var s = this.getAttribute('data-search') || '';
      this.style.display = (!q || s.indexOf(q) !== -1) ? '' : 'none';
    });
  }

  function load() {
    var params = $('#attForm').serialize();
    history.pushState(null, '', window.location.pathname + '?' + params);

    var loader = document.getElementById('att-loader');
    loader.classList.add('show');
    var \$btn = $('#attForm [type=submit]');
    \$btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Loading…');

    $.getJSON(DATA_URL + '?' + params)
      .done(function(data) { render(data); })
      .fail(function()     { showToast('Failed to load attendance data.', 'danger'); })
      .always(function()   {
        loader.classList.remove('show');
        \$btn.prop('disabled', false).html('<i class="bi bi-search"></i> Load');
      });
  }

  window.__attReload = load;
  $(function() {
    $('#attForm').on('submit', function(e) { e.preventDefault(); load(); });
    $('#attSearch').on('input', applyAttSearch);
    if (AUTOLOAD) load();
  });
})();
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php'; ?>
