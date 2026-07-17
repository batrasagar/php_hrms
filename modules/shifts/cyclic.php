<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

try { $db->query("SELECT 1 FROM tblShiftCycle LIMIT 1"); }
catch (PDOException $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

// Company comes from the global topbar switcher
$fCompany = activeCompanyId($db, $user);

$msg = ''; $msgType = 'success';
$viewCycleId = (int)($_GET['cycle'] ?? 0);

// ── POST handlers ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fCompany) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_cycle') {
        $name = trim($_POST['name'] ?? '');
        $days = max(2, min(366, (int)($_POST['cycle_days'] ?? 14)));
        $desc = trim($_POST['description'] ?? '');
        if (!$name) { $msg = 'Cycle name is required.'; $msgType = 'danger'; }
        else {
            $db->prepare("INSERT INTO tblShiftCycle (CompanyId, Name, CycleDays, Description) VALUES (?,?,?,?)")
               ->execute([$fCompany, $name, $days, $desc ?: null]);
            $viewCycleId = (int)$db->lastInsertId();
            $msg = 'Cycle created.';
        }

    } elseif ($action === 'edit_cycle') {
        $id   = (int)$_POST['cycle_id'];
        $name = trim($_POST['name'] ?? '');
        $days = max(2, min(366, (int)($_POST['cycle_days'] ?? 14)));
        $desc = trim($_POST['description'] ?? '');
        if ($id && $name) {
            $db->prepare("UPDATE tblShiftCycle SET Name=?, CycleDays=?, Description=? WHERE id=? AND CompanyId=?")
               ->execute([$name, $days, $desc ?: null, $id, $fCompany]);
            $viewCycleId = $id;
            $msg = 'Cycle updated.';
        }

    } elseif ($action === 'toggle_cycle') {
        $id = (int)$_POST['cycle_id'];
        $db->prepare("UPDATE tblShiftCycle SET IsActive = 1 - IsActive WHERE id=? AND CompanyId=?")->execute([$id, $fCompany]);
        $viewCycleId = $id;

    } elseif ($action === 'delete_cycle') {
        $id = (int)$_POST['cycle_id'];
        $db->prepare("DELETE FROM tblShiftCycleDay WHERE CycleId=?")->execute([$id]);
        $db->prepare("UPDATE tblEmployeeShiftCycle SET IsActive=0 WHERE CycleId=?")->execute([$id]);
        $db->prepare("DELETE FROM tblShiftCycle WHERE id=? AND CompanyId=?")->execute([$id, $fCompany]);
        $msg = 'Cycle deleted.';
        $viewCycleId = 0;

    } elseif ($action === 'save_days') {
        $cycleId = (int)$_POST['cycle_id'];
        $dayShifts = $_POST['day_shift'] ?? [];
        $cycleRow = null;
        $s = $db->prepare("SELECT id, CycleDays FROM tblShiftCycle WHERE id=? AND CompanyId=?");
        $s->execute([$cycleId, $fCompany]);
        $cycleRow = $s->fetch();
        if ($cycleRow) {
            $db->prepare("DELETE FROM tblShiftCycleDay WHERE CycleId=?")->execute([$cycleId]);
            $ins = $db->prepare("INSERT INTO tblShiftCycleDay (CycleId, DayNo, ShiftId) VALUES (?,?,?)");
            for ($d = 1; $d <= (int)$cycleRow['CycleDays']; $d++) {
                $shiftId = ($dayShifts[$d] ?? '') !== '' ? (int)$dayShifts[$d] : null;
                $ins->execute([$cycleId, $d, $shiftId]);
            }
            $msg = 'Day schedule saved.';
            $viewCycleId = $cycleId;
        }

    } elseif ($action === 'assign_employee') {
        $cycleId    = (int)$_POST['cycle_id'];
        $empId      = (int)$_POST['employee_id'];
        $startDate  = trim($_POST['start_date'] ?? '');
        if ($cycleId && $empId && $startDate) {
            $db->prepare("UPDATE tblEmployeeShiftCycle SET IsActive=0 WHERE EmployeeId=?")->execute([$empId]);
            $db->prepare("INSERT INTO tblEmployeeShiftCycle (EmployeeId, CompanyId, CycleId, CycleStartDate, IsActive) VALUES (?,?,?,?,1)")
               ->execute([$empId, $fCompany, $cycleId, $startDate]);
            $msg = 'Employee assigned to cycle.';
            $viewCycleId = $cycleId;
        }

    } elseif ($action === 'remove_assignment') {
        $assId = (int)$_POST['assignment_id'];
        $cycleId = (int)$_POST['cycle_id'];
        $db->prepare("DELETE FROM tblEmployeeShiftCycle WHERE id=?")->execute([$assId]);
        $msg = 'Assignment removed.';
        $viewCycleId = $cycleId;
    }

    if ($isAjax) {
        $redir = "cyclic.php?company=$fCompany&cycle=$viewCycleId";
        if ($msgType === 'danger') {
            header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>[$msg]]); exit;
        }
        header('Content-Type: application/json'); echo json_encode(array_filter(['success'=>true,'message'=>$msg ?: null,'redirect'=>$redir])); exit;
    }
    header("Location: cyclic.php?company=$fCompany&cycle=$viewCycleId" . ($msg ? '&msg=' . urlencode($msg) . '&mt=' . $msgType : ''));
    exit;
}

