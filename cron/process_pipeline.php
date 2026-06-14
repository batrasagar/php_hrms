<?php
/**
 * Attendance Pipeline Runner
 * Usage: php process_pipeline.php [step] [options]
 *
 * Steps:
 *   sync      [company_id] [from_date] [to_date]  — ADMS API → tblPunchLog_YYMM
 *   attendance [company_id] [end_date]             — PunchLog shards → tblAttendance_YYMM
 *   monthly   [company_id] [YYMM]                 — Attendance → MonthlyAttendance
 *   payroll   [company_id] [YYMM]                 — Monthly → PayRoll
 *   all       [company_id] [YYMM]                 — Run all stages
 *
 * Examples:
 *   php process_pipeline.php all 0 2606          # all companies, June 2026
 *   php process_pipeline.php attendance 5        # company 5, up to today
 *   php process_pipeline.php payroll 0 2605      # all companies, May 2026
 */

define('BASE_URL', '..');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../services/ShardManager.php';
require_once __DIR__ . '/../services/AdmsSyncService.php';
require_once __DIR__ . '/../services/AttendanceProcessor.php';
require_once __DIR__ . '/../services/MonthlyProcessor.php';
require_once __DIR__ . '/../services/PayRollProcessor.php';

// ── CLI only ─────────────────────────────────────────────────────────────────
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$step      = $argv[1] ?? 'all';
$cFilter   = (int)($argv[2] ?? 0);  // 0 = all companies
$ymArg     = $argv[3] ?? ShardManager::ym();
$fromDate  = $argv[3] ?? date('Y-m-d', strtotime('-1 day'));
$toDate    = $argv[4] ?? date('Y-m-d');

$db    = getDb();
$shard = new ShardManager($db);

// ── Load company list ─────────────────────────────────────────────────────────
if ($cFilter > 0) {
    $stmt = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND IsActive=1");
    $stmt->execute([$cFilter]);
} else {
    $stmt = $db->query("SELECT id FROM tblCompany WHERE IsActive=1");
}
$companies = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');

if (!$companies) {
    echo "No active companies found.\n";
    exit(0);
}

// ── Parse YYMM arg ────────────────────────────────────────────────────────────
$ym    = str_pad($ymArg, 4, '0', STR_PAD_LEFT);
$yr    = (int)substr($ym, 0, 2) + 2000; // 26 → 2026
$mnth  = (int)substr($ym, 2, 2);

$t0 = microtime(true);
$scope = $cFilter > 0 ? "company={$cFilter}" : "companies=" . count($companies);
echo "[" . date('H:i:s') . "] Pipeline: step={$step} {$scope} ym={$ym}\n";

// ── Stage functions ───────────────────────────────────────────────────────────

function runSync(PDO $db, int $cFilter, string $from, string $to): void
{
    $svc   = new AdmsSyncService($db);
    $scope = $cFilter > 0 ? "company={$cFilter}" : 'all companies';
    if ($cFilter > 0) {
        $res = $svc->sync($cFilter, $from, $to);
        echo "  sync  {$scope} inserted={$res['inserted']} skipped={$res['skipped']}" .
             (count($res['errors']) ? ' errors=' . implode('; ', $res['errors']) : '') . "\n";
    } else {
        // Per-company so each company only gets its own devices
        $companies = $db->query("SELECT id FROM tblCompany WHERE IsActive=1")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($companies as $cid) {
            $res = $svc->sync((int)$cid, $from, $to);
            echo "  sync  company={$cid} inserted={$res['inserted']} skipped={$res['skipped']}\n";
        }
    }
}

function runAttendance(PDO $db, ShardManager $shard, array $companies, string $endDate): void
{
    $proc = new AttendanceProcessor($db, $shard);
    foreach ($companies as $cid) {
        $n = $proc->processCompany($cid, $endDate);
        echo "  attn  company={$cid} rows={$n}\n";
    }
}

function runMonthly(PDO $db, ShardManager $shard, array $companies, int $yr, int $mnth): void
{
    $proc = new MonthlyProcessor($db, $shard);
    foreach ($companies as $cid) {
        $n = $proc->processMonth($cid, $yr, $mnth);
        echo "  mnth  company={$cid} rows={$n}\n";
    }
}

function runPayroll(PDO $db, ShardManager $shard, array $companies, int $yr, int $mnth): void
{
    $proc = new PayRollProcessor($db, $shard);
    foreach ($companies as $cid) {
        $n = $proc->processMonth($cid, $yr, $mnth);
        echo "  pay   company={$cid} rows={$n}\n";
    }
}

// ── Dispatch ──────────────────────────────────────────────────────────────────

switch ($step) {
    case 'sync':
        runSync($db, $cFilter, $fromDate, $toDate);
        break;
    case 'attendance':
        runAttendance($db, $shard, $companies, $toDate);
        break;
    case 'monthly':
        runMonthly($db, $shard, $companies, $yr, $mnth);
        break;
    case 'payroll':
        runPayroll($db, $shard, $companies, $yr, $mnth);
        break;
    case 'all':
    default:
        runSync($db, $cFilter, $fromDate, $toDate);
        runAttendance($db, $shard, $companies, $toDate);
        runMonthly($db, $shard, $companies, $yr, $mnth);
        runPayroll($db, $shard, $companies, $yr, $mnth);
        break;
}

$elapsed = round(microtime(true) - $t0, 2);
echo "[" . date('H:i:s') . "] Done in {$elapsed}s\n";
