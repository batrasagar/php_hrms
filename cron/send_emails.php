<?php
/**
 * Cron endpoint for scheduled email notifications.
 *
 * All companies (recommended — one cron job):
 *   /cron/send_emails.php?secret=MASTER_SECRET
 *   /cron/send_emails.php?secret=MASTER_SECRET&force=1
 *
 * Single company (legacy / per-company secret):
 *   /cron/send_emails.php?company=1&secret=COMPANY_SECRET
 */
define('BASE_URL', '..');
define('IS_CRON', true);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../services/ShardManager.php';

header('Content-Type: text/plain; charset=UTF-8');

// ── Auth ──────────────────────────────────────────────────────────────────
$secret    = trim($_GET['secret']  ?? '');
$companyId = (int)($_GET['company'] ?? 0);

if (!$secret) { http_response_code(400); echo "Missing secret.\n"; exit; }

$db    = getDb();
$now   = new DateTime();
$force = !empty($_GET['force']);

if (!$companyId) {
    // ── Master mode: one cron for all companies ───────────────────────────
    if (!defined('CRON_MASTER_SECRET') || !hash_equals(CRON_MASTER_SECRET, $secret)) {
        http_response_code(403); echo "Invalid master secret.\n"; exit;
    }
    $companyIds = $db->query(
        "SELECT CompanyId FROM tblEmailSmtp
         WHERE SmtpHost != '' AND SmtpHost IS NOT NULL
         ORDER BY CompanyId"
    )->fetchAll(PDO::FETCH_COLUMN);
    echo "[" . $now->format('Y-m-d H:i:s') . "] Master mode — processing "
       . count($companyIds) . " company/companies" . ($force ? " (FORCED)" : "") . "\n";
} else {
    // ── Single-company mode: validate per-company secret ──────────────────
    $s = $db->prepare("SELECT CronSecret FROM tblEmailSmtp WHERE CompanyId=?");
    $s->execute([$companyId]);
    $row = $s->fetch();
    if (!$row || !hash_equals($row['CronSecret'], $secret)) {
        http_response_code(403); echo "Invalid secret.\n"; exit;
    }
    $companyIds = [$companyId];
    echo "[" . $now->format('Y-m-d H:i:s') . "] Single-company mode — company {$companyId}"
       . ($force ? " (FORCED)" : "") . "\n";
}

$sm   = new ShardManager($db);
$defs = require __DIR__ . '/../modules/notifications/definitions.php';

$allDefs = [];
foreach ($defs['groups'] as $group)
    foreach ($group['items'] as $key => $def) $allDefs[$key] = $def;
foreach ($defs['scheduled_reports'] as $key => $def) $allDefs[$key] = $def;

// ── Utility helpers ───────────────────────────────────────────────────────

function minsHm(int $m): string {
    if ($m <= 0) return '—';
    $h = (int)floor($m / 60); $mn = $m % 60;
    return $h . 'h' . ($mn ? ' ' . $mn . 'm' : '');
}

function fmtRs(float $v): string {
    return '₹' . number_format($v, 0);
}

