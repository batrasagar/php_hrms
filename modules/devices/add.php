<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
requirePermission('devices.view');

$editId = (int)($_GET['edit'] ?? 0);
$user   = currentUser();
$db     = getDb();
$errors = [];

// ── Load accessible companies for dropdown ────────────────────────────────────
if ($user['role'] === 'superadmin') {
    $companies = $db->query(
        "SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name"
    )->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['scope_id']]);
    $companies = $stmt->fetchAll();
}

$companyNames = array_column($companies, 'Name');

$row = ['Company' => '', 'SerialNumber' => '', 'Stamp' => '1541180497'];

// ── Load existing record for edit ─────────────────────────────────────────────
if ($editId) {
    if ($user['role'] === 'superadmin') {
        $stmt = $db->prepare("SELECT id, Company, SerialNumber, Stamp FROM tblDevices WHERE id=?");
        $stmt->execute([$editId]);
    } elseif (!empty($companyNames)) {
        $ph   = implode(',', array_fill(0, count($companyNames), '?'));
        $stmt = $db->prepare("SELECT id, Company, SerialNumber, Stamp FROM tblDevices WHERE id=? AND Company IN ($ph)");
        $stmt->execute([$editId, ...$companyNames]);
    } else {
        header('Location: list.php'); exit;
    }
    $fetched = $stmt->fetch();
    if (!$fetched) { header('Location: list.php'); exit; }
    $row = $fetched;
}

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('devices.edit');
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $company = trim($_POST['Company'] ?? '');
    $sn      = trim($_POST['SerialNumber'] ?? '');
    $stamp   = trim($_POST['Stamp'] ?? '1541180497');

    if (!$company) $errors[] = 'Company is required.';
    if (!$sn)      $errors[] = 'Serial Number is required.';

    // Verify the posted company is in the accessible list (for non-superadmin)
    if ($company && $user['role'] !== 'superadmin' && !in_array($company, $companyNames, true)) {
        $errors[] = 'Invalid company selected.';
    }

    if (!$errors) {
        try {
            if ($editId) {
                $db->prepare("UPDATE tblDevices SET Company=?,SerialNumber=?,Stamp=? WHERE id=?")
                   ->execute([$company, $sn, $stamp, $editId]);
            } else {
                $db->prepare("INSERT INTO tblDevices (Company,SerialNumber,Stamp) VALUES (?,?,?)")
                   ->execute([$company, $sn, $stamp]);
            }
            $_SESSION['flash'] = $editId ? 'Device updated.' : 'Device added.';
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>'list.php']); exit; }
            header('Location: list.php'); exit;
        } catch (\PDOException $e) {
            $errors[] = str_contains($e->getMessage(), 'Duplicate') ? 'Serial number already exists.' : 'DB error: ' . $e->getMessage();
        }
    }
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>$errors]); exit; }
    $row = ['Company' => $company, 'SerialNumber' => $sn, 'Stamp' => $stamp];
}

$pageTitle  = $editId ? 'Edit Device' : 'Add Device';
$activePage = 'devices';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($errors): ?>
<div class="alert alert-danger">
  <ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?></ul>
</div>
<?php endif; ?>
<div class="card border-0 shadow-sm" style="max-width:480px">
  <div class="card-body">
    <form method="POST" data-ajax>
      <div class="mb-3">
        <label class="form-label">Company <span class="text-danger">*</span></label>
        <select name="Company" class="form-select" required>
          <option value="">— select company —</option>
          <?php foreach ($companies as $c): ?>
          <option value="<?= htmlspecialchars($c['Name']) ?>"
                  <?= $row['Company'] === $c['Name'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['Name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Serial Number <span class="text-danger">*</span></label>
        <input type="text" name="SerialNumber" class="form-control"
               value="<?= htmlspecialchars($row['SerialNumber']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Stamp</label>
        <input type="text" name="Stamp" class="form-control"
               value="<?= htmlspecialchars($row['Stamp']) ?>">
        <div class="form-text">Initial stamp sent to device on first GET ping.</div>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $editId ? 'Update' : 'Add' ?> Device</button>
        <a href="list.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
