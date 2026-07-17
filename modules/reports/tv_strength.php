<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db   = getDb();
$user = currentUser();

// Company comes from the global topbar switcher (?company= deep-link still honored)
$fCompany   = activeCompanyId($db, $user);
$scopeWhere = 'e.CompanyId = ' . (int)$fCompany;
$n = $db->prepare("SELECT Name FROM tblCompany WHERE id=?");
$n->execute([$fCompany]);
$scopeName = $n->fetchColumn() ?: 'Company';

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

// ── Today's attendance (from the processed shard) ─────────────────────────────
$attDate = trim($_GET['date'] ?? date('Y-m-d'));
$attnT   = 'tblAttendance_' . date('ym', strtotime($attDate));
$att     = ['p'=>0,'ab'=>0,'hd'=>0,'lv'=>0,'co'=>0];
$attByDept = [];
try {
    $q = $db->prepare(
        "SELECT SUM(a.AttStatus='P') AS p, SUM(a.AttStatus='A') AS ab,
                SUM(a.AttStatus='HD') AS hd, SUM(a.AttStatus IN ('L','SL')) AS lv,
                SUM(a.AttStatus='CO') AS co
         FROM `$attnT` a
         JOIN tblEmployee e ON e.CompanyId=a.CompanyId AND e.EmployeeCode=a.EmpCode COLLATE utf8mb4_unicode_ci
         JOIN tblCompany c ON c.id=e.CompanyId
         WHERE a.tDate=? AND $scopeWhere"
    );
    $q->execute([$attDate]);
    $r = $q->fetch(PDO::FETCH_ASSOC) ?: [];
    foreach ($att as $k => $_) $att[$k] = (int)($r[$k] ?? 0);

    $qd = $db->prepare(
        "SELECT COALESCE(NULLIF(e.Department,''),'—') AS n,
                SUM(a.AttStatus='P') AS p, SUM(a.AttStatus='A') AS ab
         FROM `$attnT` a
         JOIN tblEmployee e ON e.CompanyId=a.CompanyId AND e.EmployeeCode=a.EmpCode COLLATE utf8mb4_unicode_ci
         JOIN tblCompany c ON c.id=e.CompanyId
         WHERE a.tDate=? AND $scopeWhere
         GROUP BY e.Department HAVING (p+ab) > 0 ORDER BY (p+ab) DESC LIMIT 10"
    );
    $qd->execute([$attDate]);
    $attByDept = $qd->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* attendance shard may not exist yet */ }

