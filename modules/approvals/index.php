<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireSuperAdmin();

$db = getDb();

// ── POST actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $action = $_POST['_action'] ?? 'approve';

    if ($action === 'approve' && isset($_POST['approve_id'])) {
        $id      = (int)$_POST['approve_id'];
        $coLimit = (int)($_POST['company_limit']  ?? 1);
        $mLimit  = (int)($_POST['machines_limit'] ?? 5);
        $eLimit  = (int)($_POST['emp_limit']      ?? 100);
        if ($coLimit < 1 && $coLimit !== -1) $coLimit = 1;
        if ($mLimit  < 1 && $mLimit  !== -1) $mLimit  = 5;
        if ($eLimit  < 1 && $eLimit  !== -1) $eLimit  = 100;
        $db->prepare(
            "UPDATE tblUser SET Status='active', CompanyLimit=?, MachinesLimit=?, EmpLimit=?, IsActive=1 WHERE id=?"
        )->execute([$coLimit, $mLimit, $eLimit, $id]);
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>'Account approved.','redirect'=>'index.php']); exit; }
        $_SESSION['flash'] = 'Account approved.';
        header('Location: index.php'); exit;

    } elseif ($action === 'reject' && isset($_POST['reject_id'])) {
        $db->prepare("UPDATE tblUser SET Status='rejected' WHERE id=? AND Role='admin'")->execute([(int)$_POST['reject_id']]);
        $_SESSION['flash'] = 'Account rejected.';
        header('Location: index.php'); exit;

    } elseif ($action === 'toggle' && isset($_POST['toggle_id'])) {
        $db->prepare(
            "UPDATE tblUser SET Status = CASE WHEN Status='active' THEN 'rejected' ELSE 'active' END WHERE id=? AND Role='admin'"
        )->execute([(int)$_POST['toggle_id']]);
        header('Location: index.php'); exit;

    } elseif ($action === 'delete' && isset($_POST['delete_id'])) {
        $db->prepare("DELETE FROM tblUser WHERE id=? AND Role='admin'")->execute([(int)$_POST['delete_id']]);
        $_SESSION['flash'] = 'Account deleted.';
        header('Location: index.php'); exit;
    }
}

// ── Page setup ────────────────────────────────────────────────────────────────
$pageTitle  = 'Signup Approvals';
$activePage = 'approvals';
require_once __DIR__ . '/../../includes/header.php';

$msg = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

