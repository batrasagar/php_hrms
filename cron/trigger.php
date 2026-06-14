<?php
/**
 * HTTP trigger for cron-job.org (or any external cron service).
 *
 * URL:  https://yourdomain.com/cron/trigger.php?step=sync&token=SECRET
 *
 * Steps: sync | attendance | monthly | payroll
 *        monthly and payroll auto-target previous month.
 *
 * Token is stored in tblCronToken (generated once via /modules/docs/pipeline.php).
 * Pass it as ?token=  OR  X-Cron-Token header.
 */

define('BASE_URL', '..');
define('CRON_MAX_SECONDS', 120);    // abort pipeline after this many seconds

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../services/ShardManager.php';
require_once __DIR__ . '/../services/PunchSyncService.php';
require_once __DIR__ . '/../services/AttendanceProcessor.php';
require_once __DIR__ . '/../services/MonthlyProcessor.php';
require_once __DIR__ . '/../services/PayRollProcessor.php';

header('Content-Type: application/json');
set_time_limit(CRON_MAX_SECONDS + 30);

$t0 = microtime(true);

// ── Auth ──────────────────────────────────────────────────────────────────────
$token = trim(
    $_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? ''
);

if ($token === '') {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => 'Missing token']));
}

$db = getDb();

// Ensure token table exists
$db->exec("CREATE TABLE IF NOT EXISTS `tblCronToken` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `Token`     CHAR(64)     NOT NULL UNIQUE COMMENT 'SHA-256 hex of the raw token',
    `Label`     VARCHAR(100) NOT NULL DEFAULT '',
    `IsActive`  TINYINT(1)   NOT NULL DEFAULT 1,
    `LastUsed`  DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB");

$hash = hash('sha256', $token);
$row  = $db->prepare("SELECT id FROM tblCronToken WHERE Token=? AND IsActive=1");
$row->execute([$hash]);
if (!$row->fetch()) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'Invalid or inactive token']));
}
$db->prepare("UPDATE tblCronToken SET LastUsed=NOW() WHERE Token=?")->execute([$hash]);

// ── Step ──────────────────────────────────────────────────────────────────────
$step = trim($_GET['step'] ?? 'sync');
$cid  = (int)($_GET['company'] ?? 0);   // 0 = all

// For monthly / payroll — accept explicit ?ym=YYMM or auto-target previous month
$ymParam = trim($_GET['ym'] ?? '');
if (preg_match('/^\d{4}$/', $ymParam)) {
    $yr   = 2000 + (int)substr($ymParam, 0, 2);
    $mnth = (int)substr($ymParam, 2, 2);
    $ym   = $ymParam;
} else {
    $prevTs = mktime(0, 0, 0, (int)date('n') - 1, 1, (int)date('Y'));
    $ym     = date('ym', $prevTs);
    $yr     = (int)date('Y', $prevTs);
    $mnth   = (int)date('n', $prevTs);
}

// Load companies
if ($cid > 0) {
    $stmt = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND IsActive=1");
    $stmt->execute([$cid]);
} else {
    $stmt = $db->query("SELECT id FROM tblCompany WHERE IsActive=1");
}
$companies = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');

if (!$companies) {
    exit(json_encode(['ok' => true, 'step' => $step, 'msg' => 'No active companies', 'rows' => 0]));
}

$shard  = new ShardManager($db);
$totals = [];

switch ($step) {

    case 'sync':
        // Sync runs once across all companies — a shared machine serves multiple companies
        // and company resolution comes from tblEmployee, not from the device.
        $svc  = new PunchSyncService($db);
        $from = date('Y-m-d', strtotime('-1 day'));
        $to   = date('Y-m-d');
        $totals['all'] = $svc->sync(0, $from, $to);   // 0 = all companies
        break;

    case 'attendance':
        $proc = new AttendanceProcessor($db, $shard);
        foreach ($companies as $c) {
            $totals[$c] = $proc->processCompany($c, date('Y-m-d'));
        }
        break;

    case 'monthly':
        $proc = new MonthlyProcessor($db, $shard);
        foreach ($companies as $c) {
            $totals[$c] = $proc->processMonth($c, $yr, $mnth);
        }
        break;

    case 'payroll':
        $proc = new PayRollProcessor($db, $shard);
        foreach ($companies as $c) {
            $totals[$c] = $proc->processMonth($c, $yr, $mnth);
        }
        break;

    default:
        http_response_code(400);
        exit(json_encode(['ok' => false, 'error' => "Unknown step: {$step}"]));
}

$elapsed = round(microtime(true) - $t0, 2);
echo json_encode([
    'ok'      => true,
    'step'    => $step,
    'ym'      => in_array($step, ['monthly','payroll']) ? $ym : null,
    'rows'    => array_sum($totals),
    'detail'  => $totals,
    'elapsed' => $elapsed . 's',
]);
