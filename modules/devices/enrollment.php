<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();

$db   = getDb();
$user = currentUser();

// ── Accessible companies for this user ────────────────────────────────────────
if ($user['role'] === 'superadmin') {
    $companies = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} elseif ($user['role'] === 'admin') {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['id']]);
    $companies = $stmt->fetchAll();
} else {
    $parentId = $user['parent_admin_id'] ?? 0;
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$parentId ?: 0]);
    $companies = $stmt->fetchAll();
}
$accessibleIds = array_column($companies, 'id');

// Guard: company must belong to this user
function canUseCompany(int $id, array $ids): bool {
    return in_array($id, $ids, true);
}

// Apply enrollment to ALL devices of the company, sync tblEmployee.EnrollId
function applyToAllDevices(PDO $db, int $company, string $enrollId, string $empCode): int {
    // Remove any existing slot for this employee on every device of this company first
    $db->prepare("DELETE de FROM tblDeviceEnrollment de
                  JOIN tblDevices d ON d.SerialNumber = de.DeviceSerial
                  JOIN tblCompany c ON c.Name = d.Company AND c.id = ?
                  WHERE de.EmpCode = ? AND de.CompanyId = ?")
       ->execute([$company, $empCode, $company]);

    $devStmt = $db->prepare("SELECT d.SerialNumber FROM tblDevices d
                              JOIN tblCompany c ON c.Name = d.Company WHERE c.id = ?");
    $devStmt->execute([$company]);
    $serials = $devStmt->fetchAll(PDO::FETCH_COLUMN);

    $ins  = $db->prepare("INSERT IGNORE INTO tblDeviceEnrollment (DeviceSerial, EnrollId, CompanyId, EmpCode) VALUES (?,?,?,?)");
    $done = 0;
    foreach ($serials as $serial) {
        $ins->execute([$serial, $enrollId, $company, $empCode]);
        $done += $ins->rowCount();
    }
    // Sync employee record
    $db->prepare("UPDATE tblEmployee SET EnrollId=? WHERE EmployeeCode=? AND CompanyId=?")
       ->execute([$enrollId, $empCode, $company]);

    return $done;
}

// ── Actions ───────────────────────────────────────────────────────────────────

if (isset($_GET['template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="enrollment_bulk_template.csv"');
    echo "EmpCode,EnrollId\r\nEMP-001,\r\nEMP-002,\r\n";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();

    if ($_POST['action'] === 'add') {
        $company = (int)($_POST['company_id'] ?? 0);
        $enroll  = trim($_POST['enroll_id']   ?? '');
        $empCode = trim($_POST['emp_code']     ?? '');

        if (!$company || !$enroll || !$empCode) {
            $_SESSION['flash'] = ['type'=>'warning','msg'=>'All fields are required.'];
        } elseif (!canUseCompany($company, $accessibleIds)) {
            $_SESSION['flash'] = ['type'=>'danger','msg'=>'Access denied.'];
        } else {
            $done = applyToAllDevices($db, $company, $enroll, $empCode);
            $_SESSION['flash'] = ['type'=>'success','msg'=>"Enrolled {$empCode} (EnrollId {$enroll}) across {$done} device(s)."];
        }
        if ($isAjax) {
            $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']);
            if (($f['type'] ?? '') !== 'success') { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>[$f['msg'] ?? 'Error']]); exit; }
            header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$f['msg'],'redirect'=>'enrollment.php']); exit;
        }
        header('Location: enrollment.php'); exit;
    }

    if ($_POST['action'] === 'bulk_import') {
        $company = (int)($_POST['company_id'] ?? 0);

        if (!$company || !canUseCompany($company, $accessibleIds)) {
            $_SESSION['flash'] = ['type'=>'danger','msg'=>'Select a valid company.'];
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>['Select a valid company.']]); exit; }
            header('Location: enrollment.php'); exit;
        }
        if (empty($_FILES['csv_file']['tmp_name'])) {
            $_SESSION['flash'] = ['type'=>'warning','msg'=>'Please upload a CSV file.'];
            if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>false,'errors'=>['Please upload a CSV file.']]); exit; }
            header('Location: enrollment.php'); exit;
        }

        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $hdr = array_map(fn($h) => strtolower(trim($h)), fgetcsv($handle));
        $ci  = array_search('empcode',  $hdr);
        $ei  = array_search('enrollid', $hdr);

        if ($ci === false) {
            fclose($handle);
            $_SESSION['flash'] = ['type'=>'danger','msg'=>'CSV must have an EmpCode column.'];
            header('Location: enrollment.php'); exit;
        }

        $enrollLookup = $db->prepare("SELECT EnrollId FROM tblEmployee WHERE EmployeeCode=? AND CompanyId=?");
        $processed = 0; $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $empCode = trim($row[$ci] ?? '');
            if (!$empCode) continue;

            $enroll = ($ei !== false) ? trim($row[$ei] ?? '') : '';
            if (!$enroll) {
                $enrollLookup->execute([$empCode, $company]);
                $enroll = $enrollLookup->fetchColumn() ?: '';
            }
            if (!$enroll) { $skipped++; continue; }

            applyToAllDevices($db, $company, $enroll, $empCode);
            $processed++;
        }
        fclose($handle);

        $msg = "Bulk import: {$processed} employee(s) enrolled";
        if ($skipped) $msg .= ", {$skipped} skipped (no EnrollId)";
        $_SESSION['flash'] = ['type'=>'success','msg'=>$msg];
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$msg,'redirect'=>'enrollment.php']); exit; }
        header('Location: enrollment.php'); exit;
    }

    if ($_POST['action'] === 'delete') {
        $company = (int)($_POST['company_id'] ?? 0);
        $empCode = trim($_POST['emp_code']    ?? '');

        if ($company && $empCode && canUseCompany($company, $accessibleIds)) {
            // Remove from all devices
            $db->prepare("DELETE de FROM tblDeviceEnrollment de
                          JOIN tblDevices d ON d.SerialNumber = de.DeviceSerial
                          JOIN tblCompany c ON c.Name = d.Company AND c.id = ?
                          WHERE de.EmpCode = ? AND de.CompanyId = ?")
               ->execute([$company, $empCode, $company]);
            // Clear employee EnrollId
            $db->prepare("UPDATE tblEmployee SET EnrollId='' WHERE EmployeeCode=? AND CompanyId=?")
               ->execute([$empCode, $company]);
        }
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>'enrollment.php']); exit; }
        header('Location: enrollment.php'); exit;
    }
}

