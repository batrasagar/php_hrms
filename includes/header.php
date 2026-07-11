<?php
if (!defined('BASE_URL')) define('BASE_URL', '');
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
$user = currentUser();
$ap   = $activePage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="<?= csrf_token() ?>">
<title><?= htmlspecialchars($pageTitle ?? 'HRMS') ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
<style>
/* ── Apple Design System ─────────────────────────────────────────────── */
:root {
  --blue:      #0071e3;
  --blue-dk:   #0058b0;
  --blue-lt:   rgba(0,113,227,.11);
  --bg:        #f5f5f7;
  --surface:   #ffffff;
  --border:    rgba(0,0,0,.09);
  --text:      #1d1d1f;
  --text-2:    #6e6e73;
  --text-3:    #aeaeb2;
  --success:   #34c759;
  --warning:   #ff9f0a;
  --danger:    #ff3b30;
  --sidebar-w: 256px;
  --sidebar-c: 64px;
  --topbar-h:  52px;
  --radius:    12px;
  --ease:      .28s cubic-bezier(.25,.46,.45,.94);
}

/* ── Reset / Base ────────────────────────────────────────────────────── */
*,*::before,*::after { box-sizing: border-box; }
html,body { height: 100%; }
body {
  font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Helvetica Neue", Arial, sans-serif;
  font-size: 14px;
  color: var(--text);
  background: var(--bg);
  -webkit-font-smoothing: antialiased;
}

/* ── Sidebar ─────────────────────────────────────────────────────────── */
.app-sidebar {
  position: fixed;
  inset: 0 auto 0 0;
  width: var(--sidebar-w);
  background: rgba(255,255,255,.94);
  backdrop-filter: saturate(200%) blur(24px);
  -webkit-backdrop-filter: saturate(200%) blur(24px);
  border-right: .5px solid var(--border);
  display: flex;
  flex-direction: column;
  z-index: 1000;
  transition: width var(--ease), transform var(--ease);
  overflow: hidden;
  will-change: width, transform;
}

/* Collapsed desktop */
body.sb-collapsed .app-sidebar { width: var(--sidebar-c); }

/* Mobile hidden by default */
@media (max-width: 991.98px) {
  .app-sidebar {
    transform: translateX(calc(-1 * var(--sidebar-w)));
    box-shadow: none;
    width: var(--sidebar-w) !important;
  }
  body.sb-open .app-sidebar {
    transform: translateX(0);
    box-shadow: 0 8px 40px rgba(0,0,0,.18);
  }
}

/* ── Backdrop ────────────────────────────────────────────────────────── */
.sb-backdrop {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.35);
  z-index: 999;
  backdrop-filter: blur(2px);
  -webkit-backdrop-filter: blur(2px);
  animation: fadeIn .22s ease;
}
body.sb-open .sb-backdrop { display: block; }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }

/* ── Sidebar Brand ───────────────────────────────────────────────────── */
.sb-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 18px 16px 14px;
  text-decoration: none;
  border-bottom: .5px solid var(--border);
  flex-shrink: 0;
  min-width: 0;
}
.sb-brand-icon {
  width: 34px; height: 34px;
  background: var(--blue);
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  color: #fff;
  font-size: 17px;
  flex-shrink: 0;
}
.sb-brand-text { overflow: hidden; white-space: nowrap; transition: opacity var(--ease), width var(--ease); }
.sb-brand-name { font-size: 16px; font-weight: 700; color: var(--text); letter-spacing: -.02em; }
.sb-brand-sub  { font-size: 11px; color: var(--text-2); }
body.sb-collapsed .sb-brand-text { opacity: 0; width: 0; }

/* ── Sidebar Nav ─────────────────────────────────────────────────────── */
.sb-nav { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 10px 8px; scrollbar-width: none; }
.sb-nav::-webkit-scrollbar { display: none; }

.sb-section-label {
  font-size: 10px;
  font-weight: 600;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: .08em;
  padding: 12px 10px 4px;
  white-space: nowrap;
  overflow: hidden;
  transition: opacity var(--ease);
}
body.sb-collapsed .sb-section-label { opacity: 0; }

/* Nav item */
.sb-item {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 8px 10px;
  border-radius: 9px;
  cursor: pointer;
  text-decoration: none;
  color: var(--text);
  transition: background .15s, color .15s;
  white-space: nowrap;
  position: relative;
  min-width: 0;
  font-size: 14px;
  font-weight: 400;
  user-select: none;
}
.sb-item:hover { background: rgba(0,0,0,.05); color: var(--text); }
.sb-item.active { background: var(--blue-lt); color: var(--blue); font-weight: 500; }
.sb-item.sb-featured {
  background: var(--blue-lt);
  color: var(--blue) !important;
  font-weight: 600;
  border-left: 3px solid var(--blue);
  border-radius: 9px;
  margin-bottom: 4px;
}
.sb-item.sb-featured:hover { background: rgba(0,113,227,.18); color: var(--blue) !important; }
.sb-item.sb-featured .sb-item-icon { color: var(--blue); }

.sb-item-icon {
  width: 22px; height: 22px;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px;
  flex-shrink: 0;
}
.sb-item-label { flex: 1; overflow: hidden; transition: opacity var(--ease), width var(--ease); }
.sb-item-chevron { font-size: 10px; color: var(--text-3); margin-left: auto; flex-shrink: 0; transition: transform .2s, opacity var(--ease); }
.sb-item[aria-expanded="true"] .sb-item-chevron { transform: rotate(90deg); }

