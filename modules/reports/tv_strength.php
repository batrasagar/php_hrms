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
  :root { --muted:#a9bde0; --text:#f3f7ff; }
  html,body { height:100%; }
  body {
    color:var(--text); font-family:'Segoe UI',system-ui,Arial,sans-serif;
    height:100vh; overflow:hidden; padding:clamp(10px,1.4vw,20px);
    display:flex; flex-direction:column; gap:clamp(8px,1vw,14px);
    background:
      radial-gradient(1200px 600px at 10% -10%, rgba(99,102,241,.28), transparent 60%),
      radial-gradient(1000px 500px at 100% 0%, rgba(236,72,153,.22), transparent 55%),
      radial-gradient(900px 700px at 50% 120%, rgba(16,185,129,.18), transparent 55%),
      linear-gradient(160deg,#0a1020,#0d1730 55%,#101a36);
  }
  .top { flex:0 0 auto; display:flex; align-items:center; justify-content:space-between; gap:12px; }
  .top h1 {
    font-size:clamp(18px,2vw,30px); font-weight:800; letter-spacing:.3px;
    background:linear-gradient(90deg,#7dd3fc,#a78bfa,#f472b6);
    -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent;
  }
  .top .sub { color:var(--muted); font-size:clamp(11px,1vw,14px); margin-top:2px; }
  .clock { text-align:right; }
  .clock .t { font-size:clamp(20px,2.2vw,34px); font-weight:800; font-variant-numeric:tabular-nums; color:#e8eeff; }
  .clock .d { color:var(--muted); font-size:clamp(10px,.9vw,13px); }

  .kpis { flex:0 0 auto; display:grid; grid-template-columns:repeat(4,1fr); gap:clamp(8px,1vw,14px); }
  .kpi {
    border-radius:16px; padding:clamp(10px,1.3vw,18px) clamp(12px,1.4vw,20px);
    border:1px solid rgba(255,255,255,.14); position:relative; overflow:hidden;
    box-shadow:0 10px 30px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.12);
  }
  .kpi::after{ content:''; position:absolute; right:-30px; top:-30px; width:120px; height:120px; border-radius:50%; background:rgba(255,255,255,.12); }
  .kpi .v { font-size:clamp(28px,4vw,54px); font-weight:900; line-height:1; text-shadow:0 2px 12px rgba(0,0,0,.35); }
  .kpi .l { color:rgba(255,255,255,.9); font-size:clamp(10px,1vw,13px); margin-top:6px; text-transform:uppercase; letter-spacing:.07em; font-weight:600; }
  .kpi.green { background:linear-gradient(135deg,#059669,#10b981 55%,#34d399); }
  .kpi.blue  { background:linear-gradient(135deg,#2563eb,#3b82f6 55%,#60a5fa); }
  .kpi.amber { background:linear-gradient(135deg,#d97706,#f59e0b 55%,#fbbf24); }
  .kpi.pink  { background:linear-gradient(135deg,#db2777,#ec4899 55%,#f472b6); }

  .grid {
    flex:1 1 auto; min-height:0; display:grid;
    grid-template-columns:1fr 1fr; grid-template-rows:1fr 1fr; gap:clamp(8px,1vw,14px);
  }
  .panel {
    min-height:0; display:flex; flex-direction:column;
    border-radius:16px; padding:clamp(8px,1vw,15px) clamp(10px,1.1vw,16px);
    background:rgba(255,255,255,.045); border:1px solid rgba(255,255,255,.10);
    backdrop-filter:blur(6px); box-shadow:0 8px 24px rgba(0,0,0,.28);
  }
  .panel h2 { flex:0 0 auto; font-size:clamp(12px,1.1vw,16px); font-weight:700; color:#dbe6ff; margin-bottom:8px; display:flex; align-items:center; gap:8px; }
  .panel h2::before{ content:''; width:9px; height:9px; border-radius:50%; background:linear-gradient(135deg,#7dd3fc,#f472b6); box-shadow:0 0 10px rgba(125,211,252,.8); }
  .chart-wrap { position:relative; flex:1 1 auto; min-height:0; }
  .chart-wrap canvas { position:absolute !important; inset:0; }

  @media (max-width:900px){
    body{ height:auto; overflow:auto; }
    .kpis{ grid-template-columns:repeat(2,1fr) }
    .grid{ grid-template-columns:1fr; grid-template-rows:none; grid-auto-rows:46vw }
  }
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
  <div class="kpi pink"><div class="v"><?= number_format((int)($gender['Female'] ?? 0)) ?><span style="font-size:.5em;color:rgba(255,255,255,.8)"> / <?= number_format((int)($gender['Male'] ?? 0)) ?></span></div><div class="l">Female / Male</div></div>
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

Chart.defaults.color = '#cdd9f5';
Chart.defaults.font.size = 13;
const grid = { color:'rgba(255,255,255,.08)' };
const PALETTE = ['#60a5fa','#34d399','#fbbf24','#f472b6','#a78bfa','#f87171','#22d3ee','#facc15','#4ade80','#fb923c'];

new Chart(document.getElementById('chBar'), {
  type:'bar',
  data:{ labels:BAR.map(x=>x.n), datasets:[{ data:BAR.map(x=>+x.a), backgroundColor:BAR.map((_,i)=>PALETTE[i%PALETTE.length]), borderRadius:8, maxBarThickness:70 }] },
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
