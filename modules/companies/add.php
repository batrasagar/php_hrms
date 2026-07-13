<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db     = getDb();
$user   = currentUser();
$errors = [];
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$rec    = ['Name'=>'','Address'=>'','Phone'=>'','Email'=>'','IsActive'=>1,'AdminId'=>0,
           'SignImage'=>'','SignName'=>'','SignDesignation'=>''];

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

// Resolve current owner email for superadmin
$ownerEmail = '';
if ($user['role'] === 'superadmin' && !empty($rec['AdminId'])) {
    $ownerEmail = $db->prepare("SELECT Email FROM tblUser WHERE id=?")
                     ->execute([$rec['AdminId']]) ? '' : '';
    $oe = $db->prepare("SELECT Email FROM tblUser WHERE id=?");
    $oe->execute([$rec['AdminId']]);
    $ownerEmail = $oe->fetchColumn() ?: '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $name       = trim($_POST['name']        ?? '');
    $address    = trim($_POST['address']     ?? '');
    $phone      = trim($_POST['phone']       ?? '');
    $email      = trim($_POST['email']       ?? '');
    $ownerEmail = trim($_POST['owner_email'] ?? '');
    $isActive   = isset($_POST['isactive']) ? 1 : 0;
    $signName   = trim($_POST['sign_name']        ?? '');
    $signDesig  = trim($_POST['sign_designation'] ?? '');

    if (!$name) $errors[] = 'Company name is required.';
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid company email.';

    // Authorized-signatory signature image (base64 → uploads/company/)
    $signImage = $rec['SignImage'] ?? '';
    $sigDir    = __DIR__ . '/../../uploads/company/';
    if (!empty($_POST['sign_data']) && str_starts_with($_POST['sign_data'], 'data:image/')) {
        $parts = explode(',', $_POST['sign_data'], 2);
        if (count($parts) === 2) {
            $sd = base64_decode($parts[1]);
            if ($sd !== false && strlen($sd) > 100) {
                if (!is_dir($sigDir)) mkdir($sigDir, 0755, true);
                $newSig = uniqid('cosig_', true) . '.png';
                file_put_contents($sigDir . $newSig, $sd);
                if ($signImage && file_exists($sigDir . $signImage)) unlink($sigDir . $signImage);
                $signImage = $newSig;
            }
        }
    } elseif (!empty($_POST['sign_clear'])) {
        if ($signImage && file_exists($sigDir . $signImage)) unlink($sigDir . $signImage);
        $signImage = '';
    }

    // Resolve AdminId from owner_email (superadmin only)
    $newAdminId = null;
    if ($user['role'] === 'superadmin') {
        if ($ownerEmail) {
            if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid owner email address.';
            } else {
                $os = $db->prepare("SELECT id FROM tblUser WHERE Email=? AND Role IN ('admin','superadmin') AND IsActive=1");
                $os->execute([$ownerEmail]);
                $newAdminId = $os->fetchColumn();
                if (!$newAdminId) $errors[] = 'Owner email not found or is not an active admin account.';
            }
        } else {
            // Blank on edit → keep current owner; blank on create → use superadmin's own id
            $newAdminId = $editId ? (int)$rec['AdminId'] : $user['id'];
        }
    }

    // Company limit check (only for non-superadmin creating new)
    if (!$errors && !$editId && $user['role'] !== 'superadmin') {
        $limit = $user['company_limit'];
        if ($limit != -1) {
            $cnt = $db->prepare("SELECT COUNT(*) FROM tblCompany WHERE AdminId=?");
            $cnt->execute([$user['id']]);
            if ((int)$cnt->fetchColumn() >= $limit)
                $errors[] = "You have reached your company limit ($limit). Contact the administrator.";
        }
    }

    if (!$errors) {
        if ($editId) {
            if ($user['role'] === 'superadmin') {
                $db->prepare(
                    "UPDATE tblCompany SET AdminId=?, Name=?, Address=?, Phone=?, Email=?, IsActive=?,
                       SignImage=?, SignName=?, SignDesignation=?, UpdatedAt=NOW() WHERE id=?"
                )->execute([$newAdminId, $name, $address ?: null, $phone ?: null, $email ?: null, $isActive,
                            $signImage ?: null, $signName ?: null, $signDesig ?: null, $editId]);
            } else {
                $db->prepare(
                    "UPDATE tblCompany SET Name=?, Address=?, Phone=?, Email=?, IsActive=?,
                       SignImage=?, SignName=?, SignDesignation=?, UpdatedAt=NOW() WHERE id=? AND AdminId=?"
                )->execute([$name, $address ?: null, $phone ?: null, $email ?: null, $isActive,
                            $signImage ?: null, $signName ?: null, $signDesig ?: null, $editId, $user['id']]);
            }
        } else {
            $adminId = $user['role'] === 'superadmin' ? $newAdminId : $user['id'];
            $db->prepare(
                "INSERT INTO tblCompany (AdminId, Name, Address, Phone, Email, IsActive, SignImage, SignName, SignDesignation)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            )->execute([$adminId, $name, $address ?: null, $phone ?: null, $email ?: null, $isActive,
                        $signImage ?: null, $signName ?: null, $signDesig ?: null]);
        }
        $_SESSION['flash'] = $editId ? 'Company updated.' : 'Company added.';
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>'index.php']); exit; }
        header('Location: index.php'); exit;
    }

    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>$errors]); exit; }
    $rec = ['Name'=>$name,'Address'=>$address,'Phone'=>$phone,'Email'=>$email,'IsActive'=>$isActive,'AdminId'=>$rec['AdminId'],
            'SignImage'=>$signImage,'SignName'=>$signName,'SignDesignation'=>$signDesig];
}