$attMarked = $att['p'] + $att['ab'] + $att['hd'] + $att['lv'] + $att['co'];

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

  .kpis { flex:0 0 auto; display:grid; grid-template-columns:repeat(6,1fr); gap:clamp(8px,.8vw,12px); }
  .kpi {
    border-radius:16px; padding:clamp(10px,1.3vw,18px) clamp(12px,1.4vw,20px);
    border:1px solid rgba(255,255,255,.14); position:relative; overflow:hidden;
    box-shadow:0 10px 30px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.12);
  }
  .kpi::after{ content:''; position:absolute; right:-30px; top:-30px; width:120px; height:120px; border-radius:50%; background:rgba(255,255,255,.12); }
  .kpi .v { font-size:clamp(22px,3vw,46px); font-weight:900; line-height:1; text-shadow:0 2px 12px rgba(0,0,0,.35); }
  .kpi .l { color:rgba(255,255,255,.92); font-size:clamp(9px,.8vw,12px); margin-top:6px; text-transform:uppercase; letter-spacing:.06em; font-weight:600; }
  .kpi.green  { background:linear-gradient(135deg,#059669,#10b981 55%,#34d399); }
  .kpi.blue   { background:linear-gradient(135deg,#2563eb,#3b82f6 55%,#60a5fa); }
  .kpi.red    { background:linear-gradient(135deg,#dc2626,#ef4444 55%,#fb7185); }
  .kpi.amber  { background:linear-gradient(135deg,#d97706,#f59e0b 55%,#fbbf24); }
  .kpi.violet { background:linear-gradient(135deg,#7c3aed,#8b5cf6 55%,#a78bfa); }
  .kpi.pink   { background:linear-gradient(135deg,#db2777,#ec4899 55%,#f472b6); }

  .grid {
    flex:1 1 auto; min-height:0; display:grid;
    grid-template-columns:repeat(3,1fr); grid-template-rows:1fr 1fr; gap:clamp(8px,1vw,14px);
  }
  .panel.wide { grid-column:1 / -1; }
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
    .kpis{ grid-template-columns:repeat(3,1fr) }
    .grid{ grid-template-columns:1fr; grid-template-rows:none; grid-auto-rows:46vw }
    .panel.wide{ grid-column:auto; }
  }
</style>
</head>
<body>
<div class="top">
  <div>
    <h1>Strength &amp; Attendance &mdash; <?= htmlspecialchars($scopeName) ?></h1>
    <div class="sub">Attendance for <?= htmlspecialchars(date('D, d M Y', strtotime($attDate))) ?> &middot; auto-refresh every <?= $refresh ?>s</div>
  </div>
  <div class="clock"><div class="t" id="clk">--:--</div><div class="d" id="clkd"></div></div>
</div>

<div class="kpis">
  <div class="kpi green"><div class="v"><?= number_format($totActive) ?></div><div class="l">Active Strength</div></div>
  <div class="kpi blue"><div class="v"><?= number_format($att['p']) ?></div><div class="l">Present Today</div></div>
  <div class="kpi red"><div class="v"><?= number_format($att['ab']) ?></div><div class="l">Absent Today</div></div>
  <div class="kpi amber"><div class="v"><?= number_format($att['lv'] + $att['co']) ?><?php if ($att['hd']): ?><span style="font-size:.5em;color:rgba(255,255,255,.85)"> +<?= (int)$att['hd'] ?>HD</span><?php endif; ?></div><div class="l">On Leave / Off</div></div>
  <div class="kpi violet"><div class="v"><?= number_format($deptCount) ?></div><div class="l">Departments</div></div>
  <div class="kpi pink"><div class="v"><?= number_format((int)($gender['Female'] ?? 0)) ?><span style="font-size:.5em;color:rgba(255,255,255,.8)"> / <?= number_format((int)($gender['Male'] ?? 0)) ?></span></div><div class="l">Female / Male</div></div>
</div>

<div class="grid">
  <div class="panel wide">
    <h2>Department-wise Present vs Absent<?= $attMarked ? '' : ' — <span style="color:#fca5a5;font-weight:500">no processed attendance for this date</span>' ?></h2>
    <div class="chart-wrap"><canvas id="chDeptAtt"></canvas></div>
  </div>
  <div class="panel">
    <h2>Attendance Today</h2>
    <div class="chart-wrap"><canvas id="chAtt"></canvas></div>
  </div>
  <div class="panel">
    <h2>Gender Split (Active)</h2>
    <div class="chart-wrap"><canvas id="chGender"></canvas></div>
  </div>
  <div class="panel">
    <h2>Status Breakdown</h2>
    <div class="chart-wrap"><canvas id="chStatus"></canvas></div>
  </div>
</div>

<script>
const ATTDEPT = <?= json_encode($attByDept) ?>;
const ATT    = <?= json_encode(['P'=>$att['p'],'A'=>$att['ab'],'HD'=>$att['hd'],'Leave'=>$att['lv']+$att['co']]) ?>;
const GENDER = <?= json_encode(['Male'=>(int)($gender['Male']??0),'Female'=>(int)($gender['Female']??0),'Other'=>(int)($gender['Other']??0)]) ?>;
const STATUS = <?= json_encode(['Active'=>(int)($status['a']??0),'Inactive'=>(int)($status['i']??0),'Terminated'=>(int)($status['t']??0)]) ?>;

Chart.defaults.color = '#cdd9f5';
Chart.defaults.font.size = 13;
const grid = { color:'rgba(255,255,255,.08)' };
const donutOpts = { maintainAspectRatio:false, cutout:'60%', plugins:{legend:{position:'bottom', labels:{boxWidth:12, padding:10}}} };

// Department-wise Present vs Absent (stacked)
new Chart(document.getElementById('chDeptAtt'), {
  type:'bar',
  data:{ labels:ATTDEPT.map(x=>x.n), datasets:[
    { label:'Present', data:ATTDEPT.map(x=>+x.p),  backgroundColor:'#34d399', borderRadius:5, maxBarThickness:60 },
    { label:'Absent',  data:ATTDEPT.map(x=>+x.ab), backgroundColor:'#f87171', borderRadius:5, maxBarThickness:60 }
  ]},
  options:{ maintainAspectRatio:false,
    plugins:{ legend:{ position:'top', labels:{boxWidth:14} } },
    scales:{ x:{ stacked:true, grid:{display:false} }, y:{ stacked:true, grid, beginAtZero:true, ticks:{precision:0} } } }
});

// Attendance today doughnut
new Chart(document.getElementById('chAtt'), {
  type:'doughnut',
  data:{ labels:['Present','Absent','Half-Day','Leave/Off'], datasets:[{ data:[ATT.P,ATT.A,ATT.HD,ATT.Leave], backgroundColor:['#34d399','#f87171','#60a5fa','#fbbf24'], borderColor:'#0d1730', borderWidth:3 }] },
  options:donutOpts
});

new Chart(document.getElementById('chGender'), {
  type:'doughnut',
  data:{ labels:['Male','Female','Other'], datasets:[{ data:[GENDER.Male,GENDER.Female,GENDER.Other], backgroundColor:['#60a5fa','#f472b6','#94a3b8'], borderColor:'#0d1730', borderWidth:3 }] },
  options:donutOpts
});

new Chart(document.getElementById('chStatus'), {
  type:'doughnut',
  data:{ labels:['Active','Inactive','Terminated'], datasets:[{ data:[STATUS.Active,STATUS.Inactive,STATUS.Terminated], backgroundColor:['#34d399','#fbbf24','#f87171'], borderColor:'#0d1730', borderWidth:3 }] },
  options:donutOpts
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
