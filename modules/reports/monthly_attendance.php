<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requirePermission('report_monthly.view');
$pageTitle  = 'Monthly Attendance';
$activePage = 'report_monthly';
require_once __DIR__ . '/../../includes/header.php';

$db   = getDb();
$user = currentUser();

// Company comes from the global topbar switcher
$fCompany    = activeCompanyId($db, $user);
$fMonth      = trim($_GET['month']       ?? date('Y-m'));
$fDept       = trim($_GET['dept']        ?? '');
$fContractor = trim($_GET['contractor']  ?? '');

$scopeJoin   = $user['role'] === 'superadmin' ? '' : 'JOIN tblCompany c ON c.id=e.CompanyId AND c.AdminId=' . $user['scope_id'];
$scopeJoin  .= complianceEmpFilter('e');
$depts       = employeeFilterValues($db, (int)$fCompany, 'Department');
$contractors = employeeFilterValues($db, (int)$fCompany, 'Contractor');

$dataUrl  = BASE_URL . '/ajax/attendance_data.php';
$autoload = $fCompany ? 1 : 0;
?>
<style>
@media print {
  .no-print { display:none!important; }
  body { font-size: 9px !important; }
  .card { border:none!important; box-shadow:none!important; }
  @page { size: A4 landscape; margin: 6mm 8mm; }
}
#tblMonthly th, #tblMonthly td { padding: 2px 3px !important; font-size: 11px; }
#tblMonthly td.dc { width: 22px; min-width:22px; text-align:center; font-size:10px; }
.s-p  { color:#155724; font-weight:600; }
.s-hp { color:#004085; font-weight:600; }
.s-a  { color:#721c24; font-weight:600; }
.s-l  { color:#7b1a00; font-weight:600; }
.s-co { color:#087990; font-weight:600; }
.s-hl { color:#856404; font-weight:600; }
.s-h  { color:#6c757d; }
.s-s  { color:#adb5bd; }
#tblMonthly td.dc { vertical-align: middle; }
.att-badge {
    display: inline-block;
    min-width: 20px;
    padding: 1px 3px;
    border-radius: 3px;
    font-size: 9px;
    font-weight: 700;
    line-height: 1.4;
    text-align: center;
}
.ab-p  { background:#d4edda; color:#155724; }
.ab-hp { background:#cce5ff; color:#004085; }
.ab-a  { background:#ffcdd2; color:#7f0000; }
.ab-l  { background:#ff9800; color:#fff; }
.ab-co { background:#0dcaf0; color:#053d47; }
.ab-wo { background:#e2e3e5; color:#555; }
.ab-hl { background:#fff3cd; color:#856404; }
.ab-h  { background:#f0f0f0; color:#6c757d; }
.ab-s  { background:#eeeeee; color:#9e9e9e; }
</style>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-3 no-print">
  <div class="card-body py-2">
    <form id="mAttForm" method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="company" value="<?= (int)$fCompany ?>">
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
        <button id="btnMALoad" type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search"></i> Load</button>
        <a id="btnMAPrint" href="monthly_attendance_print.php?<?= htmlspecialchars(http_build_query(array_filter(['company'=>$fCompany,'month'=>$fMonth,'dept'=>$fDept,'contractor'=>$fContractor]))) ?>"
           target="_blank" class="btn btn-outline-success btn-sm <?= $fCompany ? '' : 'd-none' ?>"><i class="bi bi-printer"></i> Print</a>
      </div>
    </form>
  </div>
</div>

<div id="filter-results">
  <div class="alert alert-info mb-0">Select a company and month to load attendance.</div>
</div>

<?php
$extraJs = <<<JS
<style>
#att-loader { display:none; position:fixed; inset:0; background:rgba(255,255,255,.88); z-index:9999; align-items:center; justify-content:center; flex-direction:column; gap:14px; }
#att-loader.show { display:flex; }
#att-loader .sp { width:48px; height:48px; border:5px solid #dee2e6; border-top-color:#0d6efd; border-radius:50%; animation:mSpin .8s linear infinite; }
@keyframes mSpin { to { transform:rotate(360deg); } }
</style>
<div id="att-loader"><div class="sp"></div><p style="font-size:14px;color:#555">Loading attendance data…</p></div>
<script>
(function () {
  var DATA_URL = '$dataUrl';
  var AUTOLOAD = $autoload;

  var DAY_NAMES = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

  function badgeHtml(type) {
    if (type === 'SUN') return '<span class="att-badge ab-s">S</span>';
    if (type === 'HOL') return '<span class="att-badge ab-h">H</span>';
    if (type === 'L')   return '<span class="att-badge ab-l">L</span>';
    if (type === 'CO')  return '<span class="att-badge ab-co">CO</span>';
    if (type === 'WO')  return '<span class="att-badge ab-wo">WO</span>';
    if (type === 'HL')  return '<span class="att-badge ab-hl">HL</span>';
    if (type === 'P')   return '<span class="att-badge ab-p">P</span>';
    if (type === 'HP')  return '<span class="att-badge ab-hp">HP</span>';
    if (type === 'A')   return '<span class="att-badge ab-a">A</span>';
    return '';
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

    var monthLabel = dates.length ? dates[0].date.slice(0,7) : '';
    try {
      var parts = monthLabel.split('-');
      monthLabel = new Date(+parts[0], +parts[1]-1, 1).toLocaleString('default', {month:'long', year:'numeric'});
    } catch(e) {}

    var html = '<div class="card border-0 shadow-sm">'
             + '<div class="card-header bg-white d-flex justify-content-between align-items-center">'
             + '<span class="fw-semibold">Monthly Attendance &mdash; ' + monthLabel
             + ' <small class="text-muted ms-2">' + emps.length + ' employees</small></span>'
             + '<span class="small text-muted no-print">'
             + '<span class="s-p me-2">P</span><span class="s-hp me-2">HP</span>'
             + '<span class="s-a me-2">A</span><span class="s-l me-2">L/HL</span>'
             + '<span class="s-h me-2">H</span><span class="s-s me-2">S</span>'
             + '</span></div>'
             + '<div class="card-body p-0" style="overflow-x:auto">'
             + '<table class="table table-sm table-bordered mb-0" id="tblMonthly" style="min-width:' + (220 + dates.length * 22) + 'px">'
             + '<thead class="table-light"><tr>'
             + '<th style="min-width:180px">Employee</th>';

    dates.forEach(function (d) {
      html += '<th class="text-center dc"' + (d.isSun ? ' style="background:#f0f0f0"' : '') + '>'
            + parseInt(d.dayNum, 10) + '</th>';
    });
    html += '<th class="text-center" style="min-width:30px" title="Present">P</th>'
          + '<th class="text-center" style="min-width:30px" title="Half Day">HP</th>'
          + '<th class="text-center" style="min-width:30px" title="Absent">A</th>'
          + '<th class="text-center" style="min-width:30px" title="Leave">L</th>'
          + '<th class="text-center" style="min-width:30px" title="Comp Off">CO</th>'
          + '<th class="text-center" style="min-width:30px" title="Holiday / Sunday">H+S</th>'
          + '<th class="text-center" style="min-width:44px" title="Total Pay Days = P + ½·HP + L + CO + Holidays/Sundays">Pay Days</th>'
          + '</tr><tr class="table-light"><th></th>';

    dates.forEach(function (d) {
      var dow = new Date(d.date).getDay(); // 0=Sun..6=Sat
      var dayIdx = dow === 0 ? 6 : dow - 1;
      html += '<th class="text-center dc" style="font-size:9px;' + (d.isSun ? 'background:#f0f0f0' : '') + '">'
            + DAY_NAMES[dayIdx] + '</th>';
    });
    html += '<th colspan="7"></th></tr></thead><tbody>';

    // Running totals across every employee, for the KPI tiles above the grid.
    var totP = 0, totHP = 0, totA = 0, totL = 0, totCO = 0, totHS = 0, totPay = 0;

    emps.forEach(function (emp) {
      var cntP = 0, cntHP = 0, cntA = 0, cntL = 0, cntCO = 0, cntHS = 0;
      var dayCells = '';
      dates.forEach(function (d) {
        var c = emp.days[d.date] || { type: '' };
        if      (c.type === 'SUN') cntHS++;
        else if (c.type === 'HOL') cntHS++;
        else if (c.type === 'L')   cntL++;
        else if (c.type === 'HL')  cntL += 0.5;
        else if (c.type === 'CO')  cntCO++;
        else if (c.type === 'P')   cntP++;
        else if (c.type === 'HP')  cntHP++;
        else if (c.type === 'A')   cntA++;
        dayCells += '<td class="dc text-center">' + badgeHtml(c.type) + '</td>';
      });
      var payDays = cntP + cntHP * 0.5 + cntL + cntCO + cntHS;
      totP += cntP; totHP += cntHP; totA += cntA; totL += cntL;
      totCO += cntCO; totHS += cntHS; totPay += payDays;
      html += '<tr><td style="font-size:11px"><strong>' + (emp.code || '&mdash;') + '</strong> ' + emp.name;
      if (emp.department) html += '<br><span style="font-size:9px;color:#888">' + emp.department + '</span>';
      html += '</td>' + dayCells
            + '<td class="text-center fw-semibold s-p">'  + (cntP  || '') + '</td>'
            + '<td class="text-center fw-semibold s-hp">' + (cntHP || '') + '</td>'
            + '<td class="text-center fw-semibold s-a">'  + (cntA  || '') + '</td>'
            + '<td class="text-center fw-semibold s-l">'  + (cntL  ? (Number.isInteger(cntL) ? cntL : cntL.toFixed(1)) : '') + '</td>'
            + '<td class="text-center fw-semibold s-co">' + (cntCO || '') + '</td>'
            + '<td class="text-center s-h">'              + (cntHS || '') + '</td>'
            + '<td class="text-center fw-bold" style="background:#eef6ff">' + (payDays ? (Number.isInteger(payDays) ? payDays : payDays.toFixed(1)) : '') + '</td></tr>';
    });

    html += '</tbody></table></div></div>';

    // KPI tiles, prepended so the headline numbers sit above the wide grid.
    var hpNote = totHP ? ' <small class="fs-6 text-primary">+' + totHP + 'HP</small>' : '';
    var tiles = '<div class="row g-2 mb-2">'
              + kpiTile(emps.length, 'text-secondary', 'Employees')
              + kpiTile(totP + hpNote, 'text-success', 'Present')
              + kpiTile(totA, 'text-danger', 'Absent')
              + kpiTile(num(totL), 'text-warning', 'Leave')
              + kpiTile(totCO, 'text-info', 'Comp Off')
              + kpiTile(totHS, 'text-muted', 'Holiday + Sunday')
              + kpiTile(num(totPay), 'text-primary', 'Pay Days')
              + '</div>';

    \$('#filter-results').html(tiles + html);
  }

  /** Whole numbers stay whole; halves show one decimal. */
  function num(v) { return Number.isInteger(v) ? v : v.toFixed(1); }

  function kpiTile(val, cls, label) {
    return '<div class="col-6 col-sm-4 col-md-3 col-xl-2"><div class="card border-0 shadow-sm text-center py-2">'
         + '<div class="fs-4 fw-bold ' + cls + '">' + val + '</div>'
         + '<div class="small text-muted">' + label + '</div></div></div>';
  }

  function load() {
    var \$form = \$('#mAttForm');
    var month  = \$form.find('[name=month]').val()       || '';
    var co     = \$form.find('[name=company]').val()     || '';
    var dept   = \$form.find('[name=dept]').val()        || '';
    var ct     = \$form.find('[name=contractor]').val()  || '';

    if (!co) {
      \$('#filter-results').html('<div class="alert alert-info mb-0">Select a company and month to load attendance.</div>');
      return;
    }

    var parts        = (month || new Date().toISOString().slice(0,7)).split('-');
    var yr           = parseInt(parts[0], 10);
    var mn           = parseInt(parts[1], 10);
    var daysInMonth  = new Date(yr, mn, 0).getDate();
    var from         = parts[0] + '-' + parts[1] + '-01';
    var to           = parts[0] + '-' + parts[1] + '-' + String(daysInMonth).padStart(2,'0');

    var params = 'company=' + encodeURIComponent(co)
               + '&from='  + encodeURIComponent(from)
               + '&to='    + encodeURIComponent(to)
               + (dept ? '&dept='       + encodeURIComponent(dept) : '')
               + (ct   ? '&contractor=' + encodeURIComponent(ct)   : '');

    history.pushState(null, '', '?' + \$form.serialize());

    var \$btn = \$('#btnMALoad');
    \$btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status"></span>Loading…');
    \$('#att-loader').addClass('show');
    \$('#filter-results').css('opacity', 0.4);

    var printParams = 'company=' + encodeURIComponent(co) + '&month=' + encodeURIComponent(month || parts[0]+'-'+parts[1])
                    + (dept ? '&dept='       + encodeURIComponent(dept) : '')
                    + (ct   ? '&contractor=' + encodeURIComponent(ct)   : '');
    \$('#btnMAPrint').attr('href', 'monthly_attendance_print.php?' + printParams).removeClass('d-none');

    \$.getJSON(DATA_URL + '?' + params)
      .done(function (data) {
        if (!data.success) {
          showToast((data.errors && data.errors[0]) || 'Load failed.', 'danger');
        } else {
          if (data.notice) showToast(data.notice, 'warning');
          render(data);
        }
      })
      .fail(function () { showToast('Failed to load attendance data.', 'danger'); })
      .always(function () {
        \$('#att-loader').removeClass('show');
        \$('#filter-results').css('opacity', 1);
        \$btn.prop('disabled', false).html('<i class="bi bi-search"></i> Load');
      });
  }

  \$(function () {
    \$('#mAttForm').on('submit', function (e) { e.preventDefault(); load(); });
    if (AUTOLOAD) load();
  });
})();
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
