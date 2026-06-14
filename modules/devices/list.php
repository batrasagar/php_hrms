<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db   = getDb();
$user = currentUser();

// ── Build accessible company names ────────────────────────────────────────────
if ($user['role'] === 'superadmin') {
    $accessibleNames = null; // null = no filter (see all)
} elseif ($user['role'] === 'admin') {
    $stmt = $db->prepare("SELECT Name FROM tblCompany WHERE AdminId=? AND IsActive=1");
    $stmt->execute([$user['id']]);
    $accessibleNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $parentId = $user['parent_admin_id'];
    if ($parentId) {
        $stmt = $db->prepare("SELECT Name FROM tblCompany WHERE AdminId=? AND IsActive=1");
        $stmt->execute([$parentId]);
        $accessibleNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $accessibleNames = [];
    }
}

// ── Actions (before any output) ───────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($user['role'] === 'superadmin') {
        $db->prepare("DELETE FROM tblDevices WHERE id=?")->execute([$id]);
    } elseif ($user['role'] === 'admin' && !empty($accessibleNames)) {
        $ph = implode(',', array_fill(0, count($accessibleNames), '?'));
        $db->prepare("DELETE FROM tblDevices WHERE id=? AND Company IN ($ph)")
           ->execute([$id, ...$accessibleNames]);
    }
    header('Location: list.php'); exit;
}

// ── Load devices scoped to accessible companies ───────────────────────────────
if ($accessibleNames === null) {
    $devices = $db->query(
        "SELECT id, Company, SerialNumber, LastPing, Stamp, CreatedAt FROM tblDevices ORDER BY id DESC"
    )->fetchAll();
} elseif (empty($accessibleNames)) {
    $devices = [];
} else {
    $ph   = implode(',', array_fill(0, count($accessibleNames), '?'));
    $stmt = $db->prepare(
        "SELECT id, Company, SerialNumber, LastPing, Stamp, CreatedAt
         FROM tblDevices WHERE Company IN ($ph) ORDER BY id DESC"
    );
    $stmt->execute($accessibleNames);
    $devices = $stmt->fetchAll();
}

// ── Page setup ────────────────────────────────────────────────────────────────
$pageTitle  = 'Devices';
$activePage = 'devices';
require_once __DIR__ . '/../../includes/header.php';

$msg = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);
$canEdit = in_array($user['role'], ['superadmin', 'admin']);
?>
<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <span><?= count($devices) ?> device(s)</span>
  <?php if ($canEdit): ?>
  <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Device</a>
  <?php endif; ?>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover mb-0" id="tblDevices">
      <thead class="table-light">
        <tr>
          <th>#</th><th>Company</th><th>Serial Number</th><th>Last Ping</th><th>Stamp</th><th>Added</th>
          <?php if ($canEdit): ?><th>Action</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($devices as $d):
        $now     = new DateTime();
        $ping    = $d['LastPing'] ? new DateTime($d['LastPing']) : null;
        $diffMin = $ping ? (int)(($now->getTimestamp() - $ping->getTimestamp()) / 60) : null;
        $status  = $diffMin === null ? 'secondary' : ($diffMin <= 30 ? 'success' : ($diffMin <= 120 ? 'warning' : 'danger'));
      ?>
        <tr>
          <td><?= $d['id'] ?></td>
          <td><?= htmlspecialchars($d['Company']) ?></td>
          <td><code><?= htmlspecialchars($d['SerialNumber']) ?></code></td>
          <td>
            <span class="badge bg-<?= $status ?>">
              <?= $d['LastPing'] ? htmlspecialchars($d['LastPing']) : 'Never' ?>
            </span>
          </td>
          <td><?= htmlspecialchars($d['Stamp']) ?></td>
          <td><?= $d['CreatedAt'] ?></td>
          <?php if ($canEdit): ?>
          <td>
            <a href="add.php?edit=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
            <a href="list.php?delete=<?= $d['id'] ?>" class="btn btn-sm btn-outline-danger"
               onclick="return confirm('Delete this device?')"><i class="bi bi-trash"></i></a>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$extraJs = '<script>$(()=>{$("#tblDevices").DataTable({order:[[0,"desc"]]});});</script>';
require_once __DIR__ . '/../../includes/footer.php';
