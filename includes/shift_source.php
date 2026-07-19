<?php
/**
 * Effective-dated shift and weekly-off resolution.
 *
 * tblEmployee.ShiftNo / .WeekdayNo hold a single CURRENT value with no date, so
 * changing them silently rewrote history — every past date recomputed on the new
 * shift. tblEmployeeShiftAssign (M034) stores dated rows instead: each row applies
 * from its EffectiveFrom until the next row for that employee, so "shift 1 up to
 * the 5th, shift 2 on the 6th, shift 1 again from the 9th" is three rows.
 *
 * Precedence for any given date, highest first:
 *   1. tblPunchLogCorrection.ShiftNo  — a one-off manual override for that date
 *   2. the dated assignment in effect  — tblEmployeeShiftAssign
 *   3. tblEmployee.ShiftNo / WeekdayNo — the legacy standing value
 *
 * With no dated rows the behaviour is byte-for-byte the legacy behaviour, which
 * is what keeps this safe to deploy before anyone creates an assignment.
 */

/**
 * All dated assignments for a company that could apply on or before $upto,
 * as [EmployeeId => [ ['from'=>Y-m-d, 'shift'=>?int, 'weekday'=>?int], … ]] sorted
 * oldest first. Returns [] when the table is missing (pre-migration).
 */
function shiftAssignLoad(PDO $db, int $companyId, string $upto): array {
    $out = [];
    try {
        $st = $db->prepare(
            "SELECT EmployeeId, ShiftNo, WeekdayNo, EffectiveFrom
               FROM tblEmployeeShiftAssign
              WHERE CompanyId = ? AND EffectiveFrom <= ?
              ORDER BY EmployeeId, EffectiveFrom"
        );
        $st->execute([$companyId, $upto]);
        foreach ($st->fetchAll() as $r) {
            $out[(int)$r['EmployeeId']][] = [
                'from'    => $r['EffectiveFrom'],
                'shift'   => $r['ShiftNo']   === null ? null : (int)$r['ShiftNo'],
                'weekday' => $r['WeekdayNo'] === null ? null : (int)$r['WeekdayNo'],
            ];
        }
    } catch (PDOException $e) { /* table not created yet */ }
    return $out;
}

/** The assignment in effect on $date for one employee's (sorted) rows, or null. */
function shiftAssignOn(?array $rows, string $date): ?array {
    if (!$rows) return null;
    $found = null;
    foreach ($rows as $r) {              // sorted oldest first; last match wins
        if ($r['from'] <= $date) $found = $r;
        else break;
    }
    return $found;
}

/**
 * Shift id in effect for an employee on a date.
 * $corrShift is tblPunchLogCorrection.ShiftNo for that date (null when absent).
 */
function shiftForDate(?array $rows, string $date, ?int $corrShift, ?int $standingShift): int {
    if ($corrShift) return $corrShift;
    $a = shiftAssignOn($rows, $date);
    if ($a !== null) return (int)($a['shift'] ?? 0);
    return (int)($standingShift ?? 0);
}

/**
 * Weekly-off weekday (0=Sun … 6=Sat) in effect on a date, or null for "no weekly off".
 * Values outside 0–6 are treated as Sunday: WeekdayNo=7 exists in the data and would
 * otherwise never match date('w'), silently giving that employee no weekly off at all.
 */
function weekOffForDate(?array $rows, string $date, $standingWeekday): ?int {
    $a = shiftAssignOn($rows, $date);
    if ($a !== null) {
        // A dated row is explicit: a NULL weekday there means "no weekly off at all".
        if ($a['weekday'] === null) return null;
        $val = $a['weekday'];
    } else {
        // No dated row → the standing value, and a NULL standing value keeps the
        // legacy default of Sunday. 60 employees have WeekdayNo NULL and currently
        // get Sunday off; treating that as "no weekly off" would quietly cost them
        // roughly four paid days a month.
        if ($standingWeekday === null || $standingWeekday === '') return 0;
        $val = $standingWeekday;
    }
    $n = (int)$val;
    return ($n >= 0 && $n <= 6) ? $n : 0;
}

/**
 * Create or update the dated assignment starting on $date.
 *
 * $shift / $weekday take three kinds of value:
 *   false  → leave this half unchanged (inherit whatever applies on $date)
 *   null   → explicitly none (no shift / no weekly off) from this date
 *   int    → set to this value
 *
 * Inheriting matters: setting only the shift must not silently clear the weekly
 * off, and vice versa, because a row carries both.
 */
function shiftAssignSet(PDO $db, int $companyId, int $empId, string $date,
                        $shift = false, $weekday = false,
                        string $reason = '', ?int $userId = null): void {
    $rows = shiftAssignLoad($db, $companyId, $date)[$empId] ?? null;
    $cur  = shiftAssignOn($rows, $date);

    if ($cur === null) {                       // nothing dated yet → fall back to standing
        $s = $db->prepare("SELECT ShiftNo, WeekdayNo FROM tblEmployee WHERE id=? AND CompanyId=?");
        $s->execute([$empId, $companyId]);
        $st  = $s->fetch() ?: [];
        $cur = [
            'shift'   => isset($st['ShiftNo'])   && $st['ShiftNo']   !== null ? (int)$st['ShiftNo']   : null,
            'weekday' => isset($st['WeekdayNo']) && $st['WeekdayNo'] !== null ? (int)$st['WeekdayNo'] : null,
        ];
    }

    $newShift   = ($shift   === false) ? $cur['shift']   : ($shift   === null ? null : (int)$shift);
    $newWeekday = ($weekday === false) ? $cur['weekday'] : ($weekday === null ? null : (int)$weekday);

    $db->prepare(
        "INSERT INTO tblEmployeeShiftAssign (CompanyId, EmployeeId, ShiftNo, WeekdayNo, EffectiveFrom, Reason, CreatedBy)
         VALUES (?,?,?,?,?,?,?)
         ON DUPLICATE KEY UPDATE ShiftNo=VALUES(ShiftNo), WeekdayNo=VALUES(WeekdayNo),
                                 Reason=VALUES(Reason), CreatedBy=VALUES(CreatedBy)"
    )->execute([$companyId, $empId, $newShift, $newWeekday, $date, $reason, $userId]);
}

/** True when $date is the employee's weekly off. */
function isWeekOffDate(?array $rows, string $date, $standingWeekday): bool {
    $wo = weekOffForDate($rows, $date, $standingWeekday);
    if ($wo === null) return false;
    return (int)date('w', strtotime($date)) === $wo;   // date('w'): 0=Sun … 6=Sat
}
