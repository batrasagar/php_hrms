<?php
define('BASE_URL', '..');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

$db   = getDb();
$user = currentUser();

function attendMinsToHm(int $m): string {
    if ($m <= 0) return '';
    $h = (int)floor($m / 60); $mn = $m % 60;
    return ($h ? $h.'h' : '') . ($mn ? $mn.'m' : ($h ? '' : '0m'));
}

// ── Company access ────────────────────────────────────────────────────────────
if ($user['role'] === 'user') {
    $fCompany = $user['company_id'];
} else {
    $fCompany = (int)($_GET['company'] ?? 0);
    if ($fCompany && in_array($user['role'], ['admin','operator'], true)) {
        $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $chk->execute([$fCompany, $user['scope_id']]);
        if (!$chk->fetch()) $fCompany = 0;
    }
}

if (!$fCompany) {
    echo json_encode(['success' => false, 'errors' => ['No company selected or access denied.']]);
    exit;
}

$fFrom       = trim($_GET['from']       ?? date('Y-m-d'));
$fTo         = trim($_GET['to']         ?? date('Y-m-d'));
$fDept       = trim($_GET['dept']       ?? '');
$fContractor = trim($_GET['contractor'] ?? '');

$coStmt = $db->prepare("SELECT Name FROM tblCompany WHERE id=?");
$coStmt->execute([$fCompany]);
$companyDisplayName = $coStmt->fetchColumn() ?: '';

// ── Load settings (company overrides global) ──────────────────────────────────
$settings = [];
try {
    $sRows = $db->query("SELECT CompanyId, SettingKey, SettingValue FROM tblSettings WHERE CompanyId IN (0, $fCompany) ORDER BY CompanyId ASC")->fetchAll();
    foreach ($sRows as $sr) {
        $settings[$sr['SettingKey']] = $sr['SettingValue']; // company row (DESC order) wins
    }
} catch (Exception $e) { /* table may not exist yet */ }
$showHolPunch = !empty($settings['show_holiday_punches']);
$showLvPunch  = !empty($settings['show_leave_punches']);
$showWoPunch  = !empty($settings['show_weekoff_punches']);
$showBefDoj   = !empty($settings['show_before_doj']);
$showAftDol   = !empty($settings['show_after_dol']);
$showOt       = ($settings['show_ot_report'] ?? '1') !== '0';  // default: show

// ── OT calculation config ─────────────────────────────────────────────────────
$otBefore     = !empty($settings['ot_before_shift']);   // count early arrival
$otAfter      = !empty($settings['ot_after_shift']);    // count late departure
$otManualOnly = !empty($settings['ot_manual_only']);    // ignore punch-based OT
$otClampOut   = !empty($settings['ot_clamp_out']) || isCompliance(); // clamp out-punch beyond OT limit (forced ON for compliance users)
$otMaxHours   = array_key_exists('ot_max_hours', $settings) ? (float)$settings['ot_max_hours'] : null;
$otMaxMin     = ($otMaxHours !== null && $otMaxHours > 0) ? (int)round($otMaxHours * 60) : null;
$otSlabs      = json_decode($settings['ot_slabs'] ?? '', true);
if (!is_array($otSlabs)) $otSlabs = [];

/** Deterministic ±5 min jitter (stable per employee+date so the report doesn't flicker). */
function otClampMinutes(int $boundaryMin, string $seed): int {
    $off = (int)(crc32($seed) % 11) - 5;   // -5..+5
    return max(0, min(1439, $boundaryMin + $off));
}

/** Lunch minutes to subtract from worked time when the employee was present through
 *  the shift's lunch window. Returns 0 when the shift has no lunch or they weren't. */
function otLunchDeduct(?array $shiftRec, int $inMins, int $outMins): int {
    if (!$shiftRec || empty($shiftRec['HasLunch'])) return 0;
    $lo = otHm2min($shiftRec['LunchOutTime'] ?? null);
    $li = otHm2min($shiftRec['LunchInTime']  ?? null);
    if ($lo === null || $li === null || $li <= $lo) return 0;
    return ($inMins <= $lo && $outMins >= $li) ? ($li - $lo) : 0;
}

/** Raw OT minutes → credited OT minutes via slab rules (inclusive ranges). */
function otApplySlab(int $raw, array $slabs): int {
    if ($raw <= 0 || !$slabs) return 0;
    $best = 0;
    foreach ($slabs as $s) {
        $from = (int)($s['from'] ?? 0); $to = (int)($s['to'] ?? 0); $cr = (int)($s['credit'] ?? 0);
        if ($raw >= $from && $raw <= $to) return $cr;
        if ($raw > $to) $best = $cr;   // beyond all defined ranges → highest slab credit
    }
    return $best;
}

