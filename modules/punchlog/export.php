<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db = getDb();

$fSN   = trim($_GET['sn']   ?? '');
$fEId  = trim($_GET['eid']  ?? '');
$fFrom = trim($_GET['from'] ?? date('Y-m-d'));
$fTo   = trim($_GET['to']   ?? date('Y-m-d'));

if (!$fSN) {
    http_response_code(400);
    exit('SerialNumber is required for export.');
}

$cred = null;
try {
    $cred = $db->query("SELECT * FROM tblAdmsCredentials WHERE IsActive = 1 ORDER BY id ASC LIMIT 1")->fetch();
} catch (Exception $e) {}

if (!$cred) {
    http_response_code(503);
    exit('No active ADMS credential configured.');
}

$url = rtrim($cred['Endpoint'], '/') . '/api/punchlog.php?SerialNumber=' . urlencode($fSN);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['X-Api-Key: ' . $cred['ApiKey']],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    $body   = json_decode($response, true);
    $detail = $body['error'] ?? $body['message'] ?? substr($response, 0, 200);
    http_response_code(502);
    exit("ADMS API returned HTTP {$httpCode}: {$detail}  [{$url}]");
}

$data = json_decode($response, true);
if (!$data || empty($data['success'])) {
    http_response_code(502);
    exit('ADMS API error: ' . ($data['error'] ?? $data['message'] ?? 'Unknown'));
}

$fromDt = $fFrom . ' 00:00:00';
$toDt   = $fTo   . ' 23:59:59';

$rows = array_filter($data['data'] ?? [], function ($r) use ($fromDt, $toDt, $fEId) {
    if ($r['PunchDateTime'] < $fromDt || $r['PunchDateTime'] > $toDt) return false;
    if ($fEId && $r['EnrollId'] !== $fEId) return false;
    return true;
});

usort($rows, fn($a, $b) => strcmp($b['PunchDateTime'], $a['PunchDateTime']));

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="punchlog_' . $fFrom . '_' . $fTo . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['SerialNumber', 'EnrollId', 'PunchDateTime', 'Mode']);
foreach ($rows as $r) {
    fputcsv($out, [$r['SerialNumber'], $r['EnrollId'], $r['PunchDateTime'], $r['Mode']]);
}
fclose($out);
