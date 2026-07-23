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

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="device_punchlog_' . $fFrom . '_' . $fTo . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Device', 'Emp Code', 'Name', 'Enroll ID', 'Punch Time', 'Type']);
foreach ($rows as $r) {
    $code = (string)($r['EmpCode'] ?? '');
    fputcsv($out, [
        $r['DeviceSerial'],
        $code,
        $code !== '' ? ($codeToName[$code] ?? '') : '',
        $r['EnrollId'],
        $r['PunchTime'],
        $typeLabel[(int)($r['PunchType'] ?? 0)] ?? 'Unknown',
    ]);
}
fclose($out);
