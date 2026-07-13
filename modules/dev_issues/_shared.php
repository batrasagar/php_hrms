<?php
// Shared constants + helpers for the Development Issue Log.

const DI_STATUSES      = ['PENDING','HOLD','RE-OPEN','CLOSE','CLOSE-VERIFIED','APPROVED','REJECTED'];
const DI_USER_STATUSES = ['PENDING','HOLD','RE-OPEN','CLOSE','CLOSE-VERIFIED','REJECTED']; // inline-selectable (APPROVED is superadmin-only)
const DI_CLOSED        = ['CLOSE','CLOSE-VERIFIED'];                                       // stamp ClosedAt
const DI_DELETABLE_BLOCK = ['APPROVED','CLOSE','CLOSE-VERIFIED','REJECTED'];               // cannot delete in these states

$GLOBALS['DI_BADGE'] = [
    'PENDING'        => 'background:#fef3c7;color:#92400e',
    'HOLD'           => 'background:#ede9fe;color:#6d28d9',
    'RE-OPEN'        => 'background:#fee2e2;color:#b91c1c',
    'CLOSE'          => 'background:#dcfce7;color:#166534',
    'CLOSE-VERIFIED' => 'background:#a7f3d0;color:#065f46',
    'APPROVED'       => 'background:#dbeafe;color:#1e40af',
    'REJECTED'       => 'background:#ffe4e6;color:#9f1239',
];
function di_badge(string $status): string {
    return $GLOBALS['DI_BADGE'][$status] ?? 'background:#e2e8f0;color:#475569';
}

/** Human elapsed time between report and closure, e.g. "3d 5h", "2h 10m", "8m". */
function di_time_taken(?string $created, ?string $closed): string {
    if (!$created || !$closed) return '—';
    $mins = (int) round((strtotime($closed) - strtotime($created)) / 60);
    if ($mins < 1) return '<1m';
    $d = intdiv($mins, 1440); $h = intdiv($mins % 1440, 60); $m = $mins % 60;
    $parts = [];
    if ($d) $parts[] = $d . 'd';
    if ($h) $parts[] = $h . 'h';
    if ($m && !$d) $parts[] = $m . 'm';   // drop minutes once in days, for brevity
    return $parts ? implode(' ', $parts) : '<1m';
}

/** Company ids the user may see. Returns ['*'] for superadmin (all companies). */
function di_scope_ids(PDO $db, array $user): array {
    if ($user['role'] === 'superadmin') return ['*'];
    if ($user['role'] === 'user')       return [(int)($user['company_id'] ?? 0)];
    $c = $db->prepare("SELECT id FROM tblCompany WHERE AdminId=? AND IsActive=1");
    $c->execute([$user['scope_id']]);
    return array_map('intval', array_column($c->fetchAll(), 'id'));
}
function di_can_access(array $scopeIds, int $companyId): bool {
    return $scopeIds === ['*'] || in_array($companyId, $scopeIds, true);
}

/** Companies for the picker (id,Name) in the user's scope. */
function di_scope_companies(PDO $db, array $user): array {
    if ($user['role'] === 'superadmin') {
        return $db->query("SELECT id, Name FROM tblCompany WHERE IsActive=1 ORDER BY Name")->fetchAll();
    }
    if ($user['role'] === 'user') {
        $c = $db->prepare("SELECT id, Name FROM tblCompany WHERE id=?");
        $c->execute([(int)($user['company_id'] ?? 0)]);
        return $c->fetchAll();
    }
    $c = $db->prepare("SELECT id, Name FROM tblCompany WHERE AdminId=? AND IsActive=1 ORDER BY Name");
    $c->execute([$user['scope_id']]);
    return $c->fetchAll();
}
