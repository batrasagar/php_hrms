<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
requirePermission('emp_bulk.view');

$db   = getDb();
$user = currentUser();
$msg  = '';

// Company comes from the global topbar switcher
$fCompany = activeCompanyId($db, $user);
$fDept    = trim($_GET['dept'] ?? '');
$fPage    = max(1, (int)($_GET['p'] ?? 1));
$perPage  = 50;

// ── Field catalog: the columns that may be shown / edited in the grid ──────────
// key = tblEmployee column; type drives the input + value coercion on save.
$CAT = [
    'Sr'            => ['label'=>'Sr',             'type'=>'int',    'w'=>55],
    'EmployeeCode'  => ['label'=>'Code',           'type'=>'text',   'w'=>90],
    'EnrollId'      => ['label'=>'Enroll ID',      'type'=>'text',   'w'=>85],
    'Name'          => ['label'=>'Name',           'type'=>'text',   'w'=>150, 'req'=>true],
    'FatherName'    => ['label'=>'Father Name',    'type'=>'text',   'w'=>150],
    'Gender'        => ['label'=>'Gender',         'type'=>'select', 'options'=>['','Male','Female','Other'], 'w'=>90],
    'DOB'           => ['label'=>'DOB',            'type'=>'date',   'w'=>120],
    'MaritalStatus' => ['label'=>'Marital Status', 'type'=>'text',   'w'=>110],
    'BloodGroup'    => ['label'=>'Blood Group',    'type'=>'text',   'w'=>90],
    'Qualification' => ['label'=>'Qualification',  'type'=>'text',   'w'=>130],
    'Phone'         => ['label'=>'Mobile',         'type'=>'text',   'w'=>110],
    'Email'         => ['label'=>'Email',          'type'=>'text',   'w'=>150],
    'PermanentAdd'  => ['label'=>'Permanent Addr', 'type'=>'text',   'w'=>170],
    'PresentAdd'    => ['label'=>'Present Addr',   'type'=>'text',   'w'=>170],
    'Department'    => ['label'=>'Department',     'type'=>'text',   'w'=>120, 'list'=>'deptList'],
    'Contractor'    => ['label'=>'Contractor',     'type'=>'text',   'w'=>120],
    'Designation'   => ['label'=>'Designation',    'type'=>'text',   'w'=>130],
    'Grade'         => ['label'=>'Grade',          'type'=>'text',   'w'=>90],
    'EmployeeType'  => ['label'=>'Employee Type',  'type'=>'text',   'w'=>120],
    'JoinDate'      => ['label'=>'Join Date',      'type'=>'date',   'w'=>120],
    'DOL'           => ['label'=>'Date of Leaving','type'=>'date',   'w'=>120],
    'Status'        => ['label'=>'Status',         'type'=>'select', 'options'=>['active','inactive','terminated'], 'w'=>100],
    'BasicSalary'   => ['label'=>'Basic Salary',   'type'=>'decimal','w'=>110],
    'GrossSalary'   => ['label'=>'Gross Salary',   'type'=>'decimal','w'=>110],
    'AdhaarID'      => ['label'=>'Aadhaar',        'type'=>'text',   'w'=>130],
    'PanNo'         => ['label'=>'PAN',            'type'=>'text',   'w'=>110],
    'UAN'           => ['label'=>'UAN',            'type'=>'text',   'w'=>120],
    'PfNo'          => ['label'=>'PF No',          'type'=>'text',   'w'=>110],
    'EsiNo'         => ['label'=>'ESI No',         'type'=>'text',   'w'=>110],
    'BankName'      => ['label'=>'Bank Name',      'type'=>'text',   'w'=>130],
    'BankAcNo'      => ['label'=>'Bank A/C',       'type'=>'text',   'w'=>120],
    'IFSCCode'      => ['label'=>'IFSC',           'type'=>'text',   'w'=>110],
];
$DEFAULT_SHOW = ['Sr','EmployeeCode','EnrollId','Name','Department','Contractor','Designation','JoinDate','Status'];

