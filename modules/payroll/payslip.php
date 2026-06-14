<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
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
if ($user['role'] === 'admin') {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$run['CompanyId'], $user['id']]);
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

.slip { border:1px solid #999; padding: 0; }
.slip-header { background:#1a3c6e; color:#fff; padding:12px 16px; display:flex; justify-content:space-between; align-items:flex-start; }
.co-name { font-size:16px; font-weight:bold; }
.co-sub  { font-size:11px; opacity:.85; margin-top:2px; }
.slip-title { font-size:14px; font-weight:bold; text-align:right; }
.slip-month { font-size:12px; opacity:.85; }

.emp-grid { display:grid; grid-template-columns:1fr 1fr; gap:0; border-bottom:1px solid #ccc; }
.emp-sec { padding:10px 16px; }
.emp-sec:first-child { border-right:1px solid #ccc; }
.field-row { display:flex; gap:4px; margin-bottom:4px; }
.field-label { color:#555; min-width:110px; }
.field-val { font-weight:600; }

.attn-row { display:flex; border-bottom:1px solid #ccc; }
.attn-cell { flex:1; padding:8px 12px; text-align:center; border-right:1px solid #eee; }
.attn-cell:last-child { border-right:none; }
.attn-num  { font-size:16px; font-weight:bold; color:#1a3c6e; }
.attn-label { font-size:10px; color:#666; }

table.sl { width:100%; border-collapse:collapse; }
table.sl td, table.sl th { padding:6px 12px; border-bottom:1px solid #eee; }
table.sl th { background:#f4f6fb; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.04em; }
.tbl-section { display:grid; grid-template-columns:1fr 1fr; }
.tbl-half { border-right:1px solid #ccc; }
.tbl-half:last-child { border-right:none; }
.tbl-half table.sl { width:100%; }

.summary-row { background:#1a3c6e; color:#fff; display:flex; justify-content:space-between; padding:10px 16px; font-weight:bold; font-size:14px; }
.note { padding:8px 16px; font-size:10px; color:#777; border-top:1px solid #eee; text-align:center; }
</style>
</head>
<body>

<div class="toolbar no-print">
  <button class="btn-print" onclick="window.print()">🖨 Print Pay Slip</button>
  <a href="run.php?company=<?= $run['CompanyId'] ?>&month=<?= htmlspecialchars($run['RunMonth']) ?>">← Back</a>
</div>

<div class="slip">
  <!-- Header -->
  <div class="slip-header">
    <div>
      <div class="co-name"><?= htmlspecialchars($run['CompanyName']) ?></div>
      <div class="co-sub"><?= htmlspecialchars($run['CompanyAddress'] ?? '') ?></div>
      <?php if ($run['CompanyPhone']): ?>
        <div class="co-sub">Ph: <?= htmlspecialchars($run['CompanyPhone']) ?></div>
      <?php endif; ?>
    </div>
    <div>
      <div class="slip-title">SALARY SLIP</div>
      <div class="slip-month"><?= $mLabel ?></div>
      <?php if ($run['Status'] === 'draft'): ?>
        <div style="color:#ffc;font-size:10px;margin-top:4px;font-weight:normal">DRAFT — Not Finalized</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Employee details -->
  <div class="emp-grid">
    <div class="emp-sec">
      <div class="field-row"><span class="field-label">Employee Name</span><span class="field-val"><?= htmlspecialchars($det['Name']) ?></span></div>
      <div class="field-row"><span class="field-label">Employee Code</span><span class="field-val"><?= htmlspecialchars($det['EmployeeCode']) ?></span></div>
      <div class="field-row"><span class="field-label">Department</span><span class="field-val"><?= htmlspecialchars($det['Department'] ?? '—') ?></span></div>
      <div class="field-row"><span class="field-label">Designation</span><span class="field-val"><?= htmlspecialchars($det['Designation'] ?? '—') ?></span></div>
      <div class="field-row"><span class="field-label">Date of Joining</span><span class="field-val"><?= $det['JoinDate'] ? date('d M Y', strtotime($det['JoinDate'])) : '—' ?></span></div>
    </div>
    <div class="emp-sec">
      <div class="field-row"><span class="field-label">UAN No.</span><span class="field-val"><?= htmlspecialchars($det['UAN'] ?? '—') ?></span></div>
      <div class="field-row"><span class="field-label">PF No.</span><span class="field-val"><?= htmlspecialchars($det['PfNo'] ?? '—') ?></span></div>
      <div class="field-row"><span class="field-label">ESI No.</span><span class="field-val"><?= htmlspecialchars($det['EsiNo'] ?? '—') ?></span></div>
      <div class="field-row"><span class="field-label">PAN No.</span><span class="field-val"><?= htmlspecialchars($det['PanNo'] ?? '—') ?></span></div>
      <div class="field-row"><span class="field-label">Bank A/C</span><span class="field-val"><?= htmlspecialchars($det['BankAcNo'] ?? '—') ?></span></div>
      <div class="field-row"><span class="field-label">IFSC</span><span class="field-val"><?= htmlspecialchars($det['IFSCCode'] ?? '—') ?></span></div>
    </div>
  </div>

  <!-- Attendance row -->
  <div class="attn-row">
    <div class="attn-cell"><div class="attn-num"><?= $det['PresentDays'] ?></div><div class="attn-label">Days Present</div></div>
    <div class="attn-cell"><div class="attn-num"><?= $det['HalfDays'] ?></div><div class="attn-label">Half Days</div></div>
    <div class="attn-cell"><div class="attn-num"><?= $det['AbsentDays'] ?></div><div class="attn-label">Days Absent</div></div>
    <div class="attn-cell"><div class="attn-num"><?= $det['OTHours'] ?></div><div class="attn-label">OT Hours</div></div>
    <div class="attn-cell"><div class="attn-num"><?= ucwords(str_replace('_',' ',$det['WageType'])) ?></div><div class="attn-label">Wage Type</div></div>
    <div class="attn-cell"><div class="attn-num">₹<?= number_format($det['WageRate'],2) ?></div><div class="attn-label">Rate</div></div>
  </div>

  <!-- Earnings & Deductions table -->
  <div class="tbl-section">
    <div class="tbl-half">
      <table class="sl">
        <thead><tr><th>Earnings</th><th style="text-align:right">Amount (₹)</th></tr></thead>
        <tbody>
          <tr><td>Basic Salary</td><td style="text-align:right"><?= number_format($det['EarnedBasic'],2) ?></td></tr>
          <?php if ($det['OTAmount'] > 0): ?>
          <tr><td>OT Amount</td><td style="text-align:right"><?= number_format($det['OTAmount'],2) ?></td></tr>
          <?php endif; ?>
          <?php foreach ($earns as $e): ?>
          <?php if ($e['amount'] > 0): ?>
          <tr><td><?= htmlspecialchars($e['name']) ?></td><td style="text-align:right"><?= number_format($e['amount'],2) ?></td></tr>
          <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="font-weight:bold;background:#edfaf1">
            <td>Gross Earnings</td><td style="text-align:right">₹<?= number_format($det['TotalEarnings'],2) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div class="tbl-half">
      <table class="sl">
        <thead><tr><th>Deductions</th><th style="text-align:right">Amount (₹)</th></tr></thead>
        <tbody>
          <?php if ($det['PFEmployee'] > 0): ?>
          <tr><td>PF (Employee)</td><td style="text-align:right"><?= number_format($det['PFEmployee'],2) ?></td></tr>
          <?php endif; ?>
          <?php if ($det['ESIEmployee'] > 0): ?>
          <tr><td>ESI (Employee)</td><td style="text-align:right"><?= number_format($det['ESIEmployee'],2) ?></td></tr>
          <?php endif; ?>
          <?php foreach ($deds as $d): ?>
          <?php if ($d['amount'] > 0): ?>
          <tr><td><?= htmlspecialchars($d['name']) ?></td><td style="text-align:right"><?= number_format($d['amount'],2) ?></td></tr>
          <?php endif; ?>
          <?php endforeach; ?>
          <?php if ($det['TDSAmount'] > 0): ?>
          <tr><td>TDS</td><td style="text-align:right"><?= number_format($det['TDSAmount'],2) ?></td></tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr style="font-weight:bold;background:#fff0ee">
            <td>Total Deductions</td><td style="text-align:right">₹<?= number_format($det['TotalDeductions'],2) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- Employer contributions note -->
  <?php if ($det['PFEmployer'] > 0 || $det['ESIEmployer'] > 0): ?>
  <div style="padding:6px 16px;background:#f8f9fa;font-size:10px;color:#666;border-top:1px solid #eee">
    Employer contribution: PF ₹<?= number_format($det['PFEmployer'],2) ?>
    <?php if ($det['ESIEmployer'] > 0): ?>, ESI ₹<?= number_format($det['ESIEmployer'],2) ?><?php endif; ?>
    (not deducted from salary)
  </div>
  <?php endif; ?>

  <!-- Net pay -->
  <div class="summary-row">
    <span>Net Pay for <?= $mLabel ?></span>
    <span>₹ <?= number_format($det['NetSalary'],2) ?></span>
  </div>

  <?php if ($det['Remarks']): ?>
  <div style="padding:6px 16px;font-size:11px;color:#555;border-top:1px solid #eee">
    Remarks: <?= htmlspecialchars($det['Remarks']) ?>
  </div>
  <?php endif; ?>

  <div class="note">This is a computer-generated salary slip and does not require a signature.</div>
</div>

</body>
</html>