/** "HH:MM[:SS]" → minutes since midnight, or null. */
function otHm2min(?string $t): ?int {
    if (!$t) return null;
    return (int)substr($t, 0, 2) * 60 + (int)substr($t, 3, 2);
}

// ── Employees ─────────────────────────────────────────────────────────────────
$where  = ['e.CompanyId = ?'];
$params = [$fCompany];
if ($fDept)       { $where[] = 'e.Department = ?'; $params[] = $fDept; }
if ($fContractor) { $where[] = 'e.Contractor = ?'; $params[] = $fContractor; }
$estmt = $db->prepare(
    "SELECT e.id, e.EmployeeCode, e.EnrollId, e.Name, e.FatherName, e.Designation, e.Contractor, e.Department, e.ShiftNo, e.JoinDate, e.DOL FROM tblEmployee e
     WHERE " . implode(' AND ', $where) . " AND e.Status='active'
     ORDER BY e.Department, ISNULL(e.Sr), e.Sr, e.Name"
);
$estmt->execute($params);
$employees = $estmt->fetchAll();

// ── Shifts ────────────────────────────────────────────────────────────────────
$shiftMap = [];
$sftStmt  = $db->prepare("SELECT id, HrsP, HrsHlf, ArrivalTime, DepartureTime, HasLunch, LunchOutTime, LunchInTime FROM tblShift WHERE CompanyId=? AND IsActive=1");
$sftStmt->execute([$fCompany]);
foreach ($sftStmt->fetchAll() as $sr) $shiftMap[(int)$sr['id']] = $sr;

// ── Leaves, holidays, punches ─────────────────────────────────────────────────
$leaveDates   = [];
$leaveCodes   = [];
$corrMap      = [];
$holidayDates = [];
$punchMap     = [];
$otMap        = [];   // [EmployeeId][date] => manual OT hours
$fetchErrors  = [];

