<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/includes/installer.php';

if (!eventforge_is_installed()) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Event Forge is not installed.',
    ]);
    exit;
}

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/system.php';

$hidePastEvents = true;
$keepCurrentMonth = true;

$appVersion = eventforge_get_system_value($connection, 'app_version') ?? '';

$sql = "
    SELECT
        e.id,
        e.title,
        e.slug,
        e.start_datetime,
        e.end_datetime,
        e.all_day,
        e.location,
        e.summary,
        e.description,
        e.image_path,
        e.pdf_path,
        e.external_url,
        e.is_canceled,
        e.category_id,
        c.name AS category_name,
        c.color AS category_color,
        c.font_color AS category_font_color
    FROM events e
    LEFT JOIN event_categories c ON e.category_id = c.id
    WHERE e.is_published = 1
      AND (e.is_recurring_parent = 0 OR e.is_recurring_parent IS NULL)
";

if ($hidePastEvents) {
    if ($keepCurrentMonth) {
        $sql .= " AND e.start_datetime >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
    } else {
        $sql .= " AND e.start_datetime >= NOW()";
    }
}

$sql .= " ORDER BY e.start_datetime ASC";

$result = mysqli_query($connection, $sql);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Query failed',
        'details' => mysqli_error($connection),
    ]);
    exit;
}

$events = [];

while ($row = mysqli_fetch_assoc($result)) {
    $events[] = [
        'id' => (int) $row['id'],
        'title' => (string) $row['title'],
        'start' => (string) $row['start_datetime'],
        'end' => !empty($row['end_datetime']) ? (string) $row['end_datetime'] : null,
        'allDay' => (bool) $row['all_day'],
        'url' => eventforge_public_path('event.php') . '?id=' . (int) $row['id'] . (!empty($row['slug']) ? '&slug=' . urlencode((string) $row['slug']) : ''),
        'extendedProps' => [
            'location' => $row['location'] ?? '',
            'summary' => $row['summary'] ?? '',
            'description' => $row['description'] ?? '',
            'image' => $row['image_path'] ?? '',
            'pdf' => $row['pdf_path'] ?? '',
            'externalUrl' => $row['external_url'] ?? '',
            'isCanceled' => (bool) ($row['is_canceled'] ?? 0),
            'categoryId' => $row['category_id'] ?? '',
            'categoryName' => $row['category_name'] ?? '',
            'categoryColor' => $row['category_color'] ?? '',
            'categoryFontColor' => $row['category_font_color'] ?? '',
        ],
    ];
}

echo json_encode([
    'events' => $events,
    'meta' => [
        'app_version' => $appVersion,
    ],
], JSON_UNESCAPED_SLASHES);

exit;