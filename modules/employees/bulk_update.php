<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/_fields.php';
requireAdmin();
requirePermission('emp_bulk.view');

$db   = getDb();
$user = currentUser();
$CAT  = employeeFieldCatalog();

// Company comes from the global topbar switcher (same as Bulk Edit).
$fCompany = (int) activeCompanyId($db, $user);

$fCompanyName = '';
if ($fCompany) {
    $cn = $db->prepare("SELECT Name FROM tblCompany WHERE id=?");
    $cn->execute([$fCompany]);
    $fCompanyName = (string) $cn->fetchColumn();
}

/** A superadmin sees all companies; anyone else only their own. */
function bu_ownsCompany(PDO $db, array $user, int $cid): bool {
    if ($cid <= 0) return false;
    if ($user['role'] === 'superadmin') return true;
    $s = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $s->execute([$cid, $user['scope_id']]);
    return (bool) $s->fetch();
}

// ── Column-name resolver: sheet header → tblEmployee column ─────────────────────
$normKey = fn($s) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $s));
$lookup  = [];
foreach ($CAT as $k => $m) {
    $lookup[$normKey($k)]          = $k;      // by column name
    $lookup[$normKey($m['label'])] = $k;      // by human label
}
// Friendly aliases people actually type in spreadsheets.
foreach ([
    'empcode' => 'EmployeeCode', 'code' => 'EmployeeCode', 'employeecode' => 'EmployeeCode',
    'phone' => 'Phone', 'mobile' => 'Phone', 'contactno' => 'Phone', 'mobileno' => 'Phone',
    'aadhaar' => 'AdhaarID', 'aadhar' => 'AdhaarID', 'adhaar' => 'AdhaarID',
    'aadhaarid' => 'AdhaarID', 'adhaarid' => 'AdhaarID', 'aadharno' => 'AdhaarID',
    'pan' => 'PanNo', 'panno' => 'PanNo', 'doj' => 'JoinDate', 'joindate' => 'JoinDate',
    'dol' => 'DOL', 'dateofleaving' => 'DOL', 'dateofleave' => 'DOL', 'exitdate' => 'DOL',
    'ifsc' => 'IFSCCode', 'ifsccode' => 'IFSCCode',
    'bankac' => 'BankAcNo', 'bankacno' => 'BankAcNo', 'bankaccount' => 'BankAcNo',
    'fathername' => 'FatherName', 'dob' => 'DOB',
] as $a => $k) {
    $lookup[$a] = $k;
}

