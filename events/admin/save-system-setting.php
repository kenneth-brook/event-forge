<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/installer.php';

if (!eventforge_is_installed()) {
    header('Location: ' . eventforge_admin_path('setup.php'));
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/system.php';

require_login();
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$settingsGroup = trim((string) ($_POST['settings_group'] ?? ''));

if ($settingsGroup === 'map-settings') {
    $mapboxPublicToken = trim((string) ($_POST['mapbox_public_token'] ?? ''));
    $mapboxGeocodingToken = trim((string) ($_POST['mapbox_geocoding_token'] ?? ''));

    if (!eventforge_set_system_value($connection, 'mapbox_public_token', $mapboxPublicToken)) {
        exit('Failed to save public token.');
    }

    if (!eventforge_set_system_value($connection, 'mapbox_geocoding_token', $mapboxGeocodingToken)) {
        exit('Failed to save geocoding token.');
    }

    header('Location: ' . eventforge_admin_path('settings.php') . '?status=map-saved');
    exit;
}

$settingKey = trim($_POST['setting_key'] ?? '');
$settingValue = trim((string) ($_POST['setting_value'] ?? ''));

$allowedKeys = [
    'public_calendar_url',
    'permissions_allow_staff_manager_calendar_theme',
];

if (!in_array($settingKey, $allowedKeys, true)) {
    exit('Invalid setting key.');
}

if ($settingKey === 'public_calendar_url') {
    if ($settingValue !== '' && filter_var($settingValue, FILTER_VALIDATE_URL) === false) {
        exit('Invalid URL.');
    }

    if (!eventforge_set_system_value($connection, $settingKey, $settingValue)) {
        exit('Failed to save setting.');
    }

    header('Location: ' . eventforge_admin_path('settings.php') . '?status=general-saved');
    exit;
}

if ($settingKey === 'permissions_allow_staff_manager_calendar_theme') {
    $flagValue = in_array($settingValue, ['1', 'true', 'yes', 'on'], true);

    if (!eventforge_set_system_flag($connection, $settingKey, $flagValue)) {
        exit('Failed to save setting.');
    }

    header('Location: ' . eventforge_admin_path('settings.php') . '?status=permissions-saved');
    exit;
}

exit('Unhandled setting key.');