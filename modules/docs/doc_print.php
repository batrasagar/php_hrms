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
if ($user['role'] === 'admin') {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$doc['CompanyId'], $user['id']]);
    if (!$chk->fetch()) { http_response_code(403); exit('Access denied.'); }
} elseif ($user['role'] === 'user' && (int)($user['company_id'] ?? 0) !== (int)$doc['CompanyId']) {
    http_response_code(403); exit('Access denied.');
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
  .print-toolbar {
    position: fixed; top: 8mm; right: 8mm;
    background: #0071e3; color: white;
    border: none; padding: 8px 16px; border-radius: 6px;
    cursor: pointer; font-size: 13px; z-index: 999;
    display: flex; align-items: center; gap: 6px;
  }
  .print-toolbar:hover { background: #005bbf; }
  @media print {
    body { background: white; }
    .page { margin: 0; box-shadow: none; padding: 15mm 20mm; }
    .print-toolbar { display: none; }
  }
</style>
</head>
<body>

<button class="print-toolbar" onclick="window.print()">
  <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
    <path d="M5 1a2 2 0 0 0-2 2v1h10V3a2 2 0 0 0-2-2H5zm6 8H5a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1z"/>
    <path d="M0 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-1v-2a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v2H2a2 2 0 0 1-2-2V7zm2.5 1a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
  </svg>
  Print
</button>

<div class="page">
  <div class="doc-header">
    <h1><?= htmlspecialchars($doc['CompanyName']) ?></h1>
    <p>HR Department</p>
  </div>
  <div class="doc-meta">
    <div>
      <strong>To:</strong> <?= htmlspecialchars($doc['EmpName']) ?><br>
      <?php if ($doc['Designation']): ?><?= htmlspecialchars($doc['Designation']) ?><?php endif; ?>
      <?php if ($doc['Department']): ?> &nbsp;·&nbsp; <?= htmlspecialchars($doc['Department']) ?><?php endif; ?>
    </div>
    <div class="text-right" style="text-align:right">
      <strong>Doc:</strong> <?= htmlspecialchars($doc['Title']) ?><br>
      <strong>Date:</strong> <?= date('d/m/Y', strtotime($doc['IssuedOn'])) ?>
    </div>
  </div>
  <div class="doc-body">
    <?= $doc['Content'] ?>
  </div>
</div>

</body>
</html>
