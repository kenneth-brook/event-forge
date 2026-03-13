<?php
declare(strict_types=1);

function eventforge_weekday_map(): array
{
    return [
        'SU' => 0,
        'MO' => 1,
        'TU' => 2,
        'WE' => 3,
        'TH' => 4,
        'FR' => 5,
        'SA' => 6,
    ];
}

function eventforge_parse_time_parts(string $datetime): array
{
    $ts = strtotime($datetime);

    return [
        'hour' => (int) date('H', $ts),
        'minute' => (int) date('i', $ts),
        'second' => (int) date('s', $ts),
    ];
}

function eventforge_build_datetime(string $date, array $timeParts): string
{
    return sprintf(
        '%s %02d:%02d:%02d',
        $date,
        $timeParts['hour'],
        $timeParts['minute'],
        $timeParts['second']
    );
}

function eventforge_nth_weekday_of_month(int $year, int $month, string $weekOfMonth, string $dayOfWeek): ?string
{
    $weekdayMap = eventforge_weekday_map();

    if (!isset($weekdayMap[$dayOfWeek])) {
        return null;
    }

    $targetWeekday = $weekdayMap[$dayOfWeek];
    $weekOfMonth = strtolower($weekOfMonth);

    if ($weekOfMonth === 'last') {
        $lastDay = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $lastDay = $lastDay->modify('last day of this month');

        while ((int) $lastDay->format('w') !== $targetWeekday) {
            $lastDay = $lastDay->modify('-1 day');
        }

        return $lastDay->format('Y-m-d');
    }

    $weekMap = [
        'first' => 1,
        'second' => 2,
        'third' => 3,
        'fourth' => 4,
    ];

    if (!isset($weekMap[$weekOfMonth])) {
        return null;
    }

    $targetOccurrence = $weekMap[$weekOfMonth];
    $date = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
    $count = 0;

    while ((int) $date->format('m') === $month) {
        if ((int) $date->format('w') === $targetWeekday) {
            $count++;

            if ($count === $targetOccurrence) {
                return $date->format('Y-m-d');
            }
        }

        $date = $date->modify('+1 day');
    }

    return null;
}

function eventforge_delete_future_children(mysqli $connection, int $parentId): void
{
    $parentId = (int) $parentId;

    $sql = "
        DELETE FROM events
        WHERE parent_event_id = {$parentId}
        AND recurrence_instance_date IS NOT NULL
        AND (is_independent_child = 0 OR is_independent_child IS NULL)
    ";

    mysqli_query($connection, $sql);
}

function eventforge_insert_child_event(mysqli $connection, array $parent, string $instanceDate): void
{
    $title = mysqli_real_escape_string($connection, (string) $parent['title']);
    $location = mysqli_real_escape_string($connection, (string) ($parent['location'] ?? ''));
    $summary = mysqli_real_escape_string($connection, (string) ($parent['summary'] ?? ''));
    $description = mysqli_real_escape_string($connection, (string) ($parent['description'] ?? ''));
    $imagePath = mysqli_real_escape_string($connection, (string) ($parent['image_path'] ?? ''));
    $pdfPath = mysqli_real_escape_string($connection, (string) ($parent['pdf_path'] ?? ''));
    $externalUrl = mysqli_real_escape_string($connection, (string) ($parent['external_url'] ?? ''));

    $startTime = eventforge_parse_time_parts((string) $parent['start_datetime']);
    $startDatetime = eventforge_build_datetime($instanceDate, $startTime);
    $startEsc = mysqli_real_escape_string($connection, $startDatetime);

    $endSql = 'NULL';

    if (!empty($parent['end_datetime'])) {
        $parentStartTs = strtotime((string) $parent['start_datetime']);
        $parentEndTs = strtotime((string) $parent['end_datetime']);
        $durationSeconds = $parentEndTs - $parentStartTs;

        $instanceStart = new DateTimeImmutable($startDatetime);
        $instanceEnd = $instanceStart->modify('+' . $durationSeconds . ' seconds');
        $endSql = "'" . mysqli_real_escape_string($connection, $instanceEnd->format('Y-m-d H:i:s')) . "'";
    }

    $imageSql = $imagePath !== '' ? "'" . $imagePath . "'" : 'NULL';
    $pdfSql = $pdfPath !== '' ? "'" . $pdfPath . "'" : 'NULL';
    $externalSql = $externalUrl !== '' ? "'" . $externalUrl . "'" : 'NULL';
    
    $parentId = (int) $parent['id'];
    $instanceDateEsc = mysqli_real_escape_string($connection, $instanceDate);
    
    $checkSql = "
        SELECT id
        FROM events
        WHERE parent_event_id = {$parentId}
        AND recurrence_instance_date = '{$instanceDateEsc}'
        LIMIT 1
    ";

    $checkResult = mysqli_query($connection, $checkSql);

    if ($checkResult && mysqli_num_rows($checkResult) > 0) {
        return;
    }

    $allDay = !empty($parent['all_day']) ? 1 : 0;
    $isPublished = !empty($parent['is_published']) ? 1 : 0;

    $sql = "
        INSERT INTO events (
            parent_event_id,
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
            is_published,
            recurrence_instance_date
        ) VALUES (
            {$parentId},
            '{$title}',
            '{$startEsc}',
            {$endSql},
            {$allDay},
            '{$location}',
            '{$summary}',
            '{$description}',
            {$imageSql},
            {$pdfSql},
            {$externalSql},
            {$isPublished},
            '{$instanceDateEsc}'
        )
    ";

    mysqli_query($connection, $sql);
}

