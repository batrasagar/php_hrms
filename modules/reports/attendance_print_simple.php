<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db   = getDb();
$user = currentUser();

$fCompany    = $user['role'] === 'user' ? $user['company_id'] : (int)($_GET['company'] ?? 0);
$fFrom       = trim($_GET['from']        ?? date('Y-m-01'));
$fTo         = trim($_GET['to']          ?? date('Y-m-d'));
$fDept       = trim($_GET['dept']        ?? '');
$fContractor = trim($_GET['contractor']  ?? '');

if (!$fCompany) { echo 'No company selected.'; exit; }

if (in_array($user['role'], ['admin','operator'], true)) {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$fCompany, $user['scope_id']]);
    if (!$chk->fetch()) { echo 'Access denied.'; exit; }
}

$companyName = $db->prepare("SELECT Name FROM tblCompany WHERE id=?");
$companyName->execute([$fCompany]);
$companyName = $companyName->fetchColumn() ?: '';

// ── Flush loader to browser before heavy ADMS fetch ───────────────────────────
while (@ob_get_level()) @ob_end_clean();
@ob_implicit_flush(true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Attendance Status — <?= htmlspecialchars($companyName) ?> — <?= $fFrom ?> to <?= $fTo ?></title>
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
</style>
</head>
<body>
<div id="pg-loader">
  <div class="spinner"></div>
  <p>Loading attendance data&hellip;</p>
</div>
<?php
echo str_pad('', 4096);
flush();

$employees = $leaveDates = $leaveCodes = $holidayDates = $punchMap = [];

$where  = ['e.CompanyId = ?'];
$params = [$fCompany];
if ($fDept)       { $where[] = 'e.Department = ?'; $params[] = $fDept; }
if ($fContractor) { $where[] = 'e.Contractor = ?'; $params[] = $fContractor; }
$estmt = $db->prepare(
    "SELECT e.id, e.EmployeeCode, e.EnrollId, e.Name, e.FatherName, e.Contractor, e.Department, e.ShiftNo FROM tblEmployee e
     WHERE " . implode(' AND ', $where) . " AND e.Status='active' ORDER BY e.Department, ISNULL(e.Sr), e.Sr, e.Name"
);
$estmt->execute($params);
$employees = $estmt->fetchAll();

if (!empty($employees)) {
    $ids = implode(',', array_column($employees, 'id'));

    foreach ($db->query("SELECT EmployeeId, LeaveDate, LeaveType, LeaveCode FROM tblLeave WHERE EmployeeId IN ($ids) AND LeaveDate BETWEEN '$fFrom' AND '$fTo'")->fetchAll() as $lv) {
        $leaveDates[$lv['EmployeeId']][$lv['LeaveDate']] = $lv['LeaveType'];
        $leaveCodes[$lv['EmployeeId']][$lv['LeaveDate']] = $lv['LeaveCode'];
    }
    $hStmt = $db->prepare("SELECT HolidayDate, Name FROM tblHoliday WHERE CompanyId=? AND HolidayDate BETWEEN ? AND ?");
    $hStmt->execute([$fCompany, $fFrom, $fTo]);
    foreach ($hStmt->fetchAll() as $h) $holidayDates[$h['HolidayDate']] = $h['Name'];

    $enrollMap = [];
    $enrStmt = $db->prepare("SELECT de.DeviceSerial, de.EnrollId, de.EmpCode FROM tblDeviceEnrollment de WHERE de.CompanyId=?");
    $enrStmt->execute([$fCompany]);
    foreach ($enrStmt->fetchAll() as $row) $enrollMap[$row['DeviceSerial']][$row['EnrollId']] = $row['EmpCode'];

    $empEnrollFallback = [];
    foreach ($employees as $emp) {
        if ($emp['EnrollId'] !== '' && $emp['EnrollId'] !== null)
            $empEnrollFallback[(string)$emp['EnrollId']] = $emp['EmployeeCode'];
    }

    $cred = null;
    try { $cred = $db->query("SELECT * FROM tblAdmsCredentials WHERE IsActive=1 ORDER BY id ASC LIMIT 1")->fetch(); } catch (Exception $e) {}

    if ($cred) {
        $coNameStmt = $db->prepare("SELECT Name FROM tblCompany WHERE id=?");
        $coNameStmt->execute([$fCompany]);
        $coName = $coNameStmt->fetchColumn();
        $companyDevices = [];
        if ($coName) {
            $dStmt = $db->prepare("SELECT SerialNumber FROM tblDevices WHERE Company=?");
            $dStmt->execute([$coName]);
            $companyDevices = $dStmt->fetchAll(PDO::FETCH_COLUMN);
        }
        $devSerials = array_values(array_unique(array_merge(array_keys($enrollMap), $companyDevices)));
        $fromDt = $fFrom . ' 00:00:00'; $toDt = $fTo . ' 23:59:59';

        foreach ($devSerials as $serial) {
            $url = rtrim($cred['Endpoint'], '/') . '/api/punchlog.php?SerialNumber=' . urlencode($serial);
            $ch  = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['X-Api-Key: '.$cred['ApiKey']], CURLOPT_TIMEOUT=>20, CURLOPT_SSL_VERIFYPEER=>true]);
            $response = curl_exec($ch); $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
            if ($httpCode !== 200) continue;
            $data = json_decode($response, true);
            if (empty($data['success']) || empty($data['data'])) continue;
            foreach ($data['data'] as $punch) {
                $pdt = $punch['PunchDateTime'] ?? '';
                if ($pdt < $fromDt || $pdt > $toDt) continue;
                $eid = (string)($punch['EnrollId'] ?? '');
                $pSN = $punch['SerialNumber'] ?? $serial;
                $date = substr($pdt, 0, 10); $time = substr($pdt, 11, 5);
                $empCode = $enrollMap[$pSN][$eid] ?? $enrollMap[$serial][$eid] ?? $empEnrollFallback[$eid] ?? null;
                if (!$empCode) continue;
                if (!isset($punchMap[$empCode][$date])) $punchMap[$empCode][$date] = true;
            }
        }
    }
}

