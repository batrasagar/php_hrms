<?php
define('BASE_URL', ($_SERVER['HTTP_HOST'] ?? '') === 'hr.attnlog.in' ? '' : '/php_hrms');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

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

// ── AJAX handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'add_link') {
        $title = trim($_POST['title'] ?? '');
        $url   = trim($_POST['url']   ?? '');
        if (!$title || !$url) { echo json_encode(['ok'=>false,'error'=>'Title and URL required']); exit; }
        if (!filter_var($url, FILTER_VALIDATE_URL)) { echo json_encode(['ok'=>false,'error'=>'Invalid URL']); exit; }
        $db->prepare("INSERT INTO tblDashboardLinks (UserId,Title,URL) VALUES (?,?,?)")->execute([$uid,$title,$url]);
        echo json_encode(['ok'=>true,'id'=>$db->lastInsertId()]);
        exit;
    }

    if ($action === 'delete_link') {
        $id = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM tblDashboardLinks WHERE id=? AND UserId=?")->execute([$id,$uid]);
        echo json_encode(['ok'=>true]);
        exit;
    }
}

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

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<?php /* ── KPI TILES ─────────────────────────────────────────────────────── */ ?>
<?php if ($role === 'superadmin'): ?>
<?php
$tiles = [
    ['label'=>'Active Companies',  'val'=>safeCount($db,"SELECT COUNT(*) FROM tblCompany WHERE IsActive=1"),          'icon'=>'bi-buildings',       'href'=>'modules/companies/index.php',  'accent'=>'#2563eb'],
    ['label'=>'Active Employees',  'val'=>safeCount($db,"SELECT COUNT(*) FROM tblEmployee WHERE Status='active'"),    'icon'=>'bi-people',          'href'=>'modules/employees/index.php',  'accent'=>'#16a34a'],
    ['label'=>'Present Today',     'val'=>safeCount($db,"SELECT COUNT(*) FROM `{$attnT}` WHERE tDate=? AND AttStatus='P'",[$today]), 'icon'=>'bi-person-check','href'=>'modules/reports/attendance.php','accent'=>'#0891b2'],
    ['label'=>'Pending Approvals', 'val'=>safeCount($db,"SELECT COUNT(*) FROM tblUser WHERE Status='pending'"),       'icon'=>'bi-hourglass-split', 'href'=>'modules/approvals/index.php',  'accent'=>'#d97706'],
];
?>
<?php elseif ($role === 'admin'): ?>
<?php
$cids = $db->prepare("SELECT id FROM tblCompany WHERE AdminId=? AND IsActive=1"); $cids->execute([$uid]);
$inList = implode(',', array_column($cids->fetchAll(PDO::FETCH_ASSOC),'id') ?: [0]);
$tiles = [
    ['label'=>'Active Employees', 'val'=>safeCount($db,"SELECT COUNT(*) FROM tblEmployee WHERE CompanyId IN ($inList) AND Status='active'"),                           'icon'=>'bi-people',          'href'=>'modules/employees/index.php',   'accent'=>'#2563eb'],
    ['label'=>'Present Today',    'val'=>safeCount($db,"SELECT COUNT(*) FROM `{$attnT}` WHERE CompanyId IN ($inList) AND tDate=? AND AttStatus='P'",[$today]),         'icon'=>'bi-person-check',    'href'=>'modules/reports/attendance.php', 'accent'=>'#16a34a'],
    ['label'=>'Absent Today',     'val'=>safeCount($db,"SELECT COUNT(*) FROM `{$attnT}` WHERE CompanyId IN ($inList) AND tDate=? AND AttStatus='A'",[$today]),         'icon'=>'bi-person-x',        'href'=>'modules/reports/attendance.php', 'accent'=>'#dc2626'],
    ['label'=>'On Leave Today',   'val'=>safeCount($db,"SELECT COUNT(*) FROM `{$attnT}` WHERE CompanyId IN ($inList) AND tDate=? AND AttStatus IN ('L','SL')",[$today]),'icon'=>'bi-calendar-x',    'href'=>'modules/leaves/index.php',      'accent'=>'#d97706'],
];
?>
<?php else: ?>
<?php
$cid   = (int)$user['company_id'];
$tiles = [
    ['label'=>'Active Employees', 'val'=>$cid ? safeCount($db,"SELECT COUNT(*) FROM tblEmployee WHERE CompanyId=? AND Status='active'",[$cid]) : 0,                              'icon'=>'bi-people',       'href'=>'modules/employees/index.php',   'accent'=>'#2563eb'],
    ['label'=>'Present Today',    'val'=>$cid ? safeCount($db,"SELECT COUNT(*) FROM `{$attnT}` WHERE CompanyId=? AND tDate=? AND AttStatus='P'",[$cid,$today]) : 0,              'icon'=>'bi-person-check', 'href'=>'modules/reports/attendance.php', 'accent'=>'#16a34a'],
    ['label'=>'Absent Today',     'val'=>$cid ? safeCount($db,"SELECT COUNT(*) FROM `{$attnT}` WHERE CompanyId=? AND tDate=? AND AttStatus='A'",[$cid,$today]) : 0,              'icon'=>'bi-person-x',     'href'=>'modules/reports/attendance.php', 'accent'=>'#dc2626'],
    ['label'=>'On Leave Today',   'val'=>$cid ? safeCount($db,"SELECT COUNT(*) FROM `{$attnT}` WHERE CompanyId=? AND tDate=? AND AttStatus IN ('L','SL')",[$cid,$today]) : 0,   'icon'=>'bi-calendar-x',   'href'=>'modules/leaves/index.php',      'accent'=>'#d97706'],
];
?>
<?php endif; ?>

