<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();
$msg  = '';

if ($user['role'] === 'superadmin') {
    $companiesDd = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['scope_id']]);
    $companiesDd = $stmt->fetchAll();
}

$fCompany = (int)($_GET['company'] ?? ($companiesDd[0]['id'] ?? 0));
$fDept    = trim($_GET['dept'] ?? '');
$fPage    = max(1, (int)($_GET['p'] ?? 1));
$perPage  = 50;

// Save changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emp_ids'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $ids    = $_POST['emp_ids']   ?? [];
    $codes  = $_POST['codes']     ?? [];
    $enroll = $_POST['enrids']    ?? [];
    $names  = $_POST['names']     ?? [];
    $depts  = $_POST['depts']     ?? [];
    $conts  = $_POST['contractors'] ?? [];
    $desigs = $_POST['desigs']    ?? [];
    $joins  = $_POST['joins']     ?? [];
    $statuses = $_POST['statuses'] ?? [];
    $srs    = $_POST['srs']       ?? [];

    $saved = 0;
    foreach ($ids as $i => $id) {
        $id = (int)$id;
        if (!$id) continue;

        // Scope guard
        if ($user['role'] !== 'superadmin') {
            $chk = $db->prepare("SELECT e.id FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId AND c.AdminId=? WHERE e.id=?");
            $chk->execute([$user['scope_id'], $id]);
            if (!$chk->fetch()) continue;
        }

        $srVal = trim($srs[$i] ?? '');
        $db->prepare(
            "UPDATE tblEmployee SET EmployeeCode=?, EnrollId=?, Name=?, Department=?, Contractor=?,
             Designation=?, JoinDate=?, Status=?, Sr=?, UpdatedAt=NOW() WHERE id=?"
        )->execute([
            trim($codes[$i] ?? ''),
            trim($enroll[$i] ?? ''),
            trim($names[$i] ?? ''),
            trim($depts[$i] ?? '') ?: null,
            trim($conts[$i] ?? '') ?: null,
            trim($desigs[$i] ?? '') ?: null,
            (!empty($joins[$i]) && strtotime($joins[$i])) ? $joins[$i] : null,
            in_array($statuses[$i] ?? '', ['active','inactive','terminated']) ? $statuses[$i] : 'active',
            $srVal !== '' ? (int)$srVal : null,
            $id,
        ]);
        $saved++;
    }
    $msg = "Saved $saved row(s).";
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$msg]); exit; }
    // Re-direct with same filters to avoid resubmit
    header("Location: bulk_edit.php?company=$fCompany&dept=" . urlencode($fDept) . "&p=$fPage&saved=$saved");
    exit;
}
if (isset($_GET['saved'])) $msg = 'Saved ' . (int)$_GET['saved'] . ' row(s).';

// Load employees for selected company
$where  = $fCompany ? ['e.CompanyId = ?'] : [];
$params = $fCompany ? [$fCompany] : [];
if ($user['role'] !== 'superadmin') { $where[] = 'c.AdminId = ?'; $params[] = $user['scope_id']; }
if ($fDept) { $where[] = 'e.Department = ?'; $params[] = $fDept; }
$wsql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalStmt = $db->prepare("SELECT COUNT(*) FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId $wsql");
$totalStmt->execute($params);
$total   = (int)$totalStmt->fetchColumn();
$pages   = max(1, ceil($total / $perPage));
$offset  = ($fPage - 1) * $perPage;

