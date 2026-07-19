<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/punch_source.php';
requireLogin();
requirePermission('punchlog.view');

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

// ADMS problems are collected rather than fatal — punches imported from a legacy
// system live only in the local shards, and the API 404s for those serials.
$admsError = null;
$rows      = [];
if (!$cred) $admsError = 'No active ADMS credential configured.';
if ($cred) {

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
        $body      = json_decode($response, true);
        $detail    = $body['error'] ?? $body['message'] ?? substr($response, 0, 200);
        $admsError = "ADMS API returned HTTP {$httpCode}: {$detail}";
    } else {
        $data = json_decode($response, true);
        if (!$data || empty($data['success'])) {
            $admsError = 'ADMS API error: ' . ($data['error'] ?? $data['message'] ?? 'Unknown');
        } else {
            $fromDt = $fFrom . ' 00:00:00';
            $toDt   = $fTo   . ' 23:59:59';
            foreach ($data['data'] ?? [] as $r) {
                if ($r['PunchDateTime'] < $fromDt || $r['PunchDateTime'] > $toDt) continue;
                if ($fEId && $r['EnrollId'] !== $fEId) continue;
                $rows[] = ['SerialNumber' => $r['SerialNumber'] ?? $fSN, 'EnrollId' => $r['EnrollId'],
                           'PunchDateTime' => $r['PunchDateTime'], 'Mode' => $r['Mode'] ?? ''];
            }
        }
    }
}

// Local shards — the only source for bulk-imported tenants.
$seen = [];
foreach ($rows as $r) $seen[$r['EnrollId'] . '|' . $r['PunchDateTime']] = true;
foreach (punchShardsForRange($fFrom, $fTo) as $tbl) {
    try {
        $ls = $db->prepare(
            "SELECT EnrollId, EmpCode, PunchTime, PunchType FROM `$tbl`
              WHERE DeviceSerial = ? AND PunchTime BETWEEN ? AND ?"
        );
        $ls->execute([$fSN, $fFrom . ' 00:00:00', $fTo . ' 23:59:59']);
    } catch (PDOException $e) { continue; }
    foreach ($ls->fetchAll() as $r) {
        $eid = (string)($r['EnrollId'] ?? '');
        if ($fEId && $eid !== $fEId) continue;
        $key = $eid . '|' . $r['PunchTime'];
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $rows[] = ['SerialNumber' => $fSN, 'EnrollId' => $eid,
                   'PunchDateTime' => $r['PunchTime'], 'Mode' => $r['PunchType'] ?? ''];
    }
}

// Only fail when nothing at all could be gathered.
if (!$rows && $admsError) { http_response_code(502); exit($admsError); }

usort($rows, fn($a, $b) => strcmp($b['PunchDateTime'], $a['PunchDateTime']));

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="punchlog_' . $fFrom . '_' . $fTo . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['SerialNumber', 'EnrollId', 'PunchDateTime', 'Mode']);
foreach ($rows as $r) {
    fputcsv($out, [$r['SerialNumber'], $r['EnrollId'], $r['PunchDateTime'], $r['Mode']]);
}
fclose($out);