<div class="row g-3 mb-4">
  <?php foreach ($tiles as $t): ?>
  <div class="col-6 col-xl-3">
    <a href="<?= BASE_URL ?>/<?= $t['href'] ?>" class="text-decoration-none">
      <div class="kpi-tile" style="--accent:<?= $t['accent'] ?>">
        <div class="kpi-tile-top">
          <span class="kpi-tile-label"><?= $t['label'] ?></span>
          <i class="bi <?= $t['icon'] ?> kpi-tile-icon"></i>
        </div>
        <div class="kpi-tile-value"><?= number_format($t['val']) ?></div>
        <div class="kpi-tile-date"><?= date('d M Y') ?></div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
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
    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addLinkModal">
      <i class="bi bi-plus-lg me-1"></i>Add Link
    </button>
  </div>
  <div class="card-body" id="quickLinksBody">
    <?php if (empty($myLinks)): ?>
    <p class="text-muted small mb-0" id="emptyLinksMsg">No quick links yet. Click <strong>Add Link</strong> to add shortcuts to pages you use often.</p>
    <?php else: ?>
    <div class="d-flex flex-wrap gap-2" id="linksList">
      <?php foreach ($myLinks as $lk): ?>
      <div class="quick-link-item" data-id="<?= $lk['id'] ?>">
        <a href="<?= htmlspecialchars($lk['URL']) ?>" target="_blank" class="quick-link-btn">
          <i class="bi bi-link-45deg"></i>
          <?= htmlspecialchars($lk['Title']) ?>
        </a>
        <button class="quick-link-del" onclick="deleteLink(<?= $lk['id'] ?>, this)" title="Remove">
          <i class="bi bi-x"></i>
        </button>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add Link Modal -->
<div class="modal fade" id="addLinkModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title mb-0">Add Quick Link</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label form-label-sm">Label</label>
          <input type="text" id="linkTitle" class="form-control form-control-sm" placeholder="e.g. Payroll Run">
        </div>
        <div class="mb-1">
          <label class="form-label form-label-sm">URL</label>
          <input type="url" id="linkUrl" class="form-control form-control-sm" placeholder="https://...">
        </div>
        <div id="linkError" class="text-danger small mt-1 d-none"></div>
      </div>
      <div class="modal-footer py-2">
        <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-sm btn-primary" onclick="saveLink()">Save</button>
      </div>
    </div>
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
.quick-link-btn { display: flex; align-items: center; gap: 5px; padding: 5px 10px; font-size: 13px; color: #374151; text-decoration: none; background: #f9fafb; transition: background .12s; flex: 1; }
.quick-link-btn:hover { background: #f3f4f6; color: #111827; }
.quick-link-del { border: none; background: transparent; border-left: 1px solid #e5e7eb; padding: 5px 7px; color: #9ca3af; cursor: pointer; font-size: 12px; transition: color .12s, background .12s; flex-shrink: 0; }
.quick-link-del:hover { background: #fee2e2; color: #dc2626; }
@media (max-width: 575.98px) { .quick-link-item { width: 100%; } }
</style>

<script>
const CSRF = '<?= csrf_token() ?>';

function saveLink() {
    const title = document.getElementById('linkTitle').value.trim();
    const url   = document.getElementById('linkUrl').value.trim();
    const err   = document.getElementById('linkError');
    err.classList.add('d-none');
    if (!title || !url) { err.textContent = 'Both fields are required.'; err.classList.remove('d-none'); return; }

    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=add_link&title=${encodeURIComponent(title)}&url=${encodeURIComponent(url)}&_csrf=${encodeURIComponent(CSRF)}`
    })
    .then(r => r.json())
    .then(d => {
        if (!d.ok) { err.textContent = d.error; err.classList.remove('d-none'); return; }
        bootstrap.Modal.getInstance(document.getElementById('addLinkModal')).hide();
        // Append to list
        let list = document.getElementById('linksList');
        const emptyMsg = document.getElementById('emptyLinksMsg');
        if (emptyMsg) {
            const body = document.getElementById('quickLinksBody');
            body.innerHTML = '<div class="d-flex flex-wrap gap-2" id="linksList"></div>';
            list = document.getElementById('linksList');
        }
        const div = document.createElement('div');
        div.className = 'quick-link-item';
        div.dataset.id = d.id;
        div.innerHTML = `<a href="${url}" target="_blank" class="quick-link-btn"><i class="bi bi-link-45deg"></i>${escHtml(title)}</a>
            <button class="quick-link-del" onclick="deleteLink(${d.id},this)" title="Remove"><i class="bi bi-x"></i></button>`;
        list.appendChild(div);
        document.getElementById('linkTitle').value = '';
        document.getElementById('linkUrl').value = '';
    });
}

function deleteLink(id, btn) {
    fetch('', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: `action=delete_link&id=${id}&_csrf=${encodeURIComponent(CSRF)}`
    })
    .then(r => r.json())
    .then(d => {
        if (!d.ok) return;
        const item = btn.closest('.quick-link-item');
        item.remove();
        if (!document.querySelectorAll('.quick-link-item').length) {
            document.getElementById('quickLinksBody').innerHTML =
                '<p class="text-muted small mb-0" id="emptyLinksMsg">No quick links yet. Click <strong>Add Link</strong> to add shortcuts to pages you use often.</p>';
        }
    });
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
