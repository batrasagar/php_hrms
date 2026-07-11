<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db     = getDb();
$user   = currentUser();
$errors = [];

// ── Default shift catalogue ───────────────────────────────────────────────────
// Day shifts carry a lunch break; the 3-shift rotation (24 hrs) runs continuous.
// Times are HH:MM. HrsP = min hours for present, HrsHlf = min hours for half day.
$DEFAULTS = [
    ['name' => 'General (09:00–18:00)', 'in' => '09:00', 'out' => '18:00', 'lunch' => ['13:30','14:00'], 'p' => 8, 'h' => 4],
    ['name' => 'Day (08:00–20:00)',     'in' => '08:00', 'out' => '20:00', 'lunch' => ['13:30','14:00'], 'p' => 8, 'h' => 4],
    ['name' => 'Day (07:00–19:00)',     'in' => '07:00', 'out' => '19:00', 'lunch' => ['12:30','13:00'], 'p' => 8, 'h' => 4],
    ['name' => 'Night (19:00–07:00)',   'in' => '19:00', 'out' => '07:00', 'lunch' => ['00:00','00:30'], 'p' => 8, 'h' => 4],
    ['name' => 'General (08:00–17:00)', 'in' => '08:00', 'out' => '17:00', 'lunch' => ['13:00','13:30'], 'p' => 8, 'h' => 4],
    ['name' => 'Evening (18:00–03:00)', 'in' => '18:00', 'out' => '03:00', 'lunch' => ['22:00','22:30'], 'p' => 8, 'h' => 4],
    ['name' => 'Shift A (06:00–14:00)', 'in' => '06:00', 'out' => '14:00', 'lunch' => null, 'p' => 8, 'h' => 4],
    ['name' => 'Shift B (14:00–22:00)', 'in' => '14:00', 'out' => '22:00', 'lunch' => null, 'p' => 8, 'h' => 4],
    ['name' => 'Shift C (22:00–06:00)', 'in' => '22:00', 'out' => '06:00', 'lunch' => null, 'p' => 8, 'h' => 4],
];

if ($user['role'] === 'superadmin') {
    $companies = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['id']]);
    $companies = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $companyId = (int)($_POST['company_id'] ?? 0);

    if (!$companyId) $errors[] = 'Please select a company.';

    if (!$errors) {
        $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=?" .
            ($user['role'] === 'superadmin' ? '' : ' AND AdminId=' . (int)$user['id']));
        $chk->execute([$companyId]);
        if (!$chk->fetch()) $errors[] = 'Invalid company selected.';
    }

    if (!$errors) {
        // Existing shift names for this company (skip duplicates)
        $ex = $db->prepare("SELECT ShiftName FROM tblShift WHERE CompanyId=?");
        $ex->execute([$companyId]);
        $existing = array_map('strtolower', array_column($ex->fetchAll(), 'ShiftName'));

        $ins = $db->prepare(
            "INSERT INTO tblShift (CompanyId, ShiftName, ArrivalTime, DepartureTime,
             HrsP, HrsHlf, HasLunch, LunchOutTime, LunchInTime, IsActive)
             VALUES (?,?,?,?,?,?,?,?,?,1)"
        );
        $added = 0; $skipped = 0;
        foreach ($DEFAULTS as $d) {
            if (in_array(strtolower($d['name']), $existing, true)) { $skipped++; continue; }
            $ins->execute([
                $companyId, $d['name'], $d['in'], $d['out'], $d['p'], $d['h'],
                $d['lunch'] ? 1 : 0,
                $d['lunch'][0] ?? null,
                $d['lunch'][1] ?? null,
            ]);
            $added++;
        }
        $_SESSION['flash'] = "Default shifts added: {$added}" . ($skipped ? " ({$skipped} already existed, skipped)." : '.');
        header('Location: index.php'); exit;
    }
}

$pageTitle  = 'Shift Master';
$activePage = 'shift_defaults';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="card border-0 shadow-sm" style="max-width:720px">
  <div class="card-header bg-white fw-semibold">Add Default Shifts</div>
  <div class="card-body">
    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
    <p class="text-muted small mb-3">
      Seeds the standard shift set below for the selected company. Shifts whose name already
      exists are skipped, so this is safe to run more than once. You can edit any shift afterwards.
    </p>
    <form method="POST">
      <?= csrf_field() ?>
      <div class="mb-3" style="max-width:340px">
        <label class="form-label">Company <span class="text-danger">*</span></label>
        <select name="company_id" class="form-select" required>
          <option value="">— Select Company —</option>
          <?php foreach ($companies as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['Name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle border">
          <thead class="table-light">
            <tr><th>Shift Name</th><th>Arrival</th><th>Departure</th><th>Lunch</th><th>Hrs/Full</th><th>Hrs/Half</th></tr>
          </thead>
          <tbody>
          <?php foreach ($DEFAULTS as $d): ?>
            <tr>
              <td class="fw-semibold"><?= htmlspecialchars($d['name']) ?></td>
              <td><?= $d['in'] ?></td>
              <td><?= $d['out'] ?></td>
              <td class="small"><?= $d['lunch'] ? ($d['lunch'][0] . '–' . $d['lunch'][1]) : '<span class="text-muted">None</span>' ?></td>
              <td><?= number_format($d['p'],2) ?></td>
              <td><?= number_format($d['h'],2) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary"><i class="bi bi-magic"></i> Add These Shifts</button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
