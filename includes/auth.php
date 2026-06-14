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
    return [
        'id'              => $_SESSION['user_id']              ?? 0,
        'name'            => $_SESSION['user_name']            ?? '',
        'role'            => $_SESSION['user_role']            ?? 'user',
        'company_limit'   => $_SESSION['user_company_limit']   ?? 1,
        'parent_admin_id' => $_SESSION['user_parent_admin_id'] ?? 0,
        'company_id'      => $_SESSION['user_company_id']      ?? 0,
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