if (!empty($employees)) {
    $ids = implode(',', array_column($employees, 'id'));

    foreach ($db->query(
        "SELECT EmployeeId, OTDate, OTHours FROM tblOvertime
         WHERE EmployeeId IN ($ids) AND OTDate BETWEEN '$fFrom' AND '$fTo'"
    )->fetchAll() as $ot) {
        $otMap[$ot['EmployeeId']][$ot['OTDate']] = (float)$ot['OTHours'];
    }

    foreach ($db->query(
        "SELECT EmployeeId, LeaveDate, LeaveType, LeaveCode FROM tblLeave
         WHERE EmployeeId IN ($ids) AND LeaveDate BETWEEN '$fFrom' AND '$fTo'"
    )->fetchAll() as $lv) {
        $leaveDates[$lv['EmployeeId']][$lv['LeaveDate']] = $lv['LeaveType'];
        $leaveCodes[$lv['EmployeeId']][$lv['LeaveDate']] = $lv['LeaveCode'];
    }

    // Manual corrections (override punches / force a status)
    $corrStmt = $db->prepare("SELECT EmpCode, tDate, InTime, OutTime, AttStatus, ShiftNo FROM tblPunchLogCorrection WHERE CompanyId=? AND tDate BETWEEN ? AND ?");
    $corrStmt->execute([$fCompany, $fFrom, $fTo]);
    foreach ($corrStmt->fetchAll() as $cr) {
        $corrMap[$cr['EmpCode']][$cr['tDate']] = $cr;
    }

    $hStmt = $db->prepare("SELECT HolidayDate, Name FROM tblHoliday WHERE CompanyId=? AND HolidayDate BETWEEN ? AND ?");
    $hStmt->execute([$fCompany, $fFrom, $fTo]);
    foreach ($hStmt->fetchAll() as $h) $holidayDates[$h['HolidayDate']] = $h['Name'];

    // Enrollment maps
    $enrollMap = [];
    $enrStmt   = $db->prepare("SELECT DeviceSerial, EnrollId, EmpCode FROM tblDeviceEnrollment WHERE CompanyId=?");
    $enrStmt->execute([$fCompany]);
    foreach ($enrStmt->fetchAll() as $row) {
        $enrollMap[$row['DeviceSerial']][$row['EnrollId']] = $row['EmpCode'];
    }

    $empEnrollFallback = [];
    foreach ($employees as $emp) {
        if ($emp['EnrollId'] !== '' && $emp['EnrollId'] !== null) {
            $empEnrollFallback[(string)$emp['EnrollId']] = $emp['EmployeeCode'];
        }
    }

    // Punch data from ADMS
    $cred = null;
    try { $cred = $db->query("SELECT * FROM tblAdmsCredentials WHERE IsActive=1 ORDER BY id LIMIT 1")->fetch(); } catch (Exception $e) {}

    if ($cred) {
        $coName = $db->prepare("SELECT Name FROM tblCompany WHERE id=?");
        $coName->execute([$fCompany]);
        $companyName    = $coName->fetchColumn();
        $companyDevices = [];
        if ($companyName) {
            $dStmt = $db->prepare("SELECT SerialNumber FROM tblDevices WHERE Company=?");
            $dStmt->execute([$companyName]);
            $companyDevices = $dStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $devSerials = array_values(array_unique(array_merge(array_keys($enrollMap), $companyDevices)));
        if (empty($devSerials)) {
            $fetchErrors[] = 'No devices linked to this company.';
        }

        $fromDt = $fFrom . ' 00:00:00';
        $toDt   = $fTo   . ' 23:59:59';

        foreach ($devSerials as $serial) {
            $url = rtrim($cred['Endpoint'], '/') . '/api/punchlog.php?SerialNumber=' . urlencode($serial);
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['X-Api-Key: ' . $cred['ApiKey']],
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr || $httpCode !== 200) {
                $fetchErrors[] = "Device {$serial}: " . ($curlErr ?: "HTTP {$httpCode}");
                continue;
            }

            $data = json_decode($response, true);
            if (empty($data['success']) || empty($data['data'])) continue;

            foreach ($data['data'] as $punch) {
                $pdt = $punch['PunchDateTime'] ?? '';
                if ($pdt < $fromDt || $pdt > $toDt) continue;
                $eid  = (string)($punch['EnrollId'] ?? '');
                $pSN  = $punch['SerialNumber'] ?? $serial;
                $date = substr($pdt, 0, 10);
                $time = substr($pdt, 11, 5);

                $empCode = $enrollMap[$pSN][$eid]
                        ?? $enrollMap[$serial][$eid]
                        ?? $empEnrollFallback[$eid]
                        ?? null;
                if (!$empCode) continue;

                if (!isset($punchMap[$empCode][$date])) {
                    $punchMap[$empCode][$date] = ['in' => $time, 'out' => $time, 'count' => 0, 'punches' => []];
                }
                $entry = &$punchMap[$empCode][$date];
                $entry['count']++;
                $entry['punches'][] = $time;
                if ($time < $entry['in'])  $entry['in']  = $time;
                if ($time > $entry['out']) $entry['out'] = $time;
                unset($entry);
            }
        }
    }
}

// ── Date range (max 31 days) ──────────────────────────────────────────────────
$dates  = [];
$ts     = strtotime($fFrom);
$tsEnd  = strtotime($fTo);
while ($ts <= $tsEnd) { $dates[] = date('Y-m-d', $ts); $ts = strtotime('+1 day', $ts); }
$notice = '';
if (count($dates) > 31) { $dates = array_slice($dates, 0, 31); $notice = 'Showing first 31 days only.'; }

$today = date('Y-m-d');

// ── Build dates metadata ──────────────────────────────────────────────────────
$datesData = [];
foreach ($dates as $dt) {
    $dow   = (int)date('N', strtotime($dt));
    $isSun = ($dow === 7);
    $isHol = isset($holidayDates[$dt]);
    $datesData[] = [
        'date'      => $dt,
        'dayNum'    => date('d', strtotime($dt)),
        'dayLetter' => substr(date('D', strtotime($dt)), 0, 1),
        'dayName'   => date('D', strtotime($dt)),
        'isSun'     => $isSun,
        'isHol'     => $isHol,
        'isFut'     => ($dt > $today),
        'holName'   => $isHol ? $holidayDates[$dt] : '',
        'bg'        => $isSun ? '#f0f0f0' : ($isHol ? '#e8f5e9' : ''),
    ];
}

// ── Build employee rows & totals ──────────────────────────────────────────────
$dayTotals    = [];
foreach ($dates as $dt) $dayTotals[$dt] = ['P'=>0,'HP'=>0,'A'=>0,'L'=>0,'HL'=>0,'CO'=>0];
$grand        = ['P'=>0,'HP'=>0,'A'=>0,'L'=>0,'HL'=>0,'CO'=>0];
$employeeRows = [];

