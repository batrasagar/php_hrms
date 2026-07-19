<?php
// ── Role management / access permissions (ported concept from laravel_saas) ──
// Permissions are a RESTRICTING layer for the operator / compliance / user roles:
//  - superadmin + admin always have full access (bypass).
//  - a restricted user WITH a permission role gets only the checked modules
//    (intersected with what their base role could reach anyway — base-role data
//    scoping such as the compliance employee filter or the pinned company of
//    role 'user' always still applies).
//  - a restricted user with NO role assigned keeps the legacy full-for-role
//    behaviour, so rolling this out changes nothing until roles are assigned.
//
// Permission names are "{module}.view" / "{module}.edit". The catalog below is
// the single source of truth; module keys deliberately match the sidebar's
// $activePage keys. tblRolePerm stores the names directly (no permission table).

const PERM_ACTIONS = ['view' => 'View', 'edit' => 'Edit'];

/** group => [module_key => [label, actions]] */
function permCatalog(): array {
    static $cat = null;
    if ($cat !== null) return $cat;
    $ve = ['view', 'edit'];
    $v  = ['view'];
    return $cat = [
        'Employees' => [
            'employees'      => ['Employees', $ve],
            'emp_import'     => ['Import Employees', $ve],
            'emp_bulk'       => ['Bulk Edit', $ve],
            'emp_left'       => ['Mark Left (Bulk)', $ve],
            'print'          => ['Print iCard / Files', $ve],
            'card_templates' => ['Card Designer', $ve],
            'workers'        => ['Wage Workers', $ve],
        ],
        'Punch Log' => [
            'punch_sync'       => ['Sync & Process', $ve],
            'punchlog'         => ['Punch Log View', $v],
            'punch_correction' => ['Punch Correction', $ve],
            'devices'          => ['Devices', $ve],
        ],
        'Shifts' => [
            'shifts'       => ['Shift Master', $ve],
            'shift_assign' => ['Shift Assignment', $ve],
            'shift_cyclic' => ['Cyclic Shifts', $ve],
            'compoff'      => ['Comp Off', $ve],
        ],
        'Attendance & Leaves' => [
            'overtime'       => ['Overtime Entry', $ve],
            'ot_approvals'   => ['OT Approvals', $ve],
            'mark_ot_abs'    => ['Mark OT / Absent', $ve],
            'attn_import'    => ['Import Attendance', $ve],
            'leaves'         => ['Leave Marking', $ve],
            'leave_range'    => ['Mark Leave (Range)', $ve],
            'leave_types'    => ['Leave Types', $ve],
            'leave_policy'   => ['Leave Policies', $ve],
            'leave_assign'   => ['Assign Leave Policy', $ve],
            'leave_register' => ['Leave Register', $ve],
        ],
        'Reports' => [
            'report_active'     => ['Active Employees', $v],
            'report_attendance' => ['Attendance Report', $ve],   // edit = grid cell editing
            'report_monthly'    => ['Monthly Report', $v],
            'report_swipe'      => ['Swipe Report', $v],
            'report_strength'   => ['Strength Summary', $v],
            'report_ot'         => ['OT Report', $v],
            'report_leave'      => ['Leave Report', $v],
            'report_joinleft'   => ['Joining / Exit', $v],
            'tv_dashboard'      => ['TV Dashboard', $v],
        ],
        'Payroll' => [
            'payroll_run'        => ['Payroll Run', $ve],
            'payroll_emp_setup'  => ['Employee Setup', $ve],
            'payroll_ctc_import' => ['CTC Import', $ve],
            'payroll_components' => ['Components', $ve],
            'payroll_settings'   => ['PF / ESI / TDS', $ve],
            'advance'            => ['Employee Advances', $ve],
        ],
        'HR Documents' => [
            'doc_templates' => ['Document Templates', $ve],
            'doc_issue'     => ['Issue Document', $ve],
            'doc_material'  => ['Issued Material', $ve],
            'doc_fnf'       => ['Full & Final', $ve],
        ],
        'Settings' => [
            'settings'          => ['HRMS Settings', $ve],
            'notifications'     => ['Email Notifications', $ve],
            'sms_settings'      => ['SMS Settings', $ve],
            'whatsapp_settings' => ['WhatsApp Settings', $ve],
            'dev_issues'        => ['Dev Issues', $ve],
        ],
        'Masters' => [
            'holidays'    => ['Holidays', $ve],
            'departments' => ['Departments', $ve],
            'contractors' => ['Contractors', $ve],
        ],
        // Data-scope permissions restrict WHICH ROWS a role sees, rather than which
        // pages it can open. They are only ever honoured when explicitly granted —
        // see hasExplicitPerm() — because can() reports true for unrestricted users.
        'Data Scope' => [
            'compliance_data' => ['Restrict to Compliance employees', $v],
        ],
        'Other' => [
            'companies' => ['Companies', $ve],
            'apikeys'   => ['API Keys', $ve],
        ],
    ];
}

