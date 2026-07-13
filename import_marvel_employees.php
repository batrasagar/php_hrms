<?php
/**
 * import_marvel_employees.php  —  CLI ONLY
 *
 * Update Marvel Home Fashion employee master data in this app's MySQL tblEmployee
 * from a CSV EXPORTED from the source SQL Server table marvel_hr.tblEmployee.
 *
 * Needs ONLY MySQL — no SQL Server driver — so it runs anywhere (incl. Hostinger).
 *
 *   Match key      : source EmpCode  =  app EmployeeCode  (within the Marvel company)
 *   Unmatched rows : skipped and logged (update-only; nothing inserted)
 *
 *   NEVER touched  : Shift & Weekly-Off  -> WeekdayNo, ShiftNo, ShiftRotation, ShiftRotationDate
 *                    Keys / app-managed  -> id, CompanyId, EmployeeCode, Compliance, Status,
 *                                           Photo, Signature, OT, CreatedAt, UpdatedAt
 *
 *   Column names differ between source and app — see $RENAME below. Everything else
 *   is matched by identical (case-insensitive) name. A column present on only one
 *   side is ignored.
 *
 *   SAFETY DEFAULT: a blank/NULL source value NEVER overwrites a populated app value.
 *                   Only real values are written, and only columns that actually change.
 *                   Pass --overwrite-blanks to also clear app fields the source left empty.
 *
 * USAGE
 *   php import_marvel_employees.php marvel_employees.csv                 # DRY RUN (writes nothing)
 *   php import_marvel_employees.php marvel_employees.csv --commit        # apply the UPDATEs
 *   php import_marvel_employees.php file.csv --limit=5 --verbose         # inspect a few rows
 *   php import_marvel_employees.php file.csv --delimiter=";"             # non-comma CSV
 *
 * Timestamped report -> ./logs/marvel_import_*.log
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only.\n"); }

date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/config/db.php';   // DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS (MySQL)

/* ------------------------------------------------------------------ config */

const APP_TABLE   = 'tblEmployee';
const APP_KEY_COL = 'EmployeeCode';        // match column in the app
const NULL_DATE_PREFIX = '1900-01-01';     // SQL Server empty-date sentinel -> NULL

// Source CSV header (lower-case)  =>  app column name.
// Only columns whose names DIFFER need an entry; identical names map automatically.
$RENAME = [
    'empcode'        => 'EmployeeCode',   // <- match key
    'empname'        => 'Name',
    'mobile'         => 'Phone',
    'doj'            => 'JoinDate',
    'contractorname' => 'Contractor',
];

// App columns we must never write (case-insensitive).
$EXCLUDE = [
    'WeekdayNo', 'ShiftNo', 'ShiftRotation', 'ShiftRotationDate',   // shift & weekly off
    'id', 'CompanyId', 'EmployeeCode',                              // keys
    'Compliance', 'Status', 'Photo', 'Signature', 'OT',            // app-managed / semantic-collision
    'CreatedAt', 'UpdatedAt',
];

/* --------------------------------------------------------------- arguments */

$argvv = $argv; array_shift($argvv);
$CSV = null;
foreach ($argvv as $a) { if ($a === '' || $a[0] !== '-') { $CSV = $a; break; } }

// Parse $argv directly — PHP getopt() stops at the first non-option arg, which
// breaks when the CSV path precedes the flags. This is order-independent.
$flag = fn(string $name) => in_array("--$name", $argvv, true);
$val  = function (string $name, $default) use ($argvv) {
    foreach ($argvv as $a) if (strncmp($a, "--$name=", strlen($name) + 3) === 0) return substr($a, strlen($name) + 3);
    return $default;
};
$COMMIT     = $flag('commit');
$VERBOSE    = $flag('verbose');
$OVERWRITE  = $flag('overwrite-blanks');
$COMPANY    = $val('company', 'Marvel Home Fashion');
$LIMIT      = max(0, (int)$val('limit', 0));
$DELIM      = $val('delimiter', ',');

if (!$CSV)              { fwrite(STDERR, "Usage: php import_marvel_employees.php <file.csv> [--commit]\n"); exit(2); }
if (!is_readable($CSV)) { fwrite(STDERR, "CSV not readable: $CSV\n"); exit(2); }

/* ------------------------------------------------------------------ logger */

@mkdir(__DIR__ . '/logs', 0775, true);
$logFile = __DIR__ . '/logs/marvel_import_' . date('Ymd_His') . ($COMMIT ? '_COMMIT' : '_DRYRUN') . '.log';
$logfh   = fopen($logFile, 'w');
function logln(string $m = ''): void { global $logfh; fwrite(STDOUT, $m . "\n"); if ($logfh) fwrite($logfh, $m . "\n"); }

