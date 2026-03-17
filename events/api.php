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
        id,
        title,
        start_datetime,
        end_datetime,
        all_day,
        location,
        summary,
        description,
        image_path,
        pdf_path,
        external_url,
        is_canceled
    FROM events
    WHERE is_published = 1
      AND (is_recurring_parent = 0 OR is_recurring_parent IS NULL)
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
        ],
    ];
}

echo json_encode($events, JSON_UNESCAPED_SLASHES);
exit;