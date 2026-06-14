<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Personal Files</title>
<style>
  @page { size: A4; margin: 15mm; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 11pt; color: #111; background: #fff; }
  .page-break { page-break-after: always; }
  .file-page { padding: 0; }
  .file-header { background: #1e2a3a; color: #fff; padding: 10mm; display: flex; align-items: center; gap: 8mm; margin-bottom: 6mm; }
  .file-header img { width: 25mm; height: 25mm; border-radius: 3mm; object-fit: cover; border: 2px solid rgba(255,255,255,.4); }
  .file-header .avatar { width: 25mm; height: 25mm; border-radius: 3mm; background: #2e3f55; display: flex; align-items: center; justify-content: center; font-size: 14pt; color: rgba(255,255,255,.5); }
  .file-header-info h2 { font-size: 16pt; margin-bottom: 2mm; }
  .file-header-info p { font-size: 9pt; opacity: .8; }
  h4 { font-size: 10pt; border-bottom: 1.5px solid #1e2a3a; padding-bottom: 1.5mm; margin: 5mm 0 3mm; color: #1e2a3a; text-transform: uppercase; letter-spacing: .05em; }
  table.info { width: 100%; border-collapse: collapse; }
  table.info td { padding: 2.5mm 3mm; vertical-align: top; font-size: 10pt; }
  table.info td:first-child { color: #555; width: 38%; font-size: 9pt; }
  table.info tr:nth-child(even) td { background: #f8f9fa; }
  .no-print { display: none; }
  @media screen {
    body { background: #e5e5e5; padding: 20px; }
    .no-print { display: block; margin-bottom: 20px; text-align: center; }
    .no-print button { padding: 8px 24px; font-size: 14px; cursor: pointer; margin: 0 5px; }
    .file-page { background: #fff; max-width: 210mm; margin: 0 auto 20px; padding: 15mm; box-shadow: 0 2px 8px rgba(0,0,0,.15); }
  }
</style>
</head>
<body>
<div class="no-print">
  <button onclick="window.print()">🖨 Print Personal Files</button>
  &nbsp;
  <button onclick="window.close()">Close</button>
</div>
<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '../..';
foreach ($printEmps as $idx => $e):
    $photoSrc = !empty($e['Photo']) ? $baseUrl . '/uploads/employees/' . htmlspecialchars($e['Photo']) : '';
?>
<div class="file-page <?= $idx < count($printEmps)-1 ? 'page-break' : '' ?>">
  <div class="file-header">
    <?php if ($photoSrc): ?>
    <img src="<?= $photoSrc ?>" alt="">
    <?php else: ?>
    <div class="avatar">👤</div>
    <?php endif; ?>
    <div class="file-header-info">
      <h2><?= htmlspecialchars($e['Name']) ?></h2>
      <p><?= htmlspecialchars($e['Designation'] ?? '') ?> &nbsp;|&nbsp; <?= htmlspecialchars($e['Department'] ?? '') ?></p>
      <p><?= htmlspecialchars($e['CompanyName']) ?></p>
    </div>
  </div>

  <h4>Personal Information</h4>
  <table class="info">
    <tr><td>Employee Code</td><td><?= htmlspecialchars($e['EmployeeCode'] ?: '—') ?></td></tr>
    <tr><td>Enroll ID (Biometric)</td><td><?= htmlspecialchars($e['EnrollId'] ?: '—') ?></td></tr>
    <tr><td>Full Name</td><td><?= htmlspecialchars($e['Name']) ?></td></tr>
    <tr><td>Email</td><td><?= htmlspecialchars($e['Email'] ?? '—') ?></td></tr>
    <tr><td>Phone</td><td><?= htmlspecialchars($e['Phone'] ?? '—') ?></td></tr>
    <tr><td>Date of Joining</td><td><?= $e['JoinDate'] ? htmlspecialchars($e['JoinDate']) : '—' ?></td></tr>
    <tr><td>Status</td><td><?= ucfirst($e['Status']) ?></td></tr>
  </table>

  <h4>Work Details</h4>
  <table class="info">
    <tr><td>Company</td><td><?= htmlspecialchars($e['CompanyName']) ?></td></tr>
    <tr><td>Department</td><td><?= htmlspecialchars($e['Department'] ?? '—') ?></td></tr>
    <tr><td>Designation</td><td><?= htmlspecialchars($e['Designation'] ?? '—') ?></td></tr>
    <tr><td>Contractor</td><td><?= htmlspecialchars($e['Contractor'] ?? '—') ?></td></tr>
  </table>

  <h4 style="margin-top: 15mm;">Signature</h4>
  <table class="info">
    <tr>
      <td style="padding-top:15mm; border-top: 1px solid #ccc; width:45%">Employee Signature</td>
      <td style="padding-top:15mm; border-top: 1px solid #ccc;">HR Signature &amp; Stamp</td>
    </tr>
  </table>
</div>
<?php endforeach; ?>
<script>
window.addEventListener('load', () => window.print());
</script>
</body>
</html>
