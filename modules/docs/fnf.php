<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

try { $db->query("SELECT 1 FROM tblFnFSettlement LIMIT 1"); }
catch (PDOException $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

if ($user['role'] === 'superadmin') {
    $companiesDd = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $s = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $s->execute([$user['scope_id']]); $companiesDd = $s->fetchAll();
}
$fCompany = (int)($_REQUEST['company'] ?? ($companiesDd[0]['id'] ?? 0));
if ($fCompany && in_array($user['role'], ['admin','operator'], true)) {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$fCompany, $user['scope_id']]); if (!$chk->fetch()) $fCompany = 0;
}

$tab       = in_array($_GET['tab'] ?? '', ['settlements','template']) ? ($_GET['tab'] ?? 'settlements') : 'settlements';
$viewId    = (int)($_GET['view'] ?? 0);
$msg = ''; $msgType = 'success';

// ── Default checklist seed ─────────────────────────────────────────────────────
$defaultItems = [
    ['Documents',  'Resignation Acceptance Letter'],
    ['Documents',  'Experience Letter Issued'],
    ['Documents',  'Relieving Letter Issued'],
    ['Documents',  'NOC Issued'],
    ['Returns',    'ID Card Returned'],
    ['Returns',    'Access Card / Gate Pass Returned'],
    ['Returns',    'Company Mobile Returned'],
    ['Returns',    'Laptop / Computer Returned'],
    ['Returns',    'Locker Cleared'],
    ['Returns',    'Uniform Returned'],
    ['Finance',    'Final Salary Settled'],
    ['Finance',    'PF Withdrawal / Transfer Form Submitted'],
    ['Finance',    'Gratuity Settled'],
    ['Finance',    'Leave Encashment Settled'],
    ['Finance',    'Expense Reimbursement Cleared'],
    ['Clearance',  'HOD Clearance'],
    ['Clearance',  'IT Dept Clearance'],
    ['Clearance',  'Admin / Accounts Clearance'],
    ['Clearance',  'Security Clearance'],
];

