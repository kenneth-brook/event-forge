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

$settingKey = trim($_POST['setting_key'] ?? '');
$settingValue = trim($_POST['setting_value'] ?? '');

$allowedKeys = [
    'public_calendar_url',
];

if (!in_array($settingKey, $allowedKeys, true)) {
    exit('Invalid setting key.');
}

if (!eventforge_set_system_value($connection, $settingKey, $settingValue)) {
    exit('Failed to save setting.');
}

header('Location: ' . eventforge_admin_path('settings.php'));
exit;