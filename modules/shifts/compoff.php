<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

try { $db->query("SELECT 1 FROM tblCompOff LIMIT 1"); }
catch (PDOException $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

if ($user['role'] === 'superadmin') {
    $companiesDd = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $s = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $s->execute([$user['id']]);
    $companiesDd = $s->fetchAll();
}

$fCompany = (int)($_REQUEST['company'] ?? ($companiesDd[0]['id'] ?? 0));
if ($fCompany && $user['role'] === 'admin') {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$fCompany, $user['id']]);
    if (!$chk->fetch()) $fCompany = 0;
}

$fStatus = in_array($_GET['status'] ?? '', ['pending','approved','rejected','redeemed']) ? $_GET['status'] : '';
$msg = ''; $msgType = 'success';

// ── POST handlers ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fCompany) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $empId    = (int)$_POST['employee_id'];
        $workedOn = trim($_POST['worked_on'] ?? '');
        $reason   = trim($_POST['reason'] ?? '');
        if ($empId && $workedOn) {
            // Verify employee belongs to company
            $chk = $db->prepare("SELECT id FROM tblEmployee WHERE id=? AND CompanyId=?");
            $chk->execute([$empId, $fCompany]);
            if ($chk->fetch()) {
                $db->prepare("INSERT INTO tblCompOff (CompanyId, EmployeeId, WorkedOn, Reason) VALUES (?,?,?,?)")
                   ->execute([$fCompany, $empId, $workedOn, $reason ?: null]);
                $msg = 'Comp off added.';
            }
        }

    } elseif ($action === 'approve') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE tblCompOff SET Status='approved', ApprovedBy=?, UpdatedAt=NOW() WHERE id=? AND CompanyId=?")
           ->execute([$user['id'], $id, $fCompany]);
        $msg = 'Approved.';

    } elseif ($action === 'reject') {
        $id = (int)$_POST['id'];
        $db->prepare("UPDATE tblCompOff SET Status='rejected', ApprovedBy=?, UpdatedAt=NOW() WHERE id=? AND CompanyId=?")
           ->execute([$user['id'], $id, $fCompany]);
        $msg = 'Rejected.';

    } elseif ($action === 'redeem') {
        $id          = (int)$_POST['id'];
        $compOffDate = trim($_POST['comp_off_date'] ?? '');
        if ($compOffDate) {
            $db->prepare("UPDATE tblCompOff SET Status='redeemed', CompOffDate=?, UpdatedAt=NOW() WHERE id=? AND CompanyId=? AND Status='approved'")
               ->execute([$compOffDate, $id, $fCompany]);
            $msg = 'Marked as redeemed.';
        }

    } elseif ($action === 'delete' && $user['role'] === 'superadmin') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM tblCompOff WHERE id=? AND CompanyId=?")->execute([$id, $fCompany]);
        $msg = 'Deleted.';
    }

    if ($isAjax) {
        $redir = "compoff.php?company=$fCompany&status=$fStatus";
        header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$msg,'redirect'=>$redir]); exit;
    }
    header("Location: compoff.php?company=$fCompany&status=$fStatus&msg=" . urlencode($msg) . '&mt=' . $msgType);
    exit;
}
if (isset($_GET['msg'])) { $msg = $_GET['msg']; $msgType = $_GET['mt'] ?? 'success'; }

// ── Load data ──────────────────────────────────────────────────────────────────
$records = [];
$employees = [];

if ($fCompany) {
    $where  = ['co.CompanyId = ?'];
    $params = [$fCompany];
    if ($fStatus) { $where[] = 'co.Status = ?'; $params[] = $fStatus; }
    $wsql = 'WHERE ' . implode(' AND ', $where);

    $stmt = $db->prepare(
        "SELECT co.*, e.Name AS EmpName, e.EmployeeCode, e.Department,
                u.Name AS ApproverName
         FROM tblCompOff co
         JOIN tblEmployee e ON e.id = co.EmployeeId
         LEFT JOIN tblUser u ON u.id = co.ApprovedBy
         $wsql
         ORDER BY co.WorkedOn DESC, e.Name"
    );
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    // Load active employees for Add form
    $s = $db->prepare(
        "SELECT e.id, e.Name, e.EmployeeCode, e.Department
         FROM tblEmployee e
         JOIN tblCompany c ON c.id = e.CompanyId
         WHERE e.CompanyId=? AND e.Status='active'
         " . ($user['role'] === 'admin' ? "AND c.AdminId={$user['id']}" : '') . "
         ORDER BY e.Department, ISNULL(e.Sr), e.Sr, e.Name"
    );
    $s->execute([$fCompany]);
    $employees = $s->fetchAll();
}

// Status counts for tabs
$counts = [''=>0,'pending'=>0,'approved'=>0,'rejected'=>0,'redeemed'=>0];
foreach ($records as $r) { $counts['']++; $counts[$r['Status']]++; }
if ($fStatus) {
    // counts are already filtered; reload totals for tab display
    if ($fCompany) {
        $sc = $db->prepare("SELECT Status, COUNT(*) AS cnt FROM tblCompOff WHERE CompanyId=? GROUP BY Status");
        $sc->execute([$fCompany]);
        $counts = [''=>0,'pending'=>0,'approved'=>0,'rejected'=>0,'redeemed'=>0];
        foreach ($sc->fetchAll() as $r) { $counts[$r['Status']] = (int)$r['cnt']; $counts[''] += (int)$r['cnt']; }
    }
}

$statusBadge = [
    'pending'  => 'warning',
    'approved' => 'success',
    'rejected' => 'danger',
    'redeemed' => 'info',
];

