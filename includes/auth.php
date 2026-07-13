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
        // An operator owns no companies of its own — it acts on its parent admin's companies —
        // so its scope_id is the parent admin. For every other role scope_id === id, which
        // keeps existing behaviour byte-for-byte identical.
        'scope_id'        => $role === 'operator' ? (int)$parent : (int)$id,
    ];
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