body.sb-collapsed .sb-item-label,
body.sb-collapsed .sb-item-chevron { opacity: 0; width: 0; overflow: hidden; }
body.sb-collapsed .sb-item { justify-content: center; padding: 8px; }
body.sb-collapsed .sb-item-icon { margin: 0; }

/* Tooltip on collapsed */
body.sb-collapsed .sb-item[data-tip]::after {
  content: attr(data-tip);
  position: absolute;
  left: calc(var(--sidebar-c) + 8px);
  top: 50%; transform: translateY(-50%);
  background: #1d1d1f;
  color: #fff;
  font-size: 12px;
  padding: 5px 10px;
  border-radius: 7px;
  white-space: nowrap;
  opacity: 0;
  pointer-events: none;
  transition: opacity .15s;
  z-index: 1001;
}
body.sb-collapsed .sb-item[data-tip]:hover::after { opacity: 1; }

/* Sub-items */
.sb-sub { padding: 2px 0 4px 31px; }
.sb-sub-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  border-radius: 7px;
  text-decoration: none;
  color: var(--text-2);
  font-size: 13px;
  transition: background .15s, color .15s;
  white-space: nowrap;
}
.sb-sub-item:hover { background: rgba(0,0,0,.05); color: var(--text); }
.sb-sub-item.active { color: var(--blue); font-weight: 500; background: var(--blue-lt); }
.sb-sub-item i { font-size: 13px; width: 16px; flex-shrink: 0; }
body.sb-collapsed .sb-sub { display: none !important; }
body.sb-collapsed .collapse.show { display: none !important; }

/* ── Sidebar Footer ──────────────────────────────────────────────────── */
.sb-footer {
  border-top: .5px solid var(--border);
  padding: 10px 8px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 9px;
  min-width: 0;
}
.sb-avatar {
  width: 30px; height: 30px;
  border-radius: 50%;
  background: var(--blue);
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  text-transform: uppercase;
}
.sb-user-info { flex: 1; overflow: hidden; transition: opacity var(--ease); }
.sb-user-name { font-size: 13px; font-weight: 500; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sb-user-role { font-size: 11px; color: var(--text-2); }
.sb-logout {
  width: 28px; height: 28px;
  border-radius: 7px;
  border: none;
  background: transparent;
  color: var(--text-2);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: background .15s, color .15s;
  flex-shrink: 0;
}
.sb-logout:hover { background: rgba(255,59,48,.1); color: var(--danger); }
body.sb-collapsed .sb-user-info { opacity: 0; width: 0; overflow: hidden; }
body.sb-collapsed .sb-footer { justify-content: center; }
.sb-avatar.ring-active { box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--blue); }

/* ── Main wrapper ────────────────────────────────────────────────────── */
.app-main {
  margin-left: var(--sidebar-w);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  transition: margin-left var(--ease);
}
body.sb-collapsed .app-main { margin-left: var(--sidebar-c); }
@media (max-width: 991.98px) { .app-main { margin-left: 0 !important; } }

/* ── Topbar ──────────────────────────────────────────────────────────── */
.app-topbar {
  position: sticky;
  top: 0;
  height: var(--topbar-h);
  background: rgba(255,255,255,.88);
  backdrop-filter: saturate(200%) blur(20px);
  -webkit-backdrop-filter: saturate(200%) blur(20px);
  border-bottom: .5px solid var(--border);
  z-index: 800;
  display: flex;
  align-items: center;
  padding: 0 20px;
  gap: 12px;
}
.topbar-toggle {
  width: 32px; height: 32px;
  border: none;
  background: transparent;
  border-radius: 8px;
  color: var(--text-2);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  font-size: 16px;
  transition: background .15s, color .15s;
  flex-shrink: 0;
}
.topbar-toggle:hover { background: rgba(0,0,0,.07); color: var(--text); }
.topbar-title { font-size: 16px; font-weight: 600; color: var(--text); flex: 1; letter-spacing: -.01em; }
.topbar-profile-btn {
  border: none;
  background: transparent;
  padding: 0;
  cursor: pointer;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
}
.topbar-avatar {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: var(--blue);
  color: #fff;
  font-size: 13px;
  font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  letter-spacing: 0;
  flex-shrink: 0;
  transition: opacity .15s;
}
.topbar-profile-btn:hover .topbar-avatar { opacity: .85; }

/* ── Content ─────────────────────────────────────────────────────────── */
.app-content { flex: 1; padding: 24px; }
@media (max-width: 767.98px) { .app-content { padding: 16px; } }
@media (max-width: 575.98px) { .app-content { padding: 12px; } }

/* ── Card overrides ──────────────────────────────────────────────────── */
.card {
  border-radius: var(--radius) !important;
  border: .5px solid var(--border) !important;
  box-shadow: 0 2px 8px rgba(0,0,0,.07), 0 0 1px rgba(0,0,0,.04) !important;
  background: var(--surface) !important;
}
.card-header {
  background: var(--surface) !important;
  border-bottom: .5px solid var(--border) !important;
  border-radius: var(--radius) var(--radius) 0 0 !important;
  padding: 14px 20px !important;
  font-size: 14px;
  font-weight: 600;
  letter-spacing: -.01em;
}
.card-body { padding: 20px !important; }
.card-body.p-0 { padding: 0 !important; }
.card-body.py-2 { padding-top: 10px !important; padding-bottom: 10px !important; }
.card-footer {
  background: var(--bg) !important;
  border-top: .5px solid var(--border) !important;
  border-radius: 0 0 var(--radius) var(--radius) !important;
  padding: 12px 20px !important;
}

