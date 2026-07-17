<?php
define('BASE_URL', '../..');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

$db   = getDb();
$user = currentUser();

try { $db->query("SELECT 1 FROM tblDocTemplate LIMIT 1"); }
catch (PDOException $e) { header('Location: ' . BASE_URL . '/migrate.php'); exit; }

// Company comes from the global topbar switcher
$fCompany = activeCompanyId($db, $user);

// ── Sample templates ──────────────────────────────────────────────────────
$samples = [

// ── 1. Offer Letter ──────────────────────────────────────────────────────
[
'name'     => 'Standard Offer Letter',
'doc_type' => 'offer_letter',
'content'  => <<<'HTML'
<p style="text-align:center; margin-bottom:18px;">
  <strong style="font-size:13pt;">OFFER LETTER</strong>
</p>

<p>Dear <strong>{{employee_name}}</strong>,</p>

<p>We are pleased to offer you the position of <strong>{{designation}}</strong> in the
<strong>{{department}}</strong> department at <strong>{{company_name}}</strong>,
effective from <strong>{{join_date}}</strong>. This offer is subject to the terms and
conditions set out below.</p>

<table style="width:100%;border-collapse:collapse;margin:16px 0;">
  <tr style="background:#f2f2f2;">
    <td style="padding:6px 10px;border:1px solid #ccc;width:42%;"><strong>Designation</strong></td>
    <td style="padding:6px 10px;border:1px solid #ccc;">{{designation}}</td>
  </tr>
  <tr>
    <td style="padding:6px 10px;border:1px solid #ccc;"><strong>Department</strong></td>
    <td style="padding:6px 10px;border:1px solid #ccc;">{{department}}</td>
  </tr>
  <tr style="background:#f2f2f2;">
    <td style="padding:6px 10px;border:1px solid #ccc;"><strong>Date of Joining</strong></td>
    <td style="padding:6px 10px;border:1px solid #ccc;">{{join_date}}</td>
  </tr>
  <tr>
    <td style="padding:6px 10px;border:1px solid #ccc;"><strong>Basic Salary</strong></td>
    <td style="padding:6px 10px;border:1px solid #ccc;">&#8377; {{basic_salary}} per month</td>
  </tr>
  <tr style="background:#f2f2f2;">
    <td style="padding:6px 10px;border:1px solid #ccc;"><strong>Gross Salary (CTC)</strong></td>
    <td style="padding:6px 10px;border:1px solid #ccc;">&#8377; {{gross_salary}} per month</td>
  </tr>
</table>

<p><strong>Terms &amp; Conditions:</strong></p>
<ol style="margin:8px 0 16px 20px;line-height:1.9;">
  <li>This offer is contingent upon successful verification of your documents and references.</li>
  <li>You will be on probation for a period of <strong>six (6) months</strong> from the date of joining, during which the notice period is <strong>15 days</strong> on either side.</li>
  <li>After successful completion of probation, the notice period will be <strong>30 days</strong>.</li>
  <li>You are expected to maintain confidentiality of all company and client information.</li>
  <li>This offer expires if you do not join on or before the date stated above without written consent from the management.</li>
</ol>

<p>Please sign and return a copy of this letter as acceptance. We look forward to welcoming you to the team.</p>

<div style="margin-top:40px;">
  <table style="width:100%;border:none;">
    <tr>
      <td style="width:50%;vertical-align:top;">
        <p>Yours sincerely,</p>
        <br><br>
        <p>______________________________</p>
        <p><strong>Authorised Signatory</strong><br>
        {{company_name}}</p>
      </td>
      <td style="width:50%;vertical-align:top;">
        <p><strong>Acceptance by Employee:</strong></p>
        <p>I, <strong>{{employee_name}}</strong>, accept the offer on the terms stated above.</p>
        <br>
        <p>Signature: ______________________</p>
        <p>Date: ______________________</p>
      </td>
    </tr>
  </table>
</div>
HTML,
],

// ── 2. Appointment Letter ─────────────────────────────────────────────────
[
'name'     => 'Standard Appointment Letter',
'doc_type' => 'appointment_letter',
'content'  => <<<'HTML'
<p style="text-align:center;margin-bottom:18px;">
  <strong style="font-size:13pt;">APPOINTMENT LETTER</strong>
</p>

<p>Dear <strong>{{employee_name}}</strong>,</p>

<p>With reference to your application and subsequent interview, we are pleased to appoint you as
<strong>{{designation}}</strong> in the <strong>{{department}}</strong> department of
<strong>{{company_name}}</strong> with effect from <strong>{{join_date}}</strong>.</p>

<p>Your appointment is subject to the following terms and conditions:</p>

<ol style="margin:10px 0 16px 20px;line-height:2;">
  <li><strong>Designation &amp; Department:</strong> You are appointed as <strong>{{designation}}</strong>
      in the <strong>{{department}}</strong> department.</li>

  <li><strong>Remuneration:</strong> Your gross monthly salary will be
      <strong>&#8377; {{gross_salary}}</strong> (Basic: &#8377; {{basic_salary}}), subject to applicable
      statutory deductions (PF, ESI, TDS etc.) as per law.</li>

  <li><strong>Probation:</strong> You will initially be on probation for
      <strong>six (6) months</strong>. The company reserves the right to extend the probation period
      if performance is not satisfactory.</li>

  <li><strong>Working Hours:</strong> You are required to work the hours as stipulated by the company.
      Attendance shall be marked through the biometric system.</li>

  <li><strong>Leave:</strong> You will be entitled to leave as per the company's leave policy in
      force from time to time.</li>

  <li><strong>Confidentiality:</strong> You shall not, during or after employment, disclose any
      confidential information relating to the company, its clients or business to any third party.</li>

  <li><strong>Notice Period:</strong> After confirmation, either party may terminate this employment
      by giving <strong>one month's</strong> notice in writing or payment in lieu thereof.</li>

  <li><strong>Conduct &amp; Discipline:</strong> You shall be governed by the service rules,
      standing orders, and policies of the company as amended from time to time.</li>

  <li><strong>Termination:</strong> The company reserves the right to terminate your services
      immediately for any act of misconduct, dishonesty, or breach of company policy, without notice
      or compensation.</li>
</ol>

<p>Kindly sign and return the duplicate copy of this letter as an acknowledgement of your acceptance
of the above terms and conditions.</p>

<div style="margin-top:40px;">
  <table style="width:100%;border:none;">
    <tr>
      <td style="width:50%;vertical-align:top;">
        <p>For <strong>{{company_name}}</strong></p>
        <br><br>
        <p>______________________________</p>
        <p><strong>Authorised Signatory</strong></p>
      </td>
      <td style="width:50%;vertical-align:top;">
        <p><strong>Acknowledged &amp; Accepted:</strong></p>
        <br>
        <p>Name: <strong>{{employee_name}}</strong></p>
        <p>Emp. Code: {{employee_code}}</p>
        <br>
        <p>Signature: ______________________</p>
        <p>Date: ______________________</p>
      </td>
    </tr>
  </table>
</div>
HTML,
],

// ── 3. Joining Letter ─────────────────────────────────────────────────────
[
'name'     => 'Joining Confirmation Letter',
'doc_type' => 'joining_letter',
'content'  => <<<'HTML'
<p style="text-align:center;margin-bottom:18px;">
  <strong style="font-size:13pt;">JOINING LETTER</strong>
</p>

<p>Dear <strong>{{employee_name}}</strong>,</p>

<p>We are pleased to confirm that you have formally joined <strong>{{company_name}}</strong>
on <strong>{{join_date}}</strong> as <strong>{{designation}}</strong> in the
<strong>{{department}}</strong> department.</p>

<p>The following details have been recorded in our system:</p>

<table style="width:100%;border-collapse:collapse;margin:14px 0;">
  <tr style="background:#f2f2f2;">
    <td style="padding:6px 10px;border:1px solid #ccc;width:42%;"><strong>Employee Name</strong></td>
    <td style="padding:6px 10px;border:1px solid #ccc;">{{employee_name}}</td>
  </tr>
  <tr>
    <td style="padding:6px 10px;border:1px solid #ccc;"><strong>Employee Code</strong></td>
    <td style="padding:6px 10px;border:1px solid #ccc;">{{employee_code}}</td>
  </tr>
  <tr style="background:#f2f2f2;">
    <td style="padding:6px 10px;border:1px solid #ccc;"><strong>Father's Name</strong></td>
    <td style="padding:6px 10px;border:1px solid #ccc;">{{father_name}}</td>
  </tr>
  <tr>
    <td style="padding:6px 10px;border:1px solid #ccc;"><strong>Date of Birth</strong></td>
    <td style="padding:6px 10px;border:1px solid #ccc;">{{dob}}</td>
  </tr>
  <tr style="background:#f2f2f2;">
    <td style="padding:6px 10px;border:1px solid #ccc;"><strong>Designation</strong></td>
    <td style="padding:6px 10px;border:1px solid #ccc;">{{designation}}</td>
  </tr>
  <tr>
    <td style="padding:6px 10px;border:1px solid #ccc;"><strong>Department</strong></td>
    <td style="padding:6px 10px;border:1px solid #ccc;">{{department}}</td>
  </tr>
  <tr style="background:#f2f2f2;">
    <td style="padding:6px 10px;border:1px solid #ccc;"><strong>Date of Joining</strong></td>
    <td style="padding:6px 10px;border:1px solid #ccc;">{{join_date}}</td>
  </tr>
  <tr>
    <td style="padding:6px 10px;border:1px solid #ccc;"><strong>Basic Salary</strong></td>
    <td style="padding:6px 10px;border:1px solid #ccc;">&#8377; {{basic_salary}} per month</td>
  </tr>
  <tr style="background:#f2f2f2;">
    <td style="padding:6px 10px;border:1px solid #ccc;"><strong>PF / UAN No.</strong></td>
    <td style="padding:6px 10px;border:1px solid #ccc;">{{uan}}</td>
  </tr>
  <tr>
    <td style="padding:6px 10px;border:1px solid #ccc;"><strong>ESI No.</strong></td>
    <td style="padding:6px 10px;border:1px solid #ccc;">{{esi_no}}</td>
  </tr>
</table>

<p>We welcome you to the <strong>{{company_name}}</strong> family and look forward to a long
and productive association. Please ensure that all original documents are submitted to the HR
department within <strong>3 working days</strong> of joining.</p>

<div style="margin-top:40px;">
  <table style="width:100%;border:none;">
    <tr>
      <td style="width:50%;">
        <p>For <strong>{{company_name}}</strong></p>
        <br><br>
        <p>______________________________</p>
        <p><strong>HR Department</strong></p>
      </td>
      <td style="width:50%;">
        <p>Received by:</p>
        <br><br>
        <p>______________________________</p>
        <p><strong>{{employee_name}}</strong><br>Date: {{join_date}}</p>
      </td>
    </tr>
  </table>
</div>
HTML,
],

// ── 4. Experience Letter ──────────────────────────────────────────────────
[
'name'     => 'Experience Certificate',
'doc_type' => 'experience_letter',
'content'  => <<<'HTML'
<p style="text-align:center;margin-bottom:18px;">
  <strong style="font-size:13pt;">EXPERIENCE CERTIFICATE</strong>
</p>

<p style="text-align:center;margin-bottom:20px;"><em>To Whom It May Concern</em></p>

<p>This is to certify that <strong>{{employee_name}}</strong>,
S/o / D/o <strong>{{father_name}}</strong>, was employed with
<strong>{{company_name}}</strong> as <strong>{{designation}}</strong>
in the <strong>{{department}}</strong> department from
<strong>{{join_date}}</strong> to <strong>{{dol}}</strong>.</p>

<p>During their tenure with us, we found <strong>{{employee_name}}</strong> to be a
sincere, hardworking, and dedicated employee. They have satisfactorily performed all the
duties and responsibilities assigned to them.</p>

<p>We wish them all the best in their future endeavours.</p>

<div style="margin-top:50px;">
  <p>Issued on: <strong>{{issue_date}}</strong></p>
  <br><br>
  <p>______________________________</p>
  <p><strong>Authorised Signatory</strong><br>
  HR Department<br>
  <strong>{{company_name}}</strong></p>
</div>
HTML,
],

// ── 5. Warning Letter ─────────────────────────────────────────────────────
[
'name'     => 'Warning Letter',
'doc_type' => 'warning_letter',
'content'  => <<<'HTML'
<p style="text-align:center;margin-bottom:18px;">
  <strong style="font-size:13pt;">WARNING LETTER</strong>
</p>

<p><strong>Employee Code:</strong> {{employee_code}} &nbsp;&nbsp;&nbsp;
<strong>Department:</strong> {{department}}</p>

<p>Dear <strong>{{employee_name}}</strong>,</p>

<p>This letter serves as a formal warning regarding your conduct / performance. Despite verbal
counselling sessions in the past, we regret to observe that there has been no noticeable
improvement in the area mentioned below.</p>

<p><strong>Nature of Misconduct / Issue:</strong></p>
<p style="border-left:3px solid #ccc;padding-left:12px;color:#333;margin:10px 0 16px 0;">
  [Describe the specific incident or pattern — e.g., unauthorised absence, poor attendance,
  insubordination, violation of company policy, etc.]
</p>

<p>Such behaviour is in violation of the company's rules and policies and is
<strong>not acceptable</strong>. You are hereby warned that any recurrence of the above
will attract strict disciplinary action, which may include termination of employment
without further notice.</p>

<p>You are requested to acknowledge receipt of this letter and submit your written explanation
within <strong>48 hours</strong> of receiving this notice. Non-submission of explanation will
be treated as an admission of guilt.</p>

<p>We sincerely hope that you will take this warning seriously and demonstrate the required
improvement going forward.</p>

<div style="margin-top:40px;">
  <table style="width:100%;border:none;">
    <tr>
      <td style="width:50%;vertical-align:top;">
        <p>For <strong>{{company_name}}</strong></p>
        <br><br>
        <p>______________________________</p>
        <p><strong>HR Manager / Authorised Signatory</strong></p>
        <p>Date: {{issue_date}}</p>
      </td>
      <td style="width:50%;vertical-align:top;">
        <p><strong>Acknowledgement:</strong></p>
        <br>
        <p>I have received and read this warning letter.</p>
        <br>
        <p>Signature: ______________________</p>
        <p>Name: <strong>{{employee_name}}</strong></p>
        <p>Date: ______________________</p>
      </td>
    </tr>
  </table>
</div>
HTML,
],


// ── 6. Warning Letter – Absent Without Permission ─────────────────────────
[
'name'     => 'Warning Letter – Absent Without Permission',
'doc_type' => 'warning_letter',
'content'  => <<<'HTML'
<p style="text-align:center;margin-bottom:18px;">
  <strong style="font-size:13pt;">WARNING LETTER</strong><br>
  <span style="font-size:10pt;color:#555;">Unauthorized Absence from Duty</span>
</p>

<p><strong>Employee Code:</strong> {{employee_code}} &nbsp;&nbsp;&nbsp;
   <strong>Department:</strong> {{department}} &nbsp;&nbsp;&nbsp;
   <strong>Designation:</strong> {{designation}}</p>

<p>Dear <strong>{{employee_name}}</strong>,</p>

<p>It has been observed from the attendance records that you were <strong>absent from duty
without prior permission or intimation</strong> on the following date(s):</p>

<table style="width:60%;border-collapse:collapse;margin:14px 0;">
  <thead>
    <tr style="background:#f2f2f2;">
      <th style="border:1px solid #ccc;padding:6px 12px;text-align:left;">Sr.</th>
      <th style="border:1px solid #ccc;padding:6px 12px;text-align:left;">Date(s) of Absence</th>
      <th style="border:1px solid #ccc;padding:6px 12px;text-align:left;">Days</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td style="border:1px solid #ccc;padding:6px 12px;">1.</td>
      <td style="border:1px solid #ccc;padding:6px 12px;">&nbsp;</td>
      <td style="border:1px solid #ccc;padding:6px 12px;">&nbsp;</td>
    </tr>
    <tr style="background:#fafafa;">
      <td style="border:1px solid #ccc;padding:6px 12px;">2.</td>
      <td style="border:1px solid #ccc;padding:6px 12px;">&nbsp;</td>
      <td style="border:1px solid #ccc;padding:6px 12px;">&nbsp;</td>
    </tr>
  </tbody>
</table>

<p>Your absence without prior sanction or intimation is a violation of the company's
attendance policy and standing orders. Such unauthorised absence is treated as
<strong>Loss of Pay (LOP)</strong> and constitutes <strong>misconduct</strong> under the
service rules of the company.</p>

<p>You are hereby <strong>warned</strong> that any repetition of such behaviour will
invite serious disciplinary action, which may include:</p>
<ul style="margin:8px 0 14px 22px;line-height:2;">
  <li>Withholding of salary / increment</li>
  <li>Suspension from duty</li>
  <li>Termination of employment without notice</li>
</ul>

<p>You are required to <strong>submit a written explanation</strong> for the above absence
within <strong>48 hours</strong> of receiving this letter. Failure to do so will be
treated as an admission of guilt and the company may proceed with disciplinary action
without further notice.</p>

<p>The above period of absence will be treated as <strong>Leave Without Pay</strong>
and deducted from your salary for the month of <strong>{{month}}</strong>.</p>

<p>Please treat this as a <strong>final warning</strong> and ensure regular and punctual
attendance hereafter.</p>

<div style="margin-top:40px;">
  <table style="width:100%;border:none;">
    <tr>
      <td style="width:50%;vertical-align:top;">
        <p>For <strong>{{company_name}}</strong></p>
        <br><br>
        <p>______________________________</p>
        <p><strong>HR Manager / Authorised Signatory</strong></p>
        <p>Date: {{issue_date}}</p>
      </td>
      <td style="width:50%;vertical-align:top;">
        <p><strong>Acknowledgement by Employee:</strong></p>
        <br>
        <p>I have received, read, and understood this warning letter.</p>
        <br>
        <p>Signature: ______________________</p>
        <p>Name: <strong>{{employee_name}}</strong></p>
        <p>Emp. Code: {{employee_code}}</p>
        <p>Date: ______________________</p>
      </td>
    </tr>
  </table>
</div>
HTML,
],

]; // end $samples

