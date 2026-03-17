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
        header('Location: /event-forge/events/admin/login.php');
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

function require_admin(): void
{
    if (!is_admin()) {
        http_response_code(403);
        exit('Access denied.');
    }
}