<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();
$msg  = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

$db->exec("CREATE TABLE IF NOT EXISTS tblEmployeeAdvance (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    CompanyId   INT UNSIGNED  NOT NULL,
    EmployeeId  INT UNSIGNED  NOT NULL,
    AdvanceDate DATE          NOT NULL,
    Amount      DECIMAL(10,2) NOT NULL,
    Purpose     VARCHAR(500)  NOT NULL DEFAULT '',
    TotalEMI    INT           NOT NULL DEFAULT 1,
    EMIPaid     INT           NOT NULL DEFAULT 0,
    EMIAmount   DECIMAL(10,2) NOT NULL DEFAULT 0,
    Status      ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
    Remarks     TEXT          NULL,
    CreatedBy   INT UNSIGNED  NULL,
    CreatedAt   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_company  (CompanyId),
    INDEX idx_employee (EmployeeId)
) ENGINE=InnoDB");

if ($user['role'] === 'superadmin') {
    $companiesDd = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['scope_id']]);
    $companiesDd = $stmt->fetchAll();
}

$fCompany = (int)($_GET['company'] ?? ($companiesDd[0]['id'] ?? 0));
$fStatus  = trim($_GET['status'] ?? '');
$fEmp     = (int)($_GET['emp'] ?? 0);

function canAccess(PDO $db, array $user, int $companyId): bool {
    if ($user['role'] === 'superadmin') return true;
    $s = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $s->execute([$companyId, $user['scope_id']]);
    return (bool)$s->fetch();
}

// ── Actions ───────────────────────────────────────────────────────────────
$qs = http_build_query(array_filter(['company' => $fCompany, 'status' => $fStatus, 'emp' => $fEmp]));

if (isset($_GET['delete']) && $user['role'] === 'superadmin') {
    $did = (int)$_GET['delete'];
    $r   = $db->prepare("SELECT CompanyId FROM tblEmployeeAdvance WHERE id=?");
    $r->execute([$did]);
    $row = $r->fetch();
    if ($row && canAccess($db, $user, (int)$row['CompanyId'])) {
        $db->prepare("DELETE FROM tblEmployeeAdvance WHERE id=?")->execute([$did]);
        $_SESSION['flash'] = 'Advance record deleted.';
    }
    header("Location: index.php?$qs"); exit;
}

if (isset($_GET['cancel_adv'])) {
    $cid = (int)$_GET['cancel_adv'];
    $r   = $db->prepare("SELECT CompanyId FROM tblEmployeeAdvance WHERE id=? AND Status='active'");
    $r->execute([$cid]);
    $row = $r->fetch();
    if ($row && canAccess($db, $user, (int)$row['CompanyId'])) {
        $db->prepare("UPDATE tblEmployeeAdvance SET Status='cancelled' WHERE id=?")->execute([$cid]);
        $_SESSION['flash'] = 'Advance cancelled.';
    }
    header("Location: index.php?$qs"); exit;
}

if (isset($_GET['pay_emi'])) {
    $pid = (int)$_GET['pay_emi'];
    $r   = $db->prepare("SELECT id, CompanyId, TotalEMI, EMIPaid FROM tblEmployeeAdvance WHERE id=? AND Status='active'");
    $r->execute([$pid]);
    $row = $r->fetch();
    if ($row && canAccess($db, $user, (int)$row['CompanyId'])) {
        $newPaid   = (int)$row['EMIPaid'] + 1;
        $newStatus = ($newPaid >= (int)$row['TotalEMI']) ? 'completed' : 'active';
        $db->prepare("UPDATE tblEmployeeAdvance SET EMIPaid=?, Status=? WHERE id=?")
           ->execute([$newPaid, $newStatus, $pid]);
        $_SESSION['flash'] = $newStatus === 'completed'
            ? 'EMI marked paid — Advance fully recovered!'
            : 'EMI marked as paid.';
    }
    header("Location: index.php?$qs"); exit;
}

// ── Load advances ─────────────────────────────────────────────────────────
$advances = [];
$summary  = ['count' => 0, 'amount' => 0, 'recovered' => 0, 'outstanding' => 0];