foreach ($employees as $e) {
    $presentDays = 0; $hpDays = 0; $absentDays = 0; $fullLv = 0; $halfLv = 0; $compOff = 0;
    $days = [];

    foreach ($dates as $dt) {
        $dow    = (int)date('N', strtotime($dt));
        $isSun  = ($dow === 7);
        $isHol  = isset($holidayDates[$dt]);
        $isFut  = ($dt > $today);
        $lvType = $leaveDates[$e['id']][$dt] ?? null;
        $lvCode = $leaveCodes[$e['id']][$dt] ?? null;
        $punch  = $punchMap[$e['EmployeeCode']][$dt] ?? null;
        $cell   = ['type' => ''];

        // DOJ / DOL blanking (overrides everything else)
        $doj = $e['JoinDate'] ?? '';
        $dol = $e['DOL']      ?? '';
        if (!$showBefDoj && $doj && $dt < $doj) {
            $days[$dt] = $cell; continue;
        }
        if (!$showAftDol && $dol && $dt > $dol) {
            $days[$dt] = $cell; continue;
        }

        // Manual correction: In/Out override the punch; AttStatus forces the cell.
        $corr = $corrMap[$e['EmployeeCode']][$dt] ?? null;
        if ($corr && ($corr['InTime'] || $corr['OutTime'])) {
            $ci = $corr['InTime']  ? substr($corr['InTime'],  0, 5) : null;
            $co = $corr['OutTime'] ? substr($corr['OutTime'], 0, 5) : null;
            $pk = array_values(array_filter([$ci, $co]));
            $punch = ['in' => ($ci ?: $co), 'out' => ($co ?: $ci), 'count' => count($pk), 'punches' => $pk];
        }
        if ($corr && !empty($corr['AttStatus'])) {
            $fmap = ['P'=>'P','OD'=>'P','A'=>'A','HD'=>'HP','L'=>'L','SL'=>'L','CO'=>'CO','PH'=>'HOL','WO'=>'WO','WOP'=>'WO'];
            $ft = $fmap[$corr['AttStatus']] ?? 'A';
            $cell['type'] = $ft;
            $cell['corr'] = true;
            switch ($ft) {
                case 'P':   $presentDays++; $dayTotals[$dt]['P']++;  break;
                case 'HP':  $hpDays++;      $dayTotals[$dt]['HP']++; break;
                case 'A':   $absentDays++;  $dayTotals[$dt]['A']++;  break;
                case 'L':   $fullLv++;      $dayTotals[$dt]['L']++;  break;
                case 'CO':  $compOff++;     $dayTotals[$dt]['CO']++; break;
                case 'HOL': $cell['holName'] = $holidayDates[$dt] ?? 'Holiday'; break;
            }
            // Keep punch times visible for present/half days AND for forced-absent
            // days (mark-absent-but-show-punches). 'A' is flagged so the grid renders
            // the punches under the A badge.
            if (($ft === 'P' || $ft === 'HP' || $ft === 'A') && $punch) {
                $inMins  = (int)substr($punch['in'], 0, 2) * 60 + (int)substr($punch['in'], 3);
                $outMins = (int)substr($punch['out'],0, 2) * 60 + (int)substr($punch['out'],3);
                $totMins = ($punch['count'] > 1) ? max(0, $outMins - $inMins) : 0;
                $fShiftNo = ($corr && !empty($corr['ShiftNo'])) ? (int)$corr['ShiftNo'] : (int)($e['ShiftNo'] ?? 0);
                $totMins = max(0, $totMins - otLunchDeduct($shiftMap[$fShiftNo] ?? null, $inMins, $outMins));
                $cell['in']  = $punch['in'];
                $cell['out'] = $punch['count'] > 1 ? $punch['out'] : null;
                $cell['tot'] = attendMinsToHm($totMins);
                $sorted = $punch['punches']; sort($sorted); $cell['punches'] = $sorted;
                if ($ft === 'A') $cell['absPunch'] = true;
            }
            $days[$dt] = $cell; continue;
        }

        if ($isSun && !($showWoPunch && $punch)) {
            $cell['type'] = 'SUN';
        } elseif ($isHol && !($showHolPunch && $punch)) {
            $cell['type']    = 'HOL';
            $cell['holName'] = $holidayDates[$dt];
        } elseif ($lvType === 'full_day' && !($showLvPunch && $punch)) {
            // Comp-off (code CO) renders as its own marker, tallied separately
            if ($lvCode === 'CO') {
                $cell['type'] = 'CO';
                $compOff++; $dayTotals[$dt]['CO']++;
            } else {
                $cell['type']   = 'L';
                $cell['lvCode'] = $lvCode;
                $fullLv++; $dayTotals[$dt]['L']++;
            }
        } elseif (($lvType === 'half_am' || $lvType === 'half_pm') && !($showLvPunch && $punch)) {
            $cell['type']   = 'HL';
            $cell['lvSub']  = $lvType === 'half_am' ? 'AM' : 'PM';
            $cell['lvCode'] = $lvCode;
            $halfLv++; $dayTotals[$dt]['HL']++;
        } elseif ($punch) {
            // Per-date shift override (correction.ShiftNo) wins over the employee's standing shift.
            $dayShiftNo = ($corr && !empty($corr['ShiftNo'])) ? (int)$corr['ShiftNo'] : (int)($e['ShiftNo'] ?? 0);
            $shiftRec  = $shiftMap[$dayShiftNo] ?? null;
            $shiftHrsP = $shiftRec ? (float)$shiftRec['HrsP']   : 8.0;
            $shiftHrsH = $shiftRec ? (float)$shiftRec['HrsHlf'] : 4.0;
            $inMins    = (int)substr($punch['in'],  0, 2) * 60 + (int)substr($punch['in'],  3);
            $outMins   = (int)substr($punch['out'], 0, 2) * 60 + (int)substr($punch['out'], 3);

            // ── Clamp out-punch beyond the OT limit ───────────────────────────
            // If out is later than shift-end + max OT hours, replace the shown out
            // time with one near that limit (±5 min), which also caps the OT.
            $depMinC = otHm2min($shiftRec['DepartureTime'] ?? null);
            if ($otClampOut && $otMaxMin !== null && $depMinC !== null && $punch['count'] > 1) {
                $boundary = $depMinC + $otMaxMin;
                if ($outMins > $boundary) {
                    $outMins = otClampMinutes($boundary, ($e['EmployeeCode'] ?? '') . $dt);
                    $newOut  = sprintf('%02d:%02d', intdiv($outMins, 60), $outMins % 60);
                    $punch['out'] = $newOut;
                    if (!empty($punch['punches'])) $punch['punches'][count($punch['punches']) - 1] = $newOut;
                }
            }

            $totMins   = ($punch['count'] > 1) ? max(0, $outMins - $inMins) : 0;
            $totMins   = max(0, $totMins - otLunchDeduct($shiftRec, $inMins, $outMins));  // net of lunch

            // ── Overtime ──────────────────────────────────────────────────────
            // Manual OT (tblOvertime) always wins for a day it is set on. Otherwise,
            // if not manual-only, compute from punches: minutes before shift start
            // (if enabled) + minutes after shift end (if enabled), rounded via slabs.
            $manualOtHrs = $otMap[$e['id']][$dt] ?? null;
            if ($manualOtHrs !== null) {
                $otMins = (int)round($manualOtHrs * 60);   // manual OT: used as entered (not capped)
            } elseif ($otManualOnly) {
                $otMins = 0;
            } else {
                if ($otSlabs && ($otBefore || $otAfter)) {
                    $arrMin = otHm2min($shiftRec['ArrivalTime']   ?? null);
                    $depMin = otHm2min($shiftRec['DepartureTime'] ?? null);
                    $early  = ($otBefore && $arrMin !== null && $punch['count'] > 1) ? max(0, $arrMin - $inMins)  : 0;
                    $late   = ($otAfter  && $depMin !== null && $punch['count'] > 1) ? max(0, $outMins - $depMin) : 0;
                    $otMins = otApplySlab($early + $late, $otSlabs);
                } else {
                    // Legacy fallback: minutes worked beyond the shift's full-day hours.
                    $otMins = ($shiftHrsP > 0 && $totMins > 0) ? max(0, $totMins - (int)round($shiftHrsP * 60)) : 0;
                }
                if ($otMaxMin !== null) $otMins = min($otMins, $otMaxMin);   // cap auto OT
            }

            if ($shiftRec && $totMins > 0 && $totMins < $shiftHrsH * 60) {
                $code = 'HP'; $hpDays++; $dayTotals[$dt]['HP']++;
            } else {
                $code = 'P'; $presentDays++; $dayTotals[$dt]['P']++;
            }
            $cell['type']   = $code;
            $cell['in']     = $punch['in'];
            $cell['out']    = $punch['count'] > 1 ? $punch['out'] : null;
            $cell['tot']    = attendMinsToHm($totMins);
            $cell['ot']     = $showOt ? attendMinsToHm($otMins) : '';
            $cell['shift']  = $dayShiftNo ? 'S' . $dayShiftNo : '';
            $sorted = $punch['punches']; sort($sorted);
            $cell['punches'] = $sorted;
        } elseif (!$isFut) {
            $cell['type'] = 'A';
            $absentDays++; $dayTotals[$dt]['A']++;
        } else {
            $cell['type'] = 'FUT';
        }

        if ($corr) $cell['corr'] = true;
        $days[$dt] = $cell;
    }

    $grand['P']  += $presentDays;
    $grand['HP'] += $hpDays;
    $grand['A']  += $absentDays;
    $grand['L']  += $fullLv;
    $grand['HL'] += $halfLv;
    $grand['CO'] += $compOff;

    $employeeRows[] = [
        'id'         => (int)$e['id'],
        'code'       => $e['EmployeeCode'],
        'name'       => $e['Name'],
        'fatherName' => $e['FatherName'] ?? '',
        'designation'=> $e['Designation'] ?? '',
        'contractor' => $e['Contractor'] ?? '',
        'department' => $e['Department'] ?? '',
        'shiftNo'    => $e['ShiftNo'] ?? '',
        'days'       => $days,
        'summary'    => ['P'=>$presentDays,'HP'=>$hpDays,'A'=>$absentDays,'L'=>$fullLv,'HL'=>$halfLv,'CO'=>$compOff],
    ];
}

