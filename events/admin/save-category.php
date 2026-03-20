<?php
declare(strict_types=1);

require __DIR__ . '/../includes/installer.php';

if (!eventforge_is_installed()) {
    header('Location: ' . eventforge_admin_path('setup.php'));
    exit;
}

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

require_login();

if (!can_manage_users()) {
    http_response_code(403);
    exit('Access denied.');
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$name = trim($_POST['name'] ?? '');
$color = trim($_POST['color'] ?? '');
$isActive = isset($_POST['is_active']) ? 1 : 0;

if ($name === '') {
    exit('Category name is required.');
}

$slug = strtolower($name);
$slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
$slug = trim((string) $slug, '-');
if ($slug === '') {
    $slug = 'category';
}

$nameEsc = mysqli_real_escape_string($connection, $name);
$slugEsc = mysqli_real_escape_string($connection, $slug);
$colorEsc = mysqli_real_escape_string($connection, $color);

if ($id > 0) {
    $sql = "
        UPDATE event_categories
        SET
            name = '{$nameEsc}',
            slug = '{$slugEsc}',
            color = " . ($color !== '' ? "'{$colorEsc}'" : "NULL") . ",
            is_active = {$isActive}
        WHERE id = {$id}
        LIMIT 1
    ";
} else {
    $sql = "
        INSERT INTO event_categories (
            name,
            slug,
            color,
            is_active
        ) VALUES (
            '{$nameEsc}',
            '{$slugEsc}',
            " . ($color !== '' ? "'{$colorEsc}'" : "NULL") . ",
            {$isActive}
        )
    ";
}

if (!mysqli_query($connection, $sql)) {
    exit('Category save failed: ' . mysqli_error($connection));
}

header('Location: ' . eventforge_admin_path('settings.php'));
exit;