<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/_shared.php';
requireLogin();

$db   = getDb();
$user = currentUser();

try { $db->query("SELECT 1 FROM tblDevIssue LIMIT 1"); }
catch (PDOException $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

$isSuper   = $user['role'] === 'superadmin';
$scopeIds  = di_scope_ids($db, $user);
$companies = di_scope_companies($db, $user);
$editId    = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$errors    = [];

$rec = ['CompanyId'=>0,'Url'=>'','Detail'=>'','Expected'=>'','AiPrompt'=>'','Status'=>'PENDING','Snapshot'=>''];

// Default company for non-superadmin
if ($user['role'] === 'user')                  $rec['CompanyId'] = (int)($user['company_id'] ?? 0);
elseif (!$isSuper && count($companies) === 1)   $rec['CompanyId'] = (int)$companies[0]['id'];

// Load existing (scope-checked)
if ($editId) {
    $s = $db->prepare("SELECT * FROM tblDevIssue WHERE id=?");
    $s->execute([$editId]);
    $f = $s->fetch();
    if (!$f || !di_can_access($scopeIds, (int)$f['CompanyId'])) { header('Location: index.php'); exit; }
    $rec = $f;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Resolve company
    if ($user['role'] === 'user') {
        $companyId = (int)($user['company_id'] ?? 0);
    } else {
        $companyId = (int)($_POST['company_id'] ?? 0);
        if (!di_can_access($scopeIds, $companyId)) $companyId = 0;
    }

    $url      = trim($_POST['url'] ?? '');
    $detail   = trim($_POST['detail'] ?? '');
    $expected = trim($_POST['expected'] ?? '');
    $aiPrompt = trim($_POST['ai_prompt'] ?? '');
    $status   = $_POST['status'] ?? 'PENDING';
    $allowed  = $isSuper ? DI_STATUSES : DI_USER_STATUSES;
    if (!in_array($status, $allowed, true)) $status = $editId ? $rec['Status'] : 'PENDING';

    if (!$companyId) $errors[] = 'Please select a company.';
    if ($detail === '') $errors[] = 'Detail is required.';

    // Snapshot upload
    $snapshot = $rec['Snapshot'];
    if (!empty($_FILES['snapshot']['tmp_name']) && is_uploaded_file($_FILES['snapshot']['tmp_name'])) {
        $info = @getimagesize($_FILES['snapshot']['tmp_name']);
        if (!$info) { $errors[] = 'Snapshot must be a valid image.'; }
        elseif ($_FILES['snapshot']['size'] > 5 * 1024 * 1024) { $errors[] = 'Snapshot must be under 5 MB.'; }
        else {
            $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'][$info['mime']] ?? 'jpg';
            $dir = __DIR__ . '/../../uploads/dev-issues/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $fn = uniqid('di_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES['snapshot']['tmp_name'], $dir . $fn)) {
                if ($snapshot && file_exists($dir . $snapshot)) @unlink($dir . $snapshot);
                $snapshot = $fn;
            }
        }
    } elseif (!empty($_POST['remove_snapshot']) && $snapshot) {
        @unlink(__DIR__ . '/../../uploads/dev-issues/' . $snapshot);
        $snapshot = '';
    }

    if (!$errors) {
        // ClosedAt logic
        $closedAt = $rec['ClosedAt'] ?? null;
        if (in_array($status, DI_CLOSED, true)) { if (!$closedAt) $closedAt = date('Y-m-d H:i:s'); }
        else $closedAt = null;

        if ($editId) {
            $db->prepare("UPDATE tblDevIssue SET CompanyId=?, Url=?, Detail=?, Expected=?, AiPrompt=?, Status=?, Snapshot=?, ClosedAt=?, UpdatedAt=NOW() WHERE id=?")
               ->execute([$companyId, $url ?: null, $detail, $expected ?: null, $aiPrompt ?: null, $status, $snapshot ?: null, $closedAt, $editId]);
            $_SESSION['flash'] = 'Issue updated.';
        } else {
            $db->prepare("INSERT INTO tblDevIssue (CompanyId, Url, Detail, Expected, AiPrompt, Status, Snapshot, ClosedAt, CreatedBy) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([$companyId, $url ?: null, $detail, $expected ?: null, $aiPrompt ?: null, $status, $snapshot ?: null, $closedAt, $user['id']]);
            $_SESSION['flash'] = 'Issue reported.';
        }
        header('Location: index.php'); exit;
    }
    // Keep entered values on error
    $rec = array_merge($rec, ['CompanyId'=>$companyId,'Url'=>$url,'Detail'=>$detail,'Expected'=>$expected,'AiPrompt'=>$aiPrompt,'Status'=>$status,'Snapshot'=>$snapshot]);
}

