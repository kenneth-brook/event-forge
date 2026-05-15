<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/installer.php';

if (!eventforge_is_installed()) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Event Forge is not installed.');
}

require_once __DIR__ . '/includes/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Event not found.');
}

$sql = "
    SELECT
        e.id,
        e.title,
        e.slug,
        e.start_datetime,
        e.end_datetime,
        e.all_day,
        e.location,
        e.address_line_1,
        e.address_line_2,
        e.address_city,
        e.address_state,
        e.address_postal_code,
        e.summary,
        e.description,
        e.external_url,
        e.is_canceled,
        e.is_published,
        c.name AS category_name
    FROM events e
    LEFT JOIN event_categories c ON e.category_id = c.id
    WHERE e.id = {$id}
      AND e.is_published = 1
    LIMIT 1
";

$result = mysqli_query($connection, $sql);

if (!$result || !($event = mysqli_fetch_assoc($result))) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Event not found.');
}

function eventforge_ics_escape_text(?string $value): string
{
    $value = (string) $value;
    $value = str_replace("\\", "\\\\", $value);
    $value = str_replace(";", "\\;", $value);
    $value = str_replace(",", "\\,", $value);
    $value = str_replace(["\r\n", "\r", "\n"], "\\n", $value);

    return $value;
}

function eventforge_ics_fold_line(string $line): string
{
    $maxLength = 74;
    $folded = '';

    while (strlen($line) > $maxLength) {
        $folded .= substr($line, 0, $maxLength) . "\r\n ";
        $line = substr($line, $maxLength);
    }

    return $folded . $line;
}

function eventforge_ics_line(string $name, string $value): string
{
    return eventforge_ics_fold_line($name . ':' . $value);
}

function eventforge_ics_datetime(DateTimeInterface $date): string
{
    return $date->format('Ymd\THis');
}

function eventforge_ics_date(DateTimeInterface $date): string
{
    return $date->format('Ymd');
}

function eventforge_ics_build_location(array $event): string
{
    $parts = [];

    foreach ([
        'location',
        'address_line_1',
        'address_line_2',
    ] as $field) {
        $value = trim((string) ($event[$field] ?? ''));

        if ($value !== '') {
            $parts[] = $value;
        }
    }

    $city = trim((string) ($event['address_city'] ?? ''));
    $state = trim((string) ($event['address_state'] ?? ''));
    $postal = trim((string) ($event['address_postal_code'] ?? ''));

    $cityStatePostal = trim(implode(', ', array_filter([$city, $state])));

    if ($postal !== '') {
        $cityStatePostal = trim($cityStatePostal . ' ' . $postal);
    }

    if ($cityStatePostal !== '') {
        $parts[] = $cityStatePostal;
    }

    return implode(', ', $parts);
}

function eventforge_ics_safe_filename(string $title, int $id): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    $slug = trim((string) $slug, '-');

    if ($slug === '') {
        $slug = 'event-' . $id;
    }

    return $slug . '.ics';
}

$eventId = (int) $event['id'];
$title = trim((string) $event['title']);
$slug = trim((string) ($event['slug'] ?? ''));
$isAllDay = !empty($event['all_day']);
$isCanceled = !empty($event['is_canceled']);
$categoryName = trim((string) ($event['category_name'] ?? ''));

$startRaw = trim((string) ($event['start_datetime'] ?? ''));
$endRaw = trim((string) ($event['end_datetime'] ?? ''));

try {
    $start = new DateTimeImmutable($startRaw);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Event start date is invalid.');
}

$end = null;

if ($endRaw !== '') {
    try {
        $end = new DateTimeImmutable($endRaw);
    } catch (Throwable $e) {
        $end = null;
    }
}

if ($isAllDay) {
    if (!$end || $end <= $start) {
        $end = $start->modify('+1 day');
    }
} else {
    if (!$end || $end <= $start) {
        $end = $start->modify('+1 hour');
    }
}

$publicEventPath = eventforge_public_path('event.php') . '?id=' . $eventId;

if ($slug !== '') {
    $publicEventPath .= '&slug=' . urlencode($slug);
}

$eventUrl = eventforge_absolute_url($publicEventPath);

$descriptionParts = [];

$summary = trim((string) ($event['summary'] ?? ''));
$description = trim((string) ($event['description'] ?? ''));
$externalUrl = trim((string) ($event['external_url'] ?? ''));

if ($summary !== '') {
    $descriptionParts[] = $summary;
}

if ($description !== '' && $description !== $summary) {
    $descriptionParts[] = $description;
}

$descriptionParts[] = 'Event details: ' . $eventUrl;

if ($externalUrl !== '') {
    $descriptionParts[] = 'More info: ' . $externalUrl;
}

$location = eventforge_ics_build_location($event);
$filename = eventforge_ics_safe_filename($title, $eventId);
$host = preg_replace('/[^a-z0-9.-]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? 'event-forge.local'));
$uid = 'eventforge-' . $eventId . '@' . ($host !== '' ? $host : 'event-forge.local');

$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//Event Forge//Event Forge v0.6.4//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    eventforge_ics_line('UID', $uid),
    eventforge_ics_line('DTSTAMP', gmdate('Ymd\THis\Z')),
    eventforge_ics_line('SUMMARY', eventforge_ics_escape_text($title)),
];

if ($isAllDay) {
    $lines[] = eventforge_ics_fold_line('DTSTART;VALUE=DATE:' . eventforge_ics_date($start));
    $lines[] = eventforge_ics_fold_line('DTEND;VALUE=DATE:' . eventforge_ics_date($end));
} else {
    $lines[] = eventforge_ics_line('DTSTART', eventforge_ics_datetime($start));
    $lines[] = eventforge_ics_line('DTEND', eventforge_ics_datetime($end));
}

if ($location !== '') {
    $lines[] = eventforge_ics_line('LOCATION', eventforge_ics_escape_text($location));
}

$lines[] = eventforge_ics_line(
    'DESCRIPTION',
    eventforge_ics_escape_text(implode("\n\n", $descriptionParts))
);

$lines[] = eventforge_ics_line('URL', eventforge_ics_escape_text($eventUrl));

if ($categoryName !== '') {
    $lines[] = eventforge_ics_line('CATEGORIES', eventforge_ics_escape_text($categoryName));
}

$lines[] = eventforge_ics_line('STATUS', $isCanceled ? 'CANCELLED' : 'CONFIRMED');
$lines[] = 'END:VEVENT';
$lines[] = 'END:VCALENDAR';

$ics = implode("\r\n", $lines) . "\r\n";

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
header('Content-Length: ' . strlen($ics));

echo $ics;
exit;