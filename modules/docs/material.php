<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

try { $db->query("SELECT 1 FROM tblIssuedMaterial LIMIT 1"); }
catch (PDOException $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

if ($user['role'] === 'superadmin') {
    $companiesDd = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $s = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $s->execute([$user['id']]); $companiesDd = $s->fetchAll();
}
$fCompany = (int)($_REQUEST['company'] ?? ($companiesDd[0]['id'] ?? 0));
if ($fCompany && $user['role'] === 'admin') {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$fCompany, $user['id']]); if (!$chk->fetch()) $fCompany = 0;
}

$fFilter = in_array($_GET['filter'] ?? '', ['pending','returned','all']) ? ($_GET['filter'] ?? 'pending') : 'pending';
$msg = ''; $msgType = 'success';

// ── POST handlers ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fCompany) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'issue') {
        $empId     = (int)$_POST['employee_id'];
        $itemName  = trim($_POST['item_name'] ?? '');
        $itemCode  = trim($_POST['item_code'] ?? '');
        $serialNo  = trim($_POST['serial_no'] ?? '');
        $issuedOn  = trim($_POST['issued_on'] ?? '');
        $returnDue = trim($_POST['return_due'] ?? '') ?: null;
        $condition = in_array($_POST['condition_issue'] ?? '', ['good','fair','poor']) ? $_POST['condition_issue'] : 'good';
        $remarks   = trim($_POST['remarks'] ?? '');
        if ($empId && $itemName && $issuedOn) {
            $db->prepare(
                "INSERT INTO tblIssuedMaterial (CompanyId,EmployeeId,ItemName,ItemCode,SerialNo,IssuedOn,ReturnDue,ConditionOnIssue,Remarks,CreatedBy)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            )->execute([$fCompany, $empId, $itemName, $itemCode?:null, $serialNo?:null, $issuedOn, $returnDue, $condition, $remarks?:null, $user['id']]);
            $msg = 'Material issued.';
        }

    } elseif ($action === 'return') {
        $id       = (int)$_POST['id'];
        $returnOn = trim($_POST['returned_on'] ?? date('Y-m-d'));
        $condRet  = in_array($_POST['condition_return'] ?? '', ['good','fair','poor','damaged','lost']) ? $_POST['condition_return'] : 'good';
        $remarks  = trim($_POST['remarks'] ?? '');
        $db->prepare("UPDATE tblIssuedMaterial SET ReturnedOn=?, ConditionOnReturn=?, Remarks=CONCAT(COALESCE(Remarks,''),' | Return: ',?), UpdatedAt=NOW() WHERE id=? AND CompanyId=?")
           ->execute([$returnOn, $condRet, $remarks?:'-', $id, $fCompany]);
        $msg = 'Return recorded.';

    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM tblIssuedMaterial WHERE id=? AND CompanyId=?")->execute([$id, $fCompany]);
        $msg = 'Record deleted.';
    }

    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$msg,'redirect'=>"material.php?company=$fCompany&filter=$fFilter"]); exit; }
    header("Location: material.php?company=$fCompany&filter=$fFilter&msg=" . urlencode($msg));
    exit;
}
if (isset($_GET['msg'])) { $msg = $_GET['msg']; }

// ── Load records ───────────────────────────────────────────────────────────────
$records = [];
if ($fCompany) {
    $where  = ['m.CompanyId = ?'];
    $params = [$fCompany];
    if ($fFilter === 'pending')  { $where[] = 'm.ReturnedOn IS NULL'; }
    if ($fFilter === 'returned') { $where[] = 'm.ReturnedOn IS NOT NULL'; }
    $wsql = 'WHERE ' . implode(' AND ', $where);

    $stmt = $db->prepare(
        "SELECT m.*, e.Name AS EmpName, e.EmployeeCode, e.Department
         FROM tblIssuedMaterial m
         JOIN tblEmployee e ON e.id = m.EmployeeId
         $wsql ORDER BY m.ReturnedOn IS NOT NULL, m.IssuedOn DESC"
    );
    $stmt->execute($params); $records = $stmt->fetchAll();
}

