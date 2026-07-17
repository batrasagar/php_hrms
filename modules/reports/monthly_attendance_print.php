<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requirePermission('report_monthly.view');

$db   = getDb();
$user = currentUser();

$fCompany    = $user['role'] === 'user' ? $user['company_id'] : (int)($_GET['company'] ?? 0);
$fMonth      = trim($_GET['month']       ?? date('Y-m'));
$fDept       = trim($_GET['dept']        ?? '');
$fContractor = trim($_GET['contractor']  ?? '');

if (!$fCompany) {
    echo '<!DOCTYPE html><html><body style="font-family:Arial;padding:20px">No company selected.</body></html>';
    exit;
}
if (in_array($user['role'], ['admin','operator'], true)) {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$fCompany, $user['scope_id']]);
    if (!$chk->fetch()) {
        echo '<!DOCTYPE html><html><body style="font-family:Arial;padding:20px">Access denied.</body></html>';
        exit;
    }
}

[$year, $mon] = array_pad(explode('-', $fMonth), 2, '01');
$year = (int)$year; $mon = (int)$mon;
$daysInMonth = (int)date('t', mktime(0, 0, 0, $mon, 1, $year));
$fFrom = sprintf('%04d-%02d-01', $year, $mon);
$fTo   = sprintf('%04d-%02d-%02d', $year, $mon, $daysInMonth);

