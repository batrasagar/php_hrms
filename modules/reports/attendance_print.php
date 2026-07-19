<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
requirePermission('report_attendance.view');

$db   = getDb();
$user = currentUser();

$fCompany    = $user['role'] === 'user' ? $user['company_id'] : (int)($_GET['company'] ?? 0);
$fFrom       = trim($_GET['from']       ?? date('Y-m-01'));
$fTo         = trim($_GET['to']         ?? date('Y-m-d'));
$fDept       = trim($_GET['dept']       ?? '');
$fContractor = trim($_GET['contractor'] ?? '');

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

$dataUrl    = BASE_URL . '/ajax/attendance_data.php';
$simpleUrl  = 'attendance_print_simple.php?' . http_build_query(['company'=>$fCompany,'from'=>$fFrom,'to'=>$fTo,'dept'=>$fDept,'contractor'=>$fContractor]);
$exportFile = 'attendance_' . $fFrom . '_' . $fTo . '.xls';
$queryStr   = http_build_query(['company'=>$fCompany,'from'=>$fFrom,'to'=>$fTo,'dept'=>$fDept,'contractor'=>$fContractor]);
$printedAt  = date('d-m-Y H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Attendance — <?= htmlspecialchars($fFrom) ?> to <?= htmlspecialchars($fTo) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Arial Narrow', Arial, sans-serif; font-size: 10px; color: #000; background: #fff; }

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
  #pg-loader p { font-size: 13px; color: #555; font-family: Arial, sans-serif; }
  @keyframes spin { to { transform: rotate(360deg); } }

  .no-print { margin: 10px; }
  .no-print button { padding: 6px 16px; margin-right: 8px; cursor: pointer; font-size: 13px; }

  .report-title { text-align: center; margin: 8px 0 4px; }
  .report-title h2 { font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
  .report-title p  { font-size: 10px; color: #333; margin-top: 2px; }

  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #000; text-align: center; padding: 2px 2px; line-height: 1.2; }
  th { background: #000; color: #fff; font-size: 8px; font-weight: bold; }
  th.col-name { background: #000; text-align: left; }
  td.col-name { text-align: left; font-size: 8px; white-space: nowrap; }

  th.dt { min-width: 36px; }
  td.dt { min-width: 36px; }

  td.c-h  { background: #f0f0f0; }
  td.c-s  { background: #e0e0e0; }
  td.dt, td.dt * { font-family: 'Arial Narrow', Arial, sans-serif !important; font-size: 10px !important; }
  td.c-p  { background: #d4edda; }
  td.c-hp { background: #cce5ff; }
  td.c-a  { background: #fff0f0; }
  td.c-l  { background: #ffcccc; }
  td.c-co { background: #cff4fc; }
  td.c-wo { background: #e2e3e5; }
  td.c-hl { background: #fff3cd; }
  td.c-cut { background: #f8d7da; color: #b02a37; }
  td.sum  { font-weight: bold; font-size: 10px; }
  td.sum-p  { color: #1b5e20; }
  td.sum-hp { color: #004085; }
  td.sum-a  { color: #b71c1c; }
  td.sum-l  { color: #e65100; }
  td.sum-co { color: #087990; }
  td.sum-hl { color: #856404; }
  td.sum-hs { color: #495057; }
  /* Compact summary block: four stacked pairs in one column. */
  td.smry   { font-size: 8px; line-height: 1.25; white-space: nowrap; text-align: left;
              padding: 2px 4px !important; font-weight: bold; }
  th.smry-h { min-width: 78px; }
  .summary-box { margin-top: 8px; border-collapse: collapse; width: auto; }
  .summary-box th { background: #222; color: #fff; font-size: 8px; padding: 3px 6px; text-align: center; }
  .summary-box td { border: 1px solid #999; font-size: 9px; padding: 3px 8px; text-align: center; font-weight: bold; }

  .legend { margin: 6px 0; font-size: 8px; }
  .legend span { margin-right: 10px; }
  .l-box { display:inline-block; width:10px; height:10px; border:1px solid #000; vertical-align:middle; margin-right:2px; }

  @media print {
    @page { size: A4 landscape; margin: 8mm 10px; }
    .no-print { display: none !important; }
    #pg-loader { display: none !important; }
    body { font-size: 10px; }
    th { font-size: 10px; }
    td.col-name { font-size: 10px; }
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
  var DATA_URL   = '<?= $dataUrl ?>';
  var QUERY      = <?= json_encode($queryStr, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  var SIMPLE_URL = '<?= htmlspecialchars($simpleUrl, ENT_QUOTES) ?>';
  var EXPORT     = '<?= htmlspecialchars($exportFile, ENT_QUOTES) ?>';
  var PRINTED_AT = '<?= $printedAt ?>';

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function renderCell(c) {
    // Week off withheld by the week-off pay rules — struck through, reason on hover.
    if (c.woCut) {
      var lbl = c.type === 'WO' ? 'WO' : 'S';
      return ['dt c-cut', '<span style="text-decoration:line-through" title="Week off not paid — ' + esc(c.woWhy || '') + '">'
                        + lbl + '</span><div style="font-size:7px">unpaid</div>'];
    }
    if (c.type === 'SUN') return ['dt c-s', '<span>S</span>'];
    if (c.type === 'HOL') return ['dt c-h', '<span title="' + esc(c.holName || '') + '">H</span>'];
    if (c.type === 'L')   return ['dt c-l', '<b>L</b>'];
    if (c.type === 'CO')  return ['dt c-co', '<b>CO</b>'];
    if (c.type === 'WO')  return ['dt c-wo', '<b>WO</b>'];
    if (c.type === 'HL')  return ['dt c-hl', '<b>HL</b><div>' + esc(c.lvSub || '') + '</div>'];
    if (c.type === 'A')   return ['dt c-a', '<span>A</span>'];
    if (c.type === 'P' || c.type === 'HP') {
      var cls     = c.type === 'HP' ? 'dt c-hp' : 'dt c-p';
      var sft     = c.shift ? '<span style="float:right;color:#555">' + esc(c.shift) + '</span>' : '';
      var outDisp = c.out != null ? esc(c.out) : '&mdash;';
      var totStr  = c.tot || '';
      var otStr   = c.ot  || '';
      var inner   = '<b>' + c.type + '</b>' + sft
                  + '<div style="clear:both">' + esc(c.in) + '</div>'
                  + '<div>' + outDisp + '</div>'
                  + (totStr ? '<div>' + totStr + (otStr ? '<br><b style="color:#b85c00">+' + otStr + '</b>' : '') + '</div>' : '');
      return [cls, inner];
    }
    return ['dt', ''];
  }

  // Minutes → "Hh Mm" (mirrors attendMinsToHm() in ajax/attendance_data.php)
  function otHm(m) {
    m = m || 0; if (m <= 0) return '';
    var h = Math.floor(m / 60), mn = m % 60;
    return (h ? h + 'h' : '') + (mn ? mn + 'm' : (h ? '' : '0m'));
  }
  // Payable days = full present + ½ half-present + paid holidays/week-offs (H+S)
  function daysTxt(p, hp, hs) { var d = (p || 0) + 0.5 * (hp || 0) + (hs || 0); return d ? (Number.isInteger(d) ? d : d.toFixed(1)) : '&mdash;'; }

  // One compact summary cell instead of nine narrow columns — nine columns ate the
  // width the date grid needs on A4 landscape. Four stacked pairs, plus OT when on.
  function smryCell(a, showOt, dark) {
    function n(v) { return (v === 0 || v === undefined || v === null) ? '0' : v; }
    var c = dark
      ? { p:'#a5d6a7', hp:'#90caf9', a:'#ef9a9a', l:'#ffcc80', co:'#4dd0e1', hl:'#ffe082', hs:'#ced4da', d:'#fff', ot:'#ffcc80' }
      : { p:'#1b5e20', hp:'#004085', a:'#b71c1c', l:'#e65100', co:'#087990', hl:'#856404', hs:'#495057', d:'#000', ot:'#b85c00' };
    var ot = showOt ? ' <span style="color:' + c.ot + '">OT:' + (otHm(a.otMins) || '0m') + '</span>' : '';
    return '<td class="smry">'
         + '<span style="color:' + c.p  + '">P:'   + n(a.P)  + '</span> | <span style="color:' + c.hp + '">HP:' + n(a.HP) + '</span><br>'
         + '<span style="color:' + c.a  + '">A:'   + n(a.A)  + '</span> | <span style="color:' + c.l  + '">L:'  + n(a.L)  + '</span><br>'
         + '<span style="color:' + c.co + '">CO:'  + n(a.CO) + '</span> | <span style="color:' + c.hl + '">HL:' + n(a.HL) + '</span><br>'
         + '<span style="color:' + c.hs + '">H+S:' + n(a.HS) + '</span> <span style="color:' + c.d + ';font-weight:bold">Days:'
         + daysTxt(a.P, a.HP, a.HS) + '</span>' + ot
         + '</td>';
  }

  function render(data) {
    var dates  = data.dates;
    var emps   = data.employees;
    var totals = data.dayTotals;
    var grand  = data.grand;
    var showOt = !!data.showOt;
    var html   = '';

    html += '<div class="no-print">'
          + '<button onclick="window.print()">&#128438; Print</button>'
          + '<button onclick="exportExcel()">&#128196; Export to Excel</button>'
          + '<a href="' + esc(SIMPLE_URL) + '" target="_blank"><button>&#9634; Status-Only View</button></a>'
          + '<button onclick="window.close()">Close</button>'
          + '</div>';

    html += '<div class="report-title">'
          + '<h2>Attendance Report &mdash; ' + esc(data.companyName || '') + '</h2>'
          + '<p>Period: ' + esc(data.fFrom) + ' to ' + esc(data.fTo);
    if (data.fDept)       html += ' &nbsp;|&nbsp; Dept: '       + esc(data.fDept);
    if (data.fContractor) html += ' &nbsp;|&nbsp; Contractor: ' + esc(data.fContractor);
    html += ' &nbsp;|&nbsp; Printed: ' + PRINTED_AT + '</p></div>';

    html += '<div class="legend">'
          + '<span><span class="l-box" style="background:#d4edda"></span>P Present</span>'
          + '<span><span class="l-box" style="background:#cce5ff"></span>HP Half-Present</span>'
          + '<span><span class="l-box" style="background:#fff0f0"></span>A Absent</span>'
          + '<span><span class="l-box" style="background:#ffcccc"></span>L Leave</span>'
          + '<span><span class="l-box" style="background:#cff4fc"></span>CO Comp Off</span>'
          + '<span><span class="l-box" style="background:#fff3cd"></span>HL Half-Leave</span>'
          + '<span><span class="l-box" style="background:#f0f0f0"></span>H Holiday</span>'
          + '<span><span class="l-box" style="background:#e0e0e0"></span>S Sunday</span>'
          + '<span><span class="l-box" style="background:#f8d7da"></span><s>S</s> Unpaid Week Off</span>'
          + '<span style="color:#b85c00">+Xm OT</span></div>';

    if (!emps || !emps.length) {
      html += '<p style="margin:20px;color:#c00">No employees found.</p>';
      document.getElementById('pg-content').innerHTML = html;
      show(); return;
    }

    html += '<table><thead><tr>';
    html += '<th class="col-name" style="min-width:120px;text-align:left">Employee</th>';
    dates.forEach(function (d) {
      var bg = d.isSun ? 'background:#555' : (d.isHol ? 'background:#2e7d32' : '');
      html += '<th class="dt" style="' + bg + '">'
            + esc(d.dayNum) + '<br>'
            + '<span style="font-weight:normal;font-size:7px">' + esc(d.dayLetter) + '</span></th>';
    });
    html += '<th class="smry-h" style="min-width:78px">Smry</th></tr></thead>';

    // tbody — grouped by department, with a subtotal row per department
    var colBefore = 1 + dates.length;                     // Employee name col + date columns
    var totalCols = colBefore + 1;                          // + the single Smry column
    var curDept = null, deptAgg = null;
    function flushDept() {
      if (!deptAgg) return;
      html += '<tr style="background:#e8e8e8;font-weight:bold"><td colspan="' + colBefore + '" style="text-align:right;font-size:8px">'
            + esc(curDept || '(No Department)') + ' — subtotal (' + deptAgg.n + ')</td>'
            + smryCell(deptAgg, showOt, false) + '</tr>';
    }

    html += '<tbody>';
    emps.forEach(function (emp) {
      var dep = emp.department || '';
      if (dep !== curDept) {
        flushDept();
        curDept = dep;
        deptAgg = { P:0, HP:0, A:0, L:0, CO:0, HL:0, HS:0, otMins:0, n:0 };
        html += '<tr style="background:#c8c8c8"><td colspan="' + totalCols + '" style="text-align:left;font-weight:bold;font-size:9px">'
              + esc(dep || '(No Department)') + '</td></tr>';
      }
      html += '<tr><td class="col-name" style="text-align:left">'
            + '<div style="font-weight:bold">' + esc(emp.code || '&mdash;') + '</div>'
            + '<div>' + esc(emp.name) + '</div>'
            + (emp.fatherName ? '<div style="color:#444">' + esc(emp.fatherName) + '</div>' : '')
            + (emp.contractor  ? '<div style="color:#444">' + esc(emp.contractor)  + '</div>' : '')
            + (emp.department  ? '<div style="color:#666;font-style:italic">' + esc(emp.department) + '</div>' : '')
            + (emp.shiftNo     ? '<div style="color:#444">Shift: ' + esc(emp.shiftNo) + '</div>' : '')
            + '</td>';
      dates.forEach(function (d) {
        var c = emp.days[d.date] || { type: '' };
        var r = renderCell(c);
        html += '<td class="' + r[0] + '">' + r[1] + '</td>';
      });
      var s = emp.summary;
      deptAgg.P += s.P || 0; deptAgg.HP += s.HP || 0; deptAgg.A += s.A || 0; deptAgg.L += s.L || 0;
      deptAgg.CO += s.CO || 0; deptAgg.HL += s.HL || 0; deptAgg.HS += s.HS || 0; deptAgg.otMins += s.otMins || 0; deptAgg.n++;
      html += smryCell(s, showOt, false) + '</tr>';
    });
    flushDept();
    html += '</tbody>';

    html += '<tfoot><tr style="background:#222;color:#fff">';
    html += '<td class="col-name" style="text-align:left;font-weight:bold;font-size:8px">Daily Total</td>';
    dates.forEach(function (d) {
      var dt = totals[d.date] || { P: 0, HP: 0, A: 0, L: 0, HL: 0, CO: 0 };
      var bg = d.isSun ? '#444' : (d.isHol ? '#2e7d32' : '#222');
      html += '<td style="background:' + bg + ';font-size:8px;line-height:1.2;padding:1px 2px">';
      if      (d.isSun) html += '<span style="color:#aaa">S</span>';
      else if (d.isHol) html += '<span style="color:#a5d6a7">H</span>';
      else if (d.isFut) html += '<span style="color:#777">&mdash;</span>';
      else {
        if (dt.P)  html += '<div style="color:#a5d6a7">P:'  + dt.P  + '</div>';
        if (dt.HP) html += '<div style="color:#90caf9">HP:' + dt.HP + '</div>';
        if (dt.A)  html += '<div style="color:#ef9a9a">A:'  + dt.A  + '</div>';
        if (dt.L)  html += '<div style="color:#ef9a9a">L:'  + dt.L  + '</div>';
        if (dt.CO) html += '<div style="color:#4dd0e1">CO:' + dt.CO + '</div>';
        if (dt.HL) html += '<div style="color:#ffe082">HL:' + dt.HL + '</div>';
      }
      html += '</td>';
    });
    // OT is inside the summary block now — a separate OT cell here would make the
    // footer one column wider than the header.
    html += smryCell({P:grand.P, HP:grand.HP, A:grand.A, L:grand.L, CO:grand.CO, HL:grand.HL,
                      HS:grand.HS, otMins:data.grandOtMins}, showOt, true)
          + '</tr></tfoot></table>';

    html += '<table class="summary-box" style="margin-top:8px"><thead><tr>'
          + '<th>Employees</th><th>Working Days</th><th>Present (P)</th>'
          + '<th>Half-Present (HP)</th><th>Absent (A)</th><th>Full Leave (L)</th>'
          + '<th>Comp Off (CO)</th><th>Half Leave (HL)</th>'
          + '<th>Hol + Week Off (H+S)</th>'
          + '<th>Total Days</th>' + (showOt ? '<th>Total OT</th>' : '')
          + '<th>Holidays</th><th>Attendance %</th>'
          + '</tr></thead><tbody><tr>'
          + '<td>' + data.totalEmps   + '</td>'
          + '<td>' + data.workingDays + '</td>'
          + '<td style="color:#1b5e20">' + grand.P  + '</td>'
          + '<td style="color:#004085">' + grand.HP + '</td>'
          + '<td style="color:#b71c1c">' + grand.A  + '</td>'
          + '<td style="color:#e65100">' + grand.L  + '</td>'
          + '<td style="color:#087990">' + grand.CO + '</td>'
          + '<td style="color:#856404">' + grand.HL + '</td>'
          + '<td style="color:#495057">' + (grand.HS || 0) + '</td>'
          + '<td>' + daysTxt(grand.P, grand.HP, grand.HS) + '</td>'
          + (showOt ? '<td style="color:#b85c00">' + (otHm(data.grandOtMins) || '&mdash;') + '</td>' : '')
          + '<td>' + data.holidayCount + '</td>'
          + '<td>' + data.pctP + '% P &nbsp; ' + data.pctA + '% A</td>'
          + '</tr></tbody></table>';

    document.getElementById('pg-content').innerHTML = html;
    show();
  }

  function show() {
    document.getElementById('pg-loader').style.display  = 'none';
    document.getElementById('pg-content').style.display = 'block';
  }

  function exportExcel() {
    var table = document.querySelector('#pg-content table');
    if (!table) return;
    var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">'
             + '<head><meta charset="UTF-8"></head><body>' + table.outerHTML + '</body></html>';
    var blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8' });
    var url  = URL.createObjectURL(blob);
    var a    = document.createElement('a');
    a.href = url; a.download = EXPORT;
    document.body.appendChild(a); a.click();
    document.body.removeChild(a); URL.revokeObjectURL(url);
  }

  window.exportExcel = exportExcel;

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
