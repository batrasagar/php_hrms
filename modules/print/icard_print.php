<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>I-Cards</title>
<style>
  @page { size: A4; margin: 8mm; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 8pt; background: #fff; }
  .cards-grid { display: flex; flex-wrap: wrap; gap: 5mm; }

  .id-card {
    width: 90mm;
    border: 1.5px solid #444;
    border-radius: 3mm;
    padding: 3mm;
    page-break-inside: avoid;
    background: #fff;
  }

  .company-name  { font-size: 9pt; font-weight: bold; text-align: center; line-height: 1.3; }
  .company-addr  { font-size: 6.5pt; color: #555; text-align: center; margin-top: 0.5mm; }

  .id-card-body  { display: flex; gap: 3mm; margin-top: 2mm; }

  .photo-section {
    display: flex; flex-direction: column; align-items: center;
    width: 27mm; flex-shrink: 0;
  }
  .emp-code       { font-size: 7.5pt; font-weight: bold; text-align: center; margin-bottom: 1mm; }
  .emp-photo      { width: 22mm; height: 25mm; object-fit: cover;
                    border: 2px solid red; border-radius: 2mm; padding: 1px; display: block; }
  .no-photo       { width: 22mm; height: 25mm; border: 2px solid #ccc; border-radius: 2mm;
                    display: flex; align-items: center; justify-content: center;
                    font-size: 6pt; color: #aaa; text-align: center; }
  .dvbarcode      { margin-top: 2mm; text-align: center; }
  .dvbarcode img  { max-height: 8px; width: auto; }

  .details-section { flex: 1; min-width: 0; }
  .details-row     { display: flex; justify-content: space-between; align-items: flex-start; }
  .details p       { font-size: 7pt; margin-bottom: 1mm; line-height: 1.35; word-break: break-word; }
  .details p b     { color: #111; }
  .qrbox           { flex-shrink: 0; margin-left: 2mm; }
  .qrbox img, .qrbox canvas { width: 48px !important; height: 48px !important; }

  .no-print { display: none; }
  @media screen {
    body { background: #ddd; padding: 20px; }
    .no-print { display: block; margin-bottom: 20px; text-align: center; }
    .no-print button { padding: 8px 22px; font-size: 14px; cursor: pointer; margin: 0 5px; }
    .cards-grid { background: #fff; padding: 12px; max-width: 210mm; margin: auto; }
  }
</style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()">&#128438; Print I-Cards</button>
  <button onclick="window.close()">Close</button>
</div>

<div class="cards-grid">
<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '../..';

function icFmtDate($d) {
    if (!$d || $d === '0000-00-00') return '—';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('d-M-Y') : $d;
}

foreach ($printEmps as $e):
    $empCode  = $e['EmployeeCode'] ?: ($e['EnrollId'] ?: '—');
    $photoSrc = !empty($e['Photo'])
        ? $baseUrl . '/uploads/employees/' . htmlspecialchars($e['Photo'])
        : '';
?>
<div class="id-card">

  <div class="company-name"><?= htmlspecialchars($e['CompanyName']) ?></div>
  <?php if (!empty($e['CompanyAddress'])): ?>
  <div class="company-addr"><?= htmlspecialchars($e['CompanyAddress']) ?></div>
  <?php endif; ?>

  <div class="id-card-body">

    <!-- Left: code + photo + barcode -->
    <div class="photo-section">
      <div class="emp-code"><?= htmlspecialchars($empCode) ?></div>
      <?php if ($photoSrc): ?>
        <img class="emp-photo" src="<?= $photoSrc ?>" alt="Photo">
      <?php else: ?>
        <div class="no-photo">No Photo</div>
      <?php endif; ?>
      <div class="dvbarcode">
        <img class="js-barcode" data-text="<?= htmlspecialchars($empCode) ?>" alt="">
      </div>
    </div>

    <!-- Right: details + QR -->
    <div class="details-section">
      <div class="details">

        <div class="details-row">
          <div>
            <p><b>Name:</b> <?= htmlspecialchars($e['Name']) ?></p>
            <?php if (!empty($e['AdhaarID'])): ?>
            <p><b>AdhaarID:</b> <?= htmlspecialchars($e['AdhaarID']) ?></p>
            <?php endif; ?>
            <?php if (!empty($e['Phone'])): ?>
            <p><b>Mobile:</b> <?= htmlspecialchars($e['Phone']) ?></p>
            <?php endif; ?>
            <?php if (!empty($e['FatherName'])): ?>
            <p><b>S/o D/o W/o:</b> <?= htmlspecialchars($e['FatherName']) ?></p>
            <?php endif; ?>
          </div>
          <?php if (!empty($e['AdhaarID'])): ?>
          <div class="qrbox" data-qr="<?= htmlspecialchars($e['AdhaarID']) ?>"></div>
          <?php endif; ?>
        </div>

        <p><b>Department:</b> <?= htmlspecialchars($e['Department'] ?? '—') ?></p>
        <p><b>Designation:</b> <?= htmlspecialchars($e['Designation'] ?? '—') ?></p>
        <?php $dob = icFmtDate($e['DOB'] ?? null); $doj = icFmtDate($e['JoinDate'] ?? null); ?>
        <?php if ($dob !== '—' || $doj !== '—'): ?>
        <p><b>DOB:</b> <?= $dob ?>&nbsp; <b>DOJ:</b> <?= $doj ?></p>
        <?php endif; ?>
        <?php if (!empty($e['PermanentAdd'])): ?>
        <p><b>Address:</b> <?= htmlspecialchars($e['PermanentAdd']) ?></p>
        <?php endif; ?>

      </div>
    </div>

  </div><!-- /id-card-body -->
</div><!-- /id-card -->
<?php endforeach; ?>
</div><!-- /cards-grid -->

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
document.querySelectorAll('img.js-barcode').forEach(function(img) {
  var text = img.dataset.text;
  if (!text || text === '—') { img.style.display = 'none'; return; }
  try {
    JsBarcode(img, text, { format: 'CODE128', width: 1, height: 8, displayValue: false, margin: 0 });
  } catch(e) { img.style.display = 'none'; }
});

document.querySelectorAll('.qrbox[data-qr]').forEach(function(div) {
  var text = div.dataset.qr;
  if (!text) return;
  try {
    new QRCode(div, { text: text, width: 48, height: 48, correctLevel: QRCode.CorrectLevel.L });
  } catch(e) {}
});

window.addEventListener('load', function() { window.print(); });
</script>
</body>
</html>
