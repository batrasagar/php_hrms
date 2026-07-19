<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireUserAdmin();
$editId = (int)($_GET['edit'] ?? 0);

$db      = getDb();
$user    = currentUser();
$errors  = [];
$row     = ['Name' => '', 'Email' => '', 'Role' => 'user', 'IsActive' => 1, 'CompanyId' => 0, 'ParentAdminId' => 0];

// Companies this admin owns (for user-role company assignment)
if ($user['role'] === 'superadmin') {
    $companies = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['id']]);
    $companies = $stmt->fetchAll();
}

// Admins a superadmin can attach an operator to (an operator manages this admin's companies).
$admins = [];
if ($user['role'] === 'superadmin') {
    $admins = $db->query("SELECT id, Name FROM tblUser WHERE Role='admin' AND IsActive=1 ORDER BY Name")->fetchAll();
}

// Permission roles this manager may assign (M033; optional restricting layer)
$permRoles = [];
$curPermRole = 0;
try {
    // Roles are per company (M035): offer only those belonging to the company being
    // worked in, plus company-agnostic ones. Without this an admin managing several
    // companies could hand a user a role built for a different tenant.
    $roleCompany = activeCompanyId($db, $user);
    if ($user['role'] === 'superadmin') {
        $permRoles = $db->query(
            "SELECT id, Name, CompanyId FROM tblRole WHERE IsActive=1 ORDER BY Name"
        )->fetchAll();
    } else {
        $pr = $db->prepare(
            "SELECT id, Name, CompanyId FROM tblRole
              WHERE IsActive=1 AND (OwnerAdminId=? OR OwnerAdminId IS NULL)
              ORDER BY Name"
        );
        $pr->execute([$user['id']]);
        $permRoles = $pr->fetchAll();
        if ($roleCompany) {
            $permRoles = array_values(array_filter(
                $permRoles,
                fn($r) => $r['CompanyId'] === null || (int)$r['CompanyId'] === (int)$roleCompany
            ));
        }
    }
    if ($editId) {
        $cr = $db->prepare("SELECT RoleId FROM tblUserRole WHERE UserId=? LIMIT 1");
        $cr->execute([$editId]);
        $curPermRole = (int)$cr->fetchColumn();
    }
} catch (Throwable $exR) { /* migration pending */ }

