<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
$editId = (int)($_GET['edit'] ?? 0);

$db      = getDb();
$user    = currentUser();
$errors  = [];
$row     = ['Name' => '', 'Email' => '', 'Role' => 'user', 'IsActive' => 1, 'CompanyId' => 0];

// Companies this admin owns (for user-role company assignment)
if ($user['role'] === 'superadmin') {
    $companies = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['id']]);
    $companies = $stmt->fetchAll();
}

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

    // Role: admin can only create user-role accounts
    $role = 'user';
    if ($user['role'] === 'superadmin') {
        $role = in_array($_POST['Role'] ?? '', ['admin', 'user']) ? $_POST['Role'] : 'user';
    }

    if (!$name)  $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (!$editId && !$pass) $errors[] = 'Password is required for new users.';
    if ($pass && $pass !== $pass2) $errors[] = 'Passwords do not match.';
    if ($pass && strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($role === 'user' && !$companyId) $errors[] = 'Please select a company for this user.';

    // Validate company belongs to this admin
    if ($companyId && $user['role'] !== 'superadmin') {
        $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $chk->execute([$companyId, $user['id']]);
        if (!$chk->fetch()) { $errors[] = 'Invalid company selected.'; $companyId = 0; }
    }

    if (!$errors) {
        if ($editId) {
            $sql    = "UPDATE tblUser SET Name=?, Email=?, Role=?, IsActive=?, CompanyId=? WHERE id=?";
            $params = [$name, $email, $role, $isActive, $companyId ?: null, $editId];
            if ($pass) {
                $sql    = "UPDATE tblUser SET Name=?, Email=?, Role=?, IsActive=?, CompanyId=?, Password=? WHERE id=?";
                $params = [$name, $email, $role, $isActive, $companyId ?: null, password_hash($pass, PASSWORD_DEFAULT), $editId];
            }
        } else {
            $parentId = $user['role'] === 'superadmin' ? 0 : $user['id'];
            $sql    = "INSERT INTO tblUser (Name, Email, Password, Role, IsActive, CompanyId, ParentAdminId, Status)
                       VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
            $params = [$name, $email, password_hash($pass, PASSWORD_DEFAULT), $role, $isActive, $companyId ?: null, $parentId];
        }
        try {
            $db->prepare($sql)->execute($params);
            $_SESSION['flash'] = $editId ? 'User updated.' : 'User created.';
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>'list.php']); exit; }
            header('Location: list.php'); exit;
        } catch (\PDOException $e) {
            $errors[] = str_contains($e->getMessage(), 'Duplicate') ? 'Email already exists.' : 'DB error: ' . $e->getMessage();
        }
    }
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>$errors]); exit; }
    $row = ['Name' => $name, 'Email' => $email, 'Role' => $role, 'IsActive' => $isActive, 'CompanyId' => $companyId];
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
        <select name="Role" class="form-select" id="roleSelect" onchange="toggleCompany()">
          <option value="user"  <?= ($row['Role'] ?? 'user') === 'user'  ? 'selected' : '' ?>>User</option>
          <option value="admin" <?= ($row['Role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
      </div>
      <?php else: ?>
      <input type="hidden" name="Role" value="user">
      <?php endif; ?>
      <div class="mb-3" id="companyRow" <?= (($row['Role'] ?? 'user') === 'admin') ? 'style="display:none"' : '' ?>>
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
function toggleCompany() {
  var role = document.getElementById('roleSelect')?.value ?? 'user';
  document.getElementById('companyRow').style.display = role === 'admin' ? 'none' : '';
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
