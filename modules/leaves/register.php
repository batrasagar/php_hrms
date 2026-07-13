<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

if ($user['role'] === 'superadmin') {
    $companiesDd = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['scope_id']]);
    $companiesDd = $stmt->fetchAll();
}

$fCompany = (int)($_GET['company'] ?? ($companiesDd[0]['id'] ?? 0));
$fYear    = (int)($_GET['year'] ?? date('Y'));
$fDept    = trim($_GET['dept'] ?? '');

function canAccess($db, $user, $cid) {
    if ($user['role'] === 'superadmin') return true;
    $s = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $s->execute([$cid, $user['scope_id']]); return (bool)$s->fetch();
}

$msg = ''; $err = '';

// Manual balance adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_adjust'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $cid    = (int)($_POST['company_id'] ?? 0);
    $eid    = (int)($_POST['emp_id'] ?? 0);
    $ltId   = (int)($_POST['lt_id'] ?? 0);
    $year   = (int)($_POST['year'] ?? 0);
    $adj    = (float)($_POST['Adjusted'] ?? 0);
    $alloc  = (float)($_POST['Allocated'] ?? 0);

    if (!$cid || !canAccess($db, $user, $cid)) {
        $err = 'Access denied.';
    } else {
        $db->prepare(
            "INSERT INTO tblLeaveBalance (EmployeeId, CompanyId, LeaveTypeId, Year, Allocated, Adjusted)
             VALUES (?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE Allocated=?, Adjusted=?"
        )->execute([$eid, $cid, $ltId, $year, $alloc, $adj, $alloc, $adj]);
        $msg = 'Balance updated.';
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$msg]); exit; }
        header("Location: register.php?company=$cid&year=$year&dept=" . urlencode($fDept) . "&msg=1"); exit;
    }
    if (!empty($isAjax) && $err) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>[$err]]); exit; }
}
if (isset($_GET['msg'])) $msg = 'Balance updated successfully.';

// Load leave types for this company
$leaveTypes = [];
if ($fCompany) {
    $s = $db->prepare("SELECT * FROM tblLeaveType WHERE CompanyId=? AND IsActive=1 ORDER BY Code");
    $s->execute([$fCompany]); $leaveTypes = $s->fetchAll();
}

// Load employees + their balances
$employees = [];
if ($fCompany) {
    $where  = ['e.CompanyId=?'];
    $params = [$fCompany];
    if ($fDept) { $where[] = 'e.Department=?'; $params[] = $fDept; }
    $s = $db->prepare(
        "SELECT e.id, e.EmployeeCode, e.Name, e.Department
         FROM tblEmployee e WHERE " . implode(' AND ', $where) . " AND e.Status='active'
         ORDER BY e.Department, e.Name"
    );
    $s->execute($params); $employees = $s->fetchAll();
}

// Build balance map: [empId][ltId] = {Allocated, Used, Adjusted}
$balanceMap = [];
if ($fCompany && !empty($employees) && !empty($leaveTypes)) {
    $empIds = array_column($employees, 'id');
    $ltIds  = array_column($leaveTypes, 'id');
    $phE    = implode(',', array_fill(0, count($empIds), '?'));
    $phL    = implode(',', array_fill(0, count($ltIds), '?'));
    $s = $db->prepare(
        "SELECT EmployeeId, LeaveTypeId, Allocated, Used, Adjusted
         FROM tblLeaveBalance WHERE Year=? AND EmployeeId IN ($phE) AND LeaveTypeId IN ($phL)"
    );
    $s->execute(array_merge([$fYear], $empIds, $ltIds));
    foreach ($s->fetchAll() as $b) {
        $balanceMap[$b['EmployeeId']][$b['LeaveTypeId']] = $b;
    }

    // Also compute Used from tblLeave for this year (actual leave records)
    $s2 = $db->prepare(
        "SELECT lv.EmployeeId, lv.LeaveCode,
                SUM(CASE WHEN lv.LeaveType='full_day' THEN 1.0 ELSE 0.5 END) AS UsedDays
         FROM tblLeave lv
         WHERE lv.CompanyId=? AND YEAR(lv.LeaveDate)=? AND lv.LeaveCode IS NOT NULL
           AND lv.EmployeeId IN ($phE)
         GROUP BY lv.EmployeeId, lv.LeaveCode"
    );
    $s2->execute(array_merge([$fCompany, $fYear], $empIds));
    // Build code→ltId map
    $codeToLt = [];
    foreach ($leaveTypes as $lt) { $codeToLt[$lt['Code']] = $lt['id']; }
    foreach ($s2->fetchAll() as $r) {
        $ltId = $codeToLt[$r['LeaveCode']] ?? null;
        if ($ltId) {
            if (!isset($balanceMap[$r['EmployeeId']][$ltId])) {
                $balanceMap[$r['EmployeeId']][$ltId] = ['Allocated'=>0,'Used'=>0,'Adjusted'=>0];
            }
            $balanceMap[$r['EmployeeId']][$ltId]['Used'] = (float)$r['UsedDays'];
        }
    }
}

// Dept filter list
$scopeJoin = $user['role']==='superadmin' ? '' : 'JOIN tblCompany c ON c.id=e.CompanyId AND c.AdminId='.$user['scope_id'];
$depts = array_filter(array_column(
    $db->query("SELECT DISTINCT Department FROM tblEmployee e $scopeJoin ORDER BY Department")->fetchAll(),
    'Department'
));

$years = range(date('Y') + 1, date('Y') - 3);

