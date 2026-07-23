<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/punch_source.php';
requireLogin();
blockCompliance();
requirePermission('report_punchlog.view');

$db   = getDb();
$user = currentUser();

$fCompany = activeCompanyId($db, $user); // validates ?company= against the user's companies
$fSN      = trim($_GET['sn']   ?? '');
$fFrom    = trim($_GET['from'] ?? date('Y-m-01'));
$fTo      = trim($_GET['to']   ?? date('Y-m-d'));

if (!$fCompany) { http_response_code(400); exit('No company in scope.'); }

// Employee-code → name for this company.
$codeToName = [];
$en = $db->prepare("SELECT EmployeeCode, Name FROM tblEmployee WHERE CompanyId = ?");
$en->execute([$fCompany]);
foreach ($en->fetchAll() as $r) $codeToName[(string)$r['EmployeeCode']] = (string)$r['Name'];

$fromDt = $fFrom . ' 00:00:00';
$toDt   = $fTo   . ' 23:59:59';
$rows   = [];
foreach (punchShardsForRange($fFrom, $fTo) as $tbl) {
    $sql  = "SELECT DeviceSerial, EmpCode, EnrollId, PunchTime, PunchType
               FROM `$tbl`
              WHERE CompanyId = ? AND PunchTime BETWEEN ? AND ?";
    $args = [$fCompany, $fromDt, $toDt];
    if ($fSN !== '') { $sql .= " AND DeviceSerial = ?"; $args[] = $fSN; }
    $sql .= " ORDER BY DeviceSerial, PunchTime";
    try {
        $st = $db->prepare($sql);
        $st->execute($args);
    } catch (PDOException $e) { continue; }
    foreach ($st->fetchAll() as $r) $rows[] = $r;
}

$typeLabel = [0 => 'Unknown', 1 => 'In', 2 => 'Out'];
$header    = ['Device', 'Emp Code', 'Name', 'Enroll ID', 'Punch Time', 'Type'];
$isXls     = (($_GET['format'] ?? '') === 'xls');
$fname     = 'device_punchlog_' . $fFrom . '_' . $fTo . ($isXls ? '.xls' : '.csv');

$line = function (array $r) use ($codeToName, $typeLabel): array {
    $code = (string)($r['EmpCode'] ?? '');
    return [
        $r['DeviceSerial'],
        $code,
        $code !== '' ? ($codeToName[$code] ?? '') : '',
        $r['EnrollId'],
        $r['PunchTime'],
        $typeLabel[(int)($r['PunchType'] ?? 0)] ?? 'Unknown',
    ];
};

if ($isXls) {
    // HTML-table .xls — same trick as the client-side excelFromRows helper.
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    echo "\xEF\xBB\xBF<html xmlns:x=\"urn:schemas-microsoft-com:office:excel\"><head><meta charset=\"UTF-8\"></head><body>";
    echo '<table border="1"><tr><th>' . implode('</th><th>', array_map($esc, $header)) . '</th></tr>';
    foreach ($rows as $r) {
        echo '<tr><td>' . implode('</td><td>', array_map($esc, $line($r))) . '</td></tr>';
    }
    echo '</table></body></html>';
    exit;
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $fname . '"');
$out = fopen('php://output', 'w');
fputcsv($out, $header);
foreach ($rows as $r) fputcsv($out, $line($r));
fclose($out);
