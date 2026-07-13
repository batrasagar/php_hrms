<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();
$id   = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); die('Invalid request.'); }

// Load settlement + employee + company (with authorised signatory)
$s = $db->prepare(
    "SELECT fs.*, e.Name AS EmpName, e.EmployeeCode, e.Department, e.Designation, e.JoinDate, e.DOL,
            e.BankAcNo, e.IFSCCode, e.PanNo, e.UAN,
            c.Name AS CompanyName, c.Address AS CompanyAddress, c.Phone AS CompanyPhone,
            c.SignImage, c.SignName, c.SignDesignation
     FROM tblFnFSettlement fs
     JOIN tblEmployee e ON e.id = fs.EmployeeId
     JOIN tblCompany  c ON c.id = fs.CompanyId
     WHERE fs.id = ?"
);
$s->execute([$id]);
$fnf = $s->fetch();
if (!$fnf) { http_response_code(404); die('Settlement not found.'); }

// Scope check
if (in_array($user['role'], ['admin','operator'], true)) {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$fnf['CompanyId'], $user['scope_id']]);
    if (!$chk->fetch()) { http_response_code(403); die('Access denied.'); }
}

$s = $db->prepare("SELECT * FROM tblFnFPayItem WHERE SettlementId=? ORDER BY Type='deduction', SortOrder, id");
$s->execute([$id]);
$items = $s->fetchAll();
$earn  = array_filter($items, fn($p) => $p['Type']==='earning');
$ded   = array_filter($items, fn($p) => $p['Type']==='deduction');
$sumE  = array_sum(array_map(fn($p)=>(float)$p['Amount'], $earn));
$sumD  = array_sum(array_map(fn($p)=>(float)$p['Amount'], $ded));
$net   = $sumE - $sumD;

/** Indian-style number to words (rupees). */
function rupeesInWords(float $n): string {
    $n = round($n);
    if ($n == 0) return 'Zero';
    $neg = $n < 0; $n = abs($n);
    $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    $two = function($x) use ($ones,$tens) {
        if ($x < 20) return $ones[$x];
        return $tens[intdiv($x,10)] . ($x%10 ? ' ' . $ones[$x%10] : '');
    };
    $out = '';
    $crore = intdiv($n, 10000000); $n %= 10000000;
    $lakh  = intdiv($n, 100000);   $n %= 100000;
    $thou  = intdiv($n, 1000);     $n %= 1000;
    $hund  = intdiv($n, 100);      $n %= 100;
    if ($crore) $out .= $two($crore) . ' Crore ';
    if ($lakh)  $out .= $two($lakh)  . ' Lakh ';
    if ($thou)  $out .= $two($thou)  . ' Thousand ';
    if ($hund)  $out .= $ones[$hund] . ' Hundred ';
    if ($n)     $out .= ($out ? 'and ' : '') . $two($n) . ' ';
    return ($neg ? 'Minus ' : '') . trim($out);
}

