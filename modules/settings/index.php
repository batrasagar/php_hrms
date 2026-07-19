<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();
requirePermission('settings.view');

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

/**
 * Every setting key this page manages, with a label and how to render its value.
 * Drives the per-company override panel — add new keys here as well as to the form,
 * or an override on them will be invisible to a superadmin.
 */
function settingCatalog(): array {
    return [
        'show_holiday_punches'  => ['Show punches on holidays',        'bool'],
        'show_leave_punches'    => ['Show punches on leave days',      'bool'],
        'show_weekoff_punches'  => ['Show punches on week offs',       'bool'],
        'show_ot_report'        => ['Show OT column in reports',       'bool'],
        'allow_negative_leave'  => ['Allow negative leave balance',    'bool'],
        'show_before_doj'       => ['Show punches before DOJ',         'bool'],
        'show_after_dol'        => ['Show punches after DOL',          'bool'],
        'ot_before_shift'       => ['OT for early arrival',            'bool'],
        'ot_after_shift'        => ['OT for late departure',           'bool'],
        'ot_manual_only'        => ['Manual OT only',                  'bool'],
        'ot_clamp_out'          => ['Clamp out-punch beyond OT limit', 'bool'],
        'ot_max_hours'          => ['Max OT hours',                    'num'],
        'ot_slabs'              => ['OT rounding slabs',               'json'],
        'wo_deduct_adj_absent'  => ['Deduct week off — absent adjoining day', 'bool'],
        'wo_deduct_low_present' => ['Deduct week off — below min present days', 'bool'],
        'wo_min_present_days'   => ['Min present days per week',       'num'],
    ];
}

/** Render a stored setting value for display in the override panel. */
function settingValueLabel(string $key, ?string $val): string {
    $type = settingCatalog()[$key][1] ?? 'num';
    if ($val === null) return '<span class="text-muted">not set</span>';
    if ($type === 'bool') {
        return !empty($val) && $val !== '0'
            ? '<span class="badge bg-success-subtle text-success border">On</span>'
            : '<span class="badge bg-secondary-subtle text-secondary border">Off</span>';
    }
    if ($type === 'json') {
        $d = json_decode($val, true);
        return is_array($d) ? '<span class="text-muted small">' . count($d) . ' rule(s)</span>'
                            : '<span class="text-muted small">—</span>';
    }
    return '<span class="fw-semibold">' . htmlspecialchars($val) . '</span>';
}

// ── Company scope ──────────────────────────────────────────────────────────────
// Everyone follows the global topbar company switcher. A superadmin can also edit
// the global defaults (CompanyId = 0) via ?scope=global — previously that was the
// ONLY thing a superadmin could edit here, so they could never set a value for one
// company, and any company-level row silently overrode what they saved.
$isSuperSettings = $user['role'] === 'superadmin';
$editGlobal      = $isSuperSettings && (($_GET['scope'] ?? '') === 'global');
$fCompany        = $editGlobal ? 0 : (int)activeCompanyId($db, $user);
if ($isSuperSettings && !$fCompany) { $editGlobal = true; $fCompany = 0; }   // no company picked

$fCompanyName = '';
if ($fCompany) {
    $cn = $db->prepare("SELECT Name FROM tblCompany WHERE id=?");
    $cn->execute([$fCompany]);
    $fCompanyName = (string)$cn->fetchColumn();
}