$pageTitle  = 'Leave Register';
$activePage = 'leave_register';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-success py-2"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Adjust Modal -->
<div class="modal fade" id="adjModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form method="POST" data-ajax>
        <input type="hidden" name="do_adjust" value="1">
        <input type="hidden" name="company_id" id="adj_cid" value="">
        <input type="hidden" name="emp_id"     id="adj_eid" value="">
        <input type="hidden" name="lt_id"      id="adj_ltid" value="">
        <input type="hidden" name="year"        id="adj_year" value="">
        <div class="modal-header py-2">
          <h6 class="modal-title">Adjust Balance</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="small text-muted mb-2" id="adj_label"></p>
          <div class="mb-2">
            <label class="form-label small">Allocated Days</label>
            <input type="number" name="Allocated" id="adj_alloc" class="form-control form-control-sm" step="0.5" min="0">
          </div>
          <div class="mb-2">
            <label class="form-label small">Manual Adjustment <small class="text-muted">(+ add, – deduct)</small></label>
            <input type="number" name="Adjusted" id="adj_adjusted" class="form-control form-control-sm" step="0.5">
          </div>
        </div>
        <div class="modal-footer py-1">
          <button type="submit" class="btn btn-primary btn-sm">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-sm-4">
        <label class="form-label small mb-1">Company</label>
        <select name="company" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— Select Company —</option>
          <?php foreach ($companiesDd as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label small mb-1">Year</label>
        <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($years as $y): ?>
          <option value="<?= $y ?>" <?= $y==$fYear?'selected':'' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-3">
        <label class="form-label small mb-1">Department</label>
        <select name="dept" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All</option>
          <?php foreach ($depts as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>" <?= $fDept===$d?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<?php if ($fCompany && empty($leaveTypes)): ?>
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>No leave types for this company. <a href="types.php?company=<?= $fCompany ?>" class="alert-link">Add leave types</a>.</div>
<?php elseif ($fCompany && !empty($employees)): ?>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold">
    Leave Balance Register — <?= $fYear ?>
    <small class="text-muted ms-2">Click any cell to adjust. <span class="badge bg-success">Balance = Allocated + Adj − Used</span></small>
  </div>
  <div class="card-body p-0" style="overflow-x:auto">
    <table class="table table-sm table-bordered mb-0" style="min-width:600px">
      <thead class="table-light">
        <tr>
          <th>Code</th>
          <th>Name</th>
          <th>Dept</th>
          <?php foreach ($leaveTypes as $lt): ?>
          <th class="text-center" colspan="2" style="min-width:100px">
            <span class="badge bg-secondary"><?= htmlspecialchars($lt['Code']) ?></span>
          </th>
          <?php endforeach; ?>
        </tr>
        <tr>
          <th colspan="3"></th>
          <?php foreach ($leaveTypes as $lt): ?>
          <th class="text-center small text-muted" style="min-width:50px">Alloc</th>
          <th class="text-center small text-muted" style="min-width:50px">Bal</th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($employees as $e): ?>
      <tr>
        <td><code class="small"><?= htmlspecialchars($e['EmployeeCode'] ?: '—') ?></code></td>
        <td><?= htmlspecialchars($e['Name']) ?></td>
        <td class="small text-muted"><?= htmlspecialchars($e['Department'] ?? '—') ?></td>
        <?php foreach ($leaveTypes as $lt):
            $b = $balanceMap[$e['id']][$lt['id']] ?? ['Allocated'=>0,'Used'=>0,'Adjusted'=>0];
            $alloc = (float)$b['Allocated'];
            $used  = (float)$b['Used'];
            $adj   = (float)$b['Adjusted'];
            $bal   = $alloc + $adj - $used;
        ?>
        <td class="text-center" style="cursor:pointer"
            onclick="openAdj(<?= $e['id'] ?>, <?= $lt['id'] ?>, <?= $fCompany ?>, <?= $fYear ?>, '<?= htmlspecialchars(addslashes($e['Name'])) ?>', '<?= htmlspecialchars($lt['Code']) ?>', <?= $alloc ?>, <?= $adj ?>)"
            title="Used: <?= $used ?>  Adj: <?= $adj ?>">
          <?= number_format($alloc, 1) ?>
          <?php if ($adj != 0): ?>
          <span class="small <?= $adj>0?'text-success':'text-danger' ?>">(<?= ($adj>0?'+':'').number_format($adj,1) ?>)</span>
          <?php endif; ?>
        </td>
        <td class="text-center fw-semibold <?= $bal<0?'text-danger':($bal==0?'text-muted':'text-success') ?>">
          <?= number_format($bal, 1) ?>
        </td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php elseif ($fCompany): ?>
<div class="alert alert-info">No active employees found.</div>
<?php else: ?>
<div class="alert alert-info">Select a company and year to view leave balances.</div>
<?php endif; ?>

<?php
$extraJs = <<<'JS'
<script>
function openAdj(eid, ltid, cid, year, ename, lcode, alloc, adj) {
  document.getElementById('adj_eid').value     = eid;
  document.getElementById('adj_ltid').value    = ltid;
  document.getElementById('adj_cid').value     = cid;
  document.getElementById('adj_year').value    = year;
  document.getElementById('adj_alloc').value   = alloc;
  document.getElementById('adj_adjusted').value = adj;
  document.getElementById('adj_label').textContent = ename + ' — ' + lcode;
  new bootstrap.Modal(document.getElementById('adjModal')).show();
}
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
