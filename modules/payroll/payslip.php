<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/_payslip_render.php';
requireLogin();

$db   = getDb();
$user = currentUser();

$runId = (int)($_GET['run'] ?? 0);
$empId = (int)($_GET['emp'] ?? 0);

if (!$runId || !$empId) { http_response_code(400); die('Invalid request.'); }

// Load run
$s = $db->prepare("SELECT r.*, c.Name AS CompanyName, c.Address AS CompanyAddress, c.Phone AS CompanyPhone, c.Email AS CompanyEmail
    FROM tblPayrollRun r JOIN tblCompany c ON c.id=r.CompanyId WHERE r.id=?");
$s->execute([$runId]);
$run = $s->fetch();
if (!$run) { http_response_code(404); die('Run not found.'); }

// Access check
if (in_array($user['role'], ['admin','operator'], true)) {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$run['CompanyId'], $user['scope_id']]);
    if (!$chk->fetch()) { http_response_code(403); die('Access denied.'); }
} elseif ($user['role'] === 'user') {
    if ($run['CompanyId'] != $user['company_id']) { http_response_code(403); die('Access denied.'); }
}

// Load detail
$s = $db->prepare("SELECT d.*, e.Name, e.EmployeeCode, e.Department, e.Designation,
        e.UAN, e.PfNo, e.EsiNo, e.PanNo, e.BankName, e.BankAcNo, e.IFSCCode, e.JoinDate
    FROM tblPayrollDetail d JOIN tblEmployee e ON e.id=d.EmployeeId
    WHERE d.RunId=? AND d.EmployeeId=?");
$s->execute([$runId, $empId]);
$det = $s->fetch();
if (!$det) { http_response_code(404); die('Employee detail not found.'); }

$earns = json_decode($det['EarningsJson'] ?: '[]', true);
$deds  = json_decode($det['DeductionsJson'] ?: '[]', true);

// Format month
$dt   = \DateTime::createFromFormat('Y-m', $run['RunMonth']);
$mLabel = $dt ? $dt->format('F Y') : $run['RunMonth'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Pay Slip — <?= htmlspecialchars($det['Name']) ?> — <?= $mLabel ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; font-size: 12px; color: #222; background: #fff; padding: 10mm; }
@page { size: A4; margin: 10mm; }
@media print { .no-print { display: none !important; } body { padding: 0; } }

.toolbar { display:flex; gap:10px; margin-bottom:14px; }
.toolbar a, .toolbar button { padding:6px 14px; border-radius:6px; font-size:12px; cursor:pointer; text-decoration:none; border:1px solid #ccc; }
.btn-print { background:#0071e3; color:#fff; border-color:#0071e3; }
<?= payslipCss() ?>
</style>
</head>
<body>

<div class="toolbar no-print">
  <button class="btn-print" onclick="window.print()">🖨 Print Pay Slip</button>
  <a href="run.php?company=<?= $run['CompanyId'] ?>&month=<?= htmlspecialchars($run['RunMonth']) ?>">← Back</a>
</div>

<?= payslipSlipHtml($run, $det, $mLabel) ?>

</body>
</html>