$workingDays = count(array_filter($dates, fn($d) => !isset($holidayDates[$d]) && date('N', strtotime($d)) != 7 && $d <= $today));
$totalEmps   = count($employees);
$maxPossible = $totalEmps * $workingDays;
$pctP        = $maxPossible > 0 ? round(($grand['P'] + $grand['HP'] * 0.5) / $maxPossible * 100) : 0;
$pctA        = $maxPossible > 0 ? round($grand['A'] / $maxPossible * 100) : 0;

// Leave types for this company (for the grid's quick-action leave dropdown)
$leaveTypesOut = [];
$shiftsOut     = [];
$leaveBalances = [];
try {
    $ltStmt = $db->prepare("SELECT Code, Name FROM tblLeaveType WHERE CompanyId=? AND IsActive=1 ORDER BY Code");
    $ltStmt->execute([$fCompany]);
    $leaveTypesOut = $ltStmt->fetchAll();

    // Shifts for this company (for the grid's quick-action shift dropdown)
    $shStmt = $db->prepare("SELECT id, ShiftName FROM tblShift WHERE CompanyId=? AND IsActive=1 ORDER BY ShiftName");
    $shStmt->execute([$fCompany]);
    $shiftsOut = $shStmt->fetchAll();

    // Per-employee remaining balance by leave code (for the dropdown ledger hint)
    $balYear = (int)substr($fFrom, 0, 4);
    $bStmt = $db->prepare(
        "SELECT b.EmployeeId, lt.Code, (b.Allocated + b.Adjusted - b.Used) AS Bal
         FROM tblLeaveBalance b JOIN tblLeaveType lt ON lt.id = b.LeaveTypeId
         WHERE b.CompanyId=? AND b.Year=?"
    );
    $bStmt->execute([$fCompany, $balYear]);
    foreach ($bStmt->fetchAll() as $r) {
        $leaveBalances[(int)$r['EmployeeId']][$r['Code']] = (float)$r['Bal'];
    }
} catch (Exception $e) {}

echo json_encode([
    'success'      => true,
    'notice'       => $notice,
    'errors'       => $fetchErrors,
    'companyName'  => $companyDisplayName,
    'fFrom'        => $fFrom,
    'fTo'          => $fTo,
    'fDept'        => $fDept,
    'fContractor'  => $fContractor,
    'dates'        => $datesData,
    'employees'    => $employeeRows,
    'dayTotals'    => $dayTotals,
    'grand'        => $grand,
    'leaveTypes'   => $leaveTypesOut,
    'shifts'       => $shiftsOut,
    'leaveBalances'=> $leaveBalances,
    'totalEmps'    => $totalEmps,
    'workingDays'  => $workingDays,
    'holidayCount' => count($holidayDates),
    'pctP'         => $pctP,
    'pctA'         => $pctA,
]);