// ── Spreadsheet readers (.csv + .xlsx, no external library) ─────────────────────
function bu_colIndex(string $ref): int {
    if (!preg_match('/^([A-Z]+)/', $ref, $m)) return 0;
    $n = 0;
    for ($i = 0, $L = strlen($m[1]); $i < $L; $i++) $n = $n * 26 + (ord($m[1][$i]) - 64);
    return $n - 1;
}
/** Text of an <si>/<is> shared-string node (may be split across <r> runs). */
function bu_siText($node): string {
    if ($node === null) return '';
    if (isset($node->t)) return (string) $node->t;
    $s = '';
    if (isset($node->r)) foreach ($node->r as $r) $s .= (string) $r->t;
    return $s;
}
/** Excel serial date → Y-m-d (1900 date system; base 1899-12-30 absorbs the leap bug). */
function bu_excelSerialToYmd(float $serial): ?string {
    if ($serial < 1 || $serial > 2958465) return null;   // ~1900-01-01 .. 9999-12-31
    $d = new DateTime('1899-12-30');
    $d->modify('+' . (int) floor($serial) . ' days');
    return $d->format('Y-m-d');
}
function bu_parseCsv(string $path): array {
    $rows = [];
    $h = fopen($path, 'r');
    if (!$h) throw new Exception('Could not read the uploaded file.');
    while (($r = fgetcsv($h)) !== false) {
        if (!$rows && isset($r[0])) $r[0] = preg_replace('/^\xEF\xBB\xBF/', '', $r[0]); // strip BOM
        $rows[] = array_map('strval', $r);
        if (count($rows) > 5001) break;
    }
    fclose($h);
    if (!$rows) return [[], []];
    $header = array_shift($rows);
    return [$header, $rows];
}
function bu_parseXlsx(string $path): array {
    if (!class_exists('ZipArchive')) throw new Exception('This server cannot read .xlsx files — please upload a CSV instead.');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new Exception('The .xlsx file could not be opened (is it a real Excel file?).');

    $shared = [];
    $ss = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss !== false) {
        $x = @simplexml_load_string($ss);
        if ($x) foreach ($x->si as $si) $shared[] = bu_siText($si);
    }
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = $zip->getNameIndex($i);
            if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $n)) { $sheetXml = $zip->getFromName($n); break; }
        }
    }
    $zip->close();
    if (!$sheetXml) throw new Exception('No worksheet was found in the .xlsx file.');
    $x = @simplexml_load_string($sheetXml);
    if (!$x) throw new Exception('The worksheet could not be parsed.');

    $rows = [];
    foreach ($x->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $col = bu_colIndex((string) $c['r']);
            $t   = (string) $c['t'];
            if     ($t === 's')         $val = $shared[(int) $c->v] ?? '';
            elseif ($t === 'inlineStr') $val = bu_siText($c->is);
            else                        $val = (string) $c->v;      // str / n / b / (blank)
            $cells[$col] = $val;
        }
        $max   = $cells ? max(array_keys($cells)) : -1;
        $dense = [];
        for ($i = 0; $i <= $max; $i++) $dense[$i] = $cells[$i] ?? '';
        $rows[] = $dense;
        if (count($rows) > 5001) break;
    }
    if (!$rows) return [[], []];
    $header = array_shift($rows);
    return [array_map('strval', $header), $rows];
}
function bu_parseUpload(array $file): array {
    $tmp = $file['tmp_name'] ?? '';
    if (!$tmp || !is_uploaded_file($tmp)) throw new Exception('Please choose a file to upload.');
    $name = strtolower($file['name'] ?? '');
    if (preg_match('/\.csv$/', $name))  return bu_parseCsv($tmp);
    if (preg_match('/\.xlsx$/', $name)) return bu_parseXlsx($tmp);
    if (preg_match('/\.xls$/', $name))  throw new Exception('Old .xls format is not supported — save the file as .xlsx or .csv and re-upload.');
    // Unknown extension: sniff the zip signature (xlsx) else treat as CSV.
    $fh = fopen($tmp, 'rb'); $sig = fread($fh, 2); fclose($fh);
    return $sig === 'PK' ? bu_parseXlsx($tmp) : bu_parseCsv($tmp);
}

/** Coerce a raw cell for a field. Returns [ok, value]; ok=false means skip
 *  (blank or invalid — blanks are intentionally ignored, never written). */
function bu_coerce(string $key, string $type, $raw, array $meta): array {
    $raw = is_string($raw) ? trim($raw) : $raw;
    if ($raw === '' || $raw === null) return [false, null];
    switch ($type) {
        case 'int':     return is_numeric($raw) ? [true, (int) $raw]   : [false, null];
        case 'decimal': return is_numeric($raw) ? [true, (float) $raw] : [false, null];
        case 'date':
            if (is_numeric($raw)) { $d = bu_excelSerialToYmd((float) $raw); return $d ? [true, $d] : [false, null]; }
            return strtotime($raw) ? [true, date('Y-m-d', strtotime($raw))] : [false, null];
        case 'select':
            $v = bu_selectValue($key, (string) $raw, $meta['options'] ?? []);
            return $v === '' ? [false, null] : [true, $v];
        default:
            return [true, (string) $raw];
    }
}
/** Match a value to a select option, tolerating case and legacy Gender codes. */
function bu_selectValue(string $key, string $val, array $options): string {
    $val = trim($val);
    if ($val === '') return '';
    if (in_array($val, $options, true)) return $val;
    foreach ($options as $o) if ($o !== '' && strcasecmp($o, $val) === 0) return $o;
    if ($key === 'Gender') {
        $c = strtolower($val[0]);
        if ($c === 'm' && in_array('Male', $options, true))   return 'Male';
        if ($c === 'f' && in_array('Female', $options, true)) return 'Female';
    }
    return '';
}
/** Whether a coerced value differs from what's stored (numeric-aware). */
function bu_differs(string $type, $new, $cur): bool {
    if ($type === 'int' || $type === 'decimal') {
        if ($cur === null || $cur === '') return true;
        return (float) $new !== (float) $cur;
    }
    return trim((string) $new) !== trim((string) ($cur ?? ''));
}

