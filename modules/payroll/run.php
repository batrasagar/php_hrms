<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

/* ── helpers ──────────────────────────────────────────────────────────── */
function getPayrollSettings(PDO $db, int $companyId): array {
    $def = ['WorkingDaysPerMonth'=>26,'PFEmployeeRate'=>12,'PFEmployerRate'=>12,
            'PFWageCeiling'=>15000,'ESIEmployeeRate'=>0.75,'ESIEmployerRate'=>3.25,
            'ESIWageCeiling'=>21000,'OTMultiplier'=>1.5];
    $s = $db->prepare("SELECT * FROM tblPayrollSettings WHERE CompanyId=? LIMIT 1");
    $s->execute([$companyId]);
    $row = $s->fetch();
    return $row ? array_merge($def, $row) : $def;
}

function calcSalaryLine(array $ep, array $settings, array $components, array $empVals,
                        float $P, float $HP, float $OT, int $pieces, float $tds): array {
    $wt     = $ep['WageType']    ?? 'monthly';
    $rate   = (float)($ep['WageRate']    ?? 0);
    $hpd    = (float)($ep['HoursPerDay'] ?? 8);
    $otMult = ($ep['OTMultiplier'] ?? null) ? (float)$ep['OTMultiplier'] : (float)$settings['OTMultiplier'];
    $wdays  = max(1, (float)$settings['WorkingDaysPerMonth']);
    $paid   = $P + $HP * 0.5;

    // Earned basic
    $eb = 0; $otAmt = 0;
    switch ($wt) {
        case 'monthly':
            $eb    = ($rate / $wdays) * $paid;
            $otAmt = ($ep['OTAllowed'] && $OT > 0)
                   ? ($rate / ($wdays * max(1,$hpd))) * $otMult * $OT : 0;
            break;
        case 'daily':
            $eb    = $rate * $paid;
            $otAmt = ($ep['OTAllowed'] && $OT > 0)
                   ? ($rate / max(1,$hpd)) * $otMult * $OT : 0;
            break;
        case 'hourly':
            $eb    = $rate * ($P * $hpd + $HP * $hpd * 0.5);
            $otAmt = ($ep['OTAllowed'] && $OT > 0) ? $rate * $otMult * $OT : 0;
            break;
        case 'piece_rate':
            $eb    = $rate * $pieces;
            break;
    }
    $eb = round($eb, 2); $otAmt = round($otAmt, 2);

    // Earnings components
    $earns = []; $sumEarns = 0;
    foreach ($components as $c) {
        if ($c['Type'] !== 'earning' || !$c['IsActive']) continue;
        $val = (float)(isset($empVals[$c['id']]) ? $empVals[$c['id']] : $c['DefaultValue']);
        $amt = match($c['CalcType']) {
            'percent_basic' => $eb * $val / 100,
            'percent_gross' => 0, // handled below
            default         => $wt === 'monthly' ? ($val / $wdays) * $paid : $val,
        };
        $earns[] = ['id'=>$c['id'],'name'=>$c['Name'],'calc'=>$c['CalcType'],'pct'=>$val,'amount'=>round($amt,2)];
        $sumEarns += $amt;
    }
    // percent_gross pass
    $preGross = $eb + $otAmt + $sumEarns;
    foreach ($earns as &$e) {
        if ($e['calc'] === 'percent_gross') {
            $a = $preGross * $e['pct'] / 100;
            $sumEarns += $a; $e['amount'] = round($a, 2);
        }
    }
    $totalEarnings = round($eb + $otAmt + $sumEarns, 2);

    // PF
    $pfE = $pfR = 0;
    if ($ep['PFApplicable'] ?? 1) {
        $pfWage = min($eb, (float)$settings['PFWageCeiling']);
        $pfE    = round($pfWage * $settings['PFEmployeeRate'] / 100, 2);
        $pfR    = round($pfWage * $settings['PFEmployerRate'] / 100, 2);
    }

    // ESI
    $esiE = $esiR = 0;
    if (($ep['ESIApplicable'] ?? 1) && $totalEarnings <= $settings['ESIWageCeiling']) {
        $esiE = round($totalEarnings * $settings['ESIEmployeeRate'] / 100, 2);
        $esiR = round($totalEarnings * $settings['ESIEmployerRate'] / 100, 2);
    }

    // Deduction components
    $deds = []; $sumDeds = 0;
    foreach ($components as $c) {
        if ($c['Type'] !== 'deduction' || !$c['IsActive']) continue;
        $val = (float)(isset($empVals[$c['id']]) ? $empVals[$c['id']] : $c['DefaultValue']);
        $amt = match($c['CalcType']) {
            'percent_basic' => $eb * $val / 100,
            'percent_gross' => $totalEarnings * $val / 100,
            default         => $val,
        };
        $deds[]  = ['id'=>$c['id'],'name'=>$c['Name'],'amount'=>round($amt,2)];
        $sumDeds += $amt;
    }

    $totalDeds = round($pfE + $esiE + $tds + $sumDeds, 2);
    $net       = round($totalEarnings - $totalDeds, 2);

    return [
        'earnedBasic'      => $eb,
        'otAmount'         => $otAmt,
        'earningsJson'     => json_encode($earns),
        'totalEarnings'    => $totalEarnings,
        'pfEmployee'       => $pfE, 'pfEmployer' => $pfR,
        'esiEmployee'      => $esiE,'esiEmployer' => $esiR,
        'tdsAmount'        => round($tds,2),
        'deductionsJson'   => json_encode($deds),
        'totalDeductions'  => $totalDeds,
        'netSalary'        => $net,
    ];
}

