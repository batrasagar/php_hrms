<?php
define('BASE_URL', ($_SERVER['HTTP_HOST'] ?? '') === 'hr.attnlog.in' ? '' : '/php_hrms');
require_once __DIR__ . '/config/db.php';
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/includes/header.php';

$db   = getDb();
$role = $user['role'];

// ── Shared helper: safe query that returns 0 if table missing ─────────────────
function safeCount(PDO $db, string $sql, array $params = []): int {
    try {
        $s = $db->prepare($sql);
        $s->execute($params);
        return (int)$s->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

function safeRow(PDO $db, string $sql, array $params = []): array {
    try {
        $s = $db->prepare($sql);
        $s->execute($params);
        return $s->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

$today = date('Y-m-d');
$ym    = date('ym'); // e.g. 2606
$attnT = "tblAttendance_{$ym}";
?>

<?php /* ══════════════════════════════════════════════════════ SUPERADMIN */ ?>
<?php if ($role === 'superadmin'): ?>
<?php
$pendingCount   = safeCount($db, "SELECT COUNT(*) FROM tblUser WHERE Status='pending'");
$totalAdmins    = safeCount($db, "SELECT COUNT(*) FROM tblUser WHERE Role='admin'");
$totalCompanies = safeCount($db, "SELECT COUNT(*) FROM tblCompany WHERE IsActive=1");
$totalUsers     = safeCount($db, "SELECT COUNT(*) FROM tblUser WHERE Role='user'");
$totalEmployees = safeCount($db, "SELECT COUNT(*) FROM tblEmployee WHERE Status='active'");
$totalDevices   = safeCount($db, "SELECT COUNT(*) FROM tblAdmsCredentials WHERE IsActive=1");
$presentToday   = safeCount($db, "SELECT COUNT(*) FROM `{$attnT}` WHERE tDate=? AND AttStatus='P'", [$today]);
$absentToday    = safeCount($db, "SELECT COUNT(*) FROM `{$attnT}` WHERE tDate=? AND AttStatus='A'", [$today]);
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <a href="modules/approvals/index.php" class="text-decoration-none">
      <div class="kpi-card <?= $pendingCount > 0 ? 'kpi-warning' : 'kpi-neutral' ?>">
        <div class="kpi-icon"><i class="bi bi-person-check-fill"></i></div>
        <div class="kpi-value"><?= $pendingCount ?></div>
        <div class="kpi-label">Pending Approvals</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/companies/index.php" class="text-decoration-none">
      <div class="kpi-card kpi-blue">
        <div class="kpi-icon"><i class="bi bi-buildings-fill"></i></div>
        <div class="kpi-value"><?= $totalCompanies ?></div>
        <div class="kpi-label">Active Companies</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/employees/index.php" class="text-decoration-none">
      <div class="kpi-card kpi-green">
        <div class="kpi-icon"><i class="bi bi-people-fill"></i></div>
        <div class="kpi-value"><?= $totalEmployees ?></div>
        <div class="kpi-label">Active Employees</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/users/list.php" class="text-decoration-none">
      <div class="kpi-card kpi-purple">
        <div class="kpi-icon"><i class="bi bi-person-badge-fill"></i></div>
        <div class="kpi-value"><?= $totalAdmins ?></div>
        <div class="kpi-label">Tenant Admins</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/reports/attendance.php" class="text-decoration-none">
      <div class="kpi-card kpi-green">
        <div class="kpi-icon"><i class="bi bi-check-circle-fill"></i></div>
        <div class="kpi-value"><?= $presentToday ?></div>
        <div class="kpi-label">Present Today</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/reports/attendance.php" class="text-decoration-none">
      <div class="kpi-card kpi-red">
        <div class="kpi-icon"><i class="bi bi-x-circle-fill"></i></div>
        <div class="kpi-value"><?= $absentToday ?></div>
        <div class="kpi-label">Absent Today</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/adms_credentials/index.php" class="text-decoration-none">
      <div class="kpi-card kpi-blue">
        <div class="kpi-icon"><i class="bi bi-hdd-network-fill"></i></div>
        <div class="kpi-value"><?= $totalDevices ?></div>
        <div class="kpi-label">Active Devices</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/users/list.php" class="text-decoration-none">
      <div class="kpi-card kpi-neutral">
        <div class="kpi-icon"><i class="bi bi-person-lines-fill"></i></div>
        <div class="kpi-value"><?= $totalUsers ?></div>
        <div class="kpi-label">Staff Users</div>
      </div>
    </a>
  </div>
</div>

<?php if ($pendingCount > 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
  <i class="bi bi-exclamation-triangle-fill fs-5"></i>
  <span><?= $pendingCount ?> signup request(s) awaiting your approval.</span>
  <a href="modules/approvals/index.php" class="btn btn-warning btn-sm ms-auto">Review Now</a>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold d-flex justify-content-between">
    <span>Recent Tenant Registrations</span>
    <a href="modules/approvals/index.php" class="btn btn-sm btn-outline-primary">View All</a>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover table-sm mb-0">
      <thead class="table-light">
        <tr><th>Name</th><th>Email</th><th>Status</th><th>Companies</th><th>Machines</th><th>Employees</th><th>Registered</th></tr>
      </thead>
      <tbody>
      <?php
      $recent = $db->query(
          "SELECT Name, Email, Status, CompanyLimit, MachinesLimit, EmpLimit, CreatedAt FROM tblUser
           WHERE Role='admin' ORDER BY CreatedAt DESC LIMIT 20"
      )->fetchAll();
      foreach ($recent as $r):
          $badge = match($r['Status']) {
              'active'   => 'bg-success',
              'pending'  => 'bg-warning text-dark',
              'rejected' => 'bg-danger',
              default    => 'bg-secondary',
          };
          $fmtL = fn($v) => $v == -1 ? '&#8734;' : $v;
      ?>
      <tr>
        <td><?= htmlspecialchars($r['Name']) ?></td>
        <td><?= htmlspecialchars($r['Email']) ?></td>
        <td><span class="badge <?= $badge ?>"><?= ucfirst($r['Status']) ?></span></td>
        <td><?= $fmtL($r['CompanyLimit']) ?></td>
        <td><?= $fmtL($r['MachinesLimit']) ?></td>
        <td><?= $fmtL($r['EmpLimit']) ?></td>
        <td class="small text-muted"><?= htmlspecialchars(substr($r['CreatedAt'], 0, 10)) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($recent)): ?>
      <tr><td colspan="7" class="text-center text-muted py-3">No tenant registrations yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php /* ══════════════════════════════════════════════════════ ADMIN */ ?>
<?php elseif ($role === 'admin'): ?>
<?php
$myCompanies = $db->prepare("SELECT id FROM tblCompany WHERE AdminId=? AND IsActive=1");
$myCompanies->execute([$user['id']]);
$companyIds   = array_column($myCompanies->fetchAll(PDO::FETCH_ASSOC), 'id');
$companyCount = count($companyIds);
$limit        = $user['company_limit'];

$inList = $companyIds ? implode(',', array_map('intval', $companyIds)) : '0';

$totalEmp    = safeCount($db, "SELECT COUNT(*) FROM tblEmployee WHERE CompanyId IN ($inList) AND Status='active'");
$presentToday= safeCount($db, "SELECT COUNT(*) FROM `{$attnT}` WHERE CompanyId IN ($inList) AND tDate=? AND AttStatus='P'", [$today]);
$absentToday = safeCount($db, "SELECT COUNT(*) FROM `{$attnT}` WHERE CompanyId IN ($inList) AND tDate=? AND AttStatus='A'", [$today]);
$leaveToday  = safeCount($db, "SELECT COUNT(*) FROM `{$attnT}` WHERE CompanyId IN ($inList) AND tDate=? AND AttStatus IN ('L','SL')", [$today]);
$lateToday   = safeCount($db, "SELECT COUNT(*) FROM `{$attnT}` WHERE CompanyId IN ($inList) AND tDate=? AND ShortTime>0 AND AttStatus NOT IN ('A','WO','PH','L','SL')", [$today]);
$onLeaveApproved = 0; // placeholder

$admsLabel = '';
$admsConfigured = false;
try {
    $adms = $db->query("SELECT Label FROM tblAdmsCredentials WHERE IsActive=1 LIMIT 1")->fetch();
    if ($adms) { $admsConfigured = true; $admsLabel = $adms['Label']; }
} catch (Exception $e) {}
?>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <a href="modules/employees/index.php" class="text-decoration-none">
      <div class="kpi-card kpi-blue">
        <div class="kpi-icon"><i class="bi bi-people-fill"></i></div>
        <div class="kpi-value"><?= $totalEmp ?></div>
        <div class="kpi-label">Active Employees</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/reports/attendance.php" class="text-decoration-none">
      <div class="kpi-card kpi-green">
        <div class="kpi-icon"><i class="bi bi-check-circle-fill"></i></div>
        <div class="kpi-value"><?= $presentToday ?></div>
        <div class="kpi-label">Present Today</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/reports/attendance.php" class="text-decoration-none">
      <div class="kpi-card kpi-red">
        <div class="kpi-icon"><i class="bi bi-x-circle-fill"></i></div>
        <div class="kpi-value"><?= $absentToday ?></div>
        <div class="kpi-label">Absent Today</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/leaves/index.php" class="text-decoration-none">
      <div class="kpi-card kpi-orange">
        <div class="kpi-icon"><i class="bi bi-calendar-x-fill"></i></div>
        <div class="kpi-value"><?= $leaveToday ?></div>
        <div class="kpi-label">On Leave Today</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/reports/attendance.php" class="text-decoration-none">
      <div class="kpi-card kpi-warning">
        <div class="kpi-icon"><i class="bi bi-clock-fill"></i></div>
        <div class="kpi-value"><?= $lateToday ?></div>
        <div class="kpi-label">Late / Short Today</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/companies/index.php" class="text-decoration-none">
      <div class="kpi-card kpi-purple">
        <div class="kpi-icon"><i class="bi bi-buildings-fill"></i></div>
        <div class="kpi-value"><?= $companyCount ?><?= $limit != -1 ? '<span class="kpi-sub">/'.$limit.'</span>' : '' ?></div>
        <div class="kpi-label">My Companies</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/punchlog/sync.php" class="text-decoration-none">
      <div class="kpi-card kpi-blue">
        <div class="kpi-icon"><i class="bi bi-arrow-repeat"></i></div>
        <div class="kpi-value" style="font-size:14px;margin:4px 0"><?= date('d M') ?></div>
        <div class="kpi-label">Sync Punches</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/adms_credentials/index.php" class="text-decoration-none">
      <div class="kpi-card <?= $admsConfigured ? 'kpi-green' : 'kpi-warning' ?>">
        <div class="kpi-icon"><i class="bi bi-<?= $admsConfigured ? 'plug-fill' : 'plug' ?>"></i></div>
        <div class="kpi-value" style="font-size:13px;margin:4px 0"><?= $admsConfigured ? htmlspecialchars($admsLabel) : 'Not Set' ?></div>
        <div class="kpi-label">ADMS <?= $admsConfigured ? 'Connected' : 'Credentials' ?></div>
      </div>
    </a>
  </div>
</div>

<?php /* ══════════════════════════════════════════════════════ USER */ ?>
<?php else: ?>
<?php
$compId   = $user['company_id'];
$compName = '';
if ($compId) {
    $cr = $db->prepare("SELECT Name FROM tblCompany WHERE id=? LIMIT 1");
    $cr->execute([$compId]);
    $compName = $cr->fetchColumn() ?: '';
}

$totalEmp    = $compId ? safeCount($db, "SELECT COUNT(*) FROM tblEmployee WHERE CompanyId=? AND Status='active'", [$compId]) : 0;
$presentToday= $compId ? safeCount($db, "SELECT COUNT(*) FROM `{$attnT}` WHERE CompanyId=? AND tDate=? AND AttStatus='P'", [$compId, $today]) : 0;
$absentToday = $compId ? safeCount($db, "SELECT COUNT(*) FROM `{$attnT}` WHERE CompanyId=? AND tDate=? AND AttStatus='A'", [$compId, $today]) : 0;
$leaveToday  = $compId ? safeCount($db, "SELECT COUNT(*) FROM `{$attnT}` WHERE CompanyId=? AND tDate=? AND AttStatus IN ('L','SL')", [$compId, $today]) : 0;
?>

<?php if ($compName): ?>
<div class="alert alert-info d-flex align-items-center gap-2 mb-4">
  <i class="bi bi-buildings fs-5"></i>
  <span>Viewing data for <strong><?= htmlspecialchars($compName) ?></strong></span>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <a href="modules/employees/index.php" class="text-decoration-none">
      <div class="kpi-card kpi-blue">
        <div class="kpi-icon"><i class="bi bi-people-fill"></i></div>
        <div class="kpi-value"><?= $totalEmp ?></div>
        <div class="kpi-label">Active Employees</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/reports/attendance.php" class="text-decoration-none">
      <div class="kpi-card kpi-green">
        <div class="kpi-icon"><i class="bi bi-check-circle-fill"></i></div>
        <div class="kpi-value"><?= $presentToday ?></div>
        <div class="kpi-label">Present Today</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/reports/attendance.php" class="text-decoration-none">
      <div class="kpi-card kpi-red">
        <div class="kpi-icon"><i class="bi bi-x-circle-fill"></i></div>
        <div class="kpi-value"><?= $absentToday ?></div>
        <div class="kpi-label">Absent Today</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/leaves/index.php" class="text-decoration-none">
      <div class="kpi-card kpi-orange">
        <div class="kpi-icon"><i class="bi bi-calendar-x-fill"></i></div>
        <div class="kpi-value"><?= $leaveToday ?></div>
        <div class="kpi-label">On Leave Today</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/reports/attendance.php" class="text-decoration-none">
      <div class="kpi-card kpi-neutral">
        <div class="kpi-icon"><i class="bi bi-calendar2-week"></i></div>
        <div class="kpi-label" style="margin-top:6px">Attendance Report</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/reports/monthly_attendance.php" class="text-decoration-none">
      <div class="kpi-card kpi-purple">
        <div class="kpi-icon"><i class="bi bi-calendar-month-fill"></i></div>
        <div class="kpi-label" style="margin-top:6px">Monthly Report</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/reports/ot_report.php" class="text-decoration-none">
      <div class="kpi-card kpi-blue">
        <div class="kpi-icon"><i class="bi bi-alarm-fill"></i></div>
        <div class="kpi-label" style="margin-top:6px">OT Report</div>
      </div>
    </a>
  </div>
  <div class="col-6 col-xl-3">
    <a href="modules/reports/leave_report.php" class="text-decoration-none">
      <div class="kpi-card kpi-orange">
        <div class="kpi-icon"><i class="bi bi-file-earmark-x-fill"></i></div>
        <div class="kpi-label" style="margin-top:6px">Leave Report</div>
      </div>
    </a>
  </div>
</div>
<?php endif; ?>

<style>
.kpi-card {
  border-radius: 14px;
  padding: 20px 16px;
  text-align: center;
  transition: transform .15s, box-shadow .15s;
  cursor: pointer;
  border: none;
  box-shadow: 0 2px 8px rgba(0,0,0,.07);
}
.kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.12); }
.kpi-icon { font-size: 28px; margin-bottom: 8px; line-height: 1; }
.kpi-value { font-size: 28px; font-weight: 700; line-height: 1; color: inherit; }
.kpi-sub { font-size: 14px; font-weight: 400; opacity: .65; }
.kpi-label { font-size: 12px; margin-top: 6px; font-weight: 500; opacity: .8; }

.kpi-blue   { background: #e8f0fe; color: #1a73e8; }
.kpi-green  { background: #e6f4ea; color: #1e8e3e; }
.kpi-red    { background: #fce8e6; color: #d93025; }
.kpi-orange { background: #fef3e2; color: #e8710a; }
.kpi-warning{ background: #fef9c3; color: #b45309; }
.kpi-purple { background: #f3e8fd; color: #7c3aed; }
.kpi-neutral{ background: #f1f3f4; color: #5f6368; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
