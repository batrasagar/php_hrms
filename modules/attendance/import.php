<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
requirePermission('attn_import.view');

$db   = getDb();
$user = currentUser();

// Valid forced-status codes (mirrors ajax/attendance_action.php manual_time)
const ATT_STATUSES = ['P','A','HD','WO','WOP','PH','L','SL','CO','OD'];
$statusHelp = [
    'P'=>'Present','A'=>'Absent','HD'=>'Half Day','WO'=>'Week Off','WOP'=>'Week Off Present',
    'PH'=>'Paid Holiday','L'=>'Leave','SL'=>'Sick Leave','CO'=>'Comp Off','OD'=>'On Duty',
];

/** Parse a date cell in several common formats → Y-m-d, or '' if invalid. */
function parseImportDate(string $s): string {
    $s = trim($s);
    if ($s === '') return '';
    $fmts = ['Y-m-d','d-m-Y','j-n-Y','d/m/Y','j/n/Y','d-M-Y','j-M-Y','d-M-y','d M Y','j M Y','m/d/Y','Y/m/d'];
    foreach ($fmts as $f) {
        $dt = DateTime::createFromFormat('!' . $f, $s);
        if ($dt && $dt->format($f) === $s) return $dt->format('Y-m-d');
    }
    $ts = strtotime($s);
    return $ts ? date('Y-m-d', $ts) : '';
}

/** Normalise a time cell → HH:MM:SS or '' if empty/invalid. */
function parseImportTime(string $s): string {
    $s = trim($s);
    if ($s === '') return '';
    $ts = strtotime($s);
    return $ts ? date('H:i:s', $ts) : '';
}

// ── CSV template download ──────────────────────────────────────────────────────
if (isset($_GET['template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="manual_attendance_template.csv"');
    echo "EmployeeCode,Date,Status,InTime,OutTime\r\n";
    echo "EMP-001,2026-07-13,P,09:00,18:00\r\n";
    echo "EMP-002,2026-07-13,A,,\r\n";
    echo "EMP-003,2026-07-13,HD,09:00,13:30\r\n";
    exit;
}

// Companies in scope
if ($user['role'] === 'superadmin') {
    $companies = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['scope_id']]);
    $companies = $stmt->fetchAll();
}
$companyIds = array_column($companies, 'id');

$error   = '';
$preview = [];
$step    = 'upload';   // upload | preview | done
$summary = null;

/** Load the set of valid employee codes for a company → [code => Name]. */
function empCodeMap(PDO $db, int $companyId): array {
    $s = $db->prepare("SELECT EmployeeCode, Name FROM tblEmployee WHERE CompanyId=? AND EmployeeCode<>''");
    $s->execute([$companyId]);
    $map = [];
    foreach ($s->fetchAll() as $r) $map[strtoupper(trim($r['EmployeeCode']))] = $r['Name'];
    return $map;
}

