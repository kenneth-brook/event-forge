<?php
declare(strict_types=1);

require_once __DIR__ . '/installer.php';
require_once __DIR__ . '/system.php';
require_once __DIR__ . '/functions.php';

function eventforge_event_select_sql(): string
{
    return "
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
            e.latitude,
            e.longitude,
            e.summary,
            e.description,
            e.image_path,
            e.pdf_path,
            e.external_url,
            e.event_cost,
            e.is_canceled,
            e.category_id,
            c.name AS category_name,
            c.color AS category_color,
            c.font_color AS category_font_color
        FROM events e
        LEFT JOIN event_categories c ON e.category_id = c.id
    ";
}

function eventforge_build_event_detail_url(int $eventId, ?string $slug = null): string
{
    $url = eventforge_public_path('event.php') . '?id=' . $eventId;

    if ($slug !== null && trim($slug) !== '') {
        $url .= '&slug=' . urlencode($slug);
    }

    return $url;
}

function eventforge_build_event_view_url(
    mysqli $connection,
    int $eventId,
    ?string $slug = null,
    ?string $publicCalendarUrl = null
): string {
    $calendarUrl = $publicCalendarUrl !== null
        ? eventforge_build_public_event_url_from_base($publicCalendarUrl, $eventId, $slug)
        : eventforge_build_public_event_url($connection, $eventId, $slug);

    if ($calendarUrl !== '') {
        return $calendarUrl;
    }

    return eventforge_build_event_detail_url($eventId, $slug);
}

function eventforge_normalize_event_row(
    mysqli $connection,
    array $row,
    ?string $publicCalendarUrl = null
): array {
    $eventId = (int) $row['id'];
    $slug = !empty($row['slug']) ? (string) $row['slug'] : null;
    $detailUrl = eventforge_build_event_detail_url($eventId, $slug);
    $viewUrl = eventforge_build_event_view_url($connection, $eventId, $slug, $publicCalendarUrl);

    return [
        'id' => $eventId,
        'title' => (string) $row['title'],
        'start' => (string) $row['start_datetime'],
        'end' => !empty($row['end_datetime']) ? (string) $row['end_datetime'] : null,
        'allDay' => (bool) $row['all_day'],
        'url' => $detailUrl,
        'viewUrl' => $viewUrl,
        'extendedProps' => [
            'location' => $row['location'] ?? '',
            'addressLine1' => $row['address_line_1'] ?? '',
            'addressLine2' => $row['address_line_2'] ?? '',
            'addressCity' => $row['address_city'] ?? '',
            'addressState' => $row['address_state'] ?? '',
            'addressPostalCode' => $row['address_postal_code'] ?? '',
            'latitude' => $row['latitude'] !== null ? (float) $row['latitude'] : null,
            'longitude' => $row['longitude'] !== null ? (float) $row['longitude'] : null,
            'summary' => $row['summary'] ?? '',
            'description' => $row['description'] ?? '',
            'image' => $row['image_path'] ?? '',
            'pdf' => $row['pdf_path'] ?? '',
            'externalUrl' => $row['external_url'] ?? '',
            'cost' => $row['event_cost'] ?? '',
            'isCanceled' => (bool) ($row['is_canceled'] ?? 0),
            'categoryId' => $row['category_id'] ?? '',
            'categoryName' => $row['category_name'] ?? '',
            'categoryColor' => $row['category_color'] ?? '',
            'categoryFontColor' => $row['category_font_color'] ?? '',
            'viewUrl' => $viewUrl,
        ],
    ];
}

function eventforge_parse_calendar_display_datetime(?string $value): ?DateTimeImmutable
{
    if ($value === null) {
        return null;
    }

    $value = trim($value);

    if ($value === '') {
        return null;
    }

    try {
        return new DateTimeImmutable($value);
    } catch (Throwable $e) {
        return null;
    }
}

function eventforge_should_split_timed_multiday_display(array $row): bool
{
    if (!empty($row['all_day'])) {
        return false;
    }

    $start = eventforge_parse_calendar_display_datetime($row['start_datetime'] ?? null);
    $end = eventforge_parse_calendar_display_datetime($row['end_datetime'] ?? null);

    if (!$start || !$end) {
        return false;
    }

    return $start->format('Y-m-d') !== $end->format('Y-m-d');
}

function eventforge_expand_timed_multiday_display_row(array $row): array
{
    $start = eventforge_parse_calendar_display_datetime($row['start_datetime'] ?? null);
    $end = eventforge_parse_calendar_display_datetime($row['end_datetime'] ?? null);

    if (!$start || !$end || $start->format('Y-m-d') === $end->format('Y-m-d')) {
        return [$row];
    }

    $startTime = $start->format('H:i:s');
    $endTime = $end->format('H:i:s');

    $currentDate = new DateTimeImmutable($start->format('Y-m-d'));
    $lastDate = new DateTimeImmutable($end->format('Y-m-d'));

    $rows = [];

    while ($currentDate <= $lastDate) {
        $date = $currentDate->format('Y-m-d');

        $dailyRow = $row;
        $dailyRow['start_datetime'] = $date . ' ' . $startTime;
        $dailyRow['end_datetime'] = $date . ' ' . $endTime;
        $dailyRow['all_day'] = 0;

        $rows[] = $dailyRow;

        $currentDate = $currentDate->modify('+1 day');
    }

    return $rows;
}