// ── Handle seed action ────────────────────────────────────────────────────
$seeded   = 0;
$skipped  = 0;
$seedMsg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fCompany) {
    csrf_verify();
    $selected = $_POST['tpls'] ?? [];
    if (!$selected) {
        $seedMsg = 'No templates selected.';
    } else {
        $check = $db->prepare("SELECT COUNT(*) FROM tblDocTemplate WHERE CompanyId=? AND Name=?");
        $ins   = $db->prepare(
            "INSERT INTO tblDocTemplate (CompanyId, Name, DocType, Content, IsActive)
             VALUES (?, ?, ?, ?, 1)"
        );
        foreach ($samples as $i => $tpl) {
            if (!in_array((string)$i, $selected)) continue;
            $check->execute([$fCompany, $tpl['name']]);
            if ($check->fetchColumn() > 0) {
                $skipped++;
            } else {
                $ins->execute([$fCompany, $tpl['name'], $tpl['doc_type'], $tpl['content']]);
                $seeded++;
            }
        }
        $parts = [];
        if ($seeded)  $parts[] = "$seeded template(s) seeded.";
        if ($skipped) $parts[] = "$skipped already existed (skipped).";
        $seedMsg = implode(' ', $parts);
    }
}

$pageTitle  = 'Seed Sample Templates';
$activePage = 'doc_seed';
require_once __DIR__ . '/../../includes/header.php';