/* ── Table overrides ─────────────────────────────────────────────────── */
.table { font-size: 13.5px; }
.table th {
  font-weight: 600;
  font-size: 11.5px;
  color: var(--text-2);
  text-transform: uppercase;
  letter-spacing: .05em;
}
.table-light { background-color: var(--bg) !important; }
.table-light th { background-color: var(--bg) !important; }
/* Regular table cells */
.table:not(.table-sm) > :not(caption) > * > * { padding: 12px 14px !important; }
/* List table-sm cells — slightly compact but still readable */
.table-sm > :not(caption) > * > * { padding: 10px 14px !important; }

/* ── Button overrides ────────────────────────────────────────────────── */
.btn {
  border-radius: 8px !important;
  font-size: 13.5px !important;
  font-weight: 500 !important;
  letter-spacing: -.01em;
  transition: all .18s !important;
}
.btn-primary { background: var(--blue) !important; border-color: var(--blue) !important; }
.btn-primary:hover { background: var(--blue-dk) !important; border-color: var(--blue-dk) !important; }
.btn-sm { border-radius: 7px !important; padding: 5px 12px !important; font-size: 12.5px !important; }
.btn-outline-primary  { color: var(--blue) !important; border-color: var(--blue) !important; }
.btn-outline-primary:hover  { background: var(--blue) !important; color: #fff !important; }

/* ── Form overrides ──────────────────────────────────────────────────── */
.form-control, .form-select {
  border-radius: 8px !important;
  border: 1px solid rgba(0,0,0,.18) !important;
  font-size: 14px !important;
  padding: 8px 12px !important;
  background: var(--surface) !important;
  color: var(--text) !important;
  line-height: 1.5;
}
.form-control:focus, .form-select:focus {
  border-color: var(--blue) !important;
  box-shadow: 0 0 0 3px rgba(0,113,227,.15) !important;
  outline: none;
}
.form-control-sm, .form-select-sm {
  border-radius: 7px !important;
  font-size: 13px !important;
  padding: 5px 10px !important;
}
.form-label { font-size: 13px; font-weight: 500; color: var(--text); margin-bottom: 6px; }
.form-text  { font-size: 11.5px; color: var(--text-2); margin-top: 4px; }
.mb-3 { margin-bottom: 1.1rem !important; }
.row.g-3 { --bs-gutter-y: 1.1rem; }

/* ── Badge overrides ─────────────────────────────────────────────────── */
.badge {
  border-radius: 6px !important;
  font-weight: 500 !important;
  font-size: 11px !important;
  padding: 3px 8px !important;
  letter-spacing: .01em;
}
.bg-success { background-color: var(--success) !important; }
.bg-danger  { background-color: var(--danger)  !important; }
.bg-warning { background-color: var(--warning) !important; }

/* ── Alert overrides ─────────────────────────────────────────────────── */
.alert {
  border-radius: var(--radius) !important;
  border: .5px solid transparent !important;
  font-size: 13.5px !important;
  padding: 12px 16px !important;
}
.alert-info    { background: #e8f3ff !important; border-color: #b3d4ff !important; color: #004a99 !important; }
.alert-success { background: #edfaf1 !important; border-color: #a8e8bc !important; color: #1a7a3a !important; }
.alert-danger  { background: #fff0ee !important; border-color: #ffb3ad !important; color: #b82a20 !important; }
.alert-warning { background: #fff8e6 !important; border-color: #ffe4a0 !important; color: #7a5500 !important; }

/* ── DataTables overrides ────────────────────────────────────────────── */
.dataTables_wrapper { font-size: 13px; }
.dataTables_wrapper .dataTables_filter label { font-weight: 500; color: var(--text-2); }
.dataTables_wrapper .dataTables_filter input {
  border-radius: 8px !important;
  border: 1px solid rgba(0,0,0,.16) !important;
  padding: 6px 12px !important;
  font-size: 13px !important;
  margin-left: 6px !important;
}
.dataTables_wrapper .dataTables_length select {
  border-radius: 7px !important;
  border: 1px solid rgba(0,0,0,.16) !important;
  padding: 5px 10px !important;
  font-size: 13px !important;
}
.dataTables_wrapper .dataTables_info { font-size: 12px; color: var(--text-2); }
.paginate_button { border-radius: 7px !important; font-size: 12px !important; }
.paginate_button.current { background: var(--blue) !important; color: #fff !important; border-color: var(--blue) !important; }

/* ── Mobile table scroll ─────────────────────────────────────────────── */
/* Tables inside p-0 card bodies scroll horizontally on narrow screens */
.card-body.p-0 { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* ── DataTables mobile ───────────────────────────────────────────────── */
@media (max-width: 575.98px) {
  .dataTables_wrapper .dataTables_length,
  .dataTables_wrapper .dataTables_filter { width: 100%; text-align: left; }
  .dataTables_wrapper .dataTables_filter input { width: calc(100% - 52px); margin-top: 4px; }
  .dataTables_wrapper .dataTables_info,
  .dataTables_wrapper .dataTables_paginate { text-align: center; width: 100%; margin-top: 8px; }
}

/* ── Misc ────────────────────────────────────────────────────────────── */
code { background: rgba(0,0,0,.06); border-radius: 5px; padding: 1px 6px; color: #c0392b; font-size: 12px; }
.sticky-top { top: 0; z-index: 1; }
hr { border-color: var(--border); opacity: 1; }
h1,h2,h3,h4,h5,h6 { letter-spacing: -.02em; }
.text-muted { color: var(--text-2) !important; }
/* Prevent wide fixed-width elements from overflowing on mobile */
@media (max-width: 575.98px) {
  [style*="max-width"] { max-width: 100% !important; }
  .card[style*="max-width"] { max-width: 100% !important; }
}

/* ── Tom-Select overrides ────────────────────────────────────────────── */
.ts-wrapper { width: 100%; }
.ts-control {
  border-radius: 8px !important;
  border: 1px solid rgba(0,0,0,.18) !important;
  background-color: var(--surface) !important;
  color: var(--text) !important;
  font-size: 14px !important;
  padding: 7px 32px 7px 12px !important;
  min-height: unset !important;
  cursor: pointer !important;
  box-shadow: none !important;
  flex-wrap: nowrap;
}
.ts-wrapper.form-select-sm .ts-control {
  font-size: 13px !important;
  padding: 4px 28px 4px 10px !important;
  border-radius: 7px !important;
}
.ts-wrapper.focus .ts-control {
  border-color: var(--blue) !important;
  box-shadow: 0 0 0 3px rgba(0,113,227,.15) !important;
}
.ts-wrapper:not(.has-items) .ts-control input::placeholder { color: var(--text-3); }
.ts-control input { color: var(--text) !important; font-size: inherit !important; }
.ts-dropdown {
  border-radius: 10px !important;
  border: .5px solid rgba(0,0,0,.14) !important;
  box-shadow: 0 8px 28px rgba(0,0,0,.11), 0 1px 4px rgba(0,0,0,.07) !important;
  font-size: 14px !important;
  overflow: hidden !important;
  z-index: 9999 !important;
  background: var(--surface) !important;
}
.ts-dropdown-content { max-height: 220px !important; }
.ts-dropdown .option {
  padding: 7px 14px !important;
  color: var(--text) !important;
  transition: background .1s !important;
  border-radius: 0 !important;
}
.ts-dropdown .option:hover,
.ts-dropdown .option.active {
  background: var(--blue-lt) !important;
  color: var(--blue) !important;
}
.ts-dropdown .option.selected {
  background: var(--blue) !important;
  color: #fff !important;
  font-weight: 500 !important;
}
.ts-dropdown .option.selected.active {
  background: var(--blue-dk) !important;
  color: #fff !important;
}
.ts-dropdown input.dropdown-input {
  border-radius: 0 !important;
  border-width: 0 0 1px !important;
  border-color: var(--border) !important;
  font-size: 13px !important;
  padding: 8px 12px !important;
  background: var(--surface) !important;
  color: var(--text) !important;
}
.ts-dropdown input.dropdown-input:focus {
  box-shadow: none !important;
  border-color: var(--blue) !important;
}
/* multi-select item chips */
.ts-control .item {
  border-radius: 6px !important;
  background: var(--blue-lt) !important;
  color: var(--blue) !important;
  font-size: 12px !important;
  font-weight: 500 !important;
  padding: 2px 6px !important;
}
.ts-control .item .remove {
  color: var(--blue) !important;
  opacity: .6;
  padding: 0 0 0 4px !important;
}
.ts-control .item .remove:hover { opacity: 1; color: var(--danger) !important; }
</style>
</head>
<body>

<!-- Backdrop (mobile) -->
<div class="sb-backdrop" id="sbBackdrop"></div>

<!-- Sidebar -->
<aside class="app-sidebar" id="appSidebar">

  <!-- Brand -->
  <a class="sb-brand" href="<?= BASE_URL ?>/index.php">
    <div class="sb-brand-icon"><i class="bi bi-building-fill"></i></div>
    <div class="sb-brand-text">
      <div class="sb-brand-name">HRMS</div>
      <div class="sb-brand-sub">HR Management</div>
    </div>
  </a>

  <!-- Nav -->
  <nav class="sb-nav" id="sbNav">
    <ul class="list-unstyled mb-0">

<?php if ($user['role'] === 'superadmin'): ?>
      <div class="sb-section-label">Administration</div>
      <li>
        <a class="sb-item <?= $ap==='approvals'?'active':'' ?>"
           href="<?= BASE_URL ?>/modules/approvals/index.php"
           data-tip="Approvals">
          <span class="sb-item-icon"><i class="bi bi-person-check"></i></span>
          <span class="sb-item-label">Approvals</span>
        </a>
      </li>
      <li>
        <a class="sb-item <?= $ap==='users'?'active':'' ?>"
           href="<?= BASE_URL ?>/modules/users/list.php"
           data-tip="Users">
          <span class="sb-item-icon"><i class="bi bi-people"></i></span>
          <span class="sb-item-label">Users</span>
        </a>
      </li>
      <li>
        <a class="sb-item <?= $ap==='companies'?'active':'' ?>"
           href="<?= BASE_URL ?>/modules/companies/index.php"
           data-tip="Companies">
          <span class="sb-item-icon"><i class="bi bi-buildings"></i></span>
          <span class="sb-item-label">Companies</span>
        </a>
      </li>
<?php endif; ?>

<?php if (in_array($user['role'], ['superadmin','admin'])): ?>
<?php
  $empOpen     = in_array($ap, ['employees','emp_import','emp_bulk','print']) ? 'show' : '';
  $shiftOpen   = in_array($ap, ['shifts','shift_defaults','shift_assign','shift_cyclic','compoff']) ? 'show' : '';
  $masterOpen  = in_array($ap, ['holidays']) ? 'show' : '';
  $attnOpen    = in_array($ap, ['overtime','leaves','leave_types','leave_policy','leave_assign','leave_register']) ? 'show' : '';
  $rptOpen     = in_array($ap, ['report_active','report_attendance','report_monthly','report_swipe','report_strength','report_ot','report_leave']) ? 'show' : '';
  $settingsUrl = BASE_URL . '/modules/settings/index.php';
  $punchOpen   = in_array($ap, ['punchlog','punch_correction','punch_sync']) ? 'show' : '';
  $devOpen     = in_array($ap, ['devices','device_enrollment']) ? 'show' : '';
  $payrollOpen = in_array($ap, ['payroll_settings','payroll_components','payroll_emp_setup','payroll_run','payroll_bank']) ? 'show' : '';
  $advanceUrl  = BASE_URL . '/modules/advance/index.php';
  $notifUrl    = BASE_URL . '/modules/notifications/index.php';
  $docsOpen    = in_array($ap, ['doc_templates','doc_issue','doc_material','doc_fnf','doc_seed']) ? 'show' : '';
?>

      <!-- Attendance Report -->
      <li>
        <a class="sb-item sb-featured"
           href="<?= BASE_URL ?>/modules/reports/attendance.php"
           data-tip="Attendance Report">
          <span class="sb-item-icon"><i class="bi bi-calendar2-week"></i></span>
          <span class="sb-item-label">Attendance Report</span>
        </a>
      </li>

      <!-- Employees -->
      <div class="sb-section-label">Employees</div>
      <li>
        <a class="sb-item" data-bs-toggle="collapse" href="#navEmp"
           aria-expanded="<?= $empOpen?'true':'false' ?>" data-tip="Employees">
          <span class="sb-item-icon"><i class="bi bi-person-vcard"></i></span>
          <span class="sb-item-label">Employees</span>
          <span class="sb-item-chevron"><i class="bi bi-chevron-right"></i></span>
        </a>
        <div class="collapse <?= $empOpen ?>" id="navEmp">
          <div class="sb-sub">
            <a class="sb-sub-item <?= $ap==='employees'?'active':'' ?>" href="<?= BASE_URL ?>/modules/employees/index.php">
              <i class="bi bi-list-ul"></i> All Employees
            </a>
            <a class="sb-sub-item <?= $ap==='emp_import'?'active':'' ?>" href="<?= BASE_URL ?>/modules/employees/import.php">
              <i class="bi bi-upload"></i> Import CSV
            </a>
            <a class="sb-sub-item <?= $ap==='emp_bulk'?'active':'' ?>" href="<?= BASE_URL ?>/modules/employees/bulk_edit.php">
              <i class="bi bi-table"></i> Bulk Edit
            </a>
            <a class="sb-sub-item <?= $ap==='print'?'active':'' ?>" href="<?= BASE_URL ?>/modules/print/index.php">
              <i class="bi bi-printer"></i> Print / iCard
            </a>
          </div>
        </div>
      </li>

      <!-- Tools -->
      <div class="sb-section-label">Tools</div>
      <li>
        <a class="sb-item" data-bs-toggle="collapse" href="#navPunch"
           aria-expanded="<?= $punchOpen?'true':'false' ?>" data-tip="Punch Log">
          <span class="sb-item-icon"><i class="bi bi-clock-history"></i></span>
          <span class="sb-item-label">Punch Log</span>
          <span class="sb-item-chevron"><i class="bi bi-chevron-right"></i></span>
        </a>
        <div class="collapse <?= $punchOpen ?>" id="navPunch">
          <div class="sb-sub">
            <a class="sb-sub-item <?= $ap==='punch_sync'?'active':'' ?>" href="<?= BASE_URL ?>/modules/punchlog/sync.php">
              <i class="bi bi-arrow-repeat"></i> Sync &amp; Process
            </a>
            <a class="sb-sub-item <?= $ap==='punchlog'?'active':'' ?>" href="<?= BASE_URL ?>/modules/punchlog/view.php">
              <i class="bi bi-list-columns"></i> View Log
            </a>
            <a class="sb-sub-item <?= $ap==='punch_correction'?'active':'' ?>" href="<?= BASE_URL ?>/modules/punchlog/correction.php">
              <i class="bi bi-pencil-square"></i> Correction
            </a>
          </div>
        </div>
      </li>

      <!-- Shifts -->
      <div class="sb-section-label">Shifts</div>
      <li>
        <a class="sb-item" data-bs-toggle="collapse" href="#navShifts"
           aria-expanded="<?= $shiftOpen?'true':'false' ?>" data-tip="Shift Management">
          <span class="sb-item-icon"><i class="bi bi-clock-history"></i></span>
          <span class="sb-item-label">Shift Management</span>
          <span class="sb-item-chevron"><i class="bi bi-chevron-right"></i></span>
        </a>
        <div class="collapse <?= $shiftOpen ?>" id="navShifts">
          <div class="sb-sub">
            <a class="sb-sub-item <?= $ap==='shifts'?'active':'' ?>" href="<?= BASE_URL ?>/modules/shifts/index.php">
              <i class="bi bi-clock"></i> Shift Master
            </a>
            <a class="sb-sub-item <?= $ap==='shift_defaults'?'active':'' ?>" href="<?= BASE_URL ?>/modules/shifts/defaults.php">
              <i class="bi bi-magic"></i> Default Shifts
            </a>
            <a class="sb-sub-item <?= $ap==='shift_assign'?'active':'' ?>" href="<?= BASE_URL ?>/modules/shifts/assign.php">
              <i class="bi bi-person-gear"></i> Shift Assignment
            </a>
            <a class="sb-sub-item <?= $ap==='shift_cyclic'?'active':'' ?>" href="<?= BASE_URL ?>/modules/shifts/cyclic.php">
              <i class="bi bi-arrow-repeat"></i> Cyclic Shifts
            </a>
            <a class="sb-sub-item <?= $ap==='compoff'?'active':'' ?>" href="<?= BASE_URL ?>/modules/shifts/compoff.php">
              <i class="bi bi-calendar-check"></i> Comp Off
            </a>
          </div>
        </div>
      </li>

      <!-- Attendance -->
      <div class="sb-section-label">Attendance</div>
      <li>
        <a class="sb-item" data-bs-toggle="collapse" href="#navAttn"
           aria-expanded="<?= $attnOpen?'true':'false' ?>" data-tip="Attendance">
          <span class="sb-item-icon"><i class="bi bi-calendar-check"></i></span>
          <span class="sb-item-label">Attendance</span>
          <span class="sb-item-chevron"><i class="bi bi-chevron-right"></i></span>
        </a>
        <div class="collapse <?= $attnOpen ?>" id="navAttn">
          <div class="sb-sub">
            <a class="sb-sub-item <?= $ap==='overtime'?'active':'' ?>" href="<?= BASE_URL ?>/modules/overtime/index.php">
              <i class="bi bi-alarm-fill"></i> Overtime
            </a>
            <a class="sb-sub-item <?= $ap==='leaves'?'active':'' ?>" href="<?= BASE_URL ?>/modules/leaves/index.php">
              <i class="bi bi-calendar-x"></i> Mark Leaves
            </a>
            <a class="sb-sub-item <?= $ap==='leave_types'?'active':'' ?>" href="<?= BASE_URL ?>/modules/leaves/types.php">
              <i class="bi bi-tags"></i> Leave Types
            </a>
            <a class="sb-sub-item <?= $ap==='leave_policy'?'active':'' ?>" href="<?= BASE_URL ?>/modules/leaves/policy.php">
              <i class="bi bi-file-earmark-ruled"></i> Leave Policies
            </a>
            <a class="sb-sub-item <?= $ap==='leave_assign'?'active':'' ?>" href="<?= BASE_URL ?>/modules/leaves/assign.php">
              <i class="bi bi-person-check"></i> Assign Policy
            </a>
            <a class="sb-sub-item <?= $ap==='leave_register'?'active':'' ?>" href="<?= BASE_URL ?>/modules/leaves/register.php">
              <i class="bi bi-journal-check"></i> Leave Register
            </a>
          </div>
        </div>
      </li>

      <!-- Reports -->
      <div class="sb-section-label">Reports</div>
      <li>
        <a class="sb-item" data-bs-toggle="collapse" href="#navReports"
           aria-expanded="<?= $rptOpen ? 'true' : 'false' ?>" data-tip="Reports">
          <span class="sb-item-icon"><i class="bi bi-bar-chart-line"></i></span>
          <span class="sb-item-label">Reports</span>
          <span class="sb-item-chevron"><i class="bi bi-chevron-right"></i></span>
        </a>
        <div class="collapse <?= $rptOpen ?>" id="navReports">
          <div class="sb-sub">
            <a class="sb-sub-item <?= $ap==='report_active'?'active':'' ?>" href="<?= BASE_URL ?>/modules/reports/active_employees.php">
              <i class="bi bi-people"></i> Active Employees
            </a>
            <a class="sb-sub-item <?= $ap==='report_attendance'?'active':'' ?>" href="<?= BASE_URL ?>/modules/reports/attendance.php">
              <i class="bi bi-calendar2-week"></i> Attendance
            </a>
            <a class="sb-sub-item <?= $ap==='report_monthly'?'active':'' ?>" href="<?= BASE_URL ?>/modules/reports/monthly_attendance.php">
              <i class="bi bi-calendar-month"></i> Monthly
            </a>
            <a class="sb-sub-item <?= $ap==='report_swipe'?'active':'' ?>" href="<?= BASE_URL ?>/modules/reports/swipe_report.php">
              <i class="bi bi-fingerprint"></i> Swipe Report
            </a>
            <a class="sb-sub-item <?= $ap==='report_strength'?'active':'' ?>" href="<?= BASE_URL ?>/modules/reports/strength_summary.php">
              <i class="bi bi-diagram-3"></i> Strength
            </a>
            <a class="sb-sub-item <?= $ap==='report_ot'?'active':'' ?>" href="<?= BASE_URL ?>/modules/reports/ot_report.php">
              <i class="bi bi-alarm"></i> OT Report
            </a>
            <a class="sb-sub-item <?= $ap==='report_leave'?'active':'' ?>" href="<?= BASE_URL ?>/modules/reports/leave_report.php">
              <i class="bi bi-file-earmark-x"></i> Leave Report
            </a>
          </div>
        </div>
      </li>

      <!-- Payroll -->
      <div class="sb-section-label">Payroll</div>
      <li>
        <a class="sb-item" data-bs-toggle="collapse" href="#navPayroll"
           aria-expanded="<?= $payrollOpen?'true':'false' ?>" data-tip="Payroll">
          <span class="sb-item-icon"><i class="bi bi-cash-stack"></i></span>
          <span class="sb-item-label">Payroll</span>
          <span class="sb-item-chevron"><i class="bi bi-chevron-right"></i></span>
        </a>
        <div class="collapse <?= $payrollOpen ?>" id="navPayroll">
          <div class="sb-sub">
            <a class="sb-sub-item <?= $ap==='payroll_run'?'active':'' ?>" href="<?= BASE_URL ?>/modules/payroll/run.php">
              <i class="bi bi-play-circle"></i> Run / Salary Sheet
            </a>
            <a class="sb-sub-item <?= $ap==='payroll_emp_setup'?'active':'' ?>" href="<?= BASE_URL ?>/modules/payroll/employee_setup.php">
              <i class="bi bi-person-gear"></i> Employee Setup
            </a>
            <a class="sb-sub-item <?= $ap==='payroll_components'?'active':'' ?>" href="<?= BASE_URL ?>/modules/payroll/components.php">
              <i class="bi bi-list-columns-reverse"></i> Income / Deduction Heads
            </a>
            <a class="sb-sub-item <?= $ap==='payroll_settings'?'active':'' ?>" href="<?= BASE_URL ?>/modules/payroll/settings.php">
              <i class="bi bi-sliders"></i> PF / ESI / TDS Settings
            </a>
          </div>
        </div>
      </li>
      <li>
        <a class="sb-item <?= $ap==='advance'?'active':'' ?>" href="<?= $advanceUrl ?>" data-tip="Employee Advances">
          <span class="sb-item-icon"><i class="bi bi-cash-coin"></i></span>
          <span class="sb-item-label">Employee Advances</span>
        </a>
      </li>

      <!-- HR Documents -->
      <div class="sb-section-label">HR Documents</div>
      <li>
        <a class="sb-item" data-bs-toggle="collapse" href="#navDocs"
           aria-expanded="<?= $docsOpen?'true':'false' ?>" data-tip="HR Documents">
          <span class="sb-item-icon"><i class="bi bi-file-earmark-text"></i></span>
          <span class="sb-item-label">HR Documents</span>
          <span class="sb-item-chevron"><i class="bi bi-chevron-right"></i></span>
        </a>
        <div class="collapse <?= $docsOpen ?>" id="navDocs">
          <div class="sb-sub">
            <a class="sb-sub-item <?= $ap==='doc_templates'?'active':'' ?>" href="<?= BASE_URL ?>/modules/docs/templates.php">
              <i class="bi bi-file-ruled"></i> Document Templates
            </a>
            <a class="sb-sub-item <?= $ap==='doc_issue'?'active':'' ?>" href="<?= BASE_URL ?>/modules/docs/issue.php">
              <i class="bi bi-file-earmark-arrow-up"></i> Issue Document
            </a>
            <a class="sb-sub-item <?= $ap==='doc_material'?'active':'' ?>" href="<?= BASE_URL ?>/modules/docs/material.php">
              <i class="bi bi-box-seam"></i> Issued Material
            </a>
            <a class="sb-sub-item <?= $ap==='doc_fnf'?'active':'' ?>" href="<?= BASE_URL ?>/modules/docs/fnf.php">
              <i class="bi bi-clipboard-check"></i> Full &amp; Final
            </a>
            <a class="sb-sub-item <?= $ap==='doc_seed'?'active':'' ?>" href="<?= BASE_URL ?>/modules/docs/seed_templates.php">
              <i class="bi bi-stars"></i> Sample Templates
            </a>
          </div>
        </div>
      </li>
      <?php if ($user['role'] === 'superadmin'): ?>
      <li>
        <a class="sb-item <?= $ap==='docs_pipeline'?'active':'' ?>"
           href="<?= BASE_URL ?>/modules/docs/pipeline.php" data-tip="Pipeline Docs">
          <span class="sb-item-icon"><i class="bi bi-journal-code"></i></span>
          <span class="sb-item-label">Pipeline Docs</span>
        </a>
      </li>
      <?php endif; ?>

      <!-- Settings -->
      <div class="sb-section-label">Settings</div>
      <li>
        <a class="sb-item <?= $ap==='notifications'?'active':'' ?>"
           href="<?= $notifUrl ?>" data-tip="Email Notifications">
          <span class="sb-item-icon"><i class="bi bi-envelope-check"></i></span>
          <span class="sb-item-label">Email Notifications</span>
        </a>
      </li>

      <!-- Masters -->
      <div class="sb-section-label">Masters</div>
      <li>
        <a class="sb-item" data-bs-toggle="collapse" href="#navMasters"
           aria-expanded="<?= $masterOpen?'true':'false' ?>" data-tip="Masters">
          <span class="sb-item-icon"><i class="bi bi-database-gear"></i></span>
          <span class="sb-item-label">Masters</span>
          <span class="sb-item-chevron"><i class="bi bi-chevron-right"></i></span>
        </a>
        <div class="collapse <?= $masterOpen ?>" id="navMasters">
          <div class="sb-sub">
            <a class="sb-sub-item <?= $ap==='holidays'?'active':'' ?>" href="<?= BASE_URL ?>/modules/holidays/index.php">
              <i class="bi bi-calendar-heart"></i> Holidays
            </a>
          </div>
        </div>
      </li>


<?php else: // role === 'user' ?>
<?php
  $rptOpenU = in_array($ap, ['report_monthly','report_ot','report_leave','report_strength']) ? 'show' : '';
?>
      <li>
        <a class="sb-item sb-featured"
           href="<?= BASE_URL ?>/modules/reports/attendance.php"
           data-tip="Attendance Report">
          <span class="sb-item-icon"><i class="bi bi-calendar2-week"></i></span>
          <span class="sb-item-label">Attendance Report</span>
        </a>
      </li>
      <div class="sb-section-label">Reports</div>
      <li>
        <a class="sb-item" data-bs-toggle="collapse" href="#navReportsU"
           aria-expanded="<?= $rptOpenU ? 'true' : 'false' ?>" data-tip="Reports">
          <span class="sb-item-icon"><i class="bi bi-bar-chart-line"></i></span>
          <span class="sb-item-label">Reports</span>
          <span class="sb-item-chevron"><i class="bi bi-chevron-right"></i></span>
        </a>
        <div class="collapse <?= $rptOpenU ?>" id="navReportsU">
          <div class="sb-sub">
            <a class="sb-sub-item <?= $ap==='report_monthly'?'active':'' ?>" href="<?= BASE_URL ?>/modules/reports/monthly_attendance.php">
              <i class="bi bi-calendar-month"></i> Monthly
            </a>
            <a class="sb-sub-item <?= $ap==='report_strength'?'active':'' ?>" href="<?= BASE_URL ?>/modules/reports/strength_summary.php">
              <i class="bi bi-diagram-3"></i> Strength
            </a>
            <a class="sb-sub-item <?= $ap==='report_ot'?'active':'' ?>" href="<?= BASE_URL ?>/modules/reports/ot_report.php">
              <i class="bi bi-alarm"></i> OT Report
            </a>
            <a class="sb-sub-item <?= $ap==='report_leave'?'active':'' ?>" href="<?= BASE_URL ?>/modules/reports/leave_report.php">
              <i class="bi bi-file-earmark-x"></i> Leave Report
            </a>
          </div>
        </div>
      </li>
      <div class="sb-section-label">Organisation</div>
      <li>
        <a class="sb-item <?= $ap==='employees'?'active':'' ?>"
           href="<?= BASE_URL ?>/modules/employees/index.php" data-tip="Employees">
          <span class="sb-item-icon"><i class="bi bi-person-vcard"></i></span>
          <span class="sb-item-label">Employees</span>
        </a>
      </li>
      <div class="sb-section-label">Tools</div>
      <li>
        <a class="sb-item <?= $ap==='punchlog'?'active':'' ?>"
           href="<?= BASE_URL ?>/modules/punchlog/view.php" data-tip="Punch Log">
          <span class="sb-item-icon"><i class="bi bi-clock-history"></i></span>
          <span class="sb-item-label">Punch Log</span>
        </a>
      </li>
<?php endif; ?>

    </ul>
  </nav>

  <!-- Footer -->
  <div class="sb-footer">
    <a href="<?= BASE_URL ?>/modules/profile/index.php" class="d-flex align-items-center gap-2 text-decoration-none flex-1 min-width-0" style="min-width:0;flex:1;">
      <div class="sb-avatar <?= $ap==='profile'?'ring-active':'' ?>"><?= mb_substr($user['name'], 0, 1) ?></div>
      <div class="sb-user-info">
        <div class="sb-user-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="sb-user-role"><?= htmlspecialchars($user['role']) ?></div>
      </div>
    </a>
    <a href="<?= BASE_URL ?>/logout.php" class="sb-logout" title="Sign out">
      <i class="bi bi-box-arrow-right"></i>
    </a>
  </div>

</aside>

<!-- Main -->
<div class="app-main" id="appMain">

  <!-- Topbar -->
  <header class="app-topbar">
    <!-- Desktop: collapse toggle; Mobile: hamburger -->
    <button class="topbar-toggle d-none d-lg-flex" id="desktopToggle" title="Toggle sidebar">
      <i class="bi bi-layout-sidebar"></i>
    </button>
    <button class="topbar-toggle d-flex d-lg-none" id="mobileToggle" title="Menu">
      <i class="bi bi-list" style="font-size:20px"></i>
    </button>
    <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? '') ?></div>

    <!-- Profile dropdown -->
    <div class="dropdown" style="flex-shrink:0">
      <button class="topbar-profile-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Profile">
        <span class="topbar-avatar"><?= strtoupper(substr($user['Name'] ?? 'U', 0, 1)) ?></span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width:210px;border-radius:10px;border:.5px solid var(--border)">
        <li class="px-3 pt-2 pb-1">
          <div class="fw-semibold" style="font-size:13px"><?= htmlspecialchars($user['Name'] ?? '') ?></div>
          <div class="text-muted" style="font-size:11px"><?= htmlspecialchars($user['Email'] ?? '') ?></div>
          <span class="badge mt-1" style="font-size:10px;background:var(--blue-lt);color:var(--blue);text-transform:capitalize"><?= htmlspecialchars($user['role']) ?></span>
        </li>
        <li><hr class="dropdown-divider my-1"></li>
        <li><a class="dropdown-item d-flex align-items-center gap-2" href="<?= BASE_URL ?>/modules/profile/index.php">
          <i class="bi bi-person-circle text-primary"></i> Profile
        </a></li>
        <li><a class="dropdown-item d-flex align-items-center gap-2" href="<?= $settingsUrl ?>">
          <i class="bi bi-gear text-secondary"></i> Settings
        </a></li>
        <?php if ($user['role'] === 'superadmin'): ?>
        <li><a class="dropdown-item d-flex align-items-center gap-2" href="<?= BASE_URL ?>/modules/adms_credentials/index.php">
          <i class="bi bi-plug text-secondary"></i> ADMS Credentials
        </a></li>
        <?php endif; ?>
        <li><hr class="dropdown-divider my-1"></li>
        <li><a class="dropdown-item d-flex align-items-center gap-2" href="<?= BASE_URL ?>/logout.php">
          <i class="bi bi-box-arrow-right text-danger"></i> Logout
        </a></li>
      </ul>
    </div>
  </header>

  <!-- Page Content -->
  <main class="app-content">
