<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db     = getDb();
$errors = [];

$users = $db->query("SELECT id, Name, Email, Role FROM tblUser WHERE IsActive=1 ORDER BY Name")->fetchAll();

// ── Action ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $label  = trim($_POST['Label']  ?? '');
    $userId = (int)($_POST['UserId'] ?? 0);

    if (!$label)  $errors[] = 'Label is required.';
    if (!$userId) $errors[] = 'User is required.';

    if (!$errors) {
        $rawKey  = bin2hex(random_bytes(32));
        $keyHash = hash('sha256', $rawKey);
        try {
            $db->prepare("INSERT INTO tblApiKeys (Label, UserId, RawKey, KeyHash) VALUES (?, ?, ?, ?)")
               ->execute([$label, $userId, $rawKey, $keyHash]);
            $_SESSION['flash'] = 'API key issued.';
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>'list.php']); exit; }
            header('Location: list.php'); exit;
        } catch (\PDOException $e) {
            $errors[] = 'DB error: ' . $e->getMessage();
        }
    }
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>$errors]); exit; }
}

// ── Page setup ────────────────────────────────────────────────────────────────
$pageTitle  = 'Issue API Key';
$activePage = 'apikeys';
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
        <label class="form-label">Label <span class="text-danger">*</span></label>
        <input type="text" name="Label" class="form-control"
               value="<?= htmlspecialchars($_POST['Label'] ?? '') ?>"
               placeholder="e.g. Sagar's mobile app" required>
        <div class="form-text">Describe what this key is used for.</div>
      </div>
      <div class="mb-3">
        <label class="form-label">User <span class="text-danger">*</span></label>
        <select name="UserId" class="form-select" required>
          <option value="">— select user —</option>
          <?php foreach ($users as $u): ?>
          <option value="<?= $u['id'] ?>"
                  <?= (int)($_POST['UserId'] ?? 0) === $u['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($u['Name']) ?>
            (<?= htmlspecialchars($u['Email']) ?> — <?= htmlspecialchars($u['Role']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Key will authorize access to all devices belonging to this user's companies.</div>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-key me-1"></i>Generate &amp; Issue Key
        </button>
        <a href="list.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
