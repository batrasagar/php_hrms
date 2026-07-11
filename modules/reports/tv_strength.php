<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db   = getDb();
$user = currentUser();
$role = $user['role'];

if ($role === 'user') {
    $scopeWhere = 'e.CompanyId = ' . (int)$user['company_id'];
    $coName = $db->prepare("SELECT Name FROM tblCompany WHERE id=?");
    $coName->execute([(int)$user['company_id']]);
    $scopeName = $coName->fetchColumn() ?: 'Company';
} elseif ($role === 'superadmin') {
    $fCompany   = (int)($_GET['company'] ?? 0);
    $scopeWhere = $fCompany ? "e.CompanyId = $fCompany" : '1';
    $scopeName  = 'All Companies';
    if ($fCompany) { $n = $db->prepare("SELECT Name FROM tblCompany WHERE id=?"); $n->execute([$fCompany]); $scopeName = $n->fetchColumn() ?: 'Company'; }
} else {
    $fCompany   = (int)($_GET['company'] ?? 0);
    $base       = 'c.AdminId = ' . (int)$user['id'];
    $scopeWhere = $fCompany ? "$base AND e.CompanyId = $fCompany" : $base;
    $scopeName  = 'All Companies';
    if ($fCompany) { $n = $db->prepare("SELECT Name FROM tblCompany WHERE id=?"); $n->execute([$fCompany]); $scopeName = $n->fetchColumn() ?: 'Company'; }
}