$pageTitle  = 'Comp Off';
$activePage = 'compoff';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> py-2"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <form method="GET" class="d-flex gap-2 align-items-center">
    <select name="company" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:160px">
      <?php foreach ($companiesDd as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="hidden" name="status" value="<?= htmlspecialchars($fStatus) ?>">
  </form>
  <?php if ($fCompany && $employees): ?>
  <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
    <i class="bi bi-plus-lg me-1"></i>Add Comp Off
  </button>
  <?php endif; ?>
</div>

<!-- Status tabs -->
<ul class="nav nav-tabs mb-3">
  <?php
  $tabs = ['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'redeemed' => 'Redeemed'];
  foreach ($tabs as $tval => $tlabel):
  ?>
  <li class="nav-item">
    <a class="nav-link <?= $fStatus===$tval?'active':'' ?>"
       href="compoff.php?company=<?= $fCompany ?>&status=<?= $tval ?>">
      <?= $tlabel ?>
      <?php if ($counts[$tval]): ?>
      <span class="badge bg-<?= $tval ? $statusBadge[$tval] : 'secondary' ?> ms-1"><?= $counts[$tval] ?></span>
      <?php endif; ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <?php if (empty($records)): ?>
    <div class="p-4 text-center text-muted">
      <i class="bi bi-calendar-check fs-3 d-block mb-2"></i>
      No comp off records<?= $fStatus ? ' with status "' . htmlspecialchars($fStatus) . '"' : '' ?>.
    </div>
    <?php else: ?>
    <table class="table table-hover table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Employee</th>
          <th>Dept</th>
          <th>Worked On</th>
          <th>Reason</th>
          <th>Status</th>
          <th>Comp Off Date</th>
          <th>Approved By</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($records as $r): ?>
      <tr>
        <td>
          <div class="fw-semibold"><?= htmlspecialchars($r['EmpName']) ?></div>
          <div class="text-muted small"><code><?= htmlspecialchars($r['EmployeeCode'] ?: '—') ?></code></div>
        </td>
        <td class="small"><?= htmlspecialchars($r['Department'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['WorkedOn']) ?></td>
        <td class="small text-muted"><?= htmlspecialchars($r['Reason'] ?? '—') ?></td>
        <td>
          <span class="badge bg-<?= $statusBadge[$r['Status']] ?? 'secondary' ?>">
            <?= ucfirst($r['Status']) ?>
          </span>
        </td>
        <td class="small"><?= $r['CompOffDate'] ? htmlspecialchars($r['CompOffDate']) : '—' ?></td>
        <td class="small text-muted"><?= $r['ApproverName'] ? htmlspecialchars($r['ApproverName']) : '—' ?></td>
        <td>
          <div class="d-flex gap-1 flex-wrap">
            <?php if ($r['Status'] === 'pending'): ?>
            <form method="POST" class="d-inline" data-ajax>
              <input type="hidden" name="action" value="approve">
              <input type="hidden" name="company" value="<?= $fCompany ?>">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-success" title="Approve"><i class="bi bi-check-lg"></i></button>
            </form>
            <form method="POST" class="d-inline" data-ajax>
              <input type="hidden" name="action" value="reject">
              <input type="hidden" name="company" value="<?= $fCompany ?>">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-danger" title="Reject"><i class="bi bi-x-lg"></i></button>
            </form>
            <?php endif; ?>
            <?php if ($r['Status'] === 'approved'): ?>
            <button class="btn btn-sm btn-info" title="Redeem"
                    onclick="document.getElementById('redeemDate<?= $r['id'] ?>').style.display='block';this.style.display='none'">
              <i class="bi bi-calendar-check"></i>
            </button>
            <div id="redeemDate<?= $r['id'] ?>" style="display:none">
              <form method="POST" class="d-flex gap-1 align-items-center" data-ajax>
                <input type="hidden" name="action" value="redeem">
                <input type="hidden" name="company" value="<?= $fCompany ?>">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <input type="date" name="comp_off_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required style="width:130px">
                <button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i></button>
              </form>
            </div>
            <?php endif; ?>
            <?php if ($user['role'] === 'superadmin'): ?>
            <form method="POST" class="d-inline" data-ajax onsubmit="return confirm('Delete this record?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="company" value="<?= $fCompany ?>">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- ── Add Comp Off Modal ───────────────────────────────────────────────────── -->
<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="compoff.php?company=<?= $fCompany ?>&status=<?= htmlspecialchars($fStatus) ?>" data-ajax>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="company" value="<?= $fCompany ?>">
        <div class="modal-header"><h5 class="modal-title">Add Comp Off</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Employee <span class="text-danger">*</span></label>
            <select name="employee_id" class="form-select" required>
              <option value="">— Select Employee —</option>
              <?php
              $prevD = null;
              foreach ($employees as $emp):
                  if ($emp['Department'] !== $prevD):
                      if ($prevD !== null) echo '</optgroup>';
                      echo '<optgroup label="' . htmlspecialchars($emp['Department'] ?? 'No Dept') . '">';
                      $prevD = $emp['Department'];
                  endif;
              ?>
              <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['Name']) ?> (<?= htmlspecialchars($emp['EmployeeCode'] ?: '—') ?>)</option>
              <?php endforeach; if ($prevD !== null) echo '</optgroup>'; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Worked On Date <span class="text-danger">*</span></label>
            <input type="date" name="worked_on" class="form-control" required value="<?= date('Y-m-d') ?>">
            <div class="form-text">The date the employee worked on their off day / holiday.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Reason</label>
            <input type="text" name="reason" class="form-control" placeholder="Optional reason / note">
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
