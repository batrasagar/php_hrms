<?php
/**
 * iclock/cdata.php — ZKTeco push handler
 * Receives ATTLOG/OPERLOG pushes from biometric devices.
 */
require_once __DIR__ . '/../config/db.php';

$srNo  = $_GET['SN']      ?? '';
$ver   = $_GET['pushver'] ?? '';
$table = $_GET['table']   ?? '';
$stamp = $_GET['stamp']   ?? '';

// ---- GET: device polls for config ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    updateLastPing($srNo);
    sendDeviceConfig($srNo);
    exit;
}

// ---- POST: device pushes data ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Heartbeat / option tables — just update ping and ack
    if (in_array($table, ['OPERLOG', 'options', 'OPERLOG '])) {
        updateLastPing($srNo);
        sendOK($ver);
        exit;
    }

    // Attendance log
    if ($table === 'ATTLOG' || $table === '') {
        $rawInput = file_get_contents('php://input');
        $inserted = insertAttLog($srNo, $stamp, $rawInput);
        if ($inserted >= 0) {
            sendOK($ver);
            exit;
        }
    }
}

// Fallback — send config
sendDeviceConfig($srNo);

// ---------------------------------------------------------------------------

function insertAttLog(string $srNo, string $stamp, string $data): int {
    if (trim($data) === '') return 0;

    $db   = getDb();
    $sql  = "INSERT IGNORE INTO tblDeviceLog (SerialNumber, EnrollId, PunchDateTime, Mode, Stamp)
             VALUES (:sn, :eid, :pdt, :mode, :stamp)";
    $stmt = $db->prepare($sql);

    $count = 0;
    foreach (explode("\n", $data) as $line) {
        $line = trim($line);
        if ($line === '') continue;

        $cols     = explode("\t", $line);
        $enrollId = $cols[0] ?? '';
        $rawDt    = $cols[1] ?? '';
        $mode     = $cols[2] ?? '';

        // Normalise datetime — strip seconds to HH:MM if full format
        $punchDt = substr($rawDt, 0, 16); // "YYYY-MM-DD HH:MM"

        if (!$enrollId || !strtotime($punchDt)) continue;

        $stmt->execute([
            ':sn'    => $srNo,
            ':eid'   => $enrollId,
            ':pdt'   => $punchDt,
            ':mode'  => $mode,
            ':stamp' => $stamp,
        ]);
        $count++;
    }

    // Update device stamp & last ping
    $db->prepare("UPDATE tblDevices SET Stamp=?, LastPing=UTC_TIMESTAMP() + INTERVAL 330 MINUTE WHERE SerialNumber=?")
       ->execute([$stamp ?: '1541180497', $srNo]);

    return $count;
}

function updateLastPing(string $srNo): void {
    if (!$srNo) return;
    getDb()->prepare("UPDATE tblDevices SET LastPing = UTC_TIMESTAMP() + INTERVAL 330 MINUTE WHERE SerialNumber = ?")
           ->execute([$srNo]);
}

function getStamp(string $srNo): string {
    $stmt = getDb()->prepare("SELECT Stamp FROM tblDevices WHERE SerialNumber = ?");
    $stmt->execute([$srNo]);
    return $stmt->fetchColumn() ?: '1541180497';
}

function sendOK(string $ver): void {
    if ($ver === '2.4.1') {
        echo "GET OPTION FROM: 354313\nStamp=82983982\nOpStamp=9238883\n";
    } else {
        echo "OK";
    }
}

function sendDeviceConfig(string $srNo): void {
    $stamp = getStamp($srNo);
    echo "Stamp={$stamp}\n";
    echo "OpStamp=1541180497\n";
    echo "PhotoStamp=1541180497\n";
    echo "ErrorDelay=60\n";
    echo "Delay=30\n";
    echo "TransTimes=18:20;18:25\n";
    echo "TransInterval=1\n";
    echo "TransFlag=1111000000\n";
    echo "Realtime=1\n";
    echo "TimeOut=60\n";
    echo "TimeZone=330\n";
    echo "Encrypt=0";
}
