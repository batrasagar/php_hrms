<?php
define('BASE_URL', '..');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/hrms_settings.php';
requireAdmin();
header('Content-Type: application/json');

$db   = getDb();
$user = currentUser();

function fail(string $m) { echo json_encode(['success' => false, 'errors' => [$m]]); exit; }
function ok(string $m)   { echo json_encode(['success' => true,  'message' => $m]);  exit; }

csrf_verify();

$action  = $_POST['action']   ?? '';
$company = (int)($_POST['company'] ?? 0);
$empId   = (int)($_POST['emp_id']  ?? 0);
$date    = trim($_POST['date']     ?? '');

if (!$company || !$empId || !$date) fail('Missing company, employee or date.');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) fail('Invalid date.');

// ── Company access ────────────────────────────────────────────────────────────
if ($user['role'] !== 'superadmin') {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$company, $user['scope_id']]);
    if (!$chk->fetch()) fail('Access denied for this company.');
}

// ── Employee belongs to company ───────────────────────────────────────────────
$emp = $db->prepare("SELECT EmployeeCode, Name FROM tblEmployee WHERE id=? AND CompanyId=?");
$emp->execute([$empId, $company]);
$empRow = $emp->fetch();
if (!$empRow) fail('Employee not found in this company.');
$empCode = $empRow['EmployeeCode'];
$year    = (int)substr($date, 0, 4);

// Recompute tblLeaveBalance.Used for this employee+year from tblLeave
function recalcLeaveBalance(PDO $db, int $empId, int $company, int $year): void {
    $rows = $db->prepare(
        "SELECT LeaveTypeId, SUM(CASE WHEN LeaveType='full_day' THEN 1.0 ELSE 0.5 END) AS Used
         FROM tblLeave
         WHERE CompanyId=? AND EmployeeId=? AND YEAR(LeaveDate)=? AND LeaveTypeId IS NOT NULL
         GROUP BY LeaveTypeId"
    );
    $rows->execute([$company, $empId, $year]);
    $used = [];
    foreach ($rows->fetchAll() as $r) { $used[(int)$r['LeaveTypeId']] = $r['Used']; }
    // Reset any existing balances for this employee/year that are no longer present
    $existing = $db->prepare("SELECT LeaveTypeId FROM tblLeaveBalance WHERE CompanyId=? AND EmployeeId=? AND Year=?");
    $existing->execute([$company, $empId, $year]);
    $upd = $db->prepare(
        "INSERT INTO tblLeaveBalance (EmployeeId, CompanyId, LeaveTypeId, Year, Used)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE Used=VALUES(Used)"
    );
    foreach ($existing->fetchAll() as $e) {
        $lt = (int)$e['LeaveTypeId'];
        $upd->execute([$empId, $company, $lt, $year, $used[$lt] ?? 0]);
        unset($used[$lt]);
    }
    foreach ($used as $lt => $u) { $upd->execute([$empId, $company, $lt, $year, $u]); }
}