function getAttendanceSummary(PDO $db, int $companyId, int $year, int $mon): array {
    $shard = sprintf('tblPunchLog_%02d%02d', $year % 100, $mon);
    $chk   = $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$shard'")->fetchColumn();
    if (!$chk) return [];

    // Shift map
    $shiftMap = [];
    $s = $db->prepare("SELECT e.EmployeeCode, s.HrsP, s.HrsHlf FROM tblEmployee e
        LEFT JOIN tblShift s ON s.id=e.ShiftNo WHERE e.CompanyId=?");
    $s->execute([$companyId]);
    foreach ($s->fetchAll() as $r) {
        $shiftMap[$r['EmployeeCode']] = [(float)($r['HrsP']??8),(float)($r['HrsHlf']??4)];
    }

    $monthStart = sprintf('%04d-%02d-01 00:00:00', $year, $mon);
    $days       = (int)date('t', mktime(0,0,0,$mon,1,$year));
    $monthEnd   = sprintf('%04d-%02d-%02d 23:59:59', $year, $mon, $days);

    $sql = "SELECT EmpCode, DATE(PunchTime) as PD,
                   TIMESTAMPDIFF(MINUTE, MIN(PunchTime), MAX(PunchTime)) as Mins
            FROM `$shard`
            WHERE CompanyId=? AND PunchTime BETWEEN ? AND ?
            GROUP BY EmpCode, DATE(PunchTime)";
    $s = $db->prepare($sql);
    $s->execute([$companyId, $monthStart, $monthEnd]);

    $result = [];
    foreach ($s->fetchAll() as $r) {
        $emp = $r['EmpCode'];
        [$hrsP, $hrsHlf] = $shiftMap[$emp] ?? [8.0, 4.0];
        $hrs = $r['Mins'] / 60.0;
        if (!isset($result[$emp])) $result[$emp] = ['P'=>0.0,'HP'=>0.0];
        if ($hrs >= $hrsP)   $result[$emp]['P']++;
        elseif ($hrs >= $hrsHlf) $result[$emp]['HP']++;
        else $result[$emp]['HP'] += 0.5;
    }
    return $result;
}

function getOTSummary(PDO $db, int $companyId, int $year, int $mon): array {
    $monthStart = sprintf('%04d-%02d-01', $year, $mon);
    $monthEnd   = sprintf('%04d-%02d-%02d', $year, $mon, (int)date('t', mktime(0,0,0,$mon,1,$year)));
    $s = $db->prepare("SELECT EmployeeId, SUM(OTHours) as TotalOT FROM tblOvertime
        WHERE CompanyId=? AND OTDate BETWEEN ? AND ? GROUP BY EmployeeId");
    $s->execute([$companyId, $monthStart, $monthEnd]);
    $result = [];
    foreach ($s->fetchAll() as $r) $result[$r['EmployeeId']] = (float)$r['TotalOT'];
    return $result;
}

// Guard: redirect to migrate if payroll tables don't exist yet
try { $db->query("SELECT 1 FROM tblPayrollRun LIMIT 1"); }
catch (PDOException $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

/* ── company scoping ──────────────────────────────────────────────────── */
if ($user['role'] === 'superadmin') {
    $companies = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $s = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $s->execute([$user['scope_id']]);
    $companies = $s->fetchAll();
}

$fCompany = (int)($_REQUEST['company'] ?? ($companies[0]['id'] ?? 0));
if ($fCompany && in_array($user['role'], ['admin','operator'], true)) {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$fCompany, $user['scope_id']]);
    if (!$chk->fetch()) $fCompany = 0;
}
$fMonth = trim($_REQUEST['month'] ?? date('Y-m'));

/* ── action handling ─────────────────────────────────────────────────── */
$msg = ''; $msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fCompany) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // ── GENERATE ─────────────────────────────────────────────────────
    if ($action === 'generate') {
        try {
            // Check run doesn't already exist
            $chk = $db->prepare("SELECT id FROM tblPayrollRun WHERE CompanyId=? AND RunMonth=?");
            $chk->execute([$fCompany, $fMonth]);
            if ($chk->fetch()) throw new Exception("Payroll for this month already exists.");

            [$yr, $mn] = array_map('intval', explode('-', $fMonth));
            $settings   = getPayrollSettings($db, $fCompany);
            $wdays      = (int)$settings['WorkingDaysPerMonth'];

            // Create run
            $db->prepare("INSERT INTO tblPayrollRun (CompanyId,RunMonth,Status,CreatedBy,CreatedAt) VALUES (?,?,'draft',?,NOW())")
               ->execute([$fCompany, $fMonth, $user['id']]);
            $runId = (int)$db->lastInsertId();

            // Get components
            $s = $db->prepare("SELECT * FROM tblPayrollComponent WHERE CompanyId=? AND IsActive=1 ORDER BY Type,SortOrder,id");
            $s->execute([$fCompany]);
            $components = $s->fetchAll();

            // Get employees with payroll config
            $s = $db->prepare(
                "SELECT e.id, e.EmployeeCode, e.Name, e.GrossSalary, e.BasicSalary,
                        ep.WageType, ep.WageRate, ep.HoursPerDay, ep.OTAllowed, ep.OTMultiplier,
                        ep.PFApplicable, ep.ESIApplicable, ep.TDSApplicable
                 FROM tblEmployee e
                 LEFT JOIN tblEmployeePayroll ep ON ep.EmployeeId=e.id
                 WHERE e.CompanyId=? AND e.Status='active'"
            );
            $s->execute([$fCompany]);
            $employees = $s->fetchAll();

            // Get attendance + OT
            $attnMap = getAttendanceSummary($db, $fCompany, $yr, $mn);
            $otMap   = getOTSummary($db, $fCompany, $yr, $mn);

            $ins = $db->prepare(
                "INSERT INTO tblPayrollDetail
                 (RunId,EmployeeId,CompanyId,PresentDays,HalfDays,AbsentDays,OTHours,Pieces,
                  WageType,WageRate,EarnedBasic,EarningsJson,OTAmount,TotalEarnings,
                  PFEmployee,PFEmployer,ESIEmployee,ESIEmployer,TDSAmount,
                  DeductionsJson,TotalDeductions,NetSalary)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
            );

            foreach ($employees as $emp) {
                // Merge payroll defaults
                $ep = [
                    'WageType'      => $emp['WageType']     ?? 'monthly',
                    'WageRate'      => (float)($emp['WageRate'] ?: $emp['GrossSalary'] ?: $emp['BasicSalary'] ?: 0),
                    'HoursPerDay'   => (float)($emp['HoursPerDay'] ?? 8),
                    'OTAllowed'     => $emp['OTAllowed']     ?? 1,
                    'OTMultiplier'  => $emp['OTMultiplier'],
                    'PFApplicable'  => $emp['PFApplicable']  ?? 1,
                    'ESIApplicable' => $emp['ESIApplicable'] ?? 1,
                    'TDSApplicable' => $emp['TDSApplicable'] ?? 0,
                ];

                $attn   = $attnMap[$emp['EmployeeCode']] ?? ['P'=>0,'HP'=>0];
                $P      = (float)$attn['P'];
                $HP     = (float)$attn['HP'];
                $OT     = (float)($otMap[$emp['id']] ?? 0);
                $absent = max(0, $wdays - $P - $HP * 0.5);

                // Employee component overrides
                $s = $db->prepare("SELECT ComponentId, Value FROM tblEmployeePayComponent WHERE EmployeeId=?");
                $s->execute([$emp['id']]);
                $empVals = [];
                foreach ($s->fetchAll() as $r) $empVals[$r['ComponentId']] = $r['Value'];

                $calc = calcSalaryLine($ep, $settings, $components, $empVals, $P, $HP, $OT, 0, 0);

                $ins->execute([
                    $runId, $emp['id'], $fCompany, $P, $HP, $absent, $OT, 0,
                    $ep['WageType'], $ep['WageRate'],
                    $calc['earnedBasic'], $calc['earningsJson'], $calc['otAmount'],
                    $calc['totalEarnings'], $calc['pfEmployee'], $calc['pfEmployer'],
                    $calc['esiEmployee'], $calc['esiEmployer'], $calc['tdsAmount'],
                    $calc['deductionsJson'], $calc['totalDeductions'], $calc['netSalary'],
                ]);
            }
            $msg = 'Payroll generated for ' . count($employees) . ' employee(s).';
        } catch (Exception $e) {
            $msgType = 'danger'; $msg = $e->getMessage();
        }
    }

    // ── SAVE DRAFT ────────────────────────────────────────────────────
    elseif ($action === 'save') {
        $runId     = (int)$_POST['run_id'];
        $run       = $db->prepare("SELECT * FROM tblPayrollRun WHERE id=? AND CompanyId=? AND Status='draft'");
        $run->execute([$runId, $fCompany]);
        if ($run->fetch()) {
            $settings   = getPayrollSettings($db, $fCompany);
            $s = $db->prepare("SELECT * FROM tblPayrollComponent WHERE CompanyId=? AND IsActive=1 ORDER BY Type,SortOrder,id");
            $s->execute([$fCompany]);
            $components = $s->fetchAll();

            $s = $db->prepare("SELECT d.*, ep.WageType AS epWageType, ep.WageRate AS epWageRate,
                    ep.HoursPerDay, ep.OTAllowed, ep.OTMultiplier, ep.PFApplicable, ep.ESIApplicable, ep.TDSApplicable
                 FROM tblPayrollDetail d
                 LEFT JOIN tblEmployeePayroll ep ON ep.EmployeeId=d.EmployeeId
                 WHERE d.RunId=?");
            $s->execute([$runId]);
            $details = $s->fetchAll();

            $upd = $db->prepare(
                "UPDATE tblPayrollDetail SET PresentDays=?,HalfDays=?,AbsentDays=?,OTHours=?,Pieces=?,
                 EarnedBasic=?,EarningsJson=?,OTAmount=?,TotalEarnings=?,
                 PFEmployee=?,PFEmployer=?,ESIEmployee=?,ESIEmployer=?,TDSAmount=?,
                 DeductionsJson=?,TotalDeductions=?,NetSalary=?,Remarks=?
                 WHERE id=? AND RunId=?"
            );

            foreach ($details as $det) {
                $i   = $det['id'];
                $P   = max(0, (float)($_POST["P_$i"]   ?? $det['PresentDays']));
                $HP  = max(0, (float)($_POST["HP_$i"]  ?? $det['HalfDays']));
                $OT  = max(0, (float)($_POST["OT_$i"]  ?? $det['OTHours']));
                $pcs = max(0, (int)($_POST["PC_$i"]    ?? $det['Pieces']));
                $tds = max(0, (float)($_POST["TDS_$i"] ?? $det['TDSAmount']));
                $rem = substr(trim($_POST["REM_$i"] ?? $det['Remarks']), 0, 255);
                $absent = max(0, $settings['WorkingDaysPerMonth'] - $P - $HP * 0.5);

                $ep = [
                    'WageType'      => $det['epWageType']    ?? $det['WageType'],
                    'WageRate'      => (float)($det['epWageRate'] ?? $det['WageRate']),
                    'HoursPerDay'   => (float)($det['HoursPerDay'] ?? 8),
                    'OTAllowed'     => $det['OTAllowed'] ?? 1,
                    'OTMultiplier'  => $det['OTMultiplier'],
                    'PFApplicable'  => $det['PFApplicable']  ?? 1,
                    'ESIApplicable' => $det['ESIApplicable'] ?? 1,
                    'TDSApplicable' => $det['TDSApplicable'] ?? 0,
                ];

                $s2 = $db->prepare("SELECT ComponentId,Value FROM tblEmployeePayComponent WHERE EmployeeId=?");
                $s2->execute([$det['EmployeeId']]);
                $ev = []; foreach ($s2->fetchAll() as $r) $ev[$r['ComponentId']] = $r['Value'];

                $calc = calcSalaryLine($ep, $settings, $components, $ev, $P, $HP, $OT, $pcs, $tds);

                $upd->execute([
                    $P, $HP, $absent, $OT, $pcs,
                    $calc['earnedBasic'], $calc['earningsJson'], $calc['otAmount'],
                    $calc['totalEarnings'], $calc['pfEmployee'], $calc['pfEmployer'],
                    $calc['esiEmployee'], $calc['esiEmployer'], $calc['tdsAmount'],
                    $calc['deductionsJson'], $calc['totalDeductions'], $calc['netSalary'],
                    $rem, $i, $runId,
                ]);
            }
            $msg = 'Draft saved.';
        }
    }

    // ── FINALIZE ──────────────────────────────────────────────────────
    elseif ($action === 'finalize') {
        $runId = (int)$_POST['run_id'];
        $db->prepare("UPDATE tblPayrollRun SET Status='finalized', FinalizedAt=NOW() WHERE id=? AND CompanyId=? AND Status='draft'")
           ->execute([$runId, $fCompany]);
        $msg = 'Payroll finalized.';
    }

    // ── DELETE / REOPEN ───────────────────────────────────────────────
    elseif ($action === 'delete') {
        $runId = (int)$_POST['run_id'];
        if ($user['role'] === 'superadmin') {
            $db->prepare("DELETE FROM tblPayrollDetail WHERE RunId=?")->execute([$runId]);
            $db->prepare("DELETE FROM tblPayrollRun WHERE id=? AND CompanyId=?")->execute([$runId, $fCompany]);
            $msg = 'Payroll run deleted.';
        }
    }

    if ($isAjax) {
        if ($msgType === 'danger') {
            header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>[$msg]]); exit;
        }
        $redir = "run.php?company=$fCompany&month=" . urlencode($fMonth);
        header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$msg,'redirect'=>$redir]); exit;
    }
    header("Location: run.php?company=$fCompany&month=" . urlencode($fMonth));
    exit;
}

