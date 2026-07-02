<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db   = getDb();
$user = currentUser();

$fCompany    = $user['role'] === 'user' ? $user['company_id'] : (int)($_GET['company'] ?? 0);
$fMonth      = trim($_GET['month']       ?? date('Y-m'));
$fDept       = trim($_GET['dept']        ?? '');
$fContractor = trim($_GET['contractor']  ?? '');
$autoPrint   = !empty($_GET['autoprint']);

if (!$fCompany) {
    echo '<!DOCTYPE html><html><body style="font-family:Arial;padding:20px">No company selected.</body></html>';
    exit;
}
if ($user['role'] === 'admin') {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$fCompany, $user['id']]);
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
$backUrl   = 'swipe_report.php?' . http_build_query(array_filter(['company'=>$fCompany,'month'=>$fMonth,'dept'=>$fDept,'contractor'=>$fContractor]));
$queryStr  = http_build_query(['company'=>$fCompany,'from'=>$fFrom,'to'=>$fTo,'dept'=>$fDept,'contractor'=>$fContractor]);
$printedAt = date('d-m-Y H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Swipe Report — <?= htmlspecialchars($fMonth) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Arial Narrow', Arial, sans-serif; font-size: 9px; color: #000; background: #fff;
  -webkit-print-color-adjust: exact; print-color-adjust: exact;
}