function eventforge_normalize_event_row_for_calendar_display(
    mysqli $connection,
    array $row,
    ?string $publicCalendarUrl = null
): array {
    if (!eventforge_should_split_timed_multiday_display($row)) {
        return [
            eventforge_normalize_event_row($connection, $row, $publicCalendarUrl),
        ];
    }

    $events = [];

    foreach (eventforge_expand_timed_multiday_display_row($row) as $displayRow) {
        $events[] = eventforge_normalize_event_row($connection, $displayRow, $publicCalendarUrl);
    }

    return $events;
}

function eventforge_apply_event_result_limit(array $events, int $limit): array
{
    if ($limit <= 0 || count($events) <= $limit) {
        return $events;
    }

    return array_slice($events, 0, $limit);
}

function eventforge_default_display_limit(string $display): int
{
    switch ($display) {
        case 'compact':
            return 5;

        case 'wall':
            return 12;

        case 'upcoming':
        default:
            return 10;
    }
}

function eventforge_fetch_public_calendar_events(
    mysqli $connection,
    bool $hidePastEvents = true,
    bool $keepCurrentMonth = true,
    int $limit = 0
): array {
    $sql = eventforge_event_select_sql() . "
        WHERE e.is_published = 1
          AND (e.is_recurring_parent = 0 OR e.is_recurring_parent IS NULL)
    ";

    if ($hidePastEvents) {
        if ($keepCurrentMonth) {
            $sql .= " AND e.start_datetime >= DATE_FORMAT(CURDATE(), '%Y-%m-01')";
        } else {
            $sql .= " AND COALESCE(e.end_datetime, e.start_datetime) >= NOW()";
        }
    }

    $sql .= " ORDER BY e.start_datetime ASC, e.id ASC";

    if ($limit > 0) {
        $sql .= " LIMIT {$limit}";
    }

    $result = mysqli_query($connection, $sql);

    if (!$result) {
        throw new RuntimeException('Event query failed: ' . mysqli_error($connection));
    }

    $publicCalendarUrl = (string) (eventforge_get_system_value($connection, 'public_calendar_url') ?? '');
    $events = [];

    while ($row = mysqli_fetch_assoc($result)) {
        foreach (eventforge_normalize_event_row_for_calendar_display($connection, $row, $publicCalendarUrl) as $event) {
            $events[] = $event;
        }
    }

    mysqli_free_result($result);

    return eventforge_apply_event_result_limit($events, $limit);
}

function eventforge_fetch_display_events(mysqli $connection, array $options = []): array
{
    $display = isset($options['display'])
        ? strtolower(trim((string) $options['display']))
        : 'upcoming';

    if ($display === 'event_wall') {
        $display = 'wall';
    }

    if (!in_array($display, ['upcoming', 'compact', 'wall'], true)) {
        $display = 'upcoming';
    }

    $limit = isset($options['limit'])
        ? (int) $options['limit']
        : eventforge_default_display_limit($display);

    $limit = max(1, min(100, $limit));

    $includeCanceled = !empty($options['include_canceled']);

    $queryLimit = max(1, min(100, $limit * 2));

    $sql = eventforge_event_select_sql() . "
        WHERE e.is_published = 1
          AND (e.is_recurring_parent = 0 OR e.is_recurring_parent IS NULL)
          AND COALESCE(e.end_datetime, e.start_datetime) >= NOW()
    ";

    if (!$includeCanceled) {
        $sql .= " AND e.is_canceled = 0";
    }

    $sql .= "
        ORDER BY e.start_datetime ASC, e.id ASC
        LIMIT {$queryLimit}
    ";

    $result = mysqli_query($connection, $sql);

    if (!$result) {
        throw new RuntimeException('Display event query failed: ' . mysqli_error($connection));
    }

    $publicCalendarUrl = (string) (eventforge_get_system_value($connection, 'public_calendar_url') ?? '');
    $events = [];

    while ($row = mysqli_fetch_assoc($result)) {
        foreach (eventforge_normalize_event_row_for_calendar_display($connection, $row, $publicCalendarUrl) as $event) {
            $events[] = $event;

            if (count($events) >= $limit) {
                break 2;
            }
        }
    }

    mysqli_free_result($result);

    return eventforge_apply_event_result_limit($events, $limit);
}

/**
 * Legacy internal function name retained for older PHP includes inside a full-file upgrade.
 * Public display components should call /events/api.php, not /events/upcoming.php.
 */
function eventforge_fetch_upcoming_events(
    mysqli $connection,
    int $limit = 20,
    bool $includeCanceled = false
): array {
    return eventforge_fetch_display_events($connection, [
        'display' => 'upcoming',
        'limit' => $limit,
        'include_canceled' => $includeCanceled,
    ]);
}
