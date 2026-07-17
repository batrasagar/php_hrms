<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
requirePermission('apikeys.view');

$db = getDb();

// ── Actions ───────────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    requirePermission('apikeys.edit');
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM tblApiKeys WHERE id=?")->execute([$id]);
    $_SESSION['flash'] = 'API key deleted.';
    header('Location: list.php'); exit;
}

if (isset($_GET['toggle'])) {
    requirePermission('apikeys.edit');
    $id = (int)$_GET['toggle'];
    $db->prepare("UPDATE tblApiKeys SET IsActive = 1 - IsActive WHERE id=?")->execute([$id]);
    header('Location: list.php'); exit;
}

// ── Page setup ────────────────────────────────────────────────────────────────
$pageTitle  = 'API Keys';
$activePage = 'apikeys';
require_once __DIR__ . '/../../includes/header.php';

$msg  = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$keys = $db->query(
    "SELECT k.id, k.Label, k.UserId, k.RawKey, k.IsActive, k.CreatedAt,
            u.Name AS UserName, u.Email AS UserEmail, u.Role AS UserRole
     FROM tblApiKeys k
     LEFT JOIN tblUser u ON u.id = k.UserId
     ORDER BY k.id DESC"
)->fetchAll();
?>
<?php if ($msg): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <span><?= count($keys) ?> key(s)</span>
  <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Issue API Key</a>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0" id="tblApiKeys">
      <thead class="table-light">
        <tr>
          <th>#</th><th>Label</th><th>User</th><th>API Key</th><th>Status</th><th>Issued</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($keys as $k): ?>
        <tr>
          <td><?= $k['id'] ?></td>
          <td><?= htmlspecialchars($k['Label']) ?></td>
          <td>
            <?php if ($k['UserName']): ?>
              <?= htmlspecialchars($k['UserName']) ?>
              <div class="text-muted small"><?= htmlspecialchars($k['UserEmail']) ?> &middot; <?= htmlspecialchars($k['UserRole']) ?></div>
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="input-group input-group-sm" style="min-width:220px;max-width:320px">
              <input type="password" class="form-control form-control-sm font-monospace key-input"
                     value="<?= htmlspecialchars($k['RawKey']) ?>" readonly>
              <button class="btn btn-outline-secondary btn-reveal" type="button" title="Show/hide key">
                <i class="bi bi-eye"></i>
              </button>
              <button class="btn btn-outline-secondary btn-copy" type="button"
                      data-key="<?= htmlspecialchars($k['RawKey']) ?>" title="Copy key">
                <i class="bi bi-clipboard"></i>
              </button>
            </div>
          </td>
          <td>
            <?= $k['IsActive']
              ? '<span class="badge bg-success">Active</span>'
              : '<span class="badge bg-secondary">Inactive</span>' ?>
          </td>
          <td><?= $k['CreatedAt'] ?></td>
          <td>
            <a href="list.php?toggle=<?= $k['id'] ?>"
               class="btn btn-sm <?= $k['IsActive'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
               title="<?= $k['IsActive'] ? 'Deactivate' : 'Activate' ?>">
              <i class="bi bi-<?= $k['IsActive'] ? 'pause-circle' : 'play-circle' ?>"></i>
            </a>
            <a href="list.php?delete=<?= $k['id'] ?>"
               class="btn btn-sm btn-outline-danger"
               onclick="return confirm('Delete this API key? This cannot be undone.')">
              <i class="bi bi-trash"></i>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$extraJs = <<<'JS'
<script>
$(()=>{
  $("#tblApiKeys").DataTable({order:[[0,"desc"]]});

  $(document).on('click','.btn-reveal', function(){
    const inp = $(this).closest('.input-group').find('.key-input')[0];
    const icon = $(this).find('i');
    if(inp.type === 'password'){
      inp.type = 'text'; icon.removeClass('bi-eye').addClass('bi-eye-slash');
    } else {
      inp.type = 'password'; icon.removeClass('bi-eye-slash').addClass('bi-eye');
    }
  });

  $(document).on('click','.btn-copy', function(){
    const key = $(this).data('key');
    navigator.clipboard.writeText(key);
    const icon = $(this).find('i');
    icon.removeClass('bi-clipboard').addClass('bi-check-lg');
    setTimeout(()=>icon.removeClass('bi-check-lg').addClass('bi-clipboard'), 1500);
  });
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