if ($editId) {
    $stmt = $db->prepare("SELECT id, Name, Email, Role, IsActive, CompanyId, ParentAdminId FROM tblUser WHERE id=?");
    $stmt->execute([$editId]);
    $row = $stmt->fetch() ?: $row;
    // Admin can only edit users they created
    if ($user['role'] !== 'superadmin' && (int)($row['ParentAdminId'] ?? 0) !== $user['id']) {
        header('Location: list.php'); exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $name      = trim($_POST['Name']      ?? '');
    $email     = trim($_POST['Email']     ?? '');
    $companyId = (int)($_POST['CompanyId'] ?? 0);
    $isActive  = isset($_POST['IsActive']) ? 1 : 0;
    $pass      = $_POST['Password']  ?? '';
    $pass2     = $_POST['Password2'] ?? '';

    // Role. Superadmin: user/admin/operator. Admin: user/operator. Others: user only.
    // Operators are co-admins scoped to their parent admin's companies (see currentUser()).
    $role = 'user';
    if ($user['role'] === 'superadmin') {
        $role = in_array($_POST['Role'] ?? '', ['admin', 'user', 'operator', 'compliance'], true) ? $_POST['Role'] : 'user';
    } elseif ($user['role'] === 'admin') {
        $role = in_array($_POST['Role'] ?? '', ['user', 'operator', 'compliance'], true) ? $_POST['Role'] : 'user';
    }

    // Parent admin an operator/compliance user works under. Superadmin picks it; an admin is always the parent.
    $parentAdminSel = (int)($_POST['ParentAdminId'] ?? 0);
    if (in_array($role, ['operator','compliance'], true)) $companyId = 0; // co-admins span all of the admin's companies

    if (!$name)  $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (!$editId && !$pass) $errors[] = 'Password is required for new users.';
    if ($pass && $pass !== $pass2) $errors[] = 'Passwords do not match.';
    if ($pass && strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($role === 'user' && !$companyId) $errors[] = 'Please select a company for this user.';
    if (in_array($role, ['operator','compliance'], true) && $user['role'] === 'superadmin' && !$parentAdminSel) {
        $errors[] = 'Please select the parent admin this ' . $role . ' will work under.';
    }

    // Validate company belongs to this admin
    if ($companyId && $user['role'] !== 'superadmin') {
        $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $chk->execute([$companyId, $user['id']]);
        if (!$chk->fetch()) { $errors[] = 'Invalid company selected.'; $companyId = 0; }
    }

    // Resolve the parent admin. An operator created by an admin belongs to that admin;
    // one created by a superadmin belongs to the selected admin. Admins/users have none.
    if (in_array($role, ['operator','compliance'], true)) {
        $parentId = $user['role'] === 'superadmin' ? $parentAdminSel : $user['id'];
    } else {
        $parentId = $user['role'] === 'superadmin' ? 0 : $user['id'];
    }

    if (!$errors) {
        if ($editId) {
            $cols   = "Name=?, Email=?, Role=?, IsActive=?, CompanyId=?";
            $params = [$name, $email, $role, $isActive, $companyId ?: null];
            // Let a superadmin (re)assign the operator's parent admin on edit.
            if (in_array($role, ['operator','compliance'], true) && $user['role'] === 'superadmin') {
                $cols    .= ", ParentAdminId=?";
                $params[] = $parentId ?: (int)($row['ParentAdminId'] ?? 0);
            }
            if ($pass) { $cols .= ", Password=?"; $params[] = password_hash($pass, PASSWORD_DEFAULT); }
            $params[] = $editId;
            $sql = "UPDATE tblUser SET $cols WHERE id=?";
        } else {
            $sql    = "INSERT INTO tblUser (Name, Email, Password, Role, IsActive, CompanyId, ParentAdminId, Status)
                       VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
            $params = [$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role, $isActive, $companyId ?: null, $parentId];
        }
        try {
            $db->prepare($sql)->execute($params);
            $savedId = $editId ?: (int)$db->lastInsertId();
            // Sync the optional permission role (only meaningful for non-admin roles)
            $permRoleSel = (int)($_POST['PermRoleId'] ?? 0);
            if ($role === 'admin') $permRoleSel = 0;
            if ($permRoleSel && !in_array($permRoleSel, array_map(fn($r) => (int)$r['id'], $permRoles), true)) $permRoleSel = 0;
            try {
                $db->prepare("DELETE FROM tblUserRole WHERE UserId=?")->execute([$savedId]);
                if ($permRoleSel) $db->prepare("INSERT INTO tblUserRole (UserId, RoleId) VALUES (?,?)")->execute([$savedId, $permRoleSel]);
            } catch (Throwable $exR) { /* migration pending */ }

            // Company ownership for an admin (superadmin only). Ticked companies move to
            // this admin; ones previously theirs but now unticked return to the acting
            // superadmin rather than being left orphaned with a dangling AdminId.
            if ($user['role'] === 'superadmin' && $role === 'admin' && isset($_POST['owned_companies'])) {
                $want = array_map('intval', (array)$_POST['owned_companies']);
                $cur  = $db->prepare("SELECT id FROM tblCompany WHERE AdminId=?");
                $cur->execute([$savedId]);
                $have = array_map('intval', $cur->fetchAll(PDO::FETCH_COLUMN));

                $give = array_diff($want, $have);
                $take = array_diff($have, $want);
                $upd  = $db->prepare("UPDATE tblCompany SET AdminId=?, UpdatedAt=NOW() WHERE id=?");
                foreach ($give as $cid) $upd->execute([$savedId, $cid]);
                foreach ($take as $cid) $upd->execute([(int)$user['id'], $cid]);
            }

            $_SESSION['flash'] = $editId ? 'User updated.' : 'User created.';
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>'list.php']); exit; }
            header('Location: list.php'); exit;
        } catch (\PDOException $e) {
            $errors[] = str_contains($e->getMessage(), 'Duplicate') ? 'Email already exists.' : 'DB error: ' . $e->getMessage();
        }
    }
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>$errors]); exit; }
    $row = ['Name' => $name, 'Email' => $email, 'Role' => $role, 'IsActive' => $isActive, 'CompanyId' => $companyId, 'ParentAdminId' => $parentAdminSel];
}