// ── Field-config storage (compact CSV in tblSettings, per active company) ──────
function beGet(PDO $db, int $cid, string $key, string $def): string {
    $s = $db->prepare("SELECT SettingValue FROM tblSettings WHERE CompanyId=? AND SettingKey=? ORDER BY id DESC LIMIT 1");
    $s->execute([$cid, $key]);
    $v = $s->fetchColumn();
    return $v === false ? $def : (string)$v;
}
function beSet(PDO $db, int $cid, string $key, string $val): void {
    $db->prepare("DELETE FROM tblSettings WHERE CompanyId=? AND SettingKey=?")->execute([$cid, $key]);
    $db->prepare("INSERT INTO tblSettings (CompanyId, SettingKey, SettingValue) VALUES (?,?,?)")->execute([$cid, $key, $val]);
}

// ── Save field configuration ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_fields') {
    requirePermission('emp_bulk.edit');
    csrf_verify();
    $keys = array_keys($CAT);
    $show = array_values(array_intersect($keys, (array)($_POST['show'] ?? [])));  // catalog order
    $edit = array_values(array_intersect($show, (array)($_POST['edit'] ?? [])));   // editable ⊆ shown
    if (!$show) $show = $DEFAULT_SHOW;                                             // never blank the grid
    beSet($db, $fCompany, 'bulk_edit_show', implode(',', $show));
    beSet($db, $fCompany, 'bulk_edit_edit', implode(',', $edit));
    header("Location: bulk_edit.php?company=$fCompany&dept=" . urlencode($fDept) . "&p=$fPage&fields=1"); exit;
}
if (isset($_GET['fields'])) $msg = 'Field settings saved.';

// Effective config
$showList = array_values(array_intersect(array_keys($CAT), array_filter(array_map('trim', explode(',', beGet($db, $fCompany, 'bulk_edit_show', implode(',', $DEFAULT_SHOW)))))));
if (!$showList) $showList = $DEFAULT_SHOW;
$editList = array_values(array_intersect($showList, array_filter(array_map('trim', explode(',', beGet($db, $fCompany, 'bulk_edit_edit', implode(',', $DEFAULT_SHOW)))))));

// ── Save edited rows ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['emp_ids'])) {
    requirePermission('emp_bulk.edit');
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $ids = $_POST['emp_ids'] ?? [];

    // Only fields that are currently editable may be written.
    $editFields = array_values(array_intersect($editList, array_keys($CAT)));

    $coerce = function (string $type, $raw, array $meta) {
        $raw = is_string($raw) ? trim($raw) : $raw;
        switch ($type) {
            case 'int':     return ($raw !== '' && $raw !== null && is_numeric($raw)) ? (int)$raw : null;
            case 'decimal': return ($raw !== '' && $raw !== null && is_numeric($raw)) ? (float)$raw : null;
            case 'date':    return ($raw && strtotime($raw)) ? date('Y-m-d', strtotime($raw)) : null;
            case 'select':  $v = in_array($raw, $meta['options'] ?? [], true) ? $raw : ''; return $v === '' ? null : $v;
            default:        return ($raw === '' || $raw === null) ? null : $raw;
        }
    };

    $saved = 0;
    foreach ($ids as $i => $id) {
        $id = (int)$id;
        if (!$id) continue;
        if ($user['role'] !== 'superadmin') {   // scope guard
            $chk = $db->prepare("SELECT e.id FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId AND c.AdminId=? WHERE e.id=?");
            $chk->execute([$user['scope_id'], $id]);
            if (!$chk->fetch()) continue;
        }
        $set = []; $vals = [];
        foreach ($editFields as $key) {
            $raw = $_POST['f_' . $key][$i] ?? null;
            if ($key === 'Status') {
                $v = in_array($raw, ['active','inactive','terminated'], true) ? $raw : 'active';
            } elseif ($key === 'Name') {
                $nm = trim((string)$raw);
                if ($nm === '') continue;   // Name is NOT NULL — never blank it
                $v = $nm;
            } else {
                $v = $coerce($CAT[$key]['type'], $raw, $CAT[$key]);
            }
            $set[] = "`$key`=?"; $vals[] = $v;
        }
        if (!$set) continue;
        $vals[] = $id;
        $db->prepare("UPDATE tblEmployee SET " . implode(', ', $set) . ", UpdatedAt=NOW() WHERE id=?")->execute($vals);
        $saved++;
    }
    $msg = "Saved $saved row(s).";
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$msg]); exit; }
    header("Location: bulk_edit.php?company=$fCompany&dept=" . urlencode($fDept) . "&p=$fPage&saved=$saved"); exit;
}
if (isset($_GET['saved'])) $msg = 'Saved ' . (int)$_GET['saved'] . ' row(s).';

// ── Load employees for selected company ───────────────────────────────────────
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

$editSet = array_flip($editList);
$tblMinW = 60; foreach ($showList as $k) $tblMinW += (int)($CAT[$k]['w'] ?? 100);

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
      <div class="col-auto">
        <span class="text-muted small">Showing <?= count($employees) ?> of <?= $total ?> employees (page <?= $fPage ?>/<?= $pages ?>)</span>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="fw-semibold">Bulk Edit <small class="text-muted">(<i class="bi bi-pencil-fill text-success"></i> = editable column; others are view-only)</small></span>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#fieldsModal">
        <i class="bi bi-sliders me-1"></i>Configure Fields
      </button>
      <?php if ($editList): ?>
      <button form="bulkForm" type="submit" class="btn btn-success btn-sm"><i class="bi bi-save me-1"></i>Save Changes</button>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body p-0" style="overflow-x:auto">
    <form id="bulkForm" method="POST" data-ajax>
    <?= csrf_field() ?>
    <?php if (empty($employees)): ?>
    <div class="p-4 text-center text-muted">No employees found. Pick a company from the top-bar switcher.</div>
    <?php else: ?>
    <style>
      #tblBulkEdit td { padding: 0 !important; vertical-align: middle; }
      #tblBulkEdit td.ro { padding: 2px 6px !important; color:#333; white-space:nowrap; }
      #tblBulkEdit td.rn { padding: 2px 4px !important; }
      #tblBulkEdit th .bi-pencil-fill { font-size:9px; }
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
    <table id="tblBulkEdit" class="table table-sm table-bordered mb-0" style="min-width:<?= (int)$tblMinW ?>px">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <?php foreach ($showList as $key):
              $m = $CAT[$key]; $ed = isset($editSet[$key]); ?>
          <th style="min-width:<?= (int)($m['w'] ?? 100) ?>px">
            <?= htmlspecialchars($m['label']) ?>
            <?php if ($ed): ?><i class="bi bi-pencil-fill text-success" title="Editable"></i><?php endif; ?>
            <?php if (!empty($m['req']) && $ed): ?><span class="text-danger">*</span><?php endif; ?>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($employees as $i => $e): ?>
      <tr>
        <td class="text-muted small rn"><?= ($fPage-1)*$perPage + $i + 1 ?></td>
        <input type="hidden" name="emp_ids[]" value="<?= $e['id'] ?>">
        <?php foreach ($showList as $key):
            $m   = $CAT[$key];
            $ed  = isset($editSet[$key]);
            $val = $e[$key] ?? '';
            if (!$ed): ?>
          <td class="ro small"><?= ($val === '' || $val === null) ? '<span class="text-muted">—</span>' : htmlspecialchars((string)$val) ?></td>
        <?php else:
            $nm = 'f_' . $key . '[]';
            if ($m['type'] === 'select'): ?>
          <td>
            <select name="<?= $nm ?>" class="form-select form-select-sm border-0 bg-transparent p-0">
              <?php foreach ($m['options'] as $opt): ?>
              <option value="<?= htmlspecialchars($opt) ?>" <?= ((string)$val === (string)$opt) ? 'selected' : '' ?>><?= $opt === '' ? '—' : htmlspecialchars(ucfirst($opt)) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
        <?php elseif ($m['type'] === 'date'): ?>
          <td><input type="date" name="<?= $nm ?>" class="form-control form-control-sm border-0 bg-transparent p-0" value="<?= htmlspecialchars($val ?: '') ?>"></td>
        <?php elseif ($m['type'] === 'int' || $m['type'] === 'decimal'): ?>
          <td><input type="number" <?= $m['type']==='decimal'?'step="0.01"':'' ?> name="<?= $nm ?>" class="form-control form-control-sm border-0 bg-transparent p-0" value="<?= htmlspecialchars($val !== null && $val !== '' ? (string)$val : '') ?>" placeholder="—"></td>
        <?php else: ?>
          <td><input type="text" name="<?= $nm ?>" class="form-control form-control-sm border-0 bg-transparent p-0" <?= !empty($m['req'])?'required':'' ?> <?= !empty($m['list'])?'list="'.htmlspecialchars($m['list']).'"':'' ?> value="<?= htmlspecialchars((string)$val) ?>"></td>
        <?php endif; endif; ?>
        <?php endforeach; ?>
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

    <!-- ── Field configuration modal ─────────────────────────────────────────── -->
    <div class="modal fade" id="fieldsModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
          <form method="POST" action="bulk_edit.php?company=<?= (int)$fCompany ?>&dept=<?= urlencode($fDept) ?>&p=<?= $fPage ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_fields">
            <div class="modal-header py-2">
              <h6 class="modal-title"><i class="bi bi-sliders me-1"></i>Configure Bulk-Edit Fields</h6>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <p class="small text-muted mb-2">Tick <strong>Show</strong> to display a column (view-only by default). Tick <strong>Edit</strong> to make it editable in the grid.</p>
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr><th>Field</th><th class="text-center" style="width:70px">Show</th><th class="text-center" style="width:70px">Edit</th></tr>
                </thead>
                <tbody>
                <?php foreach ($CAT as $key => $m):
                    $shown = in_array($key, $showList, true);
                    $edb   = isset($editSet[$key]); ?>
                  <tr>
                    <td><?= htmlspecialchars($m['label']) ?> <span class="text-muted small">(<?= htmlspecialchars($key) ?>)</span></td>
                    <td class="text-center"><input type="checkbox" class="form-check-input be-show" name="show[]" value="<?= $key ?>" <?= $shown?'checked':'' ?>></td>
                    <td class="text-center"><input type="checkbox" class="form-check-input be-edit" name="edit[]" value="<?= $key ?>" <?= $edb?'checked':'' ?>></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="modal-footer py-2">
              <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save &amp; Apply</button>
            </div>
          </form>
        </div>
      </div>
    </div>

