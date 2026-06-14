<?php
define('BASE_URL', '..');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$cid = (int)($_GET['company_id'] ?? 0);
if (!$cid) { echo '[]'; exit; }

$rows = getDb()->prepare("
    SELECT EmployeeCode AS code, Name AS name, EnrollId AS enroll
    FROM tblEmployee
    WHERE CompanyId = ? AND Status = 'active'
    ORDER BY Name
");
$rows->execute([$cid]);
echo json_encode($rows->fetchAll(PDO::FETCH_ASSOC));
