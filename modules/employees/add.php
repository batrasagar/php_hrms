<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db     = getDb();
$user   = currentUser();
$errors = [];
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// All field defaults (keys match DB column names — used as form input names directly)
$rec = [
    // Core / Home
    'CompanyId'         => 0,
    'EmployeeCode'      => '',
    'EnrollId'          => '',
    'Sr'                => '',
    'Name'              => '',
    'FatherName'        => '',
    'Phone'             => '',
    'Email'             => '',
    'JoinDate'          => '',
    'DOB'               => '',
    'Age'               => '',
    'Gender'            => '',
    'PresentAdd'        => '',
    'PermanentAdd'      => '',
    'Department'        => '',
    'Designation'       => '',
    'Contractor'        => '',
    'WeekdayNo'         => '',
    'ShiftNo'           => '',
    'ShiftRotation'     => '',
    'ShiftRotationDate' => '',
    'BasicSalary'       => '',
    'OT'                => 0,
    'Status'            => 'active',
    'DOL'               => '',
    'InterviewDate'     => '',
    'AppointmentDate'   => '',
    'AppDate'           => '',
    'PlaceOfBirth'      => '',
    'Place'             => '',
    'AgeProof'          => '',
    // Profile
    'FatherDOB'            => '',
    'FatherAge'            => '',
    'FatherAadharNo'       => '',
    'RelFatherHusband'     => '',
    'MotherName'           => '',
    'MotherDOB'            => '',
    'MotherAge'            => '',
    'MotherAadharNo'       => '',
    'CharacterCertificate' => '',
    'MaritalStatus'        => '',
    'Qualification'        => '',
    'EmployeeType'         => '',
    'PassportNo'           => '',
    'DriveLicenseNo'       => '',
    'VoterID'              => '',
    'AdhaarID'             => '',
    'Religion'             => '',
    'Nationality'          => '',
    'EmploymentCategory'   => '',
    'EmploymentType'       => '',
    'Grade'                => '',
    'PhoneNo'              => '',
    'BloodGroup'           => '',
    'MachineNo'            => '',
    'Thana'                => '',
    'RegionCode'           => '',
    'District'             => '',
    'Dispensery'           => '',
    'HairColor'            => '',
    'IdentityMark'         => '',
    'Height'               => '',
    'WitnessName1'         => '',
    'WitnessAdd1'          => '',
    'WitnessName2'         => '',
    'WitnessAdd2'          => '',
    // Salary
    'PastExp'               => '',
    'PrevEmployerName'      => '',
    'PrevEmployerCompany'   => '',
    'PrevEmployerContactNo' => '',
    'PrevDOJ'               => '',
    'PrevDOL'               => '',
    'OldPfNo'               => '',
    'OldEsicNo'             => '',
    'EmergencyNo'           => '',
    'UAN'                   => '',
    'PfNo'                  => '',
    'EsiNo'                 => '',
    'PanNo'                 => '',
    'PfAreaCode'            => '',
    'DA'                    => '',
    'Hra'                   => '',
    'Medical'               => '',
    'Conveyence'            => '',
    'OtherAllowance'        => '',
    'CC_Allowance'          => '',
    'GradeAmt'              => '',
    'GrossSalary'           => '',
    'BankName'              => '',
    'BranchName'            => '',
    'BankAcNo'              => '',
    'IFSCCode'              => '',
    'LicPolicyNo'           => '',
    // Nominee
    'FH'                       => '',
    'SpouseName'               => '',
    'SpouseAadharNo'           => '',
    'Nominee1'                 => '',
    'NomineeRelation1'         => '',
    'NomineeDOB1'              => '',
    'NomineeAge1'              => '',
    'NomineeAdd1'              => '',
    'Nominee1FatherHusband'    => '',
    'Nominee1RelFatherHusband' => '',
    'Nominee2'                 => '',
    'NomineeRelation2'         => '',
    'NomineeDOB2'              => '',
    'NomineeAge2'              => '',
    'NomineeAdd2'              => '',
    'Nominee2FatherHusband'    => '',
    'Nominee2RelFatherHusband' => '',
    'Rel1'                     => '',
    'FamilyMember1'            => '',
    'MemberAdhaar1'            => '',
    'Member1DOB'               => '',
    'MemberAge1'               => '',
    'Member1ResidingWith'      => '',
    'Rel2'                     => '',
    'FamilyMember2'            => '',
    'MemberAdhaar2'            => '',
    'Member2DOB'               => '',
    'MemberAge2'               => '',
    'Member2ResidingWith'      => '',
    'Rel3'                     => '',
    'FamilyMember3'            => '',
    'MemberAdhaar3'            => '',
    'Member3DOB'               => '',
    'MemberAge3'               => '',
    'Member3ResidingWith'      => '',
    // Family (children)
    'SD1'                => '',
    'Child1'             => '',
    'ChildAdhaar1'       => '',
    'Child1DOB'          => '',
    'ChildAge1'          => '',
    'Child1ResidingWith' => '',
    'SD2'                => '',
    'Child2'             => '',
    'ChildAdhaar2'       => '',
    'Child2DOB'          => '',
    'ChildAge2'          => '',
    'Child2ResidingWith' => '',
    'SD3'                => '',
    'Child3'             => '',
    'ChildAdhaar3'       => '',
    'Child3DOB'          => '',
    'ChildAge3'          => '',
    'Child3ResidingWith' => '',
    'SD4'                => '',
    'Child4'             => '',
    'ChildAdhaar4'       => '',
    'Child4DOB'          => '',
    'ChildAge4'          => '',
    'Child4ResidingWith' => '',
    'SD5'                => '',
    'Child5'             => '',
    'ChildAdhaar5'       => '',
    'Child5DOB'          => '',
    'ChildAge5'          => '',
    'Child5ResidingWith' => '',
    // Photo
    'Photo'              => '',
];

// Companies for dropdown
if ($user['role'] === 'superadmin') {
    $companies = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['scope_id']]);
    $companies = $stmt->fetchAll();
}

// Datalist sources
$scopeWhere  = $user['role'] === 'superadmin' ? '' : 'JOIN tblCompany c ON c.id = e.CompanyId WHERE c.AdminId = ?';
$scopeParams = $user['role'] === 'superadmin' ? [] : [$user['scope_id']];

$deptStmt = $db->prepare("SELECT DISTINCT Department FROM tblEmployee e $scopeWhere ORDER BY Department");
$deptStmt->execute($scopeParams);
$departments = array_filter(array_column($deptStmt->fetchAll(), 'Department'));

$contStmt = $db->prepare("SELECT DISTINCT Contractor FROM tblEmployee e $scopeWhere ORDER BY Contractor");
$contStmt->execute($scopeParams);
$contractors = array_filter(array_column($contStmt->fetchAll(), 'Contractor'));

$desigStmt = $db->prepare("SELECT DISTINCT Designation FROM tblEmployee e $scopeWhere ORDER BY Designation");
$desigStmt->execute($scopeParams);
$designations = array_filter(array_column($desigStmt->fetchAll(), 'Designation'));

// Load record for edit
if ($editId) {
    if ($user['role'] === 'superadmin') {
        $q = $db->prepare("SELECT * FROM tblEmployee WHERE id=?");
        $q->execute([$editId]);
    } else {
        $q = $db->prepare(
            "SELECT e.* FROM tblEmployee e
             JOIN tblCompany c ON c.id = e.CompanyId AND c.AdminId = ?
             WHERE e.id = ?"
        );
        $q->execute([$user['scope_id'], $editId]);
    }
    $fetched = $q->fetch();
    if (!$fetched) { header('Location: index.php'); exit; }
    foreach ($fetched as $k => $v) {
        if (array_key_exists($k, $rec)) $rec[$k] = $v ?? '';
    }
}

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    csrf_verify();

    foreach (array_keys($rec) as $col) {
        if ($col === 'Photo') continue;
        if ($col === 'OT')        { $rec[$col] = isset($_POST['OT']) ? 1 : 0; }
        elseif ($col === 'CompanyId') { $rec[$col] = (int)($_POST['CompanyId'] ?? 0); }
        else                      { $rec[$col] = trim($_POST[$col] ?? ''); }
    }

    // Validation
    if (!$rec['Name'])      $errors[] = 'Employee name is required.';
    if (!$rec['CompanyId']) $errors[] = 'Please select a company.';
    if ($rec['Email'] && !filter_var($rec['Email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if (!in_array($rec['Status'], ['active','inactive','terminated'])) $rec['Status'] = 'active';

    if (!$errors && $user['role'] !== 'superadmin') {
        $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
        $chk->execute([$rec['CompanyId'], $user['scope_id']]);
        if (!$chk->fetch()) $errors[] = 'Invalid company selected.';
    }

    // Photo
    $photoFilename = $rec['Photo'];
    if (!empty($_POST['photo_data']) && str_starts_with($_POST['photo_data'], 'data:image/')) {
        $parts = explode(',', $_POST['photo_data'], 2);
        if (count($parts) === 2) {
            $imageData = base64_decode($parts[1]);
            if ($imageData !== false && strlen($imageData) > 100) {
                $uploadDir = __DIR__ . '/../../uploads/employees/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $newFilename = uniqid('emp_', true) . '.jpg';
                file_put_contents($uploadDir . $newFilename, $imageData);
                if ($photoFilename && file_exists($uploadDir . $photoFilename)) {
                    unlink($uploadDir . $photoFilename);
                }
                $photoFilename = $newFilename;
            }
        }
    }
    $rec['Photo'] = $photoFilename;

    if (!$errors) {
        $dbCols   = array_column($db->query("SHOW COLUMNS FROM tblEmployee")->fetchAll(), 'Field');
        $saveCols = array_values(array_filter(array_keys($rec), fn($c) => in_array($c, $dbCols, true)));
        $vals     = array_map(fn($c) => ($rec[$c] === '' ? null : $rec[$c]), $saveCols);

        try {
            if ($editId) {
                $set = implode(', ', array_map(fn($c) => "`$c`=?", $saveCols));
                $db->prepare("UPDATE tblEmployee SET $set, UpdatedAt=NOW() WHERE id=?")
                   ->execute([...$vals, $editId]);
                $_SESSION['flash'] = 'Employee updated.';
            } else {
                $cols = '`' . implode('`, `', $saveCols) . '`';
                $ph   = implode(', ', array_fill(0, count($saveCols), '?'));
                $db->prepare("INSERT INTO tblEmployee ($cols) VALUES ($ph)")->execute($vals);
                $_SESSION['flash'] = 'Employee added.';
            }

            // Sync EnrollId → tblDeviceEnrollment for all devices of this company
            $empCode  = $rec['EmployeeCode'];
            $enrollId = $rec['EnrollId'];
            $coId     = $rec['CompanyId'];
            if ($empCode && $enrollId && $coId) {
                $devStmt = $db->prepare(
                    "SELECT d.SerialNumber FROM tblDevices d
                     JOIN tblCompany c ON c.Name = d.Company
                     WHERE c.id = ?"
                );
                $devStmt->execute([$coId]);
                $serials = $devStmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($serials as $serial) {
                    // Remove old enrollment slot for this employee on this device
                    $db->prepare("DELETE FROM tblDeviceEnrollment WHERE DeviceSerial=? AND EmpCode=? AND CompanyId=?")
                       ->execute([$serial, $empCode, $coId]);
                    // Insert with new EnrollId (ignore if slot already taken by someone else)
                    try {
                        $db->prepare("INSERT INTO tblDeviceEnrollment (DeviceSerial, EnrollId, CompanyId, EmpCode) VALUES (?,?,?,?)")
                           ->execute([$serial, $enrollId, $coId, $empCode]);
                    } catch (PDOException $dup) { /* slot taken on this device — skip */ }
                }
            }

            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'redirect' => 'index.php']);
                exit;
            }
            header('Location: index.php'); exit;
        } catch (PDOException $e) {
            $errors[] = 'Save failed: ' . $e->getMessage();
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
}

// Helper — HTML-safe value
function v($rec, $k) { return htmlspecialchars($rec[$k] ?? ''); }
// Helper — select option
function opt($val, $cur, $label) {
    $s = ($cur === $val) ? ' selected' : '';
    return "<option value=\"$val\"$s>$label</option>";
}

$pageTitle  = 'Employees';
$activePage = 'employees';
require_once __DIR__ . '/../../includes/header.php';
?>

<?php foreach ($errors as $e): ?>
<div class="alert alert-danger py-2"><?= htmlspecialchars($e) ?></div>
<?php endforeach; ?>

<div class="card border-0 shadow-sm">
  <div class="card-header bg-white p-0 d-flex justify-content-between align-items-center pe-3">
    <ul class="nav nav-tabs border-0 px-3" id="empTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active px-3" id="t-home-tab" data-bs-toggle="tab" data-bs-target="#t-home" type="button">
          <i class="bi bi-house-door me-1"></i>Home
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link px-3" id="t-profile-tab" data-bs-toggle="tab" data-bs-target="#t-profile" type="button">
          <i class="bi bi-person-vcard me-1"></i>Profile
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link px-3" id="t-salary-tab" data-bs-toggle="tab" data-bs-target="#t-salary" type="button">
          <i class="bi bi-cash-coin me-1"></i>Salary
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link px-3" id="t-nominee-tab" data-bs-toggle="tab" data-bs-target="#t-nominee" type="button">
          <i class="bi bi-people me-1"></i>Nominee
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link px-3" id="t-family-tab" data-bs-toggle="tab" data-bs-target="#t-family" type="button">
          <i class="bi bi-person-hearts me-1"></i>Family
        </button>
      </li>
    </ul>
    <span class="small text-muted"><?= $editId ? 'Edit Employee' : 'Add Employee' ?></span>
  </div>

  <div class="card-body">
    <form method="POST" id="empForm" data-ajax>
      <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
      <input type="hidden" name="photo_data" id="photoData">

      <div class="tab-content">

        <!-- ===== HOME TAB ===== -->
        <div class="tab-pane fade show active" id="t-home" role="tabpanel">
          <div class="row g-2">

            <!-- Photo -->
            <div class="col-12 col-sm-auto d-flex flex-column align-items-center mb-2">
              <div id="photoPreviewWrap" style="width:110px;height:110px;border:2px dashed #dee2e6;border-radius:8px;overflow:hidden;background:#f8f9fa;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <?php if (!empty($rec['Photo'])): ?>
                  <img id="photoPreview" src="<?= BASE_URL ?>/uploads/employees/<?= v($rec,'Photo') ?>" style="width:100%;height:100%;object-fit:cover">
                <?php else: ?>
                  <i class="bi bi-person-circle text-muted" style="font-size:3.5rem" id="photoPlaceholder"></i>
                  <img id="photoPreview" style="width:100%;height:100%;object-fit:cover;display:none">
                <?php endif; ?>
              </div>
              <input type="file" id="photoFileInput" accept="image/jpeg,image/png,image/gif,image/webp" capture="environment"
                     class="form-control form-control-sm mt-2" style="width:110px;font-size:10px">
            </div>

            <div class="col">
              <div class="row g-2">
                <div class="col-12">
                  <label class="form-label">Company <span class="text-danger">*</span></label>
                  <select name="CompanyId" class="form-select form-select-sm" required>
                    <option value="">— Select Company —</option>
                    <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $rec['CompanyId'] == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['Name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-sm-4">
                  <label class="form-label">Employee Code</label>
                  <input type="text" name="EmployeeCode" class="form-control form-control-sm" value="<?= v($rec,'EmployeeCode') ?>" placeholder="EMP-001">
                </div>
                <div class="col-sm-4">
                  <label class="form-label">Enroll ID <span class="text-muted small">(biometric)</span></label>
                  <input type="text" name="EnrollId" class="form-control form-control-sm" value="<?= v($rec,'EnrollId') ?>">
                </div>
                <div class="col-sm-4">
                  <label class="form-label">Sr. No.</label>
                  <input type="number" name="Sr" class="form-control form-control-sm" value="<?= v($rec,'Sr') ?>">
                </div>
              </div>
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-sm-4">
              <label class="form-label">Full Name <span class="text-danger">*</span></label>
              <input type="text" name="Name" class="form-control form-control-sm" required value="<?= v($rec,'Name') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Father's Name</label>
              <input type="text" name="FatherName" class="form-control form-control-sm" value="<?= v($rec,'FatherName') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Mobile</label>
              <input type="text" name="Phone" class="form-control form-control-sm" value="<?= v($rec,'Phone') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Email</label>
              <input type="email" name="Email" class="form-control form-control-sm" value="<?= v($rec,'Email') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Date of Birth</label>
              <input type="date" name="DOB" id="DOB" class="form-control form-control-sm" value="<?= v($rec,'DOB') ?>">
            </div>
            <div class="col-sm-2">
              <label class="form-label">Age</label>
              <input type="text" name="Age" id="Age" class="form-control form-control-sm" value="<?= v($rec,'Age') ?>" placeholder="Auto">
            </div>
            <div class="col-sm-2">
              <label class="form-label">Gender</label>
              <select name="Gender" class="form-select form-select-sm">
                <option value="">—</option>
                <?= opt('Male', $rec['Gender'], 'Male') ?>
                <?= opt('Female', $rec['Gender'], 'Female') ?>
                <?= opt('Other', $rec['Gender'], 'Other') ?>
              </select>
            </div>
            <div class="col-sm-4">
              <label class="form-label">Place of Birth</label>
              <input type="text" name="PlaceOfBirth" class="form-control form-control-sm" value="<?= v($rec,'PlaceOfBirth') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Place</label>
              <input type="text" name="Place" class="form-control form-control-sm" value="<?= v($rec,'Place') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Age Proof</label>
              <input type="text" name="AgeProof" class="form-control form-control-sm" value="<?= v($rec,'AgeProof') ?>" placeholder="School cert / Aadhar / etc.">
            </div>

            <div class="col-12">
              <label class="form-label">Present Address</label>
              <textarea name="PresentAdd" class="form-control form-control-sm" rows="2"><?= v($rec,'PresentAdd') ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Permanent Address</label>
              <textarea name="PermanentAdd" class="form-control form-control-sm" rows="2"><?= v($rec,'PermanentAdd') ?></textarea>
            </div>

            <div class="col-12"><hr class="my-1"><p class="text-muted small text-uppercase fw-semibold mb-0">Employment</p></div>

            <div class="col-sm-4">
              <label class="form-label">Department</label>
              <input type="text" name="Department" class="form-control form-control-sm" list="deptList" value="<?= v($rec,'Department') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Designation</label>
              <input type="text" name="Designation" class="form-control form-control-sm" list="desigList" value="<?= v($rec,'Designation') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Contractor</label>
              <input type="text" name="Contractor" class="form-control form-control-sm" list="contractorList" value="<?= v($rec,'Contractor') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Date of Joining</label>
              <input type="date" name="JoinDate" class="form-control form-control-sm" value="<?= v($rec,'JoinDate') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Date of Leaving</label>
              <input type="date" name="DOL" class="form-control form-control-sm" value="<?= v($rec,'DOL') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Interview Date</label>
              <input type="date" name="InterviewDate" class="form-control form-control-sm" value="<?= v($rec,'InterviewDate') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Appointment Date</label>
              <input type="date" name="AppointmentDate" class="form-control form-control-sm" value="<?= v($rec,'AppointmentDate') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">App Date</label>
              <input type="date" name="AppDate" class="form-control form-control-sm" value="<?= v($rec,'AppDate') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Weekday No.</label>
              <input type="number" name="WeekdayNo" class="form-control form-control-sm" value="<?= v($rec,'WeekdayNo') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Shift No.</label>
              <input type="number" name="ShiftNo" class="form-control form-control-sm" value="<?= v($rec,'ShiftNo') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Shift Rotation</label>
              <input type="text" name="ShiftRotation" class="form-control form-control-sm" value="<?= v($rec,'ShiftRotation') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Shift Rotation Date</label>
              <input type="date" name="ShiftRotationDate" class="form-control form-control-sm" value="<?= v($rec,'ShiftRotationDate') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Status</label>
              <select name="Status" class="form-select form-select-sm">
                <?= opt('active', $rec['Status'], 'Active') ?>
                <?= opt('inactive', $rec['Status'], 'Inactive') ?>
                <?= opt('terminated', $rec['Status'], 'Terminated') ?>
              </select>
            </div>
            <div class="col-sm-3 d-flex align-items-end pb-1">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="OT" id="OT" value="1" <?= $rec['OT'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="OT">Eligible for OT</label>
              </div>
            </div>

            <!-- Datalists -->
            <datalist id="deptList">
              <?php foreach ($departments as $d): ?><option value="<?= htmlspecialchars($d) ?>"><?php endforeach; ?>
            </datalist>
            <datalist id="desigList">
              <?php foreach ($designations as $d): ?><option value="<?= htmlspecialchars($d) ?>"><?php endforeach; ?>
            </datalist>
            <datalist id="contractorList">
              <?php foreach ($contractors as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?php endforeach; ?>
            </datalist>
          </div>
        </div><!-- /t-home -->

        <!-- ===== PROFILE TAB ===== -->
        <div class="tab-pane fade" id="t-profile" role="tabpanel">
          <div class="row g-2">
            <div class="col-12"><p class="text-muted small text-uppercase fw-semibold mb-0">Parents</p></div>

            <div class="col-sm-3">
              <label class="form-label">Father's DOB</label>
              <input type="date" name="FatherDOB" id="FatherDOB" class="form-control form-control-sm" value="<?= v($rec,'FatherDOB') ?>">
            </div>
            <div class="col-sm-2">
              <label class="form-label">Father's Age</label>
              <input type="text" name="FatherAge" id="FatherAge" class="form-control form-control-sm" value="<?= v($rec,'FatherAge') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Father's Aadhar No.</label>
              <input type="text" name="FatherAadharNo" class="form-control form-control-sm" value="<?= v($rec,'FatherAadharNo') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Relation (Father/Husband)</label>
              <select name="RelFatherHusband" class="form-select form-select-sm">
                <option value="">—</option>
                <?= opt('Father', $rec['RelFatherHusband'], 'Father') ?>
                <?= opt('Husband', $rec['RelFatherHusband'], 'Husband') ?>
              </select>
            </div>
            <div class="col-sm-3">
              <label class="form-label">Mother's Name</label>
              <input type="text" name="MotherName" class="form-control form-control-sm" value="<?= v($rec,'MotherName') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Mother's DOB</label>
              <input type="date" name="MotherDOB" id="MotherDOB" class="form-control form-control-sm" value="<?= v($rec,'MotherDOB') ?>">
            </div>
            <div class="col-sm-2">
              <label class="form-label">Mother's Age</label>
              <input type="text" name="MotherAge" id="MotherAge" class="form-control form-control-sm" value="<?= v($rec,'MotherAge') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Mother's Aadhar No.</label>
              <input type="text" name="MotherAadharNo" class="form-control form-control-sm" value="<?= v($rec,'MotherAadharNo') ?>">
            </div>

            <div class="col-12"><hr class="my-1"><p class="text-muted small text-uppercase fw-semibold mb-0">Identity &amp; Documents</p></div>

            <div class="col-sm-3">
              <label class="form-label">Marital Status</label>
              <select name="MaritalStatus" class="form-select form-select-sm">
                <option value="">—</option>
                <?= opt('Single', $rec['MaritalStatus'], 'Single') ?>
                <?= opt('Married', $rec['MaritalStatus'], 'Married') ?>
                <?= opt('Divorced', $rec['MaritalStatus'], 'Divorced') ?>
                <?= opt('Widowed', $rec['MaritalStatus'], 'Widowed') ?>
              </select>
            </div>
            <div class="col-sm-3">
              <label class="form-label">Aadhar ID</label>
              <input type="text" name="AdhaarID" class="form-control form-control-sm" value="<?= v($rec,'AdhaarID') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">PAN No.</label>
              <input type="text" name="PanNo" class="form-control form-control-sm" value="<?= v($rec,'PanNo') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Voter ID</label>
              <input type="text" name="VoterID" class="form-control form-control-sm" value="<?= v($rec,'VoterID') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Passport No.</label>
              <input type="text" name="PassportNo" class="form-control form-control-sm" value="<?= v($rec,'PassportNo') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Driving Licence No.</label>
              <input type="text" name="DriveLicenseNo" class="form-control form-control-sm" value="<?= v($rec,'DriveLicenseNo') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Blood Group</label>
              <select name="BloodGroup" class="form-select form-select-sm">
                <option value="">—</option>
                <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                  <?= opt($bg, $rec['BloodGroup'], $bg) ?>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-3">
              <label class="form-label">Character Certificate</label>
              <input type="text" name="CharacterCertificate" class="form-control form-control-sm" value="<?= v($rec,'CharacterCertificate') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Qualification</label>
              <input type="text" name="Qualification" class="form-control form-control-sm" value="<?= v($rec,'Qualification') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Employee Type</label>
              <input type="text" name="EmployeeType" class="form-control form-control-sm" value="<?= v($rec,'EmployeeType') ?>" placeholder="Permanent / Contract">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Employment Category</label>
              <input type="text" name="EmploymentCategory" class="form-control form-control-sm" value="<?= v($rec,'EmploymentCategory') ?>" placeholder="Worker / Staff">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Employment Type</label>
              <input type="text" name="EmploymentType" class="form-control form-control-sm" value="<?= v($rec,'EmploymentType') ?>" placeholder="Full-Time / Part-Time">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Grade</label>
              <input type="text" name="Grade" class="form-control form-control-sm" value="<?= v($rec,'Grade') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Religion</label>
              <input type="text" name="Religion" class="form-control form-control-sm" value="<?= v($rec,'Religion') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Nationality</label>
              <input type="text" name="Nationality" class="form-control form-control-sm" value="<?= v($rec,'Nationality') ?>" placeholder="Indian">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Phone No. (Alt)</label>
              <input type="text" name="PhoneNo" class="form-control form-control-sm" value="<?= v($rec,'PhoneNo') ?>">
            </div>

            <div class="col-12"><hr class="my-1"><p class="text-muted small text-uppercase fw-semibold mb-0">Physical Description</p></div>

            <div class="col-sm-3">
              <label class="form-label">Height</label>
              <input type="text" name="Height" class="form-control form-control-sm" value="<?= v($rec,'Height') ?>" placeholder="cm / ft-in">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Hair Color</label>
              <input type="text" name="HairColor" class="form-control form-control-sm" value="<?= v($rec,'HairColor') ?>">
            </div>
            <div class="col-sm-6">
              <label class="form-label">Identity Mark</label>
              <input type="text" name="IdentityMark" class="form-control form-control-sm" value="<?= v($rec,'IdentityMark') ?>">
            </div>

            <div class="col-12"><hr class="my-1"><p class="text-muted small text-uppercase fw-semibold mb-0">Address Details</p></div>

            <div class="col-sm-3">
              <label class="form-label">Machine No.</label>
              <input type="text" name="MachineNo" class="form-control form-control-sm" value="<?= v($rec,'MachineNo') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Thana</label>
              <input type="text" name="Thana" class="form-control form-control-sm" value="<?= v($rec,'Thana') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Region Code</label>
              <input type="text" name="RegionCode" class="form-control form-control-sm" value="<?= v($rec,'RegionCode') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">District</label>
              <input type="text" name="District" class="form-control form-control-sm" value="<?= v($rec,'District') ?>">
            </div>
            <div class="col-sm-6">
              <label class="form-label">Dispensery</label>
              <input type="text" name="Dispensery" class="form-control form-control-sm" value="<?= v($rec,'Dispensery') ?>">
            </div>

            <div class="col-12"><hr class="my-1"><p class="text-muted small text-uppercase fw-semibold mb-0">Witnesses</p></div>

            <div class="col-sm-4">
              <label class="form-label">Witness 1 Name</label>
              <input type="text" name="WitnessName1" class="form-control form-control-sm" value="<?= v($rec,'WitnessName1') ?>">
            </div>
            <div class="col-sm-8">
              <label class="form-label">Witness 1 Address</label>
              <input type="text" name="WitnessAdd1" class="form-control form-control-sm" value="<?= v($rec,'WitnessAdd1') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Witness 2 Name</label>
              <input type="text" name="WitnessName2" class="form-control form-control-sm" value="<?= v($rec,'WitnessName2') ?>">
            </div>
            <div class="col-sm-8">
              <label class="form-label">Witness 2 Address</label>
              <input type="text" name="WitnessAdd2" class="form-control form-control-sm" value="<?= v($rec,'WitnessAdd2') ?>">
            </div>
          </div>
        </div><!-- /t-profile -->

        <!-- ===== SALARY TAB ===== -->
        <div class="tab-pane fade" id="t-salary" role="tabpanel">
          <div class="row g-2">
            <div class="col-12"><p class="text-muted small text-uppercase fw-semibold mb-0">Previous Employment</p></div>

            <div class="col-sm-3">
              <label class="form-label">Past Experience</label>
              <input type="text" name="PastExp" class="form-control form-control-sm" value="<?= v($rec,'PastExp') ?>" placeholder="2 yrs 3 mths">
            </div>
            <div class="col-sm-5">
              <label class="form-label">Prev. Employer Name</label>
              <input type="text" name="PrevEmployerName" class="form-control form-control-sm" value="<?= v($rec,'PrevEmployerName') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Prev. Employer Company</label>
              <input type="text" name="PrevEmployerCompany" class="form-control form-control-sm" value="<?= v($rec,'PrevEmployerCompany') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Prev. Employer Contact</label>
              <input type="text" name="PrevEmployerContactNo" class="form-control form-control-sm" value="<?= v($rec,'PrevEmployerContactNo') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Prev. DOJ</label>
              <input type="date" name="PrevDOJ" class="form-control form-control-sm" value="<?= v($rec,'PrevDOJ') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Prev. DOL</label>
              <input type="date" name="PrevDOL" class="form-control form-control-sm" value="<?= v($rec,'PrevDOL') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Emergency No.</label>
              <input type="text" name="EmergencyNo" class="form-control form-control-sm" value="<?= v($rec,'EmergencyNo') ?>">
            </div>

            <div class="col-12"><hr class="my-1"><p class="text-muted small text-uppercase fw-semibold mb-0">Statutory Numbers</p></div>

            <div class="col-sm-3">
              <label class="form-label">UAN</label>
              <input type="text" name="UAN" class="form-control form-control-sm" value="<?= v($rec,'UAN') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">PF No.</label>
              <input type="text" name="PfNo" class="form-control form-control-sm" value="<?= v($rec,'PfNo') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Old PF No.</label>
              <input type="text" name="OldPfNo" class="form-control form-control-sm" value="<?= v($rec,'OldPfNo') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">PF Area Code</label>
              <input type="text" name="PfAreaCode" class="form-control form-control-sm" value="<?= v($rec,'PfAreaCode') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">ESI No.</label>
              <input type="text" name="EsiNo" class="form-control form-control-sm" value="<?= v($rec,'EsiNo') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Old ESIC No.</label>
              <input type="text" name="OldEsicNo" class="form-control form-control-sm" value="<?= v($rec,'OldEsicNo') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">LIC Policy No.</label>
              <input type="text" name="LicPolicyNo" class="form-control form-control-sm" value="<?= v($rec,'LicPolicyNo') ?>">
            </div>

            <div class="col-12"><hr class="my-1"><p class="text-muted small text-uppercase fw-semibold mb-0">Salary Components</p></div>

            <div class="col-sm-2">
              <label class="form-label">Basic</label>
              <input type="number" step="0.01" name="BasicSalary" id="sal_basic" class="form-control form-control-sm sal-comp" value="<?= v($rec,'BasicSalary') ?>">
            </div>
            <div class="col-sm-2">
              <label class="form-label">DA</label>
              <input type="number" step="0.01" name="DA" id="sal_da" class="form-control form-control-sm sal-comp" value="<?= v($rec,'DA') ?>">
            </div>
            <div class="col-sm-2">
              <label class="form-label">HRA</label>
              <input type="number" step="0.01" name="Hra" id="sal_hra" class="form-control form-control-sm sal-comp" value="<?= v($rec,'Hra') ?>">
            </div>
            <div class="col-sm-2">
              <label class="form-label">Medical</label>
              <input type="number" step="0.01" name="Medical" id="sal_medical" class="form-control form-control-sm sal-comp" value="<?= v($rec,'Medical') ?>">
            </div>
            <div class="col-sm-2">
              <label class="form-label">Conveyance</label>
              <input type="number" step="0.01" name="Conveyence" id="sal_conv" class="form-control form-control-sm sal-comp" value="<?= v($rec,'Conveyence') ?>">
            </div>
            <div class="col-sm-2">
              <label class="form-label">Other Allow.</label>
              <input type="number" step="0.01" name="OtherAllowance" id="sal_other" class="form-control form-control-sm sal-comp" value="<?= v($rec,'OtherAllowance') ?>">
            </div>
            <div class="col-sm-2">
              <label class="form-label">CC Allow.</label>
              <input type="number" step="0.01" name="CC_Allowance" id="sal_cc" class="form-control form-control-sm sal-comp" value="<?= v($rec,'CC_Allowance') ?>">
            </div>
            <div class="col-sm-2">
              <label class="form-label">Grade Amt.</label>
              <input type="number" step="0.01" name="GradeAmt" id="sal_grade" class="form-control form-control-sm sal-comp" value="<?= v($rec,'GradeAmt') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label fw-semibold">Gross Salary <span class="badge bg-primary ms-1">Auto</span></label>
              <input type="number" step="0.01" name="GrossSalary" id="GrossSalary" class="form-control form-control-sm fw-semibold" value="<?= v($rec,'GrossSalary') ?>" style="background:#f0f7ff">
            </div>

            <div class="col-12"><hr class="my-1"><p class="text-muted small text-uppercase fw-semibold mb-0">Bank Details</p></div>

            <div class="col-sm-4">
              <label class="form-label">Bank Name</label>
              <input type="text" name="BankName" class="form-control form-control-sm" value="<?= v($rec,'BankName') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Branch Name</label>
              <input type="text" name="BranchName" class="form-control form-control-sm" value="<?= v($rec,'BranchName') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Account No.</label>
              <input type="text" name="BankAcNo" class="form-control form-control-sm" value="<?= v($rec,'BankAcNo') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">IFSC Code</label>
              <input type="text" name="IFSCCode" class="form-control form-control-sm" value="<?= v($rec,'IFSCCode') ?>">
            </div>
          </div>
        </div><!-- /t-salary -->

        <!-- ===== NOMINEE TAB ===== -->
        <div class="tab-pane fade" id="t-nominee" role="tabpanel">
          <div class="row g-2">
            <div class="col-12"><p class="text-muted small text-uppercase fw-semibold mb-0">Spouse</p></div>

            <div class="col-sm-4">
              <label class="form-label">FH (Father/Husband Name)</label>
              <input type="text" name="FH" class="form-control form-control-sm" value="<?= v($rec,'FH') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Spouse Name</label>
              <input type="text" name="SpouseName" class="form-control form-control-sm" value="<?= v($rec,'SpouseName') ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Spouse Aadhar No.</label>
              <input type="text" name="SpouseAadharNo" class="form-control form-control-sm" value="<?= v($rec,'SpouseAadharNo') ?>">
            </div>

            <div class="col-12"><hr class="my-1"><p class="text-muted small text-uppercase fw-semibold mb-0">Nominee 1</p></div>

            <div class="col-sm-4">
              <label class="form-label">Nominee 1 Name</label>
              <input type="text" name="Nominee1" class="form-control form-control-sm" value="<?= v($rec,'Nominee1') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Relation</label>
              <input type="text" name="NomineeRelation1" class="form-control form-control-sm" value="<?= v($rec,'NomineeRelation1') ?>" placeholder="Wife / Son / Daughter">
            </div>
            <div class="col-sm-3">
              <label class="form-label">DOB</label>
              <input type="date" name="NomineeDOB1" id="NomineeDOB1" class="form-control form-control-sm" value="<?= v($rec,'NomineeDOB1') ?>">
            </div>
            <div class="col-sm-2">
              <label class="form-label">Age</label>
              <input type="text" name="NomineeAge1" id="NomineeAge1" class="form-control form-control-sm" value="<?= v($rec,'NomineeAge1') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Nominee 1 Address</label>
              <textarea name="NomineeAdd1" class="form-control form-control-sm" rows="2"><?= v($rec,'NomineeAdd1') ?></textarea>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Nominee 1 Father/Husband</label>
              <input type="text" name="Nominee1FatherHusband" class="form-control form-control-sm" value="<?= v($rec,'Nominee1FatherHusband') ?>">
            </div>
            <div class="col-sm-6">
              <label class="form-label">Relation (Father/Husband)</label>
              <input type="text" name="Nominee1RelFatherHusband" class="form-control form-control-sm" value="<?= v($rec,'Nominee1RelFatherHusband') ?>">
            </div>

            <div class="col-12"><hr class="my-1"><p class="text-muted small text-uppercase fw-semibold mb-0">Nominee 2</p></div>

            <div class="col-sm-4">
              <label class="form-label">Nominee 2 Name</label>
              <input type="text" name="Nominee2" class="form-control form-control-sm" value="<?= v($rec,'Nominee2') ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Relation</label>
              <input type="text" name="NomineeRelation2" class="form-control form-control-sm" value="<?= v($rec,'NomineeRelation2') ?>" placeholder="Wife / Son / Daughter">
            </div>
            <div class="col-sm-3">
              <label class="form-label">DOB</label>
              <input type="date" name="NomineeDOB2" id="NomineeDOB2" class="form-control form-control-sm" value="<?= v($rec,'NomineeDOB2') ?>">
            </div>
            <div class="col-sm-2">
              <label class="form-label">Age</label>
              <input type="text" name="NomineeAge2" id="NomineeAge2" class="form-control form-control-sm" value="<?= v($rec,'NomineeAge2') ?>">
            </div>
            <div class="col-12">
              <label class="form-label">Nominee 2 Address</label>
              <textarea name="NomineeAdd2" class="form-control form-control-sm" rows="2"><?= v($rec,'NomineeAdd2') ?></textarea>
            </div>
            <div class="col-sm-6">
              <label class="form-label">Nominee 2 Father/Husband</label>
              <input type="text" name="Nominee2FatherHusband" class="form-control form-control-sm" value="<?= v($rec,'Nominee2FatherHusband') ?>">
            </div>
            <div class="col-sm-6">
              <label class="form-label">Relation (Father/Husband)</label>
              <input type="text" name="Nominee2RelFatherHusband" class="form-control form-control-sm" value="<?= v($rec,'Nominee2RelFatherHusband') ?>">
            </div>

            <div class="col-12"><hr class="my-1"><p class="text-muted small text-uppercase fw-semibold mb-0">Family Members</p></div>

            <?php foreach ([1,2,3] as $n): ?>
            <div class="col-sm-2">
              <label class="form-label">Relation <?= $n ?></label>
              <input type="text" name="Rel<?= $n ?>" class="form-control form-control-sm" value="<?= v($rec,"Rel$n") ?>" placeholder="Brother / Sister">
            </div>
            <div class="col-sm-3">
              <label class="form-label">Member <?= $n ?> Name</label>
              <input type="text" name="FamilyMember<?= $n ?>" class="form-control form-control-sm" value="<?= v($rec,"FamilyMember$n") ?>">
            </div>
            <div class="col-sm-2">
              <label class="form-label">Aadhar</label>
              <input type="text" name="MemberAdhaar<?= $n ?>" class="form-control form-control-sm" value="<?= v($rec,"MemberAdhaar$n") ?>">
            </div>
            <div class="col-sm-3">
              <label class="form-label">DOB</label>
              <input type="date" name="Member<?= $n ?>DOB" id="Member<?= $n ?>DOB" class="form-control form-control-sm" value="<?= v($rec,"Member{$n}DOB") ?>">
            </div>
            <div class="col-sm-1">
              <label class="form-label">Age</label>
              <input type="text" name="MemberAge<?= $n ?>" id="MemberAge<?= $n ?>" class="form-control form-control-sm" value="<?= v($rec,"MemberAge$n") ?>">
            </div>
            <div class="col-sm-1">
              <label class="form-label">Residing</label>
              <input type="text" name="Member<?= $n ?>ResidingWith" class="form-control form-control-sm" value="<?= v($rec,"Member{$n}ResidingWith") ?>" placeholder="Yes/No">
            </div>
            <?php endforeach; ?>

          </div>
        </div><!-- /t-nominee -->

        <!-- ===== FAMILY TAB (Children) ===== -->
        <div class="tab-pane fade" id="t-family" role="tabpanel">
          <div class="row g-2">
            <div class="col-12"><p class="text-muted small text-uppercase fw-semibold mb-0">Children</p></div>

            <?php foreach ([1,2,3,4,5] as $n): ?>
            <div class="col-sm-2">
              <label class="form-label">Child <?= $n ?> Type</label>
              <select name="SD<?= $n ?>" class="form-select form-select-sm">
                <option value="">—</option>
                <?= opt('Son', $rec["SD$n"], 'Son') ?>
                <?= opt('Daughter', $rec["SD$n"], 'Daughter') ?>
              </select>
            </div>
            <div class="col-sm-3">
              <label class="form-label">Child <?= $n ?> Name</label>
              <input type="text" name="Child<?= $n ?>" class="form-control form-control-sm" value="<?= v($rec,"Child$n") ?>">
            </div>
            <div class="col-sm-2">
              <label class="form-label">Aadhar</label>
              <input type="text" name="ChildAdhaar<?= $n ?>" class="form-control form-control-sm" value="<?= v($rec,"ChildAdhaar$n") ?>">
            </div>
            <div class="col-sm-2">
              <label class="form-label">DOB</label>
              <input type="date" name="Child<?= $n ?>DOB" id="Child<?= $n ?>DOB" class="form-control form-control-sm" value="<?= v($rec,"Child{$n}DOB") ?>">
            </div>
            <div class="col-sm-1">
              <label class="form-label">Age</label>
              <input type="text" name="ChildAge<?= $n ?>" id="ChildAge<?= $n ?>" class="form-control form-control-sm" value="<?= v($rec,"ChildAge$n") ?>">
            </div>
            <div class="col-sm-2">
              <label class="form-label">Residing With</label>
              <input type="text" name="Child<?= $n ?>ResidingWith" class="form-control form-control-sm" value="<?= v($rec,"Child{$n}ResidingWith") ?>" placeholder="Yes/No">
            </div>
            <?php endforeach; ?>

          </div>
        </div><!-- /t-family -->

      </div><!-- /tab-content -->

      <hr class="mt-3 mb-2">
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?= $editId ? 'Save Changes' : 'Add Employee' ?></button>
        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>

<!-- Crop Modal -->
<div class="modal fade" id="cropModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down" style="max-width:min(520px,96vw)">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title mb-0">Crop Photo</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-2" style="max-height:min(420px,60vh);overflow:hidden">
        <img id="cropImage" style="max-width:100%;display:block">
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-sm btn-primary" id="btnApplyCrop">Apply Crop</button>
      </div>
    </div>
  </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<?php
$extraJs = <<<'JS'
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script>
// Photo crop
let cropper = null;
const cropModal = new bootstrap.Modal(document.getElementById('cropModal'));
document.getElementById('photoFileInput').addEventListener('change', function(){
  const file = this.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    const img = document.getElementById('cropImage');
    img.src = e.target.result;
    if (cropper) { cropper.destroy(); cropper = null; }
    cropModal.show();
    document.getElementById('cropModal').addEventListener('shown.bs.modal', () => {
      if (!cropper) cropper = new Cropper(img, { aspectRatio: 1, viewMode: 1, autoCropArea: 0.9 });
    }, { once: true });
  };
  reader.readAsDataURL(file);
});
document.getElementById('btnApplyCrop').addEventListener('click', function(){
  if (!cropper) return;
  const canvas = cropper.getCroppedCanvas({ width: 300, height: 300 });
  const dataUrl = canvas.toDataURL('image/jpeg', 0.85);
  document.getElementById('photoData').value = dataUrl;
  const preview = document.getElementById('photoPreview');
  const placeholder = document.getElementById('photoPlaceholder');
  preview.src = dataUrl;
  preview.style.display = 'block';
  if (placeholder) placeholder.style.display = 'none';
  cropModal.hide();
});

// Age from DOB helper
function calcAge(dob) {
  if (!dob) return '';
  const d = new Date(dob), now = new Date();
  let age = now.getFullYear() - d.getFullYear();
  const m = now.getMonth() - d.getMonth();
  if (m < 0 || (m === 0 && now.getDate() < d.getDate())) age--;
  return age >= 0 ? String(age) : '';
}

// Wire up DOB → Age auto-fill pairs
[
  ['DOB',        'Age'],
  ['FatherDOB',  'FatherAge'],
  ['MotherDOB',  'MotherAge'],
  ['NomineeDOB1','NomineeAge1'],
  ['NomineeDOB2','NomineeAge2'],
  ['Member1DOB', 'MemberAge1'],
  ['Member2DOB', 'MemberAge2'],
  ['Member3DOB', 'MemberAge3'],
  ['Child1DOB',  'ChildAge1'],
  ['Child2DOB',  'ChildAge2'],
  ['Child3DOB',  'ChildAge3'],
  ['Child4DOB',  'ChildAge4'],
  ['Child5DOB',  'ChildAge5'],
].forEach(([dobId, ageId]) => {
  const dobEl = document.getElementById(dobId);
  const ageEl = document.getElementById(ageId);
  if (!dobEl || !ageEl) return;
  dobEl.addEventListener('change', () => {
    if (!ageEl.value) ageEl.value = calcAge(dobEl.value);
  });
});

// Gross salary auto-calc
function recalcGross() {
  const ids = ['sal_basic','sal_da','sal_hra','sal_medical','sal_conv','sal_other','sal_cc','sal_grade'];
  const total = ids.reduce((sum, id) => {
    const el = document.getElementById(id);
    return sum + (el ? (parseFloat(el.value) || 0) : 0);
  }, 0);
  const gs = document.getElementById('GrossSalary');
  if (gs) gs.value = total > 0 ? total.toFixed(2) : '';
}
document.querySelectorAll('.sal-comp').forEach(el => el.addEventListener('input', recalcGross));

// Note: form submission is handled by the global data-ajax handler in footer.php.
// Do not add a local submit handler here — it would double-submit and create duplicate records.
</script>
JS;
require_once __DIR__ . '/../../includes/footer.php';
?>
