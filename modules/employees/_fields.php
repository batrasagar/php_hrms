<?php
/**
 * Shared employee field catalog — used by the Bulk Edit grid (bulk_edit.php)
 * and the Excel/CSV bulk-update facility (bulk_update.php) so both agree on
 * exactly which tblEmployee columns exist and how each value is coerced.
 *
 * key = tblEmployee column; 'type' drives the input widget and value coercion.
 */
function employeeFieldCatalog(): array {
    return [
        'Sr'            => ['label'=>'Sr',             'type'=>'int',    'w'=>55],
        'EmployeeCode'  => ['label'=>'Code',           'type'=>'text',   'w'=>90],
        'EnrollId'      => ['label'=>'Enroll ID',      'type'=>'text',   'w'=>85],
        'Name'          => ['label'=>'Name',           'type'=>'text',   'w'=>150, 'req'=>true],
        'FatherName'    => ['label'=>'Father Name',    'type'=>'text',   'w'=>150],
        'Gender'        => ['label'=>'Gender',         'type'=>'select', 'options'=>['','Male','Female','Other'], 'w'=>90],
        'DOB'           => ['label'=>'DOB',            'type'=>'date',   'w'=>120],
        'MaritalStatus' => ['label'=>'Marital Status', 'type'=>'text',   'w'=>110],
        'BloodGroup'    => ['label'=>'Blood Group',    'type'=>'text',   'w'=>90],
        'Qualification' => ['label'=>'Qualification',  'type'=>'text',   'w'=>130],
        'Phone'         => ['label'=>'Mobile',         'type'=>'text',   'w'=>110],
        'Email'         => ['label'=>'Email',          'type'=>'text',   'w'=>150],
        'PermanentAdd'  => ['label'=>'Permanent Addr', 'type'=>'text',   'w'=>170],
        'PresentAdd'    => ['label'=>'Present Addr',   'type'=>'text',   'w'=>170],
        'Department'    => ['label'=>'Department',     'type'=>'text',   'w'=>120, 'list'=>'deptList'],
        'Contractor'    => ['label'=>'Contractor',     'type'=>'text',   'w'=>120],
        'Designation'   => ['label'=>'Designation',    'type'=>'text',   'w'=>130],
        'Grade'         => ['label'=>'Grade',          'type'=>'text',   'w'=>90],
        'EmployeeType'  => ['label'=>'Employee Type',  'type'=>'text',   'w'=>120],
        'JoinDate'      => ['label'=>'Join Date',      'type'=>'date',   'w'=>120],
        'DOL'           => ['label'=>'Date of Leaving','type'=>'date',   'w'=>120],
        'Status'        => ['label'=>'Status',         'type'=>'select', 'options'=>['active','inactive','terminated'], 'w'=>100],
        'BasicSalary'   => ['label'=>'Basic Salary',   'type'=>'decimal','w'=>110],
        'GrossSalary'   => ['label'=>'Gross Salary',   'type'=>'decimal','w'=>110],
        'AdhaarID'      => ['label'=>'Aadhaar',        'type'=>'text',   'w'=>130],
        'PanNo'         => ['label'=>'PAN',            'type'=>'text',   'w'=>110],
        'UAN'           => ['label'=>'UAN',            'type'=>'text',   'w'=>120],
        'PfNo'          => ['label'=>'PF No',          'type'=>'text',   'w'=>110],
        'EsiNo'         => ['label'=>'ESI No',         'type'=>'text',   'w'=>110],
        'BankName'      => ['label'=>'Bank Name',      'type'=>'text',   'w'=>130],
        'BankAcNo'      => ['label'=>'Bank A/C',       'type'=>'text',   'w'=>120],
        'IFSCCode'      => ['label'=>'IFSC',           'type'=>'text',   'w'=>110],
    ];
}