// ── Aggregates ────────────────────────────────────────────────────────────────
$byCompany = $db->query(
    "SELECT c.Name AS n, SUM(e.Status='active') AS a
     FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId
     WHERE $scopeWhere GROUP BY c.id, c.Name ORDER BY a DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$byDept = $db->query(
    "SELECT COALESCE(NULLIF(e.Department,''),'—') AS n, SUM(e.Status='active') AS a
     FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId
     WHERE $scopeWhere GROUP BY e.Department ORDER BY a DESC LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);

$gender = $db->query(
    "SELECT
        SUM(CASE WHEN LOWER(LEFT(TRIM(e.Gender),1))='m' THEN 1 ELSE 0 END) AS Male,
        SUM(CASE WHEN LOWER(LEFT(TRIM(e.Gender),1))='f' THEN 1 ELSE 0 END) AS Female,
        SUM(CASE WHEN e.Gender IS NULL OR TRIM(e.Gender)='' OR LOWER(LEFT(TRIM(e.Gender),1)) NOT IN ('m','f') THEN 1 ELSE 0 END) AS Other
     FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId
     WHERE $scopeWhere AND e.Status='active'"
)->fetch(PDO::FETCH_ASSOC);

$status = $db->query(
    "SELECT
        SUM(e.Status='active')     AS a,
        SUM(e.Status='inactive')   AS i,
        SUM(e.Status='terminated') AS t
     FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId
     WHERE $scopeWhere"
)->fetch(PDO::FETCH_ASSOC);

$totActive = array_sum(array_column($byCompany, 'a'));
$totAll    = (int)($status['a'] ?? 0) + (int)($status['i'] ?? 0) + (int)($status['t'] ?? 0);
$deptCount = count(array_filter($byDept, fn($d) => $d['n'] !== '—'));
$multiCo   = count($byCompany) > 1;

$refresh = max(20, (int)($_GET['refresh'] ?? 60));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="<?= $refresh ?>">
<title>Employee Strength — <?= htmlspecialchars($scopeName) ?></title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
  * { box-sizing:border-box; margin:0; padding:0; }
  :root { --bg:#0b1220; --panel:#131c2e; --line:#22304a; --muted:#8ea3c0; --text:#eaf1ff; }
  html,body { height:100%; }
  body { background:var(--bg); color:var(--text); font-family:'Segoe UI',system-ui,Arial,sans-serif; padding:18px; overflow-x:hidden; }
  .top { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
  .top h1 { font-size:26px; font-weight:700; letter-spacing:.3px; }
  .top .sub { color:var(--muted); font-size:14px; margin-top:2px; }
  .clock { text-align:right; }
  .clock .t { font-size:30px; font-weight:700; font-variant-numeric:tabular-nums; }
  .clock .d { color:var(--muted); font-size:13px; }
  .kpis { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:14px; }
  .kpi { background:var(--panel); border:1px solid var(--line); border-radius:14px; padding:16px 18px; }
  .kpi .v { font-size:48px; font-weight:800; line-height:1; }
  .kpi .l { color:var(--muted); font-size:13px; margin-top:8px; text-transform:uppercase; letter-spacing:.06em; }
  .kpi.green .v{color:#34d399} .kpi.blue .v{color:#60a5fa} .kpi.amber .v{color:#fbbf24} .kpi.pink .v{color:#f472b6}
  .grid { display:grid; grid-template-columns:repeat(2,1fr); gap:14px; }
  .panel { background:var(--panel); border:1px solid var(--line); border-radius:14px; padding:14px 16px; }
  .panel h2 { font-size:15px; font-weight:600; color:#cfe0ff; margin-bottom:10px; }
  .chart-wrap { position:relative; height:300px; }
  @media (max-width:900px){ .kpis{grid-template-columns:repeat(2,1fr)} .grid{grid-template-columns:1fr} }
</style>
</head>
<body>
<div class="top">
  <div>
    <h1><i></i>Employee Strength &mdash; <?= htmlspecialchars($scopeName) ?></h1>
    <div class="sub">Live headcount overview &middot; auto-refresh every <?= $refresh ?>s</div>
  </div>
  <div class="clock"><div class="t" id="clk">--:--</div><div class="d" id="clkd"></div></div>
</div>

<div class="kpis">
  <div class="kpi green"><div class="v"><?= number_format($totActive) ?></div><div class="l">Active Strength</div></div>
  <div class="kpi blue"><div class="v"><?= number_format($totAll) ?></div><div class="l">Total Headcount</div></div>
  <div class="kpi amber"><div class="v"><?= number_format($deptCount) ?></div><div class="l">Departments</div></div>
  <div class="kpi pink"><div class="v"><?= number_format((int)($gender['Female'] ?? 0)) ?><span style="font-size:22px;color:var(--muted)"> / <?= number_format((int)($gender['Male'] ?? 0)) ?></span></div><div class="l">Female / Male</div></div>
</div>

<div class="grid">
  <div class="panel">
    <h2><?= $multiCo ? 'Active by Company' : 'Active by Department' ?></h2>
    <div class="chart-wrap"><canvas id="chBar"></canvas></div>
  </div>
  <div class="panel">
    <h2>Gender Split (Active)</h2>
    <div class="chart-wrap"><canvas id="chGender"></canvas></div>
  </div>
  <div class="panel">
    <h2>Status Breakdown</h2>
    <div class="chart-wrap"><canvas id="chStatus"></canvas></div>
  </div>
  <div class="panel">
    <h2>Top Departments</h2>
    <div class="chart-wrap"><canvas id="chDept"></canvas></div>
  </div>
</div>

<script>
const BAR = <?= json_encode($multiCo ? $byCompany : $byDept) ?>;
const DEPT = <?= json_encode($byDept) ?>;
const GENDER = <?= json_encode(['Male'=>(int)($gender['Male']??0),'Female'=>(int)($gender['Female']??0),'Other'=>(int)($gender['Other']??0)]) ?>;
const STATUS = <?= json_encode(['Active'=>(int)($status['a']??0),'Inactive'=>(int)($status['i']??0),'Terminated'=>(int)($status['t']??0)]) ?>;

Chart.defaults.color = '#8ea3c0';
Chart.defaults.font.size = 13;
const grid = { color:'#22304a' };
const PALETTE = ['#60a5fa','#34d399','#fbbf24','#f472b6','#a78bfa','#f87171','#22d3ee','#facc15','#4ade80','#fb923c'];

new Chart(document.getElementById('chBar'), {
  type:'bar',
  data:{ labels:BAR.map(x=>x.n), datasets:[{ data:BAR.map(x=>+x.a), backgroundColor:'#60a5fa', borderRadius:6 }] },
  options:{ maintainAspectRatio:false, plugins:{legend:{display:false}},
    scales:{ x:{grid:{display:false}}, y:{grid, beginAtZero:true, ticks:{precision:0}} } }
});

new Chart(document.getElementById('chGender'), {
  type:'doughnut',
  data:{ labels:['Male','Female','Other'], datasets:[{ data:[GENDER.Male,GENDER.Female,GENDER.Other], backgroundColor:['#60a5fa','#f472b6','#94a3b8'], borderColor:'#131c2e', borderWidth:3 }] },
  options:{ maintainAspectRatio:false, cutout:'62%', plugins:{legend:{position:'bottom'}} }
});

new Chart(document.getElementById('chStatus'), {
  type:'doughnut',
  data:{ labels:['Active','Inactive','Terminated'], datasets:[{ data:[STATUS.Active,STATUS.Inactive,STATUS.Terminated], backgroundColor:['#34d399','#fbbf24','#f87171'], borderColor:'#131c2e', borderWidth:3 }] },
  options:{ maintainAspectRatio:false, cutout:'62%', plugins:{legend:{position:'bottom'}} }
});

new Chart(document.getElementById('chDept'), {
  type:'bar',
  data:{ labels:DEPT.map(x=>x.n), datasets:[{ data:DEPT.map(x=>+x.a), backgroundColor:DEPT.map((_,i)=>PALETTE[i%PALETTE.length]), borderRadius:6 }] },
  options:{ indexAxis:'y', maintainAspectRatio:false, plugins:{legend:{display:false}},
    scales:{ x:{grid, beginAtZero:true, ticks:{precision:0}}, y:{grid:{display:false}} } }
});

function tick(){
  const now = new Date();
  document.getElementById('clk').textContent = now.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit', second:'2-digit'});
  document.getElementById('clkd').textContent = now.toLocaleDateString([], {weekday:'long', day:'numeric', month:'short', year:'numeric'});
}
tick(); setInterval(tick, 1000);
</script>
</body>
</html>
