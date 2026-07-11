<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
$pageTitle  = 'Attendance Report';
$activePage = 'report_attendance';

$db   = getDb();
$user = currentUser();

// Filter dropdown data only — computation moved to ajax/attendance_data.php
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

$fFrom       = trim($_GET['from']       ?? date('Y-m-d'));
$fTo         = trim($_GET['to']         ?? date('Y-m-d'));
$fDept       = trim($_GET['dept']       ?? '');
$fContractor = trim($_GET['contractor'] ?? '');

$scopeJoin   = $user['role'] === 'superadmin' ? '' : 'JOIN tblCompany c ON c.id=e.CompanyId AND c.AdminId=' . $user['id'];
$depts       = array_filter(array_column($db->query("SELECT DISTINCT Department FROM tblEmployee e $scopeJoin ORDER BY Department")->fetchAll(), 'Department'));
$contractors = array_filter(array_column($db->query("SELECT DISTINCT Contractor FROM tblEmployee e $scopeJoin ORDER BY Contractor")->fetchAll(), 'Contractor'));

$dataUrl  = BASE_URL . '/ajax/attendance_data.php';
$autoload = (int)$fCompany;

require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form id="attForm" method="GET" class="row g-2 align-items-end">
      <?php if ($user['role'] !== 'user'): ?>
      <div class="col-12 col-sm-6 col-md-3"><label class="form-label small mb-1">Company <span class="text-danger">*</span></label>
        <select name="company" class="form-select form-select-sm" onchange="$(this.form).trigger('submit')">
          <option value="">— Select —</option>
          <?php foreach ($companiesDd as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?>
      <input type="hidden" name="company" value="<?= $fCompany ?>">
      <?php endif; ?>
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

<div id="filter-results">
  <div class="alert alert-info">Select a company and date range, then click <strong>Load</strong>.</div>
</div>

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
#tblAttendance td.att-cell { padding:2px 0 !important; vertical-align:middle; }
#tblAttendance tfoot td    { font-size:10px; padding:2px 3px !important; }
</style>
<div id="att-loader"><div class="sp"></div><p>Loading attendance data&hellip;</p></div>
<script>
(function () {
  var DATA_URL  = '$dataUrl';
  var AUTOLOAD  = $autoload;

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
      case 'A': return ['<span style="font-size:9px">A</span>', '#fff0f0', 'text-danger'];
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
          + '<th class="text-center" style="min-width:28px" title="Half Leave">HL</th>'
          + '</tr></thead>';

    // tbody
    html += '<tbody>';
    data.employees.forEach(function(emp){
      html += '<tr><td class="small"><code>'+esc(emp.code||'—')+'</code></td><td class="small">'+esc(emp.name)+'</td>';
      data.dates.forEach(function(d){
        var r = renderCell(emp.days[d.date]||{type:''});
        html += '<td class="text-center att-cell '+r[2]+'" style="background:'+r[1]+'">'+r[0]+'</td>';
      });
      var s = emp.summary;
      html += '<td class="text-center fw-semibold text-success">'+s.P+'</td>'
            + '<td class="text-center fw-semibold text-primary">'+(s.HP||'—')+'</td>'
            + '<td class="text-center fw-semibold text-danger">'+s.A+'</td>'
            + '<td class="text-center fw-semibold" style="color:#c0392b">'+(s.L||'—')+'</td>'
            + '<td class="text-center fw-semibold text-warning">'+(s.HL||'—')+'</td></tr>';
    });
    html += '</tbody>';

    // tfoot
    html += '<tfoot><tr class="table-dark"><td colspan="2" class="fw-semibold text-center">Daily Total</td>';
    data.dates.forEach(function(d){
      var dt = data.dayTotals[d.date]||{P:0,HP:0,A:0,L:0,HL:0};
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
        if (dt.HL) html += '<div style="color:#ffe082;font-size:9px;line-height:1.1">HL:'+dt.HL+'</div>';
      }
      html += '</td>';
    });
    var g = data.grand;
    html += '<td class="text-center text-success fw-bold">'+g.P+'</td>'
          + '<td class="text-center text-primary fw-bold">'+(g.HP||'—')+'</td>'
          + '<td class="text-center text-danger fw-bold">'+g.A+'</td>'
          + '<td class="text-center fw-bold" style="color:#ef9a9a">'+(g.L||'—')+'</td>'
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
  }

  function updatePrintBtns(show) {
    if (!show) { $('#btnPrint,#btnGrid').addClass('d-none'); return; }
    var qs = $('#attForm').serialize();
    $('#btnPrint').attr('href', 'attendance_print.php?'+qs).removeClass('d-none');
    $('#btnGrid').attr('href',  'attendance_print_simple.php?'+qs).removeClass('d-none');
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

  $(function() {
    $('#attForm').on('submit', function(e) { e.preventDefault(); load(); });
    if (AUTOLOAD) load();
  });
})();
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php'; ?>
