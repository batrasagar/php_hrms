<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
requirePermission('payroll_ctc_import.view');

$db   = getDb();
$user = currentUser();

try { $db->query("SELECT 1 FROM tblEmployeePayroll LIMIT 1"); }
catch (PDOException $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

// Company comes from the global topbar switcher
$companyIds = array_map('intval', array_column(companiesForUser($db, $user), 'id'));
$fCompany   = activeCompanyId($db, $user);

const WAGE_TYPES  = ['monthly','daily','hourly','piece_rate'];
$FIXED_HEADERS = ['employeecode','employeename','wagetype','wagerate','hoursperday','otallowed','pfapplicable','esiapplicable','tdsapplicable'];

function norm(string $s): string { return preg_replace('/[^a-z0-9]/', '', strtolower(trim($s))); }
function yesno($v): int { return in_array(strtolower(trim((string)$v)), ['1','yes','y','true','t'], true) ? 1 : 0; }

/** Active pay components for a company → [id, Name, Type, DefaultValue]. */
function ctpComponents(PDO $db, int $companyId): array {
    $s = $db->prepare("SELECT id, Name, Type, DefaultValue FROM tblPayrollComponent WHERE CompanyId=? AND IsActive=1 ORDER BY Type, SortOrder, id");
    $s->execute([$companyId]);
    return $s->fetchAll();
}

// ── Export current CTC chart as CSV ─────────────────────────────────────────────
if (isset($_GET['export']) && $fCompany) {
    $comps = ctpComponents($db, $fCompany);
    $s = $db->prepare(
        "SELECT e.id, e.EmployeeCode, e.Name,
                ep.WageType, ep.WageRate, ep.HoursPerDay, ep.OTAllowed, ep.PFApplicable, ep.ESIApplicable, ep.TDSApplicable
         FROM tblEmployee e
         LEFT JOIN tblEmployeePayroll ep ON ep.EmployeeId = e.id
         WHERE e.CompanyId = ? AND e.Status = 'active'
         ORDER BY e.Name"
    );
    $s->execute([$fCompany]);
    $emps = $s->fetchAll();

    // Preload component values for all employees
    $vals = [];
    if ($emps) {
        $cv = $db->prepare("SELECT EmployeeId, ComponentId, Value FROM tblEmployeePayComponent WHERE CompanyId=?");
        $cv->execute([$fCompany]);
        foreach ($cv->fetchAll() as $r) $vals[$r['EmployeeId']][$r['ComponentId']] = $r['Value'];
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="ctc_chart_company' . $fCompany . '.csv"');
    $out = fopen('php://output', 'w');
    $head = ['EmployeeCode','EmployeeName','WageType','WageRate','HoursPerDay','OTAllowed','PFApplicable','ESIApplicable','TDSApplicable'];
    foreach ($comps as $c) $head[] = $c['Name'];
    fputcsv($out, $head);
    foreach ($emps as $e) {
        $row = [
            $e['EmployeeCode'], $e['Name'],
            $e['WageType'] ?: 'monthly',
            $e['WageRate'] ?? 0,
            $e['HoursPerDay'] ?? 8,
            ($e['OTAllowed']     ?? 1) ? 'Yes' : 'No',
            ($e['PFApplicable']  ?? 1) ? 'Yes' : 'No',
            ($e['ESIApplicable'] ?? 1) ? 'Yes' : 'No',
            ($e['TDSApplicable'] ?? 0) ? 'Yes' : 'No',
        ];
        foreach ($comps as $c) $row[] = $vals[$e['id']][$c['id']] ?? '';
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

$error   = '';
$preview = [];
$step    = 'upload';
$summary = null;

/** Parse + validate a CTC CSV against a company's employees & components. */
function parseCtcCsv(string $path, array $codeMap, array $comps): array {
    $compByNorm = [];
    foreach ($comps as $c) $compByNorm[norm($c['Name'])] = $c;

    $rows = []; $valid = 0;
    $h = fopen($path, 'r');
    if (!$h) return [[], 0];
    $header = fgetcsv($h);
    if ($header === false) { fclose($h); return [[], 0]; }
    $hnorm = array_map('norm', $header);

    $rowNum = 1;
    while (($r = fgetcsv($h)) !== false && $rowNum < 20000) {
        $rowNum++;
        $cell = [];
        foreach ($hnorm as $i => $key) $cell[$key] = trim($r[$i] ?? '');
        if (($cell['employeecode'] ?? '') === '') continue;

        $code = strtoupper($cell['employeecode']);
        $errs = [];
        if (!isset($codeMap[$code])) $errs[] = 'Unknown code';

        $wageType = strtolower($cell['wagetype'] ?? '');
        if ($wageType === '') $wageType = 'monthly';
        if (!in_array($wageType, WAGE_TYPES, true)) $errs[] = 'Bad WageType';

        $wageRate = ($cell['wagerate'] ?? '') === '' ? 0.0 : (float)$cell['wagerate'];
        if ($wageRate < 0) $errs[] = 'Bad WageRate';
        $hpd = ($cell['hoursperday'] ?? '') === '' ? 8.0 : (float)$cell['hoursperday'];

        // Component values present in this row (blank = leave unchanged)
        $compVals = [];
        foreach ($cell as $key => $v) {
            if (in_array($key, ['employeecode','employeename','wagetype','wagerate','hoursperday','otallowed','pfapplicable','esiapplicable','tdsapplicable'], true)) continue;
            if ($v === '') continue;
            if (isset($compByNorm[$key])) {
                if (!is_numeric($v)) { $errs[] = 'Bad ' . $compByNorm[$key]['Name']; continue; }
                $compVals[(int)$compByNorm[$key]['id']] = (float)$v;
            }
        }

        $okRow = empty($errs);
        if ($okRow) $valid++;
        $rows[] = [
            '_row'     => $rowNum,
            'code'     => $code,
            'name'     => $codeMap[$code] ?? '—',
            'wageType' => $wageType,
            'wageRate' => $wageRate,
            'hpd'      => $hpd,
            'ot'       => yesno($cell['otallowed']     ?? 'yes'),
            'pf'       => yesno($cell['pfapplicable']  ?? 'yes'),
            'esi'      => yesno($cell['esiapplicable'] ?? 'yes'),
            'tds'      => yesno($cell['tdsapplicable'] ?? 'no'),
            'compVals' => $compVals,
            'ok'       => $okRow,
            'errs'     => $errs,
        ];
    }
    fclose($h);
    return [$rows, $valid];
}

/** EmployeeCode → [id, Name] for a company. */
function ctcEmpMap(PDO $db, int $companyId): array {
    $s = $db->prepare("SELECT id, EmployeeCode, Name FROM tblEmployee WHERE CompanyId=? AND EmployeeCode<>''");
    $s->execute([$companyId]);
    $m = [];
    foreach ($s->fetchAll() as $r) $m[strtoupper(trim($r['EmployeeCode']))] = ['id' => (int)$r['id'], 'name' => $r['Name']];
    return $m;
}

// ── Step 1: upload → preview ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'preview' && $fCompany) {
    requirePermission('payroll_ctc_import.edit');
    csrf_verify();
    if (empty($_FILES['csv_file']['tmp_name']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $error = 'Please choose a CSV file.';
    } else {
        $tmpPath = sys_get_temp_dir() . '/hrms_ctc_import_' . session_id() . '.csv';
        move_uploaded_file($_FILES['csv_file']['tmp_name'], $tmpPath);
        $codeMap = array_map(fn($v) => $v['name'], ctcEmpMap($db, $fCompany));
        [$preview] = parseCtcCsv($tmpPath, $codeMap, ctpComponents($db, $fCompany));
        if (!$preview) { $error = 'No data rows found. Export the chart first, edit it, then upload.'; @unlink($tmpPath); }
        else { $_SESSION['ctc_import_path'] = $tmpPath; $_SESSION['ctc_import_company'] = $fCompany; $step = 'preview'; }
    }
}

// ── Step 2: confirm → import ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    requirePermission('payroll_ctc_import.edit');
    csrf_verify();
    $companyId = (int)($_SESSION['ctc_import_company'] ?? 0);
    $tmpPath   = $_SESSION['ctc_import_path'] ?? '';
    if (!$companyId || !in_array($companyId, $companyIds, true) || !$tmpPath || !file_exists($tmpPath)) {
        $error = 'Import session expired. Please upload again.';
    } else {
        $empMap  = ctcEmpMap($db, $companyId);
        $codeMap = array_map(fn($v) => $v['name'], $empMap);
        [$rows]  = parseCtcCsv($tmpPath, $codeMap, ctpComponents($db, $companyId));

        $upPay = $db->prepare(
            "INSERT INTO tblEmployeePayroll (EmployeeId,CompanyId,WageType,WageRate,HoursPerDay,OTAllowed,PFApplicable,ESIApplicable,TDSApplicable)
             VALUES (?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE WageType=VALUES(WageType),WageRate=VALUES(WageRate),HoursPerDay=VALUES(HoursPerDay),
               OTAllowed=VALUES(OTAllowed),PFApplicable=VALUES(PFApplicable),ESIApplicable=VALUES(ESIApplicable),TDSApplicable=VALUES(TDSApplicable)"
        );
        $upComp = $db->prepare(
            "INSERT INTO tblEmployeePayComponent (EmployeeId,CompanyId,ComponentId,Value) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE Value=VALUES(Value)"
        );

        $done = 0; $skipped = 0; $compUpd = 0;
        $db->beginTransaction();
        foreach ($rows as $r) {
            if (!$r['ok'] || !isset($empMap[$r['code']])) { $skipped++; continue; }
            $eid = $empMap[$r['code']]['id'];
            $upPay->execute([$eid, $companyId, $r['wageType'], $r['wageRate'], $r['hpd'], $r['ot'], $r['pf'], $r['esi'], $r['tds']]);
            foreach ($r['compVals'] as $cid => $val) { $upComp->execute([$eid, $companyId, $cid, $val]); $compUpd++; }
            $done++;
        }
        $db->commit();
        @unlink($tmpPath);
        unset($_SESSION['ctc_import_path'], $_SESSION['ctc_import_company']);
        $summary = ['done' => $done, 'skipped' => $skipped, 'comp' => $compUpd];
        $step = 'done';
    }
}

$components = $fCompany ? ctpComponents($db, $fCompany) : [];
$pageTitle  = 'Import CTC Chart';
$activePage = 'payroll_ctc_import';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div>
    <h5 class="mb-0">Bulk CTC Import</h5>
    <div class="text-muted small">Export the current CTC chart, edit in Excel, then re-upload to bulk-update wages &amp; components.</div>
  </div>
</div>

<?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if (!$fCompany): ?>
<div class="alert alert-warning">Please select a company from the topbar switcher.</div>

<?php elseif ($step === 'done'): ?>
<div class="card border-0 shadow-sm" style="max-width:560px">
  <div class="card-body text-center py-4">
    <div style="font-size:2.4rem">✅</div>
    <h5 class="mt-2">CTC chart updated</h5>
    <p class="mb-3"><strong><?= (int)$summary['done'] ?></strong> employee(s) updated,
      <strong><?= (int)$summary['comp'] ?></strong> component value(s)<?php if ($summary['skipped']): ?>,
      <strong><?= (int)$summary['skipped'] ?></strong> row(s) skipped<?php endif; ?>.</p>
    <a href="import_ctc.php?company=<?= $fCompany ?>" class="btn btn-primary btn-sm">Import Again</a>
    <a href="employee_setup.php?company=<?= $fCompany ?>" class="btn btn-outline-secondary btn-sm">Payroll Setup</a>
  </div>
</div>

<?php elseif ($step === 'preview'):
    $validCount = count(array_filter($preview, fn($r) => $r['ok']));
    $badCount   = count($preview) - $validCount;
?>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="fw-semibold">Preview
      <span class="badge bg-success ms-1"><?= $validCount ?> valid</span>
      <?php if ($badCount): ?><span class="badge bg-danger"><?= $badCount ?> invalid</span><?php endif; ?>
    </span>
    <form method="POST" class="d-flex gap-2 mb-0">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="import">
      <input type="hidden" name="company" value="<?= $fCompany ?>">
      <a href="import_ctc.php?company=<?= $fCompany ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
      <button type="submit" class="btn btn-success btn-sm" <?= $validCount ? '' : 'disabled' ?>>
        <i class="bi bi-check2-circle"></i> Update <?= $validCount ?> employee(s)
      </button>
    </form>
  </div>
  <div class="card-body p-0" style="max-height:60vh;overflow:auto">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light sticky-top">
        <tr><th>#</th><th>Code</th><th>Name</th><th>Wage Type</th><th>Rate</th><th>OT</th><th>PF</th><th>ESI</th><th>TDS</th><th>Comp.</th><th>Result</th></tr>
      </thead>
      <tbody>
      <?php foreach ($preview as $r): ?>
        <tr class="<?= $r['ok'] ? '' : 'table-danger' ?>">
          <td class="text-muted small"><?= $r['_row'] ?></td>
          <td><?= htmlspecialchars($r['code']) ?></td>
          <td class="small"><?= htmlspecialchars($r['name']) ?></td>
          <td class="small"><?= htmlspecialchars($r['wageType']) ?></td>
          <td class="small">₹<?= number_format($r['wageRate'], 2) ?></td>
          <td><?= $r['ot']?'✓':'—' ?></td>
          <td><?= $r['pf']?'✓':'—' ?></td>
          <td><?= $r['esi']?'✓':'—' ?></td>
          <td><?= $r['tds']?'✓':'—' ?></td>
          <td class="small"><?= count($r['compVals']) ?: '—' ?></td>
          <td class="small"><?= $r['ok'] ? '<span class="text-success">OK</span>' : '<span class="text-danger">'.htmlspecialchars(implode(', ', $r['errs'])).'</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: // upload ?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span>Upload edited CTC chart</span>
        <a href="?company=<?= $fCompany ?>&export=1" class="btn btn-outline-primary btn-sm"><i class="bi bi-download"></i> Export current chart</a>
      </div>
      <div class="card-body">
        <ol class="small text-muted mb-3" style="padding-left:18px">
          <li>Click <strong>Export current chart</strong> to download this company's CTC as CSV.</li>
          <li>Open in Excel, edit wages / component amounts, keep the <code>EmployeeCode</code> column intact.</li>
          <li>Save as CSV and upload it below. Blank component cells are left unchanged.</li>
        </ol>
        <form method="POST" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="preview">
          <input type="hidden" name="company" value="<?= $fCompany ?>">
          <div class="mb-3">
            <label class="form-label">CSV File <span class="text-danger">*</span></label>
            <input type="file" name="csv_file" accept=".csv,text/csv" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary"><i class="bi bi-eye"></i> Preview Changes</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold small">Columns in this company's chart</div>
      <div class="card-body py-2 small">
        <div class="mb-2"><span class="text-muted">Fixed:</span>
          EmployeeCode, WageType, WageRate, HoursPerDay, OTAllowed, PFApplicable, ESIApplicable, TDSApplicable</div>
        <div><span class="text-muted">Components (earnings/deductions):</span><br>
          <?php if ($components): ?>
            <?php foreach ($components as $c): ?>
              <span class="badge <?= $c['Type']==='earning'?'bg-primary':'bg-danger' ?> me-1 mb-1"><?= htmlspecialchars($c['Name']) ?></span>
            <?php endforeach; ?>
          <?php else: ?>
            <span class="text-muted">No pay components defined yet. Add them under
              <a href="components.php?company=<?= $fCompany ?>">Income / Deduction Heads</a> (e.g. Basic, HRA, EPF, ESI).</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