// ── POST handlers ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fCompany) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // ─ Template tab ─
    if ($action === 'add_tpl_item') {
        $itemName = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        $sort     = (int)($_POST['sort_order'] ?? 0);
        if ($itemName) {
            $db->prepare("INSERT INTO tblFnFTemplate (CompanyId,ItemName,Category,SortOrder) VALUES (?,?,?,?)")
               ->execute([$fCompany, $itemName, $category, $sort]);
            $msg = 'Item added.';
        }
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(array_filter(['success'=>true,'message'=>$msg ?: null,'redirect'=>"fnf.php?company=$fCompany&tab=template"])); exit; }
        header("Location: fnf.php?company=$fCompany&tab=template&msg=" . urlencode($msg)); exit;

    } elseif ($action === 'toggle_tpl_item') {
        $db->prepare("UPDATE tblFnFTemplate SET IsActive=1-IsActive WHERE id=? AND CompanyId=?")->execute([(int)$_POST['id'], $fCompany]);
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>"fnf.php?company=$fCompany&tab=template"]); exit; }
        header("Location: fnf.php?company=$fCompany&tab=template"); exit;

    } elseif ($action === 'delete_tpl_item') {
        $db->prepare("DELETE FROM tblFnFTemplate WHERE id=? AND CompanyId=?")->execute([(int)$_POST['id'], $fCompany]);
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>'Item deleted.','redirect'=>"fnf.php?company=$fCompany&tab=template"]); exit; }
        header("Location: fnf.php?company=$fCompany&tab=template"); exit;

    } elseif ($action === 'seed_defaults') {
        $ins = $db->prepare("INSERT INTO tblFnFTemplate (CompanyId,ItemName,Category,SortOrder) VALUES (?,?,?,?)");
        foreach ($defaultItems as $i => [$cat, $item]) {
            $ins->execute([$fCompany, $item, $cat, $i + 1]);
        }
        $msg = 'Default checklist loaded.';
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$msg,'redirect'=>"fnf.php?company=$fCompany&tab=template"]); exit; }
        header("Location: fnf.php?company=$fCompany&tab=template&msg=" . urlencode($msg)); exit;

    // ─ Settlement tab ─
    } elseif ($action === 'initiate') {
        $empId     = (int)$_POST['employee_id'];
        $initiatedOn = trim($_POST['initiated_on'] ?? date('Y-m-d'));
        if ($empId && $initiatedOn) {
            // Check not already open
            $chk = $db->prepare("SELECT id FROM tblFnFSettlement WHERE CompanyId=? AND EmployeeId=? AND Status='open'");
            $chk->execute([$fCompany, $empId]);
            if ($chk->fetch()) { $msg = 'An open F&F already exists for this employee.'; $msgType = 'warning'; }
            else {
                $db->prepare("INSERT INTO tblFnFSettlement (CompanyId,EmployeeId,InitiatedOn,CreatedBy) VALUES (?,?,?,?)")
                   ->execute([$fCompany, $empId, $initiatedOn, $user['id']]);
                $settId = (int)$db->lastInsertId();
                // Copy template items
                $items = $db->prepare("SELECT * FROM tblFnFTemplate WHERE CompanyId=? AND IsActive=1 ORDER BY Category, SortOrder");
                $items->execute([$fCompany]);
                $ins = $db->prepare("INSERT INTO tblFnFItem (SettlementId,ItemName,Category,SortOrder) VALUES (?,?,?,?)");
                foreach ($items->fetchAll() as $ti) $ins->execute([$settId, $ti['ItemName'], $ti['Category'], $ti['SortOrder']]);
                if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>"fnf.php?company=$fCompany&view=$settId"]); exit; }
                header("Location: fnf.php?company=$fCompany&view=$settId"); exit;
            }
        }
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>[$msg ?: 'Employee and date are required.']]); exit; }
        header("Location: fnf.php?company=$fCompany&msg=" . urlencode($msg) . "&mt=$msgType"); exit;

    } elseif ($action === 'update_item') {
        $itemId  = (int)$_POST['item_id'];
        $status  = in_array($_POST['status'] ?? '', ['pending','done','na']) ? $_POST['status'] : 'pending';
        $remarks = trim($_POST['remarks'] ?? '');
        $doneAt  = $status === 'done' ? date('Y-m-d H:i:s') : null;
        $doneBy  = $status === 'done' ? $user['id'] : null;
        $db->prepare("UPDATE tblFnFItem SET Status=?, Remarks=?, DoneAt=?, DoneBy=? WHERE id=?")
           ->execute([$status, $remarks?:null, $doneAt, $doneBy, $itemId]);
        $sid = (int)$_POST['settlement_id']; if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>"fnf.php?company=$fCompany&view=$sid"]); exit; }
        header("Location: fnf.php?company=$fCompany&view={$_POST['settlement_id']}"); exit;

    } elseif ($action === 'add_item') {
        $settId   = (int)$_POST['settlement_id'];
        $itemName = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? 'General');
        if ($settId && $itemName) {
            $db->prepare("INSERT INTO tblFnFItem (SettlementId,ItemName,Category) VALUES (?,?,?)")
               ->execute([$settId, $itemName, $category]);
        }
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>"fnf.php?company=$fCompany&view=$settId"]); exit; }
        header("Location: fnf.php?company=$fCompany&view=$settId"); exit;

    } elseif ($action === 'add_pay_item') {
        $settId = (int)$_POST['settlement_id'];
        $label  = trim($_POST['label'] ?? '');
        $type   = ($_POST['pay_type'] ?? 'earning') === 'deduction' ? 'deduction' : 'earning';
        $amount = round((float)($_POST['amount'] ?? 0), 2);
        // Verify settlement belongs to this company
        $own = $db->prepare("SELECT id FROM tblFnFSettlement WHERE id=? AND CompanyId=?");
        $own->execute([$settId, $fCompany]);
        if ($settId && $label && $own->fetch()) {
            $db->prepare("INSERT INTO tblFnFPayItem (SettlementId,Label,Type,Amount) VALUES (?,?,?,?)")
               ->execute([$settId, $label, $type, $amount]);
        }
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>"fnf.php?company=$fCompany&view=$settId"]); exit; }
        header("Location: fnf.php?company=$fCompany&view=$settId"); exit;

    } elseif ($action === 'delete_pay_item') {
        $settId = (int)$_POST['settlement_id'];
        $pid    = (int)$_POST['pay_item_id'];
        $db->prepare("DELETE p FROM tblFnFPayItem p JOIN tblFnFSettlement s ON s.id=p.SettlementId AND s.CompanyId=? WHERE p.id=?")
           ->execute([$fCompany, $pid]);
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>"fnf.php?company=$fCompany&view=$settId"]); exit; }
        header("Location: fnf.php?company=$fCompany&view=$settId"); exit;

    } elseif ($action === 'complete') {
        $settId = (int)$_POST['settlement_id'];
        $db->prepare("UPDATE tblFnFSettlement SET Status='completed', CompletedOn=NOW(), UpdatedAt=NOW() WHERE id=? AND CompanyId=?")
           ->execute([$settId, $fCompany]);
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>'Marked as completed.','redirect'=>"fnf.php?company=$fCompany&view=$settId"]); exit; }
        header("Location: fnf.php?company=$fCompany&view=$settId"); exit;

    } elseif ($action === 'reopen') {
        $settId = (int)$_POST['settlement_id'];
        $db->prepare("UPDATE tblFnFSettlement SET Status='open', CompletedOn=NULL, UpdatedAt=NOW() WHERE id=? AND CompanyId=?")
           ->execute([$settId, $fCompany]);
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>'Settlement reopened.','redirect'=>"fnf.php?company=$fCompany&view=$settId"]); exit; }
        header("Location: fnf.php?company=$fCompany&view=$settId"); exit;

    } elseif ($action === 'delete_settlement') {
        $settId = (int)$_POST['settlement_id'];
        $db->prepare("DELETE FROM tblFnFItem WHERE SettlementId=?")->execute([$settId]);
        $db->prepare("DELETE FROM tblFnFSettlement WHERE id=? AND CompanyId=?")->execute([$settId, $fCompany]);
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>'Settlement deleted.','redirect'=>"fnf.php?company=$fCompany"]); exit; }
        header("Location: fnf.php?company=$fCompany"); exit;
    }
}
if (isset($_GET['msg'])) { $msg = $_GET['msg']; $msgType = $_GET['mt'] ?? 'success'; }

