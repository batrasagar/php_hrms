<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

try { $db->query("SELECT 1 FROM tblDocTemplate LIMIT 1"); }
catch (PDOException $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

if ($user['role'] === 'superadmin') {
    $companiesDd = $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
} else {
    $s = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $s->execute([$user['id']]);
    $companiesDd = $s->fetchAll();
}
$fCompany = (int)($_REQUEST['company'] ?? ($companiesDd[0]['id'] ?? 0));
if ($fCompany && $user['role'] === 'admin') {
    $chk = $db->prepare("SELECT id FROM tblCompany WHERE id=? AND AdminId=?");
    $chk->execute([$fCompany, $user['id']]); if (!$chk->fetch()) $fCompany = 0;
}

$fEmp = (int)($_GET['emp'] ?? 0);
$fTpl = (int)($_GET['tpl'] ?? 0);
$msg  = ''; $msgType = 'success';

// ── DELETE document ────────────────────────────────────────────────────────────
if (isset($_GET['del'])) {
    $delId = (int)$_GET['del'];
    $db->prepare("DELETE FROM tblEmployeeDocument WHERE id=? AND CompanyId=?")->execute([$delId, $fCompany]);
    header("Location: issue.php?company=$fCompany&emp=$fEmp"); exit;
}

// ── POST: save generated document ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fCompany) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    csrf_verify();
    $empId  = (int)($_POST['employee_id'] ?? 0);
    $tplId  = (int)($_POST['template_id'] ?? 0);
    $title  = trim($_POST['title'] ?? '');
    $issued = trim($_POST['issued_on'] ?? date('Y-m-d'));

    // Load template
    $s = $db->prepare("SELECT * FROM tblDocTemplate WHERE id=? AND CompanyId=?");
    $s->execute([$tplId, $fCompany]);
    $tpl = $s->fetch();

    // Load employee + company
    $s = $db->prepare("SELECT e.*, c.Name AS CompanyName FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId WHERE e.id=? AND e.CompanyId=?");
    $s->execute([$empId, $fCompany]);
    $emp = $s->fetch();

    if ($tpl && $emp) {
        // Build variable map
        $vars = [
            'employee_name'   => $emp['Name'] ?? '',
            'employee_code'   => $emp['EmployeeCode'] ?? '',
            'enroll_id'       => $emp['EnrollId'] ?? '',
            'father_name'     => $emp['FatherName'] ?? '',
            'designation'     => $emp['Designation'] ?? '',
            'department'      => $emp['Department'] ?? '',
            'contractor'      => $emp['Contractor'] ?? '',
            'join_date'       => $emp['JoinDate'] ? date('d/m/Y', strtotime($emp['JoinDate'])) : '',
            'dol'             => $emp['DOL'] ? date('d/m/Y', strtotime($emp['DOL'])) : '',
            'dob'             => $emp['DOB'] ? date('d/m/Y', strtotime($emp['DOB'])) : '',
            'gender'          => $emp['Gender'] ?? '',
            'phone'           => $emp['PhoneNo'] ?? '',
            'present_address' => $emp['PresentAdd'] ?? '',
            'basic_salary'    => $emp['BasicSalary'] ? number_format((float)$emp['BasicSalary'], 2) : '',
            'gross_salary'    => $emp['GrossSalary'] ? number_format((float)$emp['GrossSalary'], 2) : '',
            'uan'             => $emp['UAN'] ?? '',
            'pf_no'           => $emp['PfNo'] ?? '',
            'esi_no'          => $emp['EsiNo'] ?? '',
            'pan_no'          => $emp['PanNo'] ?? '',
            'bank_name'       => $emp['BankName'] ?? '',
            'bank_branch'     => $emp['BranchName'] ?? '',
            'bank_account'    => $emp['BankAcNo'] ?? '',
            'bank_ifsc'       => $emp['IFSCCode'] ?? '',
            'company_name'    => $emp['CompanyName'] ?? '',
            'today_date'      => date('d/m/Y'),
            'issue_date'      => $issued ? date('d/m/Y', strtotime($issued)) : date('d/m/Y'),
        ];

        // Override with user-supplied values from the form
        foreach ($_POST as $k => $v) {
            if (strpos($k, 'var_') === 0) {
                $vars[substr($k, 4)] = trim($v);
            }
        }

        // Substitute variables in template content
        $content = $tpl['Content'];
        foreach ($vars as $k => $v) {
            $content = str_replace('{{' . $k . '}}', htmlspecialchars($v, ENT_QUOTES, 'UTF-8'), $content);
        }

        $db->prepare(
            "INSERT INTO tblEmployeeDocument (CompanyId,EmployeeId,TemplateId,Title,DocType,Content,IssuedOn,CreatedBy)
             VALUES (?,?,?,?,?,?,?,?)"
        )->execute([$fCompany, $empId, $tplId, $title ?: $tpl['Name'], $tpl['DocType'], $content, $issued, $user['id']]);
        $newId = (int)$db->lastInsertId();
        if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['success'=>true,'redirect'=>"doc_print.php?id=$newId"]); exit; }
        header("Location: doc_print.php?id=$newId"); exit;
    }
}

// ── Load data for current view ─────────────────────────────────────────────────
$employees = [];
if ($fCompany) {
    $s = $db->prepare(
        "SELECT e.id, e.Name, e.EmployeeCode, e.Department
         FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId
         WHERE e.CompanyId=? AND e.Status='active'
         " . ($user['role'] === 'admin' ? "AND c.AdminId={$user['id']}" : '') . "
         ORDER BY e.Department, ISNULL(e.Sr), e.Sr, e.Name"
    );
    $s->execute([$fCompany]); $employees = $s->fetchAll();
}

$empRow   = null;
$empDocs  = [];
$templates = [];
$tplRow   = null;
$tplVars  = [];

if ($fEmp && $fCompany) {
    $s = $db->prepare("SELECT e.*, c.Name AS CompanyName FROM tblEmployee e JOIN tblCompany c ON c.id=e.CompanyId WHERE e.id=? AND e.CompanyId=?");
    $s->execute([$fEmp, $fCompany]); $empRow = $s->fetch();

    if ($empRow) {
        $s = $db->prepare("SELECT d.*, t.Name AS TemplateName FROM tblEmployeeDocument d LEFT JOIN tblDocTemplate t ON t.id=d.TemplateId WHERE d.EmployeeId=? AND d.CompanyId=? ORDER BY d.IssuedOn DESC, d.id DESC");
        $s->execute([$fEmp, $fCompany]); $empDocs = $s->fetchAll();

        $s = $db->prepare("SELECT id, Name, DocType FROM tblDocTemplate WHERE CompanyId=? AND IsActive=1 ORDER BY DocType, Name");
        $s->execute([$fCompany]); $templates = $s->fetchAll();
    }
}

if ($fTpl && $fCompany) {
    $s = $db->prepare("SELECT * FROM tblDocTemplate WHERE id=? AND CompanyId=?");
    $s->execute([$fTpl, $fCompany]); $tplRow = $s->fetch();
    if ($tplRow) {
        preg_match_all('/\{\{(\w+)\}\}/', $tplRow['Content'], $m);
        $tplVars = array_unique($m[1]);
    }
}

// Employee variable defaults for pre-filling
$empDefaults = [];
if ($empRow) {
    $empDefaults = [
        'employee_name'   => $empRow['Name'] ?? '',
        'employee_code'   => $empRow['EmployeeCode'] ?? '',
        'enroll_id'       => $empRow['EnrollId'] ?? '',
        'father_name'     => $empRow['FatherName'] ?? '',
        'designation'     => $empRow['Designation'] ?? '',
        'department'      => $empRow['Department'] ?? '',
        'contractor'      => $empRow['Contractor'] ?? '',
        'join_date'       => $empRow['JoinDate'] ? date('d/m/Y', strtotime($empRow['JoinDate'])) : '',
        'dol'             => $empRow['DOL'] ? date('d/m/Y', strtotime($empRow['DOL'])) : '',
        'dob'             => $empRow['DOB'] ? date('d/m/Y', strtotime($empRow['DOB'])) : '',
        'gender'          => $empRow['Gender'] ?? '',
        'phone'           => $empRow['PhoneNo'] ?? '',
        'present_address' => $empRow['PresentAdd'] ?? '',
        'basic_salary'    => $empRow['BasicSalary'] ? number_format((float)$empRow['BasicSalary'], 2) : '',
        'gross_salary'    => $empRow['GrossSalary'] ? number_format((float)$empRow['GrossSalary'], 2) : '',
        'uan'             => $empRow['UAN'] ?? '',
        'pf_no'           => $empRow['PfNo'] ?? '',
        'esi_no'          => $empRow['EsiNo'] ?? '',
        'pan_no'          => $empRow['PanNo'] ?? '',
        'bank_name'       => $empRow['BankName'] ?? '',
        'bank_branch'     => $empRow['BranchName'] ?? '',
        'bank_account'    => $empRow['BankAcNo'] ?? '',
        'bank_ifsc'       => $empRow['IFSCCode'] ?? '',
        'company_name'    => $empRow['CompanyName'] ?? '',
        'today_date'      => date('d/m/Y'),
        'issue_date'      => date('d/m/Y'),
    ];
}

$docTypeLabels = [
    'offer_letter'=>'Offer Letter','joining_letter'=>'Joining Letter','appointment_letter'=>'Appointment Letter',
    'experience_letter'=>'Experience Letter','warning_letter'=>'Warning Letter','termination_letter'=>'Termination Letter','custom'=>'Custom',
];

