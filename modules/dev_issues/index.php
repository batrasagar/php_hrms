<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/_shared.php';
requireLogin();

$db   = getDb();
$user = currentUser();

try { $db->query("SELECT 1 FROM tblDevIssue LIMIT 1"); }
catch (PDOException $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

$isSuper  = $user['role'] === 'superadmin';
$scopeIds = di_scope_ids($db, $user);
$msg = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']);

/** Load one issue the user may act on. Superadmin: any; everyone else: only their own. */
function di_load(PDO $db, int $id, array $user): ?array {
    $s = $db->prepare("SELECT * FROM tblDevIssue WHERE id=?");
    $s->execute([$id]);
    $row = $s->fetch();
    if (!$row) return null;
    if ($user['role'] === 'superadmin') return $row;
    return (int)$row['CreatedBy'] === (int)$user['id'] ? $row : null;
}

// ── POST actions (status change / approve / reject / delete) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $issue  = $id ? di_load($db, $id, $user) : null;

    if ($issue) {
        if ($action === 'set_status') {
            $new = $_POST['status'] ?? '';
            $allowed = $isSuper ? DI_STATUSES : DI_USER_STATUSES;
            if (in_array($new, $allowed, true)) {
                $closedAt = $issue['ClosedAt'];
                if (in_array($new, DI_CLOSED, true)) { if (!$closedAt) $closedAt = date('Y-m-d H:i:s'); }
                else $closedAt = null;
                $db->prepare("UPDATE tblDevIssue SET Status=?, ClosedAt=?, UpdatedAt=NOW() WHERE id=?")
                   ->execute([$new, $closedAt, $id]);
                $_SESSION['flash'] = 'Status updated.';
            }
        } elseif ($action === 'approve' && $isSuper) {
            $db->prepare("UPDATE tblDevIssue SET Status='APPROVED', ClosedAt=NULL, UpdatedAt=NOW() WHERE id=?")->execute([$id]);
            $_SESSION['flash'] = 'Issue approved.';
        } elseif ($action === 'reject' && $isSuper) {
            $db->prepare("UPDATE tblDevIssue SET Status='REJECTED', ClosedAt=NULL, UpdatedAt=NOW() WHERE id=?")->execute([$id]);
            $_SESSION['flash'] = 'Issue rejected.';
        } elseif ($action === 'delete') {
            if ($isSuper || !in_array($issue['Status'], DI_DELETABLE_BLOCK, true)) {
                if (!empty($issue['Snapshot'])) @unlink(__DIR__ . '/../../uploads/dev-issues/' . $issue['Snapshot']);
                $db->prepare("DELETE FROM tblDevIssue WHERE id=?")->execute([$id]);
                $_SESSION['flash'] = 'Issue deleted.';
            }
        }
    }
    $qs = http_build_query(array_filter([
        'status'=>$_GET['status']??'', 'search'=>$_GET['search']??'', 'company'=>$_GET['company']??'',
        'from'=>$_GET['from']??'', 'to'=>$_GET['to']??'',
    ]));
    header('Location: index.php' . ($qs ? "?$qs" : '')); exit;
}

// ── Filters ────────────────────────────────────────────────────────────────────
$fStatus  = in_array($_GET['status'] ?? '', DI_STATUSES, true) ? $_GET['status'] : '';
$fSearch  = trim($_GET['search'] ?? '');
$fCompany = (int)($_GET['company'] ?? 0);

// Date range on the Issued date. Default to the last month on a fresh visit
// (no from/to in the query); once the filter form submits, the values are kept.
$fFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
$fTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']   ?? '') ? $_GET['to']   : '';
if (!isset($_GET['from']) && !isset($_GET['to'])) {
    $fFrom = date('Y-m-d', strtotime('-1 month'));
    $fTo   = date('Y-m-d');
}

$where = []; $params = [];
if (!$isSuper) {                              // non-superadmin: only issues they created
    $where[] = 'd.CreatedBy = ?';
    $params[] = (int)$user['id'];
}
if ($isSuper && $fCompany) { $where[] = 'd.CompanyId = ?'; $params[] = $fCompany; }
if ($fSearch !== '') {
    $where[] = '(d.Detail LIKE ? OR d.Url LIKE ? OR d.Expected LIKE ?)';
    $like = "%$fSearch%"; array_push($params, $like, $like, $like);
}
if ($fFrom !== '') { $where[] = 'd.CreatedAt >= ?'; $params[] = $fFrom . ' 00:00:00'; }
if ($fTo   !== '') { $where[] = 'd.CreatedAt <= ?'; $params[] = $fTo . ' 23:59:59'; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $db->prepare(
    "SELECT d.*, c.Name AS CompanyName, u.Name AS CreatorName
     FROM tblDevIssue d
     JOIN tblCompany c ON c.id = d.CompanyId
     LEFT JOIN tblUser u ON u.id = d.CreatedBy
     $whereSql
     ORDER BY d.id DESC"
);
$stmt->execute($params);
$allRows = $stmt->fetchAll();

// Counts per status (over the search-matched set, ignoring the status filter)
$counts = array_fill_keys(DI_STATUSES, 0);
foreach ($allRows as $r) { if (isset($counts[$r['Status']])) $counts[$r['Status']]++; }

// Apply status filter for display
$rows = $fStatus ? array_values(array_filter($allRows, fn($r) => $r['Status'] === $fStatus)) : $allRows;

$companiesDd = $isSuper || in_array($user['role'], ['admin','operator'], true) ? di_scope_companies($db, $user) : [];

function di_trunc(?string $s, int $n = 70): string {
    $s = trim((string)$s);
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n) . '…' : $s;
}
function di_fmt_dt(?string $s): string {
    return $s ? date('d M, H:i', strtotime($s)) : '—';
}

