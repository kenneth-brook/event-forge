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
require_admin();
eventforge_require_post_csrf();

try {
    $enabled = !empty($_POST['external_events_enabled']);
    $provider = trim((string) ($_POST['external_events_provider'] ?? 'chambermate'));
    $feedUrl = trim((string) ($_POST['external_events_feed_url'] ?? ''));

    eventforge_set_external_events_settings($connection, $enabled, $provider, $feedUrl);

    header('Location: ' . eventforge_admin_path('external-events.php') . '?status=settings-saved');
    exit;
} catch (Throwable $e) {
    error_log('Event Forge external settings save failed: ' . $e->getMessage());
    header('Location: ' . eventforge_admin_path('external-events.php') . '?status=settings-error');
    exit;
}
