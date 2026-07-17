<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

// Company comes from the global topbar switcher
$fCompany = activeCompanyId($db, $user);

// Scope guard helper
function canAccessCompany($db, $user, $cid) {
    if ($user['role'] === 'superadmin') return true;
    $s = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $s->execute([$cid, $user['scope_id']]);
    return (bool)$s->fetch();
}

// Toggle active
if (isset($_GET['toggle']) && $fCompany && canAccessCompany($db, $user, $fCompany)) {
    $db->prepare("UPDATE tblLeaveType SET IsActive = 1 - IsActive WHERE id=? AND CompanyId=?")
       ->execute([(int)$_GET['toggle'], $fCompany]);
    header("Location: types.php?company=$fCompany"); exit;
}

// Delete
if (isset($_GET['delete']) && $fCompany && canAccessCompany($db, $user, $fCompany)) {
    $db->prepare("DELETE FROM tblLeaveType WHERE id=? AND CompanyId=?")->execute([(int)$_GET['delete'], $fCompany]);
    header("Location: types.php?company=$fCompany"); exit;
}

$editId  = (int)($_GET['edit'] ?? 0);
$editRec = null;
$msg     = '';
$err     = '';

// Load record for editing
if ($editId && $fCompany) {
    $s = $db->prepare("SELECT * FROM tblLeaveType WHERE id=? AND CompanyId=?");
    $s->execute([$editId, $fCompany]);
    $editRec = $s->fetch();
}

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_type'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $cid  = (int)($_POST['company_id'] ?? 0);
    $id   = (int)($_POST['type_id'] ?? 0);
    $code = strtoupper(trim($_POST['Code'] ?? ''));
    $name = trim($_POST['Name'] ?? '');
    $paid = isset($_POST['IsPaid']) ? 1 : 0;
    $half = isset($_POST['IsHalfDayAllowed']) ? 1 : 0;

    if (!$cid || !canAccessCompany($db, $user, $cid)) {
        $err = 'Access denied.';
    } elseif (!$code || !$name) {
        $err = 'Code and Name are required.';
    } else {
        try {
            if ($id) {
                $db->prepare(
                    "UPDATE tblLeaveType SET Code=?, Name=?, IsPaid=?, IsHalfDayAllowed=? WHERE id=? AND CompanyId=?"
                )->execute([$code, $name, $paid, $half, $id, $cid]);
                $msg = 'Leave type updated.';
            } else {
                $db->prepare(
                    "INSERT INTO tblLeaveType (CompanyId, Code, Name, IsPaid, IsHalfDayAllowed) VALUES (?,?,?,?,?)"
                )->execute([$cid, $code, $name, $paid, $half]);
                $msg = 'Leave type added.';
            }
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$msg]); exit; }
            header("Location: types.php?company=$cid&msg=" . urlencode($msg)); exit;
        } catch (PDOException $e) {
            $err = strpos($e->getMessage(), 'Duplicate') !== false
                 ? "Code '$code' already exists for this company."
                 : $e->getMessage();
        }
    }
    if (!empty($isAjax) && $err) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>[$err]]); exit; }
}

if (isset($_GET['msg'])) $msg = $_GET['msg'];

// Load types for current company
$types = [];
if ($fCompany) {
    $s = $db->prepare("SELECT * FROM tblLeaveType WHERE CompanyId=? ORDER BY Code");
    $s->execute([$fCompany]);
    $types = $s->fetchAll();
}

$pageTitle  = 'Leave Types';
$activePage = 'leave_types';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-success py-2"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<?php if ($fCompany): ?>
<div class="row g-3">
  <!-- Form -->
  <div class="col-md-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">
        <?= $editRec ? 'Edit Leave Type' : 'Add Leave Type' ?>
      </div>
      <div class="card-body">
        <form method="POST" data-ajax>
          <input type="hidden" name="save_type" value="1">
          <input type="hidden" name="company_id" value="<?= $fCompany ?>">
          <input type="hidden" name="type_id" value="<?= $editRec ? $editRec['id'] : 0 ?>">
          <div class="mb-3">
            <label class="form-label small">Code <span class="text-danger">*</span> <small class="text-muted">(e.g. CL, SL, EL)</small></label>
            <input type="text" name="Code" class="form-control form-control-sm" maxlength="10" required
                   value="<?= htmlspecialchars($editRec['Code'] ?? '') ?>" placeholder="CL">
          </div>
          <div class="mb-3">
            <label class="form-label small">Name <span class="text-danger">*</span></label>
            <input type="text" name="Name" class="form-control form-control-sm" maxlength="100" required
                   value="<?= htmlspecialchars($editRec['Name'] ?? '') ?>" placeholder="Casual Leave">
          </div>
          <div class="mb-2">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="IsPaid" id="chkPaid" value="1"
                     <?= ($editRec['IsPaid'] ?? 1) ? 'checked' : '' ?>>
              <label class="form-check-label small" for="chkPaid">Paid Leave</label>
            </div>
          </div>
          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="IsHalfDayAllowed" id="chkHalf" value="1"
                     <?= ($editRec['IsHalfDayAllowed'] ?? 1) ? 'checked' : '' ?>>
              <label class="form-check-label small" for="chkHalf">Half Day Allowed</label>
            </div>
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-save me-1"></i><?= $editRec ? 'Update' : 'Add' ?>
            </button>
            <?php if ($editRec): ?>
            <a href="types.php?company=<?= $fCompany ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- List -->
  <div class="col-md-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">Leave Types (<?= count($types) ?>)</div>
      <div class="card-body p-0">
        <?php if (empty($types)): ?>
        <div class="p-4 text-center text-muted">No leave types added yet.</div>
        <?php else: ?>
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr><th>Code</th><th>Name</th><th>Paid</th><th>Half Day</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php foreach ($types as $t): ?>
          <tr class="<?= $t['IsActive'] ? '' : 'text-muted' ?>">
            <td><span class="badge bg-secondary"><?= htmlspecialchars($t['Code']) ?></span></td>
            <td><?= htmlspecialchars($t['Name']) ?></td>
            <td><?= $t['IsPaid'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-dash text-muted"></i>' ?></td>
            <td><?= $t['IsHalfDayAllowed'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-dash text-muted"></i>' ?></td>
            <td>
              <span class="badge bg-<?= $t['IsActive'] ? 'success' : 'secondary' ?>">
                <?= $t['IsActive'] ? 'Active' : 'Inactive' ?>
              </span>
            </td>
            <td>
              <a href="types.php?company=<?= $fCompany ?>&edit=<?= $t['id'] ?>" class="btn btn-xs btn-outline-primary btn-sm py-0 px-1"><i class="bi bi-pencil"></i></a>
              <a href="types.php?company=<?= $fCompany ?>&toggle=<?= $t['id'] ?>" class="btn btn-xs btn-outline-<?= $t['IsActive']?'warning':'success' ?> btn-sm py-0 px-1">
                <i class="bi bi-<?= $t['IsActive']?'pause':'play' ?>"></i>
              </a>
              <a href="types.php?company=<?= $fCompany ?>&delete=<?= $t['id'] ?>" class="btn btn-xs btn-outline-danger btn-sm py-0 px-1"
                 onclick="return confirm('Delete this leave type?')"><i class="bi bi-trash"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php else: ?>
<div class="alert alert-info">Select a company from the topbar switcher to manage leave types.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
