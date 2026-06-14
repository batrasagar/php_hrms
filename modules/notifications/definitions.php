<?php
return [
    'groups' => [
        'employee' => [
            'icon'  => 'bi-person-badge',
            'label' => 'Employee',
            'items' => [
                'emp_attendance_daily'   => ['label' => 'Daily Attendance Summary',       'schedule' => 'daily'],
                'emp_late_alert'         => ['label' => 'Late Coming Alert',              'schedule' => 'event'],
                'emp_absent_alert'       => ['label' => 'Absent Alert',                   'schedule' => 'event'],
                'emp_missing_punch'      => ['label' => 'Missing Punch Alert',            'schedule' => 'daily'],
                'emp_ot_request'         => ['label' => 'Overtime Approval Request',      'schedule' => 'event'],
                'emp_leave_status'       => ['label' => 'Leave Approval / Rejection',     'schedule' => 'event'],
                'emp_leave_balance'      => ['label' => 'Leave Balance Statement',        'schedule' => 'monthly'],
                'emp_shift_notify'       => ['label' => 'Shift Schedule Notification',    'schedule' => 'event'],
                'emp_birthday'           => ['label' => 'Birthday Greeting',              'schedule' => 'daily'],
                'emp_anniversary'        => ['label' => 'Work Anniversary Greeting',      'schedule' => 'daily'],
                'emp_attendance_monthly' => ['label' => 'Monthly Attendance Statement',   'schedule' => 'monthly'],
            ],
        ],
        'manager' => [
            'icon'  => 'bi-briefcase',
            'label' => 'Manager',
            'items' => [
                'mgr_absent_today'  => ['label' => 'Employees Absent Today',            'schedule' => 'daily'],
                'mgr_late_report'   => ['label' => 'Late Arrivals Report',              'schedule' => 'daily'],
                'mgr_ot_pending'    => ['label' => 'Overtime Pending Approvals',        'schedule' => 'daily'],
                'mgr_leave_pending' => ['label' => 'Leave Requests Awaiting Approval',  'schedule' => 'event'],
                'mgr_dept_summary'  => ['label' => 'Department Attendance Summary',     'schedule' => 'daily'],
                'mgr_shortage'      => ['label' => 'Workforce Shortage Alert',          'schedule' => 'daily'],
            ],
        ],
        'hr' => [
            'icon'  => 'bi-people',
            'label' => 'HR',
            'items' => [
                'hr_attendance_dashboard' => ['label' => 'Daily Attendance Dashboard',      'schedule' => 'daily'],
                'hr_anomalies'            => ['label' => 'Attendance Anomalies Report',     'schedule' => 'daily'],
                'hr_probation_reminder'   => ['label' => 'Probation Completion Reminders', 'schedule' => 'daily'],
                'hr_contract_renewal'     => ['label' => 'Contract Renewal Reminders',     'schedule' => 'daily'],
                'hr_doc_expiry'           => ['label' => 'Document Expiry Alerts',         'schedule' => 'daily'],
            ],
        ],
        'payroll' => [
            'icon'  => 'bi-currency-rupee',
            'label' => 'Payroll',
            'items' => [
                'pay_slip'      => ['label' => 'Salary Slip Delivery',            'schedule' => 'event'],
                'pay_processed' => ['label' => 'Payroll Processed Notification',  'schedule' => 'event'],
                'pay_hold'      => ['label' => 'Salary Hold Notification',        'schedule' => 'event'],
                'pay_bonus'     => ['label' => 'Bonus / Incentive Statement',     'schedule' => 'event'],
                'pay_tax'       => ['label' => 'Tax Deduction Summary',           'schedule' => 'monthly'],
                'pay_form16'    => ['label' => 'Year-end Form 16 Availability',   'schedule' => 'event'],
            ],
        ],
    ],

    'scheduled_reports' => [
        'report_attn_daily'    => ['label' => 'Daily Attendance Summary',            'frequency' => 'daily',   'default_time' => '08:00', 'default_day' => ''],
        'report_late_weekly'   => ['label' => 'Weekly Late / Absent Analysis',       'frequency' => 'weekly',  'default_time' => '08:00', 'default_day' => 'monday'],
        'report_payroll_mthly' => ['label' => 'Monthly Payroll Register',            'frequency' => 'monthly', 'default_time' => '10:00', 'default_day' => '1'],
        'report_ot_mthly'      => ['label' => 'Monthly Overtime Report',             'frequency' => 'monthly', 'default_time' => '10:00', 'default_day' => '1'],
        'report_dept_mthly'    => ['label' => 'Monthly Dept. Attendance Analysis',   'frequency' => 'monthly', 'default_time' => '10:00', 'default_day' => '1'],
    ],
];