/** Read + validate rows from a CSV path against a company. Returns [rows, validCount]. */
function parseAttendanceCsv(string $path, array $codeMap): array {
    $rows  = [];
    $valid = 0;
    $h = fopen($path, 'r');
    if (!$h) return [[], 0];
    $header = fgetcsv($h);
    if ($header === false) { fclose($h); return [[], 0]; }
    $header = array_map(fn($c) => strtolower(trim($c)), $header);
    $idx = [
        'code'   => array_search('employeecode', $header, true),
        'date'   => array_search('date', $header, true),
        'status' => array_search('status', $header, true),
        'in'     => array_search('intime', $header, true),
        'out'    => array_search('outtime', $header, true),
    ];
    $rowNum = 1;
    while (($r = fgetcsv($h)) !== false && $rowNum < 20000) {
        $rowNum++;
        $get = fn($k) => $idx[$k] !== false && isset($r[$idx[$k]]) ? trim($r[$idx[$k]]) : '';
        $codeRaw = $get('code');
        if ($codeRaw === '' && $get('date') === '' && $get('status') === '') continue; // blank line
        $code   = strtoupper($codeRaw);
        $date   = parseImportDate($get('date'));
        $status = strtoupper($get('status'));
        $in     = parseImportTime($get('in'));
        $out    = parseImportTime($get('out'));

        $errs = [];
        if ($codeRaw === '')                          $errs[] = 'Missing code';
        elseif (!isset($codeMap[$code]))              $errs[] = 'Unknown employee code';
        if ($date === '')                             $errs[] = 'Bad/missing date';
        if ($status !== '' && !in_array($status, ATT_STATUSES, true)) $errs[] = 'Bad status';
        if ($status === '' && $in === '' && $out === '') $errs[] = 'No status or time';

        $okRow = empty($errs);
        if ($okRow) $valid++;
        $rows[] = [
            '_row'   => $rowNum,
            'code'   => $code,
            'name'   => $codeMap[$code] ?? '—',
            'date'   => $date ?: $get('date'),
            'status' => $status,
            'in'     => $in ? substr($in, 0, 5) : '',
            'out'    => $out ? substr($out, 0, 5) : '',
            'ok'     => $okRow,
            'errs'   => $errs,
        ];
    }
    fclose($h);
    return [$rows, $valid];
}

// ── Step 1: upload → preview ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'preview') {
    requirePermission('attn_import.edit');
    csrf_verify();
    $companyId = (int)($_POST['company_id'] ?? 0);
    if (!$companyId || !in_array($companyId, $companyIds, true)) {
        $error = 'Please select a valid company.';
    } elseif (empty($_FILES['csv_file']['tmp_name']) || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $error = 'Please choose a CSV file.';
    } else {
        $tmpPath = sys_get_temp_dir() . '/hrms_att_import_' . session_id() . '.csv';
        move_uploaded_file($_FILES['csv_file']['tmp_name'], $tmpPath);
        [$preview, $validCount] = parseAttendanceCsv($tmpPath, empCodeMap($db, $companyId));
        if (!$preview) {
            $error = 'No data rows found. Check the file has a header row and at least one record.';
            @unlink($tmpPath);
        } else {
            $_SESSION['att_import_path']    = $tmpPath;
            $_SESSION['att_import_company'] = $companyId;
            $step = 'preview';
        }
    }
}

// ── Step 2: confirm → import ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import') {
    requirePermission('attn_import.edit');
    csrf_verify();
    $companyId = (int)($_SESSION['att_import_company'] ?? 0);
    $tmpPath   = $_SESSION['att_import_path'] ?? '';
    if (!$companyId || !in_array($companyId, $companyIds, true) || !$tmpPath || !file_exists($tmpPath)) {
        $error = 'Import session expired. Please upload the file again.';
    } else {
        [$rows] = parseAttendanceCsv($tmpPath, empCodeMap($db, $companyId));
        $ins = $db->prepare(
            "REPLACE INTO tblPunchLogCorrection
                (CompanyId, EmpCode, tDate, InTime, OutTime, AttStatus, Reason, CorrectedBy, CorrectedAt)
             VALUES (?,?,?,?,?,?,?,?, NOW())"
        );
        $done = 0; $skipped = 0;
        $db->beginTransaction();
        foreach ($rows as $r) {
            if (!$r['ok']) { $skipped++; continue; }
            $ins->execute([
                $companyId, $r['code'], $r['date'],
                $r['in']  ? $r['in']  . ':00' : null,
                $r['out'] ? $r['out'] . ':00' : null,
                $r['status'] ?: null,
                'Manual attendance import',
                $user['id'],
            ]);
            $done++;
        }
        $db->commit();
        @unlink($tmpPath);
        unset($_SESSION['att_import_path'], $_SESSION['att_import_company']);
        $summary = ['done' => $done, 'skipped' => $skipped];
        $step = 'done';
    }
}

