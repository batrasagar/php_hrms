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

function canAccess($db, $user, $cid) {
    if ($user['role'] === 'superadmin') return true;
    $s = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $s->execute([$cid, $user['scope_id']]); return (bool)$s->fetch();
}

// Delete policy
if (isset($_GET['delete']) && $fCompany && canAccess($db, $user, $fCompany)) {
    $pid = (int)$_GET['delete'];
    $db->prepare("DELETE FROM tblLeavePolicyDetail WHERE PolicyId=?")->execute([$pid]);
    $db->prepare("DELETE FROM tblLeavePolicy WHERE id=? AND CompanyId=?")->execute([$pid, $fCompany]);
    header("Location: policy.php?company=$fCompany"); exit;
}

$editId  = (int)($_GET['edit'] ?? 0);
$editRec = null;
$editDetails = [];
$msg = ''; $err = '';

// Load for edit
if ($editId && $fCompany) {
    $s = $db->prepare("SELECT * FROM tblLeavePolicy WHERE id=? AND CompanyId=?");
    $s->execute([$editId, $fCompany]);
    $editRec = $s->fetch();
    if ($editRec) {
        $s2 = $db->prepare("SELECT * FROM tblLeavePolicyDetail WHERE PolicyId=?");
        $s2->execute([$editId]);
        foreach ($s2->fetchAll() as $d) {
            $editDetails[$d['LeaveTypeId']] = $d['DaysPerYear'];
        }
    }
}

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_policy'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $cid    = (int)($_POST['company_id'] ?? 0);
    $pid    = (int)($_POST['policy_id'] ?? 0);
    $pname  = trim($_POST['PolicyName'] ?? '');
    $ltIds  = $_POST['lt_ids']  ?? [];
    $ltDays = $_POST['lt_days'] ?? [];

    if (!$cid || !canAccess($db, $user, $cid)) {
        $err = 'Access denied.';
    } elseif (!$pname) {
        $err = 'Policy name is required.';
    } else {
        try {
            if ($pid) {
                $db->prepare("UPDATE tblLeavePolicy SET PolicyName=? WHERE id=? AND CompanyId=?")->execute([$pname, $pid, $cid]);
                $db->prepare("DELETE FROM tblLeavePolicyDetail WHERE PolicyId=?")->execute([$pid]);
            } else {
                $db->prepare("INSERT INTO tblLeavePolicy (CompanyId, PolicyName) VALUES (?,?)")->execute([$cid, $pname]);
                $pid = $db->lastInsertId();
            }
            // Save detail lines
            $ins = $db->prepare("INSERT INTO tblLeavePolicyDetail (PolicyId, LeaveTypeId, DaysPerYear) VALUES (?,?,?)");
            foreach ($ltIds as $i => $ltId) {
                $ltId = (int)$ltId;
                $days = max(0, (float)($ltDays[$i] ?? 0));
                if ($ltId && $days > 0) {
                    $ins->execute([$pid, $ltId, $days]);
                }
            }
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>'Policy saved.']); exit; }
            header("Location: policy.php?company=$cid&msg=Saved"); exit;
        } catch (PDOException $e) {
            $err = $e->getMessage();
        }
    }
    if (!empty($isAjax) && $err) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>[$err]]); exit; }
}

if (isset($_GET['msg'])) $msg = 'Policy saved successfully.';