// Employees for the form
$employees = [];
if ($fCompany) {
    $s = $db->prepare(
        "SELECT e.id, e.Name, e.EmployeeCode, e.Department FROM tblEmployee e
         JOIN tblCompany c ON c.id=e.CompanyId
         WHERE e.CompanyId=? AND e.Status='active'
         " . ($user['role']==='admin' ? "AND c.AdminId={$user['id']}" : '') . "
         ORDER BY e.Department, ISNULL(e.Sr), e.Sr, e.Name"
    );
    $s->execute([$fCompany]); $employees = $s->fetchAll();
}

$condLabels = ['good'=>'Good','fair'=>'Fair','poor'=>'Poor','damaged'=>'Damaged','lost'=>'Lost'];
$pending = count(array_filter($records, fn($r) => !$r['ReturnedOn']));

$pageTitle  = 'Issued Material';
$activePage = 'doc_material';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-success py-2"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div class="d-flex gap-2 align-items-center">
    <form method="GET" class="d-inline">
      <select name="company" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:160px">
        <?php foreach ($companiesDd as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="hidden" name="filter" value="<?= $fFilter ?>">
    </form>
    <div class="btn-group btn-group-sm">
      <?php foreach (['pending'=>'Pending Returns','returned'=>'Returned','all'=>'All'] as $f=>$l): ?>
      <a href="material.php?company=<?= $fCompany ?>&filter=<?= $f ?>"
         class="btn <?= $fFilter===$f?'btn-primary':'btn-outline-secondary' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php if ($employees): ?>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#issueModal">
    <i class="bi bi-box-seam me-1"></i>Issue Material
  </button>
  <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($records)): ?>
    <div class="p-4 text-center text-muted">
      <i class="bi bi-box-seam fs-2 d-block mb-2"></i>
      No <?= $fFilter==='pending'?'pending':'' ?> material records.
    </div>
    <?php else: ?>
    <table class="table table-hover table-sm align-middle mb-0">
      <thead class="table-light">
        <tr><th>Employee</th><th>Item</th><th>Code / Serial</th><th>Issued</th><th>Return Due</th><th>Condition</th><th>Returned</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php foreach ($records as $r): $overdue = !$r['ReturnedOn'] && $r['ReturnDue'] && $r['ReturnDue'] < date('Y-m-d'); ?>
      <tr class="<?= $overdue?'table-warning':'' ?>">
        <td>
          <div class="fw-semibold"><?= htmlspecialchars($r['EmpName']) ?></div>
          <div class="text-muted small"><?= htmlspecialchars($r['Department']??'') ?></div>
        </td>
        <td>
          <div class="fw-semibold"><?= htmlspecialchars($r['ItemName']) ?></div>
          <?php if ($r['Remarks']): ?><div class="text-muted small"><?= htmlspecialchars($r['Remarks']) ?></div><?php endif; ?>
        </td>
        <td class="small text-muted">
          <?= $r['ItemCode'] ? htmlspecialchars($r['ItemCode']).'<br>' : '' ?>
          <?= $r['SerialNo'] ? '<code>'.htmlspecialchars($r['SerialNo']).'</code>' : '—' ?>
        </td>
        <td class="small"><?= htmlspecialchars($r['IssuedOn']) ?><br><span class="badge bg-<?= ['good'=>'success','fair'=>'warning','poor'=>'danger'][$r['ConditionOnIssue']]??'secondary' ?>"><?= $condLabels[$r['ConditionOnIssue']]??$r['ConditionOnIssue'] ?></span></td>
        <td class="small <?= $overdue?'text-danger fw-bold':'' ?>"><?= $r['ReturnDue'] ?: '—' ?><?= $overdue?' ⚠':''; ?></td>
        <td><?php if ($r['ReturnedOn']): ?>
          <span class="badge bg-<?= ['good'=>'success','fair'=>'warning','poor'=>'danger','damaged'=>'danger','lost'=>'dark'][$r['ConditionOnReturn']]??'secondary' ?>">
            <?= $condLabels[$r['ConditionOnReturn']]??$r['ConditionOnReturn'] ?>
          </span>
        <?php else: ?><span class="text-muted small">Pending</span><?php endif; ?></td>
        <td class="small"><?= $r['ReturnedOn'] ? htmlspecialchars($r['ReturnedOn']) : '—' ?></td>
        <td>
          <?php if (!$r['ReturnedOn']): ?>
          <button class="btn btn-sm btn-outline-success"
                  onclick="fillReturn(<?= $r['id'] ?>,'<?= htmlspecialchars($r['ItemName'], ENT_QUOTES) ?>')"
                  data-bs-toggle="modal" data-bs-target="#returnModal">
            <i class="bi bi-arrow-return-left"></i>
          </button>
          <?php endif; ?>
          <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')" data-ajax>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="company" value="<?= $fCompany ?>">
            <input type="hidden" name="id" value="<?= $r['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Issue Modal -->
