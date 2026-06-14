<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireSuperAdmin();

$db     = getDb();
$errors = [];
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$rec    = ['Label' => '', 'Endpoint' => '', 'ApiKey' => '', 'IsActive' => 1];

if ($editId) {
    $r = $db->prepare("SELECT * FROM tblAdmsCredentials WHERE id = ?");
    $r->execute([$editId]);
    $fetched = $r->fetch();
    if ($fetched) $rec = $fetched;
}

// Redirect new-add requests to edit if a credential already exists
if (!$editId) {
    $first = $db->query("SELECT id FROM tblAdmsCredentials LIMIT 1")->fetchColumn();
    if ($first) {
        header('Location: add.php?id=' . (int)$first); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $label    = trim($_POST['label']    ?? '');
    $endpoint = rtrim(trim($_POST['endpoint'] ?? ''), '/');
    $apikey   = trim($_POST['apikey']   ?? '');
    $isActive = isset($_POST['isactive']) ? 1 : 0;

    if (!$label)    $errors[] = 'Label is required.';
    if (!$endpoint) $errors[] = 'Endpoint URL is required.';
    elseif (!filter_var($endpoint, FILTER_VALIDATE_URL)) $errors[] = 'Endpoint must be a valid URL (include https://).';
    if (!$apikey)   $errors[] = 'API Key is required.';

    if (!$errors) {
        if ($editId) {
            $db->prepare("UPDATE tblAdmsCredentials SET Label=?, Endpoint=?, ApiKey=?, IsActive=?, UpdatedAt=NOW() WHERE id=?")
               ->execute([$label, $endpoint, $apikey, $isActive, $editId]);
        } else {
            $db->prepare("INSERT INTO tblAdmsCredentials (Label, Endpoint, ApiKey, IsActive) VALUES (?,?,?,?)")
               ->execute([$label, $endpoint, $apikey, $isActive]);
        }
        $_SESSION['flash'] = $editId ? 'Credential updated.' : 'Credential added.';
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>'index.php']); exit; }
        header('Location: index.php'); exit;
    }

    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>$errors]); exit; }
    $rec = ['Label' => $label, 'Endpoint' => $endpoint, 'ApiKey' => $apikey, 'IsActive' => $isActive];
}
$pageTitle  = 'ADMS Credentials';
$activePage = 'adms';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card border-0 shadow-sm" style="max-width:600px">
  <div class="card-header bg-white fw-semibold">
    <?= $editId ? 'Edit' : 'Add' ?> ADMS Credential
  </div>
  <div class="card-body">
    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger py-2"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <form method="POST" data-ajax>
      <div class="mb-3">
        <label class="form-label">Label <span class="text-danger">*</span></label>
        <input type="text" name="label" class="form-control"
               value="<?= htmlspecialchars($rec['Label']) ?>"
               placeholder="e.g. Head Office AttnLog" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Endpoint URL <span class="text-danger">*</span></label>
        <input type="url" name="endpoint" class="form-control"
               value="<?= htmlspecialchars($rec['Endpoint']) ?>"
               placeholder="https://attnlog.example.com" required>
        <div class="form-text">Base URL of the AttnLog server — no trailing slash.</div>
      </div>
      <div class="mb-3">
        <label class="form-label">API Key <span class="text-danger">*</span></label>
        <div class="input-group">
          <input type="password" name="apikey" id="apikey" class="form-control font-monospace"
                 value="<?= htmlspecialchars($rec['ApiKey']) ?>" required>
          <button type="button" class="btn btn-outline-secondary" id="toggleKey">
            <i class="bi bi-eye"></i>
          </button>
        </div>
        <div class="form-text">The <code>X-Api-Key</code> issued by the AttnLog server.</div>
      </div>
      <div class="mb-4">
        <div class="form-check">
          <input type="checkbox" name="isactive" class="form-check-input" id="isactive"
                 <?= $rec['IsActive'] ? 'checked' : '' ?>>
          <label class="form-check-label" for="isactive">Active</label>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <?= $editId ? 'Save Changes' : 'Add Credential' ?>
        </button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php
$extraJs = <<<'JS'
<script>
$(()=>{
  $('#toggleKey').on('click', function(){
    const inp = $('#apikey')[0];
    const icon = $(this).find('i');
    if(inp.type === 'password'){
      inp.type = 'text';
      icon.removeClass('bi-eye').addClass('bi-eye-slash');
    } else {
      inp.type = 'password';
      icon.removeClass('bi-eye-slash').addClass('bi-eye');
    }
  });
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
