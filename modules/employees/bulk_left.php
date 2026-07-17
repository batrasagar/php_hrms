<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();
$msg  = ''; $msgType = 'success';

// Company comes from the global topbar switcher (scope-validated inside the helper)
$fCompany = activeCompanyId($db, $user);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_left'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $cid    = (int)($_POST['company_id'] ?? 0);
    $dol    = trim($_POST['dol'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['inactive','terminated'], true) ? $_POST['status'] : 'inactive';
    $ids    = array_values(array_filter(array_map('intval', (array)($_POST['emp_ids'] ?? []))));

    $ok = ($user['role'] === 'superadmin');
    if (!$ok && $cid) {
        $c = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $c->execute([$cid, $user['scope_id']]);
        $ok = (bool)$c->fetch();
    }

    if (!$ok)      { $msg = 'Access denied.'; $msgType = 'danger'; }
    elseif (!$dol) { $msg = 'Select a date of leaving.'; $msgType = 'danger'; }
    elseif (!$ids) { $msg = 'Select at least one employee.'; $msgType = 'danger'; }
    else {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $upd = $db->prepare("UPDATE tblEmployee SET DOL=?, Status=?, UpdatedAt=NOW()
                             WHERE CompanyId=? AND id IN ($ph)");
        $upd->execute(array_merge([$dol, $status, $cid], $ids));
        $n = $upd->rowCount();
        $msg = "$n employee(s) marked as left (DOL $dol, status $status).";
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $msgType === 'success', 'message' => $msg, 'errors' => [$msg]]);
        exit;
    }
    header("Location: bulk_left.php?company=$cid&msg=" . urlencode($msg) . "&mt=$msgType"); exit;
}
if (isset($_GET['msg'])) { $msg = $_GET['msg']; $msgType = in_array($_GET['mt'] ?? '', ['success','danger']) ? $_GET['mt'] : 'success'; }

// Active employees for the selected company
$employees = [];
if ($fCompany) {
    $s = $db->prepare(
        "SELECT id, Name, EmployeeCode, Department, JoinDate FROM tblEmployee
         WHERE CompanyId=? AND Status='active'
         ORDER BY Department, ISNULL(Sr), Sr, Name"
    );
    $s->execute([$fCompany]); $employees = $s->fetchAll();
}

$pageTitle  = 'Mark Left (Bulk)';
$activePage = 'emp_left';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType ?> py-2"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<?php if ($fCompany && !empty($employees)): ?>
<form method="POST" action="bulk_left.php?company=<?= $fCompany ?>" data-ajax
      onsubmit="return confirm('Mark the selected employees as left? They will no longer appear as active.');">
  <input type="hidden" name="do_left" value="1">
  <input type="hidden" name="company_id" value="<?= $fCompany ?>">
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">Leaving Details</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Date of Leaving <span class="text-danger">*</span></label>
            <input type="date" name="dol" class="form-control" required value="<?= date('Y-m-d') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">New Status</label>
            <select name="status" class="form-select">
              <option value="inactive">Inactive</option>
              <option value="terminated">Terminated</option>
            </select>
            <div class="form-text">Employee stops appearing in active lists / attendance.</div>
          </div>
          <button type="submit" class="btn btn-danger w-100"><i class="bi bi-box-arrow-right me-1"></i>Mark Selected as Left</button>
          <div class="form-text mt-1">Sets Date of Leaving and status for every ticked employee.</div>
        </div>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <span class="fw-semibold">Active Employees <span class="badge bg-secondary"><?= count($employees) ?></span></span>
          <input type="text" id="empSearch" class="form-control form-control-sm" style="max-width:220px"
                 placeholder="Search code / name…" autocomplete="off">
          <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" id="chkAll">
            <label class="form-check-label small" for="chkAll">Select all</label>
          </div>
        </div>
        <div class="card-body" style="max-height:520px;overflow-y:auto">
          <?php
          $prevD = null;
          foreach ($employees as $e):
              if ($e['Department'] !== $prevD):
                  $prevD = $e['Department'];
          ?>
          <div class="small fw-semibold text-muted mt-2 mb-1 border-bottom pb-1 dept-hdr"><?= htmlspecialchars($e['Department'] ?: '— No Department —') ?></div>
          <?php endif; ?>
          <div class="form-check emp-row" data-search="<?= htmlspecialchars(strtolower($e['Name'] . ' ' . ($e['EmployeeCode'] ?: ''))) ?>">
            <input class="form-check-input emp-chk" type="checkbox" name="emp_ids[]" value="<?= $e['id'] ?>" id="e<?= $e['id'] ?>">
            <label class="form-check-label" for="e<?= $e['id'] ?>">
              <?= htmlspecialchars($e['Name']) ?> <span class="text-muted small">(<?= htmlspecialchars($e['EmployeeCode'] ?: '—') ?>)</span>
              <?php if ($e['JoinDate']): ?><span class="text-muted small">· joined <?= htmlspecialchars($e['JoinDate']) ?></span><?php endif; ?>
            </label>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</form>
<script>
document.getElementById('chkAll')?.addEventListener('change', function(){
  var on = this.checked;
  document.querySelectorAll('.emp-row').forEach(function(row){
    if (row.style.display !== 'none') { var c = row.querySelector('.emp-chk'); if (c) c.checked = on; }
  });
});
document.getElementById('empSearch')?.addEventListener('input', function(){
  var q = this.value.trim().toLowerCase();
  document.querySelectorAll('.emp-row').forEach(function(row){
    row.style.display = (!q || (row.dataset.search || '').indexOf(q) !== -1) ? '' : 'none';
  });
  document.querySelectorAll('.dept-hdr').forEach(function(hdr){
    var vis = false, n = hdr.nextElementSibling;
    while (n && !n.classList.contains('dept-hdr')) {
      if (n.classList.contains('emp-row') && n.style.display !== 'none') { vis = true; break; }
      n = n.nextElementSibling;
    }
    hdr.style.display = vis ? '' : 'none';
  });
});
</script>
<?php elseif ($fCompany): ?>
<div class="alert alert-info">No active employees for this company.</div>
<?php else: ?>
<div class="alert alert-info">Select a company from the top-bar switcher to mark employees as left.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