if ($fCompany) {
    if (!canAccess($db, $user, $fCompany)) $fCompany = 0;
    if ($fCompany) {
        $where  = ['a.CompanyId = ?'];
        $params = [$fCompany];
        if ($fStatus) { $where[] = 'a.Status = ?'; $params[] = $fStatus; }
        if ($fEmp)    { $where[] = 'a.EmployeeId = ?'; $params[] = $fEmp; }

        $stmt = $db->prepare(
            "SELECT a.*, e.Name AS EmpName, e.EmployeeCode, e.Department
             FROM tblEmployeeAdvance a
             JOIN tblEmployee e ON e.id = a.EmployeeId
             WHERE " . implode(' AND ', $where) . "
             ORDER BY a.AdvanceDate DESC, a.id DESC"
        );
        $stmt->execute($params);
        $advances = $stmt->fetchAll();

        foreach ($advances as $a) {
            $rec = $a['EMIPaid'] * $a['EMIAmount'];
            $out = max(0, $a['Amount'] - $rec);
            $summary['count']++;
            $summary['amount']      += $a['Amount'];
            $summary['recovered']   += $rec;
            $summary['outstanding'] += $out;
        }
    }
}

$empsDd = [];
if ($fCompany) {
    $s = $db->prepare(
        "SELECT id, Name, EmployeeCode FROM tblEmployee
         WHERE CompanyId=? AND Status='active' ORDER BY Name"
    );
    $s->execute([$fCompany]);
    $empsDd = $s->fetchAll();
}

