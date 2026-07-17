<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();
$msg  = '';
$err  = '';

// Company comes from the global topbar switcher
$fCompany = activeCompanyId($db, $user);

// ── Employees for selected company ───────────────────────────────────────────
$employees = [];
if ($fCompany) {
    $stmt = $db->prepare("
        SELECT EmployeeCode, Name
        FROM tblEmployee
        WHERE CompanyId = ? AND Status = 'active'
        ORDER BY Name
    ");
    $stmt->execute([$fCompany]);
    $employees = $stmt->fetchAll();
}

// ── Handle Save ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $cid     = (int)($_POST['company']   ?? 0);
    $empCode = trim($_POST['emp_code']   ?? '');
    $tDate   = trim($_POST['t_date']     ?? '');
    $inTime  = trim($_POST['in_time']    ?? '');
    $outTime = trim($_POST['out_time']   ?? '');
    $sts     = strtoupper(trim($_POST['att_status'] ?? ''));
    $reason  = trim($_POST['reason']     ?? '');

    // Auth guard
    if ($user['role'] !== 'superadmin') {
        $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $chk->execute([$cid, $user['scope_id']]);
        if (!$chk->fetch()) { $err = 'Unauthorized company.'; if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>[$err]]); exit; } goto render; }
    }

    if (!$cid || !$empCode || !$tDate) {
        $err = 'Company, Employee and Date are required.';
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>[$err]]); exit; }
        goto render;
    }

    // Validate status code if given
    $validSts = ['P','A','HD','WO','WOP','PH','L','SL','CO','OD',''];
    if ($sts && !in_array($sts, $validSts, true)) {
        $err = 'Invalid status code. Use: P / A / HD / WO / PH / L / SL / CO / OD';
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>[$err]]); exit; }
        goto render;
    }

    $db->prepare("
        REPLACE INTO tblPunchLogCorrection
            (CompanyId, EmpCode, tDate, InTime, OutTime, AttStatus, Reason, CorrectedBy, CorrectedAt)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ")->execute([
        $cid, $empCode, $tDate,
        $inTime  ?: null,
        $outTime ?: null,
        $sts     ?: null,
        $reason,
        $user['id'],
    ]);
    $msg = "Correction saved for <strong>{$empCode}</strong> on {$tDate}. Re-run pipeline to apply.";
    $fCompany = $cid;
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>"Correction saved for {$empCode} on {$tDate}."]); exit; }
}