$stmt = $db->prepare(
    "SELECT e.* FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId $wsql
     ORDER BY ISNULL(e.Sr), e.Sr, e.Name LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$employees = $stmt->fetchAll();

$scopeJoin = $user['role'] === 'superadmin' ? '' : 'JOIN tblCompany c ON c.id=e.CompanyId AND c.AdminId=' . $user['scope_id'];
$depts = array_filter(array_column(
    $db->query("SELECT DISTINCT Department FROM tblEmployee e $scopeJoin ORDER BY Department")->fetchAll(),
    'Department'
));
$pageTitle  = 'Bulk Edit Employees';
$activePage = 'emp_bulk';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-success py-2"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-sm-4">
        <label class="form-label small mb-1">Company</label>
        <select name="company" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">All Companies</option>
          <?php foreach ($companiesDd as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
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
      <div class="col-auto">
        <span class="text-muted small">Showing <?= count($employees) ?> of <?= $total ?> employees (page <?= $fPage ?>/<?= $pages ?>)</span>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Bulk Edit <small class="text-muted">(edit inline then Save)</small></span>
    <div>
      <button form="bulkForm" type="submit" class="btn btn-success btn-sm"><i class="bi bi-save me-1"></i>Save Changes</button>
    </div>
  </div>
  <div class="card-body p-0" style="overflow-x:auto">
    <form id="bulkForm" method="POST" data-ajax>
    <?php if (empty($employees)): ?>
    <div class="p-4 text-center text-muted">No employees found. Select a company above.</div>
    <?php else: ?>
    <style>
      #tblBulkEdit td { padding: 0 !important; vertical-align: middle; }
      #tblBulkEdit td.text-muted { padding: 2px 4px !important; }
      #tblBulkEdit .form-control,
      #tblBulkEdit .form-select {
        padding: 1px 3px !important;
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
        height: auto !important;
        min-height: unset !important;
        border-radius: 0 !important;
      }
    </style>
    <table id="tblBulkEdit" class="table table-sm table-bordered mb-0" style="min-width:900px">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th style="min-width:55px">Sr</th>
          <th style="min-width:90px">Code</th>
          <th style="min-width:80px">Enroll ID</th>
          <th style="min-width:140px">Name <span class="text-danger">*</span></th>
          <th style="min-width:110px">Department</th>
          <th style="min-width:110px">Contractor</th>
          <th style="min-width:120px">Designation</th>
          <th style="min-width:110px">Join Date</th>
          <th style="min-width:100px">Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($employees as $i => $e): ?>
      <tr>
        <td class="text-muted small"><?= ($fPage-1)*$perPage + $i + 1 ?></td>
        <input type="hidden" name="emp_ids[]" value="<?= $e['id'] ?>">
        <td><input type="number" name="srs[]" class="form-control form-control-sm border-0 bg-transparent p-0" style="min-width:50px" value="<?= $e['Sr'] !== null ? (int)$e['Sr'] : '' ?>" placeholder="—"></td>
        <td><input type="text" name="codes[]"  class="form-control form-control-sm border-0 bg-transparent p-0" value="<?= htmlspecialchars($e['EmployeeCode']) ?>"></td>
        <td><input type="text" name="enrids[]" class="form-control form-control-sm border-0 bg-transparent p-0" value="<?= htmlspecialchars($e['EnrollId']) ?>"></td>
        <td><input type="text" name="names[]"  class="form-control form-control-sm border-0 bg-transparent p-0" required value="<?= htmlspecialchars($e['Name']) ?>"></td>
        <td><input type="text" name="depts[]"  class="form-control form-control-sm border-0 bg-transparent p-0" list="deptList" value="<?= htmlspecialchars($e['Department'] ?? '') ?>"></td>
        <td><input type="text" name="contractors[]" class="form-control form-control-sm border-0 bg-transparent p-0" value="<?= htmlspecialchars($e['Contractor'] ?? '') ?>"></td>
        <td><input type="text" name="desigs[]" class="form-control form-control-sm border-0 bg-transparent p-0" value="<?= htmlspecialchars($e['Designation'] ?? '') ?>"></td>
        <td><input type="date" name="joins[]"  class="form-control form-control-sm border-0 bg-transparent p-0" value="<?= htmlspecialchars($e['JoinDate'] ?? '') ?>"></td>
        <td>
          <select name="statuses[]" class="form-select form-select-sm border-0 bg-transparent p-0">
            <option value="active"     <?= $e['Status']==='active'    ?'selected':'' ?>>Active</option>
            <option value="inactive"   <?= $e['Status']==='inactive'  ?'selected':'' ?>>Inactive</option>
            <option value="terminated" <?= $e['Status']==='terminated'?'selected':'' ?>>Terminated</option>
          </select>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <datalist id="deptList">
      <?php foreach ($depts as $d): ?>
      <option value="<?= htmlspecialchars($d) ?>">
      <?php endforeach; ?>
    </datalist>
    <?php endif; ?>
    </form>
<script>
(function () {
  const tbl = document.getElementById('tblBulkEdit');
  if (!tbl) return;

  function cellIndex(td) {
    return Array.prototype.indexOf.call(td.parentElement.children, td);
  }

  function focusCell(tr, colIdx) {
    if (!tr) return;
    const td = tr.children[colIdx];
    if (!td) return;
    const el = td.querySelector('input, select');
    if (!el) return;
    el.focus();
    if (el.tagName === 'INPUT') el.select();
  }

  tbl.addEventListener('keydown', function (e) {
    const el = e.target;
    if (el.tagName !== 'INPUT' && el.tagName !== 'SELECT') return;

    const td   = el.closest('td');
    const tr   = el.closest('tr');
    const col  = cellIndex(td);
    const rows = tbl.tBodies[0].rows;
    const rowIdx = Array.prototype.indexOf.call(rows, tr);

    if (e.key === 'ArrowDown' || (e.key === 'Enter' && el.tagName === 'INPUT')) {
      e.preventDefault();
      focusCell(rows[rowIdx + 1], col);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      focusCell(rows[rowIdx - 1], col);
    } else if (e.key === 'ArrowRight' && el.tagName === 'SELECT') {
      e.preventDefault();
      focusCell(tr, col + 1);
    } else if (e.key === 'ArrowLeft' && el.tagName === 'SELECT') {
      e.preventDefault();
      focusCell(tr, col - 1);
    } else if (e.key === 'Tab') {
      // let Tab work naturally but select text on arrival
      setTimeout(() => {
        const active = document.activeElement;
        if (active && active.tagName === 'INPUT') active.select();
      }, 0);
    }
  });

  // Select all text when clicking into a cell
  tbl.addEventListener('focusin', function (e) {
    if (e.target.tagName === 'INPUT') e.target.select();
  });
})();
</script>
  </div>
  <?php if ($pages > 1): ?>
  <div class="card-footer bg-white d-flex justify-content-center gap-1">
    <?php for ($pg = 1; $pg <= $pages; $pg++): ?>
    <a href="?company=<?= $fCompany ?>&dept=<?= urlencode($fDept) ?>&p=<?= $pg ?>"
       class="btn btn-sm <?= $pg===$fPage?'btn-primary':'btn-outline-secondary' ?>"><?= $pg ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
