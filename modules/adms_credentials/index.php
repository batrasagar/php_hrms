<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireSuperAdmin();

$db = getDb();

// ── Actions ───────────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM tblAdmsCredentials WHERE id = ?")->execute([$id]);
    $_SESSION['flash'] = 'Credential deleted.';
    header('Location: index.php'); exit;
}

if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $db->prepare("UPDATE tblAdmsCredentials SET IsActive = 1 - IsActive WHERE id = ?")->execute([$id]);
    header('Location: index.php'); exit;
}

// ── Page setup ────────────────────────────────────────────────────────────────
$pageTitle  = 'ADMS Credentials';
$activePage = 'adms';
require_once __DIR__ . '/../../includes/header.php';

$msg   = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$creds = $db->query("SELECT * FROM tblAdmsCredentials ORDER BY id DESC")->fetchAll();
$existing = $creds[0] ?? null;
?>
<?php if ($msg): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <span><?= count($creds) ?> credential(s)</span>
  <?php if ($existing): ?>
    <a href="add.php?id=<?= $existing['id'] ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Edit Credential</a>
  <?php else: ?>
    <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Credential</a>
  <?php endif; ?>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0" id="tblCreds">
      <thead class="table-light">
        <tr>
          <th>#</th><th>Label</th><th>Endpoint</th><th>API Key</th><th>Status</th><th>Created</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($creds as $c): ?>
        <tr>
          <td><?= $c['id'] ?></td>
          <td><?= htmlspecialchars($c['Label']) ?></td>
          <td><code class="small"><?= htmlspecialchars($c['Endpoint']) ?></code></td>
          <td>
            <div class="input-group input-group-sm" style="min-width:220px;max-width:320px">
              <input type="password" class="form-control form-control-sm font-monospace key-input"
                     value="<?= htmlspecialchars($c['ApiKey']) ?>" readonly>
              <button class="btn btn-outline-secondary btn-reveal" type="button" title="Show/hide key">
                <i class="bi bi-eye"></i>
              </button>
              <button class="btn btn-outline-secondary btn-copy" type="button"
                      data-key="<?= htmlspecialchars($c['ApiKey']) ?>" title="Copy key">
                <i class="bi bi-clipboard"></i>
              </button>
            </div>
          </td>
          <td>
            <?= $c['IsActive']
              ? '<span class="badge bg-success">Active</span>'
              : '<span class="badge bg-secondary">Inactive</span>' ?>
          </td>
          <td><?= htmlspecialchars(substr($c['CreatedAt'], 0, 10)) ?></td>
          <td>
            <a href="add.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
              <i class="bi bi-pencil"></i>
            </a>
            <a href="index.php?toggle=<?= $c['id'] ?>"
               class="btn btn-sm <?= $c['IsActive'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
               title="<?= $c['IsActive'] ? 'Deactivate' : 'Activate' ?>">
              <i class="bi bi-<?= $c['IsActive'] ? 'pause-circle' : 'play-circle' ?>"></i>
            </a>
            <a href="index.php?delete=<?= $c['id'] ?>"
               class="btn btn-sm btn-outline-danger"
               onclick="return confirm('Delete this credential?')">
              <i class="bi bi-trash"></i>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($creds)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No credentials yet. <a href="add.php">Add one</a> to start fetching punch data.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$extraJs = <<<'JS'
<script>
$(()=>{
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
