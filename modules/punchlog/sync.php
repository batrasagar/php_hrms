<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../services/ShardManager.php';
require_once __DIR__ . '/../../services/AdmsSyncService.php';
require_once __DIR__ . '/../../services/AttendanceProcessor.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

// ── Company list scoped to user ───────────────────────────────────────────────
if ($user['role'] === 'superadmin') {
    $companies = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $adminId   = (in_array($user['role'], ['admin','operator'], true)) ? $user['scope_id'] : ($user['parent_admin_id'] ?? null);
    $stmt      = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$adminId]);
    $companies = $stmt->fetchAll();
}

$result     = null;
$syncRes    = null;
$attnCount  = 0;
$attnError  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $fCompany   = (int)($_POST['company'] ?? 0);
    $fFrom      = trim($_POST['from']    ?? date('Y-m-d', strtotime('-1 day')));
    $fTo        = trim($_POST['to']      ?? date('Y-m-d'));
    $doAttn     = !empty($_POST['process_attendance']);

    // Authorise company access
    if ($fCompany && $user['role'] !== 'superadmin') {
        $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $chk->execute([$fCompany, $user['scope_id']]);
        if (!$chk->fetch()) { $fCompany = 0; }
    }

    if ($fCompany) {
        // Step 1: Sync from ADMS API
        $svc     = new AdmsSyncService($db);
        $syncRes = $svc->sync($fCompany, $fFrom, $fTo);

        // Step 2 (optional): process attendance from shards
        if ($doAttn && empty($syncRes['errors'])) {
            try {
                $sm   = new ShardManager($db);
                $proc = new AttendanceProcessor($db, $sm);
                $attnCount = $proc->processCompany($fCompany, $fTo);
            } catch (Exception $e) {
                $attnError = $e->getMessage();
            }
        }

        $result = 'done';
        if ($isAjax) {
            $parts = [];
            $parts[] = $syncRes['devices'] . ' device(s) queried.';
            $parts[] = $syncRes['inserted'] . ' new punches stored.';
            if ($syncRes['skipped']) $parts[] = $syncRes['skipped'] . ' punches skipped.';
            if ($attnCount > 0) $parts[] = $attnCount . ' attendance record(s) processed.';
            $success = empty($syncRes['errors']) && !$attnError;
            if (!$success) {
                $errs = array_merge($syncRes['errors'], $attnError ? [$attnError] : []);
                header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>$errs,'message'=>implode(' ', $parts)]); exit;
            }
            header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>implode(' ', $parts)]); exit;
        }
    }
}

$pageTitle  = 'Sync & Process Attendance';
$activePage = 'punch_sync';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row justify-content-center">
<div class="col-lg-7">

<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white">
    <h5 class="mb-0"><i class="bi bi-arrow-repeat me-2"></i>Sync Punches &amp; Process Attendance</h5>
  </div>
  <div class="card-body">
    <p class="text-muted small mb-3">
      Fetches punch data from the ADMS API for the selected company and date range, stores it in
      <code>tblPunchLog_YYMM</code> shards, then optionally runs the attendance processor to build
      <code>tblAttendance_YYMM</code>.
    </p>

    <?php if ($result === 'done'): ?>
    <div class="alert alert-<?= empty($syncRes['errors']) ? 'success' : 'warning' ?>">
      <strong>Sync result</strong> — <?= $syncRes['devices'] ?> device(s) queried.<br>
      <i class="bi bi-plus-circle me-1"></i><strong><?= $syncRes['inserted'] ?></strong> new punches stored.
      <?php if ($syncRes['skipped']): ?>
        <br><i class="bi bi-skip-forward me-1"></i><?= $syncRes['skipped'] ?> punches outside date range skipped.
      <?php endif; ?>
      <?php foreach ($syncRes['errors'] as $e): ?>
        <br><small class="text-danger"><i class="bi bi-x-circle me-1"></i><?= htmlspecialchars($e) ?></small>
      <?php endforeach; ?>
      <?php if ($attnCount > 0): ?>
        <hr class="my-2">
        <i class="bi bi-calendar-check me-1"></i><strong><?= $attnCount ?></strong> attendance record(s) processed.
      <?php endif; ?>
      <?php if ($attnError): ?>
        <br><small class="text-danger">Attendance error: <?= htmlspecialchars($attnError) ?></small>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="POST" data-ajax>
      <div class="mb-3">
        <label class="form-label">Company <span class="text-danger">*</span></label>
        <select name="company" class="form-select" required>
          <option value="">— Select Company —</option>
          <?php foreach ($companies as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['Name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="row g-3 mb-3">
        <div class="col">
          <label class="form-label">From Date</label>
          <input type="date" name="from" class="form-control" value="<?= date('Y-m-d', strtotime('-1 day')) ?>">
        </div>
        <div class="col">
          <label class="form-label">To Date</label>
          <input type="date" name="to" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" name="process_attendance" id="chkAttn" value="1" checked>
        <label class="form-check-label" for="chkAttn">
          Also run Attendance Processor after sync
          <small class="text-muted">(builds tblAttendance_YYMM from synced punches)</small>
        </label>
      </div>
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-arrow-repeat me-1"></i> Sync Now
      </button>
    </form>
  </div>
</div>

</div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
