<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Personal File 2</title>
<style>
  @page { size: A4; margin: 10mm 12mm; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 11px; color: #000; background: #fff; }

  /* Borders */
  .bdr     { border: 1px solid #000; }
  .bdr-btm { border-bottom: 1px solid #000; }
  .bdr-top { border-top: 1px solid #000; }
  .bdr-rt  { border-right: 1px solid #000; }
  .bdr-lt  { border-left: 1px solid #000; }

  /* Typography */
  .bold        { font-weight: bold; }
  .text-center { text-align: center; }
  .text-right  { text-align: right; }
  .underline   { text-decoration: underline; }
  .fs-12 { font-size: 12px; }
  .fs-14 { font-size: 14px; }
  .fs-16 { font-size: 16px; }
  .fs-18 { font-size: 18px; }

  /* Spacing */
  .p2  { padding: 2px 4px; }
  .p3  { padding: 3px 6px; }
  .p4  { padding: 4px; }
  .p8  { padding: 8px; }
  .p10 { padding: 10px; }
  .pt10 { padding-top: 10px; }
  .pt15 { padding-top: 15px; }
  .pl10 { padding-left: 10px; }
  .pr10 { padding-right: 10px; }
  .mt8  { margin-top: 8px; }
  .mt12 { margin-top: 12px; }
  .mt15 { margin-top: 15px; }
  .mt20 { margin-top: 20px; }
  .mb8  { margin-bottom: 8px; }
  .lh2  { line-height: 2; }

  /* Helpers */
  .w100  { width: 100%; }
  .blank-line { display: inline-block; min-width: 160px; border-bottom: 1px solid #000; }
  .blank-sm   { display: inline-block; min-width: 80px;  border-bottom: 1px solid #000; }
  .itbl  { border-collapse: collapse; width: 100%; }
  .itbl td { padding: 3px 6px; vertical-align: top; }
  .fl-right { display: flex; justify-content: flex-end; }

  /* Additional styles for pages 1, 5-13 */
  .fs-20{font-size:13px;} .fs-22{font-size:14px;} .fs-24{font-size:15px;}
  .fs-30{font-size:18px;} .fs-40{font-size:22px;} .fs-25{font-size:16px;}
  .lh-30{line-height:1.8;} .lh-25{line-height:1.6;} .lh-20{line-height:1.4;}
  .flex{display:flex;flex-wrap:wrap;} .d-flex{display:flex;}
  .pull-right{margin-left:auto;} .ml-auto{margin-left:auto;} .ml-10{margin-left:10px;}
  .pl-15{padding-left:15px;} .pl-20{padding-left:20px;} .pl-25{padding-left:25px;} .pl-30{padding-left:30px;}
  .pt-20{padding-top:20px;} .pt-25{padding-top:25px;}
  .mt-10{margin-top:10px;} .mt-20{margin-top:20px;} .mt-30{margin-top:30px;}
  .mt-50{margin-top:50px;} .mb-100{margin-bottom:60px;} .p-ml-50{padding-left:30px;}
  .v-top{vertical-align:top;} .text-underline{text-decoration:underline;}
  .w-70pr{width:70%;} .w-90pr{width:90%;} .w-55pr{width:55%;}
  .ws-15{word-spacing:8px;} .bdr-5{border:2px solid #000;}
  .bdr-btm-2{border-bottom:2px solid #000;} .bdr-dotted{border-bottom:1px dotted #000;}
  .h-50{min-height:50px;} .h-100{min-height:60px;} .h-200{min-height:80px;}
  .w-200{width:200px;} .w-300{width:300px;} .w-500{width:300px;}
  .w-250{width:250px;} .w-150{width:150px;}
  .inline-block{display:inline-block;} .p-3{padding:3px 6px;} .py-2{padding:2px 0;} .p-2{padding:2px 4px;}
  .p-0{padding:0;} .ml-20{margin-left:20px;} .ml-100{margin-left:30px;}
  .tstable td{border:1px solid #000;padding:2px 4px;} .tstable{border-collapse:collapse;}
  .tbl2 td{padding:2px 4px;vertical-align:top;} .tbl2{border-collapse:collapse;}
  .tbl3 td{padding:2px 4px;} .tbl3{border-collapse:collapse;}
  .form-2{} .form-gratuity{} .emp-cert-body{}
  .list-style-none{list-style:none;padding-left:0;} .mb-0{margin-bottom:0;} .mt-0{margin-top:0;}
  .fs-15{font-size:11px;} .fw-bold{font-weight:bold;} .lh-16{line-height:1.4;}
  .align-end{align-items:flex-end;} .justify-end{justify-content:flex-end;}
  .nominee-name{position:relative;} .nominee-sr{position:absolute;top:2px;left:2px;font-weight:bold;}
  pre{white-space:pre-wrap;font-family:Arial,sans-serif;}

  /* Page break */
  .pb { page-break-after: always; break-after: page; display: block; }

  .no-print { display: none; }
  @media screen {
    body { background: #888; padding: 20px; font-size: 12px; }
    .no-print { display: block; margin-bottom: 20px; text-align: center; }
    .no-print button { padding: 8px 22px; font-size: 14px; cursor: pointer; margin: 0 5px; }
    .emp-wrap { background: #fff; max-width: 210mm; margin: 0 auto 30px; padding: 10mm; box-shadow: 0 2px 10px rgba(0,0,0,.3); }
  }
  @media print {
    .no-print { display: none !important; }
    .emp-wrap { padding: 0; }
  }
</style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()">&#128438; Print Personal File 2</button>
  <button onclick="window.close()">Close</button>
</div>

<?php
$baseUrl = defined('BASE_URL') ? BASE_URL : '../..';

function pf2Fmt($d) {
    if (!$d || $d === '0000-00-00') return '';
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt ? $dt->format('d/m/Y') : $d;
}
function pf2H($v, $fb = '') {
    return htmlspecialchars((string)(($v ?? '') ?: $fb));
}

foreach ($printEmps as $empIdx => $e):

    $nm      = pf2H($e['Name']           ?? '');
    $code    = pf2H($e['EmployeeCode']   ?? '');
    $enrol   = pf2H($e['EnrollId']       ?? '');
    $dept    = pf2H($e['Department']     ?? '');
    $desig   = pf2H($e['Designation']    ?? '');
    $co      = pf2H($e['CompanyName']    ?? '');
    $coAdd   = pf2H($e['CompanyAddress'] ?? '');
    $contr   = pf2H($e['Contractor']     ?? '');
    $dob     = pf2Fmt($e['DOB']          ?? null);
    $doj     = pf2Fmt($e['JoinDate']     ?? null);
    $intDt   = pf2Fmt($e['InterviewDate']   ?? null);
    $appDt   = pf2Fmt($e['AppointmentDate'] ?? null);
    $appDate = pf2Fmt($e['AppDate']      ?? null);
    $age     = pf2H($e['Age']            ?? '');
    $gender  = pf2H($e['Gender']         ?? '');
    $father  = pf2H($e['FatherName']     ?? '');
    $phone1  = pf2H($e['Phone']          ?? '');
    $phone2  = pf2H($e['PhoneNo']        ?? '');
    $adhaar  = pf2H($e['AdhaarID']       ?? '');
    $bPlace  = pf2H($e['PlaceOfBirth']   ?? '');
    $quali   = pf2H($e['Qualification']  ?? '');
    $ms      = pf2H($e['MaritalStatus']  ?? '');
    $relig   = pf2H($e['Religion']       ?? '');
    $nation  = pf2H($e['Nationality']    ?? '');
    $place   = pf2H($e['Place']          ?? '');
    $idmark  = pf2H($e['IdentityMark']   ?? '');
    $height  = pf2H($e['Height']         ?? '');
    $empType = pf2H($e['EmploymentType'] ?? '');
    $empCat  = pf2H($e['EmploymentCategory'] ?? '');
    $shiftNo = pf2H($e['ShiftNo']        ?? '');
    $weekOff = pf2H($e['WeekdayNo']      ?? '');
    $presAdd = pf2H($e['PresentAdd']     ?? '');
    $permAdd = pf2H($e['PermanentAdd']   ?? '');
    $wit1    = pf2H($e['WitnessName1']   ?? '');
    $wit1Add = pf2H($e['WitnessAdd1']    ?? '');
    $wit2    = pf2H($e['WitnessName2']   ?? '');
    $wit2Add = pf2H($e['WitnessAdd2']    ?? '');
    $basic   = pf2H($e['BasicSalary']    ?? '');
    $da      = pf2H($e['DA']             ?? '');
    $gross   = pf2H($e['GrossSalary']    ?? '');
    $pfNo    = pf2H($e['PfNo']           ?? '');
    $esiNo   = pf2H($e['EsiNo']          ?? '');
    $panNo   = pf2H($e['PanNo']          ?? '');
    $bank    = pf2H($e['BankName']       ?? '');
    $branch  = pf2H($e['BranchName']     ?? '');
    $acno    = pf2H($e['BankAcNo']       ?? '');
    $ifsc    = pf2H($e['IFSCCode']       ?? '');
    $uan     = pf2H($e['UAN']            ?? '');
    $oldPf   = pf2H($e['OldPfNo']        ?? '');
    $oldEsi  = pf2H($e['OldEsicNo']      ?? '');
    $prevCo  = pf2H($e['PrevEmployerCompany'] ?? '');
    $prevDoj = pf2Fmt($e['PrevDOJ']      ?? null);
    $prevDol = pf2Fmt($e['PrevDOL']      ?? null);
    $sr      = pf2H($e['Sr']             ?? '');
    $nom1    = pf2H($e['Nominee1']       ?? '');
    $nomRel1 = pf2H($e['NomineeRelation1'] ?? '');
    $nomAge1 = pf2H($e['NomineeAge1']    ?? '');
    $nomDob1 = pf2Fmt($e['NomineeDOB1']  ?? null);
    $nomFH1  = pf2H($e['Nominee1FatherHusband'] ?? '');
    $thana   = pf2H($e['Thana']            ?? '');
    $dist    = pf2H($e['District']         ?? '');
    $relFH   = pf2H($e['RelFatherHusband'] ?? '');
    $voteId  = pf2H($e['VoterID']          ?? '');
    $driveNo = pf2H($e['DriveLicenseNo']   ?? '');
    $passNo  = pf2H($e['PassportNo']       ?? '');
    $email   = pf2H($e['Email']            ?? '');
    $photoSrc = !empty($e['Photo'])
        ? $baseUrl . '/uploads/employees/' . htmlspecialchars($e['Photo']) : '';

    $famRows = [
        [pf2H($e['FamilyMember1'] ?? ''), pf2H($e['MemberAge1'] ?? ''), pf2H($e['Rel1'] ?? ''), pf2Fmt($e['Member1DOB'] ?? null), pf2H($e['Member1ResidingWith'] ?? '')],
        [pf2H($e['FamilyMember2'] ?? ''), pf2H($e['MemberAge2'] ?? ''), pf2H($e['Rel2'] ?? ''), pf2Fmt($e['Member2DOB'] ?? null), pf2H($e['Member2ResidingWith'] ?? '')],
        [pf2H($e['FamilyMember3'] ?? ''), pf2H($e['MemberAge3'] ?? ''), pf2H($e['Rel3'] ?? ''), pf2Fmt($e['Member3DOB'] ?? null), pf2H($e['Member3ResidingWith'] ?? '')],
    ];
    $childRows = [];
    for ($ci = 1; $ci <= 5; $ci++) {
        $childRows[] = [pf2H($e["Child$ci"] ?? ''), pf2H($e["ChildAge$ci"] ?? ''), pf2H($e["SD$ci"] ?? ''), pf2Fmt($e["Child{$ci}DOB"] ?? null), pf2H($e["Child{$ci}ResidingWith"] ?? '')];
    }
    $allFamRows = array_merge($famRows, $childRows);
?>
<div class="emp-wrap<?= $empIdx > 0 ? ' pb' : '' ?>">

<!-- =========================================================
     PAGE 0 — NEW JOINING FORM
     ========================================================= -->
<table class="itbl bdr" style="font-size:11px;">
  <tr>
    <td colspan="6" class="text-center bold" style="font-size:18px;padding:6px;">NEW JOINING FORM</td>
  </tr>

  <tr class="bold text-center">
    <td class="bdr-btm bdr-rt">COMPANY / CONTRACTOR NAME</td>
    <td class="bdr-btm bdr-rt">MOBILE NO 1</td>
    <td class="bdr-btm bdr-rt">MOBILE NO 2</td>
    <td class="bdr-btm bdr-rt" colspan="3">SALARY DETAILS</td>
  </tr>
  <tr>
    <td class="bdr-btm bdr-rt"><?= $co ?><?= $contr ? " / $contr" : '' ?></td>
    <td class="bdr-btm bdr-rt"><?= $phone1 ?></td>
    <td class="bdr-btm bdr-rt"><?= $phone2 ?></td>
    <td class="bdr-btm bdr-rt">BASIC</td>
    <td class="bdr-btm" colspan="2"><?= $basic ?></td>
  </tr>

  <tr class="bold text-center">
    <td class="bdr-btm bdr-rt">ENROLL ID</td>
    <td class="bdr-btm bdr-rt">EMP CODE</td>
    <td class="bdr-btm bdr-rt">PF CODE COMPANY</td>
    <td class="bdr-btm bdr-rt">ESI CODE COMPANY</td>
    <td class="bdr-btm bdr-rt">D.A</td>
    <td class="bdr-btm"></td>
  </tr>
  <tr>
    <td class="bdr-btm bdr-rt"><?= $enrol ?></td>
    <td class="bdr-btm bdr-rt"><?= $code ?></td>
    <td class="bdr-btm bdr-rt"></td>
    <td class="bdr-btm bdr-rt"></td>
    <td class="bdr-btm bdr-rt"><?= $da ?></td>
    <td class="bdr-btm"></td>
  </tr>

  <tr class="bold text-center">
    <td class="bdr-btm bdr-rt">INTERVIEW DATE</td>
    <td class="bdr-btm bdr-rt">APPOINTMENT DATE</td>
    <td class="bdr-btm bdr-rt">APPLICATION DATE</td>
    <td class="bdr-btm bdr-rt">DOJ</td>
    <td class="bdr-btm" colspan="2"></td>
  </tr>
  <tr>
    <td class="bdr-btm bdr-rt"><?= $intDt ?></td>
    <td class="bdr-btm bdr-rt"><?= $appDt ?></td>
    <td class="bdr-btm bdr-rt"><?= $appDate ?></td>
    <td class="bdr-btm bdr-rt"><?= $doj ?></td>
    <td class="bdr-btm" colspan="2"><?= $gross ?></td>
  </tr>

  <tr class="bold text-center">
    <td class="bdr-btm bdr-rt">EMPLOYEE NAME</td>
    <td class="bdr-btm bdr-rt">FATHER NAME</td>
    <td class="bdr-btm bdr-rt">DOB</td>
    <td class="bdr-btm bdr-rt">AGE</td>
    <td class="bdr-btm" colspan="2"></td>
  </tr>
  <tr>
    <td class="bdr-btm bdr-rt"><?= $nm ?></td>
    <td class="bdr-btm bdr-rt"><?= $father ?></td>
    <td class="bdr-btm bdr-rt"><?= $dob ?></td>
    <td class="bdr-btm bdr-rt"><?= $age ?></td>
    <td class="bdr-btm" colspan="2"></td>
  </tr>

  <tr class="bold text-center">
    <td class="bdr-btm bdr-rt">EMPLOYEE AADHAR</td>
    <td class="bdr-btm bdr-rt">PLACE OF BIRTH</td>
    <td class="bdr-btm bdr-rt">QUALIFICATION</td>
    <td class="bdr-btm bdr-rt">GENDER</td>
    <td class="bdr-btm" colspan="2">EMPLOYMENT TYPE</td>
  </tr>
  <tr>
    <td class="bdr-btm bdr-rt"><?= $adhaar ?></td>
    <td class="bdr-btm bdr-rt"><?= $bPlace ?></td>
    <td class="bdr-btm bdr-rt"><?= $quali ?></td>
    <td class="bdr-btm bdr-rt"><?= $gender ?></td>
    <td class="bdr-btm" colspan="2"><?= $empType ?></td>
  </tr>

  <tr class="bold text-center">
    <td class="bdr-btm bdr-rt">MARITAL STATUS</td>
    <td class="bdr-btm bdr-rt">RELIGION</td>
    <td class="bdr-btm bdr-rt">NATIONALITY</td>
    <td class="bdr-btm bdr-rt">PLACE</td>
    <td class="bdr-btm" colspan="2">IDENTITY MARK</td>
  </tr>
  <tr>
    <td class="bdr-btm bdr-rt"><?= $ms ?></td>
    <td class="bdr-btm bdr-rt"><?= $relig ?></td>
    <td class="bdr-btm bdr-rt"><?= $nation ?></td>
    <td class="bdr-btm bdr-rt"><?= $place ?></td>
    <td class="bdr-btm" colspan="2"><?= $idmark ?></td>
  </tr>

  <tr class="bold text-center">
    <td class="bdr-btm bdr-rt">DEPARTMENT</td>
    <td class="bdr-btm bdr-rt">DESIGNATION</td>
    <td class="bdr-btm bdr-rt">HEIGHT</td>
    <td class="bdr-btm bdr-rt">CATEGORY</td>
    <td class="bdr-btm" colspan="2">SHIFT &amp; OFF</td>
  </tr>
  <tr>
    <td class="bdr-btm bdr-rt"><?= $dept ?></td>
    <td class="bdr-btm bdr-rt"><?= $desig ?></td>
    <td class="bdr-btm bdr-rt"><?= $height ?></td>
    <td class="bdr-btm bdr-rt"><?= $empCat ?></td>
    <td class="bdr-btm" colspan="2"><?= $shiftNo ? "Shift: $shiftNo" : '' ?><?= $weekOff ? " Off: $weekOff" : '' ?></td>
  </tr>

  <tr class="bold text-center">
    <td class="bdr-btm bdr-rt" colspan="3">PRESENT ADDRESS</td>
    <td class="bdr-btm" colspan="3">PERMANENT ADDRESS</td>
  </tr>
  <tr style="height:60px;">
    <td class="bdr-btm bdr-rt" colspan="3"><?= $presAdd ?></td>
    <td class="bdr-btm" colspan="3"><?= $permAdd ?></td>
  </tr>

  <tr class="bold text-center">
    <td class="bdr-btm bdr-rt" colspan="3">WITNESS 1</td>
    <td class="bdr-btm" colspan="3">WITNESS 2</td>
  </tr>
  <tr>
    <td class="bdr-btm bdr-rt" colspan="3"><?= $wit1 ?></td>
    <td class="bdr-btm" colspan="3"><?= $wit2 ?></td>
  </tr>
  <tr class="bold text-center">
    <td class="bdr-btm bdr-rt" colspan="3">WITNESS ADDRESS 1</td>
    <td class="bdr-btm" colspan="3">WITNESS ADDRESS 2</td>
  </tr>
  <tr style="height:45px;">
    <td class="bdr-btm bdr-rt" colspan="3"><?= $wit1Add ?></td>
    <td class="bdr-btm" colspan="3"><?= $wit2Add ?></td>
  </tr>

  <tr class="bold text-center">
    <td class="bdr-btm bdr-rt">PREV. UAN / PF NO</td>
    <td class="bdr-btm bdr-rt">PREVIOUS ESI NO</td>
    <td class="bdr-btm" colspan="4">PREV. EMPLOYER</td>
  </tr>
  <tr>
    <td class="bdr-btm bdr-rt"><?= $oldPf ?: $uan ?></td>
    <td class="bdr-btm bdr-rt"><?= $oldEsi ?></td>
    <td class="bdr-btm" colspan="4"><?= $prevCo ?></td>
  </tr>
  <tr class="bold text-center">
    <td class="bdr-btm bdr-rt">PREVIOUS DOJ</td>
    <td class="bdr-btm bdr-rt">PREVIOUS DOL</td>
    <td class="bdr-btm bdr-rt" colspan="2">PREV. DEPT / DESIGNATION</td>
    <td class="bdr-btm bdr-rt">ESI YES/NO</td>
    <td class="bdr-btm">PF YES/NO</td>
  </tr>
  <tr>
    <td class="bdr-btm bdr-rt"><?= $prevDoj ?></td>
    <td class="bdr-btm bdr-rt"><?= $prevDol ?></td>
    <td class="bdr-btm bdr-rt" colspan="2"></td>
    <td class="bdr-btm bdr-rt"><?= $oldEsi ? 'YES' : '' ?></td>
    <td class="bdr-btm"><?= $oldPf ? 'YES' : '' ?></td>
  </tr>

  <tr class="bold text-center">
    <td class="bdr-btm bdr-rt">NOMINEE NAME</td>
    <td class="bdr-btm bdr-rt">NOMINEE RELATION</td>
    <td class="bdr-btm bdr-rt">NOMINEE DOB</td>
    <td class="bdr-btm bdr-rt">NOMINEE AGE</td>
    <td class="bdr-btm bdr-rt">NOMINEE AADHAR</td>
    <td class="bdr-btm">YES</td>
  </tr>
  <tr>
    <td class="bdr-btm bdr-rt"><?= $nom1 ?></td>
    <td class="bdr-btm bdr-rt"><?= $nomRel1 ?></td>
    <td class="bdr-btm bdr-rt"><?= $nomDob1 ?></td>
    <td class="bdr-btm bdr-rt"><?= $nomAge1 ?></td>
    <td class="bdr-btm bdr-rt"></td>
    <td class="bdr-btm"></td>
  </tr>

  <tr class="bold text-center">
    <td class="bdr-btm bdr-rt">FAMILY NAME</td>
    <td class="bdr-btm bdr-rt">FAMILY RELATION</td>
    <td class="bdr-btm bdr-rt">FAMILY DOB</td>
    <td class="bdr-btm bdr-rt">FAMILY AGE</td>
    <td class="bdr-btm bdr-rt">FAMILY AADHAR</td>
    <td class="bdr-btm">YES/NO</td>
  </tr>
  <?php foreach ($allFamRows as [$fn,$fa,$fr,$fd,$frs]): if (!$fn) continue; ?>
  <tr>
    <td class="bdr-btm bdr-rt"><?= $fn ?></td>
    <td class="bdr-btm bdr-rt"><?= $fr ?></td>
    <td class="bdr-btm bdr-rt"><?= $fd ?></td>
    <td class="bdr-btm bdr-rt"><?= $fa ?></td>
    <td class="bdr-btm bdr-rt"></td>
    <td class="bdr-btm"><?= $frs ?></td>
  </tr>
  <?php endforeach; ?>

  <tr class="bold text-center">
    <td class="bdr-rt">BANK NAME</td>
    <td class="bdr-rt">BRANCH NAME</td>
    <td class="bdr-rt">ACCOUNT NUMBER</td>
    <td class="bdr-rt">IFSC CODE</td>
    <td colspan="2">PAN NUMBER</td>
  </tr>
  <tr>
    <td class="bdr-rt"><?= $bank ?></td>
    <td class="bdr-rt"><?= $branch ?></td>
    <td class="bdr-rt"><?= $acno ?></td>
    <td class="bdr-rt"><?= $ifsc ?></td>
    <td colspan="2"><?= $panNo ?></td>
  </tr>
</table>


<!-- =========================================================
     PAGE 1 — INDUCTION CHECKLIST
     ========================================================= -->
<div class="pb"></div>
<table width="100%">
    <tr>
        <td class="bdr" style="padding:0;text-align:center;font-weight:bold;"><h1>प्रक्रिया संचालन मानक प्रक्रिया</h1></td>
        <td class="bdr" width="25%" style="padding:0px;">
            <div style="padding:5px 10px;line-height:16px;">Doc No. : HRMS/IP/6.2/14</div>
            <div class="bdr">Format No. : HRMS/IP/6.2/F01</div>
        </td>
    </tr>
    <tr>
        <td class="text-center bdr" style="padding-top:20px;font-size:16px;">कर्मचारी प्रेरणा (परिचय) कार्यक्रम</td>
        <td class="bdr" style="padding:0px;">
            <div style="padding:5px 10px;line-height:16px;">Rev. No: 10</div>
            <div class="bdr">Eff. Date : 22/May/2026</div>
            <div class="bdr">Page 1 of 1</div>
        </td>
    </tr>
</table>
<table class="bdr" width="100%" style="border-collapse:collapse;line-height:1.4;font-size:12px;">
    <tr class="text-center" style="font-weight:bold">
        <td class="bdr" width="5%">क्रमिक संख्या</td>
        <td class="bdr" width="78%">विषय वस्तु</td>
        <td class="bdr" width="17%">टिप्पणी</td>
    </tr>
    <tr><td class="bdr text-center">1.</td><td class="bdr">मानव संसाधन विभाग से परिचय</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">2.</td><td class="bdr">कर्मचारी के नियुक्ति पत्र की प्रति एवं उसकी पावती</td><td class="bdr"></td></tr>
    <tr>
        <td colspan="3" style="padding:0;">
            <table width="100%" style="border-collapse:collapse;">
                <tr>
                    <td class="bdr-rt text-center" width="4.7%" rowspan="4" style="vertical-align:top;">3.</td>
                    <td class="bdr-rt bdr-btm" width="78.3%" colspan="2"> deZpkfj;ksa dks fn, tk jgs iw.kZ lekftd ykHkksa dh tkudkjh tSls%&amp;</td>
                    <td class="bdr-btm" width="17%"></td>
                </tr>
                <tr>
                    <td class="bdr-rt" width="4%" rowspan="3"></td>
                    <td class="bdr-rt bdr-btm">√ कर्मचारी भविष्य निधि, ई. एस. आई. एवं श्रम कल्याण कोष</td>
                    <td class="bdr-btm">&nbsp;</td>
                </tr>
                <tr>
                    <td class="bdr bdr-rt"> √  अवकाश - उपार्जित/ आकस्मिक/ बीमारी/ मातृत्व लाभ इत्यादि</td>
                    <td class="bdr-btm">&nbsp;</td>
                </tr>
                <tr>
                    <td class="bdr-rt">√ राष्ट्रीय अवकाश एवं त्योहारिक अवकाश</td>
                    <td></td>
                </tr>
            </table>
        </td>
    </tr>
    <tr><td class="bdr text-center">4.</td><td class="bdr">ठेकेदार के कर्मचारियों को दिए गए फार्म 10 की जानकारी के संबंध में (केवल ठेकेदार के कर्मचारियों के लिए)</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">5.</td><td class="bdr">¼v½dEiuh ds fn;s igpku i= ds laca/k esa tkudkjh rFkk dk;Z vof/k ds nkSjku bls iguuk vko';d gSA ¼c½;fn dksbZ dkMZ [kjkc gks tk;s rks bldh lwpuk ekuo lalk/ku foHkkx dks nsuh gksxhA</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">6.</td><td class="bdr">संगठन की संरचना, उत्पाद एवं उसके कार्य प्रणाली की जानकारी</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">7.</td><td class="bdr">QSDVªh@dEiuh dh nqjnf'kZrk ,oa fe'ku dh tkudkjh</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">8.</td><td class="bdr"><span style="padding-left:11px;">फैक्ट्री / कंपनी की आचार संहिता / बी.एस.सी.आई. के बारे में जानकारी</span></td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">9.</td><td class="bdr">QSDVªh@dEiuh dh fufr;ksa] fu;e ,oa dkuwu dh tkudkjh</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">10.</td><td class="bdr">¼v½ßXkksifu;rk [k.MÞ ds laca/k esa funsZ'k ¼c½ lwpuk izkS|ksfxdh vkSj mldh xyr rjhds ¼ dEi;wVj ok;jl½ls cpko dh tkudkjh</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">11.</td><td class="bdr">¼v½ Hkz"Vkpkj@fj'or@vukSipkfjd ncko fojks/kh uhfr ¼c½ vokafNr oLrq@vkUrfjd "kM;a= @/kedh ds ckjs esa lwpuk</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">12.</td><td class="bdr">QSDVªh@dEiuh dh gkftjh ,oa le; dh vfHkys[k iz.kkyh</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">13.</td><td class="bdr">osru ,oa ykHkksa dh x.kuk dh tkudkjh</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">14.</td><td class="bdr">कार्य विवरण/उत्तरदायित्व एवं कार्य निर्देश</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">15.</td><td class="bdr">कार्य मूल्यांकन प्रणाली के संबंध में उल्लेख</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">16.</td><td class="bdr">वातावरण, स्वास्थ्य एवं सुरक्षा / कोविड-19 / गर्मी की लहर / ओमिक्रॉन के प्रशिक्षण की जानकारी (आपातकालीन स्थिति में निकासी योजना)</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">17.</td><td class="bdr">प्राथमिक चिकित्सा एवं उपकरण के संबंध में प्रशिक्षण</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">18.</td><td class="bdr">vfXu lqj{kk ,oa mlds midj.k ds laca/k esa tkudkjh</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">19.</td><td class="bdr">fofHkUu izdkj dh lfefr;ka ¼deZpkjh ls lacaf/kr½ ,l ih Vh lnL;ksa ,oa mudh dk;Z iz.kkyh</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">20.</td><td class="bdr">अनुशासन एवं निष्कासन प्रणाली की जानकारी</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">21.</td><td class="bdr">लैंगिक समानता (DE &amp; I)/ शिकायत निवारण प्रणाली की जानकारी</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">22.</td><td class="bdr">(i) सुझाव नीति (ii) भर्ती / पदोन्नति शुल्क के बाबत प्रशिक्षण</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">23.</td><td class="bdr">वातावरण एवं गुणवत्ता नीति की जानकारी</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">24.</td><td class="bdr">(i) सुरक्षा एवं सीमान्त व्यापार साझेदारी विरूद्ध आतंकवाद के संबंध में प्रशिक्षण/माल की अखण्डता/ विश्वसनीयता/ समझौता सुरक्षा अवसंरचना की सूचना<br>(ii) कृषि नाशक नियंत्रण निति (कन्टेनर/माल के रख रखाव के संबंध में )</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">25.</td><td class="bdr"> lqj{kk ds 5 dne ij izf'k{k.k </td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">26.</td><td class="bdr">xqykeh @ ekuo rLdjh @voS/k oLrqvksa vkfn uhfr ds lEcU/k esa tkudkjh</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">27.</td><td class="bdr">laLFkk esa jkmUM ds nkSjku vius foHkkx ds izca/kd@lkFkh ,oa ofj"B vf/kdkjh oxZ ls ifjp;</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">28.</td><td class="bdr">deZpkjh ds dk;Z fu"iknu ls lacaf/kr dk;Z funsZ'k] xq.koÙkk fu;a=.k ij fo'ks"k :i ls /;ku j[kuk vkSj j[k&amp;j[kko foHkkx ds deZpkjh ds dk;Z laca/kh tkudkjh</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">29.</td><td class="bdr">विश्राम कक्ष/जलपान गृह के संबंध में जानकारी</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">30.</td><td class="bdr">कर्मचारी के द्वारा भरे गए प्रतिपुष्टि प्रपत्र को पूर्ण करने के संबंध में जानकारी</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">31.</td><td class="bdr">LABS (भवन सुरक्षा, अग्नि सुरक्षा एवं विद्युत सुरक्षा) के बारे में जानकारी</td><td class="bdr"></td></tr>
    <tr><td class="bdr text-center">32.</td><td class="bdr">अपशिष्ठ प्रबंधन प्रक्रिया के बारे में जानकारी</td><td class="bdr"></td></tr>
</table>
<table width="100%" style="margin-top:30px;">
    <tr>
        <td align="left">हस्ताक्षर कर्मचारी</td>
        <td align="center">हस्ताक्षर प्रशिक्षक</td>
        <td align="center">हस्ताक्षर ठेकेदार/अधिकृतअधिकारी/प्रबंधक</td>
        <td align="right">हस्ताक्षर कार्मिक विभाग</td>
    </tr>
</table>


<!-- =========================================================
     PAGE 2 — FORM V / SERVICE CARD
     ========================================================= -->
<div class="pb"></div>
<div class="text-right mb8">Token No.:- <?= $enrol ?></div>

<table class="itbl bdr" style="margin-bottom:0;">
  <tr>
    <td style="padding:6px;text-align:center;">
      <p class="underline bold fs-16">FORM V</p>
      <p class="fs-12">(See Standing Order I, Schedule I-B)</p>
      <p class="fs-12">SERVICE CARD</p>
    </td>
    <td width="130px" style="text-align:center;vertical-align:middle;border-left:1px solid #000;">
      <?php if ($photoSrc): ?><img src="<?= $photoSrc ?>" width="110" style="border:1px solid #ccc;"><?php endif; ?>
    </td>
  </tr>
</table>

<table class="itbl bdr" style="border-top:0;">
  <tr class="bdr-btm"><td class="bold bdr-rt" width="200px" style="padding:4px;">Name of Estt. / Factory</td><td style="padding:4px;"><?= $co ?></td></tr>
  <tr class="bdr-btm"><td class="bold bdr-rt" style="padding:4px;">Address</td><td style="padding:4px;"><?= $coAdd ?></td></tr>
  <tr>                <td class="bold bdr-rt" style="padding:4px;">Ticket / Token No.</td><td style="padding:4px;"><?= $enrol ?></td></tr>
</table>

<table class="itbl bdr" style="border-top:0;">
  <?php foreach ([
    [1,  'Register Serial No.',                       $sr],
    [2,  'Name',                                      $nm],
    [3,  'Specimen Signature / Thumb-Impression',     ''],
    [4,  "Father's or Husband's Name",                $father],
    [5,  'Gender',                                    $gender],
    [6,  'Religion',                                  $relig],
    [7,  'Date Of Birth',                             $dob],
    [8,  'Place Of Birth',                            $bPlace],
    [9,  'Date Of Joining',                           $doj],
    [10, 'Details of medical certificate at joining', 'Medical Fitness Certificate'],
    [11, 'Education and Other Qualification',         'Application Form Attached'],
  ] as [$n, $lbl, $val]): ?>
  <tr class="bdr-btm">
    <td class="bdr-rt text-center" width="25px" style="padding:4px;"><?= $n ?></td>
    <td class="bdr-rt bold" width="280px" style="padding:4px 4px 4px 8px;"><?= $lbl ?></td>
    <td style="padding:4px 4px 4px 8px;"><?= $val ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<!-- Language -->
<table class="itbl bdr" style="border-top:0;border-collapse:collapse;">
  <tr style="text-align:center;border-bottom:1px solid #000;">
    <td style="border-right:1px solid #000;border-bottom:1px solid #000;padding:3px;" rowspan="1">12</td>
    <td style="border-right:1px solid #000;border-bottom:1px solid #000;padding:3px;">Language</td>
    <td style="border-right:1px solid #000;border-bottom:1px solid #000;padding:3px;" colspan="4">Can Read</td>
    <td style="border-right:1px solid #000;border-bottom:1px solid #000;padding:3px;" colspan="4">Can Write</td>
    <td style="border-bottom:1px solid #000;padding:3px;" colspan="4">Can Speak</td>
  </tr>
  <?php foreach (['English','Hindi','Other'] as $lang): ?>
  <tr style="border-bottom:1px solid #000;text-align:center;">
    <td style="border-right:1px solid #000;padding:3px;" colspan="2"><?= $lang ?></td>
    <?php for ($li = 0; $li < 3; $li++): ?>
      <td style="border-right:1px solid #000;padding:2px;">Yes</td><td style="border-right:1px solid #000;padding:2px;">&nbsp;</td>
      <td style="border-right:1px solid #000;padding:2px;">No</td><td style="<?= $li<2 ? 'border-right:1px solid #000;' : '' ?>padding:2px;">&nbsp;</td>
    <?php endfor; ?>
  </tr>
  <?php endforeach; ?>
</table>

<table class="itbl bdr" style="border-top:0;">
  <?php foreach ([
    [13, 'Height',              $height],
    [14, 'Identification Marks',$idmark],
    [15, 'Category of Workman', $empCat],
    [16, 'Department',          "$dept ($desig)"],
  ] as [$n, $lbl, $val]): ?>
  <tr class="bdr-btm">
    <td class="bdr-rt text-center" width="25px" style="padding:4px;"><?= $n ?></td>
    <td class="bdr-rt bold" width="280px" style="padding:4px 4px 4px 8px;"><?= $lbl ?></td>
    <td style="padding:4px 4px 4px 8px;"><?= $val ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<table class="itbl bdr" style="border-top:0;">
  <tr class="bdr-btm"><td class="bdr-rt text-center" width="25px" style="padding:4px;">17</td><td class="bold text-center" style="padding:4px;">Details of Family Members</td></tr>
</table>
<table class="itbl bdr" style="border-top:0;">
  <tr style="border-bottom:1px solid #000;text-align:center;font-weight:bold;">
    <td class="bdr-rt" width="30px" style="padding:3px;">S.No.</td>
    <td class="bdr-rt" style="padding:3px;">Name</td>
    <td class="bdr-rt" style="padding:3px;">Age</td>
    <td class="bdr-rt" style="padding:3px;">Relationship</td>
    <td style="padding:3px;">Others</td>
  </tr>
  <?php $fi = 1; foreach ($allFamRows as [$fn,$fa,$fr,$fd,$frs]): if ($fi > 8) break; ?>
  <tr class="bdr-btm text-center">
    <td class="bdr-rt" style="padding:3px;"><?= $fi++ ?>.</td>
    <td class="bdr-rt" style="padding:3px;"><?= $fn ?></td>
    <td class="bdr-rt" style="padding:3px;"><?= $fa ?></td>
    <td class="bdr-rt" style="padding:3px;"><?= $fr ?></td>
    <td style="padding:3px;"></td>
  </tr>
  <?php endforeach; ?>
</table>

<table class="itbl bdr" style="border-top:0;">
  <?php foreach ([
    [18,'Permanent Address',     $permAdd],
    [19,'Local Address',         $presAdd],
    [20,'Quarter No.',           '---'],
    [22,'PF Account No.',        $pfNo],
    [23,'Nominee for Gratuity',  $nom1],
    [24,'Nominee for Pension',   $nom1],
    [25,'ESI No.',               $esiNo],
    [26,'Training Course',       'Induction Checklist Attached'],
  ] as [$n,$lbl,$val]): ?>
  <tr class="bdr-btm">
    <td class="bdr-rt text-center" width="25px" style="padding:4px;"><?= $n ?></td>
    <td class="bdr-rt bold" width="280px" style="padding:4px 4px 4px 8px;"><?= $lbl ?></td>
    <td style="padding:4px 4px 4px 8px;"><?= $val ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<table class="itbl bdr" style="border-top:0;margin-top:6px;">
  <tr class="bdr-btm"><td class="bold text-center" colspan="3" style="padding:4px;">Record Of Salary</td></tr>
  <tr class="bdr-btm bold text-center">
    <td class="bdr-rt" style="padding:3px;">SALARY</td><td class="bdr-rt" style="padding:3px;">W.E.F.</td><td style="padding:3px;">Remarks</td>
  </tr>
  <?php for ($si = 0; $si < 5; $si++): ?>
  <tr class="bdr-btm text-center">
    <td class="bdr-rt" style="padding:5px;"><?= $si===0 ? $gross : '&nbsp;' ?></td>
    <td class="bdr-rt" style="padding:5px;"><?= $si===0 ? $doj : '&nbsp;' ?></td>
    <td style="padding:5px;">&nbsp;</td>
  </tr>
  <?php endfor; ?>
</table>


<!-- =========================================================
     PAGE 3 — EMPLOYMENT CARD / FORM NO. 10 (×2 copies)
     ========================================================= -->
<?php foreach (['Employee Copy', 'Office Copy'] as $copy): ?>
<div class="pb"></div>
<div class="text-right mb8">Token No.:- <?= $enrol ?></div>

<div class="bdr text-center" style="padding:15px;">
  <div class="bold fs-16">Form No - 10</div>
  <div class="fs-12" style="margin-top:4px;">[See Rule 75]</div>
  <div class="bold fs-16" style="margin-top:6px;">Employment Card</div>
  <div class="fs-12" style="margin-top:4px;">(<?= $copy ?>)</div>
</div>

<table class="itbl bdr" style="border-top:0;">
  <tr class="bdr-btm"><td class="bdr-rt" style="padding:10px;width:50%;">Name and Address of the contractor</td><td style="padding:10px;"><?= $contr ?><br><?= $coAdd ?></td></tr>
  <tr class="bdr-btm"><td class="bdr-rt" style="padding:10px;">Name and Address of the establishment</td><td class="bold" style="padding:10px;"><?= $co ?><br><?= $coAdd ?></td></tr>
  <tr class="bdr-btm"><td class="bdr-rt" style="padding:10px;">Nature of work and location of work</td><td style="padding:10px;"><?= $desig ?><br><?= $dept ?></td></tr>
  <tr>               <td class="bdr-rt" style="padding:10px;">Name and address of the Principal Employer</td><td class="bold" style="padding:10px;"><?= $co ?><br><?= $coAdd ?></td></tr>
</table>

<table class="itbl bdr" style="border-top:0;">
  <?php foreach ([
    [1, 'Name of the workman',                         $nm],
    [2, 'Serial No. in the register of workman',       $sr],
    [3, 'Nature of employment / designation',          $desig],
    [4, 'Wage rate',                                   'Rs. ' . $gross . ' P.M. &nbsp; W.E.F. ' . $doj],
    [5, 'Wage period',                                 'MONTHLY'],
    [6, 'Period of Employment',                        $doj],
    [7, 'Remarks',                                     ''],
  ] as [$n,$lbl,$val]): ?>
  <tr class="bdr-btm">
    <td class="bdr-rt text-center" width="25px" style="padding:10px;"><?= $n ?></td>
    <td class="bdr-rt" width="280px" style="padding:6px 6px 6px 10px;"><?= $lbl ?></td>
    <td style="padding:6px 6px 6px 10px;"><?= $val ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<div class="bdr" style="border-top:0;padding:15px 10px;">
  <div style="line-height:2;font-size:13px;">
    Place: &nbsp; <?= $coAdd ?: '___________' ?><br>
    Date: &nbsp;&nbsp; <?= $doj ?>
  </div>
  <div style="text-align:right;padding: 30px 20px 20px 0;">Signature of the contractor</div>
</div>
<?php endforeach; // copies ?>


<!-- =========================================================
     PAGE 4 — FORM D (HINDI) — Factories Act 1948 Rule 100
     ========================================================= -->
<div class="pb"></div>
<div class="text-right mb8">Token No.:- <?= $enrol ?></div>
<div class="text-right mb8" style="font-size:12px;">दिनांक:- <?= $doj ?></div>
<div class="bdr" style="padding:10px;">
  <div class="text-center bold fs-18" style="margin-top:8px;">परिपत्र-डी</div>
  <div class="text-center fs-14" style="margin-top:6px;">कारखाना अधिनियम 1948, नियम-100</div>
  <div style="font-size:13px;line-height:2;padding:15px 10px 30px 10px;">
    कारखाना अधिनियम 1948 के तहत, मैं घोषणा करता हूं, मेरी मृत्यु के बाद जो कि मेरे कार्य के अंतिम दिवस के अनुसार में अर्जित छुट्टी के जो पैसे देय बनते होंगे वह
    श्री/श्रीमती <span class="blank-line"><?= $nom1 ?></span> को जो के
    <span class="blank-sm"><?= $nomRel1 ?></span> को दिए जाए। जिसकी मैं पुष्टि करता/करती हूँ।
    <br><br><br>
    <div style="font-size:11px;">श्रमिक के हस्ताक्षर / घोषणा करने वाले कर्मचारी का नाम</div>
  </div>
  <div class="fl-right" style="min-height:70px;">
    <div style="width:320px;">
      गवाह का नाम / हस्ताक्षर
      <table class="itbl bdr" style="margin-top:10px;">
        <tr class="bdr-btm"><td class="bdr-rt text-center" width="30px" style="padding:8px;font-size:13px;">1</td><td style="padding:8px;"><?= $wit1 ?></td></tr>
        <tr>                <td class="bdr-rt text-center" style="padding:8px;font-size:13px;">2</td><td style="padding:8px;"><?= $wit2 ?></td></tr>
      </table>
    </div>
  </div>
  <div style="padding-top:20px;font-size:13px;">कानूनी वारिस का स्थायी पता:-</div>
  <div class="bdr" style="margin-top:8px;padding:12px;display:inline-block;min-width:180px;"><?= $permAdd ?></div>
  <div style="padding-top:20px;font-size:13px;">कानूनी वारिस का अस्थायी पता:-</div>
  <div class="bdr" style="margin-top:8px;padding:12px;display:inline-block;min-width:180px;"><?= $presAdd ?></div>
</div>


<!-- =========================================================
     PAGE 4E — FORM D (ENGLISH) — Factories Act 1948 Rule 100
     ========================================================= -->
<div class="pb"></div>
<div class="text-right mb8" style="font-size:12px;"><?= $doj ?></div>
<div class="bdr" style="padding:10px;font-size:13px;">
  <div class="text-center bold fs-18" style="margin-top:10px;">FORM 'D'</div>
  <div class="text-center fs-14" style="margin-top:6px;">FACTORIES ACT 1948, RULE-100</div>
  <div style="line-height:2;margin-top:15px;">
    I hereby declare that in the event of my death before resuming work the balance of pay due for the period of Leave with wages not availed of shall be paid to
    Sh./Smt. <span class="blank-line"><?= $nom1 ?></span> who is my <span class="blank-sm"><?= $nomRel1 ?></span>.
  </div>
  <div style="min-height:50px;margin-top:15px;font-size:11px;">Signature of Worker / Name of the Declarant Employee</div>
  <div class="fl-right" style="min-height:60px;">
    <div style="width:300px;">
      Name / Signature of the witness
      <table class="itbl bdr" style="margin-top:10px;">
        <tr class="bdr-btm"><td class="bdr-rt text-center" width="30px" style="padding:6px;">1</td><td style="padding:6px;"><?= $wit1 ?></td></tr>
        <tr>                <td class="bdr-rt text-center" style="padding:6px;">2</td><td style="padding:6px;"><?= $wit2 ?></td></tr>
      </table>
    </div>
  </div>
  <div style="margin-top:25px;">Permanent address of Legal Heir:-</div>
  <div class="bdr" style="margin-top:8px;padding:12px;display:inline-block;min-width:180px;"><?= $permAdd ?></div>
  <div style="margin-top:25px;">Temporary address of Legal Heir:-</div>
  <div class="bdr" style="margin-top:8px;padding:12px;display:inline-block;min-width:180px;"><?= $presAdd ?></div>
</div>

<!-- ===================== PAGE 5 — ESI LETTER ===================== -->
<div class="pb"></div>

<div class="fs-20 mt-50">
    <div class="text-right">
        <span class="pr-20">Token No.:-</span> <?= $enrol ?>
    </div>

<pre style="line-height: 35px;border:unset;">

सेवा में,

<?= $co ?>
<?= $coAdd ?>

 विशय:- ई एस आई का सदस्य होने / न होने की सूचना के सम्बन्ध में।

 महोदय,

 निवेदन है कि मैं आपको सूचित कर रहा/रही हूँ   कि -
(1)	मै ई एस आई का कभी सदस्य नहीं था/थी।
(2)	मैने पहले <span class="underline text-center inline-block w-300"><?= $prevCo ?></span>कम्पनी में काम किया था  मेरा ई एस आई नम्बर <span class="underline text-center inline-block w-300"><?= $oldEsi ?></span> था।
 उपरोक्त सूचना सही है यदि इसमे कोई गलती पाई जाए तो इसकी पूर्णतः जिम्मेवारी मेरी होगी।

                                                                            भवदीय

                                                                            नाम -<?= $nm ?>
                                                                            पिता का नाम- <?= $father ?>
                                                                            टोकन - <?= $enrol ?>

दिनांक 	<?= $doj ?>



नोट:- (1) एवं (2) में जो लागू न हो उसे काट दे।
    </pre>

</div>
<!-- FORM OLD ESI APPLICATION END -->

<!-- ===================== PAGE 6 — LUNCH APPLICATION ===================== -->
<div class="pb"></div>

<div class="fs-20 mt-50 lh-30">
    <div class="text-right">
        <span class="pr-20">Token No.:-</span> <?= $enrol ?>
    </div>
    <div class="flex" style="line-height:40px!important;">
        <div class="pull-right">
            <span class="pr-20">दिनांक:-</span> <?= $doj ?>
        </div>
        <br>
    <?= $co ?>
         <br>
     <?= $coAdd ?></div>
   <br><br>
        विषय:   <span class="underline ml-10">विश्राम काल (दोपहर) में परिसर से बाहर भोजन करने के क्रम में।</span>
   <br><br>
        मान्यवर,
    <br><br>
        उपरोक्त विषय में निवेदन है कि मेरा निवास स्थान कारखाने के पास होने के कारण मैं भोजनावकाश के दौरान कैन्टीन में भोजन
        <br><br>ना करके अपने घर पर भोजन करने जाऊँगा/जाऊँगी। कृपया मुझे इसके लिये अनुमति प्रदान करें।
    <br><br><br>
        धन्यवाद
  <br><br>
        <div style="float:right">आवेदक</div>
     <br><br><br>
        स्वीकृत अधिकारी
  <br><br><br><br>
        ठेकेदार
    </div>
</div>
<!-- FORM LUNCH APPLICATION END -->

<!-- ===================== PAGE 7 — APPOINTMENT LETTER (OFFICE COPY) ===================== -->
<div class="pb"></div>
<!-- APPOINTMENT LETTER OFFICE -->
<div class="fs-20 mb-100 p-ml-50">
    <div class="text-right">
        <span class="pr-20">Token No.:-</span> <?= $enrol ?>
    </div>
    <div class="text-center fs-40 ws-15"><?= $co ?></div>
    <div class="text-center fs-30"></div>
    <div class="text-center fs-24"><?= $coAdd ?></div>
    <div class="bdr-top bdr-btm" style="height:3px;"></div>
    <div class="text-center">नियुक्ति पत्र</div>
    <div class="text-center">(Office Copy)</div>
    <div class="flex mt-30">
        <div class="pull-right">दिनांक:-<?= $intDt ?></div>
        <div>श्री/कुमारी/श्री मति <?= $nm ?></div>
    </div>
    <div>
        <div>
            पुत्र/पुत्री/पत्नी 	<?= $father ?>
        </div>
        <br>
        <div>
            <p>
                आपके द्वारा दिए गए प्रार्थना पत्र के संदर्भ में आपको सूचित किया जाता है कि मुझे <?= $contr ?: $co ?> से कार्यादेश प्राप्त हुआ है, इसके लिए मुझे अस्थायी रूप से श्रमिकों की जरूरत है। आपकी नियुक्ति दिनांक <?= $doj ?> से कार्यादेश(ठेका) रहने तक की जा रही है।
                यदि मेरा ठेका यहाँ नहीं रहता या अवधि समाप्त हो जाती है तो यदि मेरा ठेका कहीं ओर हुआ और आपके लिए कुछ काम हुआ तो आपको उस स्थान पर स्थानांतरित कर दिया जाएगा।
                यदि मेरा ठेका कहीं ओर नहीं हुआ और आपके लिए कुछ काम नहीं हुआ तो आपकी सेवाएं समाप्त कर दी जाएंगी।
            </p>
            <p>
                आपको सूचित किया जाता है कि मेरे द्वारा निम्न शर्तों पर आपकी नियुक्ति प्रदान की जाती है।
            </p>
            <table class="fs-20">
                <tbody>
                    <tr><td style="vertical-align:top" valign="top">1.</td><td class="pl-20">पद	<?= $desig ?></td></tr>
                    <tr><td style="vertical-align:top" valign="top">2.</td><td class="pl-20">आपको प्रतिमाह <?= $gross ?>/- रुपये वेतन का भुगतान किया जायेगा।<table class="ml-100"></table></td></tr>
                    <tr><td style="vertical-align:top" valign="top">3.</td><td class="pl-20">संस्थान में आपके द्वारा लगातार 240 दिन तक कार्य कर लेने के पश्चात् कारखाना 1948 के अन्तर्गत आपको प्रत्येक 20 दिन की उपस्थिति पर एक दिन का सवैतनिक अवकाश प्रदान किया जायेगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">4.</td><td class="pl-20">आपको बोनस अदायगी अधिनियम 1965 के अन्तर्गत नियमानुसार आपको बोनस का भुगतान किया जायेगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">5.</td><td class="pl-20">आप मातृत्व लाभांश अधिनियम 1961 या समय - समय पर इस संशोधित अधिनियम के अंतर्गत होने वाले परिवर्तित लाभांशों के लिए अधिकृत होंगे।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">6.</td><td class="pl-20">आपको ग्रेज्युटी अधिनियम के अन्तर्गत नियमानुसार ग्रेज्युटी का भुगतान किया जायेगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">7.</td><td class="pl-20">आपको प्रति कलैण्डर वर्ष में 7 दिनों का आकस्मिक अवकाश प्रदान किया जायेगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">8.</td><td class="pl-20">आपको प्रतिवर्ष 26 जनवरी, 15 अगस्त एवं गाँधी जयन्ती का राष्ट्रीय और 6 दिनों का उत्सव अवकाश प्रदान किया जायेगा। और यदि आपको इन दिनों पर काम के लिए बुलाया जाता है तो आपको नियमानुसार ओवरटाइम का भुगतान किया जाएगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">9.</td><td class="pl-20">आप हर उस काम को करेंगे जो आपको करने का आदेश दिया जाएगा क्योंकि मैं विभिन्न कार्यों के लिए मजदूर की आपूर्ति करता हूं। इसलिए कोई भी काम आपको दिया जा सकता है और आपको मेरे नियमानुसार वह काम करना होगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">10.</td><td class="pl-20">यह कि, मेरा ठेका के समाप्त होने या यदि मुझे आपके लिए कोई उपयुक्त काम नहीं मिलता है तो मुझे आपकी सेवाओं को समाप्त करने का अधिकार है। यदि कोई संवैधानिक देय राशि बकाया है तो आपको इसका पूरी तरह से भुगतान किया जाएगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">11.</td><td class="pl-20">लगातार छः दिनों के लिए काम करने पर आपको एक साप्ताहिक अवकाश प्रदान किया जाएगा। आपको अपना समय सारणी बताई जाएगी, जिसमे कभी भी परिवर्तन सुचना चस्पा कर दिया जा सकता है| अपने कार्य समय के घंटे कारखाना अधिनियम 1948 के अनुसार होंगे । आपको दिन/रात किसी भी शिफ्ट में काम करने के लिए कहा जा सकता है, जो नियोक्ता के कारखाने की चल रही व्यवस्था के आधार पर होगा। आपके 8 घंटे से अधिक कार्य करने पर नियमानुसार ओवरटाइम का भुगतान किया जायेगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">12.</td><td class="pl-20">आपको कर्मचारी भविष्य निधि एवं प्रकीर्ण उपबंद अधिनियम 1952 के अंतर्गत भविष्यनिधि के लाभ प्रदान किये जाएंगे। भविष्य निधी अंशदान के रूप में प्रतिमाह आपको देय मूल वेतन के 12 प्रतिशत राशि की कटौती की जाएगी एवं इसके बराबर की राशि का योगदान मेरे द्वारा दिया जाएगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">13.</td><td class="pl-20">आपको कर्मचारी राज्य बीमा अधिनियम 1948 के अन्तर्गत आवृत किया जाएगा एवं कार्ड जारी किये जाएगें जिसके अन्तर्गत आप व आपके परिवार को सुविधाएं पाने का हक होगा, इसके लिए प्रतिमाह आपके सकल वेतन में से 0.75 प्रतिशत की दर से राशि की कटौती की जाएगी।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">14.</td><td class="pl-20">आपके वेतन से श्रम कल्याण अधिनियम के तहत नियमानुसार कटौती की जाएगी।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">15.</td><td class="pl-20">शारीरिक दुर्बलता के कारण या पूर्ण अपंगता के कारण या किसी अन्य कारण से, यदि आप सुचारू रूप से काम करने में असमर्थ हैं आप अपनी सेवाओं से मुक्त कर दिए जाएंगे।</td></tr>
                    <tr><td style="vertical-align:top" class="v-top bdr-white">16.</td><td class="pl-20">यह है कि आप जिस ठेकेदार के अंतर्गत कार्य करेंगे   उसके श्रमिक /कर्मचारी रहेंगे परन्तु आप सस्थान के प्रत्यक्ष / अप्रत्यक्ष से कोई सम्बन्ध  नहीं रहेगा</td></tr>
                </tbody>
            </table>

            <div class="pb"></div>

            <table width="100%" class="fs-20">
                <tbody>
                    <tr><td style="vertical-align:top" valign="top">17.</td><td class="pl-20">यदि आप बिना सुचना के 3 दिन या उससे अधिक दिन छुट्टी /अवकाश पर रहते है तो आपके विरुद्ध अनुशासनात्मक / नियमानुसार कार्यवाही की जा सकती है</td></tr>
                    <tr><td style="vertical-align:top" valign="top">18.</td><td class="pl-20">यह कि आप किसी भी तरह से अनियमितता या अनुशासनहीनता पैदा नहीं करेंगे, जिस संस्थान में करेंगे उनके किसी भी अधिकारी, कर्मचारी से बदतमीजी से पेश आना उनका निरादर करना मानते हुए गंभीर दुराचरण के अंतर्गत आपके विरुद्ध कार्यवाही की जाएगी।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">19.</td><td class="pl-20">यह कि मेरे यहां नियोजित रहने की अवधि में आप किसी अन्य स्थान अथवा संस्थान में लाभ सहित या लाभ रहित किसी भी प्रकार का कार्य नहीं कर सकेंगे।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">20.</td><td class="pl-20">यदि आप इस अवधि में काम छोड़ना चाहते हैं तो आपको अपना त्याग पत्र मुझे देना होगा| आपको आपकी बकाया राशि(यदि कोई देय हो तो) का भुगतान कर दिया जाएगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">21.</td><td class="pl-20">आपके द्वारा 58 वर्ष की आयु ग्रहण कर लेने के पश्चात्, आपको मेरे द्वारा सेवा से सेवानिवृत्त कर दिया जाएगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">22.</td><td class="pl-20">आपको मुझसे किसी प्रकार का लोन या एडवांस नहीं दिया जाएगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">23.</td><td class="pl-20">यह कि अनुबंध के आधार पर जो कामगार / कारीगर की श्रेणी में आते है उनको न्यूनतम वेतन सुनिश्चित करते हुए बाजार में प्रचलित समकक्ष दरों के आधार पर उत्पादन प्रोत्साहन राशि भी प्रदान की जाएगी जो पीस रेट / औद्योगिक आधार पर होगी ।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">24.</td><td class="pl-20">संस्था के दस्तावेज / सुचना को गोपनीय रखना होगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">25.</td><td class="pl-20">संस्था में किसी भी प्रकार की "सुरक्षा जमा राशि" नहीं देनी होगी।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">26.</td><td class="pl-20">यह कि किसी कर्मचारी की मर्यादा/ गौरव के प्रति किसी भी तरह का शोषण या अभद्रता नहीं होगी तथा सुरक्षा कर्मचारी भी आश्वस्त करेगे कि किसी कर्मचारी की मर्यादा/ गौरव के प्रति किसी भी तरह का शोषण या अभद्रता ना हो।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">27.</td><td class="pl-20">यह कि किसी भी कर्मचारी से नौकरी के बाबत किसी भी तरह कि सुरक्षा राशि / पदोन्नति शुल्क / किसी दस्तावेज की मूल प्रति मानव संसाधन विभाग या किसी भी अन्य विभाग में जमा नहीं कि जाएगी।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">28.</td><td class="pl-20">ठेकेदार कोई कारण बताए बिना आपको पृथक कर सकता है, लेकिन इससे पहले ठेकेदार आपको एक महीने का नोटिस प्रदान करेगा या आपको नोटिस के बजाय वेतन भुगतान करेगा, और उसी तरह यदि आप ठेकेदार की सेवा त्यागते हैं तो आप एक महीने का पूर्व नोटिस प्रदान करेंगे या आप नोटिस के बदले एक महीने का भुगतान करेंगे।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">29.</td><td class="pl-20">यदि श्रमिक और ठेकेदार के माध्यम कोई वाद-विवाद उत्पन्न होता है, तो उसका निपटान ठेकेदारी अधिनियम (विनियम और उन्मुलन)1970 के अंतर्गत पानीपत (हरियाणा) में होगा।</td></tr>
                    <tr><td colspan="2" class="pt-20">यदि उपरोक्त शर्तों पर आप मेरे साथ काम करने के इच्छूक हैं तो आप इस पत्र की द्वितीय प्रति पर हस्ताक्षर करके काम ग्रहण कर सकते हैं।</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-20 text-right" style="padding-top:50px">
        (हस्ताक्षर ठेकेदार)
    </div>
    <div class="mt-20 h-100 flex align-end" style="padding-top:20px">
        <p>मैंने उपरोक्त शर्तेँ पढ़कर समझ ली है, मुझे स्वीकार है और इसकी एक प्रति प्राप्त कर ली है।</p>
    </div>
    <div class="mt-20 text-right" style="padding-top:20px">
        (हस्ताक्षर श्रमिक)
    </div>
</div>
<!-- APPOINTMENT LETTER OFFICE END -->

<!-- ===================== PAGE 7 — APPOINTMENT LETTER (EMPLOYEE COPY) ===================== -->
<div class="pb"></div>
<!-- APPOINTMENT LETTER EMPLOYEE -->
<div class="fs-20 mb-100 p-ml-50">
    <div class="text-right">
        <span class="pr-20">Token No.:-</span> <?= $enrol ?>
    </div>
    <div class="text-center fs-40 ws-15"><?= $co ?></div>
    <div class="text-center fs-30"></div>
    <div class="text-center fs-24"><?= $coAdd ?></div>
    <div class="bdr-top bdr-btm" style="height:3px;"></div>
    <div class="text-center">नियुक्ति पत्र</div>
    <div class="text-center">(Employee Copy)</div>
    <div class="flex mt-30">
        <div class="pull-right">दिनांक:-<?= $intDt ?></div>
        <div>श्री/कुमारी/श्री मति <?= $nm ?></div>
    </div>
    <div>
        <div>
            पुत्र/पुत्री/पत्नी 	<?= $father ?>
        </div>
        <br>
        <div>
            <p>
                आपके द्वारा दिए गए प्रार्थना पत्र के संदर्भ में आपको सूचित किया जाता है कि मुझे <?= $contr ?: $co ?> से कार्यादेश प्राप्त हुआ है, इसके लिए मुझे अस्थायी रूप से श्रमिकों की जरूरत है। आपकी नियुक्ति दिनांक <?= $doj ?> से कार्यादेश(ठेका) रहने तक की जा रही है।
                यदि मेरा ठेका यहाँ नहीं रहता या अवधि समाप्त हो जाती है तो यदि मेरा ठेका कहीं ओर हुआ और आपके लिए कुछ काम हुआ तो आपको उस स्थान पर स्थानांतरित कर दिया जाएगा।
                यदि मेरा ठेका कहीं ओर नहीं हुआ और आपके लिए कुछ काम नहीं हुआ तो आपकी सेवाएं समाप्त कर दी जाएंगी।
            </p>
            <p>
                आपको सूचित किया जाता है कि मेरे द्वारा निम्न शर्तों पर आपकी नियुक्ति प्रदान की जाती है।
            </p>
            <table class="fs-20">
                <tbody>
                    <tr><td style="vertical-align:top" valign="top">1.</td><td class="pl-20">पद	<?= $desig ?></td></tr>
                    <tr><td style="vertical-align:top" valign="top">2.</td><td class="pl-20">आपको प्रतिमाह <?= $gross ?>/- रुपये वेतन का भुगतान किया जायेगा।<table class="ml-100"></table></td></tr>
                    <tr><td style="vertical-align:top" valign="top">3.</td><td class="pl-20">संस्थान में आपके द्वारा लगातार 240 दिन तक कार्य कर लेने के पश्चात् कारखाना 1948 के अन्तर्गत आपको प्रत्येक 20 दिन की उपस्थिति पर एक दिन का सवैतनिक अवकाश प्रदान किया जायेगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">4.</td><td class="pl-20">आपको बोनस अदायगी अधिनियम 1965 के अन्तर्गत नियमानुसार आपको बोनस का भुगतान किया जायेगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">5.</td><td class="pl-20">आप मातृत्व लाभांश अधिनियम 1961 या समय - समय पर इस संशोधित अधिनियम के अंतर्गत होने वाले परिवर्तित लाभांशों के लिए अधिकृत होंगे।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">6.</td><td class="pl-20">आपको ग्रेज्युटी अधिनियम के अन्तर्गत नियमानुसार ग्रेज्युटी का भुगतान किया जायेगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">7.</td><td class="pl-20">आपको प्रति कलैण्डर वर्ष में 7 दिनों का आकस्मिक अवकाश प्रदान किया जायेगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">8.</td><td class="pl-20">आपको प्रतिवर्ष 26 जनवरी, 15 अगस्त एवं गाँधी जयन्ती का राष्ट्रीय और 6 दिनों का उत्सव अवकाश प्रदान किया जायेगा। और यदि आपको इन दिनों पर काम के लिए बुलाया जाता है तो आपको नियमानुसार ओवरटाइम का भुगतान किया जाएगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">9.</td><td class="pl-20">आप हर उस काम को करेंगे जो आपको करने का आदेश दिया जाएगा क्योंकि मैं विभिन्न कार्यों के लिए मजदूर की आपूर्ति करता हूं। इसलिए कोई भी काम आपको दिया जा सकता है और आपको मेरे नियमानुसार वह काम करना होगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">10.</td><td class="pl-20">यह कि, मेरा ठेका के समाप्त होने या यदि मुझे आपके लिए कोई उपयुक्त काम नहीं मिलता है तो मुझे आपकी सेवाओं को समाप्त करने का अधिकार है। यदि कोई संवैधानिक देय राशि बकाया है तो आपको इसका पूरी तरह से भुगतान किया जाएगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">11.</td><td class="pl-20">लगातार छः दिनों के लिए काम करने पर आपको एक साप्ताहिक अवकाश प्रदान किया जाएगा। आपको अपना समय सारणी बताई जाएगी, जिसमे कभी भी परिवर्तन सुचना चस्पा कर दिया जा सकता है| अपने कार्य समय के घंटे कारखाना अधिनियम 1948 के अनुसार होंगे । आपको दिन/रात किसी भी शिफ्ट में काम करने के लिए कहा जा सकता है, जो नियोक्ता के कारखाने की चल रही व्यवस्था के आधार पर होगा। आपके 8 घंटे से अधिक कार्य करने पर नियमानुसार ओवरटाइम का भुगतान किया जायेगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">12.</td><td class="pl-20">आपको कर्मचारी भविष्य निधि एवं प्रकीर्ण उपबंद अधिनियम 1952 के अंतर्गत भविष्यनिधि के लाभ प्रदान किये जाएंगे। भविष्य निधी अंशदान के रूप में प्रतिमाह आपको देय मूल वेतन के 12 प्रतिशत राशि की कटौती की जाएगी एवं इसके बराबर की राशि का योगदान मेरे द्वारा दिया जाएगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">13.</td><td class="pl-20">आपको कर्मचारी राज्य बीमा अधिनियम 1948 के अन्तर्गत आवृत किया जाएगा एवं कार्ड जारी किये जाएगें जिसके अन्तर्गत आप व आपके परिवार को सुविधाएं पाने का हक होगा, इसके लिए प्रतिमाह आपके सकल वेतन में से 0.75 प्रतिशत की दर से राशि की कटौती की जाएगी।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">14.</td><td class="pl-20">आपके वेतन से श्रम कल्याण अधिनियम के तहत नियमानुसार कटौती की जाएगी।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">15.</td><td class="pl-20">शारीरिक दुर्बलता के कारण या पूर्ण अपंगता के कारण या किसी अन्य कारण से, यदि आप सुचारू रूप से काम करने में असमर्थ हैं आप अपनी सेवाओं से मुक्त कर दिए जाएंगे।</td></tr>
                    <tr><td style="vertical-align:top" class="v-top bdr-white">16.</td><td class="pl-20">यह है कि आप जिस ठेकेदार के अंतर्गत कार्य करेंगे   उसके श्रमिक /कर्मचारी रहेंगे परन्तु आप सस्थान के प्रत्यक्ष / अप्रत्यक्ष से कोई सम्बन्ध  नहीं रहेगा</td></tr>
                </tbody>
            </table>

            <div class="pb"></div>

            <table width="100%" class="fs-20">
                <tbody>
                    <tr><td style="vertical-align:top" valign="top">17.</td><td class="pl-20">यदि आप बिना सुचना के 3 दिन या उससे अधिक दिन छुट्टी /अवकाश पर रहते है तो आपके विरुद्ध अनुशासनात्मक / नियमानुसार कार्यवाही की जा सकती है</td></tr>
                    <tr><td style="vertical-align:top" valign="top">18.</td><td class="pl-20">यह कि आप किसी भी तरह से अनियमितता या अनुशासनहीनता पैदा नहीं करेंगे, जिस संस्थान में करेंगे उनके किसी भी अधिकारी, कर्मचारी से बदतमीजी से पेश आना उनका निरादर करना मानते हुए गंभीर दुराचरण के अंतर्गत आपके विरुद्ध कार्यवाही की जाएगी।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">19.</td><td class="pl-20">यह कि मेरे यहां नियोजित रहने की अवधि में आप किसी अन्य स्थान अथवा संस्थान में लाभ सहित या लाभ रहित किसी भी प्रकार का कार्य नहीं कर सकेंगे।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">20.</td><td class="pl-20">यदि आप इस अवधि में काम छोड़ना चाहते हैं तो आपको अपना त्याग पत्र मुझे देना होगा| आपको आपकी बकाया राशि(यदि कोई देय हो तो) का भुगतान कर दिया जाएगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">21.</td><td class="pl-20">आपके द्वारा 58 वर्ष की आयु ग्रहण कर लेने के पश्चात्, आपको मेरे द्वारा सेवा से सेवानिवृत्त कर दिया जाएगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">22.</td><td class="pl-20">आपको मुझसे किसी प्रकार का लोन या एडवांस नहीं दिया जाएगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">23.</td><td class="pl-20">यह कि अनुबंध के आधार पर जो कामगार / कारीगर की श्रेणी में आते है उनको न्यूनतम वेतन सुनिश्चित करते हुए बाजार में प्रचलित समकक्ष दरों के आधार पर उत्पादन प्रोत्साहन राशि भी प्रदान की जाएगी जो पीस रेट / औद्योगिक आधार पर होगी ।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">24.</td><td class="pl-20">संस्था के दस्तावेज / सुचना को गोपनीय रखना होगा।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">25.</td><td class="pl-20">संस्था में किसी भी प्रकार की "सुरक्षा जमा राशि" नहीं देनी होगी।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">26.</td><td class="pl-20">यह कि किसी कर्मचारी की मर्यादा/ गौरव के प्रति किसी भी तरह का शोषण या अभद्रता नहीं होगी तथा सुरक्षा कर्मचारी भी आश्वस्त करेगे कि किसी कर्मचारी की मर्यादा/ गौरव के प्रति किसी भी तरह का शोषण या अभद्रता ना हो।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">27.</td><td class="pl-20">यह कि किसी भी कर्मचारी से नौकरी के बाबत किसी भी तरह कि सुरक्षा राशि / पदोन्नति शुल्क / किसी दस्तावेज की मूल प्रति मानव संसाधन विभाग या किसी भी अन्य विभाग में जमा नहीं कि जाएगी।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">28.</td><td class="pl-20">ठेकेदार कोई कारण बताए बिना आपको पृथक कर सकता है, लेकिन इससे पहले ठेकेदार आपको एक महीने का नोटिस प्रदान करेगा या आपको नोटिस के बजाय वेतन भुगतान करेगा, और उसी तरह यदि आप ठेकेदार की सेवा त्यागते हैं तो आप एक महीने का पूर्व नोटिस प्रदान करेंगे या आप नोटिस के बदले एक महीने का भुगतान करेंगे।</td></tr>
                    <tr><td style="vertical-align:top" valign="top">29.</td><td class="pl-20">यदि श्रमिक और ठेकेदार के माध्यम कोई वाद-विवाद उत्पन्न होता है, तो उसका निपटान ठेकेदारी अधिनियम (विनियम और उन्मुलन)1970 के अंतर्गत पानीपत (हरियाणा) में होगा।</td></tr>
                    <tr><td colspan="2" class="pt-20">यदि उपरोक्त शर्तों पर आप मेरे साथ काम करने के इच्छूक हैं तो आप इस पत्र की द्वितीय प्रति पर हस्ताक्षर करके काम ग्रहण कर सकते हैं।</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-20 text-right" style="padding-top:50px">
        (हस्ताक्षर ठेकेदार)
    </div>
    <div class="mt-20 h-100 flex align-end" style="padding-top:20px">
        <p>मैंने उपरोक्त शर्तेँ पढ़कर समझ ली है, मुझे स्वीकार है और इसकी एक प्रति प्राप्त कर ली है।</p>
    </div>
    <div class="mt-20 text-right" style="padding-top:20px">
        (हस्ताक्षर श्रमिक)
    </div>
</div>
<!-- APPOINTMENT LETTER EMPLOYEE END -->

<!-- ===================== PAGE 8 — ESI DECLARATION FORM 1 ===================== -->
<div class="pb"></div>
<!-- FORM ESI APPLICATION -->
<div class="text-right">
    Token No.:-<?= $enrol ?>
</div>

<table class="bdr" style="border-collapse: collapse; width: 100%; ">
    <tbody>
        <tr style="height: 15px;">
            <td style="border-right: 0px white; border-left: 1px solid;border-top: 1px solid;width: 30%; height: 15px; vertical-align: top;" rowspan="6">घोषणा पत्र<br />DECLARATION FORM</td>
            <td style="border-right: 0px white; border-top: 1px solid;width: 50%; height: 15px; text-align: center;" rowspan="6">विनिमय 11 व 12<br />Regulation 11 &amp; 12<br /><span class="fs-14">केवल उस स्थिति में भरा जाना है यदि कर्मचारी पहले बीमाक्रत न हुआ हो</span><br />(To be filled in only if the employee is not insured earlier)<br/><span class="fs-14">घोषणा प्रपत्र विवरणी 3 में क्रमिक संख्या</span><br />Serial No. in return of declaration form No. 3</td>
            <td style="border-right: 1px solid; border-top: 1px solid;width: 29.6121%; height: 90px; text-align: right; vertical-align: top;" rowspan="6"><div class="">प्रपत्-1</div><div class="">Form-1</div></td>
        </tr>
    </tbody>
</table>

<table style="border-collapse: collapse; width: 100%; " border="1">
    <tbody>
        <tr class="text-center">
            <td style="width: 10%; height: 15px; ">बीमा संः<br />Ins. No.<br />लिंग SEX</td>
            <td style="width: 10%; height: 45px; "><?= $esiNo ?><br><span><?= $gender ?></span></td>
            <td style="width: 20%; height: 15px; ">वैवाहिक स्थिति<br />Marital Status</td>
            <td style="width: 20%; height: 45px; " width="154"><?= $ms ?></td>
            <td style="width: 20%; height: 15px; ">नियोजक कूट संख्या&nbsp;<br />Employer's Code No.</td>
            <td style="width: 20%; height: 45px; " width="134"></td>
        </tr>
        <tr class="text-center">
            <td class="p-2" style="line-height:16px;" colspan="2">नाम मोटे अक्षरों में<br/>Name in the Block<br/>(Capital)</td>
            <td class="p-2" style="line-height:16px;" width="206"><?= $nm ?></td>
            <td class="p-2" style="line-height:16px;">जन्म का वर्ष<br />Year of Birth&nbsp;</td>
            <td class="p-2" style="line-height:16px;" colspan="2"><?= $dob ?></td>
        </tr>
        <tr class="text-center">
            <td class="p-2" style="line-height:16px;" colspan="2">पिता/पति का नाम<br />Father's/ Husband's<br />Name (Capital)</td>
            <td class="p-2" style="line-height:16px;"><?= $father ?></td>
            <td class="p-2" style="line-height:16px;">नियुक्ति की तारीख<br />Date of Appointment</td>
            <td class="p-2" style="line-height:16px;" colspan="2"><?= $doj ?></td>
        </tr>
    </tbody>
</table>

<table style="border-collapse: collapse; width: 100%;border-top: 0px;border-bottom: none;" border="1">
    <tbody>
        <tr>
            <td class="p-2" style="width: 20%; text-align: center;line-height:16px;">वर्तमान पता<br />Present Address</td>
            <td class="p-2" style="width: 30%; text-align: center;line-height:16px;"><?= $presAdd ?></td>
            <td class="p-2" style="width: 20%; text-align: center;line-height:16px;">स्थानीय कार्यालय<br />Local Office</td>
            <td class="p-2" style="width: 20%; text-align: center;line-height:16px;">Panipat</td>
        </tr>
        <tr>
            <td class="p-2" style="text-align: center;line-height:16px;" rowspan="3">घर का स्थायी पता<br />Permanent Home&nbsp;</td>
            <td class="p-2" style="text-align: center;line-height:16px;" rowspan="3"><?= $permAdd ?></td>
            <td class="p-2" style="text-align: center;line-height:16px;" rowspan="3">औषधालय/Dispansary</td>
            <td class="p-2" style="text-align: center;line-height:16px;" rowspan="3">Panipat</td>
        </tr>
    </tbody>
</table>

<table class="bdr w-100">
    <tbody>
        <tr>
            <td style="width: 100%; text-align: center;" rowspan="6"><span class="fs-14">अकिंत करे अविवाहित पुरूष/स्त्री विवाहित विधवा या विधुर</span><br />(State Whether Bachelor, Spinstar, Married Widow or widower)<br /><span class="fs-14">नोटः- परिवार के सदस्यो के विवरण की दो प्रतियां अपेक्षित है कृपया टी आईसी को नीचे मोड़े <br />तथा कार्बन लगा लें परिवार की परिभाषा के लिये पीछे देंखें।</span><br />Note : Family Particulars are required in duplicate please fold TIC below back word<br />insert carbon, Please see overleaf for definition of family.<br /><span class="fs-14">परिवार के सदस्यो का विवरण/Particulars of Family</span></td>
        </tr>
    </tbody>
</table>

<table class="w-100">
    <tbody>
        <tr>
            <td style="width: 100%;" class="fs-20">
                <table class="w-100" border="1">
                    <tbody>
                        <tr>
                            <td style="width: 8.09202%; text-align: center;">सं<br/>No.</td>
                            <td style="width: 22.9094%; text-align: center;">नाम<br/>Name</td>
                            <td style="width: 17.6319%; text-align: center;">जन्म की तारीख<br/>Date Of Birth</td>
                            <td style="width: 24.9391%; text-align: center;">बीमाक्रत व्यक्ति से सम्बन्ध<br/>Relationship with insured person</td>
                            <td style="width: 26.4276%; text-align: center;">क्या उसके साथ रहते है या अथवा नहीं<br/>Whether residing with him/her or not</td>
                        </tr>
                        <tr class="text-center"><td>1</td><td><?= $famRows[0][0] ?></td><td><?= $famRows[0][3] ?></td><td><?= $famRows[0][2] ?></td><td><?= $famRows[0][4] ?></td></tr>
                        <tr class="text-center"><td>2</td><td><?= $famRows[1][0] ?></td><td><?= $famRows[1][3] ?></td><td><?= $famRows[1][2] ?></td><td><?= $famRows[1][4] ?></td></tr>
                        <tr class="text-center"><td>3</td><td><?= $famRows[2][0] ?></td><td><?= $famRows[2][3] ?></td><td><?= $famRows[2][2] ?></td><td><?= $famRows[2][4] ?></td></tr>
                        <tr class="text-center"><td>4</td><td><?= $childRows[0][0] ?></td><td><?= $childRows[0][3] ?></td><td><?= $childRows[0][2] ?></td><td><?= $childRows[0][4] ?></td></tr>
                        <tr class="text-center"><td>5</td><td><?= $childRows[1][0] ?></td><td><?= $childRows[1][3] ?></td><td><?= $childRows[1][2] ?></td><td><?= $childRows[1][4] ?></td></tr>
                        <tr class="text-center"><td>6</td><td><?= $childRows[2][0] ?></td><td><?= $childRows[2][3] ?></td><td><?= $childRows[2][2] ?></td><td><?= $childRows[2][4] ?></td></tr>
                        <tr class="text-center"><td>7</td><td><?= $childRows[3][0] ?></td><td><?= $childRows[3][3] ?></td><td><?= $childRows[3][2] ?></td><td><?= $childRows[3][4] ?></td></tr>
                        <tr class="text-center"><td>8</td><td><?= $childRows[4][0] ?></td><td><?= $childRows[4][3] ?></td><td><?= $childRows[4][2] ?></td><td><?= $childRows[4][4] ?></td></tr>
                    </tbody>
                </table>

                <table width="100%" class="bdr-lt bdr-rt">
                    <tr class="text-center bdr-btm"><td colspan="4">इएसआईसी टी आई सी नियुक्ति की तारीख 13 सप्ताह तक वैध <br/> ESIC T.I.C. Valid for 13 week from the date of appointment</td></tr>
                    <tr class="text-center">
                        <td width="25%" class="p-3" style="line-height:16px;">बीमा संख्या <br/> Ins. No.</td>
                        <td width="25%" class="p-3" style="line-height:16px;"><?= $esiNo ?></td>
                        <td width="25%" class="p-3" style="line-height:16px;">नियुक्ति की तारीख <br/> Date of Appointment</td>
                        <td width="25%" class="p-3" style="line-height:16px;"><?= $doj ?></td>
                    </tr>
                </table>

                <table class="w-100" border="1">
                    <tbody>
                        <tr class="text-center">
                            <td class="p-3" style="width: 25%;line-height:16px;">नाम/<br>Name<br></td>
                            <td class="p-3" style="width: 25%;line-height:16px;"><?= $nm ?><br></td>
                            <td class="p-3" style="width: 25%;line-height:16px;">स्थानीय कार्यालय / <br>Local Office<br></td>
                            <td class="p-3" style="width: 25%;line-height:16px;">Panipat<br></td>
                        </tr>
                    </tbody>
                </table>
                <table class="w-100" border="1">
                    <tbody>
                        <tr class="text-center">
                            <td style="text-align: center;" width="25%">नियोजक का नाम व कुट संख्या<br />Name, address &amp; Code no. of the employer</td>
                            <td width="25%">
                                <div class="bdr-btm"><?= $contr ?: $co ?></div>
                                <div class="bdr-btm fs-14"><?= $coAdd ?></div>
                                <div class=""></div>
                            </td>
                            <td width="25%">औषधालय/<br>Dispansary</td>
                            <td width="25%">Panipat</td>
                        </tr>
                    </tbody>
                </table>
                <table class="bdr-btm bdr-rt bdr-lt w-100">
                    <tbody>
                        <tr>
                            <td style="width: 50%; vertical-align: top;" rowspan="8">परिचय प़त्र की पावती<br />Receipt of the Identity Card</td>
                            <td style="width: 50%; text-align: right;" rowspan="8">बीमा व्यक्ति के हस्ताक्षर या अंगूठा निशान<br />&nbsp;Signature or thumb impression of the insured person<br />उपर लिखित बीमा संख्या का परिचय पत्र-प्राप्त किया<br />Received the identity Card bearing Ins. No. as over leaf<br />हस्ताक्षर/Signature<br />बीमाक्रत व्यक्ति/Insured Person</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>

<table cellspacing="0" style="width: 100%; margin-left: 20px;position: relative;">
    <tbody>
        <tr>
            <td style="width: 100%;" class="fs-20">
                <table class="w-100" border="1">
                    <tbody>
                        <tr>
                            <td style="width: 100%; text-align: center;">परिवार से अभिप्रायः बीमाक्रत व्यक्ति पर आश्रित पति या पत्नी और वैध दतक नाबालिक बच्चे तथा<br />उस पर आश्रित माता-पिता से है कृपया क़ रा़ बी़ अधिनियम 1948 का पैरा (ii) दो <br />&nbsp;Family means the spouse and minor legitimate adopted children dependent on the insured&nbsp;<br />&nbsp;person and his dependant parents (See para (ii) of the ESI ACT 1948) 'A'</td>
                        </tr>
                    </tbody>
                </table>
                <table class="bdr-rt bdr-lt">
                    <tbody>
                        <tr class="w-100 bdr-tp">
                            <td class="bdr-rt" style="width: 33.3333%; height: 43.8889px;" rowspan="2">नियोजक का विवरण&nbsp;<br />Particulars of Employment &nbsp;</td>
                            <td style="width: 33.3333%;" class="bdr-btm bdr-rt" colspan="2">क्या सीधे/ठेकेदार द्वारा नियुक्ति की गयी <br>Whether employed directly/through contractor</td>
                            <td style="width: 33.3333%;" class="bdr-btm bdr-rt" colspan="2"><strong>DIRECT</strong></td>
                        </tr>
                        <tr>
                            <td class="bdr-rt">विभाग / <br>DEPARTMENT</td>
                            <td class="bdr-rt"><strong><?= $dept ?></strong></td>
                            <td class="bdr-rt">कार्य का स्वरूप / <br>Nature of Work Incharge</td>
                            <td><?= $desig ?></td>
                        </tr>
                    </tbody>
                </table>
                <table style="border-collapse: collapse; width: 100%;" border="1">
                    <tbody>
                        <tr>
                            <td style="width: 100%; text-align: center;">कर्मचारी राज्य बीमा अधिनियम की धारा 50 (2) केवल महिलाओ के लिए तथा 71 के अंतर्गत देय हितलाभ<br />जो इन धाराओं में उल्लेखित है के हेतु नामांकन<br />Nomination u/s 50 (2) for femals only and 71 of the ESI Act. For payment of any benefit that may be&nbsp;&nbsp;<br />due in the event of death.</td>
                        </tr>
                    </tbody>
                </table>
                <table class="w-100 bdr-btm bdr-rt bdr-lt">
                    <tbody>
                        <tr>
                            <td class="bdr-rt" style="width: 16.6667%; text-align: center;">नामित व्यक्ति का नाम<br />Name of Nominee</td>
                            <td class="bdr-rt" style="width: 16.6667%; text-align: center;"><?= $nom1 ?></td>
                            <td class="bdr-rt" style="width: 16.6667%; text-align: center;">आयु /Age&nbsp;&nbsp;</td>
                            <td class="bdr-rt" style="width: 16.6667%; text-align: center;"><?= $nomAge1 ?></td>
                            <td class="bdr-rt" style="width: 16.6667%; text-align: center;">वर्ष/Year&nbsp;</td>
                            <td style="width: 16.6667%; text-align: center;"><?= $nomDob1 ?></td>
                        </tr>
                    </tbody>
                </table>
                <table class="bdr-btm bdr-rt bdr-lt w-100">
                    <tbody>
                        <tr>
                            <td class="bdr-rt" style="width: 25%; text-align: center;">पिता/पति का नाम<br />Father's/Husband's Name&nbsp;</td>
                            <td class="bdr-rt" style="width: 25%; text-align: center;"><?= $nomFH1 ?></td>
                            <td class="bdr-rt" style="width: 25%; text-align: center;">पता/Address</td>
                            <td style="width: 27.0974%; text-align: center;" width="192"><?= $presAdd ?></td>
                        </tr>
                    </tbody>
                </table>
                <table style="border-top: 0px;border-collapse: collapse; width: 100%;" border="1">
                    <tbody>
                        <tr>
                            <td style="width: 40%; text-align: center;">बीमाक्रत व्यक्ति से नामित व्यक्ति का सम्बन्ध<br />Relationship of the Nominee with insured person</td>
                            <td style="width: 30%; text-align: center;"><?= $nomRel1 ?></td>
                            <td style="width: 30%; text-align: center;"><br/><br/> </td>
                        </tr>
                    </tbody>
                </table>
                <table class="bdr-btm bdr-rt bdr-lt" style="width: 100%; height: 200px;">
                    <tbody>
                        <tr>
                            <td style="width: 100%; text-align: center;" colspan="2" rowspan="6">मैं घोषणा करता/करती हूँ कि अधिनियम के अंतर्गत बीमित नही था/थी और न ही कोई पहचान पत्र जारी किया गया है।<br />मैं एतद द्वारा घोषित करता हूँ कि उपरोक्त दिया हुआ विवरण मेरी जानकारी एंव विश्वास के अनुसार सत्य है।<br />मैं एतद वचन देता/देती हूँ कि मेरे परिवार में कोई परिवर्तन होने पर मैं इस परिवर्तन की सूचना 15 दिन अवधि के<br />अन्दर निगम को सूचित कंरूगा/करूँगी।<br />I affirm that I have not been previously insured under the Act and no indentity card has issued to me.<br />I hereby declare that the above particulars have been given be me and are correct to the best of my knowledge<br />and belief. I also undertake to intimate the corporation any change in the membership of my family within 15<br />days of such change having accured.</td>
                        </tr>
                    </tbody>
                </table>
                <table class="bdr-lt bdr-rt w-100">
                    <tbody>
                        <tr class="">
                            <td class="pt-25" style="width: 49.4695%; ">स्थान/Place:&nbsp;PANIPAT<br/>_______________________________________________<br />पपत्र पर हस्ताक्षर करने की तारीख<br />Date of Signing the Form<br />नियोजक का नाम व पता<br />Name and address of employer</td>
                            <td style="width: 49.4695%; text-align: right;">________________________________<br/>कर्मचारी के हस्ताक्षर या अंगूठा निशान<br />Signature /thumb inpression of the employee<br/> नियोजक के प्रति हस्ताक्षर<br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Counter Signature of Employer</td>
                        </tr>
                    </tbody>
                </table>
                <table class="bdr-rt bdr-lt w-100">
                    <tbody>
                        <tr class="text-center lh-20">
                            <td>नाम/Name<br />पद में/in Designation<br />....................................................</td>
                        </tr>
                    </tbody>
                </table>
                <table class="w-100" border="1">
                    <tbody>
                        <tr>
                            <td style="width: 8.09202%; text-align: center;">सं<br/>No.</td>
                            <td style="width: 22.9094%; text-align: center;">नाम<br/>Name</td>
                            <td style="width: 17.6319%; text-align: center;">जन्म की तारीख<br/>Date Of Birth</td>
                            <td style="width: 24.9391%; text-align: center;">बीमाक्रत व्यक्ति से सम्बन्ध<br/>Relationship with insured person</td>
                            <td style="width: 26.4276%; text-align: center;">क्या उसके साथ रहते है या अथवा नहीं<br/>Whether residing with him/her or not</td>
                        </tr>
                        <tr class="text-center"><td>1</td><td><?= $famRows[0][0] ?></td><td><?= $famRows[0][3] ?></td><td><?= $famRows[0][2] ?></td><td><?= $famRows[0][4] ?></td></tr>
                        <tr class="text-center"><td>2</td><td><?= $famRows[1][0] ?></td><td><?= $famRows[1][3] ?></td><td><?= $famRows[1][2] ?></td><td><?= $famRows[1][4] ?></td></tr>
                        <tr class="text-center"><td>3</td><td><?= $famRows[2][0] ?></td><td><?= $famRows[2][3] ?></td><td><?= $famRows[2][2] ?></td><td><?= $famRows[2][4] ?></td></tr>
                        <tr class="text-center"><td>4</td><td><?= $childRows[0][0] ?></td><td><?= $childRows[0][3] ?></td><td><?= $childRows[0][2] ?></td><td><?= $childRows[0][4] ?></td></tr>
                        <tr class="text-center"><td>5</td><td><?= $childRows[1][0] ?></td><td><?= $childRows[1][3] ?></td><td><?= $childRows[1][2] ?></td><td><?= $childRows[1][4] ?></td></tr>
                        <tr class="text-center"><td>6</td><td><?= $childRows[2][0] ?></td><td><?= $childRows[2][3] ?></td><td><?= $childRows[2][2] ?></td><td><?= $childRows[2][4] ?></td></tr>
                        <tr class="text-center"><td>7</td><td><?= $childRows[3][0] ?></td><td><?= $childRows[3][3] ?></td><td><?= $childRows[3][2] ?></td><td><?= $childRows[3][4] ?></td></tr>
                        <tr class="text-center"><td>8</td><td><?= $childRows[4][0] ?></td><td><?= $childRows[4][3] ?></td><td><?= $childRows[4][2] ?></td><td><?= $childRows[4][4] ?></td></tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>
<!-- FORM ESI APPLICATION END -->

<!-- ===================== PAGE 9 — FORM 2 PF NOMINATION (COPY 1) ===================== -->
<div class="pb"></div>
<!-- FORM 2 REVISED -->
<div class="text-right"><span class="pr-20">Token No.:-</span> <?= $enrol ?></div>
<div class="mb-100 p-ml-50">
    <div class="form-2 lh-30">
        <div class="heading">
            <div class="bold fs-24 text-center">FORM-2 (REVISED)</div>
            <div class="text-center bold fs-22">NOMINATION AND DECLARATION FORM FOR UNEXEMPTED/EXEMPTED ESTABLISHMENTS</div>
            <div class="text-center fs-22">Declaration and Nomination Form under the Employee's Provident Funds Employee's Pension Scheme.</div>
            <div class="text-center fs-18">(Paragraph 33 61 (1) of the Employees Provident Fund Scheme, 1952 Paragraph 18 of the Employee's Scheme, 1995)</div>
            <br>
        </div>
        <table class="mt-10 fs-20" width="100%">
            <tr>
                <td width="1%"><sub>1.</sub></td><td width="15%" class="pl-10">NAME (IN BLOCK LETTERS)</td><td width="20%" class="bdr-btm"><?= $nm ?></td>
                <td width="1%"><sub>4.</sub></td><td width="15%" class="pl-10">Sex</td><td width="20%" class="bdr-btm"><?= $gender ?></td>
            </tr>
            <tr>
                <td><sub>2.</sub></td><td width="15%" class="pl-10">FATHER'S/ HUSBAND'S NAME</td><td class="bdr-btm"><?= $father ?></td>
                <td><sub>5.</sub></td><td class="pl-10">MATRIMONIAL <br>STATUS</td><td class="bdr-btm"><?= $ms ?></td>
            </tr>
            <tr>
                <td><sub>3.</sub></td><td width="15%" class="pl-10">DATE OF BIRTH</td><td class="bdr-btm"><?= $dob ?></td>
                <td><sub>6.</sub></td><td class="pl-10">ACCOUNT NO </td><td class="bdr-btm">/<?= $pfNo ?></td>
            </tr>
        </table>
        <br>
        <table width="100%" class="fs-20">
            <tr><td width="1.5%"><sub>7.</sub></td><td width="20%" class="pl-10">ADDRESS PERMANENT</td><td width="77%" class="bdr-btm"><?= $permAdd ?></td></tr>
            <tr><td colspan="3" class="bdr-btm">&nbsp;</td></tr>
        </table>
        <table width="100%" class="fs-20">
            <tr><td width="10%" colspan="2">TEMPORARY</td><td width="77%" class="bdr-btm"><?= $presAdd ?></td></tr>
        </table>
        <br>
        <div class="mt-20 fs-20"><p class="bold text-center mb-0">PART-A (EPF)</p><p class="fs-14 text-center mt-0 mb-0">I HEREBY NOMINATE THE PERSON (S) CANCLE THE NOMINATION MADE BY THE PREVIOUSLY AND NOMINATE THE PERSON (S) MENTIONED</p></div>
        <br>
        <div class="fs-20"><p class="text-center bold mb-0">BELOW TO RECEIVE</p><p class="fs-18 bold text-center mt-0 mb-0">THE AMOUNT STANDING TO MY CREDIT IN THE EMPLOYEE'S PROVIDENT FUND, IN THE EVENT OF MY DEATH.</p></div>
        <br>
        <table width="100%">
            <tr class="bdr fs-20 text-center v-top">
                <td width="15%" class="bdr-rt fs-14 bold">NAME OF THE NOMINEE/NOMINEES</td>
                <td width="20%" class="bdr-rt fs-14 bold">ADDRESS</td>
                <td width="15%" class="bdr-rt fs-14 bold">NOMINEES RELATIONSHIP WITH THE MEMBER</td>
                <td width="19%" class="bdr-rt fs-14 bold">DATE OF BIRTH</td>
                <td width="15%" class="bdr-rt fs-14 bold">TOTAL AMT. OF SHARE OF ACCUMULATIONS IN PF TO BE PAID TO EACH NOMINEE</td>
                <td width="25%" class="fs-14 bold">IF THE NOMINEE IS A MINOR NAME &amp; RELATIONSHIP &amp; ADDRESS OF THE GUARDIAN WHO MAY RECEIVE THE AMT. DURING THE MINORITY OF THE NOMINEE</td>
            </tr>
            <tr class="bdr fs-20 text-center v-top">
                <td class="bdr-rt text-center py-2">1</td><td class="bdr-rt text-center py-2">2</td><td class="bdr-rt text-center py-2">3</td><td class="bdr-rt text-center py-2">4</td><td class="bdr-rt text-center py-2">5</td><td class="bdr-rt text-center py-2">6</td>
            </tr>
            <tr class="bdr fs-20 text-center">
                <td class="bdr-rt"><?= $nom1 ?></td>
                <td class="bdr-rt"><?= $permAdd ?></td>
                <td class="bdr-rt"><?= $nomRel1 ?></td>
                <td class="bdr-rt"><?= $nomDob1 ?></td>
                <td class="bdr-rt">100%</td>
                <td class="bdr-rt"></td>
            </tr>
        </table>
        <br><br>
        <div class="bold fs-20">
            1. CERTIFIED THAT I HAVE NOFAMILY AS DEFINED IN PARA 2 (g) OF THE EMPLOYEES PROVIDENT FUND SCHEME,1952 AND SHOULD I ACQUIRE A FAMILY. HERE AFTER THE ABOVE NOMINATION SHOULD BE DEEMED AS CANCELLED.<br><br>
            2. CERTIFIED THAT MY FATHER/MOTHER IS/ARE DEPENDENT UP ON ME.
        </div>
        <br>
        <div class="h-100 flex justify-end align-end fs-20 pb">
            <div class="bold" style="width:78%">*STRIKE OUT WHICHEVER IS NOT APPLICABLE</div>
            <br><br><br><br>
            <div class="bold ml-auto text-right">SIGNATURE OR THUMB IMPRESSION OF THE SUBSCRIBER</div>
        </div>
        <br>
        <div class="bold text-center fs-20 mt-50">I HEREBY FURNISH BELOW PARTICULERS OF THE MEMBERS OF MY FAMILY WHO WOULD BE ELIGBLE TO RECEIVE WIDOW/CHILDREN PENSION IN THE EVENT OF MY DEATH</div>
        <table width="100%" class="fs-20 mt-20 bdr">
            <tr class="bdr v-top text-center">
                <td width="10%" class="bdr-rt py-2">SL. NO.</td>
                <td width="30%" class="bdr-rt py-2">NAME &amp; ADDRESS OF THE FAMILY MEMBER</td>
                <td width="20%" class="bdr-rt py-2">ADDRESS</td>
                <td width="20%" class="bdr-rt py-2">DATE OF BIRTH</td>
                <td width="20%">RELATIONSHIP WITH THE MEMBER</td>
            </tr>
            <tr class="bdr text-center"><td class="bdr-rt py-2">1</td><td class="bdr-rt py-2"><?= $nom1 ?></td><td class="bdr-rt py-2" rowspan="10"><?= $permAdd ?></td><td class="bdr-rt py-2"><?= $nomDob1 ?></td><td class="bdr-rt py-2"><?= $nomRel1 ?></td></tr>
            <tr class="bdr text-center"><td class="bdr-rt py-2">2</td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td></tr>
            <tr class="bdr text-center"><td class="bdr-rt py-2">3</td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td></tr>
            <tr class="bdr text-center"><td class="bdr-rt py-2">4</td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td></tr>
            <tr class="bdr text-center"><td class="bdr-rt py-2">5</td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td></tr>
            <tr class="bdr text-center"><td class="bdr-rt py-2">6</td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td></tr>
            <tr class="bdr text-center"><td class="bdr-rt py-2">7</td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td></tr>
        </table>
        <div>
            <p class="bold fs-18" style="line-height: 25px;">** CERTIFIED THAT I HAVE NO FAMILY AS DEFINED IN PARA 2(VII) OF EMPLOYEES' PENSION SCHEME, 1995 AND SHOULD I ACQUIRE A FAMILY HERE AFTER I SHALL FURNISH PARTICULARS THERE ON IN THE ABOVE FORM. I HEREBY NOMINATE THE FOLLOWING PERSON FOR RECEIVING THE MONTHLY WIDOW PENSION (ADMISSIBLE UNDER PARA 16 2 (A) (I) &amp; (II) IN THE EVENT OF MY DEATH WITHOUT LEAVING ANY ELIGIBLE FAMILY MEMBER FOR RECEIVING PENSION</p>
        </div>
        <table width="100%" class="mt-10 bdr fs-20">
            <tr class="text-center bdr-btm">
                <td width="40%" class="bdr-rt">NAME AND ADDRESS OF THE NOMINEE</td>
                <td width="25%" class="bdr-rt">DATE OF BIRTH</td>
                <td width="35%" class="">RELATIONSHIP WITH MEMBER</td>
            </tr>
            <tr class="text-center bdr-btm"><td width="40%" class="bdr-rt"><?= $nom1 ?>&nbsp;</td><td width="25%" class="bdr-rt"><?= $nomDob1 ?></td><td width="35%" class=""><?= $nomRel1 ?></td></tr>
            <tr class="text-center bdr-btm"><td width="40%" class="bdr-rt"><?= $permAdd ?>&nbsp;</td><td width="25%" class="bdr-rt"></td><td width="35%" class=""></td></tr>
        </table>
        <div class="flex mt-10 bold fs-20">
            <div class="w-100">DATE:-</div>
            <div class="w-500"><?= $doj ?></div>
            <br><br>
            <div class="w-437 ml-auto text-right">SIGNATURE OR THUMB IMPRESSION OF THE SUBSCRIBER</div>
        </div>
        <div class="bold fs-20">"STRIKE OUT WHICHEVER IS NOT APPLICABLE</div>
        <div class="bold mt-20">
            <p class="text-center fs-20 text-underline">CERTIFICATE BY EMPLOYER</p>
            <p class="fs-18 emp-cert-body">CERTIFIED THAT THE ABOVE DECLARATION AND NOMINATION HAS BEEN SIGNED/THUMB INPRESSED BEFORE ME BY <span class="inline-block w-200">SHRI/SMT/KUM</span>	<span class="inline-block fs-20 underline"><?= $nm ?></span><br/>EMPLOYED IN MY EASTBLISHMENT AFTER HE/SHE HAS READ THE ENTRIES/ENTRIES HAVE BEEN READ OVER TO HIM/HER BY ME AND GOT CONFIRMED BY HIM/HER.</p>
        </div>
        <div class="flex justify-end fs-18" style="float:right">
            <br><br><br><br><br>
            <div class="bold">SIGNATURE OF THE EMPLOYER OR OTHER<br><br>AUTHORISED OFFICERS OF THE ESTT.<br>DESINAGATION<br><br>NAME &amp; ADDRESS OF THE FACTORY /ESTT<br>OR RUBBER STAMP THEREOF.</div>
        </div>
        <div class="bold fs-18">PLACE PANIPAT</div>
        <div class="flex fs-18">
            <div class="bold w-100">DATE : <?= $doj ?></div>
        </div>
    </div>
</div>
<!-- FORM 2 REVISED END -->

<!-- ===================== PAGE 9 — FORM 2 PF NOMINATION (COPY 2) ===================== -->
<div class="pb"></div>
<div class="text-right"><span class="pr-20">Token No.:-</span> <?= $enrol ?></div>
<div class="mb-100 p-ml-50">
    <div class="form-2 lh-30">
        <div class="heading">
            <div class="bold fs-24 text-center">FORM-2 (REVISED)</div>
            <div class="text-center bold fs-22">NOMINATION AND DECLARATION FORM FOR UNEXEMPTED/EXEMPTED ESTABLISHMENTS</div>
            <div class="text-center fs-22">Declaration and Nomination Form under the Employee's Provident Funds Employee's Pension Scheme.</div>
            <div class="text-center fs-18">(Paragraph 33 61 (1) of the Employees Provident Fund Scheme, 1952 Paragraph 18 of the Employee's Scheme, 1995)</div>
            <br>
        </div>
        <table class="mt-10 fs-20" width="100%">
            <tr>
                <td width="1%"><sub>1.</sub></td><td width="15%" class="pl-10">NAME (IN BLOCK LETTERS)</td><td width="20%" class="bdr-btm"><?= $nm ?></td>
                <td width="1%"><sub>4.</sub></td><td width="15%" class="pl-10">Sex</td><td width="20%" class="bdr-btm"><?= $gender ?></td>
            </tr>
            <tr>
                <td><sub>2.</sub></td><td width="15%" class="pl-10">FATHER'S/ HUSBAND'S NAME</td><td class="bdr-btm"><?= $father ?></td>
                <td><sub>5.</sub></td><td class="pl-10">MATRIMONIAL <br>STATUS</td><td class="bdr-btm"><?= $ms ?></td>
            </tr>
            <tr>
                <td><sub>3.</sub></td><td width="15%" class="pl-10">DATE OF BIRTH</td><td class="bdr-btm"><?= $dob ?></td>
                <td><sub>6.</sub></td><td class="pl-10">ACCOUNT NO </td><td class="bdr-btm">/<?= $pfNo ?></td>
            </tr>
        </table>
        <br>
        <table width="100%" class="fs-20">
            <tr><td width="1.5%"><sub>7.</sub></td><td width="20%" class="pl-10">ADDRESS PERMANENT</td><td width="77%" class="bdr-btm"><?= $permAdd ?></td></tr>
            <tr><td colspan="3" class="bdr-btm">&nbsp;</td></tr>
        </table>
        <table width="100%" class="fs-20">
            <tr><td width="10%" colspan="2">TEMPORARY</td><td width="77%" class="bdr-btm"><?= $presAdd ?></td></tr>
        </table>
        <br>
        <div class="mt-20 fs-20"><p class="bold text-center mb-0">PART-A (EPF)</p><p class="fs-14 text-center mt-0 mb-0">I HEREBY NOMINATE THE PERSON (S) CANCLE THE NOMINATION MADE BY THE PREVIOUSLY AND NOMINATE THE PERSON (S) MENTIONED</p></div>
        <br>
        <div class="fs-20"><p class="text-center bold mb-0">BELOW TO RECEIVE</p><p class="fs-18 bold text-center mt-0 mb-0">THE AMOUNT STANDING TO MY CREDIT IN THE EMPLOYEE'S PROVIDENT FUND, IN THE EVENT OF MY DEATH.</p></div>
        <br>
        <table width="100%">
            <tr class="bdr fs-20 text-center v-top">
                <td width="15%" class="bdr-rt fs-14 bold">NAME OF THE NOMINEE/NOMINEES</td>
                <td width="20%" class="bdr-rt fs-14 bold">ADDRESS</td>
                <td width="15%" class="bdr-rt fs-14 bold">NOMINEES RELATIONSHIP WITH THE MEMBER</td>
                <td width="19%" class="bdr-rt fs-14 bold">DATE OF BIRTH</td>
                <td width="15%" class="bdr-rt fs-14 bold">TOTAL AMT. OF SHARE OF ACCUMULATIONS IN PF TO BE PAID TO EACH NOMINEE</td>
                <td width="25%" class="fs-14 bold">IF THE NOMINEE IS A MINOR NAME &amp; RELATIONSHIP &amp; ADDRESS OF THE GUARDIAN WHO MAY RECEIVE THE AMT. DURING THE MINORITY OF THE NOMINEE</td>
            </tr>
            <tr class="bdr fs-20 text-center v-top">
                <td class="bdr-rt text-center py-2">1</td><td class="bdr-rt text-center py-2">2</td><td class="bdr-rt text-center py-2">3</td><td class="bdr-rt text-center py-2">4</td><td class="bdr-rt text-center py-2">5</td><td class="bdr-rt text-center py-2">6</td>
            </tr>
            <tr class="bdr fs-20 text-center">
                <td class="bdr-rt"><?= $nom1 ?></td><td class="bdr-rt"><?= $permAdd ?></td><td class="bdr-rt"><?= $nomRel1 ?></td><td class="bdr-rt"><?= $nomDob1 ?></td><td class="bdr-rt">100%</td><td class="bdr-rt"></td>
            </tr>
        </table>
        <br><br>
        <div class="bold fs-20">1. CERTIFIED THAT I HAVE NOFAMILY AS DEFINED IN PARA 2 (g) OF THE EMPLOYEES PROVIDENT FUND SCHEME,1952 AND SHOULD I ACQUIRE A FAMILY. HERE AFTER THE ABOVE NOMINATION SHOULD BE DEEMED AS CANCELLED.<br><br>2. CERTIFIED THAT MY FATHER/MOTHER IS/ARE DEPENDENT UP ON ME.</div>
        <br>
        <div class="h-100 flex justify-end align-end fs-20 pb">
            <div class="bold" style="width:78%">*STRIKE OUT WHICHEVER IS NOT APPLICABLE</div>
            <div class="bold ml-auto text-right">SIGNATURE OR THUMB IMPRESSION OF THE SUBSCRIBER</div>
        </div>
        <br>
        <div class="bold text-center fs-20 mt-50">I HEREBY FURNISH BELOW PARTICULERS OF THE MEMBERS OF MY FAMILY WHO WOULD BE ELIGBLE TO RECEIVE WIDOW/CHILDREN PENSION IN THE EVENT OF MY DEATH</div>
        <table width="100%" class="fs-20 mt-20 bdr">
            <tr class="bdr v-top text-center">
                <td width="10%" class="bdr-rt py-2">SL. NO.</td><td width="30%" class="bdr-rt py-2">NAME &amp; ADDRESS OF THE FAMILY MEMBER</td><td width="20%" class="bdr-rt py-2">ADDRESS</td><td width="20%" class="bdr-rt py-2">DATE OF BIRTH</td><td width="20%">RELATIONSHIP WITH THE MEMBER</td>
            </tr>
            <tr class="bdr text-center"><td class="bdr-rt py-2">1</td><td class="bdr-rt py-2"><?= $nom1 ?></td><td class="bdr-rt py-2" rowspan="10"><?= $permAdd ?></td><td class="bdr-rt py-2"><?= $nomDob1 ?></td><td class="bdr-rt py-2"><?= $nomRel1 ?></td></tr>
            <tr class="bdr text-center"><td class="bdr-rt py-2">2</td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td></tr>
            <tr class="bdr text-center"><td class="bdr-rt py-2">3</td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td></tr>
            <tr class="bdr text-center"><td class="bdr-rt py-2">4</td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td></tr>
            <tr class="bdr text-center"><td class="bdr-rt py-2">5</td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td></tr>
            <tr class="bdr text-center"><td class="bdr-rt py-2">6</td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td></tr>
            <tr class="bdr text-center"><td class="bdr-rt py-2">7</td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td><td class="bdr-rt py-2"></td></tr>
        </table>
        <div><p class="bold fs-18" style="line-height: 25px;">** CERTIFIED THAT I HAVE NO FAMILY AS DEFINED IN PARA 2(VII) OF EMPLOYEES' PENSION SCHEME, 1995 AND SHOULD I ACQUIRE A FAMILY HERE AFTER I SHALL FURNISH PARTICULARS THERE ON IN THE ABOVE FORM. I HEREBY NOMINATE THE FOLLOWING PERSON FOR RECEIVING THE MONTHLY WIDOW PENSION (ADMISSIBLE UNDER PARA 16 2 (A) (I) &amp; (II) IN THE EVENT OF MY DEATH WITHOUT LEAVING ANY ELIGIBLE FAMILY MEMBER FOR RECEIVING PENSION</p></div>
        <table width="100%" class="mt-10 bdr fs-20">
            <tr class="text-center bdr-btm"><td width="40%" class="bdr-rt">NAME AND ADDRESS OF THE NOMINEE</td><td width="25%" class="bdr-rt">DATE OF BIRTH</td><td width="35%" class="">RELATIONSHIP WITH MEMBER</td></tr>
            <tr class="text-center bdr-btm"><td width="40%" class="bdr-rt"><?= $nom1 ?>&nbsp;</td><td width="25%" class="bdr-rt"><?= $nomDob1 ?></td><td width="35%" class=""><?= $nomRel1 ?></td></tr>
            <tr class="text-center bdr-btm"><td width="40%" class="bdr-rt"><?= $permAdd ?>&nbsp;</td><td width="25%" class="bdr-rt"></td><td width="35%" class=""></td></tr>
        </table>
        <div class="flex mt-10 bold fs-20"><div class="w-100">DATE:-</div><div class="w-500"><?= $doj ?></div><br><br><div class="w-437 ml-auto text-right">SIGNATURE OR THUMB IMPRESSION OF THE SUBSCRIBER</div></div>
        <div class="bold fs-20">"STRIKE OUT WHICHEVER IS NOT APPLICABLE</div>
        <div class="bold mt-20">
            <p class="text-center fs-20 text-underline">CERTIFICATE BY EMPLOYER</p>
            <p class="fs-18 emp-cert-body">CERTIFIED THAT THE ABOVE DECLARATION AND NOMINATION HAS BEEN SIGNED/THUMB INPRESSED BEFORE ME BY <span class="inline-block w-200">SHRI/SMT/KUM</span>	<span class="inline-block fs-20 underline"><?= $nm ?></span><br/>EMPLOYED IN MY EASTBLISHMENT AFTER HE/SHE HAS READ THE ENTRIES/ENTRIES HAVE BEEN READ OVER TO HIM/HER BY ME AND GOT CONFIRMED BY HIM/HER.</p>
        </div>
        <div class="flex justify-end fs-18" style="float:right"><br><br><br><br><br><div class="bold">SIGNATURE OF THE EMPLOYER OR OTHER<br><br>AUTHORISED OFFICERS OF THE ESTT.<br>DESINAGATION<br><br>NAME &amp; ADDRESS OF THE FACTORY /ESTT<br>OR RUBBER STAMP THEREOF.</div></div>
        <div class="bold fs-18">PLACE PANIPAT</div>
        <div class="flex fs-18"><div class="bold w-100">DATE : <?= $doj ?></div></div>
    </div>
</div>
<!-- FORM 2 REVISED END (COPY 2) -->

<!-- ===================== PAGE 10 — FORM F GRATUITY (COPY 1) ===================== -->
<div class="pb"></div>
<!-- FORM GRATUITY OFFICE -->
<div class="page pb-50 fs-20">
    <div class="form-gratuity">
        <div class="text-right"><span class="pr-20">Token No.:-</span> <?= $enrol ?></div>
        <table width="100%">
            <tr>
                <td class="form-type bdr-5" width="300px"><p class="text-center lh-20 mt-10"><span class="bold">Form F</span><br> [ See Sub-Rule (1) of Rule 7 ]</p></td>
                <td class="form-name" align="center"><div class="content"><div class="heading fs-25 bold"> Payment of Gratuity </div><div class="sub-heading"> [ See Sub-Rule (1) of Rule 6 ] </div></div></td>
            </tr>
        </table>
        <div class="text-center mt-10 fs-22"> <span class="bold">NOMINATION</span> </div>
        <div class="mt-20 fs-20"> To <span class="bold">&nbsp;<?= $contr ?: $co ?>, <?= $coAdd ?></span> </div>
        <div class="fs-20 mt-20"> (Give here name or description of the establishment with the full address.) </div>
        <table width="60%" class="mt-20">
            <tr class="fs-20">
                <td width="5%">1</td>
                <td>Shri/Smt./Kumari</td>
                <td width="50%"><div class="bdr-btm-2 text-center"><?= $nm ?></div><div class="fs-14">(Name in full here)</div></td>
            </tr>
        </table>
        <table class="mt-20 fs-20" width="100%">
            <tr><td></td><td width="97%"> Whose particulers are given in the statement below, hereby nominate the person(s) mentioned below to receive the gratuity payable after my death as also the gratuity standing to my credit in the event of my deathbefore that amount has become payable or having become payable has not been paid and direct that the said amount of gratuity shall be paid in proportion indicated against the name(s) of the nominee(s). </td></tr>
            <tr><td style="vertical-align:top" valign="top">2</td><td width="97%"> I hereby certify that the person(s) nominated is/are member(s) of my family within the meaning of clause(h) of section (2) of the Payment of Gratuity Act, 1972. </td></tr>
            <tr><td style="vertical-align:top" valign="top">3</td><td width="97%"> I hereby declare that I have no family within the meaning of clause(h) of section (2) of the said Act.<br> (a) My father/ mother/parent is/are not dependent on me.<br> (b) My husband's father/mother/parents is/are not depandent on my husband </td></tr>
            <tr style="vertical-align:top" valign="top"><td>4</td><td width="97%"> I have excluded my husband from my family by a notice dated the…………………………… to the controlling authority in terms of the provisio to caluse (h) of section 2 of the said Act. </td></tr>
            <tr style="vertical-align:top" valign="top"><td>5</td><td width="97%"> Nomination made herein invalidates my previous nomination. </td></tr>
        </table>
        <div class="mt-30 bold text-center fs-22"> NOMINEE(S) </div>
        <table class="bdr mt-20 fs-20" width="100%">
            <tr class="bdr-btm">
                <th class="bdr-rt" width="40%">Name in full with full address of nominee(s)</th>
                <th class="bdr-rt" width="20%">Relationship with the employee</th>
                <th class="bdr-rt" width="20%">Age of Nominee</th>
                <th width="20%">Proporation by which the gratuity will be shared</th>
            </tr>
            <tr class="bdr-btm">
                <td class="bdr-rt nominee-name" width="99.5%"><div class="nominee-sr">1.</div><div><p class="text-center"><?= $nom1 ?></p><p class="text-center fs-12"><?= $permAdd ?></p></div></td>
                <td class="bdr-rt text-center"><?= $nomRel1 ?></td>
                <td class="bdr-rt text-center"><?= $nomAge1 ?></td>
                <td class="bdr-rt text-center">100%</td>
            </tr>
            <tr class="bdr-btm">
                <td class="bdr-rt nominee-name" width="99.5%"><div class="nominee-sr">2.</div><div><p class="text-center"></p><p class="text-center fs-12"></p></div></td>
                <td class="bdr-rt">&nbsp;</td><td class="bdr-rt">&nbsp;</td><td>&nbsp;</td>
            </tr>
            <tr class="bdr-btm"><td class="bdr-rt nominee-name" width="99.5%"> as on </td><td class="bdr-rt">&nbsp;</td><td class="bdr-rt">&nbsp;</td><td>&nbsp;</td></tr>
        </table>
        <div class="text-center mt-30 fs-22"> Statement </div>
        <table width="100%" class="mt-20 fs-20 tstable tbl3">
            <tr><td width="20px">1.</td><td> Name of employee (in full)</td><td class="text-left" style="padding-left:10px;font-weight:bold"><?= $nm ?></td></tr>
            <tr><td width="20px">2.</td><td> Sex</td><td class="text-left" style="padding-left:10px;font-weight:bold"><?= $gender ?></td></tr>
            <tr><td width="20px">3.</td><td> Religion</td><td class="text-left" style="padding-left:10px;font-weight:bold"><?= $relig ?></td></tr>
            <tr><td width="20px">4.</td><td> Whether unmarried/ married/widow/widower</td><td class="text-left" style="padding-left:10px;font-weight:bold"><?= $ms ?></td></tr>
            <tr><td width="20px">5.</td><td> Department/Branch/Section where employed</td><td class="text-left" style="padding-left:10px;font-weight:bold"><?= $dept ?></td></tr>
            <tr><td width="20px">6.</td><td> Post held with Ticket of serial No. if any</td><td class="text-left" style="padding-left:10px;font-weight:bold"><?= $desig ?></td></tr>
            <tr><td width="20px">7.</td><td> Date of appointment</td><td class="text-left" style="padding-left:10px;font-weight:bold"><?= $doj ?></td></tr>
            <tr><td width="20px">8.</td><td> Permanent address</td><td class="text-left" style="padding-left:10px;font-weight:bold"><?= $permAdd ?></td></tr>
        </table>
        <table width="100%" class="mt-20 fs-20">
            <tr>
                <td width="20px"></td><td width="10%">Village</td><td width="24%" class="bdr-btm"></td>
                <td width="8%">Thana</td><td width="24%" class="bdr-btm"><?= $thana ?></td>
                <td width="20%">Sub Division</td><td width="24%" class="bdr-btm"></td>
            </tr>
            <tr>
                <td width="5%"></td><td width="10%">Post Office</td><td width="24%" class="bdr-btm"></td>
                <td width="8%">District</td><td width="24%" class="bdr-btm"><?= $dist ?></td>
                <td width="8%">State</td><td width="24%" class="bdr-btm"></td>
            </tr>
        </table>
        <br>
        <table width="90%" class="mt-20 fs-20 pb">
            <tr><td width="20%">Place</td><td width="60%">Panipat</td><td width="15%">Signature/Thumb of the employee</td></tr>
            <tr><td width="20%">Date</td><td width="60%"><?= $doj ?></td><td width="15%">&nbsp;</td></tr>
        </table>
        <div class="text-center bold mt-20 fs-20"> Declaration by witness </div>
        <div class="mt-20 fs-20"> Nomination signed/thumb impressed before me </div>
        <div class="ml-20 mt-20 flex pl-20 fs-20">
            <div> Name in full and full address of witness </div>
            <div class="pull-right" style="width: 250px;"> Signature of witness </div>
        </div>
        <table width="100%" class="bdr fs-20">
            <tr class="bdr-btm"><td width="5%" class="bdr-rt">1</td><td width="20%" class="bdr-rt"><?= $wit1 ?></td><td width="35%" class="bdr-rt"><?= $wit1Add ?></td><td width="5%" class="bdr-rt">1</td><td width="30%" class="bdr-rt">&nbsp;</td></tr>
            <tr class="bdr-btm"><td width="5%" class="bdr-rt">2</td><td width="20%" class="bdr-rt"><?= $wit2 ?></td><td width="35%" class="bdr-rt"><?= $wit2Add ?></td><td width="5%" class="bdr-rt">2</td><td width="30%" class="bdr-rt">&nbsp;</td></tr>
        </table>
        <table width="90%" class="mt-20 fs-20"><tr><td width="20%">Place</td><td width="60%">Panipat</td></tr><tr><td width="20%">Date</td><td width="60%"><?= $doj ?></td></tr></table>
        <div><p class="text-center bold fs-20"> Certificate by the employer </p><p class="fs-20"> Certified that the particulars of the above nomination have been verified and recorded in this eastblishment. </p></div>
        <br><br><br>
        <table width="100%"><tr class="fs-20"><td width="50%">Employer's Reference No. if any</td><td class="text-right"> Signature of the employer/ office authorised &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<br>Designation </td></tr></table>
        <div style="width: 200px; height: 20px; border-bottom: 1px dotted black;"></div>
        <div><p class="ml-auto fs-20" style="width: 270px;"> Name and address of the eastblishment or &nbsp;&nbsp;&nbsp;&nbsp; Rubber Stamp thereof</p></div>
        <table width="90%"><tr class="fs-20"><td width="20%">Date</td><td width="60%"><?= $doj ?></td></tr></table>
        <div class="text-center bold mt-20 fs-20"> Acknowledgement by the employee </div>
        <div class="text-center fs-20"> Received that duplicate copy of nomination Form 'F' filled by me and duly certified by the employer. </div>
        <br><br><br>
        <table width="100%" class="mt-30"><tr class="fs-20"><td width="22.1%">Date</td><td width="51%"><?= $doj ?></td><td width="25%">Signature of the employee</td></tr></table>
    </div>
</div>
<!-- FORM GRATUITY OFFICE END (COPY 1) -->

<!-- ===================== PAGE 10 — FORM F GRATUITY (COPY 2) ===================== -->
<div class="pb"></div>
<div class="page pb-50 fs-20">
    <div class="form-gratuity">
        <div class="text-right"><span class="pr-20">Token No.:-</span> <?= $enrol ?></div>
        <table width="100%">
            <tr>
                <td class="form-type bdr-5" width="300px"><p class="text-center lh-20 mt-10"><span class="bold">Form F</span><br> [ See Sub-Rule (1) of Rule 7 ]</p></td>
                <td class="form-name" align="center"><div class="content"><div class="heading fs-25 bold"> Payment of Gratuity </div><div class="sub-heading"> [ See Sub-Rule (1) of Rule 6 ] </div></div></td>
            </tr>
        </table>
        <div class="text-center mt-10 fs-22"> <span class="bold">NOMINATION</span> </div>
        <div class="mt-20 fs-20"> To <span class="bold">&nbsp;<?= $contr ?: $co ?>, <?= $coAdd ?></span> </div>
        <div class="fs-20 mt-20"> (Give here name or description of the establishment with the full address.) </div>
        <table width="60%" class="mt-20">
            <tr class="fs-20"><td width="5%">1</td><td>Shri/Smt./Kumari</td><td width="50%"><div class="bdr-btm-2 text-center"><?= $nm ?></div><div class="fs-14">(Name in full here)</div></td></tr>
        </table>
        <table class="mt-20 fs-20" width="100%">
            <tr><td></td><td width="97%"> Whose particulers are given in the statement below, hereby nominate the person(s) mentioned below to receive the gratuity payable after my death as also the gratuity standing to my credit in the event of my deathbefore that amount has become payable or having become payable has not been paid and direct that the said amount of gratuity shall be paid in proportion indicated against the name(s) of the nominee(s). </td></tr>
            <tr><td style="vertical-align:top" valign="top">2</td><td width="97%"> I hereby certify that the person(s) nominated is/are member(s) of my family within the meaning of clause(h) of section (2) of the Payment of Gratuity Act, 1972. </td></tr>
            <tr><td style="vertical-align:top" valign="top">3</td><td width="97%"> I hereby declare that I have no family within the meaning of clause(h) of section (2) of the said Act.<br>(a) My father/ mother/parent is/are not dependent on me.<br>(b) My husband's father/mother/parents is/are not depandent on my husband </td></tr>
            <tr style="vertical-align:top" valign="top"><td>4</td><td width="97%"> I have excluded my husband from my family by a notice dated the…………………………… to the controlling authority in terms of the provisio to caluse (h) of section 2 of the said Act. </td></tr>
            <tr style="vertical-align:top" valign="top"><td>5</td><td width="97%"> Nomination made herein invalidates my previous nomination. </td></tr>
        </table>
        <div class="mt-30 bold text-center fs-22"> NOMINEE(S) </div>
        <table class="bdr mt-20 fs-20" width="100%">
            <tr class="bdr-btm"><th class="bdr-rt" width="40%">Name in full with full address of nominee(s)</th><th class="bdr-rt" width="20%">Relationship with the employee</th><th class="bdr-rt" width="20%">Age of Nominee</th><th width="20%">Proporation by which the gratuity will be shared</th></tr>
            <tr class="bdr-btm"><td class="bdr-rt nominee-name" width="99.5%"><div class="nominee-sr">1.</div><div><p class="text-center"><?= $nom1 ?></p><p class="text-center fs-12"><?= $permAdd ?></p></div></td><td class="bdr-rt text-center"><?= $nomRel1 ?></td><td class="bdr-rt text-center"><?= $nomAge1 ?></td><td class="bdr-rt text-center">100%</td></tr>
            <tr class="bdr-btm"><td class="bdr-rt nominee-name" width="99.5%"><div class="nominee-sr">2.</div><div><p class="text-center"></p><p class="text-center fs-12"></p></div></td><td class="bdr-rt">&nbsp;</td><td class="bdr-rt">&nbsp;</td><td>&nbsp;</td></tr>
            <tr class="bdr-btm"><td class="bdr-rt nominee-name" width="99.5%"> as on </td><td class="bdr-rt">&nbsp;</td><td class="bdr-rt">&nbsp;</td><td>&nbsp;</td></tr>
        </table>
        <div class="text-center mt-30 fs-22"> Statement </div>
        <table width="100%" class="mt-20 fs-20 tstable tbl3">
            <tr><td width="20px">1.</td><td> Name of employee (in full)</td><td class="text-left" style="padding-left:10px;font-weight:bold"><?= $nm ?></td></tr>
            <tr><td width="20px">2.</td><td> Sex</td><td class="text-left" style="padding-left:10px;font-weight:bold"><?= $gender ?></td></tr>
            <tr><td width="20px">3.</td><td> Religion</td><td class="text-left" style="padding-left:10px;font-weight:bold"><?= $relig ?></td></tr>
            <tr><td width="20px">4.</td><td> Whether unmarried/ married/widow/widower</td><td class="text-left" style="padding-left:10px;font-weight:bold"><?= $ms ?></td></tr>
            <tr><td width="20px">5.</td><td> Department/Branch/Section where employed</td><td class="text-left" style="padding-left:10px;font-weight:bold"><?= $dept ?></td></tr>
            <tr><td width="20px">6.</td><td> Post held with Ticket of serial No. if any</td><td class="text-left" style="padding-left:10px;font-weight:bold"><?= $desig ?></td></tr>
            <tr><td width="20px">7.</td><td> Date of appointment</td><td class="text-left" style="padding-left:10px;font-weight:bold"><?= $doj ?></td></tr>
            <tr><td width="20px">8.</td><td> Permanent address</td><td class="text-left" style="padding-left:10px;font-weight:bold"><?= $permAdd ?></td></tr>
        </table>
        <table width="100%" class="mt-20 fs-20">
            <tr><td width="20px"></td><td width="10%">Village</td><td width="24%" class="bdr-btm"></td><td width="8%">Thana</td><td width="24%" class="bdr-btm"><?= $thana ?></td><td width="20%">Sub Division</td><td width="24%" class="bdr-btm"></td></tr>
            <tr><td width="5%"></td><td width="10%">Post Office</td><td width="24%" class="bdr-btm"></td><td width="8%">District</td><td width="24%" class="bdr-btm"><?= $dist ?></td><td width="8%">State</td><td width="24%" class="bdr-btm"></td></tr>
        </table>
        <br>
        <table width="90%" class="mt-20 fs-20 pb">
            <tr><td width="20%">Place</td><td width="60%">Panipat</td><td width="15%">Signature/Thumb of the employee</td></tr>
            <tr><td width="20%">Date</td><td width="60%"><?= $doj ?></td><td width="15%">&nbsp;</td></tr>
        </table>
        <div class="text-center bold mt-20 fs-20"> Declaration by witness </div>
        <div class="mt-20 fs-20"> Nomination signed/thumb impressed before me </div>
        <div class="ml-20 mt-20 flex pl-20 fs-20"><div> Name in full and full address of witness </div><div class="pull-right" style="width: 250px;"> Signature of witness </div></div>
        <table width="100%" class="bdr fs-20">
            <tr class="bdr-btm"><td width="5%" class="bdr-rt">1</td><td width="20%" class="bdr-rt"><?= $wit1 ?></td><td width="35%" class="bdr-rt"><?= $wit1Add ?></td><td width="5%" class="bdr-rt">1</td><td width="30%" class="bdr-rt">&nbsp;</td></tr>
            <tr class="bdr-btm"><td width="5%" class="bdr-rt">2</td><td width="20%" class="bdr-rt"><?= $wit2 ?></td><td width="35%" class="bdr-rt"><?= $wit2Add ?></td><td width="5%" class="bdr-rt">2</td><td width="30%" class="bdr-rt">&nbsp;</td></tr>
        </table>
        <table width="90%" class="mt-20 fs-20"><tr><td width="20%">Place</td><td width="60%">Panipat</td></tr><tr><td width="20%">Date</td><td width="60%"><?= $doj ?></td></tr></table>
        <div><p class="text-center bold fs-20"> Certificate by the employer </p><p class="fs-20"> Certified that the particulars of the above nomination have been verified and recorded in this eastblishment. </p></div>
        <br><br><br>
        <table width="100%"><tr class="fs-20"><td width="50%">Employer's Reference No. if any</td><td class="text-right"> Signature of the employer/ office authorised &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<br>Designation </td></tr></table>
        <div style="width: 200px; height: 20px; border-bottom: 1px dotted black;"></div>
        <div><p class="ml-auto fs-20" style="width: 270px;"> Name and address of the eastblishment or &nbsp;&nbsp;&nbsp;&nbsp; Rubber Stamp thereof</p></div>
        <table width="90%"><tr class="fs-20"><td width="20%">Date</td><td width="60%"><?= $doj ?></td></tr></table>
        <div class="text-center bold mt-20 fs-20"> Acknowledgement by the employee </div>
        <div class="text-center fs-20"> Received that duplicate copy of nomination Form 'F' filled by me and duly certified by the employer. </div>
        <br><br><br>
        <table width="100%" class="mt-30"><tr class="fs-20"><td width="22.1%">Date</td><td width="51%"><?= $doj ?></td><td width="25%">Signature of the employee</td></tr></table>
    </div>
</div>
<!-- FORM GRATUITY OFFICE END (COPY 2) -->

<!-- ===================== PAGE 11 — EMPLOYEE HANDBOOK HINDI (COPY 1) ===================== -->
<div class="pb"></div>
<!-- FORM HANDBOOK OFFICE-->
<div class="text-right"><span class="pr-20">Token No.:-</span> <?= $enrol ?></div>
<div class="bdr" style="padding:20px;">
    <strong>
        <div class="text-center fs-40 ws-15" style="line-height:48px;"><?= $co ?></div>
        <div class="text-center fs-24"><?= $coAdd ?></div>
    </strong>
</div>
<div class="lh-30 pt-10 fs-20">
    <div class="bdr ml-auto fs-20">
        <strong>
            <div class="fs-22 text-center mt-0" style="line-height:40px">
                <u> कर्मचारियों की हस्त पुस्तिका व प्रसुविधा निधि </u>
           <br>
                <u>भर्ती प्रक्रिया व कार्य सम्बन्धित जानकारी (RECRUITMENT POLICY)</u>
                <br>
            </div>
        </strong>
        <table width="100%" class="lh-25 tbl2">
            <tr><td width="3%" style="vertical-align:top" class="v-top text-center">1.</td><td><?= $co ?> में किसी भी वर्ग की नियुक्ति  होने के सम्बन्ध में प्रत्येक व्यक्ति को निम्न अवस्थाओं को पूरा करना अनिवार्य है, अन्यथा वह किसी भी दशा में संस्था में नियुक्त नहीं समझा जाएगा ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">2.</td><td>उक्त व्यक्ति एक स्वस्थ मस्तिष्क का व्यक्ति होना चाहिए ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">3.</td><td>उक्त व्यक्ति को कार्य समझने की क्षमता होनी चाहिए ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">4.</td><td>उक्त व्यक्ति को कम से कम एक संदर्भित नाम काम का उल्लेख अवश्य करना होगा ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">5.</td><td>आयु सम्बन्धी प्रमाण पत्र लाना उक्त व्यक्ति की जिम्मेवारी होगी ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">6.</td><td>उक्त व्यक्ति को संस्था में आने के समय स्वयं हाजरी लगानी होगी जो कि कार्ड को मशीन पर रखकर/हस्ताक्षर करके लगानी होगी</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">7.</td><td>संस्था में वेतन भुगतान माह समाप्त होने के 7 तारीख तक देय होगा उससे पूर्व किसी प्रकार का कोई अग्रिम संस्था में देय नहीं है ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">8.</td><td>किसी भी आपराधिक प्रवृति के व्यक्ति को संस्था कर्मचारी के रूप में स्वीकार नहीं करेगी ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">9.</td><td>संस्था में भर्ती के समय आवेदक को लिंग, जाति, धर्म आदि के आधार पर प्रवेेश नहीं मिलेगा।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">10.</td><td>संस्था में कार्य करने के लिए कर्मचारी को सदैव अपने सुरक्षा उपकरणों का प्रयोग करना होगा।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">11.</td><td>संस्था में प्रवेेश के लिए प्रत्येक व्यक्ति को अपने वेतन में से सरकार द्वारा निर्धारित की गई कटौतियां कटवानी होगी और यह अनिवार्य है ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">12.</td><td>संस्था में नये कर्मचारी की भर्ती के समय आवेदक की व्यक्तिगत परिचय आदि को भर्ती होने के लिए वैद्य नहीं माना जाएगा तथा ऐसा व्यक्तिगत प्रभावीकरण भर्ती के लिए अमान्य होगा।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">13.</td><td>सभी कर्मचारियों को भविष्य निधि का सदस्य बनाया जायेगा, यदि वह 15000/- रू0 प्रति माह तक अर्जित कर रहे है। कर्मचारियों के वेतन मे से 12 प्रतिशत की कटौती अंशदान के रूप मे की जाएगी और इस 12 प्रतिशत की दर से ही अंशदान कम्पनी द्वारा कर्मचारियों के भविष्य निधि खाते मे जमा करवाया जायेगा और अन्य सुविधाऐ जैसा कि पेंशन, कर्ज इत्यादि भी कर्मचारी भविष्य निधि संघ से आवेदन देकर प्राप्त कर सकता है। कर्मचारी 15.67 प्रतिशत अंशदान उसके खाते नं0 1 में जमा होगा और 8.33 प्रतिशत खाता नं0 10 में दर्ज पैन्शन स्वरूप लिया जाएगा, खाता नं0 10 में दर्ज जमा राशि की एवज मे उसे भविष्य निधि संघ द्वारा पैन्शन मिलने का भी प्रावधान है।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">14.</td><td>जो कर्मचारी प्रतिमाह 21000/- रू0 की दर तक  जो वेतन ग्रहण करेगें उन्ही कर्मचारियों को ई0 एस0 आई0 निगम का सदस्य बनाया जायेगा। प्रत्येक कर्मचारी का राज्य कर्मचारी बीमा अधिनियम के अंतर्गत 0.75% अंशदान इस खाते में देय होगा, जिसमें संस्था द्वारा 3.25% का अंशदान देय होगा । वह कर्मचारी राज्य बीमा अधिनियम के तहत देय सहुलियते जैसा की बिमारी के संदर्भ में चोट लगने पर अथवा कार्य के दौरान हुई दुर्घटना या मृत्यु पर परिजनो को पैन्शन का प्रावधान है ताकि उनकी आजिविका चल सके। महिला कर्मचारियों को प्रसुति के दौरान सवेतन अवकाश का प्रावधान है। बीमाकृत व्यक्ति व उसके आश्रित परिजनों का मुफ्त ईलाज व दवाईया देता है।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">15.</td><td>यदि कर्मचारी 30 दिन तक कार्य करता है और वह कर्मचारी बोनस अधिनियम के अनुसार उसे वित वर्ष समाप्ति के 8 माह के भीतर कम से कम 8.33% बोनस वितरण किया जाएगा।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">16.</td><td>कर्मचारी को प्रतिवर्ष 7 आकस्मिक छुट्टियां, 3 दिन राष्ट्रीय अवकाश व 6 त्यौहारिक अवकाश प्रति कैलेंडर वर्ष दिया जायेगा। राष्ट्रीय अवकाश 26 जनवरी, 15 अगस्त, 2 अक्टूबर घोषित है, तटस्थ रहेगे। अवकाशो की सुची नोटिस बोर्ड पर चस्पा दी जाएगी। आकस्मिक की छुट्टी कर्मचारी आवेदन पत्र देकर ग्रहण कर सकता है। आकस्मिक छुट्टी ग्रहण करने के लिए कर्मचारी प्रत्येक तिमाही में दो दिन की आकस्मिक छुट्टी लेगा।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">17.</td><td>जो कर्मचारी 20 दिन कार्य करेगा उसकी एवज में उसे एक अर्जित छुट्टी देय होगी। जो कि उसकी अर्जित छुट्टियों के रजिस्टर में दर्ज कर दी जाएगी। 240 दिन कार्य के पश्चात् कर्मचारी अर्जित छुट्टी ग्रहण करने के लिए आवेदन पत्र दे सकता है । जो कैलेंडर वर्ष  के हिसाब से चलेगी। जो छुट्टिया बच जाएगी वह कानुन के हिसाब से कर्मचारी के अगले वर्ष में जमा कर दी जाएगी या स्थायी आदेश के आधार पर भुगतान होगा।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">18.</td><td>संस्था में कर्मचारी के हितों को ध्यान में रखते हुए अनेक समितियों का गठन किया गया है ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">19.</td><td>प्रत्येक कर्मचारी का  श्रम कल्याण कोष कम से कम 0.2% से लेकर अधिकतम 35रू तक प्रतिमाह काटा जाएगा और नियोक्ता द्वारा उसी अनुरूप दोगुना जमा प्रतिमाह की दर से कम्पनी हरियाणा सरकार के श्रम कल्याण कोष के खाते में जमा करवायेगी एवज में हरियाणा श्रम विभाग उनको समय-समय पर निर्धारित स्कीमो के तहत लाभांश देगी।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">20.</td><td>संस्था में कर्मचारी को वेतन तथा मजदूरी प्रदान करने के लिए अगले माह की 7 तारीख तक का समय निश्चित किया गया है तथा वेतन पर्ची वेतन व मजदूरी मिलने से कम से कम एक दिन पहले प्रदान की जाती है।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">21.</td><td>कम्पनी किसी कर्मचारी मे किसी भी प्रकार के भेदभाव में विश्वास नही रखती व सभी को सम्पूर्ण अवसर देते हुए सभी प्रकार की निर्धारित सुविधाऐ देने के लिए वचनबद्ध है।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">22.</td><td>हमारी संस्था मे कोई भी बंधुआ मजदूर कार्य नही करते और न ही  किसी भी बाल श्रमिक से कार्य लिया जाता है।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">23.</td><td>आपको ग्रेज्युटी अधिनियम के अन्तर्गत नियमानुसार ग्रेज्युटी का भुगतान किया जायेगा, उन्हे प्रतिवर्ष 15 दिन की दर से आवेदन पत्र देने के पश्चात ग्रेज्युटी दी जाएगी। इस की गणना एक माह में 26 दिन के आधार पर की जायेगी।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">24.</td><td>जो कर्मचारी सप्ताह में 48 घण्टे से अधिक कार्य करेगा तो उसे उस अधिक अवधि के कार्य की ऐवज में सामान्य वेतन से दुगने की दर से भुगतान किया जाएगा।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">25.</td><td>प्रत्येक कर्मचारी के लिए यह नितांत आवश्यक हो जाता है कि वह संस्था में प्रवेश के समय अपनी उपस्थिति स्वयं मशीन पर अपना/अपनी कार्ड रख कर दर्ज करें तथा संस्था में प्रवेश करते समय अपना परिचय पत्र अवश्य साथ लेकर आये, और किसी भी अधिकारी के मांगने पर परिचय पत्र दिखाना होगा ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">26.</td><td class="pl-20">हमारी संस्था मे 18 वर्ष से कम आयु के श्रमिक से कार्य नही लिया जाता है। किसी भी नये श्रमिक अथवा कर्मचारी को कार्य पर आने से पहले अपना आयु संबन्धित प्रमाण पत्र जमा कराने के बाद कार्य शुरू करने की अनुमति दी जाती है।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">27.</td><td class="pl-20">संस्था में किसी भी कर्मचारी के साथ जाति, धर्म, लिंग आदि के आधार पर भेद भाव नहीं किया जाता। यदि किसी कर्मचारी को इससे सम्बन्धित कोई शिकायत उत्पन्न होती है तो वह संस्था के मानव संसाधन विभाग में शिकायत कर सकता है ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">28.</td><td class="pl-20">आप मातृत्व लाभांश अधिनियम 1961 या समय - समय पर इस संशोधित अधिनियम के अंतर्गत होने वाले परिवर्तित लाभांशों के लिए अधिकृत होंगे।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">29.</td><td class="pl-20">संस्था मे सभी कर्मचारियो के हितार्थ विभिन्न ट्रेनिंग एवं समितियों का आयोजन किया जाता है सभी कर्मचारियो से अपेक्षा की जाती है वो अपनी रुचि के अनुसार समितियों के सदस्य बनने के लिए मानव संसाधन विभाग से सम्पर्क कर सकते हैं।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">30.</td><td class="pl-20">संस्था मे किसी भी कर्मचारी से नौकरी के बाबत किसी भी तरह की सुरक्षा राशि / पदोन्नति शुल्क / किसी दस्तावेज की मूल प्रति मानव संसाधन विभाग या किसी भी अन्य विभाग में जमा नहीं की जाएगी।</td></tr>
        </table>
        <table width="100%" class="tbl2">
            <tr><td colspan="2">सी-टीपैट (C-TPAT) के सम्बन्ध में जानकारी</td></tr>
            <tr><td colspan="2">सी ( कस्टम ) - सीमान्त</td></tr>
            <tr><td colspan="2">टी ( ट्रैंड ) - व्यापार</td></tr>
            <tr><td>पी ( पार्टनर ) - साझेदारी</td><td>अर्थात आतंकवाद के विरूद्ध सीमान्त व्यापार साझेदारी</td></tr>
            <tr><td colspan="2">ए ( अगेन्स्ट ) - विरूद्ध</td></tr>
            <tr><td colspan="2">टी ( टेररिज्म ) - आतंकवाद</td></tr>
            <tr><td colspan="2"><table width="100%">
                <tr><td style="vertical-align:top" valign="top">1.</td><td class="pl-20">नौकरी पर आने के समय पर आप अपने पहचान पत्र अवश्य पहनकर रखें।</td></tr>
                <tr><td style="vertical-align:top" valign="top">2.</td><td class="pl-20">खाने का टिफिन लंच रूम में अवश्य रखें न कि कार्य स्थल पर।</td></tr>
                <tr><td style="vertical-align:top" valign="top">3.</td><td class="pl-20">फैक्ट्री परिसर में अपनी निजी वस्तु ले जाना निषेध है।</td></tr>
                <tr><td style="vertical-align:top" valign="top">4.</td><td class="pl-20">किसी भी घोषित प्रतिबंधित क्षेत्र में जाना निषेध है।</td></tr>
                <tr><td style="vertical-align:top" valign="top">5.</td><td class="pl-20">अनाधिकृत व्यक्ति या वस्तु जैसे तेजधार वाला हथियार/विस्फोटक/बम्ब की धमकी की जानकारी/तस्करी के सम्बन्ध में/आन्तरिक गोपनीय साजिश करने वाले सम्बन्ध में जानकारी देने वाले की सूचना गुप्त रखी जाएगी तथा उसे उचित पारितोषिक भी दिया जाएगा और इस साजिश से सजग रहने हेतू प्रशिक्षण दिया जाएगा।</td></tr>
            </table></td></tr>
        </table>
        <div class="fs-20">
            <div class="text-center text-underline fs-22">निलम्बन प्रक्रिया (TERMINATION POLICY)</div>
            <div class="text-center">संस्था में कार्यरत किसी कर्मचारी के दुराचार अनैतिक कार्यो एवं संदिग्ध गतिविधि में संलिप्त होने पर प्रक्रिया संस्था के स्थाई आदेशों में वर्णित प्रक्रिया के अनुसार ही होगी स्थाई आदेशों में वर्णित निर्देश के अधीन <span class="underline">निलम्बन प्रक्रिया</span> की जा सकती है।</div>
            <div class="flex mt-10 h-50">
                <div class="">प्रतिलिपि प्राप्त की<br><br><br><br></div>
                <div class="pull-right">कृते <?= $co ?><br></div>
            </div>
            <div class="flex mt-10 h-50">
                <div class="">कर्मचारी के हस्ताक्षर<br><br><br><br></div>
                <div class="pull-right">हस्ताक्षर अधिकृत अधिकारी<br></div>
            </div>
            तिथि:- <?= $doj ?>
        </div>
    </div>
</div>
<!-- FORM HANDBOOK OFFICE END (COPY 1) -->

<!-- ===================== PAGE 11 — EMPLOYEE HANDBOOK HINDI (COPY 2) ===================== -->
<div class="pb"></div>
<div class="text-right"><span class="pr-20">Token No.:-</span> <?= $enrol ?></div>
<div class="bdr" style="padding:20px;">
    <strong>
        <div class="text-center fs-40 ws-15" style="line-height:48px;"><?= $co ?></div>
        <div class="text-center fs-24"><?= $coAdd ?></div>
    </strong>
</div>
<div class="lh-30 pt-10 fs-20">
    <div class="bdr ml-auto fs-20">
        <strong>
            <div class="fs-22 text-center mt-0" style="line-height:40px">
                <u> कर्मचारियों की हस्त पुस्तिका व प्रसुविधा निधि </u>
           <br>
                <u>भर्ती प्रक्रिया व कार्य सम्बन्धित जानकारी (RECRUITMENT POLICY)</u>
                <br>
            </div>
        </strong>
        <table width="100%" class="lh-25 tbl2">
            <tr><td width="3%" style="vertical-align:top" class="v-top text-center">1.</td><td><?= $co ?> में किसी भी वर्ग की नियुक्ति  होने के सम्बन्ध में प्रत्येक व्यक्ति को निम्न अवस्थाओं को पूरा करना अनिवार्य है, अन्यथा वह किसी भी दशा में संस्था में नियुक्त नहीं समझा जाएगा ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">2.</td><td>उक्त व्यक्ति एक स्वस्थ मस्तिष्क का व्यक्ति होना चाहिए ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">3.</td><td>उक्त व्यक्ति को कार्य समझने की क्षमता होनी चाहिए ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">4.</td><td>उक्त व्यक्ति को कम से कम एक संदर्भित नाम काम का उल्लेख अवश्य करना होगा ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">5.</td><td>आयु सम्बन्धी प्रमाण पत्र लाना उक्त व्यक्ति की जिम्मेवारी होगी ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">6.</td><td>उक्त व्यक्ति को संस्था में आने के समय स्वयं हाजरी लगानी होगी जो कि कार्ड को मशीन पर रखकर/हस्ताक्षर करके लगानी होगी</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">7.</td><td>संस्था में वेतन भुगतान माह समाप्त होने के 7 तारीख तक देय होगा उससे पूर्व किसी प्रकार का कोई अग्रिम संस्था में देय नहीं है ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">8.</td><td>किसी भी आपराधिक प्रवृति के व्यक्ति को संस्था कर्मचारी के रूप में स्वीकार नहीं करेगी ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">9.</td><td>संस्था में भर्ती के समय आवेदक को लिंग, जाति, धर्म आदि के आधार पर प्रवेेश नहीं मिलेगा।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">10.</td><td>संस्था में कार्य करने के लिए कर्मचारी को सदैव अपने सुरक्षा उपकरणों का प्रयोग करना होगा।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">11.</td><td>संस्था में प्रवेेश के लिए प्रत्येक व्यक्ति को अपने वेतन में से सरकार द्वारा निर्धारित की गई कटौतियां कटवानी होगी और यह अनिवार्य है ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">12.</td><td>संस्था में नये कर्मचारी की भर्ती के समय आवेदक की व्यक्तिगत परिचय आदि को भर्ती होने के लिए वैद्य नहीं माना जाएगा तथा ऐसा व्यक्तिगत प्रभावीकरण भर्ती के लिए अमान्य होगा।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">13.</td><td>सभी कर्मचारियों को भविष्य निधि का सदस्य बनाया जायेगा, यदि वह 15000/- रू0 प्रति माह तक अर्जित कर रहे है। कर्मचारियों के वेतन मे से 12 प्रतिशत की कटौती अंशदान के रूप मे की जाएगी और इस 12 प्रतिशत की दर से ही अंशदान कम्पनी द्वारा कर्मचारियों के भविष्य निधि खाते मे जमा करवाया जायेगा और अन्य सुविधाऐ जैसा कि पेंशन, कर्ज इत्यादि भी कर्मचारी भविष्य निधि संघ से आवेदन देकर प्राप्त कर सकता है। कर्मचारी 15.67 प्रतिशत अंशदान उसके खाते नं0 1 में जमा होगा और 8.33 प्रतिशत खाता नं0 10 में दर्ज पैन्शन स्वरूप लिया जाएगा, खाता नं0 10 में दर्ज जमा राशि की एवज मे उसे भविष्य निधि संघ द्वारा पैन्शन मिलने का भी प्रावधान है।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">14.</td><td>जो कर्मचारी प्रतिमाह 21000/- रू0 की दर तक  जो वेतन ग्रहण करेगें उन्ही कर्मचारियों को ई0 एस0 आई0 निगम का सदस्य बनाया जायेगा। प्रत्येक कर्मचारी का राज्य कर्मचारी बीमा अधिनियम के अंतर्गत 0.75% अंशदान इस खाते में देय होगा, जिसमें संस्था द्वारा 3.25% का अंशदान देय होगा । वह कर्मचारी राज्य बीमा अधिनियम के तहत देय सहुलियते जैसा की बिमारी के संदर्भ में चोट लगने पर अथवा कार्य के दौरान हुई दुर्घटना या मृत्यु पर परिजनो को पैन्शन का प्रावधान है ताकि उनकी आजिविका चल सके। महिला कर्मचारियों को प्रसुति के दौरान सवेतन अवकाश का प्रावधान है। बीमाकृत व्यक्ति व उसके आश्रित परिजनों का मुफ्त ईलाज व दवाईया देता है।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">15.</td><td>यदि कर्मचारी 30 दिन तक कार्य करता है और वह कर्मचारी बोनस अधिनियम के अनुसार उसे वित वर्ष समाप्ति के 8 माह के भीतर कम से कम 8.33% बोनस वितरण किया जाएगा।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">16.</td><td>कर्मचारी को प्रतिवर्ष 7 आकस्मिक छुट्टियां, 3 दिन राष्ट्रीय अवकाश व 6 त्यौहारिक अवकाश प्रति कैलेंडर वर्ष दिया जायेगा। राष्ट्रीय अवकाश 26 जनवरी, 15 अगस्त, 2 अक्टूबर घोषित है, तटस्थ रहेगे। अवकाशो की सुची नोटिस बोर्ड पर चस्पा दी जाएगी। आकस्मिक की छुट्टी कर्मचारी आवेदन पत्र देकर ग्रहण कर सकता है। आकस्मिक छुट्टी ग्रहण करने के लिए कर्मचारी प्रत्येक तिमाही में दो दिन की आकस्मिक छुट्टी लेगा।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">17.</td><td>जो कर्मचारी 20 दिन कार्य करेगा उसकी एवज में उसे एक अर्जित छुट्टी देय होगी। जो कि उसकी अर्जित छुट्टियों के रजिस्टर में दर्ज कर दी जाएगी। 240 दिन कार्य के पश्चात् कर्मचारी अर्जित छुट्टी ग्रहण करने के लिए आवेदन पत्र दे सकता है । जो कैलेंडर वर्ष  के हिसाब से चलेगी। जो छुट्टिया बच जाएगी वह कानुन के हिसाब से कर्मचारी के अगले वर्ष में जमा कर दी जाएगी या स्थायी आदेश के आधार पर भुगतान होगा।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">18.</td><td>संस्था में कर्मचारी के हितों को ध्यान में रखते हुए अनेक समितियों का गठन किया गया है ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">19.</td><td>प्रत्येक कर्मचारी का  श्रम कल्याण कोष कम से कम 0.2% से लेकर अधिकतम 35रू तक प्रतिमाह काटा जाएगा और नियोक्ता द्वारा उसी अनुरूप दोगुना जमा प्रतिमाह की दर से कम्पनी हरियाणा सरकार के श्रम कल्याण कोष के खाते में जमा करवायेगी एवज में हरियाणा श्रम विभाग उनको समय-समय पर निर्धारित स्कीमो के तहत लाभांश देगी।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">20.</td><td>संस्था में कर्मचारी को वेतन तथा मजदूरी प्रदान करने के लिए अगले माह की 7 तारीख तक का समय निश्चित किया गया है तथा वेतन पर्ची वेतन व मजदूरी मिलने से कम से कम एक दिन पहले प्रदान की जाती है।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">21.</td><td>कम्पनी किसी कर्मचारी मे किसी भी प्रकार के भेदभाव में विश्वास नही रखती व सभी को सम्पूर्ण अवसर देते हुए सभी प्रकार की निर्धारित सुविधाऐ देने के लिए वचनबद्ध है।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">22.</td><td>हमारी संस्था मे कोई भी बंधुआ मजदूर कार्य नही करते और न ही  किसी भी बाल श्रमिक से कार्य लिया जाता है।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">23.</td><td>आपको ग्रेज्युटी अधिनियम के अन्तर्गत नियमानुसार ग्रेज्युटी का भुगतान किया जायेगा, उन्हे प्रतिवर्ष 15 दिन की दर से आवेदन पत्र देने के पश्चात ग्रेज्युटी दी जाएगी। इस की गणना एक माह में 26 दिन के आधार पर की जायेगी।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">24.</td><td>जो कर्मचारी सप्ताह में 48 घण्टे से अधिक कार्य करेगा तो उसे उस अधिक अवधि के कार्य की ऐवज में सामान्य वेतन से दुगने की दर से भुगतान किया जाएगा।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">25.</td><td>प्रत्येक कर्मचारी के लिए यह नितांत आवश्यक हो जाता है कि वह संस्था में प्रवेश के समय अपनी उपस्थिति स्वयं मशीन पर अपना/अपनी कार्ड रख कर दर्ज करें तथा संस्था में प्रवेश करते समय अपना परिचय पत्र अवश्य साथ लेकर आये, और किसी भी अधिकारी के मांगने पर परिचय पत्र दिखाना होगा ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">26.</td><td class="pl-20">हमारी संस्था मे 18 वर्ष से कम आयु के श्रमिक से कार्य नही लिया जाता है। किसी भी नये श्रमिक अथवा कर्मचारी को कार्य पर आने से पहले अपना आयु संबन्धित प्रमाण पत्र जमा कराने के बाद कार्य शुरू करने की अनुमति दी जाती है।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">27.</td><td class="pl-20">संस्था में किसी भी कर्मचारी के साथ जाति, धर्म, लिंग आदि के आधार पर भेद भाव नहीं किया जाता। यदि किसी कर्मचारी को इससे सम्बन्धित कोई शिकायत उत्पन्न होती है तो वह संस्था के मानव संसाधन विभाग में शिकायत कर सकता है ।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">28.</td><td class="pl-20">आप मातृत्व लाभांश अधिनियम 1961 या समय - समय पर इस संशोधित अधिनियम के अंतर्गत होने वाले परिवर्तित लाभांशों के लिए अधिकृत होंगे।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">29.</td><td class="pl-20">संस्था मे सभी कर्मचारियो के हितार्थ विभिन्न ट्रेनिंग एवं समितियों का आयोजन किया जाता है सभी कर्मचारियो से अपेक्षा की जाती है वो अपनी रुचि के अनुसार समितियों के सदस्य बनने के लिए मानव संसाधन विभाग से सम्पर्क कर सकते हैं।</td></tr>
            <tr><td style="vertical-align:top" class="v-top text-center">30.</td><td class="pl-20">संस्था मे किसी भी कर्मचारी से नौकरी के बाबत किसी भी तरह की सुरक्षा राशि / पदोन्नति शुल्क / किसी दस्तावेज की मूल प्रति मानव संसाधन विभाग या किसी भी अन्य विभाग में जमा नहीं की जाएगी।</td></tr>
        </table>
        <table width="100%" class="tbl2">
            <tr><td colspan="2">सी-टीपैट (C-TPAT) के सम्बन्ध में जानकारी</td></tr>
            <tr><td colspan="2">सी ( कस्टम ) - सीमान्त</td></tr>
            <tr><td colspan="2">टी ( ट्रैंड ) - व्यापार</td></tr>
            <tr><td>पी ( पार्टनर ) - साझेदारी</td><td>अर्थात आतंकवाद के विरूद्ध सीमान्त व्यापार साझेदारी</td></tr>
            <tr><td colspan="2">ए ( अगेन्स्ट ) - विरूद्ध</td></tr>
            <tr><td colspan="2">टी ( टेररिज्म ) - आतंकवाद</td></tr>
            <tr><td colspan="2"><table width="100%">
                <tr><td style="vertical-align:top" valign="top">1.</td><td class="pl-20">नौकरी पर आने के समय पर आप अपने पहचान पत्र अवश्य पहनकर रखें।</td></tr>
                <tr><td style="vertical-align:top" valign="top">2.</td><td class="pl-20">खाने का टिफिन लंच रूम में अवश्य रखें न कि कार्य स्थल पर।</td></tr>
                <tr><td style="vertical-align:top" valign="top">3.</td><td class="pl-20">फैक्ट्री परिसर में अपनी निजी वस्तु ले जाना निषेध है।</td></tr>
                <tr><td style="vertical-align:top" valign="top">4.</td><td class="pl-20">किसी भी घोषित प्रतिबंधित क्षेत्र में जाना निषेध है।</td></tr>
                <tr><td style="vertical-align:top" valign="top">5.</td><td class="pl-20">अनाधिकृत व्यक्ति या वस्तु जैसे तेजधार वाला हथियार/विस्फोटक/बम्ब की धमकी की जानकारी/तस्करी के सम्बन्ध में/आन्तरिक गोपनीय साजिश करने वाले सम्बन्ध में जानकारी देने वाले की सूचना गुप्त रखी जाएगी तथा उसे उचित पारितोषिक भी दिया जाएगा और इस साजिश से सजग रहने हेतू प्रशिक्षण दिया जाएगा।</td></tr>
            </table></td></tr>
        </table>
        <div class="fs-20">
            <div class="text-center text-underline fs-22">निलम्बन प्रक्रिया (TERMINATION POLICY)</div>
            <div class="text-center">संस्था में कार्यरत किसी कर्मचारी के दुराचार अनैतिक कार्यो एवं संदिग्ध गतिविधि में संलिप्त होने पर प्रक्रिया संस्था के स्थाई आदेशों में वर्णित प्रक्रिया के अनुसार ही होगी स्थाई आदेशों में वर्णित निर्देश के अधीन <span class="underline">निलम्बन प्रक्रिया</span> की जा सकती है।</div>
            <div class="flex mt-10 h-50">
                <div class="">प्रतिलिपि प्राप्त की<br><br><br><br></div>
                <div class="pull-right">कृते <?= $co ?><br></div>
            </div>
            <div class="flex mt-10 h-50">
                <div class="">कर्मचारी के हस्ताक्षर<br><br><br><br></div>
                <div class="pull-right">हस्ताक्षर अधिकृत अधिकारी<br></div>
            </div>
            तिथि:- <?= $doj ?>
        </div>
    </div>
</div>
<!-- FORM HANDBOOK OFFICE END (COPY 2) -->

<!-- ===================== PAGE 12 — EPF DECLARATION FORM 11 ===================== -->
<div class="pb"></div>
<!-- EMPLOYEE PROVIDENT FUND -->
<div class="text-right"><span class="pr-20">Token No.:-</span> <?= $enrol ?></div>

<div class="w-full bdr pb">
    <table width="100%" cellspacing="0" style="line-height: 20px;position: relative;">
        <tbody>
            <tr>
                <td width="20%" colspan="6"><img src="../_hr/assets/images/epfo_logo.png" alt="jpg" width="200" height="200" /></td>
                <td>
                    <div class="">
                        <div class="text-center flex">
                            <div class="w-70pr">
                                <h2>DECLARATION FORM 11</h2>
                                <h4 class="fs-12">(To be retained by the Employer for future reference)</h4>
                            </div>
                        </div>
                        <div class="w-70pr">
                            <h2 class="fs-24 text-center">Employees&rsquo; Provident Fund Organization</h2>
                            <h4 class="text-center">&nbsp;THE EMPLOYEES&rsquo; PROVIDENT FUNDS SCHEME, 1952 (PARAGRAPH-34 &amp; 57)</h4>
                            <h4 class="text-center">&amp;</h4>
                            <h4 class="text-center">THE EMPLOYEES&rsquo; PENSION SCHEME, 1995 (PARAGRAPH-24)</h4>
                        </div>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
    <div class=""><h4 class="fs-14" style="text-align: center;">DECLARATION BY A PERSON TAKING UP EMPLOYMENT IN AN ESTABLISHMENT ON WHICH EMPLOYEES&rsquo; PROVIDENT FUND SCHEME, 1952 AND/OR EMPLOYEES&rsquo; PENSION SCHEME, 1995 IS APPLICABLE.&nbsp;</h4><h4 class="fs-15" style="text-align: center;">(PLEASE GO THROUGH THE INSTRUCTIONS)</h4></div>
    <table class="bdr-btm" width="100%" cellspacing="0" style="line-height: 20px; position: relative;">
        <tbody>
            <tr><td style="min-width:800px">1) NAME&nbsp;&nbsp; (TITLE)&nbsp;&nbsp;</td></tr>
            <tr><td style="padding-left:100px;padding-bottom:10px;"><table class="tstable" style="width:100%"><tr><td align="center"><b><?= $nm ?></b></td></tr></table></td></tr>
            <tr><td>2) DATE OF BIRTH&nbsp;&nbsp;</td></tr>
            <tr><td style="padding-left:100px;padding-bottom:10px;"><table class="tstable" style="width:300px;"><tr><tr><td align="center">D</td><td align="center">D</td><td align="center">M</td><td align="center">M</td><td align="center">Y</td><td align="center">Y</td><td align="center">Y</td><td align="center">Y</td></tr><td colspan="8" align="center"><b><?= $dob ?></b></td></tr></table></td></tr>
            <tr><td>3) FATHER&rsquo;S/HUSBAND&rsquo;S NAME&nbsp;&nbsp;</td></tr>
            <tr><td style="padding-left:100px;padding-bottom:10px;"><table class="tstable" style="width:100%"><tr><td align="center"><b><?= $father ?></b></td></tr></table></td></tr>
            <tr><td>4) RELATIONSHIP IN RESPECT OF (3) ABOVE&nbsp;</td></tr>
            <tr><td style="padding-left:700px;"><span class="inline-block bdr w-300"><?= $relFH ?></span></td></tr>
            <tr><td style="padding-bottom:20px;">(PLEASE TICK)</td></tr>
            <tr><td>5) GENDER&nbsp;</td></tr>
            <tr><td style="padding-left:700px;"><span class="inline-block bdr w-300"><?= $gender ?> </span></td></tr>
            <tr><td>6) MOBILE NUMBER (IF ANY)</td></tr>
            <tr><td style="padding-left:700px;"><div class="bdr" style="width:200px;"> &nbsp;<?= $phone1 ?></div></td></tr>
            <tr><td>7) EMAIL ID (IF ANY)</td></tr>
            <tr><td style="padding-left:700px;"><div class="bdr" style="width:200px;"> &nbsp; <?= $email ?></div></td></tr>
            <tr><td>8) WHETHER EARLIER A MEMBER OF THE EMPLOYEES&rsquo; PROVIDENT FUND SCHEME, 1952 ?</td></tr>
            <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;(PLEASE TICK)</td></tr>
            <tr><td style="padding-left:700px;"><table class="tstable" style="width:160px"><tr><td style="width: 50px;" class="bdr text-center">YES</td><td style="width: 50px;" class="bdr text-center">&nbsp;</td><td style="width: 50px;" class="bdr text-center">NO</td><td style="width: 50px;" class="bdr">&nbsp;</td></tr></table></td></tr>
            <tr><td>9) WHETHER EARLIER A MEMBER OF THE EMPLOYEES&rsquo; PENSION SCHEME, 1995?</td></tr>
            <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;(PLEASE TICK)</td></tr>
            <tr><td style="padding-left:700px;"><table class="tstable" style="width:160px"><tr><td style="width: 50px;" class="bdr text-center" colspan="4">YES</td><td style="width: 50px;" class="bdr text-center">&nbsp;</td><td style="width: 50px;" class="bdr text-center" colspan="4">NO</td><td style="width: 50px;" class="bdr">&nbsp;</td></tr></table></td></tr>
            <tr><td>IF RESPONSE TO ANY OR BOTH OF (8) &amp; (9) ABOVE IS YES, THEN MANDATORILY FILL UP THE PREVIOUS EMPLOYMENT DETAILS AT (10,11&amp;12):</td></tr>
        </tbody>
    </table>
    <table width="100%">
        <tbody>
            <tr><td class="text-center bold">A. PREVIOUS EMPLOYMENT DETAILS</td></tr>
            <tr><td>10) THE DETAILS OF THE UNIVERSAL ACCOUNT NUMBER (UAN) OR PREVIOUS PF MEMBER ID:</td></tr>
            <tr><td class="bold">UAN</td></tr>
            <tr><td style="padding-left:100px"><table class="tstable" style="width:160px"><tr><td style="text-align: center;"><?= $uan ?></td></tr></table></td></tr>
            <tr><td style="padding-left:100px">OR</td></tr>
            <tr><td class="bold">PREVIOUS PF MEMBER ID</td></tr>
            <tr><td style="padding-left:100px"><table class="tstable" style="width:160px"><tr><td style="text-align: center;"><?= $oldPf ?></td></tr></table></td></tr>
            <tr><td>11) DATE OF EXIT FOR PREVIOUS MEMBER ID</td></tr>
            <tr><td style="padding-left:100px;padding-bottom:10px;">(DD/MM/YYYY)<table class="tstable" style="width:300px;"><tr><tr><td align="center">D</td><td align="center">D</td><td align="center">M</td><td align="center">M</td><td align="center">Y</td><td align="center">Y</td><td align="center">Y</td><td align="center">Y</td></tr><td colspan="8" align="center"><b></b></td></tr></table></td></tr>
        </tbody>
    </table>
    <table>
        <tbody>
            <tr><td colspan="100">12) (A) IF SCHEME CERTIFICATE ISSUED FOR PREVIOUS EMPLOYMENT, THEN SCHEME CERTIFICATE NUMBER:________</td></tr>
            <tr><td colspan="100" style="padding-left:20px;">(B) IF PENSION PAYMENT ORDER (PPO) ISSUED FOR PREVIOUS EMPLOYMENT, THEN PPO NUMBER:___________</td></tr>
            <tr><td style="" class="pt-20" colspan="9">13) INTERNATIONAL WORKER</td></tr>
            <tr><td style="">(PLEASE TICK)</td></tr>
            <tr><td style="padding-left:700px;"><table class="tstable" style="width:160px"><tr><td style="width: 50px;" class="bdr text-center" colspan="4">YES</td><td style="width: 50px;" class="bdr text-center">&nbsp;</td><td style="width: 50px;" class="bdr text-center" colspan="4">NO</td><td style="width: 50px;" class="bdr text-center">&radic;</td></tr></table></td></tr>
        </tbody>
    </table>

    <div class="pb"></div>

    <table>
        <tbody>
            <tr><td style="padding:20px;" colspan="100" class="fs-15 bold">IF THE REPLY TO (13) ABOVE IS YES, THEN ENTER THE DETAILS IN 13(A), 13(B) &amp; 13(C):</td></tr>
            <tr><td style="" colspan="100"><div class="w-55pr ml-auto">13(A) COUNTRY OF ORIGIN (Please Tick)</div></td></tr>
            <tr><td><table align="center"><tr><td>India</td><td>OTHER THAN INDIA (IF YES, PLEASE MENTION NAME OF THE COUNTRY)</td></tr><tr><td class="text-center">&radic;</td><td>&nbsp;</td></tr></table></td></tr>
            <tr><td style="width: 433.576px;" colspan="17">13(B) PASSPORT NUMBER <span class="bdr-btm"><?= $passNo ?></span> </td></tr>
            <tr><td style="width: 193.576px;" colspan="9">13(C) PASSPORT VALID FROM</td></tr>
            <tr><td style="padding-left:100px;padding-bottom:10px;"><table class="tstable" style="width:300px;"><tr><tr><td align="center">D</td><td align="center">D</td><td align="center">M</td><td align="center">M</td><td align="center">Y</td><td align="center">Y</td><td align="center">Y</td><td align="center">Y</td></tr><td colspan="8" align="center">&nbsp;<b></b></td></tr></table></td></tr>
            <tr><td style="width: 15.7986px;">To</td></tr>
            <tr><td style="padding-left:100px;padding-bottom:40px;"><table class="tstable" style="width:300px;"><tr><tr><td align="center">D</td><td align="center">D</td><td align="center">M</td><td align="center">M</td><td align="center">Y</td><td align="center">Y</td><td align="center">Y</td><td align="center">Y</td></tr><td colspan="8" align="center">&nbsp;<b></b></td></tr></table></td></tr>
            <tr><td colspan="100" class="">14) EDUCATIONAL QUALIFICATION</td></tr>
            <tr><td class="" colspan="100">(PLEASE TICK)</td></tr>
            <tr><td style="padding-left:100px;">
                <table class="tstable" style="width:100%;"><tr>
                <td style="width:150px; text-align: center; border:1px solid;" colspan="3">ILLITERATE</td>
                <td style="width:150px; text-align: center; border:1px solid;" colspan="3">NON-MATRIC</td>
                <td style="width:150px; text-align: center; border:1px solid;" colspan="3">MATRIC</td>
                <td style="width:150px; text-align: center; border:1px solid;" colspan="3">SENIOR SECONDARY</td>
                <td style="width:150px; text-align: center; border:1px solid;" colspan="3">GRADUATE</td>
                <td style="width:150px; text-align: center; border:1px solid;" colspan="3">POST GRADUATE</td>
                <td style="width:150px; text-align: center; border:1px solid;" colspan="3">DOCTOR</td>
                <td style="width:150px; text-align: center; border:1px solid;" colspan="5">TECHNICAL/ PROFESSIONAL</td>
                </tr><tr>
                <td style="width:150px; text-align: center; border:1px solid;" colspan="3">&nbsp;</td>
                <td style="width:150px; text-align: center; border:1px solid;" colspan="3">&nbsp;</td>
                <td style="width:150px; text-align: center; border:1px solid;" colspan="3">&nbsp;</td>
                <td style="width:150px; text-align: center; border:1px solid;" colspan="3">&nbsp;</td>
                <td style="width:150px; text-align: center; border:1px solid;" colspan="3">&nbsp;</td>
                <td style="width:150px; text-align: center; border:1px solid;" colspan="3">&nbsp;</td>
                <td style="width:150px; text-align: center; border:1px solid;" colspan="3">&nbsp;</td>
                <td style="width:150px; text-align: center; border:1px solid;" colspan="5">&nbsp;</td>
                </tr></table>
            </td></tr>
            <tr><td>15) MARITAL STATUS</td></tr>
            <tr><td style="padding-left:700px"><div class="bdr"><?= $ms ?></div></td></tr>
            <tr><td class="pl-20">(PLEASE TICK)</td></tr>
            <tr><td style="padding-top:40px">16) SPECIALLY ABLED (PLEASE TICK)</td></tr>
            <tr><td style="padding-left:100px">
                <table class="tstable" style="width:100%;" align="center"><tr>
                <td style="width: 54.6875px; border:1px solid; text-align: center;" colspan="3">YES</td>
                <td style="width: 56.9097px; border:1px solid; text-align: center;" colspan="3">NO</td>
                <td style="width: 46.9097px;">&nbsp;</td>
                <td style="width: 298.021px; text-align: center; border:1px solid;" colspan="12">IF YES, TICK THE CATEGORY</td>
                </tr><tr>
                <td style="width: 54.6875px; border:1px solid; text-align: center;" colspan="3">&nbsp;</td>
                <td style="width: 56.9097px; border:1px solid; text-align: center;" colspan="3">&radic;</td>
                <td style="width: 46.9097px;">&nbsp;</td>
                <td style="width: 105.799px; text-align: center; border:1px solid;" colspan="4">LOCOMOTIVE</td>
                <td style="width: 94.6875px; text-align: center; border:1px solid;" colspan="4">VISUAL</td>
                <td style="width: 86.9097px; text-align: center; border:1px solid;" colspan="4">HEARING</td>
                </tr></table>
            </td></tr>
            <tr><td style="width: 135.799px;">17) KYC DETAILS</td></tr>
            <tr><td style="padding-left:100px">
                <table align="center">
                <tr>
                    <td style="text-align: center; border:1px solid;">KYC DOCUMENT TYPE</td>
                    <td style="text-align: center; border:1px solid;">NAME AS ON KYC DOCUMENT</td>
                    <td style="text-align: center; border:1px solid;">NUMBER</td>
                    <td style="text-align: center; border:1px solid;">REMARKS, IF ANY</td>
                    <td style="">&nbsp;</td>
                </tr>
                <tr><td style="text-align: center; border:1px solid;">BANK ACCOUNT-1*</td><td style="text-align: center; border:1px solid;"><?= $bank ?></td><td style="text-align: center; border:1px solid;"><?= $acno ?></td><td style="text-align: center; border:1px solid;"><?= $ifsc ?></td><td style="">&nbsp;</td></tr>
                <tr><td style="text-align: center; border:1px solid;">NPR/AADHAAR</td><td style="text-align: center; border:1px solid;">AADHAAR</td><td style="text-align: center; border:1px solid;"><?= $adhaar ?></td><td style="text-align: center; border:1px solid;">&nbsp;</td><td style="">&nbsp;</td></tr>
                <tr><td style="text-align: center; border:1px solid;">PERMANENT ACCOUNT NUMBER (PAN)</td><td style="text-align: center; border:1px solid;"></td><td style="text-align: center; border:1px solid;"><?= $panNo ?></td><td style="text-align: center; border:1px solid;">&nbsp;</td><td style="">&nbsp;</td></tr>
                <tr><td style="text-align: center; border:1px solid;">PASSPORT</td><td style="text-align: center; border:1px solid;">&nbsp;</td><td style="text-align: center; border:1px solid;">&nbsp;</td><td style="text-align: center; border:1px solid;"></td><td style="">&nbsp;</td></tr>
                <tr><td style="text-align: center; border:1px solid;">DRIVING LICENCE</td><td style="text-align: center; border:1px solid;">&nbsp;</td><td style="text-align: center; border:1px solid;"><?= $driveNo ?></td><td style="text-align: center; border:1px solid;"></td><td style="">&nbsp;</td></tr>
                <tr><td style="text-align: center; border:1px solid;">ELECTION CARD</td><td style="text-align: center; border:1px solid;">&nbsp;</td><td style="text-align: center; border:1px solid;"><?= $voteId ?></td><td style="text-align: center; border:1px solid;">&nbsp;</td><td style="">&nbsp;</td></tr>
                <tr><td style="text-align: center; border:1px solid;">RATION CARD</td><td style="text-align: center; border:1px solid;">&nbsp;</td><td style="text-align: center; border:1px solid;">&nbsp;</td><td style="text-align: center; border:1px solid;">&nbsp;</td><td style="">&nbsp;</td></tr>
                <tr><td style="text-align: center; border:1px solid;">ESIC CARD</td><td style="text-align: center; border:1px solid;">&nbsp;</td><td style="text-align: center; border:1px solid;"><?= $esiNo ?></td><td style="text-align: center; border:1px solid;">&nbsp;</td><td style="">&nbsp;</td></tr>
                </table>
            </td></tr>
            <tr><td style="padding-left:100px"><div style="text-align: center; border:1px solid;" colspan="4" class="fs-12"><span class="bold">* Mandatory Field (NOTE: BANK ACCOUNT NUMBER (ALONG WITH IFSC CODE) IS MANDATORY.</span> YOU ARE HOWEVER ADVISED TO PROVIDE ALL KYC DOCUMENTS AVAILABLE WITH YOU IN ADDITION TO MANDATORY KYCS TO AVAIL BETTER SERVICES. <span class="bold">SELF-ATTESTED PHOTOCOPIES OF THE DOCUMENTS </span>MUST BE ATTACHED WITH THIS FORM.</div></td></tr>
        </tbody>
    </table>
</div>

<div class="pb_"></div>
<br>
<div class="bold fs-22 text-center"><span>C.</span><span>Undertaking:</span></div>
<br>
<div class="bold fs-20 text-center">A. I CERTIFY THAT ALL THE INFORMATION GIVEN ABOVE IS TRUE TO THE BEST OF MY KNOWLEDGE AND BELIEF.</div>
<br>
<div>
    <div class="bold fs-20 m-auto text-center">B. IN CASE, EARLIER A MEMBER OF EPF SCHEME, 1952 AND/OR EPS, 1995,</div>
    <div>
        <ol class="list-style-none fs-20 text-center">
            <li class="bold">(I) I HAVE ENSURED THE CORRECTNESS OF MY UAN/PREVIOUS PF MEMBER ID.<br></li>
            <li class="bold">(II) THIS MAY ALSO BE TREATED AS MY REQUEST FOR TRANSFER OF FUNDS AND SERVICE DETAILS IF APPLICABLE FROM THE PREVIOUS ACCOUNT AS DECLARED ABOVE TO THE PRESENT P.F. ACCOUNT. (THE TRANSFER WOULD BE POSSIBLE ONLY IF THE IDENTIFIED KYC DETAILS APPROVED BY PREVIOUS EMPLOYER HAS BEEN VERIFIED BY PRESENT EMPLOYER USING HIS DIGITAL SIGNATURE CERTIFICATE).<br></li>
            <li>(III) <span class="bold">I</span> AM AWARE THAT <span class="bold">I</span> CAN SUBMIT <span class="bold">M</span>Y NOMINATION FORM THROUGH <span class="bold fs-22">UAN</span> BASED MEMBER PORTAL.<br></li>
        </ol>
    </div>
    <div class="flex fs-20 bold">
        <div><div>Date:<span class="ml-20"><?= $doj ?></span></div><div class="">Place: <span class="ml-20">Panipat</span></div></div>
        <div class="w-250 ml-auto mt-auto pull-right">SIGNATURE OF MEMBER</div>
    </div>
    <div class="fs-22"><br><br><div class="mt-30 text-center bold">DECLARATION BY PRESENT EMPLOYER</div></div>
    <br>
    <div class="fs-20 text-center lh-30">
        <div class="">
            <span class="bold">A.</span> THE MEMBER Mr./Ms./Mrs <span class="inline-block w-150 text-center bold"><?= $nm ?></span>
            HAS JOINED ON <span class="inline-block w-150 text-center bold"><?= $doj ?></span> HAS BEEN ALLOTED PF MEMBER ID
            <span class="inline-block text-center bold">/<?= $pfNo ?> </span>
        <br>
            <span class="bold">B.</span> IN CASE THE PERSON WAS EARLIER NOT A MEMBER OF EPF SCHEME, 1952 AND EPS, 1995:
            <br><span class="bold">(POST ALLOTMENT OF UAN)</span> THE UAN ALLOTTED FOR THE MEMBER IS <span class="inline-block text-center bold bdr-dotted w-250"></span>
            <br><b>PLEASE TICK THE APPROPRIATE OPTION:</b>
            <span>THE KYC DETAILS OF THE ABOVE MEMBER IN THE UAN DATABASE</span>
            <br><input type="checkbox">HAVE NOT BEEN UPLOADED
            <br><input type="checkbox">HAVE BEEN UPLOADED BUT NOT APPROVED
            <br><input type="checkbox">HAVE BEEN UPLOADED AND APPROVED WITH DSC
        </div>
        <br>
    </div>
    <div class="text-center"><span class="bold">C.</span> <span>IN CASE THE PERSON WAS EARLIER A MEMBER OF EPF SCHEME, 1952 AND EPS, 1995:</span></div>
    <br>THE ABOVE MEMBER ID OF THE MEMBER AS MENTIONED IN (A) ABOVE HAS BEEN TAGGED WITH HIS/HER UAN/PREVIOUS MEMBER ID AS DECLARED BY MEMBER.
    <br><b>PLEASE TICK THE APPROPRIATE OPTION:-</b>
    <br><input type="checkbox">THE KYC DETAILS OF THE ABOVE MEMBER IN THE UAN DATABASE HAVE BEEN APPROVED WITH DIGITAL SIGNATURE CERTIFICATE AND TRANSFER REQUEST HAS BEEN GENERATED ON PORTAL.
    <br><input type="checkbox">AS THE DSC OF ESTABLISHMENT ARE NOT REGISTERED WITH EPFO, THE MEMBER HAS BEEN INFORMED TO FILE PHYSICAL CLAIM (FORM-13) FOR TRANSFER OF FUNDS FROM HIS PREVIOUS ESTABLISHMENT.
    <br>
    <div class="flex align-end h-100">
        <div>DATE:<span class="pl-25"><?= $doj ?></span></div>
        <div class="w-70pr ml-auto fs-20 bold pull-right"><br><br><br>SIGNATURE OF EMPLOYER WITH SEAL OF ESTABLISHMENT</div>
    </div>
</div>
<!-- EMPLOYEE PROVIDENT FUND END -->

<!-- ===================== PAGE 13 — POLICE VERIFICATION ===================== -->
<div class="pb"></div>
<!-- POLICE VERIFICATION -->
<div class="lh-30 fs-20">
    <div class="pull-right">
        <div class="ml-auto w-200">Date:-<?= date('d/m/Y') ?></div>
    </div>
    <div class="flex">
        <div class="">
            <div class=""><br>
                <div>Token No.:<?= $enrol ?></div>
                <div class="pl-20">To,</div>
                <div class="pl-30">
                    Police Station<br>
                    Chandni Bagh Thana, Panipat
                </div>
                <br>
                <br>
            </div>
        </div>
        <div class="pull-right">
            <div class="h-200 w-200">
                <?php if ($photoSrc): ?><img src="<?= $photoSrc ?>" width="120px" style="position:relative; top:-70px;"><?php endif; ?>
            </div>
        </div>
    </div>
    <div class=""><p class="pl-30">SUB: <span class="text-underline">POLICE VERIFICATION FOR EMPLOYMENT</span></p></div>
    <br>
    <div class="pl-30">Dear Sir,</div>
    <div class="w-90pr m-auto">
        The candidate whose particulars are given below is under consideration for employment/
        already employed in our organization. We shall be grateful if you could kindly verify his
        antecedents and background. An endorsement from your side in this respect will be highly
        appreciated.
        <br><br>
    </div>
    <br>
    <table class="w-100 m-auto mt-30" border="1">
        <tr class="bold text-center"><td>Name Of Candidate:</td><td>Token No:</td><td>Department</td><td>Date of joining</td></tr>
        <tr class="bold text-center"><td><?= $nm ?></td><td><?= $enrol ?></td><td><?= $dept ?></td><td><?= $doj ?></td></tr>
    </table>
    <br>
    <table class="w-100 m-auto mt-50" border="1">
        <tr class="bold"><td>Age</td><td class="text-center" width="64.6%"><?= $age ?></td></tr>
        <tr class="bold"><td>Father/Husband's Name</td><td class="text-center"><?= $father ?></td></tr>
        <tr class="bold"><td>Permanent Address</td><td class="text-center"><?= $permAdd ?></td></tr>
        <tr class="bold"><td>Thana</td><td class="text-center">Panipat</td></tr>
        <tr class="bold"><td>Present Address</td><td class="text-center"><?= $presAdd ?></td></tr>
    </table>
    <br>
    <div class="w-100 ml-auto bold">Declaration:- I hereby declare that the above information is true and I undertake to observe the above procedure.</div>
    <br><br>
    <table class="w-100 m-auto bold mt-50">
        <tr>
            <td>(Employee's Signature)</td>
            <td>(Employee's Left Thumb Impression)</td>
            <td>(Identification Mark)</td>
        </tr>
    </table>
    <br><br>
    <div class="mt-50 bold pl-30">Thanking You,</div>
    <br><br>
    <div class="mt-50 mb-100 bold pl-30">Yours faithfully,</div>
    <br><br>
    <div class="bold pl-30">Authorized Signatory</div>
</div>
<!-- POLICE VERIFICATION END -->

</div><!-- /emp-wrap -->
<?php endforeach; ?>

<script>
window.addEventListener('load', function() { window.print(); });
</script>
</body>
</html>