if (isset($_GET['msg'])) { $msg = $_GET['msg']; $msgType = $_GET['mt'] ?? 'success'; }

// ── Load data ──────────────────────────────────────────────────────────────────
$cycles = [];
if ($fCompany) {
    $s = $db->prepare("SELECT * FROM tblShiftCycle WHERE CompanyId=? ORDER BY Name");
    $s->execute([$fCompany]);
    $cycles = $s->fetchAll();
}

$cycleRow  = null;
$cycleDays = [];
$cycleEmps = [];
$allShifts = [];
$unassignedEmps = [];

if ($viewCycleId && $fCompany) {
    $s = $db->prepare("SELECT * FROM tblShiftCycle WHERE id=? AND CompanyId=?");
    $s->execute([$viewCycleId, $fCompany]);
    $cycleRow = $s->fetch();

    if ($cycleRow) {
        // Day assignments
        $s = $db->prepare("SELECT DayNo, ShiftId FROM tblShiftCycleDay WHERE CycleId=? ORDER BY DayNo");
        $s->execute([$viewCycleId]);
        foreach ($s->fetchAll() as $row) $cycleDays[$row['DayNo']] = (int)$row['ShiftId'];

        // Employees assigned to this cycle
        $s = $db->prepare(
            "SELECT esc.id AS AssignmentId, esc.CycleStartDate, e.id AS EmpId,
                    e.Name AS EmpName, e.EmployeeCode, e.Department
             FROM tblEmployeeShiftCycle esc
             JOIN tblEmployee e ON e.id = esc.EmployeeId
             WHERE esc.CycleId=? AND esc.IsActive=1
             ORDER BY e.Department, e.Name"
        );
        $s->execute([$viewCycleId]);
        $cycleEmps = $s->fetchAll();

        // Available shifts
        $s = $db->prepare("SELECT id, ShiftName FROM tblShift WHERE CompanyId=? AND IsActive=1 ORDER BY ShiftName");
        $s->execute([$fCompany]);
        $allShifts = $s->fetchAll();

        // Employees not assigned to this cycle (active employees of company)
        $assignedIds = array_column($cycleEmps, 'EmpId');
        $s = $db->prepare(
            "SELECT e.id, e.Name, e.EmployeeCode, e.Department
             FROM tblEmployee e
             JOIN tblCompany c ON c.id = e.CompanyId
             WHERE e.CompanyId=? AND e.Status='active'
             " . (in_array($user['role'], ['admin','operator'], true) ? "AND c.AdminId={$user['scope_id']}" : '') . "
             ORDER BY e.Department, ISNULL(e.Sr), e.Sr, e.Name"
        );
        $s->execute([$fCompany]);
        foreach ($s->fetchAll() as $emp) {
            if (!in_array($emp['id'], $assignedIds)) $unassignedEmps[] = $emp;
        }
    }
}

// Build shift name map for display
$shiftNameMap = [];
foreach ($allShifts as $sh) $shiftNameMap[$sh['id']] = $sh['ShiftName'];

$pageTitle  = 'Cyclic Shifts';
$activePage = 'shift_cyclic';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?> py-2"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<div class="row g-3">

