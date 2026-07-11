<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

// Download template
if (isset($_GET['template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="employee_import_template.csv"');
    echo "EmployeeCode,EnrollId,Name,Email,Phone,Department,Contractor,Designation,JoinDate,Status\r\n";
    echo "EMP-001,1001,John Doe,john@example.com,9876543210,Engineering,,Software Engineer,2024-01-15,active\r\n";
    echo "EMP-002,1002,Jane Smith,,9876543211,HR,ABC Agency,HR Executive,2024-02-01,active\r\n";
    exit;
}

if ($user['role'] === 'superadmin') {
    $companies = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['id']]);
    $companies = $stmt->fetchAll();
}

$preview   = [];
$importLog = [];
$step      = 'upload'; // upload | preview | done

// Step 1 → parse CSV for preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'preview') {
    csrf_verify();
    $companyId = (int)($_POST['company_id'] ?? 0);
    if (!$companyId) {
        $error = 'Please select a company.';
    } elseif (empty($_FILES['csv_file']['tmp_name'])) {
        $error = 'Please choose a CSV file.';
    } else {
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        $header = fgetcsv($handle);
        // Normalize header
        $header = array_map(fn($h) => strtolower(trim($h)), $header);
        $map = [
            'employeecode' => 'EmployeeCode',
            'enrollid'     => 'EnrollId',
            'name'         => 'Name',
            'email'        => 'Email',
            'phone'        => 'Phone',
            'department'   => 'Department',
            'contractor'   => 'Contractor',
            'designation'  => 'Designation',
            'joindate'     => 'JoinDate',
            'status'       => 'Status',
        ];
        $rowNum = 0;
        while (($row = fgetcsv($handle)) !== false && $rowNum < 5000) {
            $rowNum++;
            $rec = [];
            foreach ($header as $i => $h) {
                $field = $map[$h] ?? null;
                if ($field) $rec[$field] = trim($row[$i] ?? '');
            }
            if (empty($rec['Name'])) continue;
            $rec['_row'] = $rowNum;
            $preview[] = $rec;
        }
        fclose($handle);
        $step = 'preview';
        // Store CSV path in session for import step
        $tmpPath = sys_get_temp_dir() . '/hrms_import_' . session_id() . '.csv';
        copy($_FILES['csv_file']['tmp_name'], $tmpPath);
        $_SESSION['import_csv_path']  = $tmpPath;
        $_SESSION['import_company_id'] = $companyId;
    }
}