// ── Load data ──────────────────────────────────────────────────────────────────
$settlements = [];
$tplItems    = [];
$settRow     = null;
$settItems   = [];
$payItems    = [];
$employees   = [];
$categories  = ['Documents','Returns','Finance','Clearance','General'];

if ($fCompany) {
    if ($tab === 'template' || !$viewId) {
        $s = $db->prepare("SELECT * FROM tblFnFTemplate WHERE CompanyId=? ORDER BY Category, SortOrder, id");
        $s->execute([$fCompany]); $tplItems = $s->fetchAll();
    }
    if ($tab === 'settlements' && !$viewId) {
        $s = $db->prepare(
            "SELECT fs.*, e.Name AS EmpName, e.EmployeeCode, e.Department,
                    (SELECT COUNT(*) FROM tblFnFItem fi WHERE fi.SettlementId=fs.id) AS TotalItems,
                    (SELECT COUNT(*) FROM tblFnFItem fi WHERE fi.SettlementId=fs.id AND fi.Status='done') AS DoneItems
             FROM tblFnFSettlement fs
             JOIN tblEmployee e ON e.id=fs.EmployeeId
             WHERE fs.CompanyId=?
             ORDER BY fs.Status='completed', fs.InitiatedOn DESC"
        );
        $s->execute([$fCompany]); $settlements = $s->fetchAll();
    }
    if ($viewId) {
        $s = $db->prepare("SELECT fs.*, e.Name AS EmpName, e.EmployeeCode, e.Department, e.Designation FROM tblFnFSettlement fs JOIN tblEmployee e ON e.id=fs.EmployeeId WHERE fs.id=? AND fs.CompanyId=?");
        $s->execute([$viewId, $fCompany]); $settRow = $s->fetch();
        if ($settRow) {
            $s = $db->prepare("SELECT fi.*, u.Name AS DoneByName FROM tblFnFItem fi LEFT JOIN tblUser u ON u.id=fi.DoneBy WHERE fi.SettlementId=? ORDER BY fi.Category, fi.SortOrder, fi.id");
            $s->execute([$viewId]); $settItems = $s->fetchAll();
            $s = $db->prepare("SELECT * FROM tblFnFPayItem WHERE SettlementId=? ORDER BY Type='deduction', SortOrder, id");
            $s->execute([$viewId]); $payItems = $s->fetchAll();
        }
    }
    // Employees for initiate form
    $s = $db->prepare("SELECT e.id, e.Name, e.EmployeeCode, e.Department FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId WHERE e.CompanyId=? " . (in_array($user['role'], ['admin','operator'], true)?"AND c.AdminId={$user['scope_id']}":'') . " ORDER BY e.Department, e.Name");
    $s->execute([$fCompany]); $employees = $s->fetchAll();
}