// Load data
$policies    = [];
$leaveTypes  = [];
if ($fCompany) {
    $s = $db->prepare("SELECT p.*, COUNT(d.id) AS TypeCount
        FROM tblLeavePolicy p LEFT JOIN tblLeavePolicyDetail d ON d.PolicyId = p.id
        WHERE p.CompanyId=? GROUP BY p.id ORDER BY p.PolicyName");
    $s->execute([$fCompany]); $policies = $s->fetchAll();

    $s2 = $db->prepare("SELECT * FROM tblLeaveType WHERE CompanyId=? AND IsActive=1 ORDER BY Code");
    $s2->execute([$fCompany]); $leaveTypes = $s2->fetchAll();
}

// For list view — load detail per policy
$policyDetails = [];
foreach ($policies as $p) {
    $s = $db->prepare(
        "SELECT lt.Code, lt.Name, d.DaysPerYear FROM tblLeavePolicyDetail d
         JOIN tblLeaveType lt ON lt.id = d.LeaveTypeId WHERE d.PolicyId=?"
    );
    $s->execute([$p['id']]);
    $policyDetails[$p['id']] = $s->fetchAll();
}

$pageTitle  = 'Leave Policies';
$activePage = 'leave_policy';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-success py-2"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>

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
    </form>
  </div>
</div>

<?php if ($fCompany): ?>
<?php if (empty($leaveTypes)): ?>
<div class="alert alert-warning">
  <i class="bi bi-exclamation-triangle me-2"></i>
  No leave types defined for this company. <a href="types.php?company=<?= $fCompany ?>" class="alert-link">Add leave types first</a>.
</div>
<?php else: ?>
<div class="row g-3">
  <!-- Form -->
  <div class="col-md-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">
        <?= $editRec ? 'Edit Policy: ' . htmlspecialchars($editRec['PolicyName']) : 'Add Leave Policy' ?>
      </div>
      <div class="card-body">
        <form method="POST" data-ajax>
          <input type="hidden" name="save_policy" value="1">
          <input type="hidden" name="company_id" value="<?= $fCompany ?>">
          <input type="hidden" name="policy_id" value="<?= $editRec ? $editRec['id'] : 0 ?>">
          <div class="mb-3">
            <label class="form-label small">Policy Name <span class="text-danger">*</span></label>
            <input type="text" name="PolicyName" class="form-control form-control-sm" required
                   value="<?= htmlspecialchars($editRec['PolicyName'] ?? '') ?>" placeholder="General Staff Policy">
          </div>
          <p class="small fw-semibold mb-2">Leave Entitlements (days/year)</p>
          <table class="table table-sm mb-3">
            <thead class="table-light"><tr><th>Leave Type</th><th style="width:100px">Days/Year</th></tr></thead>
            <tbody>
            <?php foreach ($leaveTypes as $lt): ?>
            <tr>
              <td>
                <input type="hidden" name="lt_ids[]" value="<?= $lt['id'] ?>">
                <span class="badge bg-secondary me-1"><?= htmlspecialchars($lt['Code']) ?></span>
                <?= htmlspecialchars($lt['Name']) ?>
              </td>
              <td>
                <input type="number" name="lt_days[]" class="form-control form-control-sm"
                       min="0" max="365" step="0.5"
                       value="<?= number_format((float)($editDetails[$lt['id']] ?? 0), 1) ?>">
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i><?= $editRec ? 'Update' : 'Add Policy' ?></button>
            <?php if ($editRec): ?>
            <a href="policy.php?company=<?= $fCompany ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- List -->
  <div class="col-md-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">Policies (<?= count($policies) ?>)</div>
      <div class="card-body p-0">
        <?php if (empty($policies)): ?>
        <div class="p-4 text-center text-muted">No policies added yet.</div>
        <?php else: ?>
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr><th>Policy Name</th><th>Entitlements</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php foreach ($policies as $p): ?>
          <tr>
            <td class="fw-semibold"><?= htmlspecialchars($p['PolicyName']) ?></td>
            <td>
              <?php foreach ($policyDetails[$p['id']] as $d): ?>
              <span class="badge bg-light text-dark border me-1">
                <?= htmlspecialchars($d['Code']) ?>: <?= number_format($d['DaysPerYear'],1) ?>d
              </span>
              <?php endforeach; ?>
              <?php if (empty($policyDetails[$p['id']])): ?>
              <span class="text-muted small">No entitlements</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="policy.php?company=<?= $fCompany ?>&edit=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-1"><i class="bi bi-pencil"></i></a>
              <a href="policy.php?company=<?= $fCompany ?>&delete=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger py-0 px-1"
                 onclick="return confirm('Delete this policy and all its detail lines?')"><i class="bi bi-trash"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php else: ?>
<div class="alert alert-info">Select a company to manage leave policies.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
