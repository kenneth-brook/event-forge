<?php
declare(strict_types=1);

session_start();

function is_logged_in(): bool
{
    return !empty($_SESSION['events_admin_logged_in']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        require_once __DIR__ . '/installer.php';
        header('Location: ' . eventforge_admin_path('login.php'));
        exit;
    }
}

function current_admin_username(): string
{
    return (string) ($_SESSION['events_admin_username'] ?? '');
}

function current_admin_role(): string
{
    return (string) ($_SESSION['events_admin_role'] ?? 'staff');
}

function is_admin(): bool
{
    return current_admin_role() === 'admin';
}

function is_staff_manager(): bool
{
    return current_admin_role() === 'staff_manager';
}

function can_manage_users(): bool
{
    return in_array(current_admin_role(), ['staff_manager', 'admin'], true);
}

function can_create_staff_accounts(): bool
{
    return in_array(current_admin_role(), ['staff_manager', 'admin'], true);
}

function can_create_staff_manager_accounts(): bool
{
    return is_admin();
}

function can_manage_staff_accounts(): bool
{
    return in_array(current_admin_role(), ['staff_manager', 'admin'], true);
}

function can_manage_admin_accounts(): bool
{
    return is_admin();
}

function require_admin(): void
{
    if (!is_admin()) {
        http_response_code(403);
        exit('Access denied.');
    }
}