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

eventforge_require_post_csrf();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$name = trim($_POST['name'] ?? '');
$color = trim($_POST['color'] ?? '');
$fontColor = trim($_POST['font_color'] ?? '');
$isActive = isset($_POST['is_active']) ? 1 : 0;

if ($name === '') {
    exit('Category name is required.');
}

foreach (['Background' => $color, 'Font' => $fontColor] as $label => $value) {
    if ($value !== '' && !preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
        exit($label . ' color must be a valid hex color.');
    }
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
$fontColorEsc = mysqli_real_escape_string($connection, $fontColor);

$colorSql = $color !== '' ? "'{$colorEsc}'" : 'NULL';
$fontColorSql = $fontColor !== '' ? "'{$fontColorEsc}'" : 'NULL';

if ($id > 0) {
    $sql = "
        UPDATE event_categories
        SET
            name = '{$nameEsc}',
            slug = '{$slugEsc}',
            color = {$colorSql},
            font_color = {$fontColorSql},
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
            font_color,
            is_active
        ) VALUES (
            '{$nameEsc}',
            '{$slugEsc}',
            {$colorSql},
            {$fontColorSql},
            {$isActive}
        )
    ";
}

if (!mysqli_query($connection, $sql)) {
    error_log('Event Forge category save failed: ' . mysqli_error($connection));
    exit('Category save failed. Please try again.');
}

header('Location: ' . eventforge_admin_path('settings.php'));
exit;
