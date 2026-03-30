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

require __DIR__ . '/includes/db.php';

$result = mysqli_query($connection, "
    SELECT id, name, color, font_color
    FROM event_categories
    WHERE is_active = 1
    ORDER BY name ASC
");

if (!$result) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Category query failed',
        'details' => mysqli_error($connection),
    ]);
    exit;
}

$items = [];

while ($row = mysqli_fetch_assoc($result)) {
    $items[] = [
        'id' => (int) $row['id'],
        'name' => (string) ($row['name'] ?? ''),
        'color' => (string) ($row['color'] ?? ''),
        'fontColor' => (string) ($row['font_color'] ?? ''),
    ];
}

echo json_encode($items, JSON_UNESCAPED_SLASHES);
exit;