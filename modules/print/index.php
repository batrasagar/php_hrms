<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
requirePermission('print.view');

$db   = getDb();
$user = currentUser();

// Company comes from the global topbar switcher
$fCompany = activeCompanyId($db, $user);
$fDept    = trim($_GET['dept'] ?? '');
$pType    = trim($_POST['ptype'] ?? $_GET['ptype'] ?? ''); // icard | file

// Load employees for selection
$employees = [];
if ($fCompany) {
    $where  = ['e.CompanyId = ?'];
    $params = [$fCompany];
    if ($user['role'] !== 'superadmin') { $where[] = 'c.AdminId = ?'; $params[] = $user['scope_id']; }
    if ($fDept) { $where[] = 'e.Department = ?'; $params[] = $fDept; }
    $stmt = $db->prepare(
        "SELECT e.*, c.Name AS CompanyName FROM tblEmployee e
         JOIN tblCompany c ON c.id=e.CompanyId
         WHERE " . implode(' AND ', $where) . " AND e.Status = 'active'
         ORDER BY e.Department, e.Name"
    );
    $stmt->execute($params);
    $employees = $stmt->fetchAll();
}

$scopeJoin = $user['role'] === 'superadmin' ? '' : 'JOIN tblCompany c ON c.id=e.CompanyId AND c.AdminId=' . $user['scope_id'];
$depts = array_filter(array_column(
    $db->query("SELECT DISTINCT Department FROM tblEmployee e $scopeJoin ORDER BY Department")->fetchAll(),
    'Department'
));

// Designed card templates (tblCardTemplate may not exist until M031 runs)
$cardTpls = [];
try {
    $ct = $db->prepare("SELECT id, Name FROM tblCardTemplate WHERE CompanyId=? AND IsActive=1 ORDER BY Name");
    $ct->execute([$fCompany]);
    $cardTpls = $ct->fetchAll();
} catch (Throwable $ex) { /* migration pending */ }

// Print mode — render cards/files and return
if ($pType && !empty($_POST['emp_ids'])) {
    $ids = array_map('intval', $_POST['emp_ids']);
    if (!empty($ids)) {
        $in = implode(',', $ids);
        $empStmt = $db->query(
            "SELECT e.*, c.Name AS CompanyName, c.Address AS CompanyAddress FROM tblEmployee e
             JOIN tblCompany c ON c.id=e.CompanyId
             WHERE e.id IN ($in) ORDER BY e.Department, e.Name"
        );
        $printEmps = $empStmt->fetchAll();
        if      ($pType === 'icard') { include __DIR__ . '/icard_print.php'; }
        elseif  ($pType === 'card')  { requirePermission('card_templates.view'); include __DIR__ . '/card_print.php'; }
        elseif  ($pType === 'file2') { include __DIR__ . '/file2_print.php'; }
        else                         { include __DIR__ . '/file_print.php'; }
        exit;
    }
}
$pageTitle  = 'Print iCard / Personal File';
$activePage = 'print';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="company" value="<?= (int)$fCompany ?>">
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

<?php if (!empty($employees)): ?>
<form method="POST">
  <input type="hidden" name="ptype" value="">
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <input type="checkbox" id="selAll" class="form-check-input me-1" style="width:22px;height:22px;cursor:pointer">
        <label for="selAll" class="fw-semibold mb-0" style="vertical-align:middle;cursor:pointer">Select All (<span id="empCount"><?= count($employees) ?></span> employees)</label>
        <input type="text" id="empSearch" class="form-control form-control-sm ms-2" style="max-width:220px"
               placeholder="Search name / code…" autocomplete="off">
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <?php if ($cardTpls): ?>
        <div class="input-group input-group-sm" style="width:auto">
          <select name="card_template_id" class="form-select form-select-sm" style="max-width:180px">
            <?php foreach ($cardTpls as $t): ?>
            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['Name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-success btn-sm" onclick="document.querySelector('[name=ptype]').value='card'">
            <i class="bi bi-credit-card-2-front me-1"></i>Print Designed Cards
          </button>
        </div>
        <?php else: ?>
        <a href="card_templates.php" class="btn btn-outline-success btn-sm" title="Design a custom card layout">
          <i class="bi bi-credit-card-2-front me-1"></i>Design Card Template
        </a>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary btn-sm" onclick="document.querySelector('[name=ptype]').value='icard'">
          <i class="bi bi-person-badge me-1"></i>Print I-Cards
        </button>
        <button type="submit" class="btn btn-outline-primary btn-sm" onclick="document.querySelector('[name=ptype]').value='file'">
          <i class="bi bi-file-earmark-person me-1"></i>Print Personal Files
        </button>
        <button type="submit" class="btn btn-outline-secondary btn-sm" onclick="document.querySelector('[name=ptype]').value='file2'">
          <i class="bi bi-file-earmark-text me-1"></i>Print Personal File 2
        </button>
      </div>
    </div>
    <div class="card-body p-0" style="max-height:500px;overflow-y:auto">
      <table class="table table-sm table-hover mb-0">
        <thead class="table-light sticky-top">
          <tr><th></th><th>Photo</th><th>Code</th><th>Name</th><th>Department</th><th>Designation</th></tr>
        </thead>
        <tbody>
        <?php foreach ($employees as $e): ?>
        <tr class="emp-row" data-search="<?= htmlspecialchars(strtolower($e['Name'] . ' ' . ($e['EmployeeCode'] ?? ''))) ?>">
          <td><input type="checkbox" name="emp_ids[]" value="<?= $e['id'] ?>" class="emp-chk form-check-input" style="width:22px;height:22px;cursor:pointer"></td>
          <td>
            <?php if (!empty($e['Photo'])): ?>
            <img src="<?= BASE_URL ?>/uploads/employees/<?= htmlspecialchars($e['Photo']) ?>"
                 style="width:32px;height:32px;border-radius:50%;object-fit:cover">
            <?php else: ?>
            <i class="bi bi-person-circle text-muted fs-4"></i>
            <?php endif; ?>
          </td>
          <td><code class="small"><?= htmlspecialchars($e['EmployeeCode'] ?: '—') ?></code></td>
          <td><?= htmlspecialchars($e['Name']) ?></td>
          <td class="small"><?= htmlspecialchars($e['Department'] ?? '—') ?></td>
          <td class="small"><?= htmlspecialchars($e['Designation'] ?? '—') ?></td>
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
<div class="alert alert-info">Select a company to begin.</div>
<?php endif; ?>

<?php
$extraJs = <<<'JS'
<script>
// Select All — only affects rows currently visible (respects the search filter)
document.getElementById('selAll')?.addEventListener('change', function(){
  var on = this.checked;
  document.querySelectorAll('.emp-row').forEach(function(row){
    if (row.style.display !== 'none') { var c = row.querySelector('.emp-chk'); if (c) c.checked = on; }
  });
});
// Live search by employee name / code
document.getElementById('empSearch')?.addEventListener('input', function(){
  var q = this.value.trim().toLowerCase(), shown = 0;
  document.querySelectorAll('.emp-row').forEach(function(row){
    var match = !q || (row.dataset.search || '').indexOf(q) !== -1;
    row.style.display = match ? '' : 'none';
    if (match) shown++;
  });
  var cnt = document.getElementById('empCount'); if (cnt) cnt.textContent = shown;
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
