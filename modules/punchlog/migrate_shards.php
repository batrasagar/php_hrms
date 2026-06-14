<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireSuperAdmin();

require_once __DIR__ . '/../../services/ShardManager.php';

$pageTitle = 'Migrate PunchLog to Shards';
$activePage = 'punchlog';
$db = getDb();

// Check legacy table exists
$hasLegacy = $db->query(
    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tblPunchLog_legacy'"
)->fetchColumn();

$totalLegacy = 0;
if ($hasLegacy) {
    $totalLegacy = (int)$db->query("SELECT COUNT(*) FROM tblPunchLog_legacy")->fetchColumn();
}

$result  = null;
$moved   = 0;
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasLegacy && $totalLegacy > 0) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $sm = new ShardManager($db);

    // Get distinct YYMM months from legacy table
    $months = $db->query(
        "SELECT DISTINCT CONCAT(LPAD(YEAR(PunchTime) % 100,2,'0'), LPAD(MONTH(PunchTime),2,'0')) AS ym
         FROM tblPunchLog_legacy ORDER BY ym"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($months as $ym) {
        $tbl = $sm->tbl('PunchLog', $ym);
        try {
            $ins = $db->exec("
                INSERT IGNORE INTO `{$tbl}`
                    (CompanyId, EmpCode, EnrollId, PunchTime, PunchType, DeviceSerial, RawStamp, IsProcessed, SyncedAt)
                SELECT CompanyId, EmpCode, EnrollId, PunchTime, PunchType, DeviceSerial, RawStamp, IsProcessed, SyncedAt
                FROM tblPunchLog_legacy
                WHERE CONCAT(LPAD(YEAR(PunchTime) % 100,2,'0'), LPAD(MONTH(PunchTime),2,'0')) = '{$ym}'
            ");
            $moved += (int)$ins;
        } catch (PDOException $e) {
            $errors[] = "Month {$ym}: " . $e->getMessage();
        }
    }

    if (empty($errors)) {
        // Truncate legacy table after successful migration
        $db->exec("TRUNCATE TABLE tblPunchLog_legacy");
        $result = 'success';
    } else {
        $result = 'partial';
    }

    // Re-read count after migration
    $totalLegacy = (int)$db->query("SELECT COUNT(*) FROM tblPunchLog_legacy")->fetchColumn();
    if ($isAjax) {
        if ($result === 'success') {
            header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>"Migrated " . number_format($moved) . " rows to YYMM shards. Legacy table cleared."]); exit;
        }
        $errMsg = "Partial migration: " . number_format($moved) . " rows moved. " . implode(' ', $errors);
        header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>[$errMsg]]); exit;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white">
    <h5 class="mb-0"><i class="bi bi-database-gear me-2"></i>PunchLog Shard Migration</h5>
  </div>
  <div class="card-body">

<?php if (!$hasLegacy): ?>
    <div class="alert alert-info mb-0">
      <i class="bi bi-info-circle me-2"></i>
      <code>tblPunchLog_legacy</code> does not exist. Run <strong>M010</strong> via
      <a href="<?= BASE_URL ?>/migrate.php?show=1" class="alert-link">migrate.php</a> first
      to rename the monolithic table.
    </div>

<?php elseif ($totalLegacy === 0): ?>
    <div class="alert alert-success mb-0">
      <i class="bi bi-check-circle me-2"></i>
      <code>tblPunchLog_legacy</code> is empty — all data has already been migrated to YYMM shards.
    </div>

<?php else: ?>

    <?php if ($result === 'success'): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>
      Migrated <strong><?= number_format($moved) ?></strong> rows to YYMM shards. Legacy table cleared.
    </div>
    <?php elseif ($result === 'partial'): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>
      Partial migration: <?= number_format($moved) ?> rows moved. Errors below. Legacy table NOT cleared.
      <?php foreach ($errors as $err): ?><br><small class="text-danger"><?= htmlspecialchars($err) ?></small><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <p>Found <strong><?= number_format($totalLegacy) ?></strong> row(s) in <code>tblPunchLog_legacy</code> that need to be distributed into YYMM shard tables.</p>
    <p class="text-muted small">Each punch will be inserted into <code>tblPunchLog_YYMM</code> based on its PunchTime month. Duplicates are skipped. After success, the legacy table is truncated.</p>

    <form method="POST" data-ajax>
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-arrow-right-circle me-1"></i> Run Migration Now
      </button>
    </form>

<?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
