<?php
/**
 * HRMS settings helpers — company settings override global (CompanyId = 0).
 * Settings live in tblSettings (CompanyId, SettingKey, SettingValue).
 */

/** Merged settings for a company (global defaults + company overrides). */
function hrmsSettings(PDO $db, int $companyId): array {
    $out = [];
    try {
        $stmt = $db->prepare("SELECT CompanyId, SettingKey, SettingValue
                              FROM tblSettings WHERE CompanyId IN (0, ?) ORDER BY CompanyId ASC");
        $stmt->execute([$companyId]);
        foreach ($stmt->fetchAll() as $r) { $out[$r['SettingKey']] = $r['SettingValue']; }
    } catch (Exception $e) { /* table may not exist yet */ }
    return $out;
}

/** Single merged setting value, or the default when unset. */
function hrmsSetting(PDO $db, int $companyId, string $key, ?string $default = null): ?string {
    $s = hrmsSettings($db, $companyId);
    return array_key_exists($key, $s) ? $s[$key] : $default;
}

/**
 * Negative leave balances allowed for this company?
 * Defaults to TRUE (allow) to preserve prior behaviour — only an explicit '0' blocks.
 */
function hrmsAllowNegativeLeave(PDO $db, int $companyId): bool {
    $v = hrmsSetting($db, $companyId, 'allow_negative_leave', null);
    return $v === null ? true : ($v !== '0');
}

/**
 * Balance a leave type would have after adding $addDays, treating $excludeDate as
 * not-yet-counted (so overwriting an existing mark on that date is handled).
 * Balance = Allocated + Adjusted − (Used on other dates + addDays).
 */
function hrmsLeaveBalanceAfter(PDO $db, int $companyId, int $empId, int $ltId, int $year, string $excludeDate, float $addDays): float {
    $b = $db->prepare("SELECT Allocated, Adjusted FROM tblLeaveBalance
                       WHERE CompanyId=? AND EmployeeId=? AND LeaveTypeId=? AND Year=?");
    $b->execute([$companyId, $empId, $ltId, $year]);
    $row   = $b->fetch();
    $alloc = (float)($row['Allocated'] ?? 0);
    $adj   = (float)($row['Adjusted'] ?? 0);

    $u = $db->prepare("SELECT COALESCE(SUM(CASE WHEN LeaveType='full_day' THEN 1.0 ELSE 0.5 END),0)
                       FROM tblLeave
                       WHERE CompanyId=? AND EmployeeId=? AND LeaveTypeId=? AND YEAR(LeaveDate)=? AND LeaveDate<>?");
    $u->execute([$companyId, $empId, $ltId, $year, $excludeDate]);
    $usedOther = (float)$u->fetchColumn();

    return $alloc + $adj - ($usedOther + $addDays);
}

/**
 * Recompute tblLeaveBalance.Used for the given employees + year from live tblLeave rows.
 *
 * A plain "SUM(...) GROUP BY LeaveTypeId" recompute only touches leave types that still
 * have rows, so a type whose leaves are all deleted keeps its stale Used and shows a
 * phantom negative balance. This resets every existing balance row for the employee/year
 * to its live value first (0 when the type no longer has any leaves), then inserts any
 * new types — keeping stored Used authoritative after deletes.
 */
function hrmsRecalcLeaveUsed(PDO $db, int $companyId, array $empIds, int $year): void {
    $empIds = array_values(array_unique(array_filter(array_map('intval', $empIds))));
    if (!$empIds || !$year || !$companyId) return;
    $phE = implode(',', array_fill(0, count($empIds), '?'));

    // Live usage per employee + leave type
    $rows = $db->prepare(
        "SELECT EmployeeId, LeaveTypeId,
                SUM(CASE WHEN LeaveType='full_day' THEN 1.0 ELSE 0.5 END) AS Used
         FROM tblLeave
         WHERE CompanyId=? AND YEAR(LeaveDate)=? AND LeaveTypeId IS NOT NULL
           AND EmployeeId IN ($phE)
         GROUP BY EmployeeId, LeaveTypeId"
    );
    $rows->execute(array_merge([$companyId, $year], $empIds));
    $used = [];   // [empId][ltId] = days
    foreach ($rows->fetchAll() as $r) { $used[(int)$r['EmployeeId']][(int)$r['LeaveTypeId']] = $r['Used']; }

    $upd = $db->prepare(
        "INSERT INTO tblLeaveBalance (EmployeeId, CompanyId, LeaveTypeId, Year, Used)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE Used=VALUES(Used)"
    );

    // Reset every existing balance row to its live value (0 when the type has no leaves left)
    $existing = $db->prepare(
        "SELECT EmployeeId, LeaveTypeId FROM tblLeaveBalance
         WHERE CompanyId=? AND Year=? AND EmployeeId IN ($phE)"
    );
    $existing->execute(array_merge([$companyId, $year], $empIds));
    foreach ($existing->fetchAll() as $e) {
        $eid = (int)$e['EmployeeId']; $lt = (int)$e['LeaveTypeId'];
        $upd->execute([$eid, $companyId, $lt, $year, $used[$eid][$lt] ?? 0]);
        unset($used[$eid][$lt]);
    }
    // Insert live usage for types that had no balance row yet
    foreach ($used as $eid => $byLt) {
        foreach ($byLt as $lt => $u) { $upd->execute([$eid, $companyId, $lt, $year, $u]); }
    }
}