/* ── load current run ─────────────────────────────────────────────────── */
$run     = null;
$details = [];
$components = [];

if ($fCompany) {
    $s = $db->prepare("SELECT * FROM tblPayrollRun WHERE CompanyId=? AND RunMonth=? LIMIT 1");
    $s->execute([$fCompany, $fMonth]);
    $run = $s->fetch();

    if ($run) {
        $s = $db->prepare(
            "SELECT d.*, e.EmployeeCode, e.Name, e.Department, e.BankName, e.BankAcNo, e.IFSCCode
             FROM tblPayrollDetail d
             JOIN tblEmployee e ON e.id=d.EmployeeId
             WHERE d.RunId=?
             ORDER BY e.Name"
        );
        $s->execute([$run['id']]);
        $details = $s->fetchAll();

        $s = $db->prepare("SELECT * FROM tblPayrollComponent WHERE CompanyId=? AND IsActive=1 ORDER BY Type,SortOrder,id");
        $s->execute([$fCompany]);
        $components = $s->fetchAll();
    }
}

$settings = $fCompany ? getPayrollSettings($db, $fCompany) : [];

/* ── page ─────────────────────────────────────────────────────────────── */
$pageTitle  = 'Payroll Run';
$activePage = 'payroll_run';
require_once __DIR__ . '/../../includes/header.php';
?>
<?php if ($msg): ?>
<div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<!-- Filter bar -->
<form method="GET" class="row g-2 mb-4 align-items-end">
  <div class="col-auto">
    <label class="form-label">Company</label>
    <select name="company" class="form-select form-select-sm" style="min-width:180px">
      <?php foreach ($companies as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $c['id']==$fCompany?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-auto">
    <label class="form-label">Month</label>
    <input type="month" name="month" class="form-control form-control-sm" value="<?= htmlspecialchars($fMonth) ?>">
  </div>
  <div class="col-auto"><button class="btn btn-outline-primary btn-sm">Go</button></div>
</form>

<?php if (!$fCompany): ?>
<div class="alert alert-warning">Select a company to continue.</div>
<?php elseif (!$run): ?>
<!-- No run yet -->
<div class="card" style="max-width:500px">
  <div class="card-body text-center py-5">
    <i class="bi bi-cash-stack" style="font-size:40px;color:var(--blue);opacity:.5"></i>
    <div class="mt-3 fw-semibold fs-6">No payroll run for <?= htmlspecialchars($fMonth) ?></div>
    <div class="text-muted small mb-4">Attendance data will be pulled from punch logs automatically.</div>
    <form method="POST" data-ajax>
      <input type="hidden" name="action" value="generate">
      <input type="hidden" name="company" value="<?= $fCompany ?>">
      <input type="hidden" name="month" value="<?= htmlspecialchars($fMonth) ?>">
      <button class="btn btn-primary"><i class="bi bi-play-fill me-1"></i>Generate Payroll</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- Run exists -->
<?php
  $isDraft    = $run['Status'] === 'draft';
  $earningCols = array_filter($components, fn($c)=>$c['Type']==='earning');
  $dedCols     = array_filter($components, fn($c)=>$c['Type']==='deduction');
?>

<div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
  <span class="badge bg-<?= $isDraft ? 'warning text-dark' : 'success' ?> fs-6 px-3 py-2">
    <?= $isDraft ? 'Draft' : 'Finalized' ?>
  </span>
  <span class="text-muted small">
    <?= count($details) ?> employee<?= count($details)!=1?'s':'' ?> &middot;
    Generated <?= date('d M Y', strtotime($run['CreatedAt'])) ?>
    <?php if (!$isDraft): ?>
      &middot; Finalized <?= date('d M Y', strtotime($run['FinalizedAt'])) ?>
    <?php endif; ?>
  </span>
  <div class="ms-auto d-flex gap-2 flex-wrap">
    <a href="bank_advice.php?run=<?= $run['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
      <i class="bi bi-bank me-1"></i>Bank Advice
    </a>
    <?php if ($user['role'] === 'superadmin' && $isDraft): ?>
    <form method="POST" class="d-inline" data-ajax onsubmit="return confirm('Delete this payroll run?')">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="run_id" value="<?= $run['id'] ?>">
      <input type="hidden" name="company" value="<?= $fCompany ?>">
      <input type="hidden" name="month" value="<?= htmlspecialchars($fMonth) ?>">
      <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
    </form>
    <?php endif; ?>
  </div>
</div>

<form method="POST" id="payrollForm" data-ajax>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="run_id" value="<?= $run['id'] ?>">
  <input type="hidden" name="company" value="<?= $fCompany ?>">
  <input type="hidden" name="month" value="<?= htmlspecialchars($fMonth) ?>">

<div class="card mb-3">
  <div class="card-body p-0" style="overflow-x:auto">
    <table class="table table-sm align-middle mb-0" style="min-width:900px;font-size:12.5px">
      <thead class="table-light" style="position:sticky;top:0">
        <tr>
          <th rowspan="2" style="min-width:140px">Employee</th>
          <th colspan="4" class="text-center border-start">Attendance</th>
          <th colspan="<?= max(1,count($earningCols)) + 2 ?>" class="text-center border-start" style="background:#edfaf1">Earnings</th>
          <th colspan="<?= 3 + max(0,count($dedCols)) ?>" class="text-center border-start" style="background:#fff0ee">Deductions</th>
          <th rowspan="2" class="border-start text-end fw-bold">Net Pay</th>
          <?php if ($isDraft): ?><th rowspan="2" style="min-width:80px">Remarks</th><?php endif; ?>
          <th rowspan="2">Slip</th>
        </tr>
        <tr>
          <th class="border-start">P</th><th>H</th><th>Ab</th><th>OT</th>
          <th class="border-start" style="background:#edfaf1">Basic</th>
          <th style="background:#edfaf1">OT Amt</th>
          <?php foreach ($earningCols as $c): ?>
            <th style="background:#edfaf1;max-width:80px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= htmlspecialchars($c['Name']) ?>">
              <?= htmlspecialchars(mb_substr($c['Name'],0,8)) ?>
            </th>
          <?php endforeach; ?>
          <th class="border-start" style="background:#edfaf1">Total</th>
          <th class="border-start" style="background:#fff0ee">PF</th>
          <th style="background:#fff0ee">ESI</th>
          <?php foreach ($dedCols as $c): ?>
            <th style="background:#fff0ee"><?= htmlspecialchars(mb_substr($c['Name'],0,6)) ?></th>
          <?php endforeach; ?>
          <th style="background:#fff0ee">TDS</th>
        </tr>
      </thead>
      <tbody>
      <?php
        $totP=$totHP=$totAb=$totOT=$totBasic=$totOTAmt=$totEarn=$totPF=$totESI=$totTDS=$totDed=$totNet=0;
        foreach ($details as $d):
          $earnsArr = json_decode($d['EarningsJson'] ?: '[]', true);
          $dedsArr  = json_decode($d['DeductionsJson'] ?: '[]', true);
          $earnsById = array_column($earnsArr, 'amount', 'id');
          $dedsById  = array_column($dedsArr,  'amount', 'id');
          $totP+=$d['PresentDays']; $totHP+=$d['HalfDays']; $totAb+=$d['AbsentDays'];
          $totOT+=$d['OTHours']; $totBasic+=$d['EarnedBasic']; $totOTAmt+=$d['OTAmount'];
          $totEarn+=$d['TotalEarnings']; $totPF+=$d['PFEmployee']; $totESI+=$d['ESIEmployee'];
          $totTDS+=$d['TDSAmount']; $totDed+=$d['TotalDeductions']; $totNet+=$d['NetSalary'];
      ?>
        <tr>
          <td>
            <div class="fw-semibold" style="font-size:12px"><?= htmlspecialchars($d['Name']) ?></div>
            <div class="text-muted" style="font-size:10px"><?= htmlspecialchars($d['EmployeeCode']) ?></div>
          </td>
          <?php if ($isDraft): ?>
          <td class="border-start"><input type="number" name="P_<?= $d['id'] ?>" class="form-control form-control-sm" style="width:52px;padding:3px 5px;font-size:12px" value="<?= $d['PresentDays'] ?>" step="0.5" min="0"></td>
          <td><input type="number" name="HP_<?= $d['id'] ?>" class="form-control form-control-sm" style="width:52px;padding:3px 5px;font-size:12px" value="<?= $d['HalfDays'] ?>" step="0.5" min="0"></td>
          <td class="text-muted" style="font-size:11px"><?= $d['AbsentDays'] ?></td>
          <td><input type="number" name="OT_<?= $d['id'] ?>" class="form-control form-control-sm" style="width:52px;padding:3px 5px;font-size:12px" value="<?= $d['OTHours'] ?>" step="0.5" min="0"></td>
          <?php else: ?>
          <td class="border-start text-center"><?= $d['PresentDays'] ?></td>
          <td class="text-center"><?= $d['HalfDays'] ?></td>
          <td class="text-center"><?= $d['AbsentDays'] ?></td>
          <td class="text-center"><?= $d['OTHours'] ?></td>
          <?php endif; ?>

          <td class="border-start text-end" style="background:#f5fff8">₹<?= number_format($d['EarnedBasic'],2) ?></td>
          <td class="text-end" style="background:#f5fff8"><?= $d['OTAmount'] > 0 ? '₹'.number_format($d['OTAmount'],2) : '—' ?></td>
          <?php foreach ($earningCols as $c): ?>
            <td class="text-end" style="background:#f5fff8"><?= isset($earnsById[$c['id']]) && $earnsById[$c['id']] > 0 ? number_format($earnsById[$c['id']],2) : '—' ?></td>
          <?php endforeach; ?>
          <td class="text-end fw-semibold" style="background:#edfaf1">₹<?= number_format($d['TotalEarnings'],2) ?></td>

          <td class="border-start text-end" style="background:#fff8f8"><?= $d['PFEmployee'] > 0 ? '₹'.number_format($d['PFEmployee'],2) : '—' ?></td>
          <td class="text-end" style="background:#fff8f8"><?= $d['ESIEmployee'] > 0 ? '₹'.number_format($d['ESIEmployee'],2) : '—' ?></td>
          <?php foreach ($dedCols as $c): ?>
            <td class="text-end" style="background:#fff8f8"><?= isset($dedsById[$c['id']]) && $dedsById[$c['id']] > 0 ? number_format($dedsById[$c['id']],2) : '—' ?></td>
          <?php endforeach; ?>
          <?php if ($isDraft): ?>
          <td style="background:#fff8f8"><input type="number" name="TDS_<?= $d['id'] ?>" class="form-control form-control-sm" style="width:70px;padding:3px 5px;font-size:12px" value="<?= $d['TDSAmount'] ?>" step="0.01" min="0"></td>
          <?php else: ?>
          <td class="text-end" style="background:#fff8f8"><?= $d['TDSAmount'] > 0 ? '₹'.number_format($d['TDSAmount'],2) : '—' ?></td>
          <?php endif; ?>

          <td class="border-start text-end fw-bold" style="font-size:13px">₹<?= number_format($d['NetSalary'],2) ?></td>
          <?php if ($isDraft): ?>
          <td><input type="text" name="REM_<?= $d['id'] ?>" class="form-control form-control-sm" style="width:100px;font-size:11px" value="<?= htmlspecialchars($d['Remarks']) ?>" placeholder="Notes"></td>
          <?php endif; ?>
          <td>
            <a href="payslip.php?run=<?= $run['id'] ?>&emp=<?= $d['EmployeeId'] ?>" target="_blank"
               class="btn btn-sm btn-outline-secondary" title="Pay Slip">
              <i class="bi bi-receipt"></i>
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot class="table-light fw-bold">
        <tr>
          <td>Total</td>
          <td class="border-start text-center"><?= $totP ?></td>
          <td class="text-center"><?= $totHP ?></td>
          <td class="text-center"><?= $totAb ?></td>
          <td class="text-center"><?= $totOT ?></td>
          <td class="border-start text-end" style="background:#edfaf1">₹<?= number_format($totBasic,2) ?></td>
          <td class="text-end" style="background:#edfaf1">₹<?= number_format($totOTAmt,2) ?></td>
          <?php foreach ($earningCols as $c): ?><td style="background:#edfaf1"></td><?php endforeach; ?>
          <td class="text-end" style="background:#edfaf1">₹<?= number_format($totEarn,2) ?></td>
          <td class="border-start text-end" style="background:#fff0ee">₹<?= number_format($totPF,2) ?></td>
          <td class="text-end" style="background:#fff0ee">₹<?= number_format($totESI,2) ?></td>
          <?php foreach ($dedCols as $c): ?><td style="background:#fff0ee"></td><?php endforeach; ?>
          <td class="text-end" style="background:#fff0ee">₹<?= number_format($totTDS,2) ?></td>
          <td class="border-start text-end">₹<?= number_format($totNet,2) ?></td>
          <?php if ($isDraft): ?><td></td><?php endif; ?>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php if ($isDraft): ?>
<div class="d-flex gap-2 flex-wrap">
  <button type="submit" class="btn btn-outline-primary"><i class="bi bi-floppy me-1"></i>Save Draft</button>
  <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#finalizeModal">
    <i class="bi bi-check-circle me-1"></i>Finalize Payroll
  </button>
</div>
<?php endif; ?>

</form>

<!-- Finalize confirm modal -->
<div class="modal fade" id="finalizeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Finalize Payroll</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p>This will <strong>lock</strong> the salary sheet for <strong><?= htmlspecialchars($fMonth) ?></strong>. You will not be able to edit it further.</p>
        <p class="mb-0 fw-semibold">Net Payroll: ₹<?= number_format($totNet, 2) ?> for <?= count($details) ?> employees</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <form method="POST" class="d-inline" data-ajax>
          <input type="hidden" name="action" value="finalize">
          <input type="hidden" name="run_id" value="<?= $run['id'] ?>">
          <input type="hidden" name="company" value="<?= $fCompany ?>">
          <input type="hidden" name="month" value="<?= htmlspecialchars($fMonth) ?>">
          <button type="submit" class="btn btn-success">Yes, Finalize</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