$pageTitle  = $editId ? 'Edit User' : 'Add User';
$activePage = 'users';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo '<li>' . htmlspecialchars($e) . '</li>'; ?></ul></div>
<?php endif; ?>
<div class="card" style="max-width:520px">
  <div class="card-body">
    <form method="POST" autocomplete="off" data-ajax>
      <div class="mb-3">
        <label class="form-label">Name <span class="text-danger">*</span></label>
        <input type="text" name="Name" class="form-control" value="<?= htmlspecialchars($row['Name']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" name="Email" class="form-control" value="<?= htmlspecialchars($row['Email']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Password <?= $editId ? '(leave blank to keep)' : '<span class="text-danger">*</span>' ?></label>
        <input type="password" name="Password" class="form-control" <?= $editId ? '' : 'required' ?> minlength="6">
      </div>
      <div class="mb-3">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="Password2" class="form-control">
      </div>
      <?php if ($user['role'] === 'superadmin'): ?>
      <div class="mb-3">
        <label class="form-label">Role</label>
        <select name="Role" class="form-select" id="roleSelect" onchange="toggleRole()">
          <option value="user"     <?= ($row['Role'] ?? 'user') === 'user'     ? 'selected' : '' ?>>User</option>
          <option value="admin"      <?= ($row['Role'] ?? '') === 'admin'      ? 'selected' : '' ?>>Admin</option>
          <option value="operator"   <?= ($row['Role'] ?? '') === 'operator'   ? 'selected' : '' ?>>Operator</option>
          <option value="compliance" <?= ($row['Role'] ?? '') === 'compliance' ? 'selected' : '' ?>>Compliance</option>
        </select>
      </div>
      <?php elseif ($user['role'] === 'admin'): ?>
      <div class="mb-3">
        <label class="form-label">Role</label>
        <select name="Role" class="form-select" id="roleSelect" onchange="toggleRole()">
          <option value="user"       <?= ($row['Role'] ?? 'user') === 'user'       ? 'selected' : '' ?>>User</option>
          <option value="operator"   <?= ($row['Role'] ?? '') === 'operator'   ? 'selected' : '' ?>>Operator</option>
          <option value="compliance" <?= ($row['Role'] ?? '') === 'compliance' ? 'selected' : '' ?>>Compliance</option>
        </select>
        <div class="form-text">An operator can do everything in your companies except manage users. A compliance user sees only compliance employees &amp; reports.</div>
      </div>
      <?php else: ?>
      <input type="hidden" name="Role" value="user">
      <?php endif; ?>
      <?php if ($user['role'] === 'superadmin'): ?>
      <div class="mb-3" id="parentAdminRow" <?= in_array(($row['Role'] ?? ''), ['operator','compliance'], true) ? '' : 'style="display:none"' ?>>
        <label class="form-label">Parent Admin <span class="text-danger">*</span></label>
        <select name="ParentAdminId" class="form-select">
          <option value="">— Select Admin —</option>
          <?php foreach ($admins as $a): ?>
          <option value="<?= $a['id'] ?>" <?= (int)($row['ParentAdminId'] ?? 0) === $a['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($a['Name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">The operator will manage all companies under this admin.</div>
      </div>
      <?php endif; ?>
      <div class="mb-3" id="companyRow" <?= (($row['Role'] ?? 'user') === 'user') ? '' : 'style="display:none"' ?>>
        <label class="form-label">Assign Company <span class="text-danger">*</span></label>
        <select name="CompanyId" class="form-select">
          <option value="">— Select Company —</option>
          <?php foreach ($companies as $c): ?>
          <option value="<?= $c['id'] ?>" <?= (int)($row['CompanyId'] ?? 0) === $c['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($c['Name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">The user will only see data for this company.</div>
      </div>
      <?php if ($user['role'] === 'superadmin'): ?>
      <?php
        // An admin's company scope is ownership (tblCompany.AdminId), not tblUser.CompanyId
        // — that column only applies to the 'user' role. Without this control the only way
        // to give an admin a company was to edit the company and retype its owner's email.
        $ownedIds = [];
        if ($editId) {
            $oc = $db->prepare("SELECT id FROM tblCompany WHERE AdminId=?");
            $oc->execute([$editId]);
            $ownedIds = array_map('intval', $oc->fetchAll(PDO::FETCH_COLUMN));
        }
        $allCos = $db->query("SELECT c.id, c.Name, c.AdminId, u.Name AS OwnerName
                                FROM tblCompany c LEFT JOIN tblUser u ON u.id = c.AdminId
                               ORDER BY c.Name")->fetchAll();
      ?>
      <div class="mb-3" id="ownedCompaniesRow" <?= (($row['Role'] ?? 'user') === 'admin') ? '' : 'style="display:none"' ?>>
        <label class="form-label">Owned Companies</label>
        <div class="border rounded p-2" style="max-height:220px;overflow-y:auto">
          <?php foreach ($allCos as $c):
              $mine  = in_array((int)$c['id'], $ownedIds, true);
              $other = !$mine && $c['AdminId']; ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="owned_companies[]"
                   value="<?= (int)$c['id'] ?>" id="oc<?= (int)$c['id'] ?>" <?= $mine ? 'checked' : '' ?>>
            <label class="form-check-label" for="oc<?= (int)$c['id'] ?>">
              <?= htmlspecialchars($c['Name']) ?>
              <?php if ($other): ?>
              <span class="text-muted small">— currently <?= htmlspecialchars($c['OwnerName'] ?? 'unassigned') ?></span>
              <?php endif; ?>
            </label>
          </div>
          <?php endforeach; ?>
          <?php if (!$allCos): ?><div class="text-muted small">No companies yet.</div><?php endif; ?>
        </div>
        <div class="form-text">
          Companies this admin owns. Ticking one moves it to this admin; unticking returns it to you.
          This is what the topbar company switcher and every report scope to.
        </div>
      </div>
      <?php endif; ?>
      <?php if ($permRoles): ?>
      <div class="mb-3" id="permRoleRow" <?= (($row['Role'] ?? 'user') === 'admin') ? 'style="display:none"' : '' ?>>
        <label class="form-label">Permission Role <span class="text-muted">(optional)</span></label>
        <select name="PermRoleId" class="form-select">
          <option value="">— Default access for this role —</option>
          <?php foreach ($permRoles as $pr): ?>
          <option value="<?= $pr['id'] ?>" <?= $curPermRole === (int)$pr['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pr['Name']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Restricts which modules this account can access. Manage roles on the
          <a href="<?= BASE_URL ?>/modules/roles/index.php" target="_blank">Roles &amp; Permissions</a> page.</div>
      </div>
      <?php endif; ?>
      <div class="mb-3 form-check">
        <input type="checkbox" name="IsActive" class="form-check-input" id="chkActive" <?= $row['IsActive'] ? 'checked' : '' ?>>
        <label class="form-check-label" for="chkActive">Active</label>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $editId ? 'Update' : 'Create' ?> User</button>
        <a href="list.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<script>
function toggleRole() {
  var role = document.getElementById('roleSelect')?.value ?? 'user';
  var companyRow = document.getElementById('companyRow');
  var parentRow  = document.getElementById('parentAdminRow');
  if (companyRow) companyRow.style.display = (role === 'user')     ? '' : 'none';
  if (parentRow)  parentRow.style.display  = (role === 'operator' || role === 'compliance') ? '' : 'none';
  var permRow = document.getElementById('permRoleRow');
  if (permRow) permRow.style.display = (role === 'admin') ? 'none' : '';
  // Company ownership is how an admin gets its scope — only meaningful for that role.
  var ownedRow = document.getElementById('ownedCompaniesRow');
  if (ownedRow) ownedRow.style.display = (role === 'admin') ? '' : 'none';
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
