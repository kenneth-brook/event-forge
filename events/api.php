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

$display = isset($_GET['display'])
    ? strtolower(trim((string) $_GET['display']))
    : '';

$limitProvided = isset($_GET['limit']) && trim((string) $_GET['limit']) !== '';

/*
 * Backwards-compatible behavior:
 * - /api.php with no display and no limit returns the full calendar feed.
 * - /api.php?limit=10 returns an upcoming-style limited feed for display components.
 * - /api.php?display=calendar returns the calendar feed.
 * - /api.php?display=upcoming&limit=10 returns upcoming events.
 * - /api.php?display=compact&limit=5 returns upcoming events with a compact display label.
 * - /api.php?display=wall&limit=12 returns upcoming events with an event wall display label.
 */
if ($display === '') {
    $display = $limitProvided ? 'upcoming' : 'calendar';
}

if ($display === 'event_wall') {
    $display = 'wall';
}

$allowedDisplays = ['calendar', 'upcoming', 'compact', 'wall'];

if (!in_array($display, $allowedDisplays, true)) {
    $display = 'calendar';
}

$limit = $limitProvided ? (int) $_GET['limit'] : 0;
$limit = $limit > 0 ? max(1, min(100, $limit)) : 0;

$includeCanceled = isset($_GET['include_canceled'])
    && in_array(strtolower((string) $_GET['include_canceled']), ['1', 'true', 'yes', 'on'], true);

$hidePastEvents = !isset($_GET['hide_past'])
    || in_array(strtolower((string) $_GET['hide_past']), ['1', 'true', 'yes', 'on'], true);

$keepCurrentMonth = !isset($_GET['keep_current_month'])
    || in_array(strtolower((string) $_GET['keep_current_month']), ['1', 'true', 'yes', 'on'], true);

$appVersion = eventforge_get_system_value($connection, 'app_version') ?? '';
$releaseChannel = eventforge_get_release_channel($connection);
$calendarTheme = eventforge_get_calendar_theme($connection);
$mapboxPublicToken = trim((string) (eventforge_get_system_value($connection, 'mapbox_public_token') ?? ''));

try {
    if ($display === 'calendar') {
        $events = eventforge_fetch_public_calendar_events(
            $connection,
            $hidePastEvents,
            $keepCurrentMonth,
            $limit
        );
    } else {
        $events = eventforge_fetch_display_events($connection, [
            'display' => $display,
            'limit' => $limit > 0 ? $limit : eventforge_default_display_limit($display),
            'include_canceled' => $includeCanceled,
        ]);
    }
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
        'display' => $display,
        'limit' => $limit,
        'include_canceled' => $includeCanceled,
    ],
], JSON_UNESCAPED_SLASHES);

exit;
