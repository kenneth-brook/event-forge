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
        header('Location: /events/admin/login.php');
        exit;
    }
}