<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db   = getDb();
$user = currentUser();
$id   = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(404); exit('Document not found.'); }

$stmt = $db->prepare(
    "SELECT d.*, e.Name AS EmpName, e.EmployeeCode, e.Designation, e.Department,
            e.Email AS EmpEmail, e.Phone AS EmpPhone, e.PhoneNo AS EmpPhoneNo,
            c.Name AS CompanyName
     FROM tblEmployeeDocument d
     JOIN tblEmployee e ON e.id = d.EmployeeId
     JOIN tblCompany c ON c.id = d.CompanyId
     WHERE d.id = ?"
);
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) { http_response_code(404); exit('Document not found.'); }

// Scope check
if (in_array($user['role'], ['admin','operator'], true)) {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$doc['CompanyId'], $user['scope_id']]);
    if (!$chk->fetch()) { http_response_code(403); exit('Access denied.'); }
} elseif ($user['role'] === 'user' && (int)($user['company_id'] ?? 0) !== (int)$doc['CompanyId']) {
    http_response_code(403); exit('Access denied.');
}

// Employee contact details for Email / WhatsApp actions
$empEmail = trim($doc['EmpEmail'] ?? '');
$empPhone = preg_replace('/\D+/', '', $doc['EmpPhoneNo'] ?: ($doc['EmpPhone'] ?? ''));

// ── Build the letter body once — reused by the page and the email ──────────────
ob_start(); ?>
  <div class="doc-header">
    <h1><?= htmlspecialchars($doc['CompanyName']) ?></h1>
    <p>HR Department</p>
  </div>
  <div class="doc-meta">
    <div>
      <strong>To:</strong> <?= htmlspecialchars($doc['EmpName']) ?><br>
      <?php if ($doc['Designation']): ?><?= htmlspecialchars($doc['Designation']) ?><?php endif; ?>
      <?php if ($doc['Department']): ?> &nbsp;&middot;&nbsp; <?= htmlspecialchars($doc['Department']) ?><?php endif; ?>
    </div>
    <div style="text-align:right">
      <strong>Doc:</strong> <?= htmlspecialchars($doc['Title']) ?><br>
      <strong>Date:</strong> <?= date('d/m/Y', strtotime($doc['IssuedOn'])) ?>
    </div>
  </div>
  <div class="doc-body">
    <?= $doc['Content'] ?>
  </div>
<?php
$letterHtml = ob_get_clean();