/* ── Loader ── */
#pg-loader {
  position: fixed; top: 0; left: 0; width: 100%; height: 100%;
  background: #fff; z-index: 9999;
  display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 14px;
}
.spinner { width: 48px; height: 48px; border: 5px solid #ddd; border-top-color: #333; border-radius: 50%; animation: spin 0.8s linear infinite; }
#pg-loader p { font-size: 13px; color: #555; font-family: Arial, sans-serif; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Toolbar ── */
.toolbar {
  padding: 8px 14px; background: #f5f5f7; border-bottom: 1px solid #ccc;
  display: flex; align-items: center; gap: 8px;
}
.toolbar button, .toolbar a {
  padding: 5px 12px; border-radius: 5px; border: 1px solid #ccc;
  cursor: pointer; font-size: 12px; text-decoration: none; color: #333; background: #fff;
}
.toolbar button.primary { background: #0071e3; color: #fff; border-color: #0071e3; }
.toolbar span { font-size: 12px; color: #555; flex: 1; }

/* ── Print area ── */
.print-area { padding: 8mm; }
.report-page + .report-page { page-break-before: always; break-before: page; margin-top: 26px; }
.report-title { text-align: center; margin-bottom: 6px; }
.report-title h2 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.report-title p  { font-size: 9px; color: #444; margin-top: 2px; }

/* ── Table ── */
/* table-layout:fixed with compact fixed column widths. The table width is
   set inline (in JS) to the exact sum of its columns, so ~15 days per page
   stay tight instead of stretching to fill the page. */
table { border-collapse: collapse; table-layout: fixed; }
th, td { border: 1px solid #555; text-align: center; padding: 1px 1px; line-height: 1.15; vertical-align: middle; overflow: hidden; }
th { background: #222; color: #fff; font-size: 7px; font-weight: 700; }
th.col-name { text-align: left; background: #222; width: 92px; }
td.col-name { text-align: left; font-size: 8px; white-space: normal; word-break: break-word; padding: 1px 3px; }

.col-day { width: 34px; }
td.col-day { font-size: 8px; vertical-align: top; padding: 1px; }

.sw-in  { font-size: 8px; font-weight: 600; color: #1a5e20; }
.sw-out { font-size: 8px; color: #333; }
.sw-tot { font-size: 7px; color: #666; border-top: 1px dotted #aaa; margin-top: 1px; }

td.bg-p  { background: #d4edda; }
td.bg-hp { background: #cce5ff; }
td.bg-a  { background: #ffcdd2; }
td.bg-l  { background: #ffcccc; }
td.bg-hl { background: #fff3cd; }
td.bg-h  { background: #f0f0f0; }
td.bg-s  { background: #e0e0e0; }

.sw-badge { display: inline-block; font-size: 8px; font-weight: 700; }
.sw-badge-a  { color: #7f0000; }
.sw-badge-l  { color: #7b1a00; }
.sw-badge-hl { color: #856404; }
.sw-badge-h  { color: #5a6268; }
.sw-badge-s  { color: #9e9e9e; }

.col-sum { width: 22px; }
td.col-sum { font-weight: 700; font-size: 9px; }
.sum-p  { color: #1b5e20; }
.sum-hp { color: #004085; }
.sum-a  { color: #7f0000; }
.sum-l  { color: #7b1a00; }
.sum-hl { color: #856404; }

.dept-row td { background: #ddd; font-weight: 700; font-size: 9px; text-align: left; padding: 2px 5px; }
.dow-row th  { font-size: 6.5px; background: #444; }

/* ── Legend ── */
.legend { margin-top: 6px; font-size: 7.5px; display: flex; gap: 10px; flex-wrap: wrap; }
.l-box { display: inline-block; width: 10px; height: 9px; border: 1px solid #666; vertical-align: middle; margin-right: 2px; }

@media print {
  .toolbar { display: none !important; }
  #pg-loader { display: none !important; }
  .print-area { padding: 0; }
  @page { size: A4 landscape; margin: 6mm; }
  body { font-size: 8px; }
  /* Repeat the header rows at the top of every printed page */
  thead { display: table-header-group; }
  /* Don't split a single employee row (or a department heading) across pages */
  tr { page-break-inside: avoid; }
  .dept-row td { page-break-after: avoid; }
  /* Force cell background colors (P/A/L/H shading) to print */
  th, td, .sw-badge, .l-box { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
</head>
<body>
<div id="pg-loader">
  <div class="spinner"></div>
  <p>Loading swipe data&hellip;</p>
</div>
<div id="pg-content" style="display:none"></div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
(function () {
  var DATA_URL      = '<?= $dataUrl ?>';
  // json_encode gives a proper JS string literal. Do NOT htmlspecialchars
  // here: inside <script> entities aren't decoded, so "&amp;" would be sent
  // literally and the server would lose the from/to params (only today shown).
  var QUERY         = <?= json_encode($queryStr, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  var BACK_URL      = <?= json_encode($backUrl, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  var PRINTED_AT    = '<?= $printedAt ?>';
  var DAYS_IN_MONTH = <?= $daysInMonth ?>;
  var AUTOPRINT     = <?= $autoPrint ? 'true' : 'false' ?>;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function renderCell(c) {
    if (c.type === 'SUN') return ['col-day bg-s', '<span class="sw-badge sw-badge-s">S</span>'];
    if (c.type === 'HOL') return ['col-day bg-h', '<span class="sw-badge sw-badge-h" title="'+esc(c.holName||'')+'">H</span>'];
    if (c.type === 'L')   return ['col-day bg-l', '<span class="sw-badge sw-badge-l">L</span>'];
    if (c.type === 'HL')  return ['col-day bg-hl','<span class="sw-badge sw-badge-hl">HL</span>'+(c.lvSub?'<div style="font-size:6.5px">'+esc(c.lvSub)+'</div>':'')];
    if (c.type === 'A')   return ['col-day bg-a', '<span class="sw-badge sw-badge-a">A</span>'];
    if (c.type === 'P' || c.type === 'HP') {
      var bg   = c.type === 'HP' ? 'col-day bg-hp' : 'col-day bg-p';
      var html = '';
      if (c.punches && c.punches.length) {
        c.punches.forEach(function(t) { html += '<div class="sw-in">' + esc(t) + '</div>'; });
      } else {
        html = '<div class="sw-in">' + esc(c.in) + '</div>'
             + '<div class="sw-out">' + (c.out != null ? esc(c.out) : '&mdash;') + '</div>';
      }
      if (c.tot) html += '<div class="sw-tot">' + esc(c.tot) + (c.ot ? '+<b style="color:#b85c00">'+esc(c.ot)+'</b>' : '') + '</div>';
      return [bg, html];
    }
    return ['col-day', ''];
  }

  function legendHtml() {
    return '<div class="legend">'
         + '<strong>Legend:</strong>'
         + '<span><span class="l-box" style="background:#d4edda"></span>P = Present (In/Out)</span>'
         + '<span><span class="l-box" style="background:#cce5ff"></span>HP = Half Present</span>'
         + '<span><span class="l-box" style="background:#ffcdd2"></span>A = Absent</span>'
         + '<span><span class="l-box" style="background:#ffcccc"></span>L = Leave</span>'
         + '<span><span class="l-box" style="background:#fff3cd"></span>HL = Half Leave</span>'
         + '<span><span class="l-box" style="background:#f0f0f0"></span>H = Holiday</span>'
         + '<span><span class="l-box" style="background:#e0e0e0"></span>S = Sunday</span>'
         + '</div>';
  }

  // Build one table for a slice of days. The P/HP/A/L/HL columns show the
  // FULL-month totals (emp.summary), so each half-page stays meaningful.
  function buildTable(data, dates) {
    var emps = data.employees;
    var cols = dates.length + 6;
    // Exact table width = name(92) + days*34 + 5 summary cols*22
    var tableW = 92 + dates.length * 34 + 5 * 22;

    var html = '<table style="width:' + tableW + 'px"><thead><tr><th class="col-name" style="text-align:left">Employee</th>';
    dates.forEach(function(d) {
      var bg = d.isSun ? 'background:#555' : (d.isHol ? 'background:#2e7d32' : '');
      html += '<th class="col-day" style="' + bg + '">' + parseInt(d.dayNum,10) + '</th>';
    });
    html += '<th class="col-sum" title="Present">P</th>'
          + '<th class="col-sum" title="Half Present">HP</th>'
          + '<th class="col-sum" title="Absent">A</th>'
          + '<th class="col-sum" title="Leave">L</th>'
          + '<th class="col-sum" title="Half Leave">HL</th>'
          + '</tr>'
          + '<tr class="dow-row"><th class="col-name"></th>';
    dates.forEach(function(d) {
      var bg = d.isSun ? 'background:#444' : (d.isHol ? 'background:#388e3c' : '');
      html += '<th class="col-day" style="' + bg + '">' + esc(d.dayName) + '</th>';
    });
    html += '<th colspan="5"></th></tr></thead><tbody>';

    var prevDept = null;
    emps.forEach(function(emp) {
      if (!data.fDept && emp.department !== prevDept) {
        prevDept = emp.department;
        html += '<tr class="dept-row"><td colspan="' + cols + '">'
              + esc(emp.department || 'No Department') + '</td></tr>';
      }

      var dayCells = '';
      dates.forEach(function(d) {
        var r = renderCell(emp.days[d.date] || {type:''});
        dayCells += '<td class="' + r[0] + '">' + r[1] + '</td>';
      });

      var s = emp.summary || {};
      html += '<tr>'
            + '<td class="col-name">'
            + '<strong>' + esc(emp.code||'') + '</strong>'
            + (emp.code ? ' ' : '') + esc(emp.name)
            + (emp.shiftNo ? ' <span style="color:#666;font-size:7px">S'+esc(emp.shiftNo)+'</span>' : '')
            + '</td>'
            + dayCells
            + '<td class="col-sum sum-p">'  + (s.P  || '') + '</td>'
            + '<td class="col-sum sum-hp">' + (s.HP || '') + '</td>'
            + '<td class="col-sum sum-a">'  + (s.A  || '') + '</td>'
            + '<td class="col-sum sum-l">'  + (s.L  || '') + '</td>'
            + '<td class="col-sum sum-hl">' + (s.HL || '') + '</td>'
            + '</tr>';
    });

    html += '</tbody></table>';
    return html;
  }

  // One printed page: repeated title (with day-range) + the sliced table.
  function pageBlock(data, dates, rangeLabel, monthLabel, withLegend) {
    var html = '<div class="report-page">';
    html += '<div class="report-title">'
          + '<h2>Department-wise Swipe Report</h2>'
          + '<p>' + esc(data.companyName||'') + ' &nbsp;|&nbsp; ' + monthLabel;
    if (data.fDept)       html += ' &nbsp;|&nbsp; ' + esc(data.fDept);
    if (data.fContractor) html += ' &nbsp;|&nbsp; ' + esc(data.fContractor);
    html += ' &nbsp;|&nbsp; ' + rangeLabel
          + ' &nbsp;|&nbsp; Printed: ' + PRINTED_AT + '</p></div>';
    html += buildTable(data, dates);
    if (withLegend) html += legendHtml();
    html += '</div>';
    return html;
  }

  function render(data) {
    var dates = data.dates;
    var emps  = data.employees;

    var monthLabel = '';
    try {
      var p = data.fFrom.slice(0,7).split('-');
      monthLabel = new Date(+p[0], +p[1]-1, 1).toLocaleString('default', {month:'long', year:'numeric'});
    } catch(e) { monthLabel = data.fFrom.slice(0,7); }

    var html = '';

    // toolbar
    html += '<div class="toolbar">'
          + '<button class="primary" onclick="window.print()">&#128438; Print</button>'
          + '<a href="' + esc(BACK_URL) + '">&#8592; Back</a>'
          + '<span>' + esc(data.companyName||'') + ' &mdash; ' + monthLabel
          + (data.fDept       ? ' &mdash; ' + esc(data.fDept)       : '')
          + (data.fContractor ? ' &mdash; ' + esc(data.fContractor) : '')
          + '</span></div>';

    html += '<div class="print-area">';

    if (!emps || !emps.length) {
      html += '<div class="report-title"><h2>Department-wise Swipe Report</h2>'
            + '<p>' + esc(data.companyName||'') + ' &nbsp;|&nbsp; ' + monthLabel + '</p></div>'
            + '<p style="text-align:center;padding:20px;color:#666">No active employees found.</p></div>';
      document.getElementById('pg-content').innerHTML = html;
      show(); return;
    }

    // Split the month into two printed pages: days 1–15 and 16–end.
    var firstHalf  = dates.filter(function(d){ return parseInt(d.dayNum,10) <= 15; });
    var secondHalf = dates.filter(function(d){ return parseInt(d.dayNum,10) >  15; });

    if (secondHalf.length) {
      var lastDay = parseInt(secondHalf[secondHalf.length-1].dayNum, 10);
      html += pageBlock(data, firstHalf,  'Days 1&ndash;15', monthLabel, false);
      html += pageBlock(data, secondHalf, 'Days 16&ndash;' + lastDay, monthLabel, true);
    } else {
      html += pageBlock(data, firstHalf, 'Days 1&ndash;' + (firstHalf.length ? parseInt(firstHalf[firstHalf.length-1].dayNum,10) : 15), monthLabel, true);
    }

    html += '</div>';

    document.getElementById('pg-content').innerHTML = html;
    show();
  }

  function show() {
    document.getElementById('pg-loader').style.display  = 'none';
    document.getElementById('pg-content').style.display = 'block';
    if (AUTOPRINT) {
      // Wait for two frames + a short delay so the full table is laid out
      // and painted before the print dialog snapshots the page.
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          setTimeout(function () { window.print(); }, 500);
        });
      });
    }
  }

  $.getJSON(DATA_URL + '?' + QUERY)
    .done(function(data) {
      if (!data.success) {
        document.getElementById('pg-loader').innerHTML =
          '<p style="color:red;font-family:Arial;padding:20px">Error: ' +
          (data.errors && data.errors[0] ? data.errors[0] : 'Unknown error') + '</p>';
        return;
      }
      render(data);
    })
    .fail(function() {
      document.getElementById('pg-loader').innerHTML =
        '<p style="color:red;font-family:Arial;padding:20px">Failed to load swipe data. Please close and try again.</p>';
    });
})();
</script>
</body>
</html>
