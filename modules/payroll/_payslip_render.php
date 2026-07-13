<?php
/**
 * Shared salary-slip renderer used by payslip.php (screen/print) and
 * send_slips.php (email body). Keeps a single source of truth for slip markup.
 */

/** Slip CSS (without page/toolbar chrome) — safe to embed in an email <style>. */
function payslipCss(): string {
    return <<<CSS
.slip { border:1px solid #999; padding:0; max-width:720px; font-family:Arial, sans-serif; color:#222; }
.slip * { box-sizing:border-box; }
.slip-header { background:#1a3c6e; color:#fff; padding:12px 16px; display:flex; justify-content:space-between; align-items:flex-start; }
.slip .co-name { font-size:16px; font-weight:bold; }
.slip .co-sub  { font-size:11px; opacity:.85; margin-top:2px; }
.slip .slip-title { font-size:14px; font-weight:bold; text-align:right; }
.slip .slip-month { font-size:12px; opacity:.85; }
.slip .emp-grid { display:grid; grid-template-columns:1fr 1fr; gap:0; border-bottom:1px solid #ccc; }
.slip .emp-sec { padding:10px 16px; }
.slip .emp-sec:first-child { border-right:1px solid #ccc; }
.slip .field-row { display:flex; gap:4px; margin-bottom:4px; font-size:12px; }
.slip .field-label { color:#555; min-width:110px; }
.slip .field-val { font-weight:600; }
.slip .attn-row { display:flex; border-bottom:1px solid #ccc; }
.slip .attn-cell { flex:1; padding:8px 12px; text-align:center; border-right:1px solid #eee; }
.slip .attn-cell:last-child { border-right:none; }
.slip .attn-num  { font-size:15px; font-weight:bold; color:#1a3c6e; }
.slip .attn-label { font-size:10px; color:#666; }
.slip table.sl { width:100%; border-collapse:collapse; }
.slip table.sl td, .slip table.sl th { padding:6px 12px; border-bottom:1px solid #eee; font-size:12px; }
.slip table.sl th { background:#f4f6fb; font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.04em; }
.slip .tbl-section { display:grid; grid-template-columns:1fr 1fr; }
.slip .tbl-half { border-right:1px solid #ccc; }
.slip .tbl-half:last-child { border-right:none; }
.slip .summary-row { background:#1a3c6e; color:#fff; display:flex; justify-content:space-between; padding:10px 16px; font-weight:bold; font-size:14px; }
.slip .note { padding:8px 16px; font-size:10px; color:#777; border-top:1px solid #eee; text-align:center; }
CSS;
}

/** Render the .slip block for one payroll detail row. $run needs CompanyName/Address/Phone/Status/RunMonth. */
function payslipSlipHtml(array $run, array $det, string $mLabel): string {
    $earns = json_decode($det['EarningsJson']   ?: '[]', true) ?: [];
    $deds  = json_decode($det['DeductionsJson'] ?: '[]', true) ?: [];
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $n = fn($v) => number_format((float)$v, 2);
    ob_start(); ?>
<div class="slip">
  <div class="slip-header">
    <div>
      <div class="co-name"><?= $h($run['CompanyName']) ?></div>
      <div class="co-sub"><?= $h($run['CompanyAddress'] ?? '') ?></div>
      <?php if (!empty($run['CompanyPhone'])): ?><div class="co-sub">Ph: <?= $h($run['CompanyPhone']) ?></div><?php endif; ?>
    </div>
    <div>
      <div class="slip-title">SALARY SLIP</div>
      <div class="slip-month"><?= $h($mLabel) ?></div>
      <?php if (($run['Status'] ?? '') === 'draft'): ?><div style="color:#ffc;font-size:10px;margin-top:4px;font-weight:normal">DRAFT — Not Finalized</div><?php endif; ?>
    </div>
  </div>

  <div class="emp-grid">
    <div class="emp-sec">
      <div class="field-row"><span class="field-label">Employee Name</span><span class="field-val"><?= $h($det['Name']) ?></span></div>
      <div class="field-row"><span class="field-label">Employee Code</span><span class="field-val"><?= $h($det['EmployeeCode']) ?></span></div>
      <div class="field-row"><span class="field-label">Department</span><span class="field-val"><?= $h($det['Department'] ?? '—') ?></span></div>
      <div class="field-row"><span class="field-label">Designation</span><span class="field-val"><?= $h($det['Designation'] ?? '—') ?></span></div>
      <div class="field-row"><span class="field-label">Date of Joining</span><span class="field-val"><?= !empty($det['JoinDate']) ? date('d M Y', strtotime($det['JoinDate'])) : '—' ?></span></div>
    </div>
    <div class="emp-sec">
      <div class="field-row"><span class="field-label">UAN No.</span><span class="field-val"><?= $h($det['UAN'] ?? '—') ?></span></div>
      <div class="field-row"><span class="field-label">PF No.</span><span class="field-val"><?= $h($det['PfNo'] ?? '—') ?></span></div>
      <div class="field-row"><span class="field-label">ESI No.</span><span class="field-val"><?= $h($det['EsiNo'] ?? '—') ?></span></div>
      <div class="field-row"><span class="field-label">PAN No.</span><span class="field-val"><?= $h($det['PanNo'] ?? '—') ?></span></div>
      <div class="field-row"><span class="field-label">Bank A/C</span><span class="field-val"><?= $h($det['BankAcNo'] ?? '—') ?></span></div>
      <div class="field-row"><span class="field-label">IFSC</span><span class="field-val"><?= $h($det['IFSCCode'] ?? '—') ?></span></div>
    </div>
  </div>

  <div class="attn-row">
    <div class="attn-cell"><div class="attn-num"><?= $h($det['PresentDays']) ?></div><div class="attn-label">Days Present</div></div>
    <div class="attn-cell"><div class="attn-num"><?= $h($det['HalfDays']) ?></div><div class="attn-label">Half Days</div></div>
    <div class="attn-cell"><div class="attn-num"><?= $h($det['AbsentDays']) ?></div><div class="attn-label">Days Absent</div></div>
    <div class="attn-cell"><div class="attn-num"><?= $h($det['OTHours']) ?></div><div class="attn-label">OT Hours</div></div>
    <div class="attn-cell"><div class="attn-num"><?= ucwords(str_replace('_',' ', $det['WageType'])) ?></div><div class="attn-label">Wage Type</div></div>
    <div class="attn-cell"><div class="attn-num">₹<?= $n($det['WageRate']) ?></div><div class="attn-label">Rate</div></div>
  </div>

  <div class="tbl-section">
    <div class="tbl-half">
      <table class="sl">
        <thead><tr><th>Earnings</th><th style="text-align:right">Amount (₹)</th></tr></thead>
        <tbody>
          <tr><td>Basic Salary</td><td style="text-align:right"><?= $n($det['EarnedBasic']) ?></td></tr>
          <?php if (($det['OTAmount'] ?? 0) > 0): ?><tr><td>OT Amount</td><td style="text-align:right"><?= $n($det['OTAmount']) ?></td></tr><?php endif; ?>
          <?php foreach ($earns as $e): if (($e['amount'] ?? 0) > 0): ?>
          <tr><td><?= $h($e['name']) ?></td><td style="text-align:right"><?= $n($e['amount']) ?></td></tr>
          <?php endif; endforeach; ?>
        </tbody>
        <tfoot><tr style="font-weight:bold;background:#edfaf1"><td>Gross Earnings</td><td style="text-align:right">₹<?= $n($det['TotalEarnings']) ?></td></tr></tfoot>
      </table>
    </div>
    <div class="tbl-half">
      <table class="sl">
        <thead><tr><th>Deductions</th><th style="text-align:right">Amount (₹)</th></tr></thead>
        <tbody>
          <?php if (($det['PFEmployee'] ?? 0) > 0): ?><tr><td>PF (Employee)</td><td style="text-align:right"><?= $n($det['PFEmployee']) ?></td></tr><?php endif; ?>
          <?php if (($det['ESIEmployee'] ?? 0) > 0): ?><tr><td>ESI (Employee)</td><td style="text-align:right"><?= $n($det['ESIEmployee']) ?></td></tr><?php endif; ?>
          <?php foreach ($deds as $d): if (($d['amount'] ?? 0) > 0): ?>
          <tr><td><?= $h($d['name']) ?></td><td style="text-align:right"><?= $n($d['amount']) ?></td></tr>
          <?php endif; endforeach; ?>
          <?php if (($det['TDSAmount'] ?? 0) > 0): ?><tr><td>TDS</td><td style="text-align:right"><?= $n($det['TDSAmount']) ?></td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr style="font-weight:bold;background:#fff0ee"><td>Total Deductions</td><td style="text-align:right">₹<?= $n($det['TotalDeductions']) ?></td></tr></tfoot>
      </table>
    </div>
  </div>

  <?php if (($det['PFEmployer'] ?? 0) > 0 || ($det['ESIEmployer'] ?? 0) > 0): ?>
  <div style="padding:6px 16px;background:#f8f9fa;font-size:10px;color:#666;border-top:1px solid #eee">
    Employer contribution: PF ₹<?= $n($det['PFEmployer']) ?><?php if (($det['ESIEmployer'] ?? 0) > 0): ?>, ESI ₹<?= $n($det['ESIEmployer']) ?><?php endif; ?> (not deducted from salary)
  </div>
  <?php endif; ?>

  <div class="summary-row"><span>Net Pay for <?= $h($mLabel) ?></span><span>₹ <?= $n($det['NetSalary']) ?></span></div>
  <?php if (!empty($det['Remarks'])): ?><div style="padding:6px 16px;font-size:11px;color:#555;border-top:1px solid #eee">Remarks: <?= $h($det['Remarks']) ?></div><?php endif; ?>
  <div class="note">This is a computer-generated salary slip and does not require a signature.</div>
</div>
<?php
    return ob_get_clean();
}

/** Full standalone HTML document for a slip (used as the email body). */
function payslipEmailHtml(array $run, array $det, string $mLabel): string {
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . payslipCss() . '</style></head><body>'
         . payslipSlipHtml($run, $det, $mLabel) . '</body></html>';
}