/** Flat list of every valid permission name. */
function permAllNames(): array {
    $out = [];
    foreach (permCatalog() as $modules) {
        foreach ($modules as $key => [, $actions]) {
            foreach ($actions as $a) $out[] = "$key.$a";
        }
    }
    return $out;
}

/**
 * Effective permission set for the current user.
 * null  => unrestricted (superadmin/admin, no role assigned, or tables missing).
 * array => explicit allow-list of "module.action" names.
 */
function userPermissions(): ?array {
    static $done = false, $perms = null;
    if ($done) return $perms;
    $done = true;
    $role = $_SESSION['user_role'] ?? '';
    $uid  = (int)($_SESSION['user_id'] ?? 0);
    if (!$uid || $role === 'superadmin' || $role === 'admin') return $perms = null;
    try {
        $db = getDb();

        // A role only grants its permissions while its own company is the active one.
        // Roles with CompanyId NULL are company-agnostic and always apply, which is
        // every role created before M035.
        $activeCo = 0;
        try { $activeCo = (int)activeCompanyId($db, currentUser()); } catch (Throwable $e) { $activeCo = 0; }

        $s = $db->prepare(
            "SELECT DISTINCT rp.Perm
             FROM tblUserRole ur
             JOIN tblRole r      ON r.id = ur.RoleId AND r.IsActive = 1
             JOIN tblRolePerm rp ON rp.RoleId = ur.RoleId
             WHERE ur.UserId = ?
               AND (r.CompanyId IS NULL OR r.CompanyId = ?)"
        );
        $s->execute([$uid, $activeCo]);
        $list = $s->fetchAll(PDO::FETCH_COLUMN);

        // Deliberately NOT filtered by company: this asks "is this user governed by
        // roles at all?". Only a user with no role anywhere gets the legacy
        // unrestricted behaviour. A user whose roles simply do not match the active
        // company must fall through to an empty allow-list (deny), never to null —
        // otherwise switching company would hand them unrestricted access.
        $chk = $db->prepare("SELECT 1 FROM tblUserRole WHERE UserId = ? LIMIT 1");
        $chk->execute([$uid]);
        if (!$chk->fetch()) return $perms = null;
        return $perms = $list;
    } catch (Throwable $e) {
        return $perms = null;   // pre-migration / table missing
    }
}

function can(string $perm): bool {
    $p = userPermissions();
    return $p === null || in_array($perm, $p, true);
}

function canAny(array $perms): bool {
    foreach ($perms as $p) if (can($p)) return true;
    return false;
}

/**
 * True only when the permission is EXPLICITLY listed on the user's assigned role.
 *
 * Different from can(), which answers "is this allowed?" and so returns true for
 * unrestricted users (superadmin, admin, or anyone with no role). That is right for
 * page gating but wrong for a data-scope permission: with can(), granting
 * "restrict to Compliance employees" would silently restrict every superadmin too.
 * A restriction has to be opted into, never inherited from being unrestricted.
 */
function hasExplicitPerm(string $perm): bool {
    $p = userPermissions();
    return is_array($p) && in_array($perm, $p, true);
}

/** True when the user actually has a permission role assigned (i.e. is restricted by one). */
function hasAssignedRole(): bool {
    return userPermissions() !== null;
}

/**
 * Pages a compliance (auditor) account may never open — each calls blockCompliance()
 * and would bounce straight back, so their sidebar links must stay hidden even when
 * the assigned role grants the permission.
 */
function complianceBlockedPerms(): array {
    return ['punchlog.view', 'punch_correction.view', 'punch_sync.view',
            'card_templates.view', 'tv_dashboard.view'];
}

/** can(), plus the compliance hard-blocks. Use for sidebar link visibility. */
function canMenu(string $perm): bool {
    if (isCompliance() && in_array($perm, complianceBlockedPerms(), true)) return false;
    return can($perm);
}

/** canAny() counterpart honouring the compliance hard-blocks. */
function canAnyMenu(array $perms): bool {
    foreach ($perms as $p) if (canMenu($p)) return true;
    return false;
}

/** Every module.view of a catalog group — for hiding whole sidebar sections. */
function canAnyInGroup(string $group): bool {
    $cat = permCatalog();
    if (!isset($cat[$group])) return false;
    foreach ($cat[$group] as $key => $m) if (can("$key.view")) return true;
    return false;
}

/** Page guard: redirect away when the module isn't permitted. */
function requirePermission(string $perm): void {
    if (can($perm)) return;
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/ajax/')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => ['You do not have permission for this action.']]);
        exit;
    }
    $base = defined('BASE_URL') ? BASE_URL : '';
    header('Location: ' . $base . '/index.php');
    exit;
}