// Step 2 → do the actual import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $companyId = (int)($_SESSION['import_company_id'] ?? 0);
    $tmpPath   = $_SESSION['import_csv_path'] ?? '';
    $skip      = (int)($_POST['skip_first'] ?? 0); // 0=insert+update, 1=skip existing

    if ($companyId && $tmpPath && file_exists($tmpPath)) {
        // Verify company ownership
        if ($user['role'] !== 'superadmin') {
            $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
            $chk->execute([$companyId, $user['id']]);
            if (!$chk->fetch()) { $_SESSION['flash'] = 'Invalid company.'; header('Location: index.php'); exit; }
        }

        $handle = fopen($tmpPath, 'r');
        $rawHeader = fgetcsv($handle);
        $header = array_map(fn($h) => strtolower(trim($h)), $rawHeader);
        $map = [
            'employeecode' => 'EmployeeCode',
            'enrollid'     => 'EnrollId',
            'name'         => 'Name',
            'email'        => 'Email',
            'phone'        => 'Phone',
            'department'   => 'Department',
            'contractor'   => 'Contractor',
            'designation'  => 'Designation',
            'joindate'     => 'JoinDate',
            'status'       => 'Status',
        ];

        $inserted = 0; $updated = 0; $skipped = 0; $errors = 0;
        $rowNum   = 0;

        $insertStmt = $db->prepare(
            "INSERT INTO tblEmployee (CompanyId, EmployeeCode, EnrollId, Name, Email, Phone,
             Department, Contractor, Designation, JoinDate, Status) VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        );
        $updateStmt = $db->prepare(
            "UPDATE tblEmployee SET EnrollId=?, Email=?, Phone=?, Department=?, Contractor=?,
             Designation=?, JoinDate=?, Status=?, UpdatedAt=NOW()
             WHERE EmployeeCode=? AND CompanyId=?"
        );
        $checkStmt = $db->prepare("SELECT id FROM tblEmployee WHERE EmployeeCode=? AND CompanyId=?");

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $rec = [];
            foreach ($header as $i => $h) {
                $field = $map[$h] ?? null;
                if ($field) $rec[$field] = trim($row[$i] ?? '');
            }
            if (empty($rec['Name'])) { $skipped++; continue; }

            $code   = $rec['EmployeeCode'] ?? '';
            $status = in_array($rec['Status'] ?? '', ['active','inactive','terminated']) ? $rec['Status'] : 'active';

            if ($code) {
                $checkStmt->execute([$code, $companyId]);
                $existing = $checkStmt->fetch();
            } else {
                $existing = false;
            }

            try {
                if ($existing && $skip === 0) {
                    $updateStmt->execute([
                        $rec['EnrollId'] ?? '', $rec['Email'] ?: null, $rec['Phone'] ?: null,
                        $rec['Department'] ?: null, $rec['Contractor'] ?: null,
                        $rec['Designation'] ?: null,
                        (!empty($rec['JoinDate']) && strtotime($rec['JoinDate'])) ? date('Y-m-d', strtotime($rec['JoinDate'])) : null,
                        $status, $code, $companyId,
                    ]);
                    $updated++;
                    $importLog[] = ['row' => $rowNum, 'name' => $rec['Name'], 'status' => 'updated'];
                } elseif ($existing) {
                    $skipped++;
                    $importLog[] = ['row' => $rowNum, 'name' => $rec['Name'], 'status' => 'skipped'];
                } else {
                    $insertStmt->execute([
                        $companyId, $code, $rec['EnrollId'] ?? '',
                        $rec['Name'], $rec['Email'] ?: null, $rec['Phone'] ?: null,
                        $rec['Department'] ?: null, $rec['Contractor'] ?: null,
                        $rec['Designation'] ?: null,
                        (!empty($rec['JoinDate']) && strtotime($rec['JoinDate'])) ? date('Y-m-d', strtotime($rec['JoinDate'])) : null,
                        $status,
                    ]);
                    $inserted++;
                    $importLog[] = ['row' => $rowNum, 'name' => $rec['Name'], 'status' => 'inserted'];
                }
            } catch (PDOException $e) {
                $errors++;
                $importLog[] = ['row' => $rowNum, 'name' => $rec['Name'], 'status' => 'error', 'msg' => $e->getMessage()];
            }
        }
        fclose($handle);
        unlink($tmpPath);
        unset($_SESSION['import_csv_path'], $_SESSION['import_company_id']);
        $step = 'done';
        if ($isAjax) {
            $msg = "Import complete. Inserted: $inserted, Updated: $updated, Skipped: $skipped" . ($errors > 0 ? ", Errors: $errors" : '') . '.';
            header('Content-Type: application/json'); echo json_encode(['success'=>true,'message'=>$msg,'redirect'=>'index.php']); exit;
        }
    }
}
$pageTitle  = 'Import Employees';
$activePage = 'emp_import';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <span class="text-muted">Import employees from a CSV file.</span>
  <a href="?template=1" class="btn btn-outline-success btn-sm"><i class="bi bi-download"></i> Download Template</a>
</div>