// ── Save ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('settings.edit');
    csrf_verify();

    // Drop a company-level override so that company falls back to the global default.
    // Superadmin only — this edits companies other than the one in the topbar switcher.
    if (($_POST['action'] ?? '') === 'clear_override') {
        if ($user['role'] !== 'superadmin') {
            header('Location: index.php'); exit;
        }
        $ocId = (int)($_POST['override_company'] ?? 0);
        $oKey = trim($_POST['override_key'] ?? '');
        if ($ocId > 0) {
            if ($oKey !== '' && array_key_exists($oKey, settingCatalog())) {
                $del = $db->prepare("DELETE FROM tblSettings WHERE CompanyId=? AND SettingKey=?");
                $del->execute([$ocId, $oKey]);
            } elseif ($oKey === '') {                       // clear every managed key for this company
                $keysIn = implode(',', array_fill(0, count(settingCatalog()), '?'));
                $del = $db->prepare("DELETE FROM tblSettings WHERE CompanyId=? AND SettingKey IN ($keysIn)");
                $del->execute(array_merge([$ocId], array_keys(settingCatalog())));
            }
        }
        header('Location: index.php?cleared=1'); exit;
    }

    $saveFor = (int)($_POST['company_id'] ?? 0);
    if ($user['role'] !== 'superadmin') {
        $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $chk->execute([$saveFor, $user['scope_id']]);
        if (!$chk->fetch()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'errors' => ['Access denied.']]);
            exit;
        }
    }
    $keys = ['show_holiday_punches','show_leave_punches','show_weekoff_punches','show_before_doj','show_after_dol','allow_negative_leave','show_ot_report',
             'ot_before_shift','ot_after_shift','ot_manual_only','ot_clamp_out',
             'wo_deduct_adj_absent','wo_deduct_low_present'];
    $ins  = $db->prepare("INSERT INTO tblSettings (CompanyId,SettingKey,SettingValue) VALUES (?,?,?) ON DUPLICATE KEY UPDATE SettingValue=VALUES(SettingValue)");
    foreach ($keys as $k) {
        $ins->execute([$saveFor, $k, isset($_POST[$k]) ? '1' : '0']);
    }
    // Max OT hours allowed (caps auto-computed OT; drives out-punch clamping)
    $maxOt = (float)($_POST['ot_max_hours'] ?? 2);
    if ($maxOt < 0) $maxOt = 0;
    $ins->execute([$saveFor, 'ot_max_hours', (string)$maxOt]);
    // Min present days in a week to earn that week's week-off (0 = no minimum)
    $minWoPresent = (float)($_POST['wo_min_present_days'] ?? 3);
    if ($minWoPresent < 0) $minWoPresent = 0;
    if ($minWoPresent > 7) $minWoPresent = 7;
    $ins->execute([$saveFor, 'wo_min_present_days', (string)$minWoPresent]);
    // OT slab rules → JSON [{from,to,credit} in minutes]
    $slabs = [];
    $sf = (array)($_POST['ot_from'] ?? []); $st = (array)($_POST['ot_to'] ?? []); $sc = (array)($_POST['ot_credit'] ?? []);
    foreach ($sf as $i => $f) {
        $f = (int)$f; $t = (int)($st[$i] ?? 0); $c = (int)($sc[$i] ?? 0);
        if ($f === 0 && $t === 0 && $c === 0) continue;      // skip empty rows
        $slabs[] = ['from' => $f, 'to' => $t, 'credit' => $c];
    }
    usort($slabs, fn($a, $b) => $a['from'] <=> $b['from']);
    $ins->execute([$saveFor, 'ot_slabs', json_encode($slabs)]);

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

// OT max hours for the form (default 2 when never set)
$otMaxHours = array_key_exists('ot_max_hours', $settings) ? (float)$settings['ot_max_hours'] : 2;

// Min present days per week to earn the week off (default 3 when never set)
$woMinPresent = array_key_exists('wo_min_present_days', $settings) ? (float)$settings['wo_min_present_days'] : 3;

// OT slab rules for the form (default to a sensible example when never set)
$otSlabs = json_decode($settings['ot_slabs'] ?? '', true);
if (!is_array($otSlabs) || !$otSlabs) {
    $otSlabs = [
        ['from' => 0,  'to' => 20, 'credit' => 0],
        ['from' => 21, 'to' => 35, 'credit' => 30],
        ['from' => 36, 'to' => 60, 'credit' => 60],
    ];
}

function checked(array $s, string $k): string {
    return !empty($s[$k]) ? 'checked' : '';
}
// Checkbox default used when a setting has never been saved (preserves prior behaviour).
function checkedDef(array $s, string $k, bool $default): string {
    if (!array_key_exists($k, $s)) return $default ? 'checked' : '';
    return !empty($s[$k]) ? 'checked' : '';
}

$pageTitle  = 'Settings';
$activePage = 'settings';
require_once __DIR__ . '/../../includes/header.php';
?>