// ── Download current employees as CSV (edit → re-upload) ────────────────────────
if (isset($_GET['export'])) {
    if (!$fCompany || !bu_ownsCompany($db, $user, $fCompany)) { http_response_code(400); exit('Select a company from the top bar first.'); }
    $cols = array_keys($CAT);
    $st = $db->prepare("SELECT * FROM tblEmployee WHERE CompanyId=? ORDER BY ISNULL(Sr), Sr, Name");
    $st->execute([$fCompany]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="employees_' . $fCompany . '_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");                       // BOM so Excel keeps UTF-8
    fputcsv($out, $cols);
    foreach ($st->fetchAll() as $e) {
        $line = [];
        foreach ($cols as $c) $line[] = $e[$c] ?? '';
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

$step   = 'upload';   // upload | preview | done
$error  = '';
$P      = [];         // preview payload

// ── Step 1: parse + build a change preview ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'preview') {
    requirePermission('emp_bulk.edit');
    csrf_verify();
    try {
        if (!$fCompany || !bu_ownsCompany($db, $user, $fCompany)) throw new Exception('Select a company from the top-bar switcher first.');
        [$header, $dataRows] = bu_parseUpload($_FILES['file'] ?? []);
        if (!$header) throw new Exception('The file has no header row.');

        // Map each column to a tblEmployee field; find the EmployeeCode (key) column.
        $map     = [];   // colIndex → CAT key
        $codeCol = null;
        foreach ($header as $i => $h) {
            $key = $lookup[$normKey($h)] ?? null;
            if ($key === null) continue;
            if ($key === 'EmployeeCode') { $codeCol = $i; continue; }   // key: match only, never write
            $map[$i] = $key;
        }
        if ($codeCol === null) throw new Exception('No "EmployeeCode" (or Code) column found — it is required to match rows.');
        if (!$map)             throw new Exception('No updatable columns recognised in the file besides EmployeeCode.');

        // Current employees for this company, keyed by code.
        $st = $db->prepare("SELECT * FROM tblEmployee WHERE CompanyId=?");
        $st->execute([$fCompany]);
        $byCode = [];
        foreach ($st->fetchAll() as $e) {
            $byCode[trim($e['EmployeeCode'] ?? '')]              = $e;   // exact
            $byCode['~lc~' . strtolower(trim($e['EmployeeCode'] ?? ''))] = $e;   // case-insensitive fallback
        }

        $updates = [];               // to store + display
        $noChange = 0; $blankCode = 0;
        $unmatched = [];

        foreach ($dataRows as $r) {
            $code = trim((string) ($r[$codeCol] ?? ''));
            if ($code === '') { $blankCode++; continue; }
            $e = $byCode[$code] ?? $byCode['~lc~' . strtolower($code)] ?? null;
            if (!$e) { if (count($unmatched) < 200) $unmatched[] = $code; else $unmatched[] = null; continue; }

            $changes = []; $set = [];
            foreach ($map as $i => $key) {
                [$ok, $val] = bu_coerce($key, $CAT[$key]['type'], $r[$i] ?? '', $CAT[$key]);
                if (!$ok) continue;                                    // blank / invalid → ignore
                if (!bu_differs($CAT[$key]['type'], $val, $e[$key] ?? null)) continue;
                $changes[$key] = ['old' => $e[$key] ?? '', 'new' => $val];
                $set[$key]     = $val;
            }
            if (!$set) { $noChange++; continue; }
            $updates[] = ['id' => (int) $e['id'], 'code' => $e['EmployeeCode'], 'name' => $e['Name'], 'changes' => $changes, 'set' => $set];
        }

        // Stash only what apply needs (id + set) — never the raw file.
        $_SESSION['bu_apply'] = [
            'company' => $fCompany,
            'rows'    => array_map(fn($u) => ['id' => $u['id'], 'set' => $u['set']], $updates),
        ];

        $unmatchedShown = array_values(array_filter($unmatched, fn($v) => $v !== null));
        $P = [
            'updates'        => $updates,
            'noChange'       => $noChange,
            'blankCode'      => $blankCode,
            'unmatchedCount' => count($unmatched),
            'unmatched'      => array_slice($unmatchedShown, 0, 50),
            'cols'           => array_values($map),
            'fileName'       => $_FILES['file']['name'] ?? '',
        ];
        $step = 'preview';
    } catch (Exception $ex) {
        $error = $ex->getMessage();
    }
}

// ── Step 2: apply the stored updates ────────────────────────────────────────────
$done = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
    requirePermission('emp_bulk.edit');
    csrf_verify();
    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    $stash  = $_SESSION['bu_apply'] ?? null;
    $cid    = (int) ($stash['company'] ?? 0);

    if (!$stash || !$cid || !bu_ownsCompany($db, $user, $cid)) {
        $error = 'Your preview expired or the company changed — please upload again.';
        $step  = 'upload';
    } else {
        $updated = 0; $errors = 0;
        $validKeys = array_flip(array_keys($CAT));
        foreach ($stash['rows'] as $u) {
            $id  = (int) ($u['id'] ?? 0);
            $set = (array) ($u['set'] ?? []);
            if (!$id || !$set) continue;
            $frags = []; $vals = [];
            foreach ($set as $k => $v) {
                if (!isset($validKeys[$k]) || $k === 'EmployeeCode') continue;   // guard: never the key
                $frags[] = "`$k`=?"; $vals[] = ($v === '' ? null : $v);
            }
            if (!$frags) continue;
            $vals[] = $id; $vals[] = $cid;                                       // scope-locked update
            try {
                $db->prepare("UPDATE tblEmployee SET " . implode(', ', $frags) . ", UpdatedAt=NOW() WHERE id=? AND CompanyId=?")
                   ->execute($vals);
                $updated++;
            } catch (PDOException $e) { $errors++; }
        }
        unset($_SESSION['bu_apply']);
        $done = ['updated' => $updated, 'errors' => $errors];
        $step = 'done';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => "Updated $updated employee(s)" . ($errors ? ", $errors error(s)" : '') . '.']);
            exit;
        }
    }
}