logln(str_repeat('=', 72));
logln('Marvel Home Fashion — employee master update (from CSV)');
logln('Mode        : ' . ($COMMIT ? '*** COMMIT (writing changes) ***' : 'DRY RUN (no writes)'));
logln('Blanks      : ' . ($OVERWRITE ? 'WILL overwrite app values with blank source values' : 'preserved (blank source never clears app value)'));
logln('CSV file    : ' . $CSV);
logln('Target co.  : ' . $COMPANY);
logln('Log file    : ' . $logFile);
logln(str_repeat('=', 72));

/* ------------------------------------------------------------ connect: MySQL */

try {
    $app = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
    logln('Connected   : MySQL ' . DB_NAME . ' @ ' . DB_HOST);
} catch (Throwable $e) { fwrite(STDERR, 'MySQL connect failed: ' . $e->getMessage() . "\n"); exit(2); }

/* --------------------------------------------------- resolve Marvel company */

$coStmt = $app->prepare("SELECT id, Name FROM tblCompany WHERE TRIM(Name) = TRIM(?) LIMIT 1");
$coStmt->execute([$COMPANY]);
$co = $coStmt->fetch();
if (!$co) {
    logln("\nERROR: Company '$COMPANY' not found. Available:");
    foreach ($app->query("SELECT id, Name FROM tblCompany ORDER BY Name") as $r) logln(sprintf('   [%d] %s', $r['id'], $r['Name']));
    exit(3);
}
$companyId = (int)$co['id'];
logln("Company     : [$companyId] {$co['Name']}");

/* ------------------------------------------------------------- read headers */

$fh = fopen($CSV, 'r');
$header = fgetcsv($fh, 0, $DELIM);
if (!$header) { logln('ERROR: empty CSV.'); exit(4); }
$header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);   // strip UTF-8 BOM
$header = array_map('trim', $header);

$headerLc = [];
foreach ($header as $i => $h) $headerLc[strtolower($h)] = $i;

/* --------------------------------------------- compute updatable column set */

$appCols = [];
foreach ($app->query('SHOW COLUMNS FROM `' . APP_TABLE . '`') as $r) $appCols[strtolower($r['Field'])] = $r['Field'];
$excludeLc  = array_map('strtolower', $EXCLUDE);
$renameLc   = array_change_key_case($RENAME, CASE_LOWER);

// Resolve each source header to an app column (via $RENAME, else identical name).
// Keyed by app-col-lc so duplicates collapse (first source header wins).
$updateCols = [];   // appColLc => ['idx'=>csvIndex, 'app'=>appColName, 'src'=>srcHeader]
$keyIdx = null;
foreach ($headerLc as $lc => $idx) {
    $appName = $renameLc[$lc] ?? ($appCols[$lc] ?? null);
    if ($appName === null) continue;                       // header not a real app column
    $appLc = strtolower($appName);
    if ($appLc === strtolower(APP_KEY_COL)) { $keyIdx = $idx; continue; }  // the match key — not updated
    if (in_array($appLc, $excludeLc, true)) continue;      // excluded
    if (isset($updateCols[$appLc])) continue;              // already mapped by an earlier header
    if (!isset($appCols[$appLc])) continue;                // rename target not in DB — skip
    $updateCols[$appLc] = ['idx' => $idx, 'app' => $appCols[$appLc], 'src' => $header[$idx]];
}

if ($keyIdx === null) {
    logln("\nERROR: no source column maps to " . APP_KEY_COL . " (expected header 'EmpCode'). Headers:\n  " . implode(', ', $header));
    exit(4);
}
$nameIdx = $headerLc['empname'] ?? ($headerLc['name'] ?? null);

logln("\nMatch key   : source '" . $header[$keyIdx] . "'  ->  app " . APP_KEY_COL);
logln("Renamed cols: " . implode(', ', array_map(fn($lc) => "{$RENAME[$lc]}<-{$lc}", array_keys(array_intersect_key($renameLc, $headerLc)))));
logln("\nColumns eligible to update (" . count($updateCols) . "):");
logln('  ' . wordwrap(implode(', ', array_column($updateCols, 'app')), 68, "\n  ", true));
$excludedPresent = [];
foreach ($headerLc as $lc => $idx) { $an = $renameLc[$lc] ?? ($appCols[$lc] ?? null); if ($an && in_array(strtolower($an), $excludeLc, true)) $excludedPresent[strtolower($an)] = $an; }
logln("\nIn CSV but deliberately NOT updated: " . implode(', ', $excludedPresent));

/* --------------------------------------------------------- prepare statements */

$find = $app->prepare("SELECT * FROM `" . APP_TABLE . "` WHERE CompanyId = ? AND TRIM(`" . APP_KEY_COL . "`) = TRIM(?) LIMIT 1");
$updateCache = [];   // signature => prepared UPDATE statement (reused across rows)