function tryQuery(PDO $db, string $sql, array $params = []): array {
    try {
        $s = $db->prepare($sql);
        $s->execute($params);
        return $s->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function currYm(DateTime $now): string {
    return ShardManager::ym((int)$now->format('Y'), (int)$now->format('n'));
}

function prevYm(DateTime $now): string {
    $p = (clone $now)->modify('first day of last month');
    return ShardManager::ym((int)$p->format('Y'), (int)$p->format('n'));
}

// ── Email HTML builders ───────────────────────────────────────────────────

function emailWrap(string $body, string $title, string $subtitle = ''): string {
    $sub = $subtitle
        ? "<p style='color:#6e6e73;margin:4px 0 20px'>" . htmlspecialchars($subtitle, ENT_QUOTES) . "</p>"
        : '<br>';
    return "<!DOCTYPE html><html><head><meta charset='utf-8'></head><body>
        <div style='font-family:Arial,sans-serif;max-width:760px;margin:0 auto;padding:24px;color:#1d1d1f'>
        <h2 style='color:#0071e3;margin-bottom:4px'>" . htmlspecialchars($title, ENT_QUOTES) . "</h2>
        $sub $body
        <p style='color:#9e9e9e;font-size:11px;margin-top:28px;border-top:1px solid #e5e5e5;padding-top:12px'>
        Generated: " . date('d M Y H:i') . " — HR System</p>
        </div></body></html>";
}

function htmlTable(array $headers, array $rows, string $emptyMsg = 'No data available.'): string {
    if (empty($rows)) {
        return "<p style='color:#6e6e73;font-style:italic;padding:12px 0'>" . htmlspecialchars($emptyMsg) . "</p>";
    }
    $thCss = "background:#0071e3;color:#fff;padding:8px 12px;text-align:left;white-space:nowrap;font-size:12px;font-weight:600";
    $th = '<tr>' . implode('', array_map(
        fn($h) => "<th style='$thCss'>" . htmlspecialchars((string)$h, ENT_QUOTES) . "</th>",
        $headers
    )) . '</tr>';
    $tdCss = "padding:7px 12px;border-bottom:1px solid #e5e5e5;font-size:13px";
    $trs = '';
    foreach ($rows as $i => $r) {
        $bg   = $i % 2 ? '#f5f5f7' : '#ffffff';
        $bold = isset($r['__bold']) ? 'font-weight:700;background:#eef4ff' : "background:$bg";
        unset($r['__bold']);
        $trs .= "<tr style='$bold'>"
            . implode('', array_map(
                fn($v) => "<td style='$tdCss'>" . htmlspecialchars((string)$v, ENT_QUOTES) . "</td>",
                array_values($r)
            ))
            . '</tr>';
    }
    return "<table style='width:100%;border-collapse:collapse'>$th$trs</table>";
}

function statBadges(array $stats): string {
    $colors = [
        'Present' => '#34c759', 'Half Day' => '#007aff', 'Absent' => '#ff3b30',
        'Leave' => '#ff9500', 'Week Off' => '#8e8e93', 'Holiday' => '#5856d6',
    ];
    $out = "<div style='display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px'>";
    foreach ($stats as $label => $val) {
        $c = $colors[$label] ?? '#333';
        $out .= "<div style='background:$c;color:#fff;padding:12px 20px;border-radius:8px;text-align:center;min-width:80px'>
                    <div style='font-size:22px;font-weight:700'>$val</div>
                    <div style='font-size:11px;margin-top:4px'>$label</div>
                 </div>";
    }
    return $out . "</div>";
}

// ── Scheduled report HTML builders ────────────────────────────────────────

function buildReport(string $key, PDO $db, int $co, DateTime $now): string {
    $coName = $db->prepare("SELECT Name FROM tblCompany WHERE id=?");
    $coName->execute([$co]);
    $company = $coName->fetchColumn() ?: "Company #{$co}";

    $today      = $now->format('Y-m-d');
    $weekAgo    = (clone $now)->modify('-6 days')->format('Y-m-d');
    $currYm     = currYm($now);
    $prevYm     = prevYm($now);
    $prevLabel  = (clone $now)->modify('first day of last month')->format('F Y');
    $attnC      = "tblAttendance_{$currYm}";
    $attnP      = "tblAttendance_{$prevYm}";
    $mAttnP     = "tblMonthlyAttendance_{$prevYm}";
    $payP       = "tblPayRoll_{$prevYm}";

    switch ($key) {

        // ── Daily Attendance Summary ──────────────────────────────────────
        case 'report_attn_daily': {
            $tot = tryQuery($db,
                "SELECT SUM(AttStatus='P') P, SUM(AttStatus IN ('HD','HP')) HD,
                        SUM(AttStatus='A') A, SUM(AttStatus IN ('L','SL')) L,
                        SUM(AttStatus='WO') WO, SUM(AttStatus='PH') PH, COUNT(*) Total
                 FROM `$attnC` WHERE CompanyId=? AND tDate=?",
                [$co, $today]
            )[0] ?? [];

            $drows = tryQuery($db,
                "SELECT COALESCE(e.Department,'—') AS Department,
                        SUM(a.AttStatus='P') Present,
                        SUM(a.AttStatus IN ('HD','HP')) HalfDay,
                        SUM(a.AttStatus='A') Absent,
                        SUM(a.AttStatus IN ('L','SL')) `Leave`,
                        SUM(a.AttStatus='WO') WeekOff,
                        COUNT(*) Total
                 FROM `$attnC` a
                 JOIN tblEmployee e ON e.EmployeeCode=a.EmpCode AND e.CompanyId=a.CompanyId
                 WHERE a.CompanyId=? AND a.tDate=?
                 GROUP BY e.Department ORDER BY e.Department",
                [$co, $today]
            );

            $badges = $tot ? statBadges([
                'Present'  => $tot['P']  ?? 0,
                'Half Day' => $tot['HD'] ?? 0,
                'Absent'   => $tot['A']  ?? 0,
                'Leave'    => $tot['L']  ?? 0,
                'Week Off' => $tot['WO'] ?? 0,
                'Holiday'  => $tot['PH'] ?? 0,
            ]) : '';

            $deptTable = htmlTable(
                ['Department','Present','Half Day','Absent','Leave','Week Off','Total'],
                array_map(fn($r) => [$r['Department'],$r['Present'],$r['HalfDay'],$r['Absent'],$r['Leave'],$r['WeekOff'],$r['Total']], $drows),
                'No attendance data for today. Ensure punch sync and attendance processing have run.'
            );

            // Per-employee punch detail
            $statusLabel = ['P'=>'Present','A'=>'Absent','HD'=>'Half Day','HP'=>'Half Present',
                            'WO'=>'Week Off','PH'=>'Holiday','L'=>'Leave','SL'=>'Sick Leave',
                            'CO'=>'Comp Off','OD'=>'On Duty'];
            $statusColor = ['P'=>'#34c759','A'=>'#ff3b30','HD'=>'#007aff','HP'=>'#007aff',
                            'WO'=>'#8e8e93','PH'=>'#5856d6','L'=>'#ff9500','SL'=>'#ff6b00',
                            'CO'=>'#32ade6','OD'=>'#30b0c7'];

            $empRows = tryQuery($db,
                "SELECT e.Name, e.EmployeeCode, COALESCE(e.Department,'—') AS Department,
                        a.AttStatus, a.TimeIn, a.TimeOut, a.TotalMins, a.ShortTime, a.OT
                 FROM `$attnC` a
                 JOIN tblEmployee e ON e.EmployeeCode=a.EmpCode AND e.CompanyId=a.CompanyId
                 WHERE a.CompanyId=? AND a.tDate=?
                 ORDER BY e.Department, e.Name",
                [$co, $today]
            );

            $empTableRows = array_map(function($r) use ($statusLabel, $statusColor) {
                $sts   = $statusLabel[$r['AttStatus']] ?? $r['AttStatus'];
                $color = $statusColor[$r['AttStatus']] ?? '#333';
                $short = (int)$r['ShortTime'] > 0 ? minsHm((int)$r['ShortTime']) : '—';
                $ot    = (int)$r['OT']        > 0 ? minsHm((int)$r['OT'])        : '—';
                return [
                    $r['Name'],
                    $r['EmployeeCode'],
                    $r['Department'],
                    "[[STATUS:$color:$sts]]",   // placeholder for colored badge
                    $r['TimeIn']  ?: '—',
                    $r['TimeOut'] ?: '—',
                    minsHm((int)$r['TotalMins']),
                    $short,
                    $ot,
                ];
            }, $empRows);

            // Build punch detail table manually to support colored status cells
            $punchTable = '';
            if (empty($empRows)) {
                $punchTable = "<p style='color:#6e6e73;font-style:italic;padding:12px 0'>No employee punch data for today.</p>";
            } else {
                $thCss  = "background:#1d1d1f;color:#fff;padding:8px 10px;text-align:left;white-space:nowrap;font-size:12px;font-weight:600";
                $tdCss  = "padding:6px 10px;border-bottom:1px solid #e5e5e5;font-size:12px";
                $heads  = ['Employee','Code','Department','Status','In','Out','Hours','Short','OT'];
                $hRow   = '<tr>' . implode('', array_map(fn($h) => "<th style='$thCss'>$h</th>", $heads)) . '</tr>';
                $tRows  = '';
                foreach ($empRows as $i => $r) {
                    $bg    = $i % 2 ? '#f9f9f9' : '#fff';
                    $sts   = $statusLabel[$r['AttStatus']] ?? $r['AttStatus'];
                    $color = $statusColor[$r['AttStatus']] ?? '#333';
                    $short = (int)$r['ShortTime'] > 0 ? "<span style='color:#ff3b30'>".minsHm((int)$r['ShortTime'])."</span>" : '—';
                    $ot    = (int)$r['OT'] > 0 ? "<span style='color:#34c759'>".minsHm((int)$r['OT'])."</span>" : '—';
                    $badge = "<span style='background:$color;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600'>$sts</span>";
                    $tRows .= "<tr style='background:$bg'>
                        <td style='$tdCss'><strong>".htmlspecialchars($r['Name'])."</strong></td>
                        <td style='$tdCss;color:#6e6e73'>".htmlspecialchars($r['EmployeeCode'])."</td>
                        <td style='$tdCss;color:#6e6e73'>".htmlspecialchars($r['Department'])."</td>
                        <td style='$tdCss'>$badge</td>
                        <td style='$tdCss;font-family:monospace'>".htmlspecialchars($r['TimeIn'] ?: '—')."</td>
                        <td style='$tdCss;font-family:monospace'>".htmlspecialchars($r['TimeOut'] ?: '—')."</td>
                        <td style='$tdCss'>".minsHm((int)$r['TotalMins'])."</td>
                        <td style='$tdCss'>$short</td>
                        <td style='$tdCss'>$ot</td>
                    </tr>";
                }
                $punchTable = "<table style='width:100%;border-collapse:collapse'>$hRow$tRows</table>";
            }

            $deptHeader  = "<h3 style='color:#1d1d1f;font-size:14px;margin:20px 0 8px'>Department Summary</h3>";
            $punchHeader = "<h3 style='color:#1d1d1f;font-size:14px;margin:24px 0 8px'>Employee Punch Detail</h3>";

            return emailWrap(
                $badges . $deptHeader . $deptTable . $punchHeader . $punchTable,
                "Daily Attendance Summary — $today",
                $company
            );
        }

        // ── Weekly Late / Absent ──────────────────────────────────────────
        case 'report_late_weekly': {
            $qry = "SELECT e.Name, e.EmployeeCode, COALESCE(e.Department,'—') AS Department,
                           SUM(a.AttStatus='A') AbsentDays,
                           SUM(a.ShortTime > 0 AND a.AttStatus NOT IN ('A','WO','PH','L','SL')) LateDays,
                           SUM(a.ShortTime) TotalShortMins
                    FROM `%s` a
                    JOIN tblEmployee e ON e.EmployeeCode=a.EmpCode AND e.CompanyId=a.CompanyId
                    WHERE a.CompanyId=? AND a.tDate BETWEEN ? AND ?
                      AND (a.AttStatus='A' OR (a.ShortTime > 0 AND a.AttStatus NOT IN ('WO','PH','L','SL')))
                    GROUP BY a.EmpCode
                    ORDER BY e.Department, TotalShortMins DESC";

            $rows = tryQuery($db, sprintf($qry, $attnC), [$co, $weekAgo, $today]);

            // Span previous month shard if week crosses month boundary
            $monthStart = $now->format('Y-m-01');
            if ($weekAgo < $monthStart) {
                $extra = tryQuery($db, sprintf($qry, $attnP), [$co, $weekAgo, date('Y-m-t', strtotime($weekAgo))]);
                $merged = [];
                foreach (array_merge($extra, $rows) as $r) {
                    $ec = $r['EmployeeCode'];
                    if (!isset($merged[$ec])) { $merged[$ec] = $r; continue; }
                    $merged[$ec]['AbsentDays']     += $r['AbsentDays'];
                    $merged[$ec]['LateDays']       += $r['LateDays'];
                    $merged[$ec]['TotalShortMins'] += $r['TotalShortMins'];
                }
                $rows = array_values($merged);
                usort($rows, fn($a,$b) => $b['TotalShortMins'] <=> $a['TotalShortMins']);
            }

            $table = htmlTable(
                ['Employee','Code','Department','Absent Days','Late Days','Short Time'],
                array_map(fn($r) => [
                    $r['Name'], $r['EmployeeCode'], $r['Department'],
                    (int)$r['AbsentDays'], (int)$r['LateDays'], minsHm((int)$r['TotalShortMins'])
                ], $rows),
                'No late or absent records in the last 7 days.'
            );
            return emailWrap($table, "Weekly Late / Absent Analysis", "$company | $weekAgo to $today");
        }

        // ── Monthly Dept. Attendance Analysis ────────────────────────────
        case 'report_dept_mthly': {
            $drows = tryQuery($db,
                "SELECT COALESCE(e.Department,'—') AS Department,
                        COUNT(DISTINCT m.EmpCode) Employees,
                        SUM(m.TotP) Present, SUM(m.TotHD) HalfDay,
                        SUM(m.TotA) Absent, SUM(m.TotL) `Leave`,
                        SUM(m.TotWO) WeekOff, SUM(m.TotOT) OTMins,
                        ROUND(SUM(m.WorkDays),1) WorkDays
                 FROM `$mAttnP` m
                 JOIN tblEmployee e ON e.EmployeeCode=m.EmpCode AND e.CompanyId=m.CompanyId
                 WHERE m.CompanyId=?
                 GROUP BY e.Department ORDER BY e.Department",
                [$co]
            );

            $tableRows = array_map(fn($r) => [
                $r['Department'], (int)$r['Employees'],
                (int)$r['Present'], (int)$r['HalfDay'], (int)$r['Absent'],
                (int)$r['Leave'], (int)$r['WeekOff'],
                minsHm((int)$r['OTMins']), $r['WorkDays']
            ], $drows);

            if ($drows) {
                $t = array_reduce($drows, fn($c,$r) => [
                    'Employees'=>$c['Employees']+(int)$r['Employees'],
                    'Present'  =>$c['Present']  +(int)$r['Present'],
                    'HalfDay'  =>$c['HalfDay']  +(int)$r['HalfDay'],
                    'Absent'   =>$c['Absent']   +(int)$r['Absent'],
                    'Leave'    =>$c['Leave']    +(int)$r['Leave'],
                    'WeekOff'  =>$c['WeekOff']  +(int)$r['WeekOff'],
                    'OTMins'   =>$c['OTMins']   +(int)$r['OTMins'],
                    'WorkDays' =>round($c['WorkDays']+(float)$r['WorkDays'],1),
                ], ['Employees'=>0,'Present'=>0,'HalfDay'=>0,'Absent'=>0,'Leave'=>0,'WeekOff'=>0,'OTMins'=>0,'WorkDays'=>0]);
                $tableRows[] = [
                    '__bold' => true,
                    'TOTAL', $t['Employees'], $t['Present'], $t['HalfDay'],
                    $t['Absent'], $t['Leave'], $t['WeekOff'],
                    minsHm($t['OTMins']), $t['WorkDays']
                ];
            }

            $table = htmlTable(
                ['Department','Employees','Present','Half Day','Absent','Leave','Week Off','OT','Work Days'],
                $tableRows,
                "No monthly attendance data for $prevLabel. Run monthly attendance processing first."
            );
            return emailWrap($table, "Monthly Dept. Attendance Analysis — $prevLabel", $company);
        }

        // ── Monthly Payroll Register ──────────────────────────────────────
        case 'report_payroll_mthly': {
            $prows = tryQuery($db,
                "SELECT COALESCE(e.Department,'—') AS Department,
                        COUNT(p.EmpCode) Employees,
                        SUM(p.GrossSalary) Gross, SUM(p.PF) PF, SUM(p.ESI) ESI,
                        SUM(p.TotDeduct) Deductions, SUM(p.NetSalary) Net
                 FROM `$payP` p
                 JOIN tblEmployee e ON e.EmployeeCode=p.EmpCode AND e.CompanyId=p.CompanyId
                 WHERE p.CompanyId=?
                 GROUP BY e.Department ORDER BY e.Department",
                [$co]
            );

            $tableRows = array_map(fn($r) => [
                $r['Department'], (int)$r['Employees'],
                fmtRs($r['Gross']), fmtRs($r['PF']),
                fmtRs($r['ESI']), fmtRs($r['Deductions']), fmtRs($r['Net'])
            ], $prows);

            $summary = '';
            if ($prows) {
                $gt = array_reduce($prows, fn($c,$r) => [
                    'Employees'  => $c['Employees']  + (int)$r['Employees'],
                    'Gross'      => $c['Gross']      + $r['Gross'],
                    'PF'         => $c['PF']         + $r['PF'],
                    'ESI'        => $c['ESI']        + $r['ESI'],
                    'Deductions' => $c['Deductions'] + $r['Deductions'],
                    'Net'        => $c['Net']        + $r['Net'],
                ], ['Employees'=>0,'Gross'=>0,'PF'=>0,'ESI'=>0,'Deductions'=>0,'Net'=>0]);
                $tableRows[] = [
                    '__bold' => true,
                    'TOTAL', $gt['Employees'],
                    fmtRs($gt['Gross']), fmtRs($gt['PF']),
                    fmtRs($gt['ESI']), fmtRs($gt['Deductions']), fmtRs($gt['Net'])
                ];
                $summary = "<div style='background:#eef4ff;border-radius:8px;padding:16px;margin-bottom:16px;display:flex;gap:32px;flex-wrap:wrap'>
                    <div><span style='color:#6e6e73;font-size:12px'>Employees</span><br><strong style='font-size:18px'>{$gt['Employees']}</strong></div>
                    <div><span style='color:#6e6e73;font-size:12px'>Gross Total</span><br><strong style='font-size:18px'>" . fmtRs($gt['Gross']) . "</strong></div>
                    <div><span style='color:#6e6e73;font-size:12px'>Net Payable</span><br><strong style='font-size:18px;color:#0071e3'>" . fmtRs($gt['Net']) . "</strong></div>
                    <div><span style='color:#6e6e73;font-size:12px'>Total PF</span><br><strong style='font-size:18px'>" . fmtRs($gt['PF']) . "</strong></div>
                    <div><span style='color:#6e6e73;font-size:12px'>Total ESI</span><br><strong style='font-size:18px'>" . fmtRs($gt['ESI']) . "</strong></div>
                </div>";
            }

            $table = htmlTable(
                ['Department','Employees','Gross Salary','PF','ESI','Total Deductions','Net Salary'],
                $tableRows,
                "No payroll data for $prevLabel. Run payroll processing first."
            );
            return emailWrap($summary . $table, "Monthly Payroll Register — $prevLabel", $company);
        }

        // ── Monthly Overtime Report ───────────────────────────────────────
        case 'report_ot_mthly': {
            $otrows = tryQuery($db,
                "SELECT e.Name, e.EmployeeCode, COALESCE(e.Department,'—') AS Department,
                        m.TotP, m.TotA, m.TotOT, m.WorkDays
                 FROM `$mAttnP` m
                 JOIN tblEmployee e ON e.EmployeeCode=m.EmpCode AND e.CompanyId=m.CompanyId
                 WHERE m.CompanyId=? AND m.TotOT > 0
                 ORDER BY m.TotOT DESC",
                [$co]
            );

            $table = htmlTable(
                ['Employee','Code','Department','Present Days','Absent Days','OT Hours','Work Days'],
                array_map(fn($r) => [
                    $r['Name'], $r['EmployeeCode'], $r['Department'],
                    (int)$r['TotP'], (int)$r['TotA'],
                    minsHm((int)$r['TotOT']), $r['WorkDays']
                ], $otrows),
                "No overtime records for $prevLabel."
            );
            return emailWrap($table, "Monthly Overtime Report — $prevLabel", $company);
        }
    }

    return '';
}

// ── Notification HTML builders (sent to configured recipients) ────────────

function buildNotif(string $key, PDO $db, int $co, DateTime $now): string {
    $coName = $db->prepare("SELECT Name FROM tblCompany WHERE id=?");
    $coName->execute([$co]);
    $company = $coName->fetchColumn() ?: "Company #{$co}";

    $today  = $now->format('Y-m-d');
    $currYm = currYm($now);
    $attnC  = "tblAttendance_{$currYm}";

    switch ($key) {

        case 'mgr_absent_today':
        case 'hr_attendance_dashboard': {
            $tot = tryQuery($db,
                "SELECT SUM(AttStatus='P') P, SUM(AttStatus IN ('HD','HP')) HD,
                        SUM(AttStatus='A') A, SUM(AttStatus IN ('L','SL')) L,
                        SUM(AttStatus='WO') WO, COUNT(*) Total
                 FROM `$attnC` WHERE CompanyId=? AND tDate=?",
                [$co, $today]
            )[0] ?? [];

            $rows = tryQuery($db,
                "SELECT e.Name, e.EmployeeCode, COALESCE(e.Department,'—') AS Department
                 FROM `$attnC` a
                 JOIN tblEmployee e ON e.EmployeeCode=a.EmpCode AND e.CompanyId=a.CompanyId
                 WHERE a.CompanyId=? AND a.tDate=? AND a.AttStatus='A'
                 ORDER BY e.Department, e.Name",
                [$co, $today]
            );
            $badges = $tot ? statBadges([
                'Present'  => $tot['P']  ?? 0,
                'Half Day' => $tot['HD'] ?? 0,
                'Absent'   => $tot['A']  ?? 0,
                'Leave'    => $tot['L']  ?? 0,
                'Week Off' => $tot['WO'] ?? 0,
            ]) : '';
            $table = htmlTable(
                ['Employee','Code','Department'],
                array_map(fn($r) => [$r['Name'],$r['EmployeeCode'],$r['Department']], $rows),
                'No absent employees today.'
            );
            $title = $key === 'mgr_absent_today' ? 'Employees Absent Today' : 'Daily Attendance Dashboard';
            return emailWrap($badges . $table, "$title — $today", $company);
        }

        case 'mgr_late_report':
        case 'hr_anomalies': {
            $rows = tryQuery($db,
                "SELECT e.Name, e.EmployeeCode, COALESCE(e.Department,'—') AS Department,
                        a.TimeIn, a.ShortTime
                 FROM `$attnC` a
                 JOIN tblEmployee e ON e.EmployeeCode=a.EmpCode AND e.CompanyId=a.CompanyId
                 WHERE a.CompanyId=? AND a.tDate=? AND a.ShortTime>0
                   AND a.AttStatus NOT IN ('A','WO','PH','L','SL')
                 ORDER BY a.ShortTime DESC",
                [$co, $today]
            );
            $table = htmlTable(
                ['Employee','Code','Department','Time In','Short By'],
                array_map(fn($r) => [
                    $r['Name'], $r['EmployeeCode'], $r['Department'],
                    $r['TimeIn'] ?: '—', minsHm((int)$r['ShortTime'])
                ], $rows),
                'No late arrivals today.'
            );
            $title = $key === 'mgr_late_report' ? 'Late Arrivals Report' : 'Attendance Anomalies';
            return emailWrap(
                "<p>" . count($rows) . " employee(s) arrived late or have short time.</p>" . $table,
                "$title — $today", $company
            );
        }

        case 'mgr_dept_summary': {
            $rows = tryQuery($db,
                "SELECT COALESCE(e.Department,'—') AS Department,
                        SUM(a.AttStatus='P') Present,
                        SUM(a.AttStatus IN ('HD','HP')) HalfDay,
                        SUM(a.AttStatus='A') Absent,
                        SUM(a.AttStatus IN ('L','SL')) `Leave`,
                        SUM(a.AttStatus='WO') WeekOff,
                        COUNT(*) Total
                 FROM `$attnC` a
                 JOIN tblEmployee e ON e.EmployeeCode=a.EmpCode AND e.CompanyId=a.CompanyId
                 WHERE a.CompanyId=? AND a.tDate=?
                 GROUP BY e.Department ORDER BY e.Department",
                [$co, $today]
            );
            $table = htmlTable(
                ['Department','Present','Half Day','Absent','Leave','Week Off','Total'],
                array_map(fn($r) => [$r['Department'],$r['Present'],$r['HalfDay'],$r['Absent'],$r['Leave'],$r['WeekOff'],$r['Total']], $rows),
                'No attendance data for today.'
            );
            return emailWrap($table, "Department Attendance Summary — $today", $company);
        }

        case 'mgr_shortage': {
            $rows = tryQuery($db,
                "SELECT COALESCE(e.Department,'—') AS Department,
                        SUM(a.AttStatus='A') Absent,
                        (SELECT COUNT(*) FROM tblEmployee e2
                         WHERE e2.CompanyId=? AND e2.Department=e.Department AND e2.Status='active') Strength
                 FROM `$attnC` a
                 JOIN tblEmployee e ON e.EmployeeCode=a.EmpCode AND e.CompanyId=a.CompanyId
                 WHERE a.CompanyId=? AND a.tDate=? AND a.AttStatus='A'
                 GROUP BY e.Department HAVING Absent > 0
                 ORDER BY Absent DESC",
                [$co, $co, $today]
            );
            $table = htmlTable(
                ['Department','Strength','Absent','Absence %'],
                array_map(fn($r) => [
                    $r['Department'], (int)$r['Strength'], (int)$r['Absent'],
                    $r['Strength'] > 0 ? round($r['Absent'] / $r['Strength'] * 100, 1) . '%' : '—'
                ], $rows),
                'No workforce shortage today.'
            );
            return emailWrap($table, "Workforce Shortage Alert — $today", $company);
        }

        case 'mgr_ot_pending': {
            // Employees with OT today (OT > 0)
            $rows = tryQuery($db,
                "SELECT e.Name, e.EmployeeCode, COALESCE(e.Department,'—') AS Department,
                        a.TimeIn, a.TimeOut, a.OT
                 FROM `$attnC` a
                 JOIN tblEmployee e ON e.EmployeeCode=a.EmpCode AND e.CompanyId=a.CompanyId
                 WHERE a.CompanyId=? AND a.tDate=? AND a.OT>0
                 ORDER BY a.OT DESC",
                [$co, $today]
            );
            $table = htmlTable(
                ['Employee','Code','Department','Time In','Time Out','OT'],
                array_map(fn($r) => [
                    $r['Name'], $r['EmployeeCode'], $r['Department'],
                    $r['TimeIn'] ?: '—', $r['TimeOut'] ?: '—', minsHm((int)$r['OT'])
                ], $rows),
                'No overtime recorded today.'
            );
            return emailWrap($table, "Overtime Summary — $today", $company);
        }

        case 'hr_probation_reminder': {
            $rows = tryQuery($db,
                "SELECT Name, EmployeeCode, COALESCE(Department,'—') AS Department,
                        JoinDate, DATEDIFF(CURDATE(), JoinDate) AS DaysWorked
                 FROM tblEmployee
                 WHERE CompanyId=? AND Status='active'
                   AND DATEDIFF(CURDATE(), JoinDate) BETWEEN 80 AND 100
                 ORDER BY JoinDate",
                [$co]
            );
            $table = htmlTable(
                ['Employee','Code','Department','Join Date','Days Worked'],
                array_map(fn($r) => [$r['Name'],$r['EmployeeCode'],$r['Department'],$r['JoinDate'],$r['DaysWorked']], $rows),
                'No employees in the 80–100 day probation completion window.'
            );
            return emailWrap($table, "Probation Completion Reminders", $company);
        }

        case 'hr_contract_renewal': {
            $rows = tryQuery($db,
                "SELECT Name, EmployeeCode, COALESCE(Department,'—') AS Department,
                        JoinDate, DOL AS ContractEnd, DATEDIFF(DOL, CURDATE()) AS DaysLeft
                 FROM tblEmployee
                 WHERE CompanyId=? AND Status='active'
                   AND DOL IS NOT NULL AND DOL != ''
                   AND DATEDIFF(DOL, CURDATE()) BETWEEN 0 AND 30
                 ORDER BY DOL",
                [$co]
            );
            $table = htmlTable(
                ['Employee','Code','Department','Join Date','Contract End','Days Left'],
                array_map(fn($r) => [$r['Name'],$r['EmployeeCode'],$r['Department'],$r['JoinDate'],$r['ContractEnd'],(int)$r['DaysLeft']], $rows),
                'No contract renewals due in the next 30 days.'
            );
            return emailWrap($table, "Contract Renewal Reminders", $company);
        }
    }

    return '';
}

// ── isDue check ───────────────────────────────────────────────────────────

function isDue(array $row, array $def, DateTime $now): bool {
    $schedType = $def['schedule'] ?? ($def['frequency'] ?? 'event');
    if ($schedType === 'event') return false;
    [$h, $m] = explode(':', $row['SendTime'] ?? '08:00');
    $dueToday = (clone $now)->setTime((int)$h, (int)$m, 0);
    if ($now < $dueToday) return false;
    $lastRun = $row['LastRunAt'] ? new DateTime($row['LastRunAt']) : null;
    if ($schedType === 'daily') return !$lastRun || $lastRun < $dueToday;
    if ($schedType === 'weekly') {
        $dayMap = ['sunday'=>0,'monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6];
        $target = $dayMap[strtolower($row['SendDay'] ?? 'monday')] ?? 1;
        if ((int)$now->format('w') !== $target) return false;
        return !$lastRun || $lastRun < $dueToday;
    }
    if ($schedType === 'monthly') {
        $targetDay = max(1, min(28, (int)($row['SendDay'] ?: 1)));
        if ((int)$now->format('j') !== $targetDay) return false;
        return !$lastRun || $lastRun < $dueToday;
    }
    return false;
}

function markRun(PDO $db, int $co, string $key): void {
    $db->prepare("UPDATE tblEmailNotification SET LastRunAt=NOW() WHERE CompanyId=? AND NotifKey=?")
       ->execute([$co, $key]);
}

function logEmail(PDO $db, int $co, string $key, string $to, string $subj, array $res): void {
    $db->prepare(
        "INSERT INTO tblEmailLog (CompanyId,NotifKey,ToEmail,Subject,Status,ErrorMsg) VALUES (?,?,?,?,?,?)"
    )->execute([$co, $key, $to, $subj, $res['ok'] ? 'sent' : 'failed', $res['error'] ?: null]);
}

function sendOne(PDO $db, array $cfg, int $co, string $key, string $to, string $subject, string $html): void {
    $res = SimpleMailer::send($cfg, $to, $subject, $html);
    logEmail($db, $co, $key, $to, $subject, $res);
    echo "  → {$to}: " . ($res['ok'] ? 'OK' : 'FAILED — ' . $res['error']) . "\n";
}

// ── Main loop — iterate over all companies ────────────────────────────────

foreach ($companyIds as $companyId) {

    // Load SMTP config for this company
    $smtpStmt = $db->prepare("SELECT * FROM tblEmailSmtp WHERE CompanyId=?");
    $smtpStmt->execute([$companyId]);
    $smtpRow = $smtpStmt->fetch();
    if (!$smtpRow) {
        echo "\n[Company {$companyId}] No SMTP config — skipping.\n";
        continue;
    }

    $cfg = [
        'host'       => $smtpRow['SmtpHost']   ?? '',
        'port'       => $smtpRow['SmtpPort']   ?? 587,
        'encryption' => $smtpRow['Encryption'] ?? 'tls',
        'user'       => $smtpRow['SmtpUser']   ?? '',
        'pass'       => $smtpRow['SmtpPass']   ?? '',
        'from_email' => $smtpRow['FromEmail']  ?? '',
        'from_name'  => $smtpRow['FromName']   ?? '',
    ];

    $s = $db->prepare(
        "SELECT NotifKey, SendTime, SendDay, Recipients, LastRunAt
         FROM tblEmailNotification
         WHERE CompanyId=? AND IsEnabled=1"
    );
    $s->execute([$companyId]);
    $enabledNotifs = $s->fetchAll();

    echo "\n[" . $now->format('H:i:s') . "] Company {$companyId} — "
       . count($enabledNotifs) . " enabled notification(s)\n";

foreach ($enabledNotifs as $row) {
    $key = $row['NotifKey'];
    $def = $allDefs[$key] ?? null;
    if (!$def) continue;

    if (!$force && !isDue($row, $def, $now)) continue;

    echo "[" . $now->format('H:i:s') . "] Processing: {$key}\n";

    // ── Birthday Greetings ────────────────────────────────────────────────
    if ($key === 'emp_birthday') {
        $emps = $db->prepare(
            "SELECT Name, Email, DOB FROM tblEmployee
             WHERE CompanyId=? AND Status='active' AND Email!='' AND Email IS NOT NULL
               AND MONTH(DOB)=MONTH(CURDATE()) AND DAY(DOB)=DAY(CURDATE())"
        );
        $emps->execute([$companyId]);
        foreach ($emps->fetchAll() as $emp) {
            $html = emailWrap(
                "<p>Wishing you a wonderful birthday filled with joy and happiness! 🎂</p>
                 <p>Thank you for your continued contributions to our team. Have a great day!</p>",
                "Happy Birthday, " . htmlspecialchars($emp['Name']) . "!",
                "From the entire HR team"
            );
            sendOne($db, $cfg, $companyId, $key, $emp['Email'], 'Happy Birthday! 🎂', $html);
        }
        markRun($db, $companyId, $key);
        continue;
    }

    // ── Work Anniversary ──────────────────────────────────────────────────
    if ($key === 'emp_anniversary') {
        $emps = $db->prepare(
            "SELECT Name, Email, JoinDate FROM tblEmployee
             WHERE CompanyId=? AND Status='active' AND Email!='' AND Email IS NOT NULL
               AND MONTH(JoinDate)=MONTH(CURDATE()) AND DAY(JoinDate)=DAY(CURDATE())
               AND YEAR(JoinDate) < YEAR(CURDATE())"
        );
        $emps->execute([$companyId]);
        foreach ($emps->fetchAll() as $emp) {
            $years = (int)$now->format('Y') - (int)date('Y', strtotime($emp['JoinDate']));
            $html  = emailWrap(
                "<p>Congratulations on completing <strong>$years year" . ($years > 1 ? 's' : '') . "</strong> with the company! 🎉</p>
                 <p>Your dedication and hard work have been invaluable. Thank you for being a part of our team!</p>",
                "Happy Work Anniversary!",
                htmlspecialchars($emp['Name']) . " — $years Year" . ($years > 1 ? 's' : '')
            );
            sendOne($db, $cfg, $companyId, $key, $emp['Email'],
                    "Happy {$years}-Year Work Anniversary! 🎉", $html);
        }
        markRun($db, $companyId, $key);
        continue;
    }

    // ── Employee daily attendance self-notification ───────────────────────
    if ($key === 'emp_attendance_daily') {
        $currYm = currYm($now);
        $attnC  = "tblAttendance_{$currYm}";
        $emps   = tryQuery($db,
            "SELECT e.Name, e.Email, a.AttStatus, a.TimeIn, a.TimeOut, a.TotalMins, a.ShortTime
             FROM `$attnC` a
             JOIN tblEmployee e ON e.EmployeeCode=a.EmpCode AND e.CompanyId=a.CompanyId
             WHERE a.CompanyId=? AND a.tDate=?
               AND e.Email!='' AND e.Email IS NOT NULL",
            [$companyId, $now->format('Y-m-d')]
        );
        $statusLabel = ['P'=>'Present','A'=>'Absent','HD'=>'Half Day','HP'=>'Half Present',
                        'WO'=>'Week Off','PH'=>'Holiday','L'=>'Leave','SL'=>'Sick Leave',
                        'CO'=>'Comp Off','OD'=>'On Duty'];
        foreach ($emps as $emp) {
            $sts   = $statusLabel[$emp['AttStatus']] ?? $emp['AttStatus'];
            $color = in_array($emp['AttStatus'], ['A']) ? '#ff3b30' : '#34c759';
            $html  = emailWrap(
                "<div style='background:#f5f5f7;border-radius:8px;padding:16px;margin-bottom:16px'>
                    <table style='width:100%;font-size:14px'>
                        <tr><td style='color:#6e6e73;padding:4px 0'>Status</td><td style='font-weight:700;color:$color'>$sts</td></tr>
                        <tr><td style='color:#6e6e73;padding:4px 0'>Time In</td><td>" . ($emp['TimeIn'] ?: '—') . "</td></tr>
                        <tr><td style='color:#6e6e73;padding:4px 0'>Time Out</td><td>" . ($emp['TimeOut'] ?: '—') . "</td></tr>
                        <tr><td style='color:#6e6e73;padding:4px 0'>Total Hrs</td><td>" . minsHm((int)$emp['TotalMins']) . "</td></tr>
                        " . ($emp['ShortTime'] > 0 ? "<tr><td style='color:#ff9500;padding:4px 0'>Short By</td><td style='color:#ff9500'>" . minsHm((int)$emp['ShortTime']) . "</td></tr>" : '') . "
                    </table>
                 </div>",
                "Your Attendance — " . $now->format('d M Y'),
                htmlspecialchars($emp['Name'])
            );
            sendOne($db, $cfg, $companyId, $key, $emp['Email'],
                    "Attendance Summary — " . $now->format('d M Y'), $html);
        }
        markRun($db, $companyId, $key);
        continue;
    }

    // ── Employee missing punch alert ──────────────────────────────────────
    if ($key === 'emp_missing_punch') {
        $currYm = currYm($now);
        $attnC  = "tblAttendance_{$currYm}";
        $emps   = tryQuery($db,
            "SELECT e.Name, e.Email, a.TimeIn
             FROM `$attnC` a
             JOIN tblEmployee e ON e.EmployeeCode=a.EmpCode AND e.CompanyId=a.CompanyId
             WHERE a.CompanyId=? AND a.tDate=?
               AND a.TimeIn IS NOT NULL AND a.TimeOut IS NULL
               AND a.AttStatus NOT IN ('WO','PH','L','SL')
               AND e.Email!='' AND e.Email IS NOT NULL",
            [$companyId, $now->format('Y-m-d')]
        );
        foreach ($emps as $emp) {
            $html = emailWrap(
                "<p>Your attendance record for today shows only an <strong>IN</strong> punch at <strong>{$emp['TimeIn']}</strong>.</p>
                 <p>Please ensure you punch OUT before leaving, or contact HR to submit a correction.</p>",
                "Missing Punch Alert — " . $now->format('d M Y'),
                htmlspecialchars($emp['Name'])
            );
            sendOne($db, $cfg, $companyId, $key, $emp['Email'],
                    "Missing Punch Alert — " . $now->format('d M Y'), $html);
        }
        markRun($db, $companyId, $key);
        continue;
    }

    // ── Scheduled reports (report_*) ──────────────────────────────────────
    if (str_starts_with($key, 'report_')) {
        $recipients = array_filter(array_map('trim', explode(',', $row['Recipients'] ?? '')));
        if (empty($recipients)) {
            echo "  ↳ Skipped (no recipients configured)\n";
            markRun($db, $companyId, $key);
            continue;
        }
        $html    = buildReport($key, $db, $companyId, $now);
        $label   = $def['label'] ?? $key;
        $subject = $label . ' — ' . $now->format('d M Y');
        foreach ($recipients as $to) {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) continue;
            sendOne($db, $cfg, $companyId, $key, $to, $subject, $html);
        }
        markRun($db, $companyId, $key);
        continue;
    }

    // ── Manager / HR / Payroll notifications with recipients ─────────────
    $html = buildNotif($key, $db, $companyId, $now);
    $recipients = array_filter(array_map('trim', explode(',', $row['Recipients'] ?? '')));

    if ($html && !empty($recipients)) {
        $label   = $def['label'] ?? $key;
        $subject = $label . ' — ' . $now->format('d M Y');
        foreach ($recipients as $to) {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) continue;
            sendOne($db, $cfg, $companyId, $key, $to, $subject, $html);
        }
        markRun($db, $companyId, $key);
        continue;
    }

    if (empty($recipients)) {
        echo "  ↳ Skipped (no recipients configured)\n";
    } else {
        echo "  ↳ Skipped (notification type '$key' not yet implemented)\n";
    }
    markRun($db, $companyId, $key);
}
// end foreach $enabledNotifs

} // end foreach $companyIds

echo "\n[" . (new DateTime())->format('H:i:s') . "] Done.\n";
