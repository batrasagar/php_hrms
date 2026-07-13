<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
$pageTitle  = 'Employee Strength Summary';
$activePage = 'report_strength';
require_once __DIR__ . '/../../includes/header.php';

$db   = getDb();
$user = currentUser();

if ($user['role'] === 'user') {
    $companiesDd = [];
    $fCompany    = $user['company_id'];
} elseif ($user['role'] === 'superadmin') {
    $companiesDd = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
    $fCompany    = (int)($_GET['company'] ?? 0);
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['scope_id']]);
    $companiesDd = $stmt->fetchAll();
    $fCompany    = (int)($_GET['company'] ?? 0);
}

$scopeWhere = match($user['role']) {
    'superadmin' => '1',
    'user'       => 'e.CompanyId = ' . (int)$fCompany,
    default      => 'c.AdminId = ' . $user['scope_id'],
};
$coFilter   = ($fCompany && $user['role'] !== 'user') ? "AND e.CompanyId = $fCompany" : '';

$byCompany = $db->query(
    "SELECT c.Name AS CompanyName,
            SUM(CASE WHEN e.Status='active'     THEN 1 ELSE 0 END) AS Active,
            SUM(CASE WHEN e.Status='inactive'   THEN 1 ELSE 0 END) AS Inactive,
            SUM(CASE WHEN e.Status='terminated' THEN 1 ELSE 0 END) AS `Terminated`,
            COUNT(*) AS Total
     FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId
     WHERE $scopeWhere $coFilter
     GROUP BY c.id, c.Name ORDER BY c.Name"
)->fetchAll();

$byDept = $db->query(
    "SELECT COALESCE(e.Department,'—') AS Department,
            SUM(CASE WHEN e.Status='active'   THEN 1 ELSE 0 END) AS Active,
            SUM(CASE WHEN e.Status='inactive' THEN 1 ELSE 0 END) AS Inactive,
            COUNT(*) AS Total
     FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId
     WHERE $scopeWhere $coFilter
     GROUP BY e.Department ORDER BY Total DESC"
)->fetchAll();

$byContractor = $db->query(
    "SELECT COALESCE(NULLIF(e.Contractor,''),'Direct') AS Contractor,
            SUM(e.Status='active') AS Active,
            COUNT(*) AS Total
     FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId
     WHERE $scopeWhere $coFilter
     GROUP BY e.Contractor ORDER BY Total DESC"
)->fetchAll();

$totalActive = array_sum(array_column($byCompany, 'Active'));
$totalAll    = array_sum(array_column($byCompany, 'Total'));