function eventforge_generate_weekly(mysqli $connection, array $parent): void
{
    $daysRaw = trim((string) ($parent['recurrence_days'] ?? ''));

    if ($daysRaw === '') {
        return;
    }

    $dayCodes = array_filter(array_map('trim', explode(',', strtoupper($daysRaw))));
    $weekdayMap = eventforge_weekday_map();

    $start = new DateTimeImmutable(date('Y-m-d', strtotime((string) $parent['start_datetime'])));
    $today = new DateTimeImmutable(date('Y-m-d'));
    $horizon = $today->modify('+1 year');

    $ruleEnd = !empty($parent['recurrence_end_date'])
        ? new DateTimeImmutable((string) $parent['recurrence_end_date'])
        : $horizon;

    $endDate = $ruleEnd < $horizon ? $ruleEnd : $horizon;

    $interval = max(1, (int) ($parent['recurrence_interval'] ?? 1));

    $cursor = $start;
    while ($cursor <= $endDate) {
        $daysSinceStart = (int) $start->diff($cursor)->format('%a');
        $weekIndex = intdiv($daysSinceStart, 7);

        if ($weekIndex % $interval === 0) {
            $weekday = (int) $cursor->format('w');

            foreach ($dayCodes as $code) {
                if (isset($weekdayMap[$code]) && $weekdayMap[$code] === $weekday) {
                    eventforge_insert_child_event($connection, $parent, $cursor->format('Y-m-d'));
                    break;
                }
            }
        }

        $cursor = $cursor->modify('+1 day');
    }
}

function eventforge_generate_monthly_nth(mysqli $connection, array $parent): void
{
    $weekOfMonth = strtoupper(trim((string) ($parent['recurrence_week_of_month'] ?? '')));
    $weekOfMonth = strtolower($weekOfMonth);
    $dayOfWeek = strtoupper(trim((string) ($parent['recurrence_day_of_week'] ?? '')));

    if ($weekOfMonth === '' || $dayOfWeek === '') {
        return;
    }

    $start = new DateTimeImmutable(date('Y-m-01', strtotime((string) $parent['start_datetime'])));
    $today = new DateTimeImmutable(date('Y-m-01'));
    $horizon = $today->modify('+1 year');

    $ruleEnd = !empty($parent['recurrence_end_date'])
        ? new DateTimeImmutable((string) $parent['recurrence_end_date'])
        : $horizon;

    $interval = max(1, (int) ($parent['recurrence_interval'] ?? 1));

    $monthCursor = $start;
    $monthIndex = 0;

    while ($monthCursor <= $horizon && $monthCursor <= $ruleEnd) {
        if ($monthIndex % $interval === 0) {
            $year = (int) $monthCursor->format('Y');
            $month = (int) $monthCursor->format('m');

            $instanceDate = eventforge_nth_weekday_of_month($year, $month, $weekOfMonth, $dayOfWeek);

            if ($instanceDate !== null && $instanceDate <= $ruleEnd->format('Y-m-d')) {
                eventforge_insert_child_event($connection, $parent, $instanceDate);
            }
        }

        $monthCursor = $monthCursor->modify('+1 month');
        $monthIndex++;
    }
}

function eventforge_generate_recurrence(mysqli $connection, int $parentId): void
{
    $parentId = (int) $parentId;

    $result = mysqli_query($connection, "
        SELECT *
        FROM events
        WHERE id = {$parentId}
          AND is_recurring_parent = 1
        LIMIT 1
    ");

    if (!$result || mysqli_num_rows($result) === 0) {
        return;
    }

    $parent = mysqli_fetch_assoc($result);

    if (!$parent) {
        return;
    }

    eventforge_delete_future_children($connection, $parentId);

    $type = strtolower((string) ($parent['recurrence_type'] ?? ''));

    if ($type === 'weekly') {
        eventforge_generate_weekly($connection, $parent);
        return;
    }

    if ($type === 'monthly_nth') {
        eventforge_generate_monthly_nth($connection, $parent);
        return;
    }
}