<div class="modal fade" id="issueModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" data-ajax>
        <input type="hidden" name="action" value="issue">
        <input type="hidden" name="company" value="<?= $fCompany ?>">
        <div class="modal-header"><h5 class="modal-title">Issue Material</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Employee <span class="text-danger">*</span></label>
              <select name="employee_id" class="form-select" required>
                <option value="">— Select Employee —</option>
                <?php $prevD=null; foreach ($employees as $e):
                    if ($e['Department']!==$prevD){if($prevD!==null)echo '</optgroup>';echo '<optgroup label="'.htmlspecialchars($e['Department']??'No Dept').'">';$prevD=$e['Department'];}
                ?>
                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['Name']) ?> (<?= htmlspecialchars($e['EmployeeCode']?:'—') ?>)</option>
                <?php endforeach; if ($prevD!==null) echo '</optgroup>'; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Item Name <span class="text-danger">*</span></label>
              <input type="text" name="item_name" class="form-control" required placeholder="e.g. Laptop, ID Card, Uniform">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Item Code</label>
              <input type="text" name="item_code" class="form-control" placeholder="Asset code">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Serial No.</label>
              <input type="text" name="serial_no" class="form-control">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Issued On <span class="text-danger">*</span></label>
              <input type="date" name="issued_on" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Return Due</label>
              <input type="date" name="return_due" class="form-control">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Condition on Issue</label>
              <select name="condition_issue" class="form-select">
                <option value="good">Good</option>
                <option value="fair">Fair</option>
                <option value="poor">Poor</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Remarks</label>
              <input type="text" name="remarks" class="form-control" placeholder="Optional notes">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Issue</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Return Modal -->
<div class="modal fade" id="returnModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" data-ajax>
        <input type="hidden" name="action" value="return">
        <input type="hidden" name="company" value="<?= $fCompany ?>">
        <input type="hidden" name="id" id="returnId">
        <div class="modal-header"><h5 class="modal-title">Record Return — <span id="returnItemName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label">Return Date</label>
              <input type="date" name="returned_on" class="form-control" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-sm-6">
              <label class="form-label">Condition on Return</label>
              <select name="condition_return" class="form-select">
                <option value="good">Good</option>
                <option value="fair">Fair</option>
                <option value="poor">Poor</option>
                <option value="damaged">Damaged</option>
                <option value="lost">Lost</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Remarks</label>
              <input type="text" name="remarks" class="form-control" placeholder="Optional">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Record Return</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function fillReturn(id, name) {
    document.getElementById('returnId').value = id;
    document.getElementById('returnItemName').textContent = name;
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