// ── Page data: one row per (company, employee) ────────────────────────────────
$pageTitle  = 'Device Enrollment';
$activePage = 'device_enrollment';
require_once __DIR__ . '/../../includes/header.php';

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

if (empty($accessibleIds)) {
    $enrollments = [];
} else {
    $ph = implode(',', array_fill(0, count($accessibleIds), '?'));
    $stmt = $db->prepare("
        SELECT de.CompanyId, de.EmpCode, de.EnrollId,
               c.Name AS CompanyName,
               e.Name AS EmpName,
               COUNT(de.id) AS DeviceCount
        FROM tblDeviceEnrollment de
        LEFT JOIN tblCompany  c ON c.id = de.CompanyId
        LEFT JOIN tblEmployee e ON e.CompanyId = de.CompanyId AND e.EmployeeCode = de.EmpCode
        WHERE de.CompanyId IN ($ph)
        GROUP BY de.CompanyId, de.EmpCode, de.EnrollId, c.Name, e.Name
        ORDER BY c.Name, CAST(de.EnrollId AS UNSIGNED)
    ");
    $stmt->execute($accessibleIds);
    $enrollments = $stmt->fetchAll();
}
?>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
  <?= htmlspecialchars($flash['msg']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-4">

  <!-- ── Add form ── -->
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header">Add Enrollment</div>
      <div class="card-body">
        <p class="text-muted small mb-3">Enrollment is applied to <strong>all devices</strong> of the selected company automatically.</p>
        <form method="POST" id="addForm" data-ajax>
          <input type="hidden" name="action" value="add">

          <div class="mb-3">
            <label class="form-label">Company</label>
            <select name="company_id" id="selCompany" class="form-select" required>
              <option value="">— select company —</option>
              <?php foreach ($companies as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['Name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Employee</label>
            <select name="emp_code" id="selEmployee" class="form-select" required disabled>
              <option value="">— select company first —</option>
            </select>
          </div>

          <div class="mb-4">
            <label class="form-label">Enroll ID <span class="text-muted fw-normal small">(biometric slot #)</span></label>
            <input type="text" name="enroll_id" id="enrollIdInput" class="form-control" placeholder="e.g. 25" required>
            <div class="form-text text-muted" id="enrollHint"></div>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-plus-lg me-1"></i> Add Enrollment
          </button>
        </form>
      </div>
    </div>

    <!-- ── Bulk import ── -->
    <div class="card border-0 shadow-sm mt-4">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span>Bulk Import</span>
        <a href="?template=1" class="btn btn-outline-success btn-sm py-0">
          <i class="bi bi-download me-1"></i>Template
        </a>
      </div>
      <div class="card-body">
        <div class="alert alert-info small mb-3 py-2">
          CSV needs <strong>EmpCode</strong>. <strong>EnrollId</strong> is optional — auto-filled from the employee record if blank.
          Applied to <strong>all devices</strong> of the company.
        </div>
        <form method="POST" enctype="multipart/form-data" data-ajax>
          <input type="hidden" name="action" value="bulk_import">

          <div class="mb-3">
            <label class="form-label">Company</label>
            <select name="company_id" class="form-select" required>
              <option value="">— select company —</option>
              <?php foreach ($companies as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['Name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-4">
            <label class="form-label">CSV File</label>
            <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-upload me-1"></i> Import Enrollments
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- ── Enrollment list ── -->
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span>Enrollments</span>
        <span class="badge bg-secondary"><?= count($enrollments) ?></span>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover table-sm mb-0" id="tblEnroll">
          <thead class="table-light">
            <tr>
              <th>Enroll ID</th>
              <th>Company</th>
              <th>Employee</th>
              <th class="text-center">Devices</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($enrollments as $e): ?>
          <tr>
            <td><code><?= htmlspecialchars($e['EnrollId']) ?></code></td>
            <td class="small"><?= htmlspecialchars($e['CompanyName'] ?? '—') ?></td>
            <td class="small">
              <?= htmlspecialchars($e['EmpName'] ?? $e['EmpCode']) ?>
              <span class="text-muted">(<?= htmlspecialchars($e['EmpCode']) ?>)</span>
            </td>
            <td class="text-center">
              <span class="badge bg-light text-dark border"><?= $e['DeviceCount'] ?></span>
            </td>
            <td class="text-end">
              <form method="POST" class="d-inline" data-ajax
                    onsubmit="return confirm('Remove enrollment for <?= htmlspecialchars(addslashes($e['EmpCode'])) ?> from all devices?')">
                <input type="hidden" name="action"     value="delete">
                <input type="hidden" name="company_id" value="<?= $e['CompanyId'] ?>">
                <input type="hidden" name="emp_code"   value="<?= htmlspecialchars($e['EmpCode']) ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$enrollments): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">No enrollments yet.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<?php
$baseUrl = BASE_URL;
$extraJs = <<<JS
<script>
$(()=>{
  const BASE = '{$baseUrl}';

  if (\$('#tblEnroll tbody tr').length > 5) {
    \$('#tblEnroll').DataTable({order:[[0,'asc']],pageLength:25,dom:'ftp'});
  }

  const selCo  = document.getElementById('selCompany');
  const selEmp = document.getElementById('selEmployee');
  const eidIn  = document.getElementById('enrollIdInput');
  const hint   = document.getElementById('enrollHint');

  selCo.addEventListener('change', loadEmployees);
  const observer = new MutationObserver(() => {
    if (selCo.tomselect) { selCo.tomselect.on('change', loadEmployees); observer.disconnect(); }
  });
  observer.observe(document.body, {childList:true, subtree:true});

  function loadEmployees() {
    const cid = selCo.value;
    if (selEmp.tomselect) selEmp.tomselect.destroy();
    selEmp.innerHTML = '<option value="">Loading…</option>';
    selEmp.disabled = true;
    hint.textContent = '';
    if (!cid) { selEmp.innerHTML = '<option value="">— select company first —</option>'; return; }
    fetch(BASE + '/ajax/employees.php?company_id=' + cid)
      .then(r => r.json())
      .then(data => {
        selEmp.innerHTML = '<option value="">— select employee —</option>';
        data.forEach(e => {
          const o = document.createElement('option');
          o.value = e.code;
          o.textContent = e.name + ' (' + e.code + ')';
          o.dataset.enroll = e.enroll || '';
          selEmp.appendChild(o);
        });
        selEmp.disabled = false;
        initTomSelects(document.getElementById('addForm'));
      })
      .catch(() => { selEmp.innerHTML = '<option value="">Error loading</option>'; });
  }

  // Auto-fill EnrollId from employee record when employee is selected
  selEmp.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const enroll = opt ? opt.dataset.enroll : '';
    if (enroll) {
      eidIn.value = enroll;
      hint.textContent = 'Auto-filled from employee record';
    } else {
      if (hint.textContent.startsWith('Auto')) eidIn.value = '';
      hint.textContent = '';
    }
  });
});
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
