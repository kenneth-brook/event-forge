<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/includes/db.php';

$hidePastEvents = true;
$keepCurrentMonth = true;

$sql = "
    SELECT
        e.id,
        e.title,
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
        c.color AS category_color
    FROM events e
    LEFT JOIN event_categories c ON e.category_id = c.id
    WHERE e.is_published = 1
      AND (e.is_recurring_parent = 0 OR e.is_recurring_parent IS NULL)
";

if ($hidePastEvents) {
    if ($keepCurrentMonth) {
        $sql .= " AND start_datetime >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
    } else {
        $sql .= " AND start_datetime >= NOW()";
    }
}

$sql .= " ORDER BY start_datetime ASC";

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
        'title' => $row['title'],
        'start' => $row['start_datetime'],
        'end' => !empty($row['end_datetime']) ? $row['end_datetime'] : null,
        'allDay' => (bool) $row['all_day'],
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
        ],
    ];
}

echo json_encode($events, JSON_UNESCAPED_SLASHES);
exit;