$pageTitle  = 'Issue Document';
$activePage = 'doc_issue';
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ── Top filters ──────────────────────────────────────────────────────────── -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-sm-4">
        <label class="form-label small mb-1">Company</label>
        <select name="company" class="form-select form-select-sm" onchange="this.form.submit()">
          <?php foreach ($companiesDd as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $fCompany==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['Name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-4">
        <label class="form-label small mb-1">Employee</label>
        <select name="emp" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="">— Select Employee —</option>
          <?php $prevD=null; foreach ($employees as $e):
              if ($e['Department'] !== $prevD) { if ($prevD!==null) echo '</optgroup>'; echo '<optgroup label="'.htmlspecialchars($e['Department']??'No Dept').'">'; $prevD=$e['Department']; }
          ?>
          <option value="<?= $e['id'] ?>" <?= $fEmp==$e['id']?'selected':'' ?>>
            <?= htmlspecialchars($e['Name']) ?> (<?= htmlspecialchars($e['EmployeeCode']?:'—') ?>)
          </option>
          <?php endforeach; if ($prevD!==null) echo '</optgroup>'; ?>
        </select>
      </div>
    </form>
  </div>
</div>

<?php if (!$fEmp): ?>
<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Select a company and employee to view or issue documents.</div>

<?php elseif (!$empRow): ?>
<div class="alert alert-danger">Employee not found.</div>

<?php elseif ($fTpl && $tplRow): ?>
<!-- ── Step 2: Variable fill form ──────────────────────────────────────────── -->
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white">
        <div class="fw-semibold">Fill Variables</div>
        <div class="text-muted small">Template: <strong><?= htmlspecialchars($tplRow['Name']) ?></strong> &nbsp;|&nbsp; Employee: <strong><?= htmlspecialchars($empRow['Name']) ?></strong></div>
      </div>
      <div class="card-body">
        <form method="POST" action="issue.php?company=<?= $fCompany ?>" data-ajax>
          <input type="hidden" name="employee_id" value="<?= $fEmp ?>">
          <input type="hidden" name="template_id" value="<?= $fTpl ?>">
          <div class="row g-2 mb-3">
            <div class="col-sm-8">
              <label class="form-label small">Document Title</label>
              <input type="text" name="title" class="form-control form-control-sm" value="<?= htmlspecialchars($tplRow['Name']) ?>">
            </div>
            <div class="col-sm-4">
              <label class="form-label small">Issue Date</label>
              <input type="date" name="issued_on" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
            </div>
          </div>
          <?php if (empty($tplVars)): ?>
          <div class="alert alert-warning py-2">This template has no <code>{{variables}}</code>. It will be saved as-is.</div>
          <?php else: ?>
          <div class="row g-2">
            <?php foreach ($tplVars as $v): ?>
            <div class="col-sm-6">
              <label class="form-label small text-muted">{{<?= htmlspecialchars($v) ?>}}</label>
              <input type="text" name="var_<?= htmlspecialchars($v) ?>" class="form-control form-control-sm"
                     value="<?= htmlspecialchars($empDefaults[$v] ?? '') ?>">
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-success"><i class="bi bi-file-earmark-check me-1"></i>Save &amp; Print</button>
            <a href="issue.php?company=<?= $fCompany ?>&emp=<?= $fEmp ?>" class="btn btn-outline-secondary">← Back</a>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold small">Template Preview</div>
      <div class="card-body p-2" style="font-size:11px;max-height:400px;overflow-y:auto;background:#fafafa">
        <?= nl2br(htmlspecialchars(substr($tplRow['Content'], 0, 800))) ?>
        <?php if (strlen($tplRow['Content']) > 800): ?><div class="text-muted text-center mt-1">… (truncated)</div><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ── Step 1: Employee documents + issue button ───────────────────────────── -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">
      <?= htmlspecialchars($empRow['Name']) ?>
      <span class="text-muted small ms-2"><?= htmlspecialchars($empRow['EmployeeCode']?:'') ?> &nbsp;·&nbsp; <?= htmlspecialchars($empRow['Department']??'') ?></span>
    </span>
    <?php if ($templates): ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#selectTplModal">
      <i class="bi bi-file-earmark-plus me-1"></i>Issue from Template
    </button>
    <?php else: ?>
    <a href="templates.php?company=<?= $fCompany ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Create Template first
    </a>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <?php if (empty($empDocs)): ?>
    <div class="p-4 text-center text-muted">No documents issued for this employee yet.</div>
    <?php else: ?>
    <table class="table table-hover table-sm align-middle mb-0">
      <thead class="table-light">
        <tr><th>Title</th><th>Type</th><th>Template</th><th>Issued On</th><th>Action</th></tr>
      </thead>
      <tbody>
      <?php foreach ($empDocs as $d): ?>
      <tr>
        <td class="fw-semibold"><?= htmlspecialchars($d['Title']) ?></td>
        <td><span class="badge bg-secondary"><?= $docTypeLabels[$d['DocType']] ?? $d['DocType'] ?></span></td>
        <td class="small text-muted"><?= $d['TemplateName'] ? htmlspecialchars($d['TemplateName']) : '—' ?></td>
        <td class="small"><?= htmlspecialchars($d['IssuedOn']) ?></td>
        <td>
          <?php if ($d['Content']): ?>
          <a href="doc_print.php?id=<?= $d['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer"></i></a>
          <?php endif; ?>
          <a href="issue.php?company=<?= $fCompany ?>&emp=<?= $fEmp ?>&del=<?= $d['id'] ?>"
             class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this document?')"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- ── Select Template Modal ──────────────────────────────────────────────── -->
<div class="modal fade" id="selectTplModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Select Template</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <?php
        $byType = [];
        foreach ($templates as $t) $byType[$t['DocType']][] = $t;
        foreach ($byType as $type => $rows):
        ?>
        <div class="mb-2">
          <div class="text-muted small fw-semibold mb-1"><?= $docTypeLabels[$type] ?? $type ?></div>
          <?php foreach ($rows as $t): ?>
          <a href="issue.php?company=<?= $fCompany ?>&emp=<?= $fEmp ?>&tpl=<?= $t['id'] ?>"
             class="list-group-item list-group-item-action py-2">
            <?= htmlspecialchars($t['Name']) ?>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
