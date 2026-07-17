<?php
// Topbar company switcher: persist the globally selected company in the session.
define('BASE_URL', '..');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
csrf_verify();

header('Content-Type: application/json');

$db   = getDb();
$user = currentUser();

if ($user['role'] === 'user') {
    echo json_encode(['success' => false, 'errors' => ['Not allowed.']]);
    exit;
}

$requested = (int)($_POST['company'] ?? 0);
$ids = array_map('intval', array_column(companiesForUser($db, $user), 'id'));

if (!$requested || !in_array($requested, $ids, true)) {
    echo json_encode(['success' => false, 'errors' => ['No access to the selected company.']]);
    exit;
}

$_SESSION['active_company_id'] = $requested;
echo json_encode(['success' => true, 'company' => $requested]);