$pageTitle  = 'Excel / CSV Update';
$activePage = 'emp_bulk_update';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div>
    <span class="fw-semibold">Excel / CSV Bulk Update</span>
    <span class="text-muted small ms-2">Match by Employee Code — update only, no inserts or deletes.</span>
  </div>
  <a href="bulk_edit.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-table me-1"></i>Bulk Edit grid</a>
</div>

<?php if (!$fCompany): ?>
<div class="alert alert-info">Select a company from the top-bar switcher to begin.</div>
<?php else: ?>

<?php if ($error): ?>
<div class="alert alert-danger" data-no-toast><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($step === 'upload'): ?>
<div class="card border-0 shadow-sm" style="max-width:720px">
  <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
    <i class="bi bi-file-earmark-arrow-up text-primary"></i> Upload a sheet to update
    <?php if ($fCompanyName): ?><span class="badge bg-primary ms-1"><?= htmlspecialchars($fCompanyName) ?></span><?php endif; ?>
  </div>
  <div class="card-body">
    <div class="alert alert-light border small">
      <div class="mb-1"><i class="bi bi-1-circle me-1"></i><a href="?export=1"><strong>Download current employees</strong></a> as a CSV starter (includes every column).</div>
      <div class="mb-1"><i class="bi bi-2-circle me-1"></i>Edit values in Excel. Keep the <code>EmployeeCode</code> column — it's the key and is <strong>never changed</strong>.</div>
      <div class="mb-1"><i class="bi bi-3-circle me-1"></i>Upload the <code>.xlsx</code> or <code>.csv</code>. Only rows whose code <strong>matches an existing employee</strong> are updated.</div>
      <div class="text-muted"><i class="bi bi-info-circle me-1"></i>Blank cells are ignored (existing value kept). Unknown codes are skipped — nothing is inserted or deleted.</div>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="preview">
      <div class="mb-3">
        <label class="form-label">File <span class="text-danger">*</span></label>
        <input type="file" name="file" class="form-control" accept=".csv,.xlsx" required>
        <div class="form-text">Recognised columns: <?= htmlspecialchars(implode(', ', array_map(fn($m) => $m['label'], $CAT))) ?>. Up to 5,000 rows.</div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="bi bi-eye me-1"></i>Preview changes</button>
    </form>
  </div>
