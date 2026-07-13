<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sms_helper.php';
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
$companyIds = array_column($companiesDd, 'id');
$fCompany   = (int)($_REQUEST['company'] ?? ($companiesDd[0]['id'] ?? 0));
if ($fCompany && !in_array($fCompany, $companyIds, true)) $fCompany = 0;

$msg = '';

// ── Approve / Reject ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fCompany) {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $ids    = array_map('intval', (array)($_POST['ot_ids'] ?? []));
    if ($ids && in_array($action, ['approve','reject'], true)) {
        $new = $action === 'approve' ? 'approved' : 'rejected';
        $in  = implode(',', array_fill(0, count($ids), '?'));
        $upd = $db->prepare("UPDATE tblOvertime SET Status=?, ApprovedBy=?, ApprovedAt=NOW()
                      WHERE id IN ($in) AND CompanyId=? AND Status='pending'");
        $upd->execute([$new, $user['id'], ...$ids, $fCompany]);
        $n = $upd->rowCount();

        // Post-OT SMS to HR Manager on approval
        if ($action === 'approve') {
            $hrMobile = hrManagerMobile($db, $fCompany);
            if ($hrMobile) {
                $sum = $db->prepare("SELECT COUNT(*) c, COALESCE(SUM(OTHours),0) h FROM tblOvertime WHERE id IN ($in) AND CompanyId=?");
                $sum->execute([...$ids, $fCompany]);
                $r = $sum->fetch();
                $coName = (string)($db->query("SELECT Name FROM tblCompany WHERE id=" . (int)$fCompany)->fetchColumn() ?: 'Company');
                $hrsStr = rtrim(rtrim(number_format((float)$r['h'], 2), '0'), '.');
                @sendOtSms(
                    $hrMobile,
                    'approved',
                    ['company' => $coName, 'count' => $r['c'], 'hours' => $hrsStr],
                    "OT approved: {$r['c']} entries, $hrsStr hrs at $coName."
                );
            }
        }
        $msg = ucfirst($action) . "d $n OT entr(y/ies).";
    }
}

// Pending OT list
$pending = [];
if ($fCompany) {
    $s = $db->prepare(
        "SELECT o.id, o.OTDate, o.OTHours, o.Reason, e.Name, e.EmployeeCode, e.Department
         FROM tblOvertime o JOIN tblEmployee e ON e.id=o.EmployeeId
         WHERE o.CompanyId=? AND o.Status='pending'
         ORDER BY o.OTDate DESC, e.Name"
    );
    $s->execute([$fCompany]);
    $pending = $s->fetchAll();
}

$pageTitle  = 'OT Approvals';
$activePage  = 'ot_approvals';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div>
    <h5 class="mb-0">Overtime Approvals</h5>
    <div class="text-muted small">Approve or reject pending OT. Only approved OT is counted in payroll.</div>
  </div>
  <form method="GET" class="d-flex gap-2">
    <select name="company" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:180px">
      <?php foreach ($companiesDd as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $c['id']==$fCompany?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
      <?php endforeach; ?>
    </select>
    <a href="index.php?company=<?= $fCompany ?>" class="btn btn-outline-secondary btn-sm">OT Entry</a>
  </form>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<?php if (!$fCompany): ?>
<div class="alert alert-warning">Please select a company.</div>
<?php elseif (!$pending): ?>
<div class="alert alert-info"><i class="bi bi-check2-circle me-1"></i>No pending OT for this company.</div>
<?php else: ?>
<form method="POST" id="apprForm">
  <?= csrf_field() ?>
  <input type="hidden" name="company" value="<?= $fCompany ?>">
  <input type="hidden" name="action" id="apprAction" value="approve">
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span class="fw-semibold">Pending OT <span class="badge bg-warning text-dark"><?= count($pending) ?></span></span>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success btn-sm" onclick="document.getElementById('apprAction').value='approve'">
          <i class="bi bi-check-lg"></i> Approve Selected
        </button>
        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="document.getElementById('apprAction').value='reject'">
          <i class="bi bi-x-lg"></i> Reject Selected
        </button>
      </div>
    </div>
    <div class="card-body p-0" style="max-height:66vh;overflow:auto">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light sticky-top">
          <tr>
            <th style="width:34px"><input type="checkbox" id="chkAll" class="form-check-input" checked></th>
            <th>Date</th><th>Code</th><th>Employee</th><th>Department</th><th class="text-end">OT Hrs</th><th>Reason</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($pending as $p): ?>
          <tr>
            <td><input type="checkbox" name="ot_ids[]" value="<?= $p['id'] ?>" class="form-check-input row-chk" checked></td>
            <td class="small"><?= date('d-M-Y', strtotime($p['OTDate'])) ?></td>
            <td class="small text-muted"><?= htmlspecialchars($p['EmployeeCode']) ?></td>
            <td class="fw-semibold"><?= htmlspecialchars($p['Name']) ?></td>
            <td class="small"><?= htmlspecialchars($p['Department'] ?? '—') ?></td>
            <td class="text-end"><?= number_format((float)$p['OTHours'], 2) ?></td>
            <td class="small"><?= htmlspecialchars($p['Reason'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</form>
<script>
(function () {
  var all = document.getElementById('chkAll');
  var rows = function () { return Array.prototype.slice.call(document.querySelectorAll('.row-chk')); };
  all.addEventListener('change', function () { rows().forEach(function (c) { c.checked = all.checked; }); });
  document.getElementById('apprForm').addEventListener('submit', function (e) {
    if (!rows().some(function (c) { return c.checked; })) { e.preventDefault(); alert('Select at least one row.'); }
  });
})();
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
