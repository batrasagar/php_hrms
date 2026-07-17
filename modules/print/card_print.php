<?php
// Prints employees on a designed card template (tblCardTemplate).
// Reached two ways:
//   1. include'd from index.php  (ptype='card', $printEmps preloaded, card_template_id POSTed)
//   2. standalone GET ?template_id=N&test=1  (designer/list test print — first active employee)
if (!defined('BASE_URL')) {
    define('BASE_URL', '../..');
    require_once __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../../includes/auth.php';
    requireAdmin();
    requirePermission('card_templates.view');
    $db   = getDb();
    $user = currentUser();
}
require_once __DIR__ . '/card_entry.php';

$fCompany = activeCompanyId($db, $user);
$tplId    = (int)($_POST['card_template_id'] ?? $_GET['template_id'] ?? 0);

$tplStmt = $db->prepare("SELECT * FROM tblCardTemplate WHERE id=? AND CompanyId=?");
$tplStmt->execute([$tplId, $fCompany]);
$tplRow = $tplStmt->fetch();
if (!$tplRow) { http_response_code(404); exit('Card template not found for the active company.'); }

// Test mode: print one sample employee
if (!isset($printEmps)) {
    $s = $db->prepare(
        "SELECT e.*, c.Name AS CompanyName, c.Address AS CompanyAddress,
                c.SignImage, c.SignName, c.SignDesignation
         FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId
         WHERE e.CompanyId=? AND e.Status='active'
         ORDER BY (e.Photo IS NULL OR e.Photo=''), e.id LIMIT 1"
    );
    $s->execute([$fCompany]);
    $printEmps = $s->fetchAll();
    if (!$printEmps) exit('No active employees in this company to test-print.');
} else {
    // Included from index.php: the picker query doesn't join signatory columns — fetch them once.
    $cInfo = $db->prepare("SELECT SignImage, SignName, SignDesignation FROM tblCompany WHERE id=?");
    $cInfo->execute([$fCompany]);
    $cRow = $cInfo->fetch() ?: [];
    foreach ($printEmps as &$peRef) { $peRef = array_merge($peRef, $cRow); }
    unset($peRef);
}

$entries = array_map(fn($e) => cardEntryFromRow($e, BASE_URL), $printEmps);

$tpl = [
    'width_mm'  => (float)$tplRow['WidthMm'],
    'height_mm' => (float)$tplRow['HeightMm'],
    'layout'    => json_decode($tplRow['Layout'] ?? '', true) ?: ['front' => ['elements' => []], 'back' => null],
];
$hasBack = !empty($tpl['layout']['back']['elements']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Print Cards — <?= htmlspecialchars($tplRow['Name']) ?></title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  @page { size: A4; margin: 8mm; }
  body { font-family: Arial, sans-serif; background:#777; }
  .sheet { display:flex; flex-wrap:wrap; gap:4mm; align-content:flex-start; padding:6mm; }
  .cr-card { page-break-inside: avoid; outline:0.2mm solid #bbb; }
  .side-title { width:100%; font-size:10px; color:#fff; padding:2mm 0 1mm; }
  .backs { page-break-before: always; }
  @media print {
    body { background:#fff; }
    .sheet { padding:0; }
    .side-title { display:none; }
    .no-print { display:none !important; }
  }
  .no-print { position:fixed; top:10px; right:10px; z-index:99; }
  .no-print button { padding:8px 16px; font-size:14px; cursor:pointer; border-radius:6px; border:none;
                     background:#0071e3; color:#fff; margin-left:6px; }
</style>
</head>
<body>
<div class="no-print">
  <button onclick="window.print()">🖨 Print</button>
  <button onclick="window.close()" style="background:#666">Close</button>
</div>
<div class="sheet" id="fronts"><div class="side-title">FRONT — <?= count($entries) ?> card(s)</div></div>
<?php if ($hasBack): ?>
<div class="sheet backs" id="backs"><div class="side-title">BACK</div></div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js"></script>
<script src="card_render.js?v=1"></script>
<script>
var TPL     = <?= json_encode($tpl, JSON_UNESCAPED_UNICODE) ?>;
var ENTRIES = <?= json_encode($entries, JSON_UNESCAPED_UNICODE) ?>;
var HASBACK = <?= $hasBack ? 'true' : 'false' ?>;
var OPTS    = { unit: 'mm' };

var fh = '', bh = '';
ENTRIES.forEach(function (en) {
  fh += CardRender.cardHtml(TPL, 'front', en, OPTS);
  if (HASBACK) bh += CardRender.cardHtml(TPL, 'back', en, OPTS);
});
document.getElementById('fronts').insertAdjacentHTML('beforeend', fh);
if (HASBACK) document.getElementById('backs').insertAdjacentHTML('beforeend', bh);
CardRender.renderCodes(document);

// Give images/QRs a moment, then open the print dialog
window.addEventListener('load', function () {
  setTimeout(function () { window.print(); }, 600);
});
</script>
</body>
</html>
<?php exit; ?>