$dates = [];
$ts = strtotime($fFrom); $tsEnd = strtotime($fTo);
while ($ts <= $tsEnd) { $dates[] = date('Y-m-d', $ts); $ts = strtotime('+1 day', $ts); }
if (count($dates) > 31) $dates = array_slice($dates, 0, 31);
$today = date('Y-m-d');

$dayTotals = [];
foreach ($dates as $dt) $dayTotals[$dt] = ['P'=>0,'HP'=>0,'A'=>0,'L'=>0,'HL'=>0,'CO'=>0];
$grand = ['P'=>0,'HP'=>0,'A'=>0,'L'=>0,'HL'=>0,'CO'=>0];
?>
<style>
  .no-print { margin: 10px; }
  .no-print button { padding: 6px 16px; margin-right: 8px; cursor: pointer; font-size: 13px; }
  .report-title { text-align: center; margin: 8px 0 4px; }
  .report-title h2 { font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
  .report-title p  { font-size: 10px; color: #333; margin-top: 2px; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #000; text-align: center; padding: 2px 1px; line-height: 1.2; font-size: 10px; }
  th { background: #000; color: #fff; font-weight: bold; }
  th.col-emp { text-align: left; }
  td.col-emp { text-align: left; min-width: 120px; }
  th.dt { min-width: 22px; max-width: 26px; }
  td.dt { min-width: 22px; max-width: 26px; font-weight: bold; }
  td.c-p  { background: #c8e6c9; }
  td.c-hp { background: #cce5ff; }
  td.c-a  { background: #fff0f0; }
  td.c-l  { background: #ffcccc; }
  td.c-co { background: #cff4fc; }
  td.c-hl { background: #fff3cd; }
  td.c-h  { background: #f0f0f0; }
  td.c-s  { background: #e0e0e0; }
  td.sum  { font-weight: bold; }
  .legend { margin: 4px 0; font-size: 9px; }
  .legend span { margin-right: 10px; }
  .summary-box { margin-top: 8px; border-collapse: collapse; width: auto; }
  .summary-box th { background: #222; color: #fff; font-size: 8px; padding: 3px 6px; text-align: center; }
  .summary-box td { border: 1px solid #999; font-size: 9px; padding: 3px 8px; text-align: center; font-weight: bold; }
  @media print {
    @page { size: A4 landscape; margin: 8mm 10px; }
    .no-print { display: none !important; }
    #pg-loader { display: none !important; }
  }
</style>

<div id="pg-content" style="display:none">
<div class="no-print">
  <button onclick="window.print()">&#128438; Print</button>
  <button onclick="exportExcel()">&#128196; Export to Excel</button>
  <button onclick="window.close()">Close</button>
</div>
<script>
function exportExcel() {
    const table = document.querySelector('table');
    if (!table) return;
    const html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">'
               + '<head><meta charset="UTF-8"></head><body>' + table.outerHTML + '</body></html>';
    const blob = new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = 'attendance_status_<?= $fFrom ?>_<?= $fTo ?>.xls';
    document.body.appendChild(a); a.click();
    document.body.removeChild(a); URL.revokeObjectURL(url);
}
</script>

<div class="report-title">
  <h2>Attendance Status — <?= htmlspecialchars($companyName) ?></h2>
  <p>Period: <?= $fFrom ?> to <?= $fTo ?>
    <?= $fDept ? ' &nbsp;|&nbsp; Dept: '.htmlspecialchars($fDept) : '' ?>
    <?= $fContractor ? ' &nbsp;|&nbsp; Contractor: '.htmlspecialchars($fContractor) : '' ?>
    &nbsp;|&nbsp; Printed: <?= date('d-m-Y H:i') ?>
  </p>
</div>

<div class="legend">
  <b>P</b> Present &nbsp;
  <b>A</b> Absent &nbsp;
  <b>L</b> Leave &nbsp;
  <b>CO</b> Comp Off &nbsp;
  <b>HL</b> Half-Leave (AM/PM) &nbsp;
  <b>H</b> Holiday &nbsp;
  <b>S</b> Sunday
</div>

<?php if (!empty($employees)): ?>
<table>
  <thead>
    <tr>
      <th class="col-emp">Employee</th>
      <?php foreach ($dates as $dt):
        $dow   = (int)date('N', strtotime($dt));
        $isSun = ($dow === 7);
        $isHol = isset($holidayDates[$dt]);
        $bg    = $isSun ? 'background:#555' : ($isHol ? 'background:#2e7d32' : '');
      ?>
      <th class="dt" style="<?= $bg ?>">
        <?= date('d', strtotime($dt)) ?><br>
        <span style="font-weight:normal;font-size:8px"><?= date('D', strtotime($dt))[0] ?></span>
      </th>
      <?php endforeach; ?>
      <th style="min-width:18px">P</th>
      <th style="min-width:18px">HP</th>
      <th style="min-width:18px">A</th>
      <th style="min-width:18px">L</th>
      <th style="min-width:18px">CO</th>
      <th style="min-width:18px">HL</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($employees as $e):
    $presentDays = 0; $hpDays = 0; $absentDays = 0; $fullLv = 0; $halfLv = 0; $compOff = 0;
  ?>
  <tr>
    <td class="col-emp">
      <div style="font-weight:bold"><?= htmlspecialchars($e['EmployeeCode'] ?: '—') ?></div>
      <div><?= htmlspecialchars($e['Name']) ?></div>
      <?php if (!empty($e['FatherName'])): ?><div style="color:#444"><?= htmlspecialchars($e['FatherName']) ?></div><?php endif; ?>
      <?php if (!empty($e['Contractor'])): ?><div style="color:#444"><?= htmlspecialchars($e['Contractor']) ?></div><?php endif; ?>
      <?php if (!empty($e['Department'])): ?><div style="color:#666;font-style:italic"><?= htmlspecialchars($e['Department']) ?></div><?php endif; ?>
      <?php if (!empty($e['ShiftNo'])): ?><div style="color:#444">Shift: <?= htmlspecialchars($e['ShiftNo']) ?></div><?php endif; ?>
    </td>
    <?php foreach ($dates as $dt):
      $dow    = (int)date('N', strtotime($dt));
      $isSun  = ($dow === 7);
      $isHol  = isset($holidayDates[$dt]);
      $isFut  = ($dt > $today);
      $lvType = $leaveDates[$e['id']][$dt] ?? null;
      $lvCode = $leaveCodes[$e['id']][$dt] ?? null;
      $present = isset($punchMap[$e['EmployeeCode']][$dt]);

      $cls = 'dt'; $label = '';

      if ($isSun) {
          $cls .= ' c-s'; $label = 'S';
      } elseif ($isHol) {
          $cls .= ' c-h'; $label = 'H';
      } elseif ($lvType === 'full_day' && $lvCode === 'CO') {
          $compOff++; $dayTotals[$dt]['CO']++;
          $cls .= ' c-co'; $label = 'CO';
      } elseif ($lvType === 'full_day') {
          $fullLv++; $dayTotals[$dt]['L']++;
          $cls .= ' c-l'; $label = 'L';
      } elseif ($lvType === 'half_am') {
          $halfLv++; $dayTotals[$dt]['HL']++;
          $cls .= ' c-hl'; $label = 'HL<br><small>AM</small>';
      } elseif ($lvType === 'half_pm') {
          $halfLv++; $dayTotals[$dt]['HL']++;
          $cls .= ' c-hl'; $label = 'HL<br><small>PM</small>';
      } elseif ($present) {
          if (false) { /* HP detection not applicable in simple view — no shift data */ }
          $presentDays++; $dayTotals[$dt]['P']++; $cls .= ' c-p'; $label = 'P';
      } elseif (!$isFut) {
          $absentDays++; $dayTotals[$dt]['A']++; $label = 'A';
      }
    ?>
    <td class="<?= $cls ?>"><?= $label ?></td>
    <?php endforeach; ?>
    <td class="sum" style="color:#1b5e20"><?= $presentDays ?></td>
    <td class="sum" style="color:#004085"><?= $hpDays ?: '—' ?></td>
    <td class="sum" style="color:#b71c1c"><?= $absentDays ?></td>
    <td class="sum" style="color:#e65100"><?= $fullLv ?: '—' ?></td>
    <td class="sum" style="color:#087990"><?= $compOff ?: '—' ?></td>
    <td class="sum" style="color:#856404"><?= $halfLv ?: '—' ?></td>
  </tr>
  <?php
    $grand['P']  += $presentDays; $grand['HP'] += $hpDays;
    $grand['A']  += $absentDays;  $grand['L']  += $fullLv; $grand['HL'] += $halfLv;
    $grand['CO'] += $compOff;
  endforeach; ?>
  </tbody>
  <tfoot>
    <tr style="background:#222;color:#fff;font-size:9px">
      <td class="col-emp" style="text-align:left;font-weight:bold">Daily Total</td>
      <?php foreach ($dates as $dt):
        $dow = (int)date('N', strtotime($dt));
        $tot = $dayTotals[$dt];
        $bg  = ($dow===7) ? '#444' : (isset($holidayDates[$dt]) ? '#2e7d32' : '#222');
      ?>
      <td class="dt" style="background:<?= $bg ?>;padding:1px 2px;line-height:1.2">
        <?php if ($dow===7): ?><span style="color:#aaa">S</span>
        <?php elseif (isset($holidayDates[$dt])): ?><span style="color:#a5d6a7">H</span>
        <?php elseif ($dt > $today): ?><span style="color:#777">—</span>
        <?php else: ?>
          <?php if ($tot['P'])  echo '<div style="color:#a5d6a7">P:'.$tot['P'].'</div>'; ?>
          <?php if ($tot['A'])  echo '<div style="color:#ef9a9a">A:'.$tot['A'].'</div>'; ?>
          <?php if ($tot['L'])  echo '<div style="color:#ef9a9a">L:'.$tot['L'].'</div>'; ?>
          <?php if ($tot['CO']) echo '<div style="color:#4dd0e1">CO:'.$tot['CO'].'</div>'; ?>
          <?php if ($tot['HL']) echo '<div style="color:#ffe082">HL:'.$tot['HL'].'</div>'; ?>
        <?php endif; ?>
      </td>
      <?php endforeach; ?>
      <td style="color:#a5d6a7;font-weight:bold"><?= $grand['P'] ?></td>
      <td style="color:#90caf9;font-weight:bold"><?= $grand['HP'] ?: '—' ?></td>
      <td style="color:#ef9a9a;font-weight:bold"><?= $grand['A'] ?></td>
      <td style="color:#ef9a9a;font-weight:bold"><?= $grand['L'] ?: '—' ?></td>
      <td style="color:#4dd0e1;font-weight:bold"><?= $grand['CO'] ?: '—' ?></td>
      <td style="color:#ffe082;font-weight:bold"><?= $grand['HL'] ?: '—' ?></td>
    </tr>
  </tfoot>
</table>
<?php
  $totalEmps   = count($employees);
  $workingDays = count(array_filter($dates, fn($d) => !isset($holidayDates[$d]) && date('N',strtotime($d))!=7 && $d<=$today));
  $maxPoss     = $totalEmps * $workingDays;
  $pctP        = $maxPoss > 0 ? round($grand['P'] / $maxPoss * 100) : 0;
  $pctA        = $maxPoss > 0 ? round($grand['A'] / $maxPoss * 100) : 0;
?>
<table class="summary-box" style="margin-top:8px">
  <thead>
    <tr>
      <th>Employees</th>
      <th>Working Days</th>
      <th>Present (P)</th>
      <th>Absent (A)</th>
      <th>Full Leave (L)</th>
      <th>Comp Off (CO)</th>
      <th>Half Leave (HL)</th>
      <th>Holidays</th>
      <th>Attendance %</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><?= $totalEmps ?></td>
      <td><?= $workingDays ?></td>
      <td style="color:#1b5e20"><?= $grand['P'] ?></td>
      <td style="color:#b71c1c"><?= $grand['A'] ?></td>
      <td style="color:#e65100"><?= $grand['L'] ?></td>
      <td style="color:#087990"><?= $grand['CO'] ?></td>
      <td style="color:#856404"><?= $grand['HL'] ?></td>
      <td><?= count($holidayDates) ?></td>
      <td><?= $pctP ?>% P &nbsp; <?= $pctA ?>% A</td>
    </tr>
  </tbody>
</table>
<?php else: ?>
<p style="margin:20px;color:#c00">No employees found.</p>
<?php endif; ?>

</div><!-- #pg-content -->
<script>
document.getElementById('pg-loader').style.display  = 'none';
document.getElementById('pg-content').style.display = 'block';
</script>
</body>
</html>