$signUrl = !empty($fnf['SignImage']) ? BASE_URL . '/uploads/company/' . rawurlencode($fnf['SignImage']) : '';
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$n = fn($v) => number_format((float)$v, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Full &amp; Final Settlement — <?= $h($fnf['EmpName']) ?></title>
<style>
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:"Times New Roman", serif; font-size:12.5px; color:#222; background:#f0f0f0; }
  @page { size:A4; margin:14mm; }
  .toolbar { max-width:210mm; margin:10px auto; text-align:right; }
  .toolbar button { background:#0071e3; color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-size:13px; }
  .page { background:#fff; width:210mm; min-height:297mm; margin:10px auto; padding:16mm; box-shadow:0 2px 16px rgba(0,0,0,.12); }
  .hd { text-align:center; border-bottom:2px solid #333; padding-bottom:8px; margin-bottom:14px; }
  .hd h1 { font-size:18px; } .hd p { font-size:11px; color:#555; }
  .title { text-align:center; font-size:15px; font-weight:bold; text-decoration:underline; margin:14px 0; }
  .meta { width:100%; border-collapse:collapse; margin-bottom:14px; }
  .meta td { padding:3px 6px; font-size:12px; vertical-align:top; }
  .meta td.l { color:#555; width:120px; }
  table.amt { width:100%; border-collapse:collapse; margin-top:8px; }
  table.amt th, table.amt td { border:1px solid #999; padding:6px 10px; font-size:12px; }
  table.amt th { background:#f2f2f2; }
  .r { text-align:right; }
  .net { margin-top:14px; padding:10px 12px; background:#1a3c6e; color:#fff; display:flex; justify-content:space-between; font-weight:bold; font-size:14px; }
  .words { font-style:italic; font-size:12px; margin-top:6px; }
  .sign { margin-top:48px; display:flex; justify-content:space-between; }
  .sign .box { width:45%; text-align:center; }
  .sign .line { border-top:1px solid #000; margin-top:40px; padding-top:4px; font-size:11px; }
  .sign img { max-height:52px; max-width:170px; }
  .note { margin-top:24px; font-size:10.5px; color:#666; text-align:center; }
  @media print { body { background:#fff; } .toolbar { display:none; } .page { margin:0; box-shadow:none; padding:0; } }
</style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">🖨 Print</button></div>

<div class="page">
  <div class="hd">
    <h1><?= $h($fnf['CompanyName']) ?></h1>
    <?php if ($fnf['CompanyAddress']): ?><p><?= $h($fnf['CompanyAddress']) ?></p><?php endif; ?>
    <?php if ($fnf['CompanyPhone']): ?><p>Ph: <?= $h($fnf['CompanyPhone']) ?></p><?php endif; ?>
  </div>

  <div class="title">FULL &amp; FINAL SETTLEMENT STATEMENT</div>

  <table class="meta">
    <tr>
      <td class="l">Employee Name</td><td><strong><?= $h($fnf['EmpName']) ?></strong></td>
      <td class="l">Employee Code</td><td><?= $h($fnf['EmployeeCode'] ?: '—') ?></td>
    </tr>
    <tr>
      <td class="l">Designation</td><td><?= $h($fnf['Designation'] ?: '—') ?></td>
      <td class="l">Department</td><td><?= $h($fnf['Department'] ?: '—') ?></td>
    </tr>
    <tr>
      <td class="l">Date of Joining</td><td><?= $fnf['JoinDate'] ? date('d-M-Y', strtotime($fnf['JoinDate'])) : '—' ?></td>
      <td class="l">Date of Leaving</td><td><?= $fnf['DOL'] ? date('d-M-Y', strtotime($fnf['DOL'])) : '—' ?></td>
    </tr>
    <tr>
      <td class="l">Bank A/C</td><td><?= $h($fnf['BankAcNo'] ?: '—') ?></td>
      <td class="l">IFSC / PAN</td><td><?= $h(($fnf['IFSCCode'] ?: '—') . ' / ' . ($fnf['PanNo'] ?: '—')) ?></td>
    </tr>
    <tr>
      <td class="l">Settlement Date</td><td><?= date('d-M-Y', strtotime($fnf['CompletedOn'] ?: $fnf['InitiatedOn'])) ?></td>
      <td class="l">Status</td><td><?= ucfirst($fnf['Status']) ?></td>
    </tr>
  </table>

  <table class="amt">
    <tr>
      <th style="width:50%">Earnings / Dues Payable</th><th class="r">Amount (₹)</th>
      <th style="width:50%">Deductions / Recoveries</th><th class="r">Amount (₹)</th>
    </tr>
    <?php
      $earn = array_values($earn); $ded = array_values($ded);
      $rows = max(count($earn), count($ded), 1);
      for ($i = 0; $i < $rows; $i++):
    ?>
    <tr>
      <td><?= isset($earn[$i]) ? $h($earn[$i]['Label']) : '' ?></td>
      <td class="r"><?= isset($earn[$i]) ? $n($earn[$i]['Amount']) : '' ?></td>
      <td><?= isset($ded[$i]) ? $h($ded[$i]['Label']) : '' ?></td>
      <td class="r"><?= isset($ded[$i]) ? $n($ded[$i]['Amount']) : '' ?></td>
    </tr>
    <?php endfor; ?>
    <tr style="font-weight:bold;background:#f2f2f2">
      <td>Total Earnings</td><td class="r">₹<?= $n($sumE) ?></td>
      <td>Total Deductions</td><td class="r">₹<?= $n($sumD) ?></td>
    </tr>
  </table>

  <div class="net">
    <span>NET <?= $net >= 0 ? 'PAYABLE TO EMPLOYEE' : 'RECOVERABLE FROM EMPLOYEE' ?></span>
    <span>₹ <?= $n(abs($net)) ?></span>
  </div>
  <div class="words">Rupees <?= $h(rupeesInWords(abs($net))) ?> only.</div>

  <?php if ($fnf['Remarks']): ?>
  <div style="margin-top:12px;font-size:11.5px"><strong>Remarks:</strong> <?= $h($fnf['Remarks']) ?></div>
  <?php endif; ?>

  <div class="sign">
    <div class="box">
      <div class="line">Employee Signature<br><?= $h($fnf['EmpName']) ?></div>
    </div>
    <div class="box">
      <?php if ($signUrl): ?><img src="<?= $h($signUrl) ?>" alt="Authorised Signatory"><?php endif; ?>
      <div class="line">
        <?= $h($fnf['SignName'] ?: 'Authorised Signatory') ?>
        <?php if ($fnf['SignDesignation']): ?><br><?= $h($fnf['SignDesignation']) ?><?php endif; ?>
        <br>For <?= $h($fnf['CompanyName']) ?>
      </div>
    </div>
  </div>

  <div class="note">
    I hereby acknowledge that the above is the full &amp; final settlement of my dues and I have no further claim against the company.
  </div>
</div>
</body>
</html>