/* ----------------------------------------------------- value normalisation */

function normalize($val) {
    if ($val === null) return null;
    $val = trim((string)$val);
    if ($val === '') return null;
    if (strncmp($val, NULL_DATE_PREFIX, strlen(NULL_DATE_PREFIX)) === 0) return null;
    // "2004-01-01 12:00:00 AM"  ->  "2004-01-01"  (strip exported time-of-day)
    if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T]\d{1,2}:\d{2}(:\d{2})?(\s*[AP]M)?$/i', $val, $m)) return $m[1];
    return $val;
}

/* ------------------------------------------------------------------- run it */

$stats = ['read'=>0,'matched'=>0,'updated'=>0,'changed'=>0,'unmatched'=>0,'errors'=>0,'writes'=>0];
$unmatched = [];

if ($COMMIT) $app->beginTransaction();

while (($row = fgetcsv($fh, 0, $DELIM)) !== false) {
    if (count($row) === 1 && ($row[0] === null || $row[0] === '')) continue;
    $stats['read']++;
    if ($LIMIT && $stats['read'] > $LIMIT) { $stats['read']--; break; }

    $code = isset($row[$keyIdx]) ? trim((string)$row[$keyIdx]) : '';
    $who  = $nameIdx !== null ? ($row[$nameIdx] ?? '') : '';
    if ($code === '') { $stats['unmatched']++; $unmatched[] = "(blank code) $who"; continue; }

    $find->execute([$companyId, $code]);
    $target = $find->fetch();
    if (!$target) { $stats['unmatched']++; $unmatched[] = "$code  $who"; continue; }
    $stats['matched']++;

    // Only columns that (a) have a usable source value (unless --overwrite-blanks) and (b) actually differ.
    $setCols = []; $setVals = []; $changes = [];
    foreach ($updateCols as $c) {
        $new = normalize($row[$c['idx']] ?? null);
        if ($new === null && !$OVERWRITE) continue;             // never wipe app value with a blank
        $oldN = normalize($target[$c['app']] ?? null);
        if ((string)$oldN === (string)$new) continue;           // no change
        $setCols[] = $c['app']; $setVals[] = $new;
        $changes[$c['app']] = [(string)$oldN, (string)$new];
    }
    if (!$setCols) continue;
    $stats['changed']++;

    if ($VERBOSE || !$COMMIT) {
        logln("\n#{$target['id']}  $code  {$target['Name']}  (" . count($setCols) . ' field(s))');
        foreach ($changes as $col => [$o, $n]) logln(sprintf('    %-22s %s  ->  %s', $col, $o === '' ? 'NULL' : $o, $n === '' ? 'NULL' : $n));
    }

    if ($COMMIT) {
        $sig = implode(',', $setCols);
        if (!isset($updateCache[$sig])) {
            $set = implode(', ', array_map(fn($c) => "`$c`=?", $setCols));
            $updateCache[$sig] = $app->prepare("UPDATE `" . APP_TABLE . "` SET $set, UpdatedAt=NOW() WHERE id=?");
        }
        try { $updateCache[$sig]->execute([...$setVals, (int)$target['id']]); $stats['updated']++; $stats['writes'] += count($setCols); }
        catch (PDOException $e) { $stats['errors']++; logln("  ! UPDATE failed id {$target['id']} ($code): " . $e->getMessage()); }
    }
}
fclose($fh);

if ($COMMIT) {
    if ($stats['errors'] === 0) { $app->commit(); logln("\nTransaction committed."); }
    else { $app->rollBack(); logln("\n" . $stats['errors'] . " error(s) — ROLLED BACK. No changes applied."); }
}

/* -------------------------------------------------------------------- report */

logln("\n" . str_repeat('-', 72));
logln('CSV rows read           : ' . $stats['read']);
logln('Matched in app          : ' . $stats['matched']);
logln('Rows with changes       : ' . $stats['changed']);
logln('Rows updated            : ' . ($COMMIT ? $stats['updated'] : '0  (dry run)'));
logln('Field writes            : ' . ($COMMIT ? $stats['writes'] : '0  (dry run)'));
logln('Unmatched (skipped)     : ' . $stats['unmatched']);
logln('Errors                  : ' . $stats['errors']);
if ($unmatched) {
    logln("\nUnmatched CSV employees (no EmployeeCode match under {$co['Name']}):");
    foreach (array_slice($unmatched, 0, 60) as $u) logln('   - ' . $u);
    if (count($unmatched) > 60) logln('   … +' . (count($unmatched) - 60) . ' more');
}
logln(str_repeat('-', 72));
logln($COMMIT ? 'DONE (committed).' : 'DRY RUN complete — re-run with --commit to apply.');
if ($logfh) fclose($logfh);
exit($stats['errors'] > 0 ? 1 : 0);