$statusBadge = ['pending'=>'warning','done'=>'success','na'=>'secondary'];
$pageTitle   = 'Full & Final Settlement';
$activePage  = 'doc_fnf';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> py-2"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Company selector + tabs -->
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <form method="GET" class="d-flex gap-2 align-items-center">
    <select name="company" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:160px">
      <?php foreach ($companiesDd as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="hidden" name="tab" value="<?= $tab ?>">
  </form>
  <?php if (!$viewId): ?>
  <ul class="nav nav-tabs mb-0">
    <li class="nav-item"><a class="nav-link <?= $tab==='settlements'?'active':'' ?>" href="fnf.php?company=<?= $fCompany ?>&tab=settlements">Settlements</a></li>
    <li class="nav-item"><a class="nav-link <?= $tab==='template'?'active':'' ?>" href="fnf.php?company=<?= $fCompany ?>&tab=template">Checklist Template</a></li>
  </ul>
  <?php endif; ?>
</div>

<?php if ($viewId && $settRow): ?>
<!-- ── Settlement Detail View ─────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <a href="fnf.php?company=<?= $fCompany ?>" class="btn btn-outline-secondary btn-sm me-2">← Back</a>
    <strong><?= htmlspecialchars($settRow['EmpName']) ?></strong>
    <span class="text-muted small ms-2"><?= htmlspecialchars($settRow['EmployeeCode']?:'') ?> · <?= htmlspecialchars($settRow['Department']??'') ?></span>
    <span class="badge bg-<?= $settRow['Status']==='completed'?'success':'warning' ?> ms-2"><?= ucfirst($settRow['Status']) ?></span>
  </div>
  <div class="d-flex gap-2">
    <a href="fnf_statement.php?company=<?= $fCompany ?>&id=<?= $viewId ?>" target="_blank" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-printer me-1"></i>Print Statement
    </a>
    <?php if ($settRow['Status']==='open'): ?>
    <form method="POST" data-ajax>
      <input type="hidden" name="action" value="complete">
      <input type="hidden" name="company" value="<?= $fCompany ?>">
      <input type="hidden" name="settlement_id" value="<?= $viewId ?>">
      <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Mark as completed?')"><i class="bi bi-check-all me-1"></i>Mark Complete</button>
    </form>
    <?php else: ?>
    <form method="POST" data-ajax>
      <input type="hidden" name="action" value="reopen">
      <input type="hidden" name="company" value="<?= $fCompany ?>">
      <input type="hidden" name="settlement_id" value="<?= $viewId ?>">
      <button type="submit" class="btn btn-outline-warning btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Reopen</button>
    </form>
    <?php endif; ?>
    <form method="POST" onsubmit="return confirm('Delete this settlement and all its items?')" data-ajax>
      <input type="hidden" name="action" value="delete_settlement">
      <input type="hidden" name="company" value="<?= $fCompany ?>">
      <input type="hidden" name="settlement_id" value="<?= $viewId ?>">
      <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
    </form>
  </div>
</div>

<?php
// Group items by category
$byCategory = [];
foreach ($settItems as $item) $byCategory[$item['Category']][] = $item;
$doneCount = count(array_filter($settItems, fn($i) => $i['Status']==='done'));
$naCount   = count(array_filter($settItems, fn($i) => $i['Status']==='na'));
$total     = count($settItems);
$progress  = $total ? round(($doneCount + $naCount) / $total * 100) : 0;
?>

<!-- Progress bar -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <div class="d-flex justify-content-between small text-muted mb-1">
      <span><?= $doneCount ?> done · <?= $naCount ?> N/A · <?= $total - $doneCount - $naCount ?> pending</span>
      <span><?= $progress ?>% complete</span>
    </div>
    <div class="progress" style="height:8px">
      <div class="progress-bar bg-success" style="width:<?= $progress ?>%"></div>
    </div>
  </div>
</div>

<!-- Checklist by category -->
<?php foreach ($byCategory as $cat => $items): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold py-2 small"><?= htmlspecialchars($cat) ?></div>
  <div class="card-body p-0">
    <?php foreach ($items as $item): ?>
    <form method="POST" class="d-flex align-items-center gap-2 px-3 py-2 border-bottom" data-ajax>
      <input type="hidden" name="action" value="update_item">
      <input type="hidden" name="company" value="<?= $fCompany ?>">
      <input type="hidden" name="settlement_id" value="<?= $viewId ?>">
      <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
      <div class="flex-grow-1 fw-semibold small"><?= htmlspecialchars($item['ItemName']) ?></div>
      <input type="text" name="remarks" class="form-control form-control-sm" style="max-width:180px"
             placeholder="Remarks" value="<?= htmlspecialchars($item['Remarks'] ?? '') ?>">
      <select name="status" class="form-select form-select-sm" style="width:100px" onchange="$(this.form).submit()">
        <option value="pending" <?= $item['Status']==='pending'?'selected':'' ?>>Pending</option>
        <option value="done"    <?= $item['Status']==='done'   ?'selected':'' ?>>Done</option>
        <option value="na"      <?= $item['Status']==='na'     ?'selected':'' ?>>N/A</option>
      </select>
      <span class="badge bg-<?= $statusBadge[$item['Status']] ?>"><?= ucfirst($item['Status']) ?></span>
      <?php if ($item['DoneAt']): ?><span class="text-muted small" style="white-space:nowrap"><?= date('d/m/y', strtotime($item['DoneAt'])) ?></span><?php endif; ?>
    </form>
    <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<?php
$payEarn = array_filter($payItems, fn($p) => $p['Type']==='earning');
$payDed  = array_filter($payItems, fn($p) => $p['Type']==='deduction');
$sumEarn = array_sum(array_map(fn($p) => (float)$p['Amount'], $payEarn));
$sumDed  = array_sum(array_map(fn($p) => (float)$p['Amount'], $payDed));
$netPay  = $sumEarn - $sumDed;
?>
<!-- Settlement Amounts -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold small d-flex justify-content-between align-items-center">
    <span><i class="bi bi-cash-coin me-1"></i>Settlement Amounts</span>
    <span class="<?= $netPay>=0?'text-success':'text-danger' ?> fw-bold">Net Payable: ₹<?= number_format($netPay, 2) ?></span>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <div class="fw-semibold small text-primary mb-2">Earnings / Dues to Employee</div>
        <table class="table table-sm mb-2">
          <tbody>
          <?php foreach ($payEarn as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['Label']) ?></td>
            <td class="text-end" style="width:110px">₹<?= number_format((float)$p['Amount'],2) ?></td>
            <td style="width:34px">
              <form method="POST" data-ajax class="d-inline">
                <input type="hidden" name="action" value="delete_pay_item">
                <input type="hidden" name="company" value="<?= $fCompany ?>">
                <input type="hidden" name="settlement_id" value="<?= $viewId ?>">
                <input type="hidden" name="pay_item_id" value="<?= $p['id'] ?>">
                <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$payEarn): ?><tr><td colspan="3" class="text-muted small">No earning lines yet.</td></tr><?php endif; ?>
          </tbody>
          <tfoot><tr class="fw-bold"><td>Total Earnings</td><td class="text-end">₹<?= number_format($sumEarn,2) ?></td><td></td></tr></tfoot>
        </table>
      </div>
      <div class="col-md-6">
        <div class="fw-semibold small text-danger mb-2">Deductions / Recoveries</div>
        <table class="table table-sm mb-2">
          <tbody>
          <?php foreach ($payDed as $p): ?>
          <tr>
            <td><?= htmlspecialchars($p['Label']) ?></td>
            <td class="text-end" style="width:110px">₹<?= number_format((float)$p['Amount'],2) ?></td>
            <td style="width:34px">
              <form method="POST" data-ajax class="d-inline">
                <input type="hidden" name="action" value="delete_pay_item">
                <input type="hidden" name="company" value="<?= $fCompany ?>">
                <input type="hidden" name="settlement_id" value="<?= $viewId ?>">
                <input type="hidden" name="pay_item_id" value="<?= $p['id'] ?>">
                <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$payDed): ?><tr><td colspan="3" class="text-muted small">No deduction lines yet.</td></tr><?php endif; ?>
          </tbody>
          <tfoot><tr class="fw-bold"><td>Total Deductions</td><td class="text-end">₹<?= number_format($sumDed,2) ?></td><td></td></tr></tfoot>
        </table>
      </div>
    </div>
    <form method="POST" class="row g-2 align-items-end border-top pt-3" data-ajax>
      <input type="hidden" name="action" value="add_pay_item">
      <input type="hidden" name="company" value="<?= $fCompany ?>">
      <input type="hidden" name="settlement_id" value="<?= $viewId ?>">
      <div class="col-sm-5">
        <label class="form-label small mb-1">Line item</label>
        <input type="text" name="label" class="form-control form-control-sm" list="fnfLabels" placeholder="e.g. Leave Encashment" required>
        <datalist id="fnfLabels">
          <option value="Pending Salary"><option value="Leave Encashment"><option value="Gratuity"><option value="Bonus"><option value="Notice Pay">
          <option value="Advance Recovery"><option value="Notice Period Recovery"><option value="Loan Recovery"><option value="Asset Recovery">
        </datalist>
      </div>
      <div class="col-sm-3">
        <label class="form-label small mb-1">Type</label>
        <select name="pay_type" class="form-select form-select-sm">
          <option value="earning">Earning / Due</option>
          <option value="deduction">Deduction / Recovery</option>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label small mb-1">Amount (₹)</label>
        <input type="number" name="amount" step="0.01" min="0" class="form-control form-control-sm" required>
      </div>
      <div class="col-sm-2">
        <button type="submit" class="btn btn-primary btn-sm w-100">Add Line</button>
      </div>
    </form>
  </div>