<?php if (!$fCompany && $user['role'] !== 'superadmin'): ?>
<div class="alert alert-info">Select a company to configure its settings.</div>
<?php else: ?>

<?php if ($isSuperSettings): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
    <span class="small fw-semibold me-1">Editing:</span>
    <a href="index.php?scope=global"
       class="btn btn-sm <?= $editGlobal ? 'btn-primary' : 'btn-outline-secondary' ?>">
      <i class="bi bi-globe2 me-1"></i>Global defaults
    </a>
    <a href="index.php"
       class="btn btn-sm <?= $editGlobal ? 'btn-outline-secondary' : 'btn-primary' ?>">
      <i class="bi bi-building me-1"></i><?= $fCompanyName ? htmlspecialchars($fCompanyName) : 'Active company' ?>
    </a>
    <div class="small text-muted ms-2" style="max-width:640px">
      <?php if ($editGlobal): ?>
        Saving here sets the fallback for <strong>every</strong> company. A company that stores its own
        value keeps it — see Per-Company Overrides below.
      <?php else: ?>
        Saving here applies to <strong><?= htmlspecialchars($fCompanyName ?: 'this company') ?></strong> only,
        and overrides the global default. Switch companies from the topbar.
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<form method="POST" data-ajax action="index.php<?= $editGlobal ? '?scope=global' : ($fCompany ? '?company='.$fCompany : '') ?>">
  <?= csrf_field() ?? '' ?>
  <input type="hidden" name="company_id" value="<?= $fCompany ?>">

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
      <i class="bi bi-toggles text-primary"></i>
      Attendance Settings
      <?php if ($editGlobal): ?>
      <span class="badge bg-secondary ms-1">Global Defaults</span>
      <?php elseif ($fCompany): ?>
      <span class="badge bg-primary ms-1"><?= htmlspecialchars($fCompanyName) ?></span>
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
                     id="show_ot_report" name="show_ot_report"
                     <?= checkedDef($settings, 'show_ot_report', true) ?>>
            </div>
            <div>
              <label class="form-check-label fw-semibold" for="show_ot_report">
                Show OT (from punches) in Attendance Report
              </label>
              <div class="text-muted small mt-1">
                When ON — the attendance grid shows overtime (worked minutes beyond the shift's full-day hours) under each present day. When OFF — the OT figure is hidden.
              </div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="d-flex align-items-start gap-3 p-3 border rounded">
            <div class="form-check form-switch mb-0 pt-1">
              <input class="form-check-input" type="checkbox" role="switch"
                     id="allow_negative_leave" name="allow_negative_leave"
                     <?= checkedDef($settings, 'allow_negative_leave', true) ?>>
            </div>
            <div>
              <label class="form-check-label fw-semibold" for="allow_negative_leave">
                Allow Leave Negative Balance
              </label>
              <div class="text-muted small mt-1">
                When ON — leaves can be marked even if the employee has no remaining balance (balance goes negative). When OFF — marking a leave that would exceed the available balance is blocked.
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

  <!-- ── Week Off Settings ───────────────────────────────────────────────────── -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
      <i class="bi bi-calendar2-week text-primary"></i>
      Week Off Settings
      <?php if ($editGlobal): ?>
      <span class="badge bg-secondary ms-1">Global Defaults</span>
      <?php elseif ($fCompany): ?>
      <span class="badge bg-primary ms-1"><?= htmlspecialchars($fCompanyName) ?></span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-4">
        Rules that decide whether a week off is <strong>paid</strong>. A deducted week off is dropped from the
        <em>H+S</em> count and from <em>Days</em> in the attendance report, and is shown struck through in the grid.
        These rules apply to Sundays and marked week-offs (WO) only &mdash; declared holidays are always paid.
      </p>

      <div class="row g-4">
        <div class="col-12">
          <div class="d-flex align-items-start gap-3 p-3 border rounded">
            <div class="form-check form-switch mb-0 pt-1">
              <input class="form-check-input" type="checkbox" role="switch" id="wo_deduct_adj_absent" name="wo_deduct_adj_absent" <?= checked($settings, 'wo_deduct_adj_absent') ?>>
            </div>
            <div>
              <label class="form-check-label fw-semibold" for="wo_deduct_adj_absent">Deduct week off if absent on either adjoining day</label>
              <div class="text-muted small mt-1">
                When ON &mdash; if the employee is absent on the working day <strong>before or after</strong> a week off, that week off is not paid.
                Intervening holidays / other week-offs are skipped, so a Fri absence still costs a Sat&ndash;Sun weekend.
              </div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="d-flex align-items-start gap-3 p-3 border rounded">
            <div class="form-check form-switch mb-0 pt-1">
              <input class="form-check-input" type="checkbox" role="switch" id="wo_deduct_low_present" name="wo_deduct_low_present" <?= checked($settings, 'wo_deduct_low_present') ?>>
            </div>
            <div>
              <label class="form-check-label fw-semibold" for="wo_deduct_low_present">Deduct week off if present less than the minimum days that week</label>
              <div class="text-muted small mt-1">
                When ON &mdash; the week off is paid only if the employee worked at least the minimum days in that
                Monday&ndash;Sunday week (half-days count as &frac12;).
                <strong>Note:</strong> run the report over whole weeks or whole months &mdash; a range that starts mid-week
                can only count the days inside the range.
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-6">
          <div class="p-3 border rounded h-100">
            <label class="form-check-label fw-semibold" for="wo_min_present_days">Minimum present days per week</label>
            <div class="text-muted small mt-1 mb-2">Used by the toggle above. Default 3. Set 0 to never deduct on this rule.</div>
            <input type="number" step="0.5" min="0" max="7" class="form-control" style="max-width:140px"
                   id="wo_min_present_days" name="wo_min_present_days" value="<?= rtrim(rtrim(number_format($woMinPresent, 1), '0'), '.') ?>">
          </div>
        </div>
      </div><!-- /.row -->
    </div>
    <div class="card-footer bg-white">
      <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Settings</button>
    </div>
  </div>

  <!-- ── OT Settings ─────────────────────────────────────────────────────────── -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
      <i class="bi bi-alarm text-primary"></i>
      OT Settings
      <?php if ($editGlobal): ?>
      <span class="badge bg-secondary ms-1">Global Defaults</span>
      <?php elseif ($fCompany): ?>
      <span class="badge bg-primary ms-1"><?= htmlspecialchars($fCompanyName) ?></span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <p class="text-muted small mb-4">Control how overtime is calculated from punches in the attendance report.</p>

      <div class="row g-4">
        <div class="col-12">
          <div class="d-flex align-items-start gap-3 p-3 border rounded">
            <div class="form-check form-switch mb-0 pt-1">
              <input class="form-check-input" type="checkbox" role="switch" id="ot_before_shift" name="ot_before_shift" <?= checked($settings, 'ot_before_shift') ?>>
            </div>
            <div>
              <label class="form-check-label fw-semibold" for="ot_before_shift">Consider OT if employee comes before shift time</label>
              <div class="text-muted small mt-1">Counts minutes punched-in earlier than the shift's start time toward overtime.</div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="d-flex align-items-start gap-3 p-3 border rounded">
            <div class="form-check form-switch mb-0 pt-1">
              <input class="form-check-input" type="checkbox" role="switch" id="ot_after_shift" name="ot_after_shift" <?= checked($settings, 'ot_after_shift') ?>>
            </div>
            <div>
              <label class="form-check-label fw-semibold" for="ot_after_shift">Consider OT if employee goes after shift time</label>
              <div class="text-muted small mt-1">Counts minutes punched-out later than the shift's end time toward overtime.</div>
            </div>
          </div>
        </div>

        <div class="col-12 col-md-6">
          <div class="p-3 border rounded h-100">
            <label class="form-check-label fw-semibold" for="ot_max_hours">Max OT hours allowed</label>
            <div class="text-muted small mt-1 mb-2">Caps auto-computed OT per day. Also the boundary for out-punch clamping below.</div>
            <input type="number" step="0.25" min="0" max="24" class="form-control" style="max-width:140px"
                   id="ot_max_hours" name="ot_max_hours" value="<?= rtrim(rtrim(number_format($otMaxHours, 2), '0'), '.') ?>">
          </div>
        </div>

        <div class="col-12 col-md-6">
          <div class="d-flex align-items-start gap-3 p-3 border rounded h-100">
            <div class="form-check form-switch mb-0 pt-1">
              <input class="form-check-input" type="checkbox" role="switch" id="ot_clamp_out" name="ot_clamp_out" <?= checked($settings, 'ot_clamp_out') ?>>
            </div>
            <div>
              <label class="form-check-label fw-semibold" for="ot_clamp_out">Restrict out-punches beyond OT limit</label>
              <div class="text-muted small mt-1">When ON — if an out-punch is later than <em>shift end + max OT hours</em>, the report shows a time close to that limit instead (random ±5 min). E.g. shift ends 18:00, max OT 2h &rarr; an out of 21:15 shows as ~20:00.</div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="d-flex align-items-start gap-3 p-3 border rounded">
            <div class="form-check form-switch mb-0 pt-1">
              <input class="form-check-input" type="checkbox" role="switch" id="ot_manual_only" name="ot_manual_only" <?= checked($settings, 'ot_manual_only') ?>>
            </div>
            <div>
              <label class="form-check-label fw-semibold" for="ot_manual_only">Use manual Overtime only</label>
              <div class="text-muted small mt-1">When ON — overtime is <strong>not</strong> auto-computed from punches; only OT entered on the <em>Mark OT / Absent</em> page is used. The two toggles and slab rules above/below are ignored.</div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="p-3 border rounded">
            <div class="fw-semibold mb-1">OT Rounding Slabs</div>
            <div class="text-muted small mb-3">Raw overtime minutes (early + late) are converted to credited OT using these ranges. Example: 20&ndash;35 min &rarr; 30 min credited.</div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-2" id="otSlabTable" style="max-width:520px">
                <thead class="table-light">
                  <tr>
                    <th style="width:130px">From (min)</th>
                    <th style="width:130px">To (min)</th>
                    <th style="width:150px">Credited OT (min)</th>
                    <th style="width:44px"></th>
                  </tr>
                </thead>
                <tbody id="otSlabBody">
                  <?php foreach ($otSlabs as $s): ?>
                  <tr>
                    <td><input type="number" name="ot_from[]"   class="form-control form-control-sm" min="0" value="<?= (int)$s['from'] ?>"></td>
                    <td><input type="number" name="ot_to[]"     class="form-control form-control-sm" min="0" value="<?= (int)$s['to'] ?>"></td>
                    <td><input type="number" name="ot_credit[]" class="form-control form-control-sm" min="0" value="<?= (int)$s['credit'] ?>"></td>
                    <td><button type="button" class="btn btn-outline-danger btn-sm ot-slab-del"><i class="bi bi-x-lg"></i></button></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm" id="otSlabAdd"><i class="bi bi-plus-lg me-1"></i>Add rule</button>
          </div>
        </div>
      </div><!-- /.row -->
    </div>
    <div class="card-footer bg-white">
      <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Save Settings</button>
    </div>
  </div>

</form>
<script>
(function(){
  var body = document.getElementById('otSlabBody');
  function rowHtml(){
    return '<tr>'
      + '<td><input type="number" name="ot_from[]" class="form-control form-control-sm" min="0" value="0"></td>'
      + '<td><input type="number" name="ot_to[]" class="form-control form-control-sm" min="0" value="0"></td>'
      + '<td><input type="number" name="ot_credit[]" class="form-control form-control-sm" min="0" value="0"></td>'
      + '<td><button type="button" class="btn btn-outline-danger btn-sm ot-slab-del"><i class="bi bi-x-lg"></i></button></td>'
      + '</tr>';
  }
  document.getElementById('otSlabAdd')?.addEventListener('click', function(){
    if (body) body.insertAdjacentHTML('beforeend', rowHtml());
  });
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.ot-slab-del');
    if (btn && body && body.rows.length > 1) btn.closest('tr').remove();
  });
})();
</script>
<?php endif; ?>

