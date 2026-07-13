<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireUserAdmin(); // admin + superadmin only (operators excluded)

$db   = getDb();
$user = currentUser();

try { $db->query("SELECT 1 FROM tblLoginLog LIMIT 1"); }
catch (PDOException $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

$isSuper = $user['role'] === 'superadmin';

// Filters
$fFrom   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
$fTo     = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']   ?? '') ? $_GET['to']   : '';
if (!isset($_GET['from']) && !isset($_GET['to'])) {
    $fFrom = date('Y-m-d', strtotime('-1 month'));
    $fTo   = date('Y-m-d');
}
$fStatus = in_array($_GET['status'] ?? '', ['success','failed'], true) ? $_GET['status'] : '';
$fSearch = trim($_GET['search'] ?? '');
$fCompany = (int)($_GET['company'] ?? 0);

// Company dropdown (for superadmin: all; admin: their companies)
if ($isSuper) {
    $companiesDd = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $s = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $s->execute([$user['scope_id']]);
    $companiesDd = $s->fetchAll();
}

$where = []; $params = [];
if (!$isSuper) {
    // Admin: only their own users (ParentAdminId = admin id) or the admin themselves.
    $where[] = '(u.ParentAdminId = ? OR u.id = ?)';
    $params[] = $user['id']; $params[] = $user['id'];
}
if ($fCompany) { $where[] = 'u.CompanyId = ?'; $params[] = $fCompany; }
if ($fStatus) { $where[] = 'l.Status = ?'; $params[] = $fStatus; }
if ($fFrom !== '') { $where[] = 'l.LoggedAt >= ?'; $params[] = $fFrom . ' 00:00:00'; }
if ($fTo   !== '') { $where[] = 'l.LoggedAt <= ?'; $params[] = $fTo . ' 23:59:59'; }
if ($fSearch !== '') {
    $where[] = '(l.Email LIKE ? OR l.IpAddress LIKE ? OR u.Name LIKE ?)';
    $like = "%$fSearch%"; array_push($params, $like, $like, $like);
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $db->prepare(
    "SELECT l.*, u.Name AS UserName, u.Role AS UserRole, c.Name AS CompanyName
     FROM tblLoginLog l
     LEFT JOIN tblUser u ON u.id = l.UserId
     LEFT JOIN tblCompany c ON c.id = u.CompanyId
     $whereSql
     ORDER BY l.id DESC
     LIMIT 5000"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$methodLabel = ['password'=>'Password','email_otp'=>'Email OTP','whatsapp_otp'=>'WhatsApp OTP','password_2fa'=>'Password + 2FA'];

$pageTitle  = 'Login Log';
$activePage = 'login_log';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0">Login Log</h5>
    <div class="text-muted small"><?= $isSuper ? 'All users across the platform.' : 'Sign-ins by your company users.' ?></div>
  </div>
</div>

<form method="GET" class="row g-2 mb-3 align-items-center">
  <div class="col-sm">
    <input type="text" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($fSearch) ?>" placeholder="Search name / email / IP…">
  </div>
  <div class="col-auto d-flex align-items-center gap-1">
    <span class="text-muted small">From</span>
    <input type="date" name="from" class="form-control form-control-sm" style="width:150px" value="<?= htmlspecialchars($fFrom) ?>">
    <span class="text-muted small">To</span>
    <input type="date" name="to" class="form-control form-control-sm" style="width:150px" value="<?= htmlspecialchars($fTo) ?>">
  </div>
  <?php if ($companiesDd): ?>
  <div class="col-auto">
    <select name="company" class="form-select form-select-sm" style="min-width:150px">
      <option value="">All companies</option>
      <?php foreach ($companiesDd as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <div class="col-auto">
    <select name="status" class="form-select form-select-sm" style="width:130px">
      <option value="">All results</option>
      <option value="success" <?= $fStatus==='success'?'selected':'' ?>>Success</option>
      <option value="failed"  <?= $fStatus==='failed'?'selected':'' ?>>Failed</option>
    </select>
  </div>
  <div class="col-auto"><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i> Filter</button></div>
</form>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold">Sign-ins <span class="badge bg-secondary"><?= count($rows) ?></span></div>
  <div class="card-body p-0">
    <table class="table table-hover table-sm align-middle mb-0" id="tblLoginLog">
      <thead class="table-light">
        <tr>
          <th>When</th><th>User</th><th>Email</th>
          <?php if ($isSuper): ?><th>Role</th><?php endif; ?>
          <th>Company</th><th>Method</th><th>Result</th><th>IP</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="small text-nowrap" data-order="<?= htmlspecialchars($r['LoggedAt']) ?>"><?= date('d-M-Y H:i', strtotime($r['LoggedAt'])) ?></td>
          <td class="fw-semibold"><?= htmlspecialchars($r['UserName'] ?? '—') ?></td>
          <td class="small"><?= htmlspecialchars($r['Email']) ?></td>
          <?php if ($isSuper): ?><td class="small text-capitalize"><?= htmlspecialchars($r['UserRole'] ?? '—') ?></td><?php endif; ?>
          <td class="small"><?= htmlspecialchars($r['CompanyName'] ?? '—') ?></td>
          <td class="small"><?= htmlspecialchars($methodLabel[$r['Method']] ?? $r['Method']) ?></td>
          <td><span class="badge bg-<?= $r['Status']==='success'?'success':'danger' ?>"><?= ucfirst($r['Status']) ?></span></td>
          <td class="small text-muted"><?= htmlspecialchars($r['IpAddress']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<script>$(()=>{$("#tblLoginLog").DataTable({order:[[0,"desc"]],pageLength:25,language:{emptyTable:"No sign-ins in this range."}});});</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
