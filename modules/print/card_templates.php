<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
blockCompliance();

$db   = getDb();
$user = currentUser();

// Table may not exist until M031 runs
try { $db->query("SELECT 1 FROM tblCardTemplate LIMIT 1"); }
catch (Throwable $ex) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

// Company comes from the global topbar switcher
$fCompany = activeCompanyId($db, $user);

// ── POST actions: duplicate / toggle / delete ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fCompany) {
    csrf_verify();
    $act = $_POST['act'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    $own = $db->prepare("SELECT * FROM tblCardTemplate WHERE id=? AND CompanyId=?");
    $own->execute([$id, $fCompany]);
    if ($row = $own->fetch()) {
        if ($act === 'delete') {
            $db->prepare("DELETE FROM tblCardTemplate WHERE id=?")->execute([$id]);
        } elseif ($act === 'toggle') {
            $db->prepare("UPDATE tblCardTemplate SET IsActive=1-IsActive WHERE id=?")->execute([$id]);
        } elseif ($act === 'duplicate') {
            $db->prepare("INSERT INTO tblCardTemplate (CompanyId, Name, WidthMm, HeightMm, Layout) VALUES (?,?,?,?,?)")
               ->execute([$fCompany, $row['Name'] . ' (copy)', $row['WidthMm'], $row['HeightMm'], $row['Layout']]);
        }
    }
    header('Location: card_templates.php');
    exit;
}

$templates = [];
if ($fCompany) {
    $s = $db->prepare("SELECT * FROM tblCardTemplate WHERE CompanyId=? ORDER BY Name");
    $s->execute([$fCompany]);
    $templates = $s->fetchAll();
}

$pageTitle  = 'ID Card Templates';
$activePage = 'card_templates';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Card Templates <span class="text-muted">(<?= count($templates) ?>)</span></span>
    <a href="card_designer.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Template</a>
  </div>
  <div class="card-body p-0">
    <?php if (!$templates): ?>
    <div class="p-4 text-muted">
      No card templates yet for this company — click <strong>New Template</strong> to design one.
      Designed cards are printed from the <a href="index.php">Print / iCard</a> page.
    </div>
    <?php else: ?>
    <table class="table table-sm table-hover mb-0 align-middle">
      <thead class="table-light">
        <tr><th>Name</th><th>Size (mm)</th><th>Sides</th><th>Status</th><th>Updated</th><th class="text-end pe-3">Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($templates as $t):
          $lay = json_decode($t['Layout'] ?? '', true);
          $sides = !empty($lay['back']) ? 'Front + Back' : 'Front';
      ?>
        <tr>
          <td><a href="card_designer.php?id=<?= $t['id'] ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars($t['Name']) ?></a></td>
          <td class="small"><?= (float)$t['WidthMm'] ?> × <?= (float)$t['HeightMm'] ?></td>
          <td class="small"><?= $sides ?></td>
          <td>
            <span class="badge <?= $t['IsActive'] ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>">
              <?= $t['IsActive'] ? 'Active' : 'Inactive' ?>
            </span>
          </td>
          <td class="small text-muted"><?= date('d-M-Y H:i', strtotime($t['UpdatedAt'])) ?></td>
          <td class="text-end pe-2">
            <a class="btn btn-sm btn-outline-primary" href="card_designer.php?id=<?= $t['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
            <a class="btn btn-sm btn-outline-success" target="_blank" href="card_print.php?template_id=<?= $t['id'] ?>&test=1" title="Test print"><i class="bi bi-printer"></i></a>
            <form method="POST" class="d-inline"><?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= $t['id'] ?>">
              <button class="btn btn-sm btn-outline-secondary" name="act" value="duplicate" title="Duplicate"><i class="bi bi-copy"></i></button>
              <button class="btn btn-sm btn-outline-secondary" name="act" value="toggle" title="Toggle active"><i class="bi bi-power"></i></button>
              <button class="btn btn-sm btn-outline-danger" name="act" value="delete" title="Delete"
                      onclick="return confirm('Delete template &quot;<?= htmlspecialchars($t['Name'], ENT_QUOTES) ?>&quot;?')"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
