<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/mailer.php';
requireAdmin();

$db   = getDb();
$user = currentUser();
$defs = require __DIR__ . '/definitions.php';

// ── Auto-create tables ────────────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS tblEmailSmtp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    CompanyId INT NOT NULL,
    FromName VARCHAR(255) DEFAULT '',
    FromEmail VARCHAR(255) DEFAULT '',
    SmtpHost VARCHAR(255) DEFAULT '',
    SmtpPort SMALLINT UNSIGNED DEFAULT 587,
    Encryption ENUM('tls','ssl','none') DEFAULT 'tls',
    SmtpUser VARCHAR(255) DEFAULT '',
    SmtpPass VARCHAR(255) DEFAULT '',
    CronSecret VARCHAR(64) DEFAULT '',
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_co (CompanyId)
)");

$db->exec("CREATE TABLE IF NOT EXISTS tblEmailNotification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    CompanyId INT NOT NULL,
    NotifKey VARCHAR(100) NOT NULL,
    IsEnabled TINYINT(1) DEFAULT 0,
    SendTime VARCHAR(5) DEFAULT '08:00',
    SendDay VARCHAR(20) DEFAULT '',
    Recipients TEXT DEFAULT NULL,
    LastRunAt DATETIME DEFAULT NULL,
    UNIQUE KEY uq_co_key (CompanyId, NotifKey)
)");

$db->exec("CREATE TABLE IF NOT EXISTS tblEmailLog (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    CompanyId INT NOT NULL,
    NotifKey VARCHAR(100) DEFAULT '',
    ToEmail VARCHAR(500) DEFAULT '',
    Subject VARCHAR(500) DEFAULT '',
    Status ENUM('sent','failed') DEFAULT 'sent',
    ErrorMsg TEXT DEFAULT NULL,
    SentAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_co_time (CompanyId, SentAt)
)");

// ── Company list ──────────────────────────────────────────────────────────
if ($user['role'] === 'superadmin') {
    $companiesDd = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $s = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $s->execute([$user['id']]);
    $companiesDd = $s->fetchAll();
}
$fCompany = (int)($_REQUEST['company'] ?? ($companiesDd[0]['id'] ?? 0));
if ($fCompany && $user['role'] === 'admin') {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$fCompany, $user['id']]);
    if (!$chk->fetch()) $fCompany = 0;
}

$activeTab = $_GET['tab'] ?? 'smtp';
if ($activeTab === 'cron' && $user['role'] !== 'superadmin') $activeTab = 'smtp';

