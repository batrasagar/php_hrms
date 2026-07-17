<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
requirePermission('departments.view');

$db   = getDb();
$user = currentUser();

try { $db->query("SELECT 1 FROM tblDepartment LIMIT 1"); }
catch (PDOException $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

// Company comes from the global topbar switcher (scope-validated inside the helper)
$fCompany = activeCompanyId($db, $user);

$msg = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$err = '';

// ── POST actions ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fCompany) {
    requirePermission('departments.edit');
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') $err = 'Department name is required.';
        else {
            try {
                $db->prepare("INSERT INTO tblDepartment (CompanyId, Name) VALUES (?,?)")->execute([$fCompany, $name]);
                $_SESSION['flash'] = 'Department added.';
            } catch (PDOException $e) {
                $err = strpos($e->getMessage(), 'Duplicate') !== false ? 'That department already exists.' : 'Could not add department.';
            }
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0); $name = trim($_POST['name'] ?? '');
        if ($id && $name !== '') {
            try {
                $db->prepare("UPDATE tblDepartment SET Name=? WHERE id=? AND CompanyId=?")->execute([$name, $id, $fCompany]);
                $_SESSION['flash'] = 'Department updated.';
            } catch (PDOException $e) { $err = 'Could not update (duplicate name?).'; }
        }
    } elseif ($action === 'toggle') {
        $db->prepare("UPDATE tblDepartment SET IsActive=1-IsActive WHERE id=? AND CompanyId=?")->execute([(int)$_POST['id'], $fCompany]);
        $_SESSION['flash'] = 'Department updated.';
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM tblDepartment WHERE id=? AND CompanyId=?")->execute([(int)$_POST['id'], $fCompany]);
        $_SESSION['flash'] = 'Department deleted.';
    }
    if (!$err) { header("Location: index.php?company=$fCompany"); exit; }
}

$depts = [];
if ($fCompany) {
    $s = $db->prepare("SELECT * FROM tblDepartment WHERE CompanyId=? ORDER BY Name");
    $s->execute([$fCompany]);
    $depts = $s->fetchAll();
}

$pageTitle  = 'Department Master';
$activePage = 'departments';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger" data-no-toast><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <h5 class="mb-0">Department Master</h5>
</div>

<?php if (!$fCompany): ?>
<div class="alert alert-warning">Please select a company.</div>
<?php else: ?>
<div class="row g-3">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">Add Department</div>
      <div class="card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="company" value="<?= $fCompany ?>">
          <div class="mb-3">
            <label class="form-label">Department Name</label>
            <input type="text" name="name" class="form-control" required placeholder="e.g. Production" autofocus>
          </div>
          <button class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Add</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-md-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">Departments <span class="badge bg-secondary"><?= count($depts) ?></span></div>
      <div class="card-body p-0">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light"><tr><th>Name</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($depts as $d): ?>
          <tr class="<?= $d['IsActive']?'':'text-muted' ?>">
            <td>
              <form method="POST" class="d-flex gap-2 align-items-center m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="company" value="<?= $fCompany ?>">
                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                <input type="text" name="name" class="form-control form-control-sm" value="<?= htmlspecialchars($d['Name']) ?>" style="max-width:260px">
                <button class="btn btn-sm btn-outline-secondary" title="Save"><i class="bi bi-check-lg"></i></button>
              </form>
            </td>
            <td><span class="badge bg-<?= $d['IsActive']?'success':'secondary' ?>"><?= $d['IsActive']?'Active':'Inactive' ?></span></td>
            <td class="text-end text-nowrap">
              <form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="company" value="<?= $fCompany ?>"><input type="hidden" name="id" value="<?= $d['id'] ?>">
                <button class="btn btn-sm btn-outline-warning" title="Toggle active"><i class="bi bi-toggle-<?= $d['IsActive']?'on':'off' ?>"></i></button>
              </form>
              <form method="POST" class="d-inline" onsubmit="return confirm('Delete this department?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="company" value="<?= $fCompany ?>"><input type="hidden" name="id" value="<?= $d['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$depts): ?><tr><td colspan="3" class="text-center text-muted py-4">No departments yet. Add one on the left.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