switch ($action) {

    // ── Assign leave (EL/CL/SL, full/half) ────────────────────────────────────
    case 'leave': {
        $code    = trim($_POST['code']     ?? '');
        $dayType = $_POST['day_type']       ?? 'full_day';
        $reason  = trim($_POST['reason']    ?? '');
        if (!in_array($dayType, ['full_day','half_am','half_pm'], true)) $dayType = 'full_day';
        if (!$code) fail('Select a leave code.');

        $lt = $db->prepare("SELECT id FROM tblLeaveType WHERE CompanyId=? AND Code=? AND IsActive=1");
        $lt->execute([$company, $code]);
        $ltId = $lt->fetchColumn();
        if (!$ltId) fail('Unknown leave code for this company.');

        if (!hrmsAllowNegativeLeave($db, $company)) {
            $add   = $dayType === 'full_day' ? 1.0 : 0.5;
            $after = hrmsLeaveBalanceAfter($db, $company, $empId, (int)$ltId, $year, $date, $add);
            if ($after < 0) fail("Insufficient $code balance — would go to " . rtrim(rtrim(number_format($after, 1), '0'), '.') . " day(s). Enable 'Allow Leave Negative Balance' in Settings to override.");
        }

        $db->prepare(
            "INSERT INTO tblLeave (CompanyId, EmployeeId, LeaveDate, LeaveType, LeaveTypeId, LeaveCode, Reason, CreatedBy)
             VALUES (?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE LeaveType=VALUES(LeaveType), LeaveTypeId=VALUES(LeaveTypeId),
                                     LeaveCode=VALUES(LeaveCode), Reason=VALUES(Reason)"
        )->execute([$company, $empId, $date, $dayType, $ltId, $code, $reason ?: null, $user['id']]);
        recalcLeaveBalance($db, $empId, $company, $year);
        ok("$code marked for {$empRow['Name']} on $date.");
    }

    // ── Comp off: mark the clicked day as a comp-off day taken (CO marker) ─────
    case 'comp_off': {
        $reason = trim($_POST['reason'] ?? '') ?: 'Comp off';
        $db->prepare(
            "INSERT INTO tblLeave (CompanyId, EmployeeId, LeaveDate, LeaveType, LeaveTypeId, LeaveCode, Reason, CreatedBy)
             VALUES (?,?,?, 'full_day', NULL, 'CO', ?, ?)
             ON DUPLICATE KEY UPDATE LeaveType='full_day', LeaveTypeId=NULL, LeaveCode='CO', Reason=VALUES(Reason)"
        )->execute([$company, $empId, $date, $reason, $user['id']]);
        // CO carries no LeaveTypeId, so balances are unaffected; still recalc in case a
        // real leave previously occupied this date.
        recalcLeaveBalance($db, $empId, $company, $year);
        ok("Comp off marked for {$empRow['Name']} on $date.");
    }

    // ── Week off: change the employee's recurring weekly off day ──────────────
    case 'week_off_recurring': {
        $wd = $_POST['weekday'] ?? '';
        if ($wd === '' || !ctype_digit((string)$wd) || (int)$wd < 0 || (int)$wd > 6) fail('Pick a weekday.');
        $db->prepare("UPDATE tblEmployee SET WeekdayNo=?, UpdatedAt=NOW() WHERE id=? AND CompanyId=?")
           ->execute([(int)$wd, $empId, $company]);
        $names = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        ok("Weekly off for {$empRow['Name']} set to {$names[(int)$wd]}.");
    }

    // ── Week off: mark just this one date as WO (punch correction) ─────────────
    case 'week_off_date': {
        $db->prepare(
            "REPLACE INTO tblPunchLogCorrection (CompanyId, EmpCode, tDate, InTime, OutTime, AttStatus, Reason, CorrectedBy, CorrectedAt)
             VALUES (?,?,?, NULL, NULL, 'WO', 'Marked week-off from grid', ?, NOW())"
        )->execute([$company, $empCode, $date, $user['id']]);
        ok("$date marked as week-off for {$empRow['Name']}.");
    }

    // ── Manual time / forced status (punch correction) ────────────────────────
    case 'manual_time': {
        $in     = trim($_POST['in_time']  ?? '');
        $out    = trim($_POST['out_time'] ?? '');
        $status = strtoupper(trim($_POST['force_status'] ?? ''));
        $reason = trim($_POST['reason']   ?? '');
        $valid  = ['P','A','HD','WO','WOP','PH','L','SL','CO','OD',''];
        if (!in_array($status, $valid, true)) fail('Invalid status code.');
        if (!$in && !$out && !$status) fail('Enter In/Out time or a forced status.');
        $db->prepare(
            "REPLACE INTO tblPunchLogCorrection (CompanyId, EmpCode, tDate, InTime, OutTime, AttStatus, Reason, CorrectedBy, CorrectedAt)
             VALUES (?,?,?,?,?,?,?,?, NOW())"
        )->execute([$company, $empCode, $date, $in ?: null, $out ?: null, $status ?: null, $reason, $user['id']]);
        ok("Manual entry saved for {$empRow['Name']} on $date.");
    }

    // ── Clear all overrides for this cell ─────────────────────────────────────
    case 'clear': {
        $db->prepare("DELETE FROM tblLeave WHERE CompanyId=? AND EmployeeId=? AND LeaveDate=?")
           ->execute([$company, $empId, $date]);
        $db->prepare("DELETE FROM tblPunchLogCorrection WHERE CompanyId=? AND EmpCode=? AND tDate=?")
           ->execute([$company, $empCode, $date]);
        recalcLeaveBalance($db, $empId, $company, $year);
        ok("Cleared overrides for {$empRow['Name']} on $date.");
    }

    default:
        fail('Unknown action.');
}
