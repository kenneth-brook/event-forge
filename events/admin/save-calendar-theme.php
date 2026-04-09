<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/installer.php';

if (!eventforge_is_installed()) {
    header('Location: ' . eventforge_admin_path('setup.php'));
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/theme.php';

require_login();

if (!eventforge_can_manage_calendar_theme($connection)) {
    http_response_code(403);
    exit('Access denied.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

foreach (eventforge_calendar_theme_definitions() as $key => $definition) {
    $rawValue = $_POST[$key] ?? '';
    $normalized = eventforge_normalize_hex_color((string) $rawValue);

    if ($normalized === null) {
        exit('Invalid color value provided for ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '.');
    }

    if (!eventforge_set_system_value($connection, $key, $normalized)) {
        exit('Failed to save calendar theme setting.');
    }
}

header('Location: ' . eventforge_admin_path('settings.php') . '?status=theme-saved');
exit;