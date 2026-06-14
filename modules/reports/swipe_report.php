<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
$pageTitle  = 'Swipe Report';
$activePage = 'report_swipe';
require_once __DIR__ . '/../../includes/header.php';

$db   = getDb();
$user = currentUser();

if ($user['role'] === 'user') {
    $companiesDd = [];
    $fCompany    = $user['company_id'];
} elseif ($user['role'] === 'superadmin') {
    $companiesDd = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
    $fCompany    = (int)($_GET['company'] ?? ($companiesDd[0]['id'] ?? 0));
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['id']]);
    $companiesDd = $stmt->fetchAll();
    $fCompany    = (int)($_GET['company'] ?? ($companiesDd[0]['id'] ?? 0));
}
$fMonth      = trim($_GET['month']       ?? date('Y-m'));
$fDept       = trim($_GET['dept']        ?? '');
$fContractor = trim($_GET['contractor']  ?? '');

$scopeJoin   = $user['role'] === 'superadmin' ? '' : 'JOIN tblCompany c ON c.id=e.CompanyId AND c.AdminId=' . $user['id'];
$depts       = array_filter(array_column($db->query("SELECT DISTINCT Department FROM tblEmployee e $scopeJoin ORDER BY Department")->fetchAll(), 'Department'));
$contractors = array_filter(array_column($db->query("SELECT DISTINCT Contractor FROM tblEmployee e $scopeJoin WHERE Contractor IS NOT NULL ORDER BY Contractor")->fetchAll(), 'Contractor'));

