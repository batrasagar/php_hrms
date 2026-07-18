<?php
define('BASE_URL', preg_match('#(^|\.)hr\.attnlog\.in$#', $_SERVER['HTTP_HOST'] ?? '') ? '' : '/php_hrms');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
blockCompliance(); // compliance role lands on Employees, not the dashboard

$db   = getDb();
$user = currentUser();
$role = $user['role'];
$uid  = (int)$user['id'];

// ── Custom links table ────────────────────────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS tblDashboardLinks (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    UserId    INT          NOT NULL,
    Title     VARCHAR(100) NOT NULL,
    URL       VARCHAR(500) NOT NULL,
    SortOrder INT          DEFAULT 0,
    CreatedAt TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (UserId)
)");


// ── KPI helpers ───────────────────────────────────────────────────────────────
function safeCount(PDO $db, string $sql, array $p = []): int {
    try { $s = $db->prepare($sql); $s->execute($p); return (int)$s->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}

$today = date('Y-m-d');
$attnT = 'tblAttendance_' . date('ym');

// ── Custom links for this user ────────────────────────────────────────────────
$myLinks = $db->prepare("SELECT id, Title, URL FROM tblDashboardLinks WHERE UserId=? ORDER BY SortOrder, id");
$myLinks->execute([$uid]);
$myLinks = $myLinks->fetchAll(PDO::FETCH_ASSOC);

// ── Live attendance-summary panel: company comes from the global topbar switcher ──
$dashCid  = activeCompanyId($db, $user);
$dataUrl  = BASE_URL . '/ajax/attendance_data.php';

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<?php /* ── LIVE ATTENDANCE SUMMARY ───────────────────────────────────────── */ ?>
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="fw-semibold" style="font-size:13px"><i class="bi bi-calendar2-week me-1"></i>Attendance Summary</span>
    <div class="d-flex gap-2 align-items-center">
      <input type="date" id="asDate" class="form-control form-control-sm" style="max-width:150px" value="<?= $today ?>">
      <a id="asFull" class="btn btn-outline-secondary btn-sm" href="<?= BASE_URL ?>/modules/reports/attendance.php"><i class="bi bi-box-arrow-up-right"></i></a>
    </div>
  </div>
  <div class="card-body">
    <div id="asTiles" class="row g-3">
      <div class="col-12 text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-1"></span>Loading…</div>
    </div>
  </div>
</div>

<?php if ($role === 'superadmin' && safeCount($db,"SELECT COUNT(*) FROM tblUser WHERE Status='pending'") > 0): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4 py-2">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <span>Signup requests awaiting approval.</span>
  <a href="<?= BASE_URL ?>/modules/approvals/index.php" class="btn btn-warning btn-sm ms-auto">Review Now</a>
</div>
<?php endif; ?>

<?php /* ── QUICK LINKS ───────────────────────────────────────────────────── */ ?>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold" style="font-size:13px">Quick Links</span>
    <a href="<?= BASE_URL ?>/modules/links/index.php" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-pencil me-1"></i>Manage
    </a>
  </div>
  <div class="card-body">
    <?php if (empty($myLinks)): ?>
    <p class="text-muted small mb-0">No quick links yet. <a href="<?= BASE_URL ?>/modules/links/index.php">Add your first link</a>.</p>
    <?php else: ?>
    <div class="d-flex flex-wrap gap-2">
      <?php foreach ($myLinks as $lk): ?>
      <div class="quick-link-item">
        <a href="<?= htmlspecialchars($lk['URL']) ?>" target="_blank" class="quick-link-btn">
          <i class="bi bi-link-45deg"></i>
          <?= htmlspecialchars($lk['Title']) ?>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<style>
/* ── KPI Tiles ── */
.kpi-tile {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-left: 4px solid var(--accent);
  border-radius: 8px;
  padding: 18px 20px 14px;
  transition: box-shadow .15s;
}
.kpi-tile:hover { box-shadow: 0 4px 16px rgba(0,0,0,.1); }
.kpi-tile-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
.kpi-tile-label { font-size: 12px; font-weight: 500; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; }
.kpi-tile-icon { font-size: 18px; color: var(--accent); opacity: .7; }
.kpi-tile-value { font-size: 32px; font-weight: 700; color: #111827; line-height: 1; }
.kpi-tile-date { font-size: 11px; color: #9ca3af; margin-top: 4px; }

/* ── Quick Links ── */
.quick-link-item { display: flex; align-items: center; border: 1px solid #e5e7eb; border-radius: 6px; overflow: hidden; }
.quick-link-btn { display: flex; align-items: center; gap: 5px; padding: 6px 12px; font-size: 13px; color: #374151; text-decoration: none; background: #f9fafb; transition: background .12s; flex: 1; }
.quick-link-btn:hover { background: #f3f4f6; color: #111827; }
@media (max-width: 575.98px) { .quick-link-item { width: 100%; } }
@media (min-width: 576px) and (max-width: 991.98px) { .quick-link-item { width: calc(50% - 4px); } }
</style>


<script>
document.addEventListener('DOMContentLoaded', function(){
  var DATA_URL = '<?= $dataUrl ?>';
  var BASE     = '<?= BASE_URL ?>';
  var elDate  = document.getElementById('asDate');
  var elTiles = document.getElementById('asTiles');
  var elFull  = document.getElementById('asFull');
  var DEFAULT_CID = '<?= $dashCid ?>';

  function tile(val, label, sub, cls){
    return '<div class="col-6 col-md-4 col-xl-2">'
      + '<div class="card text-center py-2 h-100">'
      + '<div class="fs-3 fw-bold '+(cls||'')+'">'+val+'</div>'
      + '<div class="small text-muted">'+label+(sub?' <span class="badge bg-light text-muted border">'+sub+'</span>':'')+'</div>'
      + '</div></div>';
  }
  function load(){
    var cid = DEFAULT_CID;
    var d = elDate.value;
    if (!cid || !d){ elTiles.innerHTML = '<div class="col-12 text-muted small py-2">Select a company.</div>'; return; }
    elTiles.innerHTML = '<div class="col-12 text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-1"></span>Loading…</div>';
    var qs = 'company='+encodeURIComponent(cid)+'&from='+encodeURIComponent(d)+'&to='+encodeURIComponent(d);
    if (elFull) elFull.href = BASE + '/modules/reports/attendance.php?' + qs;
    fetch(DATA_URL + '?' + qs, {credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data || !data.success){ elTiles.innerHTML = '<div class="col-12 text-danger small">'+((data&&data.errors&&data.errors[0])||'Failed to load.')+'</div>'; return; }
        var g = data.grand||{};
        var html = '';
        html += tile(data.totalEmps||0, 'Employees', '', 'text-secondary');
        html += tile(data.workingDays||0, 'Working Days', '', 'text-muted');
        html += tile((g.P||0)+(g.HP?' <small class="text-primary">+'+g.HP+'HP</small>':''), 'Present', (data.pctP||0)+'%', 'text-success');
        html += tile(g.A||0, 'Absent', (data.pctA||0)+'%', 'text-danger');
        html += tile((g.L||0)+(g.HL?' <small class="text-warning">+'+g.HL+'HL</small>':''), 'On Leave', '', 'text-danger');
        html += tile(data.holidayCount||0, 'Holidays', '', 'text-info');
        elTiles.innerHTML = html;
      })
      .catch(function(){ elTiles.innerHTML = '<div class="col-12 text-danger small">Failed to load attendance.</div>'; });
  }
  elDate.addEventListener('change', load);
  load();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
