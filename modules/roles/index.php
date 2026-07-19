<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireUserAdmin();   // superadmin + admin (each admin manages roles for their own staff)

$db   = getDb();
$user = currentUser();

try { $db->query("SELECT 1 FROM tblRole LIMIT 1"); }
catch (Throwable $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

$isSuper = $user['role'] === 'superadmin';
// Admin-owned roles; superadmin's own roles are global (OwnerAdminId NULL)
$ownerId = $isSuper ? null : (int)$user['id'];
$msg = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$err = '';

/** Roles visible to the current manager: own + (for admins) global built-ins. */
function roleVisible(array $r, bool $isSuper, int $uid): bool {
    return $isSuper || $r['OwnerAdminId'] === null || (int)$r['OwnerAdminId'] === $uid;
}
/** Roles editable by the current manager (global roles are superadmin-only to edit). */
function roleEditable(array $r, bool $isSuper, int $uid): bool {
    return $isSuper ? true : (int)$r['OwnerAdminId'] === $uid;
}

// ── POST actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $act = $_POST['act'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);

    $row = null;
    if ($id) {
        $s = $db->prepare("SELECT * FROM tblRole WHERE id=?");
        $s->execute([$id]);
        $row = $s->fetch();
        if (!$row || !roleEditable($row, $isSuper, (int)$user['id'])) { header('Location: index.php'); exit; }
    }

    if ($act === 'save') {
        $name  = trim($_POST['name'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $perms = array_values(array_intersect((array)($_POST['perms'] ?? []), permAllNames()));
        // edit implies view
        foreach ($perms as $p) {
            if (str_ends_with($p, '.edit')) {
                $vp = substr($p, 0, -5) . '.view';
                if (in_array($vp, permAllNames(), true) && !in_array($vp, $perms, true)) $perms[] = $vp;
            }
        }
        if ($name === '') $err = 'Role name is required.';
        elseif (!$perms)  $err = 'Select at least one permission.';
        else {
            try {
                if ($row) {
                    $db->prepare("UPDATE tblRole SET Name=?, Description=? WHERE id=?")->execute([$name, $desc, $id]);
                } else {
                    $db->prepare("INSERT INTO tblRole (OwnerAdminId, Name, Description) VALUES (?,?,?)")
                       ->execute([$ownerId, $name, $desc]);
                    $id = (int)$db->lastInsertId();
                }
                $db->prepare("DELETE FROM tblRolePerm WHERE RoleId=?")->execute([$id]);
                $ins = $db->prepare("INSERT IGNORE INTO tblRolePerm (RoleId, Perm) VALUES (?,?)");
                foreach ($perms as $p) $ins->execute([$id, $p]);
                $_SESSION['flash'] = 'Role saved.';
                header('Location: index.php'); exit;
            } catch (PDOException $e) {
                $err = strpos($e->getMessage(), 'Duplicate') !== false ? 'A role with that name already exists.' : 'Could not save role.';
            }
        }
    } elseif ($act === 'toggle' && $row) {
        $db->prepare("UPDATE tblRole SET IsActive=1-IsActive WHERE id=?")->execute([$id]);
        header('Location: index.php'); exit;
    } elseif ($act === 'delete' && $row) {
        $db->prepare("DELETE FROM tblRole WHERE id=?")->execute([$id]);   // FK cascades perms + assignments
        $_SESSION['flash'] = 'Role deleted.';
        header('Location: index.php'); exit;
    }
}

// ── Load data ─────────────────────────────────────────────────────────────────
$editId  = (int)($_GET['id'] ?? 0);
$showNew = isset($_GET['new']) || $editId;

$editRole  = null;
$editPerms = [];
if ($editId) {
    $s = $db->prepare("SELECT * FROM tblRole WHERE id=?");
    $s->execute([$editId]);
    $editRole = $s->fetch();
    if (!$editRole || !roleEditable($editRole, $isSuper, (int)$user['id'])) { header('Location: index.php'); exit; }
    $p = $db->prepare("SELECT Perm FROM tblRolePerm WHERE RoleId=?");
    $p->execute([$editId]);
    $editPerms = $p->fetchAll(PDO::FETCH_COLUMN);
}

$rolesSql = $isSuper
    ? "SELECT r.*, (SELECT COUNT(*) FROM tblUserRole ur WHERE ur.RoleId=r.id) AS Users,
              (SELECT COUNT(*) FROM tblRolePerm rp WHERE rp.RoleId=r.id) AS Perms,
              u.Name AS OwnerName
       FROM tblRole r LEFT JOIN tblUser u ON u.id=r.OwnerAdminId ORDER BY r.Name"
    : "SELECT r.*, (SELECT COUNT(*) FROM tblUserRole ur WHERE ur.RoleId=r.id) AS Users,
              (SELECT COUNT(*) FROM tblRolePerm rp WHERE rp.RoleId=r.id) AS Perms,
              NULL AS OwnerName
       FROM tblRole r WHERE r.OwnerAdminId = " . (int)$user['id'] . " OR r.OwnerAdminId IS NULL ORDER BY r.Name";
$roles = $db->query($rolesSql)->fetchAll();

$pageTitle  = 'Roles & Permissions';
$activePage = 'roles';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger" data-no-toast><?= htmlspecialchars($err) ?></div><?php endif; ?>

<?php if (!$showNew): ?>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Permission Roles <span class="text-muted">(<?= count($roles) ?>)</span></span>
    <a href="index.php?new=1" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Role</a>
  </div>
  <div class="card-body p-0">
    <?php if (!$roles): ?>
    <div class="p-4 text-muted">
      No permission roles yet. Roles restrict what an <b>operator / compliance / user</b> account can access —
      superadmin and admin always have full access, and a staff account with <b>no role</b> keeps its default access.
      Click <strong>New Role</strong>, tick the allowed modules, then assign the role on the
      <a href="<?= BASE_URL ?>/modules/users/list.php">Users</a> page.
    </div>
    <?php else: ?>
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>Role</th><th>Description</th><?php if ($isSuper): ?><th>Owner</th><?php endif; ?>
            <th>Permissions</th><th>Users</th><th>Status</th><th class="text-end pe-3">Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($roles as $r):
          $editable = roleEditable($r, $isSuper, (int)$user['id']); ?>
        <tr class="<?= $r['IsActive'] ? '' : 'text-muted' ?>">
          <td class="fw-semibold">
            <?php if ($editable): ?><a href="index.php?id=<?= $r['id'] ?>" class="text-decoration-none"><?= htmlspecialchars($r['Name']) ?></a>
            <?php else: ?><?= htmlspecialchars($r['Name']) ?> <span class="badge bg-secondary-subtle text-secondary">built-in</span><?php endif; ?>
          </td>
          <td class="small text-muted"><?= htmlspecialchars($r['Description'] ?? '') ?></td>
          <?php if ($isSuper): ?><td class="small"><?= $r['OwnerAdminId'] ? htmlspecialchars($r['OwnerName'] ?? '') : '<span class="badge bg-primary-subtle text-primary">Global</span>' ?></td><?php endif; ?>
          <td><span class="badge bg-light text-dark border"><?= $r['Perms'] ?></span></td>
          <td><span class="badge bg-light text-dark border"><?= $r['Users'] ?></span></td>
          <td><span class="badge <?= $r['IsActive'] ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' ?>"><?= $r['IsActive'] ? 'Active' : 'Inactive' ?></span></td>
          <td class="text-end pe-2">
            <?php if ($editable): ?>
            <a class="btn btn-sm btn-outline-primary" href="index.php?id=<?= $r['id'] ?>" title="Edit"><i class="bi bi-pencil"></i></a>
            <form method="POST" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm btn-outline-warning" name="act" value="toggle" title="Toggle active"><i class="bi bi-power"></i></button>
              <button class="btn btn-sm btn-outline-danger" name="act" value="delete" title="Delete"
                      onclick="return confirm('Delete role &quot;<?= htmlspecialchars($r['Name'], ENT_QUOTES) ?>&quot;? Users assigned to it revert to default access.')"><i class="bi bi-trash"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php else: /* ── Role form with permission matrix ── */ ?>
<form method="POST">
  <?= csrf_field() ?>
  <input type="hidden" name="act" value="save">
  <input type="hidden" name="id" value="<?= $editRole ? (int)$editRole['id'] : 0 ?>">
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-end">
      <div><label class="form-label small mb-1">Role Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control form-control-sm" style="width:220px" required
               value="<?= htmlspecialchars($editRole['Name'] ?? '') ?>" placeholder="e.g. Attendance Operator">
      </div>
      <div style="flex:1;min-width:220px"><label class="form-label small mb-1">Description</label>
        <input type="text" name="description" class="form-control form-control-sm"
               value="<?= htmlspecialchars($editRole['Description'] ?? '') ?>" placeholder="What this role is for">
      </div>
      <button class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Save Role</button>
      <a href="index.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
    </div>
  </div>

  <div class="row g-3">
    <?php foreach (permCatalog() as $group => $modules): ?>
    <div class="col-md-6 col-xl-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
          <span class="fw-semibold" style="font-size:13px"><?= htmlspecialchars($group) ?></span>
          <a href="#" class="small text-decoration-none grp-toggle">all</a>
        </div>
        <div class="card-body py-2">
          <?php if ($group === 'Data Scope'): ?>
          <div class="alert alert-warning py-2 px-2 small mb-2">
            <i class="bi bi-funnel me-1"></i>
            These <strong>restrict which employees</strong> a role can see, rather than which pages it can open.
            Ticking one narrows every list, report and export for anyone holding this role.
          </div>
          <?php endif; ?>
          <table class="table table-sm mb-0 align-middle" style="font-size:12.5px">
            <thead><tr><th></th><th class="text-center" style="width:52px">View</th><th class="text-center" style="width:52px">Edit</th></tr></thead>
            <tbody>
            <?php foreach ($modules as $key => [$label, $actions]): ?>
            <tr>
              <td><?= htmlspecialchars($label) ?></td>
              <?php foreach (['view','edit'] as $a): $pn = "$key.$a"; ?>
              <td class="text-center">
                <?php if (in_array($a, $actions, true)): ?>
                <input type="checkbox" class="form-check-input pm" name="perms[]" value="<?= $pn ?>"
                       data-mod="<?= $key ?>" data-act="<?= $a ?>" <?= in_array($pn, $editPerms, true) ? 'checked' : '' ?>>
                <?php else: ?><span class="text-muted">—</span><?php endif; ?>
              </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="mt-3 d-flex gap-2">
    <button class="btn btn-success btn-sm"><i class="bi bi-check-lg me-1"></i>Save Role</button>
    <a href="index.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
  </div>
</form>
<?php endif; ?>

<?php
$extraJs = <<<'JS'
<script>
// edit implies view; unchecking view clears edit; group "all" toggles everything in the card
document.querySelectorAll('.pm').forEach(cb => cb.addEventListener('change', function(){
  const mod = this.dataset.mod;
  if (this.dataset.act === 'edit' && this.checked) {
    const v = document.querySelector('.pm[data-mod="'+mod+'"][data-act="view"]');
    if (v) v.checked = true;
  }
  if (this.dataset.act === 'view' && !this.checked) {
    const e = document.querySelector('.pm[data-mod="'+mod+'"][data-act="edit"]');
    if (e) e.checked = false;
  }
}));
document.querySelectorAll('.grp-toggle').forEach(a => a.addEventListener('click', function(ev){
  ev.preventDefault();
  const boxes = this.closest('.card').querySelectorAll('.pm');
  const allOn = [...boxes].every(b => b.checked);
  boxes.forEach(b => b.checked = !allOn);
}));
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
