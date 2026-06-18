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
    eventforge_set_system_value($connection, 'external_events_last_sync_error', '');

    eventforge_sync_external_events($connection);

    header('Location: ' . eventforge_admin_path('external-events.php') . '?status=synced');
    exit;
} catch (Throwable $e) {
    $message = trim($e->getMessage());

    if ($message === '') {
        $message = 'Unknown sync error.';
    }

    error_log('Event Forge external sync failed: ' . $message);

    eventforge_set_system_value($connection, 'external_events_last_sync_error', $message);

    header('Location: ' . eventforge_admin_path('external-events.php') . '?status=sync-error&sync_error=' . rawurlencode($message));
    exit;
}