$pageTitle  = 'Companies';
$activePage = 'companies';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card border-0 shadow-sm" style="max-width:600px">
  <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
    <span><?= $editId ? 'Edit' : 'Add' ?> Company</span>
    <?php if ($editId): ?>
    <a href="<?= BASE_URL ?>/modules/devices/list.php?company=<?= urlencode($rec['Name']) ?>"
       class="btn btn-sm btn-outline-secondary fw-normal">
      <i class="bi bi-hdd-network"></i> Devices
    </a>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <form method="POST" data-ajax>
      <?= csrf_field() ?>
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
          <label class="form-label">Company Email</label>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($rec['Email'] ?? '') ?>">
        </div>
      </div>
      <?php if ($user['role'] === 'superadmin'): ?>
      <div class="mb-3">
        <label class="form-label">Owner Email <span class="text-danger">*</span></label>
        <input type="email" name="owner_email" class="form-control"
               value="<?= htmlspecialchars($ownerEmail) ?>"
               placeholder="admin@example.com">
        <div class="form-text">Email of the admin account this company belongs to.</div>
      </div>
      <?php endif; ?>
      <hr>
      <div class="fw-semibold mb-1">Authorized Signatory <span class="text-muted small fw-normal">(prints on issued documents)</span></div>
      <input type="hidden" name="sign_data" id="signData">
      <input type="hidden" name="sign_clear" id="signClear" value="">
      <div class="row g-3 mb-3">
        <div class="col-sm-6">
          <label class="form-label">Signatory Name</label>
          <input type="text" name="sign_name" class="form-control" value="<?= htmlspecialchars($rec['SignName'] ?? '') ?>" placeholder="e.g. Ramesh Kumar">
        </div>
        <div class="col-sm-6">
          <label class="form-label">Designation</label>
          <input type="text" name="sign_designation" class="form-control" value="<?= htmlspecialchars($rec['SignDesignation'] ?? '') ?>" placeholder="e.g. HR Manager">
        </div>
        <div class="col-12">
          <label class="form-label">Signature Image</label>
          <div class="d-flex align-items-center gap-3">
            <div id="coSigWrap" style="width:200px;height:70px;border:2px dashed #dee2e6;border-radius:8px;overflow:hidden;background:#f8f9fa;display:flex;align-items:center;justify-content:center;padding:4px">
              <?php if (!empty($rec['SignImage'])): ?>
                <img id="coSigPreview" src="<?= BASE_URL ?>/uploads/company/<?= htmlspecialchars($rec['SignImage']) ?>" style="max-width:100%;max-height:100%;object-fit:contain">
              <?php else: ?>
                <span class="text-muted small" id="coSigPlaceholder" style="font-size:11px"><i class="bi bi-vector-pen"></i> Signature</span>
                <img id="coSigPreview" style="max-width:100%;max-height:100%;object-fit:contain;display:none">
              <?php endif; ?>
            </div>
            <div>
              <input type="file" id="coSigInput" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm" style="max-width:220px">
              <button type="button" id="coSigClear" class="btn btn-sm btn-outline-danger mt-2"><i class="bi bi-x-lg"></i> Remove</button>
            </div>
          </div>
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
<script>
(function () {
  var input = document.getElementById('coSigInput');
  var clear = document.getElementById('coSigClear');
  var prev  = document.getElementById('coSigPreview');
  var ph    = document.getElementById('coSigPlaceholder');
  var data  = document.getElementById('signData');
  var flag  = document.getElementById('signClear');
  input.addEventListener('change', function () {
    var file = this.files[0]; if (!file) return;
    var reader = new FileReader();
    reader.onload = function (e) {
      data.value = e.target.result; flag.value = '';
      prev.src = e.target.result; prev.style.display = 'block';
      if (ph) ph.style.display = 'none';
    };
    reader.readAsDataURL(file);
  });
  clear.addEventListener('click', function () {
    data.value = ''; flag.value = '1'; input.value = '';
    prev.src = ''; prev.style.display = 'none';
    if (ph) ph.style.display = 'inline';
  });
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
