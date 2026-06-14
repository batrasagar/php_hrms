<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
if (currentUser()['role'] !== 'superadmin') { header('Location: ' . BASE_URL . '/index.php'); exit; }

$db = getDb();

// Ensure token table exists (with RawToken for display)
$db->exec("CREATE TABLE IF NOT EXISTS `tblCronToken` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `Token`     CHAR(64)     NOT NULL UNIQUE COMMENT 'SHA-256 of RawToken, used for auth',
    `RawToken`  CHAR(64)     NOT NULL DEFAULT '' COMMENT 'Stored for display in docs only',
    `Label`     VARCHAR(100) NOT NULL DEFAULT '',
    `IsActive`  TINYINT(1)   NOT NULL DEFAULT 1,
    `LastUsed`  DATETIME     DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB");
// Add RawToken column if table existed before this change
try { $db->exec("ALTER TABLE `tblCronToken` ADD COLUMN `RawToken` CHAR(64) NOT NULL DEFAULT '' AFTER `Token`"); } catch (PDOException $e) {}

$tokenMsg = '';

// Generate new token
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gen_token'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $raw   = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $raw);
    $label = trim($_POST['label'] ?? 'cron-job.org');
    $db->prepare("INSERT INTO tblCronToken (Token, RawToken, Label) VALUES (?,?,?)")->execute([$hash, $raw, $label]);
    $tokenMsg = 'generated';
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>'Token generated.','redirect'=>'pipeline.php']); exit; }
    header('Location: pipeline.php'); exit; // reload so token shows in table
}
// Revoke
if (isset($_GET['revoke'])) {
    $db->prepare("UPDATE tblCronToken SET IsActive=0 WHERE id=?")->execute([(int)$_GET['revoke']]);
    header('Location: pipeline.php'); exit;
}

$tokens = $db->query("SELECT * FROM tblCronToken ORDER BY id DESC")->fetchAll();
// Active token for URL display (first active one)
$activeToken = '';
foreach ($tokens as $t) { if ($t['IsActive'] && $t['RawToken']) { $activeToken = $t['RawToken']; break; } }

$pageTitle  = 'Pipeline Docs';
$activePage = 'docs_pipeline';
require_once __DIR__ . '/../../includes/header.php';