$pageTitle  = 'Development Issues';
$activePage = 'dev_issues';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
.di-stats{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
.di-stat{font-size:11.5px;font-weight:700;padding:4px 11px;border-radius:999px;border:1.5px solid transparent;cursor:pointer;font-family:inherit;text-decoration:none;transition:box-shadow .12s}
.di-stat:hover{box-shadow:0 1px 5px rgba(15,23,42,.15)}
.di-stat.active{border-color:currentColor}
.di-badge{font-size:11px;border-radius:999px;padding:2px 9px;font-weight:700;display:inline-block}
.di-thumb{width:44px;height:32px;object-fit:cover;border-radius:5px;border:1px solid var(--border)}
.di-tl{font-size:11.5px;white-space:nowrap}
.di-tl b{color:var(--text-3);font-weight:600;display:inline-block;min-width:44px}
.di-taken{font-weight:700;color:var(--blue)}
.di-stsel{border:1.5px solid var(--border);border-radius:999px;padding:3px 8px;font-size:11.5px;font-weight:700;cursor:pointer;outline:none}
</style>

<?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0">Development Issue Log</h5>
    <div class="text-muted small">Report &amp; track issues — screenshots, expected behaviour &amp; status.</div>
  </div>
  <a href="form.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Issue</a>
</div>

<form method="GET" class="row g-2 mb-3 align-items-center" data-filter>
  <div class="col-sm">
    <input type="text" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($fSearch) ?>" placeholder="Search detail / URL / expected…">
  </div>
  <div class="col-auto d-flex align-items-center gap-1">
    <span class="text-muted small">From</span>
    <input type="date" name="from" class="form-control form-control-sm" style="width:150px" value="<?= htmlspecialchars($fFrom) ?>" title="Issued from" onchange="$(this.form).trigger('submit')">
    <span class="text-muted small">To</span>
    <input type="date" name="to" class="form-control form-control-sm" style="width:150px" value="<?= htmlspecialchars($fTo) ?>" title="Issued to" onchange="$(this.form).trigger('submit')">
  </div>
  <?php if ($isSuper): ?>
  <div class="col-sm-auto">
    <select name="company" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:150px">
      <option value="">All companies</option>
      <?php foreach ($companiesDd as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <input type="hidden" name="status" value="<?= htmlspecialchars($fStatus) ?>">
  <div class="col-sm-auto"><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-search"></i> Search</button></div>
</form>

<div id="filter-results">
<?php
  // Build the querystring base for stat-badge links (preserve search/company/dates).
  // Always include from/to (even empty) so clicking a badge doesn't re-trigger the
  // "fresh visit" last-month default.
  $baseQs = ['from'=>$fFrom, 'to'=>$fTo];
  if ($fSearch !== '') $baseQs['search'] = $fSearch;
  if ($fCompany)       $baseQs['company'] = $fCompany;
?>
<div class="di-stats">
  <?php foreach (DI_STATUSES as $st):
      $on = $fStatus === $st;
      $qs = http_build_query($on ? $baseQs : ($baseQs + ['status'=>$st]));
  ?>
  <a class="di-stat <?= $on?'active':'' ?>" style="<?= di_badge($st) ?>" href="index.php<?= $qs?"?$qs":'' ?>"
     title="<?= $on?'Clear filter':'Filter by '.$st ?>"><?= $st ?>: <?= $counts[$st] ?></a>
  <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <?php if ($isSuper): ?><th>Company</th><?php endif; ?>
          <th>Detail</th><th>URL</th><th>Snap</th><th>Issued / Closed / Taken</th><th>Status</th><th>By</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $d):
        $canDelete = $isSuper || !in_array($d['Status'], DI_DELETABLE_BLOCK, true);
        $canEdit   = $d['Status'] !== 'APPROVED';
        $rowOpts   = DI_USER_STATUSES; if ($d['Status'] === 'APPROVED') $rowOpts[] = 'APPROVED';
      ?>
        <tr>
          <td class="text-muted small"><?= $d['id'] ?></td>
          <?php if ($isSuper): ?><td class="small"><?= htmlspecialchars($d['CompanyName']) ?></td><?php endif; ?>
          <td style="max-width:320px">
            <a href="form.php?id=<?= $d['id'] ?>" class="fw-semibold text-decoration-none"><?= htmlspecialchars(di_trunc($d['Detail'])) ?></a>
          </td>
          <td class="small"><?= $d['Url'] ? '<a href="'.htmlspecialchars($d['Url']).'" target="_blank" rel="noopener">'.htmlspecialchars(di_trunc($d['Url'],36)).'</a>' : '<span class="text-muted">—</span>' ?></td>
          <td>
            <?php if ($d['Snapshot']): ?>
              <a href="<?= BASE_URL ?>/uploads/dev-issues/<?= htmlspecialchars($d['Snapshot']) ?>" target="_blank">
                <img class="di-thumb" src="<?= BASE_URL ?>/uploads/dev-issues/<?= htmlspecialchars($d['Snapshot']) ?>" alt="snap">
              </a>
            <?php else: ?><span class="text-muted">—</span><?php endif; ?>
          </td>
          <td class="di-tl">
            <div><b>Issued</b> <?= di_fmt_dt($d['CreatedAt']) ?></div>
            <div><b>Closed</b> <?= di_fmt_dt($d['ClosedAt']) ?></div>
            <div><b>Taken</b> <span class="di-taken"><?= di_time_taken($d['CreatedAt'], $d['ClosedAt']) ?></span></div>
          </td>
          <td>
            <form method="POST" class="m-0">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="set_status">
              <input type="hidden" name="id" value="<?= $d['id'] ?>">
              <select name="status" class="di-stsel" style="<?= di_badge($d['Status']) ?>" onchange="this.form.submit()">
                <?php foreach ($rowOpts as $s): ?>
                <option value="<?= $s ?>" <?= $d['Status']===$s?'selected':'' ?>><?= $s ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td class="small text-muted"><?= htmlspecialchars($d['CreatorName'] ?? '—') ?></td>
          <td class="text-nowrap">
            <?php if ($canEdit): ?>
            <a href="form.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
            <?php endif; ?>
            <?php if ($isSuper && !in_array($d['Status'], ['APPROVED'], true)): ?>
            <form method="POST" class="d-inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= $d['id'] ?>">
              <button class="btn btn-sm btn-outline-success" title="Approve"><i class="bi bi-check-lg"></i></button>
            </form>
            <?php endif; ?>
            <?php if ($canDelete): ?>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this issue?')">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $d['id'] ?>">
              <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="<?= $isSuper?9:8 ?>" class="text-center text-muted py-4">No issues logged<?= $fStatus?' with this status':'' ?>. <a href="form.php">Report one</a>.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div><!-- /#filter-results -->
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
