<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db     = getDb();
$user   = currentUser();
$errors = [];
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$rec    = ['Name'=>'','Address'=>'','Phone'=>'','Email'=>'','IsActive'=>1];

if ($editId) {
    if ($user['role'] === 'superadmin') {
        $q = $db->prepare("SELECT * FROM tblCompany WHERE id=?");
        $q->execute([$editId]);
    } else {
        $q = $db->prepare("SELECT * FROM tblCompany WHERE id=? AND AdminId=?");
        $q->execute([$editId, $user['id']]);
    }
    $fetched = $q->fetch();
    if ($fetched) $rec = $fetched; else { header('Location: index.php'); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $name     = trim($_POST['name']    ?? '');
    $address  = trim($_POST['address'] ?? '');
    $phone    = trim($_POST['phone']   ?? '');
    $email    = trim($_POST['email']   ?? '');
    $isActive = isset($_POST['isactive']) ? 1 : 0;

    if (!$name) $errors[] = 'Company name is required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';

    // Company limit check (only for non-superadmin creating new)
    if (!$errors && !$editId && $user['role'] !== 'superadmin') {
        $limit = $user['company_limit'];
        if ($limit != -1) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM tblCompany WHERE AdminId=?");
            $stmt->execute([$user['id']]);
            $count = (int)$stmt->fetchColumn();
            if ($count >= $limit) {
                $errors[] = "You have reached your company limit ($limit). Contact the administrator to increase it.";
            }
        }
    }

    if (!$errors) {
        if ($editId) {
            if ($user['role'] === 'superadmin') {
                $db->prepare(
                    "UPDATE tblCompany SET Name=?, Address=?, Phone=?, Email=?, IsActive=?, UpdatedAt=NOW() WHERE id=?"
                )->execute([$name, $address ?: null, $phone ?: null, $email ?: null, $isActive, $editId]);
            } else {
                $db->prepare(
                    "UPDATE tblCompany SET Name=?, Address=?, Phone=?, Email=?, IsActive=?, UpdatedAt=NOW() WHERE id=? AND AdminId=?"
                )->execute([$name, $address ?: null, $phone ?: null, $email ?: null, $isActive, $editId, $user['id']]);
            }
        } else {
            $db->prepare(
                "INSERT INTO tblCompany (AdminId, Name, Address, Phone, Email, IsActive)
                 VALUES (?,?,?,?,?,?)"
            )->execute([$user['id'], $name, $address ?: null, $phone ?: null, $email ?: null, $isActive]);
        }
        $_SESSION['flash'] = $editId ? 'Company updated.' : 'Company added.';
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>'index.php']); exit; }
        header('Location: index.php'); exit;
    }

    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>$errors]); exit; }
    $rec = compact('name','address','phone','email','isActive') +
           ['Name'=>$name,'Address'=>$address,'Phone'=>$phone,'Email'=>$email,'IsActive'=>$isActive];
}
$pageTitle  = 'Companies';
$activePage = 'companies';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card border-0 shadow-sm" style="max-width:600px">
  <div class="card-header bg-white fw-semibold"><?= $editId ? 'Edit' : 'Add' ?> Company</div>
  <div class="card-body">
    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <form method="POST" data-ajax>
      <div class="mb-3">
        <label class="form-label">Company Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control"
               value="<?= htmlspecialchars($rec['Name']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Address</label>
        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($rec['Address'] ?? '') ?></textarea>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-sm-6">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($rec['Phone'] ?? '') ?>">
        </div>
        <div class="col-sm-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($rec['Email'] ?? '') ?>">
        </div>
      </div>
      <div class="mb-4">
        <div class="form-check">
          <input type="checkbox" name="isactive" class="form-check-input" id="isactive"
                 <?= ($rec['IsActive'] ?? 1) ? 'checked' : '' ?>>
          <label class="form-check-label" for="isactive">Active</label>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $editId ? 'Save Changes' : 'Add Company' ?></button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