$pending  = $db->query("SELECT * FROM tblUser WHERE Role='admin' AND Status='pending'  ORDER BY CreatedAt ASC")->fetchAll();
$approved = $db->query("SELECT * FROM tblUser WHERE Role='admin' AND Status='active'   ORDER BY CreatedAt DESC LIMIT 50")->fetchAll();
$rejected = $db->query("SELECT * FROM tblUser WHERE Role='admin' AND Status='rejected' ORDER BY CreatedAt DESC LIMIT 50")->fetchAll();
?>
<?php if ($msg): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Pending -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
    <span>Pending Requests</span>
    <?php if ($pending): ?>
    <span class="badge bg-warning text-dark"><?= count($pending) ?></span>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>Name</th><th>Email</th><th>Requested</th><th>Limits &amp; Action</th></tr>
      </thead>
      <tbody>
      <?php foreach ($pending as $u): ?>
      <tr>
        <td><?= htmlspecialchars($u['Name']) ?></td>
        <td><?= htmlspecialchars($u['Email']) ?></td>
        <td class="small text-muted"><?= htmlspecialchars(substr($u['CreatedAt'], 0, 16)) ?></td>
        <td>
          <form method="POST" class="mb-1" data-ajax>
            <?= csrf_field() ?>
            <input type="hidden" name="_action"    value="approve">
            <input type="hidden" name="approve_id" value="<?= $u['id'] ?>">
            <div class="d-flex flex-wrap gap-2 align-items-end">
              <div>
                <label class="form-label small mb-1">Companies</label>
                <input type="number" name="company_limit"  class="form-control form-control-sm"
                       value="1"   min="-1" style="width:75px" placeholder="1" title="-1 = unlimited">
              </div>
              <div>
                <label class="form-label small mb-1">Machines</label>
                <input type="number" name="machines_limit" class="form-control form-control-sm"
                       value="5"   min="-1" style="width:75px" placeholder="5" title="-1 = unlimited">
              </div>
              <div>
                <label class="form-label small mb-1">Employees</label>
                <input type="number" name="emp_limit"      class="form-control form-control-sm"
                       value="100" min="-1" style="width:85px" placeholder="100" title="-1 = unlimited">
              </div>
              <button type="submit" class="btn btn-sm btn-success">
                <i class="bi bi-check-lg"></i> Approve
              </button>
            </div>
            <div class="form-text">-1 = unlimited</div>
          </form>
          <div class="d-flex gap-1">
            <form method="POST" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="_action"  value="reject">
              <input type="hidden" name="reject_id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger"
                      onclick="return confirm('Reject this request?')">
                <i class="bi bi-x-lg"></i> Reject
              </button>
            </form>
            <form method="POST" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="_action"   value="delete">
              <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-secondary"
                      onclick="return confirm('Permanently delete this account?')">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($pending)): ?>
      <tr><td colspan="4" class="text-center text-muted py-3">No pending requests.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Approved -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white fw-semibold">Approved Tenants</div>
  <div class="card-body p-0">
    <table class="table table-hover table-sm align-middle mb-0" id="tblApproved">
      <thead class="table-light">
        <tr>
          <th>Name</th><th>Email</th>
          <th>Companies</th><th>Machines</th><th>Employees</th>
          <th>Approved</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($approved as $u): ?>
      <?php $fmtLimit = fn($v) => $v == -1 ? '<span class="badge bg-info">&#8734;</span>' : $v; ?>
      <tr>
        <td><?= htmlspecialchars($u['Name']) ?></td>
        <td><?= htmlspecialchars($u['Email']) ?></td>
        <td><?= $fmtLimit($u['CompanyLimit']) ?></td>
        <td><?= $fmtLimit($u['MachinesLimit']) ?></td>
        <td><?= $fmtLimit($u['EmpLimit']) ?></td>
        <td class="small text-muted"><?= htmlspecialchars(substr($u['CreatedAt'], 0, 10)) ?></td>
        <td>
          <form method="POST" class="d-inline mb-1" data-ajax>
            <?= csrf_field() ?>
            <input type="hidden" name="_action"    value="approve">
            <input type="hidden" name="approve_id" value="<?= $u['id'] ?>">
            <div class="d-flex flex-wrap gap-1 align-items-center">
              <input type="number" name="company_limit"  class="form-control form-control-sm"
                     value="<?= $u['CompanyLimit']  ?>" min="-1" style="width:60px"
                     placeholder="Co." title="Company limit (-1=unlimited)">
              <input type="number" name="machines_limit" class="form-control form-control-sm"
                     value="<?= $u['MachinesLimit'] ?>" min="-1" style="width:60px"
                     placeholder="Mac." title="Machines limit (-1=unlimited)">
              <input type="number" name="emp_limit"      class="form-control form-control-sm"
                     value="<?= $u['EmpLimit']      ?>" min="-1" style="width:68px"
                     placeholder="Emp." title="Employee limit (-1=unlimited)">
              <button type="submit" class="btn btn-sm btn-outline-primary" title="Save limits">
                <i class="bi bi-floppy"></i>
              </button>
            </div>
          </form>
          <div class="d-flex gap-1 mt-1">
            <form method="POST" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="_action"   value="toggle">
              <input type="hidden" name="toggle_id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-warning"
                      onclick="return confirm('Revoke this account?')" title="Revoke">
                <i class="bi bi-pause-circle"></i>
              </button>
            </form>
            <form method="POST" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="_action"   value="delete">
              <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger"
                      onclick="return confirm('Permanently delete this account and all its data?')" title="Delete">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Rejected -->
<?php if ($rejected): ?>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold">Rejected</div>
  <div class="card-body p-0">
    <table class="table table-hover table-sm mb-0">
      <thead class="table-light">
        <tr><th>Name</th><th>Email</th><th>Date</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rejected as $u): ?>
      <tr>
        <td><?= htmlspecialchars($u['Name']) ?></td>
        <td><?= htmlspecialchars($u['Email']) ?></td>
        <td class="small text-muted"><?= htmlspecialchars(substr($u['CreatedAt'], 0, 10)) ?></td>
        <td>
          <div class="d-flex gap-1">
            <form method="POST" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="_action"   value="toggle">
              <input type="hidden" name="toggle_id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-success">
                <i class="bi bi-arrow-counterclockwise"></i> Re-approve
              </button>
            </form>
            <form method="POST" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="_action"   value="delete">
              <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger"
                      onclick="return confirm('Permanently delete this account?')">
                <i class="bi bi-trash"></i>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php
$extraJs = '<script>$(()=>{$("#tblApproved").DataTable({order:[[5,"desc"]],pageLength:15});});</script>';
require_once __DIR__ . '/../../includes/footer.php';