$dataUrl   = BASE_URL . '/ajax/attendance_data.php';
$backUrl   = 'monthly_attendance.php?' . http_build_query(array_filter(['company'=>$fCompany,'month'=>$fMonth,'dept'=>$fDept,'contractor'=>$fContractor]));
$queryStr  = http_build_query(['company'=>$fCompany,'from'=>$fFrom,'to'=>$fTo,'dept'=>$fDept,'contractor'=>$fContractor]);
$printedAt = date('d-m-Y H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Monthly Attendance — <?= htmlspecialchars($fMonth) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; font-size: 10px; color: #000; background: #fff; }

/* ── Loader ── */
#pg-loader {
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: #fff; z-index: 9999;
  display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 14px;
}
.spinner {
  width: 48px; height: 48px;
  border: 5px solid #ddd; border-top-color: #333;
  border-radius: 50%; animation: spin 0.8s linear infinite;
}
#pg-loader p { font-size: 13px; color: #555; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Screen toolbar ── */
.toolbar {
  padding: 10px 16px;
  background: #f5f5f7;
  border-bottom: 1px solid #ccc;
  display: flex;
  align-items: center;
  gap: 10px;
}
.toolbar button, .toolbar a {
  padding: 6px 14px;
  border-radius: 6px;
  border: 1px solid #ccc;
  cursor: pointer;
  font-size: 13px;
  text-decoration: none;
  color: #333;
  background: #fff;
}
.toolbar button.primary { background: #0071e3; color: #fff; border-color: #0071e3; }
.toolbar span { font-size: 13px; color: #555; flex: 1; }

/* ── Print area ── */
.print-area { padding: 10mm; }

.report-title { text-align: center; margin-bottom: 8px; }
.report-title h2 { font-size: 14px; font-weight: 700; margin-bottom: 2px; }
.report-title p  { font-size: 10px; color: #555; }

table { width: 100%; border-collapse: collapse; table-layout: fixed; }
th, td { border: 1.5px solid #333; padding: 2px 2px; text-align: center; font-size: 9px; vertical-align: middle; overflow: hidden; }
th { background: #e8e8e8; font-weight: 700; }
.col-name { text-align: left; width: 120px; min-width: 120px; }
.col-day  { width: 18px; min-width: 18px; }
.col-sum  { width: 22px; min-width: 22px; }

.dow-row th { font-size: 7px; background: #f0f0f0; }
.sun-col { background: #f5f5f5; }

.att-badge {
  display: inline-block;
  width: 16px; height: 14px;
  border-radius: 2px;
  font-size: 7.5px;
  font-weight: 700;
  line-height: 14px;
  text-align: center;
}
.ab-p  { background: #d4edda; color: #155724; }
.ab-hp { background: #cce5ff; color: #004085; }
.ab-a  { background: #ffcdd2; color: #7f0000; }
.ab-l  { background: #ff9800; color: #fff; }
.ab-co { background: #0dcaf0; color: #053d47; }
.ab-wo { background: #e2e3e5; color: #555; }
.ab-hl { background: #fff3cd; color: #856404; }
.ab-h  { background: #f0f0f0; color: #6c757d; }
.ab-s  { background: #eeeeee; color: #9e9e9e; }

.sum-p  { color: #155724; font-weight: 700; }
.sum-hp { color: #004085; font-weight: 700; }
.sum-a  { color: #7f0000; font-weight: 700; }
.sum-l  { color: #7b1a00; font-weight: 700; }
.sum-co { color: #087990; font-weight: 700; }
.sum-hs { color: #555; }

.legend { margin-top: 6px; font-size: 8px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.legend-item { display: flex; align-items: center; gap: 3px; }
.legend-box { display: inline-block; width: 12px; height: 10px; border-radius: 2px; border: 1px solid #ccc; }

@media print {
  .toolbar { display: none !important; }
  #pg-loader { display: none !important; }
  .print-area { padding: 0; }
  @page { size: A4 landscape; margin: 8mm 10mm; }
  body { font-size: 9px; }
  th, td { border-color: #000 !important; }
}
</style>
</head>
<body>
<div id="pg-loader">
  <div class="spinner"></div>
  <p>Loading attendance data&hellip;</p>
</div>
<div id="pg-content" style="display:none"></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
(function () {
  var DATA_URL      = '<?= $dataUrl ?>';
  var QUERY         = <?= json_encode($queryStr, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  var BACK_URL      = '<?= htmlspecialchars($backUrl, ENT_QUOTES) ?>';
  var PRINTED_AT    = '<?= $printedAt ?>';
  var DAYS_IN_MONTH = <?= $daysInMonth ?>;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

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
    var cols  = DAYS_IN_MONTH + 7;

    var monthLabel = '';
    try {
      var parts = data.fFrom.slice(0, 7).split('-');
      monthLabel = new Date(+parts[0], +parts[1] - 1, 1)
        .toLocaleString('default', { month: 'long', year: 'numeric' });
    } catch (e) { monthLabel = data.fFrom.slice(0, 7); }

    var html = '';

    // toolbar
    html += '<div class="toolbar">'
          + '<button class="primary" onclick="window.print()">&#128438; Print</button>'
          + '<a href="' + esc(BACK_URL) + '">&#8592; Back</a>'
          + '<span>' + esc(data.companyName || '') + ' &mdash; ' + monthLabel
          + (data.fDept       ? ' &mdash; ' + esc(data.fDept)       : '')
          + (data.fContractor ? ' &mdash; ' + esc(data.fContractor) : '')
          + '</span></div>';

    html += '<div class="print-area">';

    // title
    html += '<div class="report-title">'
          + '<h2>Monthly Attendance Register</h2>'
          + '<p>' + esc(data.companyName || '') + ' &nbsp;|&nbsp; ' + monthLabel;
    if (data.fDept)       html += ' &nbsp;|&nbsp; ' + esc(data.fDept);
    if (data.fContractor) html += ' &nbsp;|&nbsp; ' + esc(data.fContractor);
    html += ' &nbsp;|&nbsp; Printed: ' + PRINTED_AT + '</p></div>';

    if (!emps || !emps.length) {
      html += '<p style="text-align:center;padding:20px;color:#666">No active employees found.</p>'
            + '</div>';
      document.getElementById('pg-content').innerHTML = html;
      show(); return;
    }

    // table
    html += '<table><thead><tr>'
          + '<th class="col-name">Employee</th>';
    dates.forEach(function (d) {
      html += '<th class="col-day' + (d.isSun ? ' sun-col' : '') + '">' + parseInt(d.dayNum, 10) + '</th>';
    });
    html += '<th class="col-sum" title="Present">P</th>'
          + '<th class="col-sum" title="Half Day">HP</th>'
          + '<th class="col-sum" title="Absent">A</th>'
          + '<th class="col-sum" title="Leave">L</th>'
          + '<th class="col-sum" title="Comp Off">CO</th>'
          + '<th class="col-sum" title="Holiday+Sunday">H+S</th>'
          + '<th class="col-sum" title="Total Pay Days = P + half-HP + L + CO + Holidays/Sundays">Pay</th>'
          + '</tr><tr class="dow-row"><th class="col-name"></th>';
    dates.forEach(function (d) {
      html += '<th class="col-day' + (d.isSun ? ' sun-col' : '') + '">' + esc(d.dayName) + '</th>';
    });
    html += '<th colspan="7"></th></tr></thead><tbody>';

    var prevDept = null;
    emps.forEach(function (emp) {
      // department separator
      if (!data.fDept && emp.department !== prevDept) {
        prevDept = emp.department;
        html += '<tr><td colspan="' + cols + '" style="background:#ddd;font-weight:700;font-size:9px;text-align:left;padding:2px 4px;">'
              + esc(emp.department || 'No Department') + '</td></tr>';
      }

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
        dayCells += '<td class="col-day' + (d.isSun ? ' sun-col' : '') + '">' + badgeHtml(c.type) + '</td>';
      });
      var payDays = cntP + cntHP * 0.5 + cntL + cntCO + cntHS;

      html += '<tr>'
            + '<td class="col-name" style="text-align:left;padding:1px 3px;">'
            + '<strong>' + esc(emp.code || '') + '</strong>'
            + (emp.code ? ' ' : '') + esc(emp.name)
            + '</td>'
            + dayCells
            + '<td class="col-sum sum-p">'  + (cntP  || '') + '</td>'
            + '<td class="col-sum sum-hp">' + (cntHP || '') + '</td>'
            + '<td class="col-sum sum-a">'  + (cntA  || '') + '</td>'
            + '<td class="col-sum sum-l">'  + (cntL > 0 ? (Number.isInteger(cntL) ? cntL : cntL.toFixed(1)) : '') + '</td>'
            + '<td class="col-sum sum-co">' + (cntCO || '') + '</td>'
            + '<td class="col-sum sum-hs">' + (cntHS || '') + '</td>'
            + '<td class="col-sum" style="font-weight:700;background:#eef6ff">' + (payDays ? (Number.isInteger(payDays) ? payDays : payDays.toFixed(1)) : '') + '</td></tr>';
    });

    html += '</tbody></table>';

    // legend
    html += '<div class="legend"><strong>Legend:</strong>'
          + '<span class="legend-item"><span class="legend-box ab-p"></span> P = Present</span>'
          + '<span class="legend-item"><span class="legend-box ab-hp"></span> HP = Half Day</span>'
          + '<span class="legend-item"><span class="legend-box ab-a"></span> A = Absent</span>'
          + '<span class="legend-item"><span class="legend-box ab-l"></span> L = Leave</span>'
          + '<span class="legend-item"><span class="legend-box ab-co"></span> CO = Comp Off</span>'
          + '<span class="legend-item"><span class="legend-box ab-hl"></span> HL = Half Leave</span>'
          + '<span class="legend-item"><span class="legend-box ab-h"></span> H = Holiday</span>'
          + '<span class="legend-item"><span class="legend-box ab-s"></span> S = Sunday</span>'
          + '</div>';

    html += '</div>';

    document.getElementById('pg-content').innerHTML = html;
    show();
  }

  function show() {
    document.getElementById('pg-loader').style.display  = 'none';
    document.getElementById('pg-content').style.display = 'block';
  }

  $.getJSON(DATA_URL + '?' + QUERY)
    .done(function (data) {
      if (!data.success) {
        document.getElementById('pg-loader').innerHTML =
          '<p style="color:red;font-family:Arial;padding:20px">Error: ' +
          (data.errors && data.errors[0] ? data.errors[0] : 'Unknown error') + '</p>';
        return;
      }
      render(data);
    })
    .fail(function () {
      document.getElementById('pg-loader').innerHTML =
        '<p style="color:red;font-family:Arial;padding:20px">Failed to load attendance data. Please close and try again.</p>';
    });
})();
</script>
</body>
</html>