<!-- ── Left: Cycle list ───────────────────────────────────────────────────── -->
<div class="col-lg-4">
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <span class="fw-semibold">Cycles</span>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCycleModal">
        <i class="bi bi-plus-lg"></i>
      </button>
    </div>
    <div class="list-group list-group-flush">
      <?php if (empty($cycles)): ?>
      <div class="p-3 text-muted small text-center">No cycles yet. Click + to add one.</div>
      <?php endif; ?>
      <?php foreach ($cycles as $cy): ?>
      <a href="cyclic.php?company=<?= $fCompany ?>&cycle=<?= $cy['id'] ?>"
         class="list-group-item list-group-item-action d-flex justify-content-between align-items-center
                <?= $cy['id'] == $viewCycleId ? 'active' : '' ?>
                <?= !$cy['IsActive'] ? 'text-muted' : '' ?>">
        <div>
          <div class="fw-semibold"><?= htmlspecialchars($cy['Name']) ?></div>
          <small class="<?= $cy['id'] == $viewCycleId ? 'opacity-75' : 'text-muted' ?>">
            <?= $cy['CycleDays'] ?>-day cycle
            <?php if (!$cy['IsActive']): ?> <span class="badge bg-secondary ms-1">Inactive</span><?php endif; ?>
          </small>
        </div>
        <i class="bi bi-chevron-right"></i>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ── Right: Cycle detail ───────────────────────────────────────────────── -->
<div class="col-lg-8">
<?php if (!$viewCycleId || !$cycleRow): ?>
  <div class="card border-0 shadow-sm">
    <div class="card-body text-center text-muted py-5">
      <i class="bi bi-arrow-repeat fs-1 d-block mb-2"></i>
      Select a cycle from the list, or create a new one.
    </div>
  </div>