// Active headcount split by gender (first letter m/f, else Other)
$gender = $db->query(
    "SELECT
        SUM(CASE WHEN LOWER(LEFT(TRIM(e.Gender),1))='m' THEN 1 ELSE 0 END) AS Male,
        SUM(CASE WHEN LOWER(LEFT(TRIM(e.Gender),1))='f' THEN 1 ELSE 0 END) AS Female,
        SUM(CASE WHEN e.Gender IS NULL OR TRIM(e.Gender)='' OR LOWER(LEFT(TRIM(e.Gender),1)) NOT IN ('m','f') THEN 1 ELSE 0 END) AS Other
     FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId
     WHERE $scopeWhere $coFilter AND e.Status='active'"
)->fetch();
$gMale   = (int)($gender['Male']   ?? 0);
$gFemale = (int)($gender['Female'] ?? 0);
$gOther  = (int)($gender['Other']  ?? 0);
$gTotal  = $gMale + $gFemale + $gOther;
$pct = fn($n) => $gTotal > 0 ? round($n / $gTotal * 100) : 0;
?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <div class="d-flex flex-wrap gap-2 align-items-end">
      <form method="GET" class="d-flex gap-2 align-items-end flex-grow-1">
        <?php if ($user['role'] !== 'user'): ?>
        <div class="flex-grow-1" style="min-width:0">
          <label class="form-label small mb-1">Company</label>
          <select name="company" class="form-select form-select-sm w-100" onchange="this.form.submit()">
            <option value="">All Companies</option>
            <?php foreach ($companiesDd as $c): ?>
            <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php else: ?><input type="hidden" name="company" value="<?= $fCompany ?>"><?php endif; ?>
      </form>
      <button onclick="window.print()" class="btn btn-outline-success btn-sm align-self-end"><i class="bi bi-printer"></i> Print</button>
    </div>
  </div>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4">
    <div class="card text-center p-3">
      <div class="fs-2 fw-bold text-success"><?= $totalActive ?></div>
      <div class="text-muted small">Active Employees</div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="card text-center p-3">
      <div class="fs-2 fw-bold text-primary"><?= $totalAll ?></div>
      <div class="text-muted small">Total Headcount</div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="card text-center p-3">
      <div class="fs-2 fw-bold"><?= count($byDept) ?></div>
      <div class="text-muted small">Departments</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-md-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">By Company</div>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light"><tr><th>Company</th><th class="text-center">Active</th><th class="text-center">Inactive</th><th class="text-center">Term.</th><th class="text-center">Total</th></tr></thead>
          <tbody>
          <?php foreach ($byCompany as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['CompanyName']) ?></td>
            <td class="text-center text-success fw-semibold"><?= $r['Active'] ?></td>
            <td class="text-center text-warning"><?= $r['Inactive'] ?></td>
            <td class="text-center text-danger"><?= $r['Terminated'] ?></td>
            <td class="text-center fw-semibold"><?= $r['Total'] ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
      <div class="card-header bg-white fw-semibold">By Gender <small class="text-muted fw-normal">(Active)</small></div>
      <div class="card-body">
        <div class="row g-2 text-center">
          <div class="col-4">
            <div class="border rounded py-2" style="background:#eaf2ff">
              <div class="fs-3 fw-bold text-primary"><?= $gMale ?></div>
              <div class="small text-muted"><i class="bi bi-gender-male"></i> Male <span class="badge bg-primary-subtle text-primary"><?= $pct($gMale) ?>%</span></div>
            </div>
          </div>
          <div class="col-4">
            <div class="border rounded py-2" style="background:#ffeef5">
              <div class="fs-3 fw-bold" style="color:#d63384"><?= $gFemale ?></div>
              <div class="small text-muted"><i class="bi bi-gender-female"></i> Female <span class="badge" style="background:#f8d7ea;color:#d63384"><?= $pct($gFemale) ?>%</span></div>
            </div>
          </div>
          <div class="col-4">
            <div class="border rounded py-2 bg-light">
              <div class="fs-3 fw-bold text-secondary"><?= $gOther ?></div>
              <div class="small text-muted">Other / N-A <span class="badge bg-secondary-subtle text-secondary"><?= $pct($gOther) ?>%</span></div>
            </div>
          </div>
        </div>
        <?php if ($gTotal > 0): ?>
        <div class="progress mt-3" style="height:10px" role="progressbar" title="Male <?= $gMale ?> / Female <?= $gFemale ?> / Other <?= $gOther ?>">
          <div class="progress-bar bg-primary" style="width:<?= $pct($gMale) ?>%"></div>
          <div class="progress-bar" style="width:<?= $pct($gFemale) ?>%;background:#d63384"></div>
          <div class="progress-bar bg-secondary" style="width:<?= $pct($gOther) ?>%"></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">By Department</div>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light"><tr><th>Department</th><th class="text-center">Active</th><th class="text-center">Inactive</th><th class="text-center">Total</th></tr></thead>
          <tbody>
          <?php foreach ($byDept as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['Department']) ?></td>
            <td class="text-center text-success fw-semibold"><?= $r['Active'] ?></td>
            <td class="text-center text-warning"><?= $r['Inactive'] ?></td>
            <td class="text-center fw-semibold"><?= $r['Total'] ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">By Contractor</div>
      <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light"><tr><th>Contractor</th><th class="text-center">Active</th><th class="text-center">Total</th></tr></thead>
          <tbody>
          <?php foreach ($byContractor as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['Contractor']) ?></td>
            <td class="text-center text-success fw-semibold"><?= $r['Active'] ?></td>
            <td class="text-center fw-semibold"><?= $r['Total'] ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
