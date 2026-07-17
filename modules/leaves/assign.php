<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
requirePermission('leave_assign.view');

$db   = getDb();
$user = currentUser();

// Company comes from the global topbar switcher
$fCompany = activeCompanyId($db, $user);
$fYear    = (int)($_GET['year'] ?? date('Y'));

function canAccess($db, $user, $cid) {
    if ($user['role'] === 'superadmin') return true;
    $s = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $s->execute([$cid, $user['scope_id']]); return (bool)$s->fetch();
}

$msg = ''; $err = '';

// Save bulk assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_assign'])) {
    requirePermission('leave_assign.edit');
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $cid    = (int)($_POST['company_id'] ?? 0);
    $year   = (int)($_POST['year'] ?? 0);
    $empIds = $_POST['emp_ids'] ?? [];
    $polIds = $_POST['pol_ids'] ?? [];

    if (!$cid || !canAccess($db, $user, $cid)) {
        $err = 'Access denied.';
    } elseif (!$year || !$empIds) {
        $err = 'Select at least one employee.';
    } else {
        $insAssign = $db->prepare(
            "INSERT INTO tblEmployeeLeavePolicy (EmployeeId, CompanyId, PolicyId, Year)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE PolicyId=?, AssignedAt=NOW()"
        );
        $insBalance = $db->prepare(
            "INSERT INTO tblLeaveBalance (EmployeeId, CompanyId, LeaveTypeId, Year, Allocated)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE Allocated=VALUES(Allocated)"
        );
        $getDetail = $db->prepare(
            "SELECT LeaveTypeId, DaysPerYear FROM tblLeavePolicyDetail WHERE PolicyId=?"
        );
        $assigned = 0;
        foreach ($empIds as $i => $eid) {
            $eid = (int)$eid;
            $pid = (int)($polIds[$i] ?? 0);
            if (!$eid || !$pid) continue;

            $insAssign->execute([$eid, $cid, $pid, $year, $pid]);
            // Credit balances for each leave type in the policy
            $getDetail->execute([$pid]);
            foreach ($getDetail->fetchAll() as $d) {
                $insBalance->execute([$eid, $cid, $d['LeaveTypeId'], $year, $d['DaysPerYear']]);
            }
            $assigned++;
        }
        $successMsg = "Policy assigned and balances credited for $assigned employee(s).";
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$successMsg]); exit; }
        header("Location: assign.php?company=$cid&year=$year&msg=$assigned"); exit;
    }
    if (!empty($isAjax) && $err) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>[$err]]); exit; }
}
if (isset($_GET['msg'])) $msg = 'Policy assigned and balances credited for ' . (int)$_GET['msg'] . ' employee(s).';

// Load policies
$policies = [];
if ($fCompany) {
    $s = $db->prepare("SELECT id, PolicyName FROM tblLeavePolicy WHERE CompanyId=? AND IsActive=1 ORDER BY PolicyName");
    $s->execute([$fCompany]); $policies = $s->fetchAll();
}

// Load employees with their current assignment for the year
$employees = [];
if ($fCompany) {
    $s = $db->prepare(
        "SELECT e.id, e.EmployeeCode, e.Name, e.Department,
                elp.PolicyId AS CurrentPolicyId, lp.PolicyName AS CurrentPolicyName
         FROM tblEmployee e
         LEFT JOIN tblEmployeeLeavePolicy elp ON elp.EmployeeId=e.id AND elp.Year=?
         LEFT JOIN tblLeavePolicy lp ON lp.id = elp.PolicyId
         WHERE e.CompanyId=? AND e.Status='active'
         ORDER BY e.Department, e.Name"
    );
    $s->execute([$fYear, $fCompany]); $employees = $s->fetchAll();
}

$years = range(date('Y') + 1, date('Y') - 3);

$pageTitle  = 'Assign Leave Policy';
$activePage = 'leave_assign';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-success py-2"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="company" value="<?= (int)$fCompany ?>">
      <div class="col-sm-2">
        <label class="form-label small mb-1">Year</label>
        <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($years as $y): ?>
          <option value="<?= $y ?>" <?= $y==$fYear?'selected':'' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<?php if ($fCompany && empty($policies)): ?>
<div class="alert alert-warning">
  <i class="bi bi-exclamation-triangle me-2"></i>
  No leave policies defined. <a href="policy.php?company=<?= $fCompany ?>" class="alert-link">Create a policy first</a>.
</div>
<?php elseif ($fCompany && !empty($employees)): ?>
<form method="POST" data-ajax>
  <input type="hidden" name="do_assign" value="1">
  <input type="hidden" name="company_id" value="<?= $fCompany ?>">
  <input type="hidden" name="year" value="<?= $fYear ?>">

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <span class="fw-semibold">Assign Policy — <?= $fYear ?></span>
      <div class="d-flex gap-2 align-items-center">
        <select id="bulkPolicy" class="form-select form-select-sm" style="width:200px">
          <option value="">— Bulk set policy —</option>
          <?php foreach ($policies as $p): ?>
          <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['PolicyName']) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnApplyAll">Apply to All</button>
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Save Assignments</button>
      </div>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:36px"><input type="checkbox" id="selAll" class="form-check-input"></th>
            <th>Code</th><th>Name</th><th>Department</th>
            <th style="min-width:220px">Policy for <?= $fYear ?></th>
            <th>Current</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($employees as $e): ?>
        <tr>
          <td><input type="checkbox" name="emp_ids[]" value="<?= $e['id'] ?>" class="form-check-input emp-chk"></td>
          <td><code class="small"><?= htmlspecialchars($e['EmployeeCode'] ?: '—') ?></code></td>
          <td><?= htmlspecialchars($e['Name']) ?></td>
          <td class="small text-muted"><?= htmlspecialchars($e['Department'] ?? '—') ?></td>
          <td>
            <select name="pol_ids[]" class="form-select form-select-sm policy-sel" data-no-ts>
              <option value="0">— None —</option>
              <?php foreach ($policies as $p): ?>
              <option value="<?= $p['id'] ?>" <?= $e['CurrentPolicyId']==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['PolicyName']) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td class="small">
            <?php if ($e['CurrentPolicyName']): ?>
            <span class="badge bg-info text-dark"><?= htmlspecialchars($e['CurrentPolicyName']) ?></span>
            <?php else: ?>
            <span class="text-muted">None</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</form>
<?php elseif ($fCompany): ?>
<div class="alert alert-info">No active employees found.</div>
<?php else: ?>
<div class="alert alert-info">Select a company from the topbar switcher to assign leave policies.</div>
<?php endif; ?>

<?php
$extraJs = <<<'JS'
<script>
document.getElementById('selAll')?.addEventListener('change', function(){
  document.querySelectorAll('.emp-chk').forEach(c => c.checked = this.checked);
});
document.getElementById('btnApplyAll')?.addEventListener('click', function(){
  var pid = document.getElementById('bulkPolicy').value;
  if (!pid) return;
  document.querySelectorAll('.emp-chk:checked').forEach(function(chk){
    chk.closest('tr').querySelector('.policy-sel').value = pid;
  });
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
