<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

if ($user['role'] === 'superadmin') {
    $companies = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $s = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $s->execute([$user['id']]);
    $companies = $s->fetchAll();
}

$fCompany = (int)($_REQUEST['company'] ?? ($companies[0]['id'] ?? 0));
if ($fCompany && $user['role'] === 'admin') {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$fCompany, $user['id']]);
    if (!$chk->fetch()) $fCompany = 0;
}

try { $db->query("SELECT 1 FROM tblPayrollComponent LIMIT 1"); }
catch (PDOException $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

$msg = ''; $msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fCompany) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name  = trim($_POST['Name'] ?? '');
        $type  = in_array($_POST['Type'] ?? '', ['earning','deduction']) ? $_POST['Type'] : 'earning';
        $calc  = in_array($_POST['CalcType'] ?? '', ['fixed','percent_basic','percent_gross']) ? $_POST['CalcType'] : 'fixed';
        $val   = max(0, (float)($_POST['DefaultValue'] ?? 0));
        $sort  = (int)($_POST['SortOrder'] ?? 0);
        if (!$name) { $msg = 'Name is required.'; $msgType = 'danger'; }
        else {
            $db->prepare("INSERT INTO tblPayrollComponent (CompanyId,Name,Type,CalcType,DefaultValue,SortOrder) VALUES (?,?,?,?,?,?)")
               ->execute([$fCompany, $name, $type, $calc, $val, $sort]);
            $msg = 'Component added.';
        }
    } elseif ($action === 'edit') {
        $id   = (int)$_POST['id'];
        $name = trim($_POST['Name'] ?? '');
        $type = in_array($_POST['Type'] ?? '', ['earning','deduction']) ? $_POST['Type'] : 'earning';
        $calc = in_array($_POST['CalcType'] ?? '', ['fixed','percent_basic','percent_gross']) ? $_POST['CalcType'] : 'fixed';
        $val  = max(0, (float)($_POST['DefaultValue'] ?? 0));
        $sort = (int)($_POST['SortOrder'] ?? 0);
        if ($id && $name) {
            $db->prepare("UPDATE tblPayrollComponent SET Name=?,Type=?,CalcType=?,DefaultValue=?,SortOrder=? WHERE id=? AND CompanyId=?")
               ->execute([$name, $type, $calc, $val, $sort, $id, $fCompany]);
            $msg = 'Component updated.';
        }
    } elseif ($action === 'toggle') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE tblPayrollComponent SET IsActive = 1 - IsActive WHERE id=? AND CompanyId=?")->execute([$id, $fCompany]);
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM tblPayrollComponent WHERE id=? AND CompanyId=?")->execute([$id, $fCompany]);
        $msg = 'Component removed.';
    }
    if ($isAjax) {
        $out = $msg ? ['success'=>true,'message'=>$msg] : ['success'=>true,'redirect'=>"components.php?company=$fCompany"];
        header('Content-Type: application/json'); echo json_encode($out); exit;
    }
    header("Location: components.php?company=$fCompany"); exit;
}

$components = [];
if ($fCompany) {
    $s = $db->prepare("SELECT * FROM tblPayrollComponent WHERE CompanyId=? ORDER BY Type, SortOrder, id");
    $s->execute([$fCompany]);
    $components = $s->fetchAll();
}

$editId = (int)($_GET['edit'] ?? 0);
$editRow = [];
if ($editId) {
    foreach ($components as $c) { if ($c['id'] == $editId) { $editRow = $c; break; } }
}

$pageTitle  = 'Payroll Components';
$activePage = 'payroll_components';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
  <form method="GET" class="d-flex gap-2 align-items-center">
    <select name="company" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:180px">
      <?php foreach ($companies as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $c['id']==$fCompany?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <?php if ($fCompany): ?>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
    <i class="bi bi-plus-lg me-1"></i>Add Component
  </button>
  <?php endif; ?>
</div>

<?php if ($fCompany && $components): ?>
<?php
  $earnings    = array_filter($components, fn($c) => $c['Type'] === 'earning');
  $deductions  = array_filter($components, fn($c) => $c['Type'] === 'deduction');
?>
<?php foreach (['earning' => [$earnings,'primary','Earnings'], 'deduction' => [$deductions,'danger','Deductions']] as $type => [$rows, $color, $label]): ?>
<div class="card mb-4">
  <div class="card-header d-flex align-items-center gap-2">
    <span class="badge bg-<?= $color ?>"><?= $label ?></span>
    <span class="text-muted small"><?= count($rows) ?> head<?= count($rows)!=1?'s':'' ?></span>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>#</th><th>Name</th><th>Calculation</th><th>Default Value</th><th>Sort</th><th>Status</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $c): ?>
        <tr class="<?= !$c['IsActive'] ? 'text-muted' : '' ?>">
          <td class="text-muted small"><?= $c['id'] ?></td>
          <td class="fw-semibold"><?= htmlspecialchars($c['Name']) ?></td>
          <td class="small">
            <?php
              echo match($c['CalcType']) {
                'fixed'         => 'Fixed ₹',
                'percent_basic' => '% of Basic',
                'percent_gross' => '% of Gross',
              };
            ?>
          </td>
          <td>
            <?= $c['CalcType'] === 'fixed' ? '₹' . number_format($c['DefaultValue'],2) : $c['DefaultValue'].'%' ?>
          </td>
          <td class="text-muted small"><?= $c['SortOrder'] ?></td>
          <td>
            <form method="POST" action="components.php?company=<?= $fCompany ?>" class="d-inline" data-ajax>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" class="badge border-0 bg-<?= $c['IsActive']?'success':'secondary' ?> text-decoration-none">
                <?= $c['IsActive'] ? 'Active' : 'Inactive' ?>
              </button>
            </form>
          </td>
          <td class="d-flex gap-1">
            <a href="components.php?company=<?= $fCompany ?>&edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">
              <i class="bi bi-pencil"></i>
            </a>
            <form method="POST" action="components.php?company=<?= $fCompany ?>" class="d-inline" data-ajax
                  onsubmit="return confirm('Delete this component?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="text-center text-muted py-3">No <?= strtolower($label) ?> heads yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>

<?php elseif ($fCompany): ?>
<div class="alert alert-info">No components yet. <button class="btn btn-sm btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#addModal">Add first component</button></div>
<?php else: ?>
<div class="alert alert-warning">Please select a company.</div>
<?php endif; ?>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="components.php?company=<?= $fCompany ?>" data-ajax>
        <input type="hidden" name="action" value="add">
        <div class="modal-header"><h5 class="modal-title">Add Component</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <?php include __DIR__ . '/component_form.php'; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<?php if ($editRow): ?>
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="components.php?company=<?= $fCompany ?>" data-ajax>
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Edit Component</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <?php $row = $editRow; include __DIR__ . '/component_form.php'; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
  var editModal = new bootstrap.Modal(document.getElementById('editModal'));
  editModal.show();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
