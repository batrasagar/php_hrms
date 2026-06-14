<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db     = getDb();
$user   = currentUser();
$errors = [];
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$rec = ['CompanyId' => 0, 'HolidayDate' => '', 'Name' => '', 'Type' => 'national'];

if ($user['role'] === 'superadmin') {
    $companies = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['id']]);
    $companies = $stmt->fetchAll();
}

if ($editId) {
    if ($user['role'] === 'superadmin') {
        $q = $db->prepare("SELECT * FROM tblHoliday WHERE id=?");
        $q->execute([$editId]);
    } else {
        $q = $db->prepare(
            "SELECT h.* FROM tblHoliday h JOIN tblCompany c ON c.id=h.CompanyId AND c.AdminId=? WHERE h.id=?"
        );
        $q->execute([$user['id'], $editId]);
    }
    $f = $q->fetch();
    if (!$f) { header('Location: index.php'); exit; }
    $rec = $f;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $companyId   = (int)($_POST['company_id']   ?? 0);
    $holidayDate = trim($_POST['holiday_date']  ?? '');
    $name        = trim($_POST['name']          ?? '');
    $type        = trim($_POST['type']          ?? 'national');

    if (!$companyId)   $errors[] = 'Please select a company.';
    if (!$holidayDate) $errors[] = 'Holiday date is required.';
    if (!$name)        $errors[] = 'Holiday name is required.';
    if (!in_array($type, ['national','optional','restricted'])) $type = 'national';

    if (!$errors && $user['role'] !== 'superadmin') {
        $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $chk->execute([$companyId, $user['id']]);
        if (!$chk->fetch()) $errors[] = 'Invalid company.';
    }

    if (!$errors) {
        if ($editId) {
            $db->prepare("UPDATE tblHoliday SET CompanyId=?, HolidayDate=?, Name=?, Type=? WHERE id=?")
               ->execute([$companyId, $holidayDate, $name, $type, $editId]);
            $_SESSION['flash'] = 'Holiday updated.';
        } else {
            try {
                $db->prepare("INSERT INTO tblHoliday (CompanyId, HolidayDate, Name, Type) VALUES (?,?,?,?)")
                   ->execute([$companyId, $holidayDate, $name, $type]);
                $_SESSION['flash'] = 'Holiday added.';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    $errors[] = 'A holiday already exists for this company on ' . $holidayDate;
                } else throw $e;
            }
        }
        if (!$errors) {
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>'index.php']); exit; }
            header('Location: index.php'); exit;
        }
    }
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>$errors]); exit; }
    $rec = ['CompanyId' => $companyId, 'HolidayDate' => $holidayDate, 'Name' => $name, 'Type' => $type];
}
$pageTitle  = 'Holiday Master';
$activePage = 'holidays';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card border-0 shadow-sm" style="max-width:500px">
  <div class="card-header bg-white fw-semibold"><?= $editId ? 'Edit' : 'Add' ?> Holiday</div>
  <div class="card-body">
    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <form method="POST" data-ajax>
      <div class="mb-3">
        <label class="form-label">Company <span class="text-danger">*</span></label>
        <select name="company_id" class="form-select" required>
          <option value="">— Select —</option>
          <?php foreach ($companies as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $rec['CompanyId']==$c['id']?'selected':'' ?>>
            <?= htmlspecialchars($c['Name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Holiday Date <span class="text-danger">*</span></label>
        <input type="date" name="holiday_date" class="form-control" required
               value="<?= htmlspecialchars($rec['HolidayDate']) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Holiday Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required
               value="<?= htmlspecialchars($rec['Name']) ?>" placeholder="e.g. Republic Day">
      </div>
      <div class="mb-4">
        <label class="form-label">Type</label>
        <select name="type" class="form-select">
          <option value="national"   <?= $rec['Type']==='national'  ?'selected':'' ?>>National Holiday</option>
          <option value="optional"   <?= $rec['Type']==='optional'  ?'selected':'' ?>>Optional Holiday</option>
          <option value="restricted" <?= $rec['Type']==='restricted'?'selected':'' ?>>Restricted Holiday</option>
        </select>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $editId ? 'Save' : 'Add Holiday' ?></button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