$docTypeLabels = [
    'offer_letter'       => 'Offer Letter',
    'joining_letter'     => 'Joining Letter',
    'appointment_letter' => 'Appointment Letter',
    'experience_letter'  => 'Experience Letter',
    'warning_letter'     => 'Warning Letter',
    'termination_letter' => 'Termination Letter',
    'custom'             => 'Custom',
];
?>

<?php if ($seedMsg): ?>
<div class="alert alert-success py-2">
  <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($seedMsg) ?>
  <a href="templates.php?company=<?= $fCompany ?>" class="ms-3 btn btn-sm btn-outline-success">
    View Templates →
  </a>
</div>
<?php endif; ?>

<?php if (!$fCompany): ?>
<div class="alert alert-info">Select a company to seed templates into.</div>
<?php else: ?>

<form method="POST" action="seed_templates.php?company=<?= $fCompany ?>">
  <?= csrf_field() ?? '' ?>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <span class="fw-semibold">
        <i class="bi bi-file-earmark-plus text-primary me-1"></i>
        Sample Templates
        <span class="text-muted fw-normal small ms-1">(<?= count($samples) ?> available)</span>
      </span>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary"
                onclick="document.querySelectorAll('.tpl-chk').forEach(c=>c.checked=true)">
          Select All
        </button>
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="bi bi-download me-1"></i>Seed Selected
        </button>
      </div>
    </div>
    <div class="card-body p-0">
      <div class="accordion accordion-flush" id="tplAccordion">
        <?php foreach ($samples as $i => $tpl): ?>
        <div class="accordion-item border-0 border-bottom">
          <div class="accordion-header">
            <div class="d-flex align-items-center px-3 py-2 gap-3">
              <input class="form-check-input tpl-chk flex-shrink-0" type="checkbox"
                     name="tpls[]" value="<?= $i ?>" id="chk<?= $i ?>" checked>
              <label class="form-check-label flex-grow-1 mb-0" for="chk<?= $i ?>">
                <div class="fw-semibold"><?= htmlspecialchars($tpl['name']) ?></div>
                <div class="text-muted small"><?= $docTypeLabels[$tpl['doc_type']] ?? $tpl['doc_type'] ?></div>
              </label>
              <button class="btn btn-sm btn-link text-muted py-0 accordion-toggle"
                      type="button" data-bs-toggle="collapse"
                      data-bs-target="#prev<?= $i ?>">
                Preview <i class="bi bi-chevron-down" style="font-size:10px"></i>
              </button>
            </div>
          </div>
          <div class="collapse" id="prev<?= $i ?>">
            <div class="px-4 pb-3 pt-1">
              <div style="border:1px solid #e0e0e0;border-radius:8px;padding:20px 24px;
                          font-family:'Times New Roman',serif;font-size:11pt;
                          line-height:1.7;background:#fafafa;max-height:380px;overflow-y:auto">
                <?= $tpl['content'] ?>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
      <span class="text-muted small">
        <i class="bi bi-info-circle me-1"></i>
        Templates already present in this company (same name) will be skipped.
      </span>
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-download me-1"></i>Seed Selected Templates
      </button>
    </div>
  </div>
</form>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