</div>

<?php elseif ($step === 'preview'): ?>
<?php $willUpdate = count($P['updates']); ?>
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3"><div class="card text-center p-2"><div class="fs-4 fw-bold text-success"><?= $willUpdate ?></div><div class="small text-muted">Will update</div></div></div>
  <div class="col-6 col-md-3"><div class="card text-center p-2"><div class="fs-4 fw-bold text-secondary"><?= (int)$P['noChange'] ?></div><div class="small text-muted">Matched, no change</div></div></div>
  <div class="col-6 col-md-3"><div class="card text-center p-2"><div class="fs-4 fw-bold text-danger"><?= (int)$P['unmatchedCount'] ?></div><div class="small text-muted">Code not found</div></div></div>
  <div class="col-6 col-md-3"><div class="card text-center p-2"><div class="fs-4 fw-bold text-muted"><?= (int)$P['blankCode'] ?></div><div class="small text-muted">Blank code (skipped)</div></div></div>
</div>

<?php if ($P['unmatched']): ?>
<div class="alert alert-warning py-2 small">
  <i class="bi bi-exclamation-triangle me-1"></i><strong>Not found in <?= htmlspecialchars($fCompanyName) ?> (skipped):</strong>
  <?= htmlspecialchars(implode(', ', $P['unmatched'])) ?><?= $P['unmatchedCount'] > count($P['unmatched']) ? ' … (+' . ($P['unmatchedCount'] - count($P['unmatched'])) . ' more)' : '' ?>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold">Changes to apply <small class="text-muted">from <?= htmlspecialchars($P['fileName']) ?></small></div>
  <div class="card-body p-0" style="max-height:440px;overflow:auto">
    <?php if (!$willUpdate): ?>
    <div class="p-4 text-center text-muted">No changes detected — every matched row already has these values.</div>
    <?php else: ?>
    <table class="table table-sm table-hover mb-0">
      <thead class="table-light sticky-top"><tr><th style="width:110px">Code</th><th style="width:180px">Name</th><th>Changes</th></tr></thead>
      <tbody>
        <?php foreach ($P['updates'] as $u): ?>
        <tr>
          <td><code class="small"><?= htmlspecialchars($u['code'] ?: '—') ?></code></td>
          <td class="small"><?= htmlspecialchars($u['name']) ?></td>
          <td class="small">
            <?php foreach ($u['changes'] as $k => $ch): ?>
            <span class="d-inline-block me-3 mb-1">
              <span class="text-muted"><?= htmlspecialchars($CAT[$k]['label']) ?>:</span>
              <span class="text-decoration-line-through text-danger"><?= htmlspecialchars(($ch['old'] === '' || $ch['old'] === null) ? '—' : (string)$ch['old']) ?></span>
              <i class="bi bi-arrow-right mx-1"></i>
              <span class="text-success fw-semibold"><?= htmlspecialchars((string)$ch['new']) ?></span>
            </span>
            <?php endforeach; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<form method="POST" class="d-flex gap-2">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="apply">
  <?php if ($willUpdate): ?>
  <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Apply <?= $willUpdate ?> update<?= $willUpdate === 1 ? '' : 's' ?></button>
  <?php endif; ?>
  <a href="bulk_update.php" class="btn btn-outline-secondary">Cancel / upload another</a>
</form>

<?php else: // done ?>
<div class="alert alert-success">
  <i class="bi bi-check-circle me-1"></i><strong>Update complete.</strong>
  Updated <strong><?= (int)$done['updated'] ?></strong> employee(s)<?= (int)$done['errors'] ? ' — <strong class="text-danger">' . (int)$done['errors'] . ' error(s)</strong>' : '' ?>.
</div>
<a href="bulk_update.php" class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Update more</a>
<a href="bulk_edit.php" class="btn btn-outline-secondary">Back to Bulk Edit</a>
<?php endif; ?>

<?php endif; // company ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