$statusOptions = $isSuper ? DI_STATUSES : DI_USER_STATUSES;
if (!in_array($rec['Status'], $statusOptions, true)) $statusOptions[] = $rec['Status'];

$pageTitle  = 'Development Issues';
$activePage = 'dev_issues';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0"><?= $editId ? 'Edit Issue #' . $editId : 'New Development Issue' ?></h5>
  <a href="index.php" class="btn btn-outline-secondary btn-sm">← Back</a>
</div>

<div class="card border-0 shadow-sm" style="max-width:820px">
  <div class="card-body">
    <?php foreach ($errors as $e): ?><div class="alert alert-danger py-2" data-no-toast><?= htmlspecialchars($e) ?></div><?php endforeach; ?>

    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <?php if ($editId): ?><input type="hidden" name="id" value="<?= $editId ?>"><?php endif; ?>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Company <span class="text-danger">*</span></label>
          <?php if ($user['role'] === 'user'): ?>
            <input type="text" class="form-control" value="<?= htmlspecialchars($companies[0]['Name'] ?? '') ?>" disabled>
          <?php else: ?>
            <select name="company_id" class="form-select" required>
              <option value="">— Select —</option>
              <?php foreach ($companies as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $rec['CompanyId']==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <?php foreach ($statusOptions as $s): ?>
            <option value="<?= $s ?>" <?= $rec['Status']===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label">URL</label>
          <input type="text" name="url" class="form-control" value="<?= htmlspecialchars($rec['Url'] ?? '') ?>" placeholder="https://… page where the issue occurs">
        </div>

        <div class="col-12">
          <label class="form-label">Detail <span class="text-danger">*</span></label>
          <textarea name="detail" class="form-control" rows="4" required placeholder="What is the problem / what happened?"><?= htmlspecialchars($rec['Detail'] ?? '') ?></textarea>
        </div>

        <div class="col-12">
          <label class="form-label">Expected</label>
          <textarea name="expected" class="form-control" rows="3" placeholder="What did you expect to happen instead?"><?= htmlspecialchars($rec['Expected'] ?? '') ?></textarea>
        </div>

        <div class="col-12">
          <label class="form-label">AI Fix Prompt <small class="text-muted">(optional)</small></label>
          <textarea name="ai_prompt" class="form-control" rows="3" placeholder="A prompt describing the fix for an AI assistant"><?= htmlspecialchars($rec['AiPrompt'] ?? '') ?></textarea>
        </div>

        <div class="col-12">
          <label class="form-label">Snapshot</label>
          <input type="file" name="snapshot" accept="image/*" class="form-control">
          <?php if (!empty($rec['Snapshot'])): ?>
          <div class="mt-2 d-flex align-items-center gap-3">
            <a href="<?= BASE_URL ?>/uploads/dev-issues/<?= htmlspecialchars($rec['Snapshot']) ?>" target="_blank">
              <img src="<?= BASE_URL ?>/uploads/dev-issues/<?= htmlspecialchars($rec['Snapshot']) ?>" style="max-width:260px;max-height:180px;border-radius:8px;border:1px solid var(--border)">
            </a>
            <label class="text-danger small"><input type="checkbox" name="remove_snapshot" value="1"> Remove</label>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary"><?= $editId ? 'Update Issue' : 'Create Issue' ?></button>
      </div>
    </form>

    <?php if ($editId && $isSuper && $rec['Status'] !== 'APPROVED'): ?>
    <form method="POST" action="index.php" class="mt-2 text-end">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="approve">
      <input type="hidden" name="id" value="<?= $editId ?>">
      <button class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Approve Issue</button>
    </form>
    <?php elseif ($editId && $rec['Status'] === 'APPROVED'): ?>
    <div class="alert alert-success mt-3 mb-0 py-2"><i class="bi bi-check-circle me-1"></i>This issue is approved.</div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