<?php
// ── Per-company overrides ─────────────────────────────────────────────────────
// The form above edits GLOBAL defaults for a superadmin, so any company-level row
// silently wins over what is shown there. Surface those rows or they are invisible.
if ($user['role'] === 'superadmin'):
    $catalog  = settingCatalog();
    $globalS  = loadSettings($db, 0);
    $ovRows   = $db->query(
        "SELECT s.CompanyId, s.SettingKey, s.SettingValue, s.UpdatedAt, c.Name
           FROM tblSettings s
           LEFT JOIN tblCompany c ON c.id = s.CompanyId
          WHERE s.CompanyId <> 0
          ORDER BY c.Name, s.SettingKey"
    )->fetchAll();
    // Keep only keys this page manages — other subsystems store their own rows here.
    $ovRows = array_values(array_filter($ovRows, fn($r) => array_key_exists($r['SettingKey'], $catalog)));
    $byCompany = [];
    foreach ($ovRows as $r) $byCompany[(int)$r['CompanyId']][] = $r;
?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold d-flex align-items-center gap-2">
    <i class="bi bi-diagram-2 text-primary"></i>
    Per-Company Overrides
    <span class="badge bg-secondary ms-1"><?= count($byCompany) ?> compan<?= count($byCompany) === 1 ? 'y' : 'ies' ?></span>
  </div>
  <div class="card-body">
    <?php if (isset($_GET['cleared'])): ?>
    <div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-1"></i>Override cleared — that company now follows the global default.</div>
    <?php endif; ?>

    <p class="text-muted small mb-3">
      The settings above are the <strong>global defaults</strong>. A company listed here has its own
      stored value that <strong>overrides</strong> the global one — changing the toggle above will
      <em>not</em> affect it. Clear an override to make that company follow the global default again.
    </p>

    <?php if (!$byCompany): ?>
      <div class="alert alert-light border py-2 small mb-0">
        <i class="bi bi-info-circle me-1"></i>No company overrides — every company follows the global defaults above.
      </div>
    <?php else: ?>
      <?php foreach ($byCompany as $cid => $rows): ?>
      <div class="border rounded mb-3">
        <div class="d-flex align-items-center justify-content-between px-3 py-2 bg-light border-bottom">
          <div class="fw-semibold">
            <i class="bi bi-building me-1"></i>
            <?= htmlspecialchars($rows[0]['Name'] ?? ('Company #' . $cid)) ?>
            <span class="text-muted small ms-1">(id <?= (int)$cid ?>)</span>
          </div>
          <form method="POST" class="m-0" onsubmit="return confirm('Clear all <?= count($rows) ?> override(s) for this company? It will then follow the global defaults.');">
            <?= csrf_field() ?? '' ?>
            <input type="hidden" name="action" value="clear_override">
            <input type="hidden" name="override_company" value="<?= (int)$cid ?>">
            <input type="hidden" name="override_key" value="">
            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-x-circle me-1"></i>Clear all</button>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Setting</th>
                <th style="width:130px">Company value</th>
                <th style="width:130px">Global value</th>
                <th style="width:110px"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r):
                $k       = $r['SettingKey'];
                $gVal    = $globalS[$k] ?? null;
                $differs = ((string)$r['SettingValue'] !== (string)($gVal ?? ''));
              ?>
              <tr>
                <td>
                  <?= htmlspecialchars($catalog[$k][0]) ?>
                  <div class="text-muted" style="font-size:11px"><code><?= htmlspecialchars($k) ?></code></div>
                </td>
                <td>
                  <?= settingValueLabel($k, $r['SettingValue']) ?>
                  <?php if ($differs): ?>
                  <div><span class="badge bg-warning-subtle text-warning border mt-1" style="font-size:10px">differs</span></div>
                  <?php else: ?>
                  <div><span class="text-muted" style="font-size:10px">same as global</span></div>
                  <?php endif; ?>
                </td>
                <td><?= settingValueLabel($k, $gVal) ?></td>
                <td>
                  <form method="POST" class="m-0">
                    <?= csrf_field() ?? '' ?>
                    <input type="hidden" name="action" value="clear_override">
                    <input type="hidden" name="override_company" value="<?= (int)$cid ?>">
                    <input type="hidden" name="override_key" value="<?= htmlspecialchars($k) ?>">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Clear</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
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