<script>
(function () {
  // Modal: Edit implies Show; clearing Show clears Edit.
  document.querySelectorAll('#fieldsModal tbody tr').forEach(function (tr) {
    var show = tr.querySelector('.be-show'), edit = tr.querySelector('.be-edit');
    if (!show || !edit) return;
    edit.addEventListener('change', function () { if (edit.checked) show.checked = true; });
    show.addEventListener('change', function () { if (!show.checked) edit.checked = false; });
  });

  const tbl = document.getElementById('tblBulkEdit');
  if (!tbl) return;
  function cellIndex(td) { return Array.prototype.indexOf.call(td.parentElement.children, td); }
  function focusCell(tr, colIdx) {
    if (!tr) return;
    const td = tr.children[colIdx]; if (!td) return;
    const el = td.querySelector('input, select'); if (!el) return;
    el.focus(); if (el.tagName === 'INPUT') el.select();
  }
  tbl.addEventListener('keydown', function (e) {
    const el = e.target;
    if (el.tagName !== 'INPUT' && el.tagName !== 'SELECT') return;
    const td = el.closest('td'), tr = el.closest('tr'), col = cellIndex(td);
    const rows = tbl.tBodies[0].rows;
    const rowIdx = Array.prototype.indexOf.call(rows, tr);
    if (e.key === 'ArrowDown' || (e.key === 'Enter' && el.tagName === 'INPUT')) { e.preventDefault(); focusCell(rows[rowIdx + 1], col); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); focusCell(rows[rowIdx - 1], col); }
    else if (e.key === 'ArrowRight' && el.tagName === 'SELECT') { e.preventDefault(); focusCell(tr, col + 1); }
    else if (e.key === 'ArrowLeft' && el.tagName === 'SELECT') { e.preventDefault(); focusCell(tr, col - 1); }
    else if (e.key === 'Tab') { setTimeout(() => { const a = document.activeElement; if (a && a.tagName === 'INPUT') a.select(); }, 0); }
  });
  tbl.addEventListener('focusin', function (e) { if (e.target.tagName === 'INPUT') e.target.select(); });
})();
</script>
  </div>
  <?php if ($pages > 1): ?>
  <div class="card-footer bg-white d-flex justify-content-center gap-1 flex-wrap">
    <?php for ($pg = 1; $pg <= $pages; $pg++): ?>
    <a href="?company=<?= $fCompany ?>&dept=<?= urlencode($fDept) ?>&p=<?= $pg ?>"
       class="btn btn-sm <?= $pg===$fPage?'btn-primary':'btn-outline-secondary' ?>"><?= $pg ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
