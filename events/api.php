<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/installer.php';

if (!eventforge_is_installed()) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Event Forge is not installed.',
    ]);
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/system.php';
require_once __DIR__ . '/includes/theme.php';
require_once __DIR__ . '/includes/event-data.php';

$hidePastEvents = true;
$keepCurrentMonth = true;

$appVersion = eventforge_get_system_value($connection, 'app_version') ?? '';
$releaseChannel = eventforge_get_release_channel($connection);
$calendarTheme = eventforge_get_calendar_theme($connection);
$mapboxPublicToken = trim((string) (eventforge_get_system_value($connection, 'mapbox_public_token') ?? ''));

try {
    $events = eventforge_fetch_public_calendar_events(
        $connection,
        $hidePastEvents,
        $keepCurrentMonth
    );
} catch (Throwable $e) {
    error_log('Event Forge API query failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Unable to load events.',
    ]);
    exit;
}

echo json_encode([
    'events' => $events,
    'meta' => [
        'app_version' => $appVersion,
        'release_channel' => $releaseChannel,
        'calendar_theme' => $calendarTheme,
        'calendar_theme_css_variables' => eventforge_calendar_theme_to_css_variables($calendarTheme),
        'mapbox_public_token' => $mapboxPublicToken,
    ],
], JSON_UNESCAPED_SLASHES);

exit;
