<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db  = getDb();
$uid = (int)currentUser()['id'];

$db->exec("CREATE TABLE IF NOT EXISTS tblDashboardLinks (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    UserId    INT          NOT NULL,
    Title     VARCHAR(100) NOT NULL,
    URL       VARCHAR(500) NOT NULL,
    SortOrder INT          DEFAULT 0,
    CreatedAt TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (UserId)
)");

$msg = ''; $msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id    = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $url   = trim($_POST['url']   ?? '');
        if (!$title || !$url) { $msg = 'Title and URL are required.'; $msgType = 'danger'; }
        elseif (!filter_var($url, FILTER_VALIDATE_URL)) { $msg = 'Please enter a valid URL (include https://).'; $msgType = 'danger'; }
        else {
            if ($id) {
                $db->prepare("UPDATE tblDashboardLinks SET Title=?, URL=? WHERE id=? AND UserId=?")
                   ->execute([$title, $url, $id, $uid]);
                $msg = 'Link updated.';
            } else {
                $db->prepare("INSERT INTO tblDashboardLinks (UserId,Title,URL) VALUES (?,?,?)")
                   ->execute([$uid, $title, $url]);
                $msg = 'Link added.';
            }
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM tblDashboardLinks WHERE id=? AND UserId=?")->execute([$id, $uid]);
        $msg = 'Link removed.';
    }
}

$editId  = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId) {
    $s = $db->prepare("SELECT * FROM tblDashboardLinks WHERE id=? AND UserId=?");
    $s->execute([$editId, $uid]);
    $editRow = $s->fetch() ?: null;
}

$links = $db->prepare("SELECT * FROM tblDashboardLinks WHERE UserId=? ORDER BY SortOrder, id");
$links->execute([$uid]);
$links = $links->fetchAll(PDO::FETCH_ASSOC);

$pageTitle  = 'Manage Quick Links';
$activePage = 'dashboard';
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> py-2"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="row g-3">

  <!-- Form -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold" style="font-size:13px">
        <?= $editRow ? 'Edit Link' : 'Add New Link' ?>
      </div>
      <div class="card-body">
        <form method="POST" action="index.php<?= $editRow ? '?edit='.$editRow['id'] : '' ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="id" value="<?= $editRow ? $editRow['id'] : 0 ?>">
          <div class="mb-3">
            <label class="form-label form-label-sm">Label <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control form-control-sm" required
                   value="<?= htmlspecialchars($editRow['Title'] ?? '') ?>"
                   placeholder="e.g. Payroll Run">
          </div>
          <div class="mb-3">
            <label class="form-label form-label-sm">URL <span class="text-danger">*</span></label>
            <input type="url" name="url" class="form-control form-control-sm" required
                   value="<?= htmlspecialchars($editRow['URL'] ?? '') ?>"
                   placeholder="https://...">
          </div>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-floppy me-1"></i><?= $editRow ? 'Update' : 'Add Link' ?>
            </button>
            <?php if ($editRow): ?>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- List -->
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold" style="font-size:13px">Your Quick Links</span>
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
      </div>
      <div class="card-body p-0">
        <?php if (empty($links)): ?>
        <p class="text-muted small p-3 mb-0">No links yet. Add your first one using the form.</p>
        <?php else: ?>
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Label</th>
              <th>URL</th>
              <th style="width:100px"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($links as $lk): ?>
            <tr class="<?= $lk['id'] == $editId ? 'table-primary' : '' ?>">
              <td class="fw-medium"><?= htmlspecialchars($lk['Title']) ?></td>
              <td class="text-muted small" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                <a href="<?= htmlspecialchars($lk['URL']) ?>" target="_blank" class="text-decoration-none">
                  <?= htmlspecialchars($lk['URL']) ?>
                </a>
              </td>
              <td>
                <div class="d-flex gap-1">
                  <a href="index.php?edit=<?= $lk['id'] ?>" class="btn btn-xs btn-outline-primary">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <form method="POST" onsubmit="return confirm('Remove this link?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $lk['id'] ?>">
                    <button type="submit" class="btn btn-xs btn-outline-danger">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </div>
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

<style>
.btn-xs { padding: 2px 7px; font-size: 12px; }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