<?php if ($step === 'upload'): ?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<div class="card border-0 shadow-sm" style="max-width:560px">
  <div class="card-header bg-white fw-semibold">Step 1 — Upload CSV</div>
  <div class="card-body">
    <div class="alert alert-info small mb-3">
      <strong>CSV Format:</strong> EmployeeCode, EnrollId, Name, Email, Phone, Department, Contractor, Designation, JoinDate (YYYY-MM-DD), Status (active/inactive/terminated)<br>
      <a href="?template=1">Download the template</a> to get started.
    </div>
    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="preview">
      <div class="mb-3">
        <label class="form-label">Company <span class="text-danger">*</span></label>
        <select name="company_id" class="form-select" required>
          <option value="">— Select Company —</option>
          <?php foreach ($companies as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['Name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">CSV File <span class="text-danger">*</span></label>
        <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
      </div>
      <button type="submit" class="btn btn-primary"><i class="bi bi-eye me-1"></i>Preview Import</button>
    </form>
  </div>
</div>

<?php elseif ($step === 'preview'): ?>
<div class="alert alert-info">
  <strong><?= count($preview) ?> row(s) detected.</strong> Preview below. Click <strong>Import Now</strong> to proceed.
</div>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body p-0" style="max-height:340px;overflow:auto">
    <table class="table table-sm table-hover mb-0">
      <thead class="table-light sticky-top">
        <tr>
          <th>#</th><th>Code</th><th>Enroll ID</th><th>Name</th>
          <th>Dept</th><th>Contractor</th><th>Designation</th><th>Join Date</th><th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($preview as $p): ?>
        <tr>
          <td class="text-muted"><?= $p['_row'] ?></td>
          <td><?= htmlspecialchars($p['EmployeeCode'] ?? '') ?></td>
          <td><?= htmlspecialchars($p['EnrollId'] ?? '') ?></td>
          <td class="fw-semibold"><?= htmlspecialchars($p['Name'] ?? '') ?></td>
          <td><?= htmlspecialchars($p['Department'] ?? '') ?></td>
          <td><?= htmlspecialchars($p['Contractor'] ?? '') ?></td>
          <td><?= htmlspecialchars($p['Designation'] ?? '') ?></td>
          <td><?= htmlspecialchars($p['JoinDate'] ?? '') ?></td>
          <td><?= htmlspecialchars($p['Status'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<form method="POST" class="d-flex gap-3 align-items-center" data-ajax>
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="import">
  <div class="form-check">
    <input class="form-check-input" type="checkbox" name="skip_existing" id="skipExisting"
           onchange="document.getElementById('skipFirst').value = this.checked ? 1 : 0">
    <label class="form-check-label" for="skipExisting">Skip rows with existing Employee Code (don't update)</label>
    <input type="hidden" name="skip_first" id="skipFirst" value="0">
  </div>
  <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>Import Now</button>
  <a href="import.php" class="btn btn-outline-secondary">Cancel</a>
</form>

<?php else: // done ?>
<div class="alert alert-success">
  <strong>Import complete!</strong>
  Inserted: <strong><?= $inserted ?></strong> &nbsp;|&nbsp;
  Updated: <strong><?= $updated ?></strong> &nbsp;|&nbsp;
  Skipped: <strong><?= $skipped ?></strong> &nbsp;|&nbsp;
  Errors: <strong><?= $errors ?></strong>
</div>
<?php if ($errors > 0): ?>
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body p-0" style="max-height:300px;overflow:auto">
    <table class="table table-sm mb-0">
      <thead class="table-light"><tr><th>Row</th><th>Name</th><th>Result</th><th>Error</th></tr></thead>
      <tbody>
        <?php foreach ($importLog as $l): if ($l['status'] === 'error'): ?>
        <tr class="table-danger">
          <td><?= $l['row'] ?></td>
          <td><?= htmlspecialchars($l['name']) ?></td>
          <td><span class="badge bg-danger">Error</span></td>
          <td class="small text-danger"><?= htmlspecialchars($l['msg'] ?? '') ?></td>
        </tr>
        <?php endif; endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<a href="index.php" class="btn btn-primary"><i class="bi bi-person-vcard me-1"></i>View Employees</a>
<a href="import.php" class="btn btn-outline-secondary">Import More</a>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
