<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db   = getDb();
$user = currentUser();

$runId = (int)($_GET['run'] ?? 0);
if (!$runId) { http_response_code(400); die('Invalid request.'); }

$s = $db->prepare("SELECT r.*, c.Name AS CompanyName, c.Address AS CompanyAddress, c.BankName AS CoBankName
    FROM tblPayrollRun r JOIN tblCompany c ON c.id=r.CompanyId WHERE r.id=?");
$s->execute([$runId]);
$run = $s->fetch();
if (!$run) { http_response_code(404); die('Run not found.'); }

if ($user['role'] === 'admin') {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$run['CompanyId'], $user['id']]);
    if (!$chk->fetch()) { http_response_code(403); die('Access denied.'); }
} elseif ($user['role'] === 'user') {
    if ($run['CompanyId'] != $user['company_id']) { http_response_code(403); die('Access denied.'); }
}

$s = $db->prepare("SELECT d.EmployeeId, d.NetSalary, d.Remarks,
        e.Name, e.EmployeeCode, e.Department, e.BankName, e.BranchName, e.BankAcNo, e.IFSCCode
    FROM tblPayrollDetail d
    JOIN tblEmployee e ON e.id=d.EmployeeId
    WHERE d.RunId=? ORDER BY e.Department, e.Name");
$s->execute([$runId]);
$rows = $s->fetchAll();

$dt     = \DateTime::createFromFormat('Y-m', $run['RunMonth']);
$mLabel = $dt ? $dt->format('F Y') : $run['RunMonth'];
$total  = array_sum(array_column($rows, 'NetSalary'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bank Advice — <?= htmlspecialchars($run['CompanyName']) ?> — <?= $mLabel ?></title>
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial,sans-serif; font-size:12px; color:#222; background:#fff; padding:10mm; }
@page { size: A4 landscape; margin:10mm; }
@media print { .no-print{display:none!important} body{padding:0} }
.toolbar{display:flex;gap:10px;margin-bottom:14px;}
.toolbar a,.toolbar button{padding:6px 14px;border-radius:6px;font-size:12px;cursor:pointer;text-decoration:none;border:1px solid #ccc;}
.btn-print{background:#0071e3;color:#fff;border-color:#0071e3;}
.hdr{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;padding-bottom:10px;border-bottom:2px solid #1a3c6e;}
.hdr-left h1{font-size:18px;color:#1a3c6e;font-weight:bold;}
.hdr-left p{font-size:11px;color:#555;margin-top:2px;}
.hdr-right{text-align:right;font-size:12px;}
.hdr-right .mth{font-size:16px;font-weight:bold;color:#1a3c6e;}
table{width:100%;border-collapse:collapse;margin-top:8px;}
thead th{background:#1a3c6e;color:#fff;padding:7px 10px;text-align:left;font-size:11px;font-weight:600;}
tbody td{padding:6px 10px;border-bottom:1px solid #e0e0e0;vertical-align:top;}
tbody tr:nth-child(even){background:#f9f9f9;}
tfoot td{padding:7px 10px;font-weight:bold;background:#e8f3ff;border-top:2px solid #1a3c6e;}
.dept-row td{background:#f0f4ff;font-weight:600;font-size:11px;color:#1a3c6e;padding:4px 10px;}
.net{text-align:right;font-weight:bold;font-size:13px;color:#1a3c6e;}
.sig-row{display:flex;justify-content:space-between;margin-top:30px;}
.sig-box{width:180px;border-top:1px solid #555;text-align:center;padding-top:4px;font-size:11px;color:#555;}
</style>
</head>
<body>

<div class="toolbar no-print">
  <button class="btn-print" onclick="window.print()">🖨 Print</button>
  <a href="run.php?company=<?= $run['CompanyId'] ?>&month=<?= htmlspecialchars($run['RunMonth']) ?>">← Back</a>
</div>

<div class="hdr">
  <div class="hdr-left">
    <h1><?= htmlspecialchars($run['CompanyName']) ?></h1>
    <p><?= htmlspecialchars($run['CompanyAddress'] ?? '') ?></p>
  </div>
  <div class="hdr-right">
    <div class="mth">BANK PAYMENT ADVICE</div>
    <div><?= $mLabel ?></div>
    <?php if ($run['Status']==='draft'): ?><div style="color:#c00;font-size:10px">DRAFT</div><?php endif; ?>
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Employee Name</th>
      <th>Code</th>
      <th>Department</th>
      <th>Bank Name</th>
      <th>Branch</th>
      <th>Account No.</th>
      <th>IFSC Code</th>
      <th style="text-align:right">Net Salary (₹)</th>
      <th>Remarks</th>
    </tr>
  </thead>
  <tbody>
  <?php
    $sno = 0; $lastDept = null;
    foreach ($rows as $r):
      $dept = $r['Department'] ?? 'General';
      if ($dept !== $lastDept):
        $lastDept = $dept;
  ?>
    <tr class="dept-row"><td colspan="10"><?= htmlspecialchars($dept) ?></td></tr>
  <?php endif; $sno++; ?>
    <tr>
      <td><?= $sno ?></td>
      <td><strong><?= htmlspecialchars($r['Name']) ?></strong></td>
      <td><?= htmlspecialchars($r['EmployeeCode']) ?></td>
      <td><?= htmlspecialchars($r['Department'] ?? '—') ?></td>
      <td><?= htmlspecialchars($r['BankName'] ?? '—') ?></td>
      <td><?= htmlspecialchars($r['BranchName'] ?? '—') ?></td>
      <td><?= htmlspecialchars($r['BankAcNo'] ?? '—') ?></td>
      <td><?= htmlspecialchars($r['IFSCCode'] ?? '—') ?></td>
      <td class="net">₹ <?= number_format($r['NetSalary'],2) ?></td>
      <td style="font-size:11px;color:#666"><?= htmlspecialchars($r['Remarks']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td colspan="8" style="text-align:right">Total Net Payroll (<?= count($rows) ?> employees):</td>
      <td class="net">₹ <?= number_format($total,2) ?></td>
      <td></td>
    </tr>
  </tfoot>
</table>

<div class="sig-row no-print" style="margin-top:40px">
  <div class="sig-box">Prepared By</div>
  <div class="sig-box">Checked By</div>
  <div class="sig-box">Authorized By</div>
</div>

</body>
</html>
