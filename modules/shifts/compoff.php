<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

try { $db->query("SELECT 1 FROM tblCompOff LIMIT 1"); }
catch (PDOException $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

// Company comes from the global topbar switcher
$fCompany = activeCompanyId($db, $user);

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

    } elseif ($action === 'bulk_add') {
        $ids    = $_POST['emp_ids'] ?? [];
        $from   = trim($_POST['from_date'] ?? '');
        $to     = trim($_POST['to_date'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $idsInt = array_values(array_filter(array_map('intval', (array)$ids)));

        if (!$idsInt || !$from) {
            $msg = 'Select at least one employee and a date.'; $msgType = 'danger';
        } else {
            if (!$to || $to < $from) $to = $from;
            // Build capped date list (guards against a huge range)
            $dates = [];
            for ($cur = strtotime($from), $end = strtotime($to); $cur <= $end && count($dates) < 60; $cur = strtotime('+1 day', $cur)) {
                $dates[] = date('Y-m-d', $cur);
            }
            // Keep only employees that belong to this company
            $ph = implode(',', array_fill(0, count($idsInt), '?'));
            $vs = $db->prepare("SELECT id FROM tblEmployee WHERE CompanyId=? AND id IN ($ph)");
            $vs->execute(array_merge([$fCompany], $idsInt));
            $valid = array_column($vs->fetchAll(), 'id');

            $exists = $db->prepare("SELECT 1 FROM tblCompOff WHERE CompanyId=? AND EmployeeId=? AND WorkedOn=? LIMIT 1");
            $ins    = $db->prepare("INSERT INTO tblCompOff (CompanyId, EmployeeId, WorkedOn, Reason) VALUES (?,?,?,?)");
            $added = 0; $skipped = 0;
            foreach ($valid as $eid) {
                foreach ($dates as $d) {
                    $exists->execute([$fCompany, (int)$eid, $d]);
                    if ($exists->fetch()) { $skipped++; continue; }
                    $ins->execute([$fCompany, (int)$eid, $d, $reason ?: null]);
                    $added++;
                }
            }
            $msg = "Comp off added: $added" . ($skipped ? " ($skipped already existed, skipped)." : '.');
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
            $upd = $db->prepare("UPDATE tblCompOff SET Status='redeemed', CompOffDate=?, UpdatedAt=NOW() WHERE id=? AND CompanyId=? AND Status='approved'");
            $upd->execute([$compOffDate, $id, $fCompany]);
            if ($upd->rowCount() > 0) {
                // Auto-credit: mark the redeemed day as a full-day off in attendance.
                // LeaveTypeId=NULL so it never touches EL/CL/SL balances; shows as 'L'.
                $eid = $db->prepare("SELECT EmployeeId FROM tblCompOff WHERE id=? AND CompanyId=?");
                $eid->execute([$id, $fCompany]);
                $empId = (int)$eid->fetchColumn();
                if ($empId) {
                    $db->prepare(
                        "INSERT INTO tblLeave (CompanyId, EmployeeId, LeaveDate, LeaveType, LeaveTypeId, LeaveCode, Reason, CreatedBy)
                         VALUES (?,?,?, 'full_day', NULL, 'CO', 'Comp off redeemed', ?)
                         ON DUPLICATE KEY UPDATE LeaveType='full_day', LeaveTypeId=NULL, LeaveCode='CO', Reason='Comp off redeemed'"
                    )->execute([$fCompany, $empId, $compOffDate, $user['id']]);
                }
            }
            $msg = 'Marked as redeemed.';
        }

    } elseif ($action === 'delete' && $user['role'] === 'superadmin') {
        $id  = (int)$_POST['id'];
        $rec = $db->prepare("SELECT EmployeeId, CompOffDate, Status FROM tblCompOff WHERE id=? AND CompanyId=?");
        $rec->execute([$id, $fCompany]);
        $r = $rec->fetch();
        $db->prepare("DELETE FROM tblCompOff WHERE id=? AND CompanyId=?")->execute([$id, $fCompany]);
        // Remove the auto-credited off day, if this record had been redeemed
        if ($r && $r['Status'] === 'redeemed' && $r['CompOffDate']) {
            $db->prepare("DELETE FROM tblLeave WHERE CompanyId=? AND EmployeeId=? AND LeaveDate=? AND LeaveCode='CO'")
               ->execute([$fCompany, $r['EmployeeId'], $r['CompOffDate']]);
        }
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
         " . (in_array($user['role'], ['admin','operator'], true) ? "AND c.AdminId={$user['scope_id']}" : '') . "
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

<div class="d-flex justify-content-end align-items-center flex-wrap gap-2 mb-3">
  <?php if ($fCompany && $employees): ?>
  <div class="d-flex gap-2">
    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkModal">
      <i class="bi bi-people me-1"></i>Bulk Add
    </button>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
      <i class="bi bi-plus-lg me-1"></i>Add Comp Off
    </button>
  </div>
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

<!-- ── Bulk Add Comp Off Modal ──────────────────────────────────────────────── -->
<div class="modal fade" id="bulkModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" action="compoff.php?company=<?= $fCompany ?>&status=<?= htmlspecialchars($fStatus) ?>" data-ajax>
        <input type="hidden" name="action" value="bulk_add">
        <input type="hidden" name="company" value="<?= $fCompany ?>">
        <div class="modal-header"><h5 class="modal-title">Bulk Add Comp Off</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-sm-4">
              <label class="form-label">Worked On — From <span class="text-danger">*</span></label>
              <input type="date" name="from_date" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">To <small class="text-muted">(optional)</small></label>
              <input type="date" name="to_date" class="form-control">
              <div class="form-text">Leave blank for a single day. Max 60 days.</div>
            </div>
            <div class="col-sm-4">
              <label class="form-label">Reason</label>
              <input type="text" name="reason" class="form-control" placeholder="Optional note">
            </div>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label mb-0 fw-semibold">Employees</label>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="bulkChkAll">
              <label class="form-check-label small" for="bulkChkAll">Select all</label>
            </div>
          </div>
          <div class="border rounded p-2" style="max-height:280px;overflow-y:auto">
            <?php
            $prevD = null;
            foreach ($employees as $emp):
                if ($emp['Department'] !== $prevD):
                    $prevD = $emp['Department'];
            ?>
            <div class="small fw-semibold text-muted mt-2 mb-1"><?= htmlspecialchars($emp['Department'] ?: 'No Dept') ?></div>
            <?php endif; ?>
            <div class="form-check">
              <input class="form-check-input bulk-emp" type="checkbox" name="emp_ids[]" value="<?= $emp['id'] ?>" id="bemp<?= $emp['id'] ?>">
              <label class="form-check-label" for="bemp<?= $emp['id'] ?>">
                <?= htmlspecialchars($emp['Name']) ?> <span class="text-muted small">(<?= htmlspecialchars($emp['EmployeeCode'] ?: '—') ?>)</span>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Add Comp Off</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
document.getElementById('bulkChkAll')?.addEventListener('change', function(){
  document.querySelectorAll('.bulk-emp').forEach(c => c.checked = this.checked);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
