<?php
// Maps a tblEmployee (+ joined company) row to the flat entry array consumed by
// card_render.js. Shared by card_designer.php (sample preview) and card_print.php.

function cardDate(?string $d): string {
    return ($d && $d !== '0000-00-00') ? date('d-M-Y', strtotime($d)) : '';
}

function cardEntryFromRow(array $e, string $base): array {
    return [
        // company
        'company_name'      => $e['CompanyName']    ?? '',
        'company_address'   => $e['CompanyAddress'] ?? '',
        'issuer_name'        => $e['SignName']        ?? '',
        'issuer_designation' => $e['SignDesignation'] ?? '',
        // identity
        'code'          => ($e['EmployeeCode'] ?? '') !== '' ? $e['EmployeeCode'] : ($e['EnrollId'] ?? ''),
        'enroll_id'     => $e['EnrollId']      ?? '',
        'name'          => $e['Name']          ?? '',
        'father_name'   => $e['FatherName']    ?? '',
        'gender'        => $e['Gender']        ?? '',
        'dob'           => cardDate($e['DOB']      ?? null),
        'doj'           => cardDate($e['JoinDate'] ?? null),
        'dol'           => cardDate($e['DOL']      ?? null),
        'department'    => $e['Department']    ?? '',
        'designation'   => $e['Designation']   ?? '',
        'grade'         => $e['Grade']         ?? '',
        'contractor'    => $e['Contractor']    ?? '',
        // contact
        'phone'         => ($e['Phone'] ?? '') !== '' ? $e['Phone'] : ($e['PhoneNo'] ?? ''),
        'email'         => $e['Email']         ?? '',
        'present_add'   => $e['PresentAdd']    ?? '',
        'permanent_add' => $e['PermanentAdd']  ?? '',
        // statutory / personal
        'aadhaar'       => $e['AdhaarID']      ?? '',
        'blood_group'   => $e['BloodGroup']    ?? '',
        'marital_status'=> $e['MaritalStatus'] ?? '',
        'qualification' => $e['Qualification'] ?? '',
        'uan'           => $e['UAN']           ?? '',
        'pf_no'         => $e['PfNo']          ?? '',
        'esi_no'        => $e['EsiNo']         ?? '',
        'pan_no'        => $e['PanNo']         ?? '',
        // images
        '__photo'       => !empty($e['Photo'])     ? $base . '/uploads/employees/' . rawurlencode($e['Photo'])     : '',
        '__signature'   => !empty($e['Signature']) ? $base . '/uploads/employees/' . rawurlencode($e['Signature']) : '',
        '__issuer_sign' => !empty($e['SignImage']) ? $base . '/uploads/company/'   . rawurlencode($e['SignImage']) : '',
    ];
}