<?php else: ?>

  <!-- Cycle header -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <div>
        <span class="fw-semibold fs-6"><?= htmlspecialchars($cycleRow['Name']) ?></span>
        <span class="text-muted small ms-2"><?= $cycleRow['CycleDays'] ?>-day rotation</span>
        <?php if ($cycleRow['Description']): ?>
        <span class="text-muted small ms-2">— <?= htmlspecialchars($cycleRow['Description']) ?></span>
        <?php endif; ?>
      </div>
      <div class="d-flex gap-1">
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCycleModal">
          <i class="bi bi-pencil"></i>
        </button>
        <form method="POST" class="d-inline" data-ajax>
          <input type="hidden" name="company" value="<?= $fCompany ?>">
          <input type="hidden" name="action" value="toggle_cycle">
          <input type="hidden" name="cycle_id" value="<?= $cycleRow['id'] ?>">
          <button type="submit" class="btn btn-sm <?= $cycleRow['IsActive']?'btn-outline-warning':'btn-outline-success' ?>">
            <i class="bi bi-<?= $cycleRow['IsActive']?'pause-circle':'play-circle' ?>"></i>
          </button>
        </form>
        <form method="POST" class="d-inline" data-ajax onsubmit="return confirm('Delete this cycle and all its assignments?')">
          <input type="hidden" name="company" value="<?= $fCompany ?>">
          <input type="hidden" name="action" value="delete_cycle">
          <input type="hidden" name="cycle_id" value="<?= $cycleRow['id'] ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
        </form>
      </div>
    </div>
  </div>

  <!-- Day Schedule -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">Day Schedule</div>
    <div class="card-body">
      <form method="POST" action="cyclic.php?company=<?= $fCompany ?>&cycle=<?= $cycleRow['id'] ?>" data-ajax>
        <input type="hidden" name="action" value="save_days">
        <input type="hidden" name="cycle_id" value="<?= $cycleRow['id'] ?>">
        <?php if (empty($allShifts)): ?>
        <div class="alert alert-warning py-2">No active shifts for this company. <a href="add.php">Add shifts</a> first.</div>
        <?php else: ?>
        <div class="row g-2">
          <?php for ($d = 1; $d <= (int)$cycleRow['CycleDays']; $d++):
              $currentShiftId = $cycleDays[$d] ?? null;
          ?>
          <div class="col-6 col-sm-4 col-md-3">
            <label class="form-label small mb-1 text-muted">Day <?= $d ?></label>
            <select name="day_shift[<?= $d ?>]" class="form-select form-select-sm">
              <option value="" <?= !$currentShiftId ? 'selected' : '' ?>>Rest Day</option>
              <?php foreach ($allShifts as $sh): ?>
              <option value="<?= $sh['id'] ?>" <?= (int)$currentShiftId === $sh['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($sh['ShiftName']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endfor; ?>
        </div>
        <div class="mt-3">
          <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-save me-1"></i>Save Schedule</button>
        </div>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Employee Assignments -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <span class="fw-semibold">Employee Assignments <span class="badge bg-secondary"><?= count($cycleEmps) ?></span></span>
      <?php if ($unassignedEmps): ?>
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignEmpModal">
        <i class="bi bi-person-plus me-1"></i>Assign Employee
      </button>
      <?php endif; ?>
    </div>
    <?php if (empty($cycleEmps)): ?>
    <div class="p-3 text-muted small text-center">No employees assigned to this cycle yet.</div>
    <?php else: ?>
    <div class="card-body p-0">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr><th>Name</th><th>Code</th><th>Dept</th><th>Cycle Start</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($cycleEmps as $ea): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($ea['EmpName']) ?></td>
          <td class="small text-muted"><code><?= htmlspecialchars($ea['EmployeeCode'] ?: '—') ?></code></td>
          <td class="small"><?= htmlspecialchars($ea['Department'] ?? '—') ?></td>
          <td class="small"><?= htmlspecialchars($ea['CycleStartDate']) ?></td>
          <td>
            <form method="POST" class="d-inline" data-ajax onsubmit="return confirm('Remove this assignment?')">
              <input type="hidden" name="action" value="remove_assignment">
              <input type="hidden" name="company" value="<?= $fCompany ?>">
              <input type="hidden" name="cycle_id" value="<?= $cycleRow['id'] ?>">
              <input type="hidden" name="assignment_id" value="<?= $ea['AssignmentId'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

<?php endif; // end cycle detail ?>
</div>
</div>

<!-- ── Add Cycle Modal ──────────────────────────────────────────────────────── -->
<div class="modal fade" id="addCycleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="cyclic.php?company=<?= $fCompany ?>" data-ajax>
        <input type="hidden" name="action" value="add_cycle">
        <input type="hidden" name="company" value="<?= $fCompany ?>">
        <div class="modal-header"><h5 class="modal-title">Add Cyclic Shift</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Cycle Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required placeholder="e.g. 14-Day Rotating">
          </div>
          <div class="mb-3">
            <label class="form-label">Cycle Length (days) <span class="text-danger">*</span></label>
            <input type="number" name="cycle_days" class="form-control" value="14" min="2" max="366" required>
            <div class="form-text">How many days before the pattern repeats.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <input type="text" name="description" class="form-control" placeholder="Optional note">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create Cycle</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($cycleRow): ?>
<!-- ── Edit Cycle Modal ─────────────────────────────────────────────────────── -->
<div class="modal fade" id="editCycleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="cyclic.php?company=<?= $fCompany ?>&cycle=<?= $cycleRow['id'] ?>" data-ajax>
        <input type="hidden" name="action" value="edit_cycle">
        <input type="hidden" name="company" value="<?= $fCompany ?>">
        <input type="hidden" name="cycle_id" value="<?= $cycleRow['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Edit Cycle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Cycle Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($cycleRow['Name']) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Cycle Length (days)</label>
            <input type="number" name="cycle_days" class="form-control" value="<?= $cycleRow['CycleDays'] ?>" min="2" max="366" required>
            <div class="form-text text-warning"><i class="bi bi-exclamation-triangle"></i> Changing cycle length clears the saved day schedule.</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <input type="text" name="description" class="form-control" value="<?= htmlspecialchars($cycleRow['Description'] ?? '') ?>">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Assign Employee Modal ────────────────────────────────────────────────── -->
<?php if ($unassignedEmps): ?>
<div class="modal fade" id="assignEmpModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="cyclic.php?company=<?= $fCompany ?>&cycle=<?= $cycleRow['id'] ?>" data-ajax>
        <input type="hidden" name="action" value="assign_employee">
        <input type="hidden" name="company" value="<?= $fCompany ?>">
        <input type="hidden" name="cycle_id" value="<?= $cycleRow['id'] ?>">
        <div class="modal-header"><h5 class="modal-title">Assign Employee to Cycle</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Employee <span class="text-danger">*</span></label>
            <select name="employee_id" class="form-select" required>
              <option value="">— Select Employee —</option>
              <?php
              $prevD = null;
              foreach ($unassignedEmps as $ue):
                  if ($ue['Department'] !== $prevD):
                      if ($prevD !== null) echo '</optgroup>';
                      echo '<optgroup label="' . htmlspecialchars($ue['Department'] ?? 'No Dept') . '">';
                      $prevD = $ue['Department'];
                  endif;
              ?>
              <option value="<?= $ue['id'] ?>"><?= htmlspecialchars($ue['Name']) ?> (<?= htmlspecialchars($ue['EmployeeCode'] ?: '—') ?>)</option>
              <?php endforeach; if ($prevD !== null) echo '</optgroup>'; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Cycle Start Date <span class="text-danger">*</span></label>
            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-01') ?>" required>
            <div class="form-text">The calendar date that maps to Day 1 of this cycle.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Assign</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>
<?php endif; // end cycleRow modals ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