$dataUrl  = BASE_URL . '/ajax/attendance_data.php';
$autoload = $fCompany ? 1 : 0;
?>
<style>
@media print {
  .no-print { display: none !important; }
  .card { border: none !important; box-shadow: none !important; }
  @page { size: A4 landscape; margin: 6mm 8mm; }
  body { font-size: 8px !important; }
}
#tblSwipe th, #tblSwipe td { padding: 1px 2px !important; font-size: 10px; vertical-align: middle; }
#tblSwipe td.sw-day { width: 44px; min-width: 44px; text-align: center; vertical-align: top; line-height: 1.2; }
#tblSwipe th.sw-day { width: 44px; min-width: 44px; }
.sw-in   { font-size: 9px; font-weight: 600; color: #1a5e20; }
.sw-out  { font-size: 9px; color: #555; }
.sw-tot  { font-size: 8px; color: #888; border-top: 1px dotted #ccc; margin-top: 1px; }
.sw-badge { display: inline-block; padding: 1px 4px; border-radius: 3px; font-size: 9px; font-weight: 700; }
.sw-p  { background: #d4edda; }
.sw-hp { background: #cce5ff; }
.sw-a  { background: #ffcdd2; color: #7f0000; }
.sw-l  { background: #ffcccc; color: #7b1a00; }
.sw-hl { background: #fff3cd; color: #856404; }
.sw-h  { background: #f0f0f0; color: #6c757d; }
.sw-s  { background: #e0e0e0; color: #9e9e9e; }
.sw-dept-row td { background: #e9ecef; font-weight: 700; font-size: 11px; padding: 4px 8px !important; }
.sw-sum { text-align: center; font-weight: 700; min-width: 26px; }
.sw-sum-p  { color: #155724; }
.sw-sum-hp { color: #004085; }
.sw-sum-a  { color: #7f0000; }
.sw-sum-l  { color: #7b1a00; }
.sw-sum-hl { color: #856404; }
</style>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-3 no-print">
  <div class="card-body py-2">
    <form id="swipeForm" method="GET" class="row g-2 align-items-end">
      <?php if ($user['role'] !== 'user'): ?>
      <div class="col-sm-4 col-md-3">
        <label class="form-label small mb-1">Company</label>
        <select name="company" class="form-select form-select-sm" onchange="$(this.form).trigger('submit')">
          <option value="">— Select —</option>
          <?php foreach ($companiesDd as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $fCompany == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['Name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?>
      <input type="hidden" name="company" value="<?= $fCompany ?>">
      <?php endif; ?>
      <div class="col-sm-2">
        <label class="form-label small mb-1">Month</label>
        <input type="month" name="month" class="form-control form-control-sm" value="<?= htmlspecialchars($fMonth) ?>" onchange="$(this.form).trigger('submit')">
      </div>
      <div class="col-sm-2">
        <label class="form-label small mb-1">Department</label>
        <select name="dept" class="form-select form-select-sm" onchange="$(this.form).trigger('submit')">
          <option value="">All</option>
          <?php foreach ($depts as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>" <?= $fDept === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label small mb-1">Contractor</label>
        <select name="contractor" class="form-select form-select-sm" onchange="$(this.form).trigger('submit')">
          <option value="">All</option>
          <?php foreach ($contractors as $ct): ?>
          <option value="<?= htmlspecialchars($ct) ?>" <?= $fContractor === $ct ? 'selected' : '' ?>><?= htmlspecialchars($ct) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto d-flex gap-1">
        <button id="btnSwipeLoad" type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Load</button>
        <a id="btnSwipePrint" href="swipe_report_print.php?<?= htmlspecialchars(http_build_query(array_filter(['company'=>$fCompany,'month'=>$fMonth,'dept'=>$fDept,'contractor'=>$fContractor]))) ?>"
           target="_blank" class="btn btn-outline-success btn-sm <?= $fCompany ? '' : 'd-none' ?>"><i class="bi bi-printer"></i> Print</a>
      </div>
    </form>
  </div>
</div>

<div id="filter-results">
  <div class="alert alert-info mb-0">Select a company and month to load the swipe report.</div>
</div>

<?php
$extraJs = <<<JS
<style>
#att-loader { display:none; position:fixed; inset:0; background:rgba(255,255,255,.88); z-index:9999; align-items:center; justify-content:center; flex-direction:column; gap:14px; }
#att-loader.show { display:flex; }
#att-loader .sp { width:48px; height:48px; border:5px solid #dee2e6; border-top-color:#0d6efd; border-radius:50%; animation:swSpin .8s linear infinite; }
@keyframes swSpin { to { transform:rotate(360deg); } }
</style>
<div id="att-loader"><div class="sp"></div><p style="font-size:14px;color:#555">Loading swipe data…</p></div>
<script>
(function () {
  var DATA_URL = '$dataUrl';
  var AUTOLOAD = $autoload;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function renderCell(c) {
    if (c.type === 'SUN') return ['sw-day sw-s', '<span class="sw-badge sw-s">S</span>'];
    if (c.type === 'HOL') return ['sw-day sw-h', '<span class="sw-badge sw-h" title="'+esc(c.holName||'')+'">H</span>'];
    if (c.type === 'L')   return ['sw-day sw-l', '<span class="sw-badge sw-l">L</span>'];
    if (c.type === 'HL')  return ['sw-day sw-hl','<span class="sw-badge sw-hl">HL</span><div style="font-size:8px">'+esc(c.lvSub||'')+'</div>'];
    if (c.type === 'A')   return ['sw-day sw-a', '<span class="sw-badge sw-a">A</span>'];
    if (c.type === 'P' || c.type === 'HP') {
      var cls  = 'sw-day ' + (c.type === 'HP' ? 'sw-hp' : 'sw-p');
      var html = '';
      if (c.punches && c.punches.length) {
        c.punches.forEach(function(t) { html += '<div class="sw-in">' + esc(t) + '</div>'; });
      } else {
        html = '<div class="sw-in">' + esc(c.in) + '</div>'
             + '<div class="sw-out">' + (c.out != null ? esc(c.out) : '&mdash;') + '</div>';
      }
      if (c.tot) html += '<div class="sw-tot">' + esc(c.tot) + (c.ot ? ' <b style="color:#b85c00">+'+esc(c.ot)+'</b>' : '') + '</div>';
      return [cls, html];
    }
    return ['sw-day', ''];
  }

  function render(data) {
    var dates = data.dates;
    var emps  = data.employees;

    if (data.errors && data.errors.length) {
      data.errors.forEach(function(e) { showToast(e, 'warning'); });
    }

    if (!emps || !emps.length) {
      \$('#filter-results').html('<div class="alert alert-info">No active employees found.</div>');
      return;
    }

    var monthLabel = '';
    try {
      var p = data.fFrom.slice(0,7).split('-');
      monthLabel = new Date(+p[0], +p[1]-1, 1).toLocaleString('default', {month:'long', year:'numeric'});
    } catch(e) { monthLabel = data.fFrom.slice(0,7); }

    var cols = dates.length + 6;

    var html = '<div class="card border-0 shadow-sm">'
             + '<div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 no-print">'
             + '<span class="fw-semibold">Department-wise Swipe Report &mdash; ' + monthLabel
             + (data.fDept ? ' &mdash; ' + esc(data.fDept) : '')
             + ' <small class="text-muted ms-1">'+emps.length+' employees</small></span>'
             + '<span class="small text-muted">'
             + '<span class="me-2" style="color:#1a5e20">&#9646; P</span>'
             + '<span class="me-2" style="color:#004085">&#9646; HP</span>'
             + '<span class="me-2" style="color:#7f0000">&#9646; A</span>'
             + '<span class="me-2" style="color:#7b1a00">&#9646; L</span>'
             + '<span class="me-2" style="color:#6c757d">&#9646; H</span>'
             + '</span></div>'
             + '<div class="card-body p-0" style="overflow-x:auto">'
             + '<table class="table table-sm table-bordered mb-0" id="tblSwipe" style="min-width:'+(160+dates.length*44+5*28)+'px">'
             + '<thead class="table-dark"><tr>'
             + '<th style="min-width:160px;text-align:left">Employee</th>';

    dates.forEach(function(d) {
      var bg = d.isSun ? 'background:#444' : (d.isHol ? 'background:#2e7d32' : '');
      html += '<th class="sw-day text-center" style="'+bg+'">' + parseInt(d.dayNum,10) + '</th>';
    });
    html += '<th class="sw-sum" title="Present">P</th>'
          + '<th class="sw-sum" title="Half Present">HP</th>'
          + '<th class="sw-sum" title="Absent">A</th>'
          + '<th class="sw-sum" title="Leave">L</th>'
          + '<th class="sw-sum" title="Half Leave">HL</th>'
          + '</tr>'
          + '<tr class="table-secondary"><th></th>';

    dates.forEach(function(d) {
      var bg = d.isSun ? 'background:#555;color:#ccc' : (d.isHol ? 'background:#388e3c;color:#fff' : '');
      html += '<th class="sw-day text-center" style="font-size:8px;'+bg+'">' + esc(d.dayName) + '</th>';
    });
    html += '<th colspan="5"></th></tr></thead><tbody>';

    var prevDept = null;
    emps.forEach(function(emp) {
      if (!data.fDept && emp.department !== prevDept) {
        prevDept = emp.department;
        html += '<tr class="sw-dept-row"><td colspan="'+cols+'">'
              + '<i class="bi bi-building me-1"></i>' + esc(emp.department || 'No Department')
              + '</td></tr>';
      }

      var cntP = 0, cntHP = 0, cntA = 0, cntL = 0, cntHL = 0;
      var dayCells = '';
      dates.forEach(function(d) {
        var c = emp.days[d.date] || {type:''};
        if      (c.type === 'P')   cntP++;
        else if (c.type === 'HP')  cntHP++;
        else if (c.type === 'A')   cntA++;
        else if (c.type === 'L')   cntL++;
        else if (c.type === 'HL')  cntHL++;
        var r = renderCell(c);
        dayCells += '<td class="'+r[0]+'">'+r[1]+'</td>';
      });

      html += '<tr>'
            + '<td style="font-size:10px;white-space:nowrap">'
            + '<strong>' + esc(emp.code||'') + '</strong> ' + esc(emp.name)
            + (emp.shiftNo ? '<span class="ms-1 text-muted" style="font-size:9px">S'+esc(emp.shiftNo)+'</span>' : '')
            + '</td>'
            + dayCells
            + '<td class="sw-sum sw-sum-p">'  + (cntP  || '') + '</td>'
            + '<td class="sw-sum sw-sum-hp">' + (cntHP || '') + '</td>'
            + '<td class="sw-sum sw-sum-a">'  + (cntA  || '') + '</td>'
            + '<td class="sw-sum sw-sum-l">'  + (cntL  || '') + '</td>'
            + '<td class="sw-sum sw-sum-hl">' + (cntHL || '') + '</td>'
            + '</tr>';
    });

    html += '</tbody></table></div></div>';
    \$('#filter-results').html(html);
  }

  function load() {
    var \$form = \$('#swipeForm');
    var co    = \$form.find('[name=company]').val()    || '';
    var month = \$form.find('[name=month]').val()      || '';
    var dept  = \$form.find('[name=dept]').val()       || '';
    var ct    = \$form.find('[name=contractor]').val() || '';

    if (!co) {
      \$('#filter-results').html('<div class="alert alert-info mb-0">Select a company and month to load the swipe report.</div>');
      return;
    }

    var parts       = (month || new Date().toISOString().slice(0,7)).split('-');
    var daysInMonth = new Date(+parts[0], +parts[1], 0).getDate();
    var from        = parts[0] + '-' + parts[1] + '-01';
    var to          = parts[0] + '-' + parts[1] + '-' + String(daysInMonth).padStart(2,'0');

    var params = 'company=' + encodeURIComponent(co)
               + '&from='  + encodeURIComponent(from)
               + '&to='    + encodeURIComponent(to)
               + (dept ? '&dept='       + encodeURIComponent(dept) : '')
               + (ct   ? '&contractor=' + encodeURIComponent(ct)   : '');

    history.pushState(null, '', '?' + \$form.serialize());

    var printParams = 'company=' + encodeURIComponent(co) + '&month=' + encodeURIComponent(month || parts[0]+'-'+parts[1])
                    + (dept ? '&dept='       + encodeURIComponent(dept) : '')
                    + (ct   ? '&contractor=' + encodeURIComponent(ct)   : '');
    \$('#btnSwipePrint').attr('href', 'swipe_report_print.php?' + printParams).removeClass('d-none');

    var \$btn = \$('#btnSwipeLoad');
    \$btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Loading…');
    \$('#att-loader').addClass('show');
    \$('#filter-results').css('opacity', 0.4);

    \$.getJSON(DATA_URL + '?' + params)
      .done(function(data) {
        if (!data.success) {
          showToast((data.errors && data.errors[0]) || 'Load failed.', 'danger');
        } else {
          if (data.notice) showToast(data.notice, 'warning');
          render(data);
        }
      })
      .fail(function() { showToast('Failed to load swipe data.', 'danger'); })
      .always(function() {
        \$('#att-loader').removeClass('show');
        \$('#filter-results').css('opacity', 1);
        \$btn.prop('disabled', false).html('<i class="bi bi-search"></i> Load');
      });
  }

  \$(function() {
    \$('#swipeForm').on('submit', function(e) { e.preventDefault(); load(); });
    if (AUTOLOAD) load();
  });
})();
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