$pageTitle  = 'Employee Advances';
$activePage = 'advance';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-success py-2"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Filter -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end" data-filter>
      <div class="col-sm-6 col-md-3">
        <label class="form-label small mb-1">Company</label>
        <select name="company" class="form-select form-select-sm" onchange="$(this.form).trigger('submit')">
          <option value="">— Select Company —</option>
          <?php foreach ($companiesDd as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($fCompany && !empty($empsDd)): ?>
      <div class="col-sm-6 col-md-3">
        <label class="form-label small mb-1">Employee</label>
        <select name="emp" class="form-select form-select-sm" onchange="$(this.form).trigger('submit')">
          <option value="">All Employees</option>
          <?php foreach ($empsDd as $e): ?>
          <option value="<?= $e['id'] ?>" <?= $fEmp==$e['id']?'selected':'' ?>>
            <?= htmlspecialchars(($e['EmployeeCode'] ? $e['EmployeeCode'].' — ' : '') . $e['Name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?>
      <input type="hidden" name="emp" value="">
      <?php endif; ?>
      <div class="col-sm-4 col-md-2">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm" onchange="$(this.form).trigger('submit')">
          <option value="">All</option>
          <option value="active"    <?= $fStatus==='active'   ?'selected':'' ?>>Active</option>
          <option value="completed" <?= $fStatus==='completed'?'selected':'' ?>>Completed</option>
          <option value="cancelled" <?= $fStatus==='cancelled'?'selected':'' ?>>Cancelled</option>
        </select>
      </div>
      <div class="col-auto">
        <a href="index.php?company=<?= $fCompany ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
      </div>
    </form>
  </div>
</div>

<div id="filter-results">
<?php if ($fCompany): ?>

<?php if ($summary['count'] > 0): ?>
<!-- Summary cards -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center py-3">
      <div class="fs-4 fw-bold text-primary"><?= $summary['count'] ?></div>
      <div class="small text-muted">Total Advances</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center py-3">
      <div class="fs-4 fw-bold">&#8377;<?= number_format($summary['amount'], 0) ?></div>
      <div class="small text-muted">Amount Given</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center py-3">
      <div class="fs-4 fw-bold text-success">&#8377;<?= number_format($summary['recovered'], 0) ?></div>
      <div class="small text-muted">Recovered</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm text-center py-3">
      <div class="fs-4 fw-bold text-danger">&#8377;<?= number_format($summary['outstanding'], 0) ?></div>
      <div class="small text-muted">Outstanding</div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Table -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">
      Advances
      <?php if ($summary['count']): ?>
      <span class="badge bg-secondary ms-1"><?= $summary['count'] ?></span>
      <?php endif; ?>
    </span>
    <a href="add.php?company=<?= $fCompany ?>" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>New Advance
    </a>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover table-sm align-middle mb-0" id="tblAdvance">
      <thead class="table-light">
        <tr>
          <th>Employee</th>
          <th>Dept</th>
          <th>Date</th>
          <th class="text-end">Amount</th>
          <th class="text-center">EMI (Paid/Total)</th>
          <th class="text-end">EMI Amt</th>
          <th class="text-end">Recovered</th>
          <th class="text-end">Outstanding</th>
          <th>Purpose</th>
          <th class="text-center">Status</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($advances as $a):
        $recovered   = $a['EMIPaid'] * $a['EMIAmount'];
        $outstanding = max(0, $a['Amount'] - $recovered);
        $pct         = $a['TotalEMI'] > 0 ? round($a['EMIPaid'] / $a['TotalEMI'] * 100) : 0;
        $sc = ['active' => 'bg-warning text-dark', 'completed' => 'bg-success', 'cancelled' => 'bg-secondary'];
        $statusClass = $sc[$a['Status']] ?? 'bg-light text-dark';
        $rowQs = http_build_query(array_filter(['company' => $fCompany, 'status' => $fStatus, 'emp' => $fEmp]));
      ?>
      <tr>
        <td>
          <div class="fw-semibold"><?= htmlspecialchars($a['EmpName']) ?></div>
          <?php if ($a['EmployeeCode']): ?>
          <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($a['EmployeeCode']) ?></div>
          <?php endif; ?>
        </td>
        <td class="small text-muted"><?= htmlspecialchars($a['Department'] ?? '—') ?></td>
        <td class="small"><?= htmlspecialchars($a['AdvanceDate']) ?></td>
        <td class="text-end fw-semibold">&#8377;<?= number_format($a['Amount'], 0) ?></td>
        <td class="text-center">
          <div class="d-flex flex-column align-items-center gap-1">
            <span class="badge bg-light text-dark border"><?= $a['EMIPaid'] ?> / <?= $a['TotalEMI'] ?></span>
            <?php if ($a['TotalEMI'] > 0): ?>
            <div class="progress w-100" style="height:4px;min-width:60px">
              <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
            </div>
            <?php endif; ?>
          </div>
        </td>
        <td class="text-end small">&#8377;<?= number_format($a['EMIAmount'], 0) ?></td>
        <td class="text-end small text-success">&#8377;<?= number_format($recovered, 0) ?></td>
        <td class="text-end small <?= $outstanding > 0 ? 'fw-semibold text-danger' : 'text-success' ?>">
          &#8377;<?= number_format($outstanding, 0) ?>
        </td>
        <td class="small" style="max-width:160px">
          <?php if ($a['Purpose']): ?>
          <span title="<?= htmlspecialchars($a['Purpose']) ?>">
            <?= htmlspecialchars(mb_strimwidth($a['Purpose'], 0, 40, '…')) ?>
          </span>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td class="text-center">
          <span class="badge <?= $statusClass ?>"><?= ucfirst($a['Status']) ?></span>
        </td>
        <td class="text-center" style="white-space:nowrap">
          <?php if ($a['Status'] === 'active'): ?>
          <a href="index.php?pay_emi=<?= $a['id'] ?>&<?= htmlspecialchars($rowQs) ?>"
             class="btn btn-sm btn-outline-success" title="Mark 1 EMI Paid"
             onclick="return confirm('Mark 1 EMI as paid for <?= htmlspecialchars(addslashes($a['EmpName'])) ?>?\n\nPaid: <?= $a['EMIPaid'] ?> / <?= $a['TotalEMI'] ?>')">
            <i class="bi bi-check-circle"></i>
          </a>
          <a href="add.php?id=<?= $a['id'] ?>&company=<?= $fCompany ?>" class="btn btn-sm btn-outline-primary" title="Edit">
            <i class="bi bi-pencil"></i>
          </a>
          <a href="index.php?cancel_adv=<?= $a['id'] ?>&<?= htmlspecialchars($rowQs) ?>"
             class="btn btn-sm btn-outline-warning" title="Cancel Advance"
             onclick="return confirm('Cancel advance for <?= htmlspecialchars(addslashes($a['EmpName'])) ?>?')">
            <i class="bi bi-x-circle"></i>
          </a>
          <?php endif; ?>
          <?php if ($user['role'] === 'superadmin'): ?>
          <a href="index.php?delete=<?= $a['id'] ?>&<?= htmlspecialchars($rowQs) ?>"
             class="btn btn-sm btn-outline-danger" title="Delete"
             onclick="return confirm('Permanently delete this advance record? This cannot be undone.')">
            <i class="bi bi-trash"></i>
          </a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<div class="alert alert-info">Select a company to view advance records.</div>
<?php endif; ?>
</div><!-- /#filter-results -->

<?php
$extraJs = <<<'JS'
<script>
function initAdvanceTable(){
  if(!$('#tblAdvance').length) return;
  if($.fn.DataTable.isDataTable('#tblAdvance')) $('#tblAdvance').DataTable().destroy();
  $('#tblAdvance').DataTable({
    order:[[2,'desc']],
    pageLength:25,
    columnDefs:[{orderable:false,targets:[10]}],
    language:{emptyTable:'No advance records found.'}
  });
}
$(()=>{
  initAdvanceTable();
  $(document).on('filter:done', initAdvanceTable);
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