$pageTitle  = 'Import Attendance';
$activePage = 'attn_import';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div>
    <h5 class="mb-0">Manual Attendance Import</h5>
    <div class="text-muted small">Upload a CSV to mark attendance when the biometric device is unavailable.</div>
  </div>
  <a href="?template=1" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download"></i> CSV Template</a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($step === 'done'): ?>
<div class="card border-0 shadow-sm" style="max-width:560px">
  <div class="card-body text-center py-4">
    <div style="font-size:2.4rem">✅</div>
    <h5 class="mt-2">Attendance imported</h5>
    <p class="mb-3"><strong><?= (int)$summary['done'] ?></strong> record(s) saved<?php if ($summary['skipped']): ?>,
      <strong><?= (int)$summary['skipped'] ?></strong> skipped (invalid rows)<?php endif; ?>.</p>
    <a href="import.php" class="btn btn-primary btn-sm">Import Another</a>
    <a href="<?= BASE_URL ?>/modules/reports/attendance.php" class="btn btn-outline-secondary btn-sm">View Attendance Report</a>
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
      <a href="import.php" class="btn btn-outline-secondary btn-sm">Cancel</a>
      <button type="submit" class="btn btn-success btn-sm" <?= $validCount ? '' : 'disabled' ?>>
        <i class="bi bi-check2-circle"></i> Import <?= $validCount ?> record(s)
      </button>
    </form>
  </div>
  <div class="card-body p-0" style="max-height:60vh;overflow:auto">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light sticky-top">
        <tr><th>#</th><th>Code</th><th>Name</th><th>Date</th><th>Status</th><th>In</th><th>Out</th><th>Result</th></tr>
      </thead>
      <tbody>
      <?php foreach ($preview as $r): ?>
        <tr class="<?= $r['ok'] ? '' : 'table-danger' ?>">
          <td class="text-muted small"><?= $r['_row'] ?></td>
          <td><?= htmlspecialchars($r['code']) ?></td>
          <td class="small"><?= htmlspecialchars($r['name']) ?></td>
          <td class="small"><?= htmlspecialchars($r['date']) ?></td>
          <td><?= $r['status'] ? '<span class="badge bg-secondary">'.htmlspecialchars($r['status']).'</span>' : '<span class="text-muted">—</span>' ?></td>
          <td class="small"><?= htmlspecialchars($r['in'] ?: '—') ?></td>
          <td class="small"><?= htmlspecialchars($r['out'] ?: '—') ?></td>
          <td class="small">
            <?= $r['ok'] ? '<span class="text-success">OK</span>'
                        : '<span class="text-danger">'.htmlspecialchars(implode(', ', $r['errs'])).'</span>' ?>
          </td>
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
      <div class="card-header bg-white fw-semibold">Upload CSV</div>
      <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="preview">
          <div class="mb-3">
            <label class="form-label">Company <span class="text-danger">*</span></label>
            <select name="company_id" class="form-select" required>
              <option value="">— Select —</option>
              <?php $activeCo = activeCompanyId($db, $user); foreach ($companies as $c): ?>
              <option value="<?= $c['id'] ?>" <?= $activeCo == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['Name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">CSV File <span class="text-danger">*</span></label>
            <input type="file" name="csv_file" accept=".csv,text/csv" class="form-control" required>
            <div class="form-text">Columns: <code>EmployeeCode, Date, Status, InTime, OutTime</code>. Export your Excel sheet as CSV first.</div>
          </div>
          <button type="submit" class="btn btn-primary"><i class="bi bi-eye"></i> Preview</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold small">Status codes</div>
      <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($statusHelp as $code => $label): ?>
          <span class="small"><span class="badge bg-secondary"><?= $code ?></span> <?= htmlspecialchars($label) ?></span>
          <?php endforeach; ?>
        </div>
        <hr class="my-2">
        <div class="text-muted small">
          Imported rows override that day's cell in the attendance grid (same as a manual correction).
          Leave <code>Status</code> blank to record only In/Out times.
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
