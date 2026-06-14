<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

// Ensure table exists
$db->exec("CREATE TABLE IF NOT EXISTS tblSettings (
    id            INT PRIMARY KEY AUTO_INCREMENT,
    CompanyId     INT         NOT NULL DEFAULT 0,
    SettingKey    VARCHAR(100) NOT NULL,
    SettingValue  VARCHAR(500) NOT NULL DEFAULT '',
    UpdatedAt     TIMESTAMP   DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cs (CompanyId, SettingKey)
)");

// ── Company scope ──────────────────────────────────────────────────────────────
if ($user['role'] === 'superadmin') {
    $companiesDd = []; // superadmin edits global (CompanyId = 0)
    $fCompany    = 0;
} else {
    $cStmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $cStmt->execute([$user['id']]);
    $companiesDd = $cStmt->fetchAll();
    $fCompany    = (int)($_GET['company'] ?? ($companiesDd[0]['id'] ?? 0));
    if ($fCompany) {
        $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $chk->execute([$fCompany, $user['id']]);
        if (!$chk->fetch()) $fCompany = 0;
    }
}

// ── Save ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $saveFor = (int)($_POST['company_id'] ?? 0);
    if ($user['role'] !== 'superadmin') {
        $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $chk->execute([$saveFor, $user['id']]);
        if (!$chk->fetch()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => ['Access denied.']]);
            exit;
        }
    }
    $keys = ['show_holiday_punches','show_leave_punches','show_weekoff_punches','show_before_doj','show_after_dol'];
    $ins  = $db->prepare("INSERT INTO tblSettings (CompanyId,SettingKey,SettingValue) VALUES (?,?,?) ON DUPLICATE KEY UPDATE SettingValue=VALUES(SettingValue)");
    foreach ($keys as $k) {
        $ins->execute([$saveFor, $k, isset($_POST[$k]) ? '1' : '0']);
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Settings saved.']);
    exit;
}

// ── Load current settings ─────────────────────────────────────────────────────
function loadSettings(PDO $db, int $companyId): array {
    $stmt = $db->prepare("SELECT SettingKey, SettingValue FROM tblSettings WHERE CompanyId=?");
    $stmt->execute([$companyId]);
    $s = [];
    foreach ($stmt->fetchAll() as $r) $s[$r['SettingKey']] = $r['SettingValue'];
    return $s;
}

$settings = loadSettings($db, $fCompany);
// Fall back to global for any key not set at company level
if ($fCompany) {
    $global = loadSettings($db, 0);
    foreach ($global as $k => $v) {
        if (!array_key_exists($k, $settings)) $settings[$k] = $v;
    }
}

function checked(array $s, string $k): string {
    return !empty($s[$k]) ? 'checked' : '';
}