</div>

<!-- Add custom item -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold small">Add Custom Item</div>
  <div class="card-body">
    <form method="POST" class="row g-2" data-ajax>
      <input type="hidden" name="action" value="add_item">
      <input type="hidden" name="company" value="<?= $fCompany ?>">
      <input type="hidden" name="settlement_id" value="<?= $viewId ?>">
      <div class="col-sm-6"><input type="text" name="item_name" class="form-control form-control-sm" placeholder="Item name" required></div>
      <div class="col-sm-4">
        <select name="category" class="form-select form-select-sm">
          <?php foreach ($categories as $cat): ?><option><?= $cat ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2"><button type="submit" class="btn btn-primary btn-sm w-100">Add</button></div>
    </form>
  </div>
</div>

<?php elseif ($tab === 'settlements'): ?>
<!-- ── Settlements list ────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-end mb-2">
  <?php if ($employees): ?>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#initiateModal">
    <i class="bi bi-person-dash me-1"></i>Initiate F&amp;F
  </button>
  <?php endif; ?>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($settlements)): ?>
    <div class="p-4 text-center text-muted">
      <i class="bi bi-clipboard-check fs-2 d-block mb-2"></i>
      No F&amp;F settlements yet. Click "Initiate F&amp;F" to start one.
    </div>
    <?php else: ?>
    <table class="table table-hover table-sm align-middle mb-0">
      <thead class="table-light">
        <tr><th>Employee</th><th>Dept</th><th>Initiated</th><th>Status</th><th>Progress</th><th>Completed</th><th></th></tr>
      </thead>
      <tbody>
      <?php foreach ($settlements as $s):
          $pct = $s['TotalItems'] ? round(($s['DoneItems'] / $s['TotalItems']) * 100) : 0;
      ?>
      <tr>
        <td><div class="fw-semibold"><?= htmlspecialchars($s['EmpName']) ?></div><div class="text-muted small"><?= htmlspecialchars($s['EmployeeCode']?:'') ?></div></td>
        <td class="small"><?= htmlspecialchars($s['Department']??'') ?></td>
        <td class="small"><?= htmlspecialchars($s['InitiatedOn']) ?></td>
        <td><span class="badge bg-<?= $s['Status']==='completed'?'success':'warning' ?>"><?= ucfirst($s['Status']) ?></span></td>
        <td style="min-width:100px">
          <div class="progress" style="height:6px">
            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
          </div>
          <div class="text-muted small"><?= $s['DoneItems'] ?>/<?= $s['TotalItems'] ?></div>
        </td>
        <td class="small"><?= $s['CompletedOn'] ? date('d/m/Y', strtotime($s['CompletedOn'])) : '—' ?></td>
        <td><a href="fnf.php?company=<?= $fCompany ?>&view=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<!-- ── Checklist Template ─────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-2">
  <span class="text-muted small"><?= count($tplItems) ?> item(s)</span>
  <div class="d-flex gap-2">
    <?php if (empty($tplItems)): ?>
    <form method="POST" data-ajax>
      <input type="hidden" name="action" value="seed_defaults">
      <input type="hidden" name="company" value="<?= $fCompany ?>">
      <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-magic me-1"></i>Load Defaults</button>
    </form>
    <?php endif; ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTplModal"><i class="bi bi-plus-lg me-1"></i>Add Item</button>
  </div>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($tplItems)): ?>
    <div class="p-4 text-center text-muted">No checklist items. Click "Load Defaults" for a standard F&amp;F checklist, or add items manually.</div>
    <?php else: ?>
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light"><tr><th>Item</th><th>Category</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($tplItems as $ti): ?>
      <tr class="<?= !$ti['IsActive']?'text-muted':'' ?>">
        <td class="fw-semibold"><?= htmlspecialchars($ti['ItemName']) ?></td>
        <td class="small text-muted"><?= htmlspecialchars($ti['Category']) ?></td>
        <td>
          <form method="POST" class="d-inline" data-ajax>
            <input type="hidden" name="action" value="toggle_tpl_item">
            <input type="hidden" name="company" value="<?= $fCompany ?>">
            <input type="hidden" name="id" value="<?= $ti['id'] ?>">
            <button type="submit" class="badge border-0 bg-<?= $ti['IsActive']?'success':'secondary' ?> cursor-pointer">
              <?= $ti['IsActive']?'Active':'Inactive' ?>
            </button>
          </form>
        </td>
        <td>
          <form method="POST" class="d-inline" onsubmit="return confirm('Delete?')" data-ajax>
            <input type="hidden" name="action" value="delete_tpl_item">
            <input type="hidden" name="company" value="<?= $fCompany ?>">
            <input type="hidden" name="id" value="<?= $ti['id'] ?>">
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
<?php endif; ?>

<!-- Initiate F&F Modal -->
<div class="modal fade" id="initiateModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" data-ajax>
        <input type="hidden" name="action" value="initiate">
        <input type="hidden" name="company" value="<?= $fCompany ?>">
        <div class="modal-header"><h5 class="modal-title">Initiate F&amp;F Settlement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
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
          <div class="mb-3">
            <label class="form-label">Initiation Date</label>
            <input type="date" name="initiated_on" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="alert alert-info py-2 small">
            The settlement will be created with all active items from your <a href="fnf.php?company=<?= $fCompany ?>&tab=template">Checklist Template</a>.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Initiate</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Template Item Modal -->
<div class="modal fade" id="addTplModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" data-ajax>
        <input type="hidden" name="action" value="add_tpl_item">
        <input type="hidden" name="company" value="<?= $fCompany ?>">
        <div class="modal-header"><h5 class="modal-title">Add Checklist Item</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Item Name <span class="text-danger">*</span></label>
            <input type="text" name="item_name" class="form-control" required placeholder="e.g. Experience Letter Issued">
          </div>
          <div class="mb-3">
            <label class="form-label">Category</label>
            <select name="category" class="form-select">
              <?php foreach ($categories as $cat): ?><option><?= $cat ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" value="0">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
