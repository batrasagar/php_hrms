<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    $role = $_SESSION['user_role'] ?? '';
    // Operators are co-admins that can reach every back-office page except user management.
    if ($role !== 'admin' && $role !== 'superadmin' && $role !== 'operator') {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

// User-management pages (create/edit/delete accounts). Operators are excluded here
// even though they pass requireAdmin() everywhere else.
function requireUserAdmin(): void {
    requireLogin();
    $role = $_SESSION['user_role'] ?? '';
    if ($role !== 'admin' && $role !== 'superadmin') {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function requireSuperAdmin(): void {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'superadmin') {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function currentUser(): array {
    $id     = $_SESSION['user_id']              ?? 0;
    $role   = $_SESSION['user_role']            ?? 'user';
    $parent = $_SESSION['user_parent_admin_id'] ?? 0;
    return [
        'id'              => $id,
        'name'            => $_SESSION['user_name']            ?? '',
        'role'            => $role,
        'company_limit'   => $_SESSION['user_company_limit']   ?? 1,
        'parent_admin_id' => $parent,
        'company_id'      => $_SESSION['user_company_id']      ?? 0,
        // Data-scope owner. All company data is owned by an admin id (tblCompany.AdminId).
        // Operators and compliance users own no companies of their own — they act on their
        // parent admin's companies — so their scope_id is the parent admin. For every other
        // role scope_id === id, which keeps existing behaviour byte-for-byte identical.
        'scope_id'        => in_array($role, ['operator','compliance'], true) ? (int)$parent : (int)$id,
    ];
}

/** The 'compliance' role: a co-admin scoped to compliance-flagged employees + reports only. */
function isCompliance(): bool {
    return ($_SESSION['user_role'] ?? '') === 'compliance';
}

/** Human-facing label for a role. The compliance (auditor) role is shown as "hrms" so the
 *  role name is not surfaced to the auditor; all other roles display as-is. */
function roleLabel(?string $role): string {
    return $role === 'compliance' ? 'hrms' : (string)$role;
}

/** Extra WHERE fragment limiting employee queries to compliance employees (for the compliance role). */
function complianceEmpFilter(string $alias = 'e'): string {
    return isCompliance() ? " AND {$alias}.Compliance = 1" : '';
}

/** Redirect a compliance user away from a page they may not use, to the first page they
 *  actually have permission for. Landing blindly on Employees loops with requirePermission()
 *  when the user's RBAC role excludes employees.view (→ back to index → back here → …). */
function blockCompliance(): void {
    if (!isCompliance()) return;
    $base = defined('BASE_URL') ? BASE_URL : '';
    if (can('employees.view'))              $target = '/modules/employees/index.php';
    elseif (can('report_attendance.view'))  $target = '/modules/reports/attendance.php';
    else                                    $target = '/modules/profile/index.php';
    header('Location: ' . $base . $target);
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_verify(): void {
    $sent  = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $valid = $_SESSION['_csrf'] ?? '';
    if (!$valid || !hash_equals($valid, $sent)) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'errors' => ['Invalid or missing CSRF token.']]));
    }
}

// For pages accessible to all roles; forces user-role to their assigned company.
function scopeCompanyId(int $requested = 0): int {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'user') return (int)($_SESSION['user_company_id'] ?? 0);
    return $requested;
}

// Module-level access permissions (roles for operator/compliance/user) — see permCatalog()
require_once __DIR__ . '/permissions.php';

/** Role-scoped list of companies the current user may work with (for the topbar switcher). */
function companiesForUser(PDO $db, array $user): array {
    if ($user['role'] === 'user') return [];
    if ($user['role'] === 'superadmin') {
        return $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
    }
    $stmt = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $stmt->execute([$user['scope_id']]);
    return $stmt->fetchAll();
}

/**
 * The globally selected company (set from the topbar switcher, kept in the session).
 * Validates the stored id against the user's accessible companies and auto-selects
 * the first one when nothing (or something stale) is stored. Role 'user' is always
 * pinned to their own company and never sees the switcher.
 */
function activeCompanyId(PDO $db, array $user): int {
    if ($user['role'] === 'user') return (int)$user['company_id'];
    $ids = array_map('intval', array_column(companiesForUser($db, $user), 'id'));
    $sel = (int)($_SESSION['active_company_id'] ?? 0);
    // A ?company= override (print pages / deep links) wins if it's one the user may access.
    $req = (int)($_GET['company'] ?? 0);
    if ($req && in_array($req, $ids, true)) return $req;
    if (!$sel || !in_array($sel, $ids, true)) {
        $sel = $ids[0] ?? 0;
        $_SESSION['active_company_id'] = $sel;
    }
    return $sel;
}