// ── Handle Delete ─────────────────────────────────────────────────────────────
if (isset($_GET['del'])) {
    $delId = (int)$_GET['del'];
    if ($user['role'] === 'superadmin') {
        $db->prepare("DELETE FROM tblPunchLogCorrection WHERE id=?")->execute([$delId]);
    } else {
        $db->prepare("
            DELETE c FROM tblPunchLogCorrection c
            JOIN tblCompany co ON co.id=c.CompanyId AND co.AdminId=?
            WHERE c.id=?
        ")->execute([$user['scope_id'], $delId]);
    }
    header('Location: correction.php?company=' . $fCompany);
    exit;
}

render:
// ── Load existing corrections ─────────────────────────────────────────────────
$fDate = trim($_GET['date'] ?? date('Y-m-d'));
$corrections = [];
if ($fCompany) {
    $where  = ['c.CompanyId = ?'];
    $params = [$fCompany];
    if ($user['role'] !== 'superadmin') {
        $where[] = 'co.AdminId = ?';
        $params[] = $user['scope_id'];
    }
    $params[] = $fDate;
    $stmt = $db->prepare("
        SELECT c.*, e.Name AS EmpName
        FROM tblPunchLogCorrection c
        JOIN tblEmployee e ON e.CompanyId=c.CompanyId AND e.EmployeeCode=c.EmpCode COLLATE utf8mb4_unicode_ci
        " . ($user['role'] !== 'superadmin' ? "JOIN tblCompany co ON co.id=c.CompanyId" : "") . "
        WHERE " . implode(' AND ', $where) . " AND c.tDate = ?
        ORDER BY c.EmpCode
    ");
    $stmt->execute($params);
    $corrections = $stmt->fetchAll();
}

$pageTitle  = 'Punch Correction';
$activePage = 'punch_correction';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold">
    Punch Log Correction
    <small class="text-muted fw-normal ms-2">— overrides tblPunchLog for attendance processing</small>
  </div>
  <div class="card-body">
    <?php if ($msg): ?>
    <div class="alert alert-success py-2"><?= $msg ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="POST" class="row g-2 align-items-end" data-ajax>
      <input type="hidden" name="save" value="1">
      <input type="hidden" name="company" value="<?= (int)$fCompany ?>">

      <!-- Employee -->
      <div class="col-sm-3">
        <label class="form-label small mb-1">Employee <span class="text-danger">*</span></label>
        <select name="emp_code" class="form-select form-select-sm" required>
          <option value="">— Select —</option>
          <?php foreach ($employees as $e): ?>
          <option value="<?= htmlspecialchars($e['EmployeeCode']) ?>">
            <?= htmlspecialchars($e['EmployeeCode'] . ' – ' . $e['Name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Date -->
      <div class="col-sm-2">
        <label class="form-label small mb-1">Date <span class="text-danger">*</span></label>
        <input type="date" name="t_date" class="form-control form-control-sm"
               value="<?= date('Y-m-d') ?>" required>
      </div>

      <!-- In / Out -->
      <div class="col-sm-1">
        <label class="form-label small mb-1">In Time</label>
        <input type="time" name="in_time" class="form-control form-control-sm">
      </div>
      <div class="col-sm-1">
        <label class="form-label small mb-1">Out Time</label>
        <input type="time" name="out_time" class="form-control form-control-sm">
      </div>

      <!-- Force Status -->
      <div class="col-sm-1">
        <label class="form-label small mb-1">Force Status</label>
        <select name="att_status" class="form-select form-select-sm"
                title="Leave blank to auto-compute from punch times">
          <option value="">Auto</option>
          <?php foreach (['P','A','HD','WO','WOP','PH','L','SL','CO','OD'] as $s): ?>
          <option value="<?= $s ?>"><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Reason -->
      <div class="col-sm-3">
        <label class="form-label small mb-1">Reason</label>
        <input type="text" name="reason" class="form-control form-control-sm"
               placeholder="e.g. Forgot to punch, Off-site visit">
      </div>

      <div class="col-sm-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-check-lg me-1"></i>Save
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm"
                onclick="this.form.reset()">Clear</button>
      </div>
    </form>

    <p class="text-muted small mt-2 mb-0">
      <i class="bi bi-info-circle me-1"></i>
      Correction overrides punch log for attendance calculation.
      After saving, re-run the pipeline (attendance → monthly → payroll) for the date.
    </p>
  </div>
</div>

<!-- Existing corrections for selected date -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Corrections</span>
    <form method="GET" class="d-flex gap-2 align-items-center">
      <input type="hidden" name="company" value="<?= $fCompany ?>">
      <input type="date" name="date" class="form-control form-control-sm" style="width:160px"
             value="<?= htmlspecialchars($fDate) ?>">
      <button class="btn btn-outline-secondary btn-sm">Filter</button>
    </form>
  </div>
  <div class="card-body p-0">
    <?php if ($corrections): ?>
    <table class="table table-sm table-hover mb-0">
      <thead class="table-light">
        <tr>
          <th>Date</th>
          <th>Emp Code</th>
          <th>Name</th>
          <th>In</th>
          <th>Out</th>
          <th>Status</th>
          <th>Reason</th>
          <th>By</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($corrections as $c): ?>
        <tr>
          <td><?= $c['tDate'] ?></td>
          <td><code><?= htmlspecialchars($c['EmpCode']) ?></code></td>
          <td><?= htmlspecialchars($c['EmpName'] ?? '') ?></td>
          <td><?= $c['InTime']    ?: '—' ?></td>
          <td><?= $c['OutTime']   ?: '—' ?></td>
          <td>
            <?php if ($c['AttStatus']): ?>
            <span class="badge bg-secondary"><?= $c['AttStatus'] ?></span>
            <?php else: ?>
            <span class="text-muted small">Auto</span>
            <?php endif; ?>
          </td>
          <td class="small"><?= htmlspecialchars($c['Reason']) ?></td>
          <td class="small text-muted"><?= $c['CorrectedAt'] ?></td>
          <td>
            <a href="correction.php?company=<?= $fCompany ?>&date=<?= $fDate ?>&del=<?= $c['id'] ?>"
               class="btn btn-outline-danger btn-sm py-0"
               onclick="return confirm('Delete this correction?')">
              <i class="bi bi-trash"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
    <div class="p-3 text-muted small">No corrections for <?= htmlspecialchars($fDate) ?>.</div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
