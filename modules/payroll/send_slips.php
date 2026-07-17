<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/smtp_helper.php';
require_once __DIR__ . '/_payslip_render.php';
requireAdmin();
requirePermission('payroll_run.view');

$db   = getDb();
$user = currentUser();
$runId = (int)($_GET['run'] ?? ($_POST['run'] ?? 0));
if (!$runId) { http_response_code(400); die('Invalid request.'); }

// Load run + company
$s = $db->prepare("SELECT r.*, c.Name AS CompanyName, c.Address AS CompanyAddress, c.Phone AS CompanyPhone
    FROM tblPayrollRun r JOIN tblCompany c ON c.id=r.CompanyId WHERE r.id=?");
$s->execute([$runId]);
$run = $s->fetch();
if (!$run) { http_response_code(404); die('Run not found.'); }

// Access check
if (in_array($user['role'], ['admin','operator'], true)) {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$run['CompanyId'], $user['scope_id']]);
    if (!$chk->fetch()) { http_response_code(403); die('Access denied.'); }
}

$dt     = DateTime::createFromFormat('Y-m', $run['RunMonth']);
$mLabel = $dt ? $dt->format('F Y') : $run['RunMonth'];

// Load all detail rows (fields required by the slip renderer + contact details)
$s = $db->prepare("SELECT d.*, e.Name, e.EmployeeCode, e.Department, e.Designation,
        e.UAN, e.PfNo, e.EsiNo, e.PanNo, e.BankName, e.BankAcNo, e.IFSCCode, e.JoinDate,
        e.Email AS EmpEmail, e.Phone AS EmpPhone, e.PhoneNo AS EmpPhoneNo
    FROM tblPayrollDetail d JOIN tblEmployee e ON e.id=d.EmployeeId
    WHERE d.RunId=? ORDER BY e.Name");
$s->execute([$runId]);
$details = $s->fetchAll();
$byEmp = [];
foreach ($details as $d) $byEmp[(int)$d['EmployeeId']] = $d;

$flash = ''; $flashType = 'success';

// ── POST: email slips to selected employees ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'email') {
    requirePermission('payroll_run.edit');
    csrf_verify();
    $ids = array_map('intval', (array)($_POST['emp_ids'] ?? []));
    $cfg = getSmtpCfg();
    $sent = 0; $noEmail = 0; $failed = 0;
    foreach ($ids as $eid) {
        if (!isset($byEmp[$eid])) continue;
        $d  = $byEmp[$eid];
        $to = filter_var(trim($d['EmpEmail'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$to) { $noEmail++; continue; }
        $html = payslipEmailHtml($run, $d, $mLabel);
        $res  = SimpleMailer::send($cfg, $to, "Salary Slip — {$mLabel} — {$run['CompanyName']}", $html);
        if (!empty($res['ok'])) $sent++; else $failed++;
    }
    $flash = "Emailed $sent slip(s)."
           . ($noEmail ? " $noEmail skipped (no email on file)." : '')
           . ($failed ? " $failed failed (check SMTP settings)." : '');
    $flashType = $failed ? 'warning' : 'success';
}

/** Normalise a phone to wa.me digits (assume +91 for 10-digit Indian numbers). */
function waPhone(?string $p1, ?string $p2): string {
    $p = preg_replace('/\D+/', '', $p2 ?: ($p1 ?? ''));
    if (strlen($p) === 10) $p = '91' . $p;
    return $p;
}

$pageTitle  = 'Send Salary Slips';
$activePage = 'payroll_run';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div>
    <h5 class="mb-0">Send Salary Slips — <?= htmlspecialchars($mLabel) ?></h5>
    <div class="text-muted small"><?= htmlspecialchars($run['CompanyName']) ?> &middot; <?= count($details) ?> employee(s)</div>
  </div>
  <a href="run.php?company=<?= $run['CompanyId'] ?>&month=<?= htmlspecialchars($run['RunMonth']) ?>" class="btn btn-outline-secondary btn-sm">← Back to Run</a>
</div>

<?php if ($flash): ?><div class="alert alert-<?= $flashType ?>"><?= htmlspecialchars($flash) ?></div><?php endif; ?>

<div class="alert alert-info py-2 small" data-no-toast>
  <i class="bi bi-info-circle me-1"></i>
  <strong>Email</strong> sends the full slip inline to each employee's email (via SMTP).
  <strong>WhatsApp</strong> opens a chat with a net-pay summary message — WhatsApp can't attach the slip from a link, so use it for a quick notification (or share the printed PDF manually).
</div>

<form method="POST" id="slipForm">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="email">
  <input type="hidden" name="run" value="<?= $runId ?>">
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span class="fw-semibold">Employees</span>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-success btn-sm" id="btnEmailSel">
          <i class="bi bi-envelope"></i> Email Selected
        </button>
      </div>
    </div>
    <div class="card-body p-0" style="max-height:66vh;overflow:auto">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light sticky-top">
          <tr>
            <th style="width:34px"><input type="checkbox" id="chkAll" class="form-check-input" checked></th>
            <th>Employee</th><th>Code</th><th>Email</th><th>Mobile</th>
            <th class="text-end">Net Pay</th><th>Slip</th><th>WhatsApp</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($details as $d):
          $eid   = (int)$d['EmployeeId'];
          $email = trim($d['EmpEmail'] ?? '');
          $wa    = waPhone($d['EmpPhone'] ?? '', $d['EmpPhoneNo'] ?? '');
          $waMsg = "Hi {$d['Name']}, your salary slip for {$mLabel} — Net Pay Rs. " . number_format((float)$d['NetSalary'], 2) . ". Regards, {$run['CompanyName']}";
        ?>
          <tr>
            <td><input type="checkbox" name="emp_ids[]" value="<?= $eid ?>" class="form-check-input row-chk" checked></td>
            <td class="fw-semibold"><?= htmlspecialchars($d['Name']) ?></td>
            <td class="small text-muted"><?= htmlspecialchars($d['EmployeeCode']) ?></td>
            <td class="small"><?= $email ? htmlspecialchars($email) : '<span class="text-danger">— none —</span>' ?></td>
            <td class="small"><?= htmlspecialchars($d['EmpPhoneNo'] ?: ($d['EmpPhone'] ?: '—')) ?></td>
            <td class="text-end">₹<?= number_format((float)$d['NetSalary'], 2) ?></td>
            <td>
              <a href="payslip.php?run=<?= $runId ?>&emp=<?= $eid ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-1" title="View / Print">
                <i class="bi bi-eye"></i>
              </a>
            </td>
            <td>
              <?php if ($wa): ?>
                <a href="https://wa.me/<?= $wa ?>?text=<?= rawurlencode($waMsg) ?>" target="_blank" class="btn btn-sm btn-outline-success py-0 px-1" title="WhatsApp">
                  <i class="bi bi-whatsapp"></i>
                </a>
              <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$details): ?><tr><td colspan="8" class="text-center text-muted py-4">No employees in this run.</td></tr><?php endif; ?>
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
  document.getElementById('slipForm').addEventListener('submit', function (e) {
    var n = rows().filter(function (c) { return c.checked; }).length;
    if (!n) { e.preventDefault(); showToast && showToast('Select at least one employee.', 'warning'); return; }
    if (!confirm('Email the salary slip to ' + n + ' selected employee(s)?')) { e.preventDefault(); return; }
    var b = document.getElementById('btnEmailSel');
    b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending…';
  });
})();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