$pageTitle  = 'Settings';
$activePage = 'settings';
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($user['role'] !== 'superadmin' && count($companiesDd) > 1): ?>
<div class="mb-3">
  <form method="GET" class="d-flex gap-2 align-items-center">
    <label class="form-label mb-0 fw-semibold">Company</label>
    <select name="company" class="form-select form-select-sm" style="width:220px" onchange="this.form.submit()">
      <?php foreach ($companiesDd as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $fCompany == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['Name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>
<?php endif; ?>

<?php if (!$fCompany && $user['role'] !== 'superadmin'): ?>
<div class="alert alert-info">Select a company to configure its settings.</div>
<?php else: ?>

<form method="POST" data-ajax action="index.php<?= $fCompany ? '?company='.$fCompany : '' ?>">
  <?= csrf_field() ?? '' ?>
  <input type="hidden" name="company_id" value="<?= $fCompany ?>">

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
      <i class="bi bi-toggles text-primary"></i>
      Attendance Settings
      <?php if ($user['role'] === 'superadmin'): ?>
      <span class="badge bg-secondary ms-1">Global Defaults</span>
      <?php elseif ($fCompany): ?>
      <span class="badge bg-primary ms-1"><?= htmlspecialchars($companiesDd[0]['Name'] ?? '') ?></span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-4">
        These settings control how attendance and swipe data is displayed in reports.
        <?php if ($user['role'] === 'superadmin'): ?>
        Global defaults apply to all companies unless overridden at the company level.
        <?php else: ?>
        Company-level settings override global defaults.
        <?php endif; ?>
      </p>

      <div class="row g-4">

        <div class="col-12">
          <div class="d-flex align-items-start gap-3 p-3 border rounded">
            <div class="form-check form-switch mb-0 pt-1">
              <input class="form-check-input" type="checkbox" role="switch"
                     id="show_holiday_punches" name="show_holiday_punches"
                     <?= checked($settings, 'show_holiday_punches') ?>>
            </div>
            <div>
              <label class="form-check-label fw-semibold" for="show_holiday_punches">
                Show Punches on Holidays
              </label>
              <div class="text-muted small mt-1">
                When ON — if an employee swipes on a declared holiday, show the actual punch times (P/HP) instead of the H badge.
              </div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="d-flex align-items-start gap-3 p-3 border rounded">
            <div class="form-check form-switch mb-0 pt-1">
              <input class="form-check-input" type="checkbox" role="switch"
                     id="show_leave_punches" name="show_leave_punches"
                     <?= checked($settings, 'show_leave_punches') ?>>
            </div>
            <div>
              <label class="form-check-label fw-semibold" for="show_leave_punches">
                Show Punches on Leave Days
              </label>
              <div class="text-muted small mt-1">
                When ON — if an employee swipes on a day marked as leave (L/HL), show the actual punch times instead of the leave badge.
              </div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="d-flex align-items-start gap-3 p-3 border rounded">
            <div class="form-check form-switch mb-0 pt-1">
              <input class="form-check-input" type="checkbox" role="switch"
                     id="show_weekoff_punches" name="show_weekoff_punches"
                     <?= checked($settings, 'show_weekoff_punches') ?>>
            </div>
            <div>
              <label class="form-check-label fw-semibold" for="show_weekoff_punches">
                Show Punches on Week Off (Sunday)
              </label>
              <div class="text-muted small mt-1">
                When ON — if an employee swipes on a Sunday, show the actual punch times instead of the S badge.
              </div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="d-flex align-items-start gap-3 p-3 border rounded">
            <div class="form-check form-switch mb-0 pt-1">
              <input class="form-check-input" type="checkbox" role="switch"
                     id="show_before_doj" name="show_before_doj"
                     <?= checked($settings, 'show_before_doj') ?>>
            </div>
            <div>
              <label class="form-check-label fw-semibold" for="show_before_doj">
                Show Punches Before Date of Joining (DOJ)
              </label>
              <div class="text-muted small mt-1">
                When ON — show punch data for dates before the employee's join date. When OFF — those days appear blank.
              </div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="d-flex align-items-start gap-3 p-3 border rounded">
            <div class="form-check form-switch mb-0 pt-1">
              <input class="form-check-input" type="checkbox" role="switch"
                     id="show_after_dol" name="show_after_dol"
                     <?= checked($settings, 'show_after_dol') ?>>
            </div>
            <div>
              <label class="form-check-label fw-semibold" for="show_after_dol">
                Show Punches After Date of Leaving (DOL)
              </label>
              <div class="text-muted small mt-1">
                When ON — show punch data for dates after the employee's date of leaving. When OFF — those days appear blank.
              </div>
            </div>
          </div>
        </div>

      </div><!-- /.row -->
    </div>
    <div class="card-footer bg-white">
      <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Settings</button>
    </div>
  </div>

</form>
<?php endif; ?>

<?php if ($user['role'] === 'superadmin'):
    $db->exec("CREATE TABLE IF NOT EXISTS `tblCronToken` (
        `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `Token`     CHAR(64)     NOT NULL UNIQUE,
        `Label`     VARCHAR(100) NOT NULL DEFAULT '',
        `IsActive`  TINYINT(1)   NOT NULL DEFAULT 1,
        `LastUsed`  DATETIME     DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB");
    $cronTokens = $db->query("SELECT id, Label, IsActive, LastUsed FROM tblCronToken ORDER BY id")->fetchAll();
?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
    <i class="bi bi-clock-history text-primary"></i>
    Cron Token Status
    <span class="badge bg-secondary ms-1">Superadmin</span>
  </div>
  <div class="card-body p-0">
    <?php if (!$cronTokens): ?>
    <p class="text-muted small p-3 mb-0">No cron tokens found. Generate one via <code>cron/trigger.php</code> setup.</p>
    <?php else: ?>
    <table class="table table-sm table-hover mb-0">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Label</th>
          <th>Active</th>
          <th>Last Used</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cronTokens as $ct):
            $lastUsed = $ct['LastUsed'];
            $hoursAgo = $lastUsed ? round((time() - strtotime($lastUsed)) / 3600, 1) : null;
            if (!$lastUsed) {
                $badge = '<span class="badge bg-secondary">Never</span>';
            } elseif ($hoursAgo <= 25) {
                $badge = '<span class="badge bg-success">OK</span>';
            } elseif ($hoursAgo <= 49) {
                $badge = '<span class="badge bg-warning text-dark">Delayed</span>';
            } else {
                $badge = '<span class="badge bg-danger">Stale</span>';
            }
        ?>
        <tr>
          <td class="text-muted"><?= $ct['id'] ?></td>
          <td><?= htmlspecialchars($ct['Label'] ?: '—') ?></td>
          <td><?= $ct['IsActive'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>' ?></td>
          <td class="font-monospace small"><?= $lastUsed ? htmlspecialchars($lastUsed) : '—' ?></td>
          <td><?= $badge ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="text-muted small px-3 pt-2 pb-1 mb-0">
      <i class="bi bi-info-circle me-1"></i>
      Status: <strong>OK</strong> = fired within 25 h &nbsp;|&nbsp;
      <strong>Delayed</strong> = 25–49 h &nbsp;|&nbsp;
      <strong>Stale</strong> = &gt;49 h or never fired.
    </p>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
