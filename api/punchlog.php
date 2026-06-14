<?php
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

function jsonError(string $message, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

$serialNumber = trim($_GET['SerialNumber'] ?? '');
$lastSerial   = $_GET['LastSerial'] ?? null;

if ($serialNumber === '') {
    jsonError('Missing required parameter: SerialNumber');
}

$lastSerialId = null;
if ($lastSerial !== null) {
    if (!ctype_digit((string) $lastSerial) || (int) $lastSerial < 0) {
        jsonError('LastSerial must be a non-negative integer');
    }
    $lastSerialId = (int) $lastSerial;
}

try {
    $db = getDb();

    // API key auth — key passed as X-Api-Key header, matched against SHA-256 hash in DB
    $rawKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($rawKey === '') {
        jsonError('Missing X-Api-Key header', 401);
    }
    $keyHash = hash('sha256', $rawKey);
    $keyStmt = $db->prepare(
        'SELECT k.UserId, u.Role FROM tblApiKeys k
         LEFT JOIN tblUser u ON u.id = k.UserId
         WHERE k.KeyHash = ? AND k.IsActive = 1 LIMIT 1'
    );
    $keyStmt->execute([$keyHash]);
    $keyRow = $keyStmt->fetch();
    if (!$keyRow) {
        jsonError('Invalid or inactive API key', 401);
    }

    // Verify the device is accessible to the key's user.
    // superadmin: all devices; others: only devices whose company belongs to the user.
    if ($keyRow['Role'] === 'superadmin') {
        $check = $db->prepare('SELECT id FROM tblDevices WHERE SerialNumber = ? LIMIT 1');
        $check->execute([$serialNumber]);
    } else {
        $check = $db->prepare(
            'SELECT d.id FROM tblDevices d
             JOIN tblCompany c ON c.Name = d.Company AND c.AdminId = ?
             WHERE d.SerialNumber = ? LIMIT 1'
        );
        $check->execute([$keyRow['UserId'], $serialNumber]);
    }
    if (!$check->fetch()) {
        jsonError('SerialNumber not found or not authorized for this API key', 404);
    }

    $sql = 'SELECT dl.id, dl.SerialNumber, dl.EnrollId, dl.PunchDateTime, dl.Mode, dl.CreatedAt
            FROM tblDeviceLog dl
            WHERE dl.SerialNumber = ?';
    $params = [$serialNumber];

    if ($lastSerialId !== null) {
        $sql     .= ' AND dl.id > ?';
        $params[] = $lastSerialId;
    }

    $sql .= ' ORDER BY dl.id ASC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'count'   => count($rows),
        'data'    => $rows,
    ]);

} catch (\PDOException $e) {
    jsonError('Database error: ' . $e->getMessage(), 500);
}
