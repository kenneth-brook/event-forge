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
require_once __DIR__ . '/../includes/external-events.php';

require_login();
eventforge_require_post_csrf();

if (!can_sync_external_events()) {
    http_response_code(403);
    exit('Access denied.');
}

try {
    eventforge_sync_external_events($connection);

    header('Location: ' . eventforge_admin_path('external-events.php') . '?status=synced');
    exit;
} catch (Throwable $e) {
    error_log('Event Forge external sync failed: ' . $e->getMessage());

    header('Location: ' . eventforge_admin_path('external-events.php') . '?status=sync-error');
    exit;
}