// ── Helper: load SMTP row ─────────────────────────────────────────────────
function loadSmtp(PDO $db, int $co): array {
    $s = $db->prepare("SELECT * FROM tblEmailSmtp WHERE CompanyId=?");
    $s->execute([$co]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: [];
}

// ── POST handlers ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fCompany) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    // ── Save SMTP ─────────────────────────────────────────────────────────
    if ($action === 'save_smtp') {
        $enc = in_array($_POST['encryption'] ?? '', ['tls','ssl','none'])
             ? $_POST['encryption'] : 'tls';

        // Preserve existing CronSecret and password if blanked
        $existing   = loadSmtp($db, $fCompany);
        $cronSecret = $existing['CronSecret'] ?: bin2hex(random_bytes(16));
        $newPass    = trim($_POST['smtp_pass'] ?? '');
        if ($newPass === '') $newPass = $existing['SmtpPass'] ?? '';

        $db->prepare(
            "INSERT INTO tblEmailSmtp
               (CompanyId, FromName, FromEmail, SmtpHost, SmtpPort, Encryption, SmtpUser, SmtpPass, CronSecret)
             VALUES (?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               FromName=VALUES(FromName), FromEmail=VALUES(FromEmail),
               SmtpHost=VALUES(SmtpHost), SmtpPort=VALUES(SmtpPort),
               Encryption=VALUES(Encryption), SmtpUser=VALUES(SmtpUser),
               SmtpPass=VALUES(SmtpPass)"
        )->execute([
            $fCompany,
            trim($_POST['from_name']  ?? ''),
            trim($_POST['from_email'] ?? ''),
            trim($_POST['smtp_host']  ?? ''),
            (int)($_POST['smtp_port'] ?? 587),
            $enc,
            trim($_POST['smtp_user']  ?? ''),
            $newPass,
            $cronSecret,
        ]);
        $_SESSION['flash'] = 'SMTP settings saved.';
        header("Location: index.php?company={$fCompany}&tab=smtp"); exit;
    }

    // ── Test email ────────────────────────────────────────────────────────
    if ($action === 'test_email') {
        $testTo = trim($_POST['test_email_to'] ?? '');
        if (!$testTo || !filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash']      = 'Enter a valid email address for the test.';
            $_SESSION['flash_type'] = 'warning';
        } else {
            $smtp = loadSmtp($db, $fCompany);
            $cfg  = [
                'host'       => $smtp['SmtpHost'] ?? '',
                'port'       => $smtp['SmtpPort'] ?? 587,
                'encryption' => $smtp['Encryption'] ?? 'tls',
                'user'       => $smtp['SmtpUser'] ?? '',
                'pass'       => $smtp['SmtpPass'] ?? '',
                'from_email' => $smtp['FromEmail'] ?? '',
                'from_name'  => $smtp['FromName']  ?? '',
            ];
            $html = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:32px">
                     <h2 style="color:#0071e3">HRMS — Test Email</h2>
                     <p>This is a test email confirming your SMTP configuration is working correctly.</p>
                     <p style="color:#6e6e73;font-size:12px">Sent from: ' . htmlspecialchars($cfg['from_email']) . '</p>
                     </div>';
            $res = SimpleMailer::send($cfg, $testTo, 'HRMS — Test Email', $html);
            if ($res['ok']) {
                $_SESSION['flash'] = "Test email sent to {$testTo} successfully.";
            } else {
                $_SESSION['flash']      = 'Test email failed: ' . $res['error'];
                $_SESSION['flash_type'] = 'danger';
            }
        }
        header("Location: index.php?company={$fCompany}&tab=smtp"); exit;
    }

    // ── Save notification toggles ─────────────────────────────────────────
    if ($action === 'save_notifications') {
        $notifPost = $_POST['notif'] ?? [];
        foreach ($defs['groups'] as $group) {
            foreach ($group['items'] as $key => $def) {
                $enabled  = !empty($notifPost[$key]['enabled']) ? 1 : 0;
                $sendTime = preg_replace('/[^0-9:]/', '', $notifPost[$key]['send_time'] ?? '08:00');
                $sendDay  = preg_replace('/[^0-9a-z]/', '', strtolower($notifPost[$key]['send_day'] ?? ''));
                $db->prepare(
                    "INSERT INTO tblEmailNotification (CompanyId, NotifKey, IsEnabled, SendTime, SendDay)
                     VALUES (?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE IsEnabled=VALUES(IsEnabled), SendTime=VALUES(SendTime), SendDay=VALUES(SendDay)"
                )->execute([$fCompany, $key, $enabled, $sendTime ?: '08:00', $sendDay]);
            }
        }
        $_SESSION['flash'] = 'Notification settings saved.';
        header("Location: index.php?company={$fCompany}&tab=notifications"); exit;
    }

    // ── Save scheduled reports ────────────────────────────────────────────
    if ($action === 'save_reports') {
        $rptPost = $_POST['report'] ?? [];
        foreach ($defs['scheduled_reports'] as $key => $def) {
            $enabled    = !empty($rptPost[$key]['enabled']) ? 1 : 0;
            $sendTime   = preg_replace('/[^0-9:]/', '', $rptPost[$key]['send_time'] ?? $def['default_time']);
            $sendDay    = preg_replace('/[^0-9a-z]/', '', strtolower($rptPost[$key]['send_day'] ?? ($def['default_day'] ?? '')));
            $recipients = trim($rptPost[$key]['recipients'] ?? '');
            $db->prepare(
                "INSERT INTO tblEmailNotification (CompanyId, NotifKey, IsEnabled, SendTime, SendDay, Recipients)
                 VALUES (?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE IsEnabled=VALUES(IsEnabled), SendTime=VALUES(SendTime),
                   SendDay=VALUES(SendDay), Recipients=VALUES(Recipients)"
            )->execute([$fCompany, $key, $enabled, $sendTime ?: $def['default_time'], $sendDay, $recipients ?: null]);
        }
        $_SESSION['flash'] = 'Scheduled report settings saved.';
        header("Location: index.php?company={$fCompany}&tab=reports"); exit;
    }
}

// ── Flash ─────────────────────────────────────────────────────────────────
$flashMsg  = '';
$flashType = 'success';
if (!empty($_SESSION['flash'])) {
    $flashMsg  = $_SESSION['flash'];
    $flashType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash'], $_SESSION['flash_type']);
}

// ── Load data ─────────────────────────────────────────────────────────────
$smtp = $fCompany ? loadSmtp($db, $fCompany) : [];

$notifSettings = [];
if ($fCompany) {
    $s = $db->prepare("SELECT NotifKey, IsEnabled, SendTime, SendDay, Recipients, LastRunAt FROM tblEmailNotification WHERE CompanyId=?");
    $s->execute([$fCompany]);
    foreach ($s->fetchAll() as $row) $notifSettings[$row['NotifKey']] = $row;
}

$emailLog = [];
if ($fCompany) {
    $s = $db->prepare("SELECT * FROM tblEmailLog WHERE CompanyId=? ORDER BY SentAt DESC LIMIT 100");
    $s->execute([$fCompany]);
    $emailLog = $s->fetchAll();
}

$daysOfWeek    = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
$pageTitle     = 'Email Notifications';
$activePage    = 'notifications';
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($flashMsg): ?>
<div class="alert alert-<?= $flashType ?> py-2"><?= htmlspecialchars($flashMsg) ?></div>
<?php endif; ?>

<!-- Company selector -->
<?php if (count($companiesDd) > 1): ?>
<form method="GET" class="mb-3 d-flex align-items-center gap-2">
  <label class="form-label mb-0 fw-semibold">Company</label>
  <select name="company" class="form-select form-select-sm" style="max-width:220px" onchange="this.form.submit()">
    <?php foreach ($companiesDd as $c): ?>
    <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
</form>
<?php endif; ?>

<?php if ($fCompany && !empty($smtp['CronSecret'])): ?>
<div class="d-flex align-items-center gap-2 mb-2 p-2 rounded" style="background:rgba(0,113,227,.07);border:1px solid rgba(0,113,227,.18)">
  <i class="bi bi-send-check text-primary"></i>
  <span class="small fw-semibold flex-grow-1">Manual Email Trigger</span>
  <button class="btn btn-sm btn-primary" onclick="triggerNow(false)">
    <i class="bi bi-send me-1"></i>Send Due Now
  </button>
  <button class="btn btn-sm btn-outline-danger" onclick="triggerNow(true)"
          title="Force-sends all enabled notifications — may cause duplicates">
    <i class="bi bi-lightning me-1"></i>Force Send All
  </button>
</div>
<!-- Output panel -->
<div id="triggerOutput" class="d-none mb-2">
  <div class="d-flex align-items-center gap-2 mb-1">
    <span class="fw-semibold small">Output</span>
    <span id="triggerSpinner" class="spinner-border spinner-border-sm text-primary d-none"></span>
  </div>
  <pre id="triggerPre" class="bg-dark text-light p-3 rounded mb-0"
       style="font-size:12px;max-height:300px;overflow-y:auto;white-space:pre-wrap"></pre>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white">
    <ul class="nav nav-tabs card-header-tabs" id="notifTabs">
      <?php
      $tabs = ['smtp'=>'SMTP','notifications'=>'Notifications','reports'=>'Scheduled Reports','log'=>'Email Log'];
      if ($user['role'] === 'superadmin') $tabs = array_merge(['smtp'=>'SMTP','notifications'=>'Notifications','reports'=>'Scheduled Reports','cron'=>'Cron Setup'], ['log'=>'Email Log']);
      foreach ($tabs as $k => $label):
      ?>
      <li class="nav-item">
        <a class="nav-link <?= $activeTab===$k?'active':'' ?>"
           href="?company=<?= $fCompany ?>&tab=<?= $k ?>">
          <?= $label ?>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="card-body">

  <?php if (!$fCompany): ?>
    <div class="alert alert-warning mb-0">Select a company to configure email notifications.</div>
  <?php endif; ?>

  <!-- ── SMTP Tab ───────────────────────────────────────────────────────── -->
  <?php if ($activeTab === 'smtp' && $fCompany): ?>
  <form method="POST" action="index.php?company=<?= $fCompany ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_smtp">
    <div class="row g-3 mb-4">
      <div class="col-sm-6">
        <label class="form-label fw-semibold">From Name</label>
        <input type="text" name="from_name" class="form-control"
               value="<?= htmlspecialchars($smtp['FromName'] ?? '') ?>"
               placeholder="e.g. HR Team">
      </div>
      <div class="col-sm-6">
        <label class="form-label fw-semibold">From Email <span class="text-danger">*</span></label>
        <input type="email" name="from_email" class="form-control"
               value="<?= htmlspecialchars($smtp['FromEmail'] ?? '') ?>"
               placeholder="noreply@yourdomain.com">
      </div>
    </div>
    <hr class="my-3">
    <div class="fw-semibold text-muted small text-uppercase mb-3">SMTP Server</div>
    <div class="row g-3 mb-4">
      <div class="col-sm-5">
        <label class="form-label fw-semibold">SMTP Host</label>
        <input type="text" name="smtp_host" class="form-control"
               value="<?= htmlspecialchars($smtp['SmtpHost'] ?? '') ?>"
               placeholder="smtp.gmail.com">
      </div>
      <div class="col-sm-3">
        <label class="form-label fw-semibold">Port</label>
        <input type="number" name="smtp_port" class="form-control"
               value="<?= (int)($smtp['SmtpPort'] ?? 587) ?>"
               min="1" max="65535">
      </div>
      <div class="col-sm-4">
        <label class="form-label fw-semibold">Encryption</label>
        <select name="encryption" class="form-select">
          <?php foreach (['tls'=>'TLS (STARTTLS — port 587)','ssl'=>'SSL (port 465)','none'=>'None (port 25)'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($smtp['Encryption'] ?? 'tls')===$v?'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-6">
        <label class="form-label fw-semibold">SMTP Username</label>
        <input type="text" name="smtp_user" class="form-control" autocomplete="off"
               value="<?= htmlspecialchars($smtp['SmtpUser'] ?? '') ?>"
               placeholder="user@gmail.com">
      </div>
      <div class="col-sm-6">
        <label class="form-label fw-semibold">SMTP Password</label>
        <input type="password" name="smtp_pass" class="form-control" autocomplete="new-password"
               placeholder="<?= !empty($smtp['SmtpPass']) ? '••••••••  (leave blank to keep)' : 'Enter password' ?>">
        <?php if (!empty($smtp['SmtpPass'])): ?>
        <div class="form-text text-success"><i class="bi bi-check-circle me-1"></i>Password is saved. Leave blank to keep it.</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i>Save SMTP Settings</button>
    </div>
  </form>

  <!-- Test email sub-form -->
  <?php if (!empty($smtp['FromEmail'])): ?>
  <hr class="my-4">
  <form method="POST" action="index.php?company=<?= $fCompany ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="test_email">
    <div class="fw-semibold mb-2">Send Test Email</div>
    <div class="d-flex gap-2 align-items-end" style="max-width:400px">
      <div class="flex-1" style="flex:1">
        <input type="email" name="test_email_to" class="form-control"
               placeholder="recipient@example.com" required>
      </div>
      <button type="submit" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-send me-1"></i>Send Test
      </button>
    </div>
  </form>
  <?php else: ?>
  <div class="alert alert-info mt-4 mb-0">Save SMTP settings first to enable the test email feature.</div>
  <?php endif; ?>

  <?php if ($user['role'] === 'superadmin'): ?>
  <hr class="my-4">
  <div class="fw-semibold mb-1">Master Cron URL <span class="badge bg-primary ms-1" style="font-size:10px">All Companies</span></div>
  <code class="d-block p-2 bg-light rounded" style="word-break:break-all;font-size:12px">
    <?= htmlspecialchars(BASE_URL . '/cron/send_emails.php?secret=' . CRON_MASTER_SECRET) ?>
  </code>
  <div class="form-text">One cron job processes all companies automatically. See the <a href="?company=<?= $fCompany ?>&tab=cron">Cron Setup</a> tab for full instructions.</div>
  <?php endif; ?>

  <!-- ── Notifications Tab ──────────────────────────────────────────────── -->
  <?php elseif ($activeTab === 'notifications' && $fCompany): ?>
  <form method="POST" action="index.php?company=<?= $fCompany ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_notifications">
    <div class="accordion" id="notifAccordion">
    <?php foreach ($defs['groups'] as $gKey => $group): ?>
    <?php $gId = 'grp_' . $gKey; ?>
    <div class="accordion-item border mb-2 rounded overflow-hidden">
      <h2 class="accordion-header">
        <button class="accordion-button fw-semibold" type="button"
                data-bs-toggle="collapse" data-bs-target="#<?= $gId ?>">
          <i class="bi <?= $group['icon'] ?> me-2 text-primary"></i>
          <?= htmlspecialchars($group['label']) ?> Notifications
        </button>
      </h2>
      <div id="<?= $gId ?>" class="accordion-collapse collapse show">
        <div class="accordion-body p-0">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:36px"></th>
                <th>Notification</th>
                <th>Trigger</th>
                <th>Send At</th>
                <th style="width:80px">Day</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($group['items'] as $key => $def):
              $row     = $notifSettings[$key] ?? [];
              $enabled = (bool)($row['IsEnabled'] ?? 0);
              $time    = $row['SendTime'] ?? '08:00';
              $day     = $row['SendDay']  ?? '';
              $isEvent = $def['schedule'] === 'event';
            ?>
            <tr>
              <td class="text-center align-middle">
                <div class="form-check form-switch mb-0">
                  <input class="form-check-input" type="checkbox" role="switch"
                         name="notif[<?= $key ?>][enabled]" value="1" <?= $enabled?'checked':'' ?>>
                </div>
              </td>
              <td class="align-middle small fw-semibold"><?= htmlspecialchars($def['label']) ?></td>
              <td class="align-middle">
                <?php if ($isEvent): ?>
                  <span class="badge bg-secondary">Event</span>
                <?php elseif ($def['schedule']==='daily'): ?>
                  <span class="badge bg-primary">Daily</span>
                <?php elseif ($def['schedule']==='monthly'): ?>
                  <span class="badge" style="background:#7c3aed">Monthly</span>
                <?php endif; ?>
              </td>
              <td class="align-middle">
                <?php if (!$isEvent): ?>
                <input type="time" name="notif[<?= $key ?>][send_time]"
                       class="form-control form-control-sm" style="width:110px"
                       value="<?= htmlspecialchars($time) ?>">
                <?php else: ?>
                <span class="text-muted small">Triggered by action</span>
                <?php endif; ?>
              </td>
              <td class="align-middle">
                <?php if ($def['schedule']==='monthly'): ?>
                <input type="number" name="notif[<?= $key ?>][send_day]"
                       class="form-control form-control-sm" style="width:70px"
                       min="1" max="28" placeholder="1"
                       value="<?= htmlspecialchars($day ?: '1') ?>"
                       title="Day of month (1–28)">
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
    <div class="mt-3">
      <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i>Save Notification Settings</button>
    </div>
  </form>

  <!-- ── Scheduled Reports Tab ──────────────────────────────────────────── -->
  <?php elseif ($activeTab === 'reports' && $fCompany): ?>
  <p class="text-muted small mb-3">
    Scheduled reports are emailed to the specified recipients automatically.
    Trigger using the <a href="?company=<?= $fCompany ?>&tab=cron">Cron URL</a>.
  </p>
  <form method="POST" action="index.php?company=<?= $fCompany ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_reports">
    <table class="table table-sm">
      <thead class="table-light">
        <tr>
          <th style="width:36px"></th>
          <th>Report</th>
          <th>Frequency</th>
          <th>Day</th>
          <th>Time</th>
          <th>Recipients (comma-separated emails)</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($defs['scheduled_reports'] as $key => $def):
        $row        = $notifSettings[$key] ?? [];
        $enabled    = (bool)($row['IsEnabled'] ?? 0);
        $sendTime   = $row['SendTime']   ?? $def['default_time'];
        $sendDay    = $row['SendDay']    ?? ($def['default_day'] ?? '');
        $recipients = $row['Recipients'] ?? '';
        $freq       = $def['frequency']; // daily | weekly | monthly
      ?>
      <tr>
        <td class="text-center align-middle">
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch"
                   name="report[<?= $key ?>][enabled]" value="1" <?= $enabled?'checked':'' ?>>
          </div>
        </td>
        <td class="align-middle small fw-semibold"><?= htmlspecialchars($def['label']) ?></td>
        <td class="align-middle">
          <?php $bcolor = $freq==='daily'?'bg-primary':($freq==='weekly'?'bg-success':'bg-secondary'); ?>
          <span class="badge <?= $bcolor ?>"><?= ucfirst($freq) ?></span>
        </td>
        <td class="align-middle">
          <?php if ($freq === 'weekly'): ?>
          <select name="report[<?= $key ?>][send_day]" class="form-select form-select-sm" style="width:110px">
            <?php foreach ($daysOfWeek as $d): ?>
            <option value="<?= $d ?>" <?= $sendDay===$d?'selected':'' ?>><?= ucfirst($d) ?></option>
            <?php endforeach; ?>
          </select>
          <?php elseif ($freq === 'monthly'): ?>
          <input type="number" name="report[<?= $key ?>][send_day]"
                 class="form-control form-control-sm" style="width:70px"
                 min="1" max="28" value="<?= htmlspecialchars($sendDay ?: '1') ?>"
                 title="Day of month (1–28)">
          <?php else: ?>
          <span class="text-muted small">Every day</span>
          <?php endif; ?>
        </td>
        <td class="align-middle">
          <input type="time" name="report[<?= $key ?>][send_time]"
                 class="form-control form-control-sm" style="width:110px"
                 value="<?= htmlspecialchars($sendTime) ?>">
        </td>
        <td class="align-middle">
          <input type="text" name="report[<?= $key ?>][recipients]"
                 class="form-control form-control-sm"
                 placeholder="hr@company.com, manager@company.com"
                 value="<?= htmlspecialchars($recipients) ?>">
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <button type="submit" class="btn btn-primary"><i class="bi bi-floppy me-1"></i>Save Report Schedules</button>
  </form>

  <!-- ── Cron Setup Tab ────────────────────────────────────────────────── -->
  <?php elseif ($activeTab === 'cron' && $user['role'] === 'superadmin'): ?>
  <?php $masterCronUrl = BASE_URL . '/cron/send_emails.php?secret=' . CRON_MASTER_SECRET; ?>

  <div class="row g-4">
    <div class="col-lg-8">
      <h6 class="fw-semibold mb-3">Setting Up Automated Email Delivery</h6>

      <div class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-1">
          <span class="fw-semibold small text-uppercase text-muted">Master Cron URL</span>
          <span class="badge bg-primary" style="font-size:10px">All Companies</span>
        </div>
        <code class="d-block p-3 bg-light rounded" style="word-break:break-all;font-size:12px;line-height:1.7">
          <?= htmlspecialchars($masterCronUrl) ?>
        </code>
        <div class="form-text">A single cron job automatically processes <strong>all companies</strong>. No per-company setup needed.</div>
      </div>

      <div class="mb-4">
        <div class="fw-semibold small text-uppercase text-muted mb-2">hPanel / cPanel Cron Job</div>
        <pre class="bg-dark text-light p-3 rounded" style="font-size:12px">*/30 * * * * curl -s "<?= htmlspecialchars($masterCronUrl) ?>" &gt;/dev/null 2&gt;&amp;1</pre>
        <div class="form-text">Add this single entry in hPanel → Advanced → Cron Jobs. Runs every 30 minutes, checks which notifications are due, skips duplicates.</div>
      </div>

      <div class="mb-4">
        <div class="fw-semibold small text-uppercase text-muted mb-2">Force Send All (manual test)</div>
        <pre class="bg-dark text-light p-3 rounded" style="font-size:12px"><?= htmlspecialchars($masterCronUrl . '&force=1') ?></pre>
        <div class="form-text">Appending <code>&force=1</code> bypasses the schedule check and fires all enabled notifications immediately — useful for testing.</div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="alert alert-success" style="font-size:13px">
        <div class="fw-semibold mb-2"><i class="bi bi-check-circle me-1"></i> One Cron for All</div>
        <p class="mb-0">You only need to add <strong>one cron job</strong>. The master secret automatically discovers all companies with SMTP configured and processes each one.</p>
      </div>
      <div class="alert alert-info" style="font-size:13px">
        <div class="fw-semibold mb-2"><i class="bi bi-info-circle me-1"></i> How It Works</div>
        <ul class="mb-0 ps-3">
          <li class="mb-1">Scheduled notifications (daily summaries, birthday greetings, reports) are dispatched by the cron.</li>
          <li class="mb-1">The cron checks <code>LastRunAt</code> to avoid duplicates. Safe to run every 30 minutes.</li>
          <li class="mb-1">Per-company secrets still work if you prefer targeted triggers.</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- ── Email Log Tab ─────────────────────────────────────────────────── -->
  <?php elseif ($activeTab === 'log' && $fCompany): ?>
  <?php if (empty($emailLog)): ?>
  <div class="text-center text-muted py-5">No emails sent yet.</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm" id="logTable">
      <thead class="table-light">
        <tr>
          <th>Sent At</th>
          <th>Type</th>
          <th>Recipient</th>
          <th>Subject</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($emailLog as $log): ?>
      <tr>
        <td class="text-nowrap small"><?= $log['SentAt'] ?></td>
        <td><code style="font-size:11px"><?= htmlspecialchars($log['NotifKey']) ?></code></td>
        <td class="small"><?= htmlspecialchars($log['ToEmail']) ?></td>
        <td class="small"><?= htmlspecialchars($log['Subject']) ?></td>
        <td>
          <?php if ($log['Status'] === 'sent'): ?>
          <span class="badge bg-success">Sent</span>
          <?php else: ?>
          <span class="badge bg-danger" title="<?= htmlspecialchars($log['ErrorMsg'] ?? '') ?>">Failed</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php endif; ?>

  </div><!-- /card-body -->
</div><!-- /card -->

<?php
$cronUrl   = $fCompany && !empty($smtp['CronSecret'])
           ? BASE_URL . '/cron/send_emails.php?company=' . $fCompany . '&secret=' . $smtp['CronSecret']
           : '';
$extraJs = '<script>
(function() {
  var logTable = document.getElementById(\'logTable\');
  if (logTable && typeof $.fn.DataTable !== \'undefined\') {
    $(logTable).DataTable({ order: [[0,\'desc\']], pageLength: 25 });
  }

  var CRON_URL = ' . json_encode($cronUrl) . ';

  window.triggerNow = function(force) {
    if (!CRON_URL) { alert(\'Configure SMTP first.\'); return; }
    if (force && !confirm(\'Force Send will dispatch ALL enabled notifications immediately, even if already sent today.\\nThis may cause duplicate emails.\\n\\nProceed?\')) return;

    var output  = document.getElementById(\'triggerOutput\');
    var pre     = document.getElementById(\'triggerPre\');
    var spinner = document.getElementById(\'triggerSpinner\');
    output.classList.remove(\'d-none\');
    pre.textContent = \'Running...\';
    spinner.classList.remove(\'d-none\');

    var url = CRON_URL + (force ? \'&force=1\' : \'\');
    fetch(url)
      .then(function(r) { return r.text(); })
      .then(function(txt) {
        pre.textContent = txt || \'(no output)\';
        spinner.classList.add(\'d-none\');
      })
      .catch(function(e) {
        pre.textContent = \'Error: \' + e.message;
        spinner.classList.add(\'d-none\');
      });
  };
})();
</script>';
require_once __DIR__ . '/../../includes/footer.php';