// ── AJAX action: email the document to the employee ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'email') {
    header('Content-Type: application/json');
    csrf_verify(); // exits with JSON error on failure
    require_once __DIR__ . '/../../includes/smtp_helper.php';

    $to = filter_var(trim($_POST['to'] ?? ''), FILTER_VALIDATE_EMAIL);
    if (!$to) { echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']); exit; }

    // Rewrite relative upload URLs to absolute so images load in email clients.
    $appUrl    = rtrim(getSmtpCfg()['_app_url'] ?? '', '/');
    $emailBody = $appUrl ? str_replace('../../uploads', $appUrl . '/uploads', $letterHtml) : $letterHtml;
    $subject   = $doc['Title'] . ' — ' . $doc['CompanyName'];

    $ok = sendSystemMail($to, $subject, $emailBody);
    echo json_encode([
        'success' => $ok,
        'error'   => $ok ? '' : 'Email could not be sent. Check SMTP settings under Email Notifications.',
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($doc['Title']) ?> — <?= htmlspecialchars($doc['EmpName']) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: "Times New Roman", serif; font-size: 12pt; background: #f0f0f0; }
  .page {
    background: white;
    width: 210mm;
    min-height: 297mm;
    margin: 10mm auto;
    padding: 20mm 20mm 15mm;
    box-shadow: 0 2px 16px rgba(0,0,0,.12);
    position: relative;
  }
  .doc-header {
    text-align: center;
    border-bottom: 2px solid #333;
    padding-bottom: 8px;
    margin-bottom: 16px;
  }
  .doc-header h1 { font-size: 16pt; font-weight: bold; }
  .doc-header p  { font-size: 10pt; color: #555; margin: 2px 0; }
  .doc-meta {
    display: flex;
    justify-content: space-between;
    font-size: 10pt;
    color: #555;
    margin-bottom: 16px;
  }
  .doc-body { line-height: 1.7; }
  .doc-body table { width: 100%; border-collapse: collapse; }
  .doc-body table td, .doc-body table th { border: 1px solid #ccc; padding: 4px 8px; }
  .doc-toolbar {
    position: fixed; top: 8mm; right: 8mm; z-index: 999;
    display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end;
  }
  .tb-btn {
    background: #0071e3; color: #fff; border: none;
    padding: 9px 15px; border-radius: 8px; cursor: pointer;
    font-size: 13px; font-weight: 600; font-family: -apple-system, "Segoe UI", Arial, sans-serif;
    display: inline-flex; align-items: center; gap: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,.16); transition: filter .15s, opacity .15s;
  }
  .tb-btn:hover  { filter: brightness(1.08); }
  .tb-btn:disabled { opacity: .6; cursor: default; }
  .tb-email    { background: #5856d6; }
  .tb-print    { background: #0071e3; }
  .tb-pdf      { background: #ff3b30; }
  .tb-whatsapp { background: #25d366; }
  .tb-toast {
    position: fixed; bottom: 16px; left: 50%; transform: translateX(-50%);
    background: #1d1d1f; color: #fff; padding: 10px 18px; border-radius: 10px;
    font: 13px -apple-system, "Segoe UI", Arial, sans-serif; z-index: 1000;
    box-shadow: 0 8px 24px rgba(0,0,0,.22); opacity: 0; transition: opacity .2s;
  }
  .tb-toast.show { opacity: 1; }
  @media print {
    body { background: white; }
    .page { margin: 0; box-shadow: none; padding: 15mm 20mm; }
    .doc-toolbar { display: none; }
  }
</style>
</head>
<body>

<div class="doc-toolbar" id="docToolbar">
  <button class="tb-btn tb-email"    onclick="emailDoc(this)">✉️ Email</button>
  <button class="tb-btn tb-print"    onclick="window.print()">🖨️ Print</button>
  <button class="tb-btn tb-pdf"      onclick="downloadPdf(this)">📄 PDF</button>
  <button class="tb-btn tb-whatsapp" onclick="shareWhatsApp()">💬 WhatsApp</button>
</div>

<div class="page" id="docPage">
<?= $letterHtml ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js"></script>
<script>
var DOC = {
  id       : <?= (int)$id ?>,
  csrf     : <?= json_encode(csrf_token()) ?>,
  title    : <?= json_encode($doc['Title'] . ' - ' . $doc['EmpName']) ?>,
  empName  : <?= json_encode($doc['EmpName']) ?>,
  company  : <?= json_encode($doc['CompanyName']) ?>,
  email    : <?= json_encode($empEmail) ?>,
  phone    : <?= json_encode($empPhone) ?>
};

function toast(msg) {
  var t = document.createElement('div');
  t.className = 'tb-toast';
  t.textContent = msg;
  document.body.appendChild(t);
  requestAnimationFrame(function () { t.classList.add('show'); });
  setTimeout(function () { t.classList.remove('show'); setTimeout(function () { t.remove(); }, 300); }, 3200);
}

function pdfFilename() {
  return (DOC.title || 'document').replace(/[^\w\-]+/g, '_') + '.pdf';
}

function downloadPdf(btn) {
  if (btn) btn.disabled = true;
  var el = document.getElementById('docPage');
  html2pdf().set({
    margin:       0,
    filename:     pdfFilename(),
    image:        { type: 'jpeg', quality: 0.98 },
    html2canvas:  { scale: 2, useCORS: true },
    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
  }).from(el).save().then(function () {
    if (btn) btn.disabled = false;
  }).catch(function () {
    if (btn) btn.disabled = false;
    toast('PDF generation failed.');
  });
}

function emailDoc(btn) {
  var to = prompt('Send this document to which email address?', DOC.email || '');
  if (to === null) return;
  to = to.trim();
  if (!to) { toast('No email address entered.'); return; }
  if (btn) { btn.disabled = true; }
  var body = new FormData();
  body.append('action', 'email');
  body.append('to', to);
  body.append('_csrf', DOC.csrf);
  fetch(window.location.href, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (btn) btn.disabled = false;
      toast(res.success ? ('Sent to ' + to) : (res.error || 'Send failed.'));
    })
    .catch(function () {
      if (btn) btn.disabled = false;
      toast('Send failed — network error.');
    });
}

function shareWhatsApp() {
  var msg = 'Hello ' + DOC.empName + ', please find your document "' + DOC.title +
            '" from ' + DOC.company + '. (Attach the downloaded PDF to this chat.)';
  // Download the PDF first so the user can attach it, then open WhatsApp.
  downloadPdf(null);
  var phone = DOC.phone || '';
  if (phone.length === 10) phone = '91' + phone;   // assume India if no country code
  var url = phone
    ? 'https://wa.me/' + phone + '?text=' + encodeURIComponent(msg)
    : 'https://wa.me/?text=' + encodeURIComponent(msg);
  window.open(url, '_blank');
}
</script>

</body>
</html>