$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
$root    = rtrim(str_replace('\\', '/', realpath(__DIR__ . '/../..')), '/');
$triggerBase = $baseUrl . '/cron/trigger.php';
?>
<style>
.doc-section { margin-bottom: 2.8rem; }
.doc-section h5 {
  font-weight: 700;
  border-bottom: 2px solid var(--border);
  padding-bottom: .45rem;
  margin-bottom: 1.1rem;
  letter-spacing: -.01em;
}
pre.cb {
  background: #1a2233; color: #e2e8f4;
  border-radius: 10px; padding: 1rem 1.3rem;
  font-size: .84rem; overflow-x: auto; margin: 0;
  line-height: 1.6;
}
pre.cb .cm  { color: #7ec8e3; }
pre.cb .kw  { color: #c792ea; }
pre.cb .str { color: #c3e88d; }
pre.cb .num { color: #f78c6c; }
.pill {
  display: inline-block; border-radius: 6px;
  font-size: .75rem; font-weight: 600; padding: 2px 9px;
}
.pill-blue   { background: #dbeafe; color: #1d4ed8; }
.pill-green  { background: #dcfce7; color: #166534; }
.pill-orange { background: #ffedd5; color: #9a3412; }
.pill-purple { background: #f3e8ff; color: #6b21a8; }
.flow-arrow  { font-size: 1.4rem; color: var(--text-3); line-height: 1; }
.flow-box {
  border: 1.5px solid var(--border);
  border-radius: 10px; padding: 10px 16px;
  background: var(--surface); font-size: .88rem;
  text-align: center; min-width: 160px;
}
.flow-box .fb-title { font-weight: 600; font-size: .9rem; }
.flow-box .fb-sub   { font-size: .75rem; color: var(--text-2); margin-top: 2px; }
.tbl-doc th { font-size: .78rem; }
</style>

<div style="max-width:900px">

<!-- ── Overview ─────────────────────────────────────────────────────────── -->
<div class="doc-section">
  <h5><i class="bi bi-diagram-3 me-2"></i>Attendance Pipeline — Overview</h5>
  <p class="text-muted mb-3">
    Four sequential stages convert raw biometric punches into pre-computed payroll.
    Each stage writes to a <strong>YrMnth-sharded</strong> table
    (<code>tblX_YYMM</code>) so every monthly dataset stays bounded at ~30M rows
    regardless of how many years the system runs.
  </p>

  <!-- Flow diagram -->
  <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
    <div class="flow-box">
      <div class="fb-title"><i class="bi bi-hdd-network me-1"></i>tblDeviceLog</div>
      <div class="fb-sub">AttnLog central raw</div>
    </div>
    <div class="flow-arrow">→</div>
    <div class="flow-box" style="border-color:#93c5fd">
      <div class="fb-title text-primary"><i class="bi bi-arrow-repeat me-1"></i>PunchSyncService</div>
      <div class="fb-sub">Every 15–30 min</div>
    </div>
    <div class="flow-arrow">→</div>
    <div class="flow-box">
      <div class="fb-title"><i class="bi bi-clock-history me-1"></i>tblPunchLog</div>
      <div class="fb-sub">Local, enriched</div>
    </div>
  </div>
  <div class="d-flex align-items-center flex-wrap gap-2 mb-3" style="padding-left:188px">
    <div class="flow-arrow">↓</div>
    <div class="text-muted small ms-2">+ tblPunchLogCorrection (override)</div>
  </div>
  <div class="d-flex align-items-center flex-wrap gap-2 mb-3">
    <div class="flow-box" style="border-color:#93c5fd">
      <div class="fb-title text-primary"><i class="bi bi-calendar-check me-1"></i>AttendanceProcessor</div>
      <div class="fb-sub">Daily at 01:00</div>
    </div>
    <div class="flow-arrow">→</div>
    <div class="flow-box">
      <div class="fb-title"><i class="bi bi-table me-1"></i>tblAttendance_YYMM</div>
      <div class="fb-sub">1 row / emp / day</div>
    </div>
    <div class="flow-arrow">→</div>
    <div class="flow-box" style="border-color:#93c5fd">
      <div class="fb-title text-primary"><i class="bi bi-calendar-month me-1"></i>MonthlyProcessor</div>
      <div class="fb-sub">1st of month 02:00</div>
    </div>
    <div class="flow-arrow">→</div>
    <div class="flow-box">
      <div class="fb-title"><i class="bi bi-bar-chart me-1"></i>tblMonthlyAttendance_YYMM</div>
      <div class="fb-sub">D01–D31 + summary</div>
    </div>
  </div>
  <div class="d-flex align-items-center flex-wrap gap-2">
    <div class="flow-box" style="border-color:#93c5fd">
      <div class="fb-title text-primary"><i class="bi bi-cash-stack me-1"></i>PayRollProcessor</div>
      <div class="fb-sub">2nd of month 02:30</div>
    </div>
    <div class="flow-arrow">→</div>
    <div class="flow-box">
      <div class="fb-title"><i class="bi bi-receipt me-1"></i>tblPayRoll_YYMM</div>
      <div class="fb-sub">status = draft</div>
    </div>
  </div>
</div>

<!-- ── Manual Run ────────────────────────────────────────────────────── -->
<div class="doc-section">
  <h5><i class="bi bi-play-circle me-2"></i>Manual Run</h5>

  <?php if (!$activeToken): ?>
  <div class="alert alert-warning py-2 small mb-0">
    <i class="bi bi-exclamation-triangle me-1"></i>
    No active token yet — generate one in the <strong>Scheduled Jobs</strong> section below first.
  </div>
  <?php else: ?>

  <div class="row g-2 mb-3">
    <div class="col-sm-6 col-md-3">
      <div class="mb-2">
        <label class="form-label small mb-1">Company (0 = all)</label>
        <select id="runCompany" class="form-select form-select-sm">
          <option value="0">All Companies</option>
          <?php
          $allCos = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
          foreach ($allCos as $co): ?>
          <option value="<?= $co['id'] ?>"><?= htmlspecialchars($co['Name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label small mb-1">Month (YYMM, blank = prev)</label>
        <input type="text" id="runYm" class="form-control form-control-sm" placeholder="e.g. <?= date('ym') ?>">
      </div>
    </div>
    <div class="col-sm-6 col-md-9">
      <label class="form-label small mb-1">Run step</label>
      <div class="d-flex flex-wrap gap-2">
        <?php
        $steps = [
          ['sync',       'bi-arrow-repeat',    'Sync Punches',    'Last 24h → tblPunchLog'],
          ['attendance', 'bi-calendar-check',  'Attendance',      'Today → tblAttendance'],
          ['monthly',    'bi-calendar-month',  'Monthly Rollup',  'Prev month → tblMonthly'],
          ['payroll',    'bi-cash-stack',       'Payroll',         'Draft → tblPayRoll'],
        ];
        foreach ($steps as [$s, $icon, $label, $sub]):
        ?>
        <button class="btn btn-outline-primary run-btn px-3 py-2" data-step="<?= $s ?>" style="min-width:130px">
          <i class="bi <?= $icon ?> d-block fs-4 mb-1"></i>
          <strong class="d-block"><?= $label ?></strong>
          <span class="small text-muted"><?= $sub ?></span>
        </button>
        <?php endforeach; ?>
        <button class="btn btn-outline-danger run-btn px-3 py-2" data-step="all" style="min-width:130px">
          <i class="bi bi-lightning-charge d-block fs-4 mb-1"></i>
          <strong class="d-block">Run All</strong>
          <span class="small text-muted">All stages in order</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Result panel -->
  <div id="runPanel" class="d-none mt-2">
    <div class="d-flex align-items-center gap-2 mb-2">
      <span id="runStepBadge" class="pill pill-blue"></span>
      <span id="runStatusMsg"></span>
      <span id="runElapsed" class="text-muted small ms-1"></span>
    </div>
    <pre id="runOutput" class="cb mb-0" style="font-size:.82rem;min-height:60px"></pre>
  </div>

  <?php endif; ?>
</div>

<!-- ── Shard Tables ───────────────────────────────────────────────────── -->
<div class="doc-section">
  <h5><i class="bi bi-database-gear me-2"></i>Shard Tables</h5>
  <p class="text-muted mb-3">
    Tables are created automatically by <code>ShardManager</code> on first access.
    The suffix <code>YYMM</code> = last-2-digits of year + 2-digit month
    (e.g. <code>2606</code> = June 2026).
  </p>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-0">
      <table class="table table-sm table-bordered tbl-doc mb-0">
        <thead class="table-light">
          <tr><th>Table</th><th>Key Columns</th><th>Rows / month</th><th>Purpose</th></tr>
        </thead>
        <tbody>
          <tr>
            <td><code>tblAttendance_YYMM</code></td>
            <td><code>CompanyId, EmpCode, tDate</code></td>
            <td>~30 M</td>
            <td>One row per employee per day — status, IN/OUT, OT, short-time</td>
          </tr>
          <tr>
            <td><code>tblMonthlyAttendance_YYMM</code></td>
            <td><code>CompanyId, EmpCode</code></td>
            <td>~1 M</td>
            <td>D01–D31 status codes, totals, JSON per-day detail</td>
          </tr>
          <tr>
            <td><code>tblPayRoll_YYMM</code></td>
            <td><code>CompanyId, EmpCode</code></td>
            <td>~1 M</td>
            <td>Earnings, deductions, net salary — starts as <span class="pill pill-orange">draft</span></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  <p class="text-muted small mb-0">
    <i class="bi bi-info-circle me-1"></i>
    Non-sharded: <code>tblPunchLog</code> (rolling, prunable by date) and
    <code>tblPunchLogCorrection</code> (manual overrides, permanent).
  </p>
</div>

<!-- ── Attendance Status Codes ──────────────────────────────────────── -->
<div class="doc-section">
  <h5><i class="bi bi-tag me-2"></i>Attendance Status Codes</h5>
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <table class="table table-sm table-bordered tbl-doc mb-0">
        <thead class="table-light">
          <tr><th>Code</th><th>Meaning</th><th>Payable</th></tr>
        </thead>
        <tbody>
          <tr><td><span class="pill pill-green">P</span></td><td>Present</td><td>1 day</td></tr>
          <tr><td><span class="pill pill-green">WOP</span></td><td>Present on Week-Off / Holiday</td><td>1 day + OT eligible</td></tr>
          <tr><td><span class="pill pill-blue">HD</span></td><td>Half Day</td><td>0.5 day</td></tr>
          <tr><td><span class="pill pill-orange">A</span></td><td>Absent</td><td>0</td></tr>
          <tr><td><span class="pill" style="background:#f1f5f9;color:#475569">WO</span></td><td>Week Off</td><td>0 (scheduled rest)</td></tr>
          <tr><td><span class="pill" style="background:#f1f5f9;color:#475569">PH</span></td><td>Public Holiday</td><td>0 (paid separately)</td></tr>
          <tr><td><span class="pill pill-purple">L</span></td><td>Leave (approved)</td><td>per leave policy</td></tr>
          <tr><td><span class="pill pill-purple">SL</span></td><td>Sick Leave</td><td>per leave policy</td></tr>
          <tr><td><span class="pill pill-purple">CO</span></td><td>Compensatory Off</td><td>0</td></tr>
          <tr><td><span class="pill pill-purple">OD</span></td><td>On Duty</td><td>1 day</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── Payroll Computation ───────────────────────────────────────────── -->
<div class="doc-section">
  <h5><i class="bi bi-calculator me-2"></i>Payroll Computation Rules</h5>
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-0">
      <table class="table table-sm table-bordered tbl-doc mb-0">
        <thead class="table-light">
          <tr><th>Item</th><th>Formula</th></tr>
        </thead>
        <tbody>
          <tr><td>Pro-rated earnings</td><td><code>Component × (WorkDays ÷ DaysInMonth)</code></td></tr>
          <tr><td>OT amount</td><td><code>(Basic ÷ 26 ÷ 8) × 2 × OT_hours</code> — double rate</td></tr>
          <tr><td>PF (employee)</td><td><code>12% of Basic</code>, capped at ₹1,800 (₹15,000 Basic ceiling)</td></tr>
          <tr><td>ESI (employee)</td><td><code>0.75% of Gross</code>, only if Gross ≤ ₹21,000</td></tr>
          <tr><td>Net Salary</td><td><code>Gross − PF − ESI − TDS − Advance − Other deductions</code></td></tr>
        </tbody>
      </table>
    </div>
  </div>
  <p class="text-muted small">
    <i class="bi bi-pencil me-1"></i>TDS, Advance, and Other deductions default to 0 (draft).
    Edit the payroll row before approving.
  </p>
</div>

<!-- ── Punch Correction ──────────────────────────────────────────────── -->
<div class="doc-section">
  <h5><i class="bi bi-pencil-square me-2"></i>Punch Correction</h5>
  <p class="text-muted mb-2">
    A correction in <code>tblPunchLogCorrection</code> takes full priority over
    <code>tblPunchLog</code> when the AttendanceProcessor runs.
    It can override IN time, OUT time, or force a specific status code.
  </p>
  <pre class="cb"><span class="cm">-- Force employee E001 as Present on 2026-06-10 with manual times</span>
INSERT INTO tblPunchLogCorrection
  (CompanyId, EmpCode, tDate, InTime, OutTime, AttStatus, Reason, CorrectedBy)
VALUES
  (1, <span class="str">'E001'</span>, <span class="str">'2026-06-10'</span>, <span class="str">'09:05:00'</span>, <span class="str">'18:20:00'</span>, <span class="kw">NULL</span>, <span class="str">'Forgot to punch'</span>, 1);

<span class="cm">-- Force as On-Duty (no punch times needed)</span>
INSERT INTO tblPunchLogCorrection
  (CompanyId, EmpCode, tDate, InTime, OutTime, AttStatus, Reason, CorrectedBy)
VALUES
  (1, <span class="str">'E001'</span>, <span class="str">'2026-06-11'</span>, <span class="kw">NULL</span>, <span class="kw">NULL</span>, <span class="str">'OD'</span>, <span class="str">'Off-site visit'</span>, 1);</pre>
  <p class="text-muted small mt-2">
    Use the <a href="<?= BASE_URL ?>/modules/punchlog/correction.php">Punch Correction UI</a>
    instead of raw SQL. After saving, re-run the attendance step for that date.
  </p>
</div>

<!-- ── CLI Runner ────────────────────────────────────────────────────── -->
<div class="doc-section">
  <h5><i class="bi bi-terminal me-2"></i>CLI Runner — <code>cron/process_pipeline.php</code></h5>
  <p class="text-muted mb-3">Run any pipeline stage manually from the command line.</p>

  <h6 class="fw-semibold mb-1">Syntax</h6>
  <pre class="cb mb-3">php <?= $root ?>/cron/process_pipeline.php <span class="kw">[step]</span> <span class="kw">[company_id]</span> <span class="kw">[YYMM]</span>

<span class="cm">Steps:</span>  sync | attendance | monthly | payroll | all
<span class="cm">company_id:</span>  0 = all active companies</pre>

  <h6 class="fw-semibold mb-1">Examples</h6>
  <pre class="cb mb-3"><span class="cm"># Sync today's punches for all companies</span>
php <?= $root ?>/cron/process_pipeline.php sync 0

<span class="cm"># Process daily attendance up to today, all companies</span>
php <?= $root ?>/cron/process_pipeline.php attendance 0

<span class="cm"># Roll up June 2026 monthly attendance, company 5 only</span>
php <?= $root ?>/cron/process_pipeline.php monthly 5 2606

<span class="cm"># Compute payroll for May 2026, all companies</span>
php <?= $root ?>/cron/process_pipeline.php payroll 0 2605

<span class="cm"># Run full pipeline (all stages) for June 2026</span>
php <?= $root ?>/cron/process_pipeline.php all 0 2606</pre>

  <p class="text-muted small">
    <i class="bi bi-shield-check me-1"></i>
    All stages use <code>REPLACE INTO</code> — safe to re-run. Re-running attendance
    after a correction automatically re-marks affected punch logs as pending.
  </p>
</div>

<!-- ── Cron / Task Scheduler ─────────────────────────────────────────── -->
<div class="doc-section">
  <h5><i class="bi bi-clock me-2"></i>Scheduled Jobs</h5>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body p-0">
      <table class="table table-sm table-bordered tbl-doc mb-0">
        <thead class="table-light">
          <tr><th>Step</th><th>Frequency</th><th>Why</th></tr>
        </thead>
        <tbody>
          <tr>
            <td><span class="pill pill-blue">sync</span></td>
            <td>Every <strong>15–30 min</strong></td>
            <td>Keeps tblPunchLog fresh; INSERT IGNORE so safe to run frequently</td>
          </tr>
          <tr>
            <td><span class="pill pill-blue">attendance</span></td>
            <td>Daily <strong>01:00</strong></td>
            <td>Processes all pending dates; runs after midnight so the day is closed</td>
          </tr>
          <tr>
            <td><span class="pill pill-blue">monthly</span></td>
            <td>1st of month <strong>02:00</strong></td>
            <td>Rolls up the previous month once it is fully closed</td>
          </tr>
          <tr>
            <td><span class="pill pill-blue">payroll</span></td>
            <td>2nd of month <strong>02:30</strong></td>
            <td>Runs after monthly is confirmed complete; produces draft payroll</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <h6 class="fw-semibold mb-2">Linux / crontab</h6>
  <pre class="cb mb-4"><span class="cm"># Edit with: crontab -e</span>

<span class="cm"># Punch sync — every 30 minutes</span>
*/30 * * * *  <?= PHP_BINARY ?> <?= $root ?>/cron/process_pipeline.php sync 0

<span class="cm"># Daily attendance — 01:00</span>
0 1 * * *     <?= PHP_BINARY ?> <?= $root ?>/cron/process_pipeline.php attendance 0

<span class="cm"># Monthly rollup — 1st of month 02:00 (targets previous month)</span>
0 2 1 * *     <?= PHP_BINARY ?> <?= $root ?>/cron/run_monthly.php monthly

<span class="cm"># Payroll — 2nd of month 02:30</span>
30 2 2 * *    <?= PHP_BINARY ?> <?= $root ?>/cron/run_monthly.php payroll</pre>

  <h6 class="fw-semibold mb-2">Windows Task Scheduler</h6>
  <pre class="cb"><span class="cm"># Create tasks via PowerShell (run as Administrator)</span>

<span class="cm"># Punch sync — every 30 minutes</span>
schtasks /Create /TN "HRMS\PunchSync" /TR <span class="str">"<?= PHP_BINARY ?> <?= $root ?>/cron/process_pipeline.php sync 0"</span> /SC MINUTE /MO 30 /F

<span class="cm"># Daily attendance — 01:00</span>
schtasks /Create /TN "HRMS\Attendance" /TR <span class="str">"<?= PHP_BINARY ?> <?= $root ?>/cron/process_pipeline.php attendance 0"</span> /SC DAILY /ST 01:00 /F

<span class="cm"># Monthly rollup — 1st of month 02:00</span>
schtasks /Create /TN "HRMS\Monthly" /TR <span class="str">"<?= PHP_BINARY ?> <?= $root ?>/cron/run_monthly.php monthly"</span> /SC MONTHLY /D 1 /ST 02:00 /F

<span class="cm"># Payroll — 2nd of month 02:30</span>
schtasks /Create /TN "HRMS\Payroll" /TR <span class="str">"<?= PHP_BINARY ?> <?= $root ?>/cron/run_monthly.php payroll"</span> /SC MONTHLY /D 2 /ST 02:30 /F</pre>
</div>

<!-- ── cron-job.org ──────────────────────────────────────────────────── -->
<div class="doc-section">
  <h5><i class="bi bi-globe me-2"></i>External HTTP Trigger — cron-job.org</h5>
  <p class="text-muted mb-3">
    Instead of a local cron / Task Scheduler, use
    <a href="https://cron-job.org" target="_blank">cron-job.org</a>
    to hit the secure trigger endpoint over HTTPS.
    Each call runs one pipeline step and returns JSON.
  </p>

  <!-- Token management -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header">
      <i class="bi bi-key me-2"></i>Cron Tokens
      <span class="text-muted fw-normal small ms-2">— required to authenticate the trigger URL</span>
    </div>
    <div class="card-body">

      <?php if ($tokenMsg === 'generated'): ?>
      <div class="alert alert-success py-2">Token generated — URLs below are updated.</div>
      <?php endif; ?>

      <form method="POST" class="d-flex gap-2 align-items-end mb-3" data-ajax>
        <div>
          <label class="form-label small mb-1">Label</label>
          <input type="text" name="label" class="form-control form-control-sm" value="cron-job.org" style="width:180px">
        </div>
        <button name="gen_token" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-lg me-1"></i>Generate Token
        </button>
      </form>

      <?php if ($tokens): ?>
      <table class="table table-sm table-bordered mb-0">
        <thead class="table-light">
          <tr><th>Label</th><th>Token</th><th>Status</th><th>Last Used</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($tokens as $t): ?>
          <tr>
            <td><?= htmlspecialchars($t['Label']) ?></td>
            <td>
              <?php if ($t['RawToken'] && $t['IsActive']): ?>
              <code class="small user-select-all" style="word-break:break-all;font-size:.78rem">
                <?= htmlspecialchars($t['RawToken']) ?>
              </code>
              <button class="btn btn-outline-secondary btn-sm py-0 ms-1"
                      onclick="navigator.clipboard.writeText('<?= htmlspecialchars($t['RawToken']) ?>');this.textContent='✓'"
                      title="Copy token">⎘</button>
              <?php else: ?>
              <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($t['IsActive']): ?>
              <span class="pill pill-green">Active</span>
              <?php else: ?>
              <span class="pill pill-orange">Revoked</span>
              <?php endif; ?>
            </td>
            <td class="text-muted small"><?= $t['LastUsed'] ?: '—' ?></td>
            <td>
              <?php if ($t['IsActive']): ?>
              <a href="pipeline.php?revoke=<?= $t['id'] ?>"
                 class="btn btn-outline-danger btn-sm py-0"
                 onclick="return confirm('Revoke this token?')">Revoke</a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <p class="text-muted small mb-0">No tokens yet. Generate one above.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Trigger URLs -->
  <h6 class="fw-semibold mb-2">Trigger URLs</h6>
  <?php if (!$activeToken): ?>
  <div class="alert alert-warning py-2 small mb-2">
    <i class="bi bi-exclamation-triangle me-1"></i>
    No active token yet — generate one above to see ready-to-use URLs.
  </div>
  <?php endif; ?>
<?php
$tok = $activeToken ?: 'YOUR_TOKEN';
$urls = [
    ['sync',       'Punch sync — every 15–30 min'],
    ['attendance', 'Daily attendance — 01:00'],
    ['monthly',    'Monthly rollup — 1st of month 02:00 (auto-targets prev month)'],
    ['payroll',    'Payroll — 2nd of month 02:30'],
];
?>
  <div class="d-flex flex-column gap-2 mb-3">
  <?php foreach ($urls as [$step, $label]): ?>
    <?php $url = $triggerBase . '?step=' . $step . '&token=' . $tok; ?>
    <div>
      <div class="text-muted small mb-1"><?= $label ?></div>
      <div class="d-flex align-items-center gap-2">
        <code class="flex-grow-1 d-block user-select-all"
              style="background:var(--bg);border:1px solid var(--border);border-radius:7px;padding:6px 10px;font-size:.82rem;word-break:break-all">
          <?= htmlspecialchars($url) ?>
        </code>
        <button class="btn btn-outline-secondary btn-sm flex-shrink-0"
                onclick="navigator.clipboard.writeText('<?= htmlspecialchars($url) ?>');this.innerHTML='<i class=\'bi bi-check-lg\'></i>'"
                title="Copy URL"><i class="bi bi-clipboard"></i></button>
      </div>
    </div>
  <?php endforeach; ?>
  </div>

  <!-- Response format -->
  <h6 class="fw-semibold mb-2">Response (JSON)</h6>
  <pre class="cb mb-3">{
  <span class="str">"ok"</span>: <span class="kw">true</span>,
  <span class="str">"step"</span>: <span class="str">"sync"</span>,
  <span class="str">"rows"</span>: <span class="num">1420</span>,
  <span class="str">"detail"</span>: { <span class="str">"1"</span>: <span class="num">820</span>, <span class="str">"2"</span>: <span class="num">600</span> },
  <span class="str">"elapsed"</span>: <span class="str">"3.42s"</span>
}</pre>

  <!-- cron-job.org setup guide -->
  <h6 class="fw-semibold mb-2">cron-job.org Setup</h6>
  <ol class="text-muted" style="line-height:2">
    <li>Sign in at <a href="https://cron-job.org" target="_blank">cron-job.org</a> → <strong>Cronjobs → Create cronjob</strong></li>
    <li>Paste the trigger URL above into the <strong>URL</strong> field</li>
    <li>Set <strong>Schedule</strong> (e.g. every 30 min for sync, daily 01:00 for attendance)</li>
    <li>Under <strong>Advanced → Request headers</strong>, optionally add <code>X-Cron-Token: YOUR_TOKEN</code> instead of the URL param for cleaner logs</li>
    <li>Enable <strong>Save responses</strong> to keep a log of each run</li>
    <li>Expected HTTP response: <span class="pill pill-green">200</span> with <code>"ok":true</code></li>
  </ol>

  <div class="alert alert-warning py-2 small mb-0">
    <i class="bi bi-exclamation-triangle me-1"></i>
    <strong>Timeout:</strong> cron-job.org free tier times out at <strong>30 s</strong>.
    <code>sync</code> and <code>attendance</code> are fast. For <code>monthly</code> / <code>payroll</code>
    on large datasets, use the paid tier (300 s) or run via local Task Scheduler instead.
  </div>
</div>

<!-- ── Service Files ─────────────────────────────────────────────────── -->
<div class="doc-section">
  <h5><i class="bi bi-folder2-open me-2"></i>Service Files</h5>
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <table class="table table-sm table-bordered tbl-doc mb-0">
        <thead class="table-light">
          <tr><th>File</th><th>Purpose</th></tr>
        </thead>
        <tbody>
          <tr><td><code>services/ShardManager.php</code></td><td>Creates <code>tblX_YYMM</code> tables on demand; caches existence check per process</td></tr>
          <tr><td><code>services/PunchSyncService.php</code></td><td>Resolves EnrollId → CompanyId + EmpCode; batch-inserts to tblPunchLog</td></tr>
          <tr><td><code>services/AttendanceProcessor.php</code></td><td>Applies shift rules + corrections; writes daily attendance shard</td></tr>
          <tr><td><code>services/MonthlyProcessor.php</code></td><td>Aggregates daily rows into D01–D31 columns + JSON DayData</td></tr>
          <tr><td><code>services/PayRollProcessor.php</code></td><td>Pro-rates salary, computes PF/ESI/OT, writes draft payroll shard</td></tr>
          <tr><td><code>cron/process_pipeline.php</code></td><td>CLI entry point — dispatches any stage for any company + month</td></tr>
          <tr><td><code>cron/run_monthly.php</code></td><td>Wrapper for Task Scheduler — auto-computes previous month's YYMM</td></tr>
          <tr><td><code>cron/trigger.php</code></td><td>HTTP endpoint for cron-job.org — token-authenticated, returns JSON</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

</div>
<?php
$tok     = htmlspecialchars($activeToken ?? '');
$baseUrl = BASE_URL;
$extraJs = <<<JS
<script>
(function(){
  const TOKEN   = '{$tok}';
  const BASE    = '{$baseUrl}';
  const steps   = ['sync','attendance','monthly','payroll'];

  if (!TOKEN) return; // no token — buttons aren't rendered

  document.querySelectorAll('.run-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
      const step = this.dataset.step;
      const co   = document.getElementById('runCompany')?.value || '0';
      const ym   = (document.getElementById('runYm')?.value || '').trim();

      // UI: lock buttons, show panel
      document.querySelectorAll('.run-btn').forEach(b => { b.disabled = true; });
      const panel  = document.getElementById('runPanel');
      const badge  = document.getElementById('runStepBadge');
      const status = document.getElementById('runStatusMsg');
      const out    = document.getElementById('runOutput');
      const elapsed = document.getElementById('runElapsed');
      panel.classList.remove('d-none');
      badge.textContent  = step;
      status.innerHTML   = '<span class="text-muted"><span class="spinner-border spinner-border-sm me-1"></span>Running…</span>';
      out.textContent    = '';
      elapsed.textContent = '';

      const runStep = async (s) => {
        let url = BASE + '/cron/trigger.php?step=' + s + '&token=' + encodeURIComponent(TOKEN) + '&company=' + co;
        if (ym) url += '&ym=' + encodeURIComponent(ym);
        const r = await fetch(url);
        return r.json();
      };

      try {
        let results = [];
        if (step === 'all') {
          for (const s of steps) {
            out.textContent += '→ Running ' + s + '…\\n';
            const d = await runStep(s);
            out.textContent += JSON.stringify(d, null, 2) + '\\n\\n';
          }
          status.innerHTML = '<span class="text-success fw-semibold">✓ All stages complete</span>';
        } else {
          const data = await runStep(step);
          out.textContent = JSON.stringify(data, null, 2);
          if (data.ok) {
            status.innerHTML = '<span class="text-success fw-semibold">✓ Done — ' + (data.rows ?? 0) + ' rows</span>';
            elapsed.textContent = data.elapsed || '';
          } else {
            status.innerHTML = '<span class="text-danger fw-semibold">✗ ' + (data.error || 'Error') + '</span>';
          }
        }
      } catch(e) {
        status.innerHTML = '<span class="text-danger fw-semibold">✗ Request failed</span>';
        out.textContent  = e.toString();
      } finally {
        document.querySelectorAll('.run-btn').forEach(b => { b.disabled = false; });
      }
    });
  });
})();
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php'; ?>
