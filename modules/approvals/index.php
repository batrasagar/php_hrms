<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireSuperAdmin();

$db = getDb();

// ── Actions (must run before any output) ─────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $id    = (int)$_POST['approve_id'];
    $limit = (int)($_POST['company_limit'] ?? 1);
    if ($limit < 1 && $limit !== -1) $limit = 1;
    $db->prepare(
        "UPDATE tblUser SET Status='active', CompanyLimit=?, IsActive=1 WHERE id=?"
    )->execute([$limit, $id]);
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>'Account approved.','redirect'=>'index.php']); exit; }
    $_SESSION['flash'] = 'Account approved.';
    header('Location: index.php'); exit;
}

if (isset($_GET['reject'])) {
    $id = (int)$_GET['reject'];
    $db->prepare("UPDATE tblUser SET Status='rejected' WHERE id=?")->execute([$id]);
    $_SESSION['flash'] = 'Account rejected.';
    header('Location: index.php'); exit;
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $db->prepare(
        "UPDATE tblUser SET Status = CASE WHEN Status='active' THEN 'rejected' ELSE 'active' END WHERE id=?"
    )->execute([$id]);
    header('Location: index.php'); exit;
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
        <tr><th>Name</th><th>Email</th><th>Requested</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php foreach ($pending as $u): ?>
      <tr>
        <td><?= htmlspecialchars($u['Name']) ?></td>
        <td><?= htmlspecialchars($u['Email']) ?></td>
        <td class="small text-muted"><?= htmlspecialchars(substr($u['CreatedAt'], 0, 16)) ?></td>
        <td>
          <form method="POST" class="d-inline" data-ajax>
            <input type="hidden" name="approve_id" value="<?= $u['id'] ?>">
            <div class="input-group input-group-sm" style="width:220px">
              <input type="number" name="company_limit" class="form-control" value="1" min="-1"
                     title="Company limit (-1 = unlimited)">
              <button type="submit" class="btn btn-success">
                <i class="bi bi-check-lg"></i> Approve
              </button>
            </div>
            <div class="form-text">Company limit (-1 = unlimited)</div>
          </form>
          <a href="index.php?reject=<?= $u['id'] ?>"
             class="btn btn-sm btn-outline-danger mt-1"
             onclick="return confirm('Reject this request?')">
            <i class="bi bi-x-lg"></i> Reject
          </a>
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
        <tr><th>Name</th><th>Email</th><th>Company Limit</th><th>Approved</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php foreach ($approved as $u): ?>
      <tr>
        <td><?= htmlspecialchars($u['Name']) ?></td>
        <td><?= htmlspecialchars($u['Email']) ?></td>
        <td><?= $u['CompanyLimit'] == -1 ? '<span class="badge bg-info">Unlimited</span>' : $u['CompanyLimit'] ?></td>
        <td class="small text-muted"><?= htmlspecialchars(substr($u['CreatedAt'], 0, 10)) ?></td>
        <td>
          <form method="POST" class="d-inline" data-ajax>
            <input type="hidden" name="approve_id" value="<?= $u['id'] ?>">
            <div class="input-group input-group-sm" style="width:160px">
              <input type="number" name="company_limit" class="form-control" value="<?= $u['CompanyLimit'] ?>" min="-1">
              <button type="submit" class="btn btn-outline-primary" title="Update limit">
                <i class="bi bi-save"></i>
              </button>
            </div>
          </form>
          <a href="index.php?toggle=<?= $u['id'] ?>"
             class="btn btn-sm btn-outline-warning ms-1"
             onclick="return confirm('Revoke this account?')">
            <i class="bi bi-pause-circle"></i>
          </a>
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
          <a href="index.php?toggle=<?= $u['id'] ?>" class="btn btn-sm btn-outline-success">
            <i class="bi bi-arrow-counterclockwise"></i> Re-approve
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php
$extraJs = '<script>$(()=>{$("#tblApproved").DataTable({order:[[3,"desc"]],pageLength:15});});</script>';
require_once __DIR__ . '/../../includes/footer.php';
