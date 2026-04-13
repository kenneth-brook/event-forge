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

function eventforge_valid_recurrence_types(): array
{
    return [
        'daily',
        'weekly',
        'monthly_nth',
        'annual',
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

function eventforge_weekday_code_from_datetime(string $datetime): string
{
    $reverse = array_flip(eventforge_weekday_map());
    $weekday = (int) date('w', strtotime($datetime));

    return $reverse[$weekday] ?? 'MO';
}

function eventforge_is_valid_date_string(string $value): bool
{
    if ($value === '') {
        return false;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

    return $date !== false && $date->format('Y-m-d') === $value;
}

function eventforge_is_recurring_enabled($value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value)) {
        return $value === 1;
    }

    $normalized = strtolower(trim((string) $value));

    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

function eventforge_normalize_weekly_days($value): array
{
    $weekdayMap = eventforge_weekday_map();
    $orderedCodes = array_keys($weekdayMap);

    if (is_array($value)) {
        $rawDays = $value;
    } else {
        $rawDays = explode(',', (string) $value);
    }

    $normalized = [];

    foreach ($rawDays as $day) {
        $code = strtoupper(trim((string) $day));

        if ($code !== '' && isset($weekdayMap[$code])) {
            $normalized[$code] = true;
        }
    }

    $result = [];

    foreach ($orderedCodes as $code) {
        if (isset($normalized[$code])) {
            $result[] = $code;
        }
    }

    return $result;
}

function eventforge_resolve_recurrence_type(array $event): string
{
    $type = strtolower(trim((string) ($event['recurrence_type'] ?? '')));

    if ($type === 'monthly') {
        $type = 'monthly_nth';
    }

    if (in_array($type, eventforge_valid_recurrence_types(), true)) {
        return $type;
    }

    $hasMonthlyParts = trim((string) ($event['recurrence_week_of_month'] ?? '')) !== ''
        && trim((string) ($event['recurrence_day_of_week'] ?? '')) !== '';

    if ($hasMonthlyParts) {
        return 'monthly_nth';
    }

    $weeklyDays = eventforge_normalize_weekly_days($event['recurrence_days'] ?? []);

    if (!empty($weeklyDays)) {
        return 'weekly';
    }

    return '';
}

function eventforge_normalize_recurrence_input(array $input, string $startDatetime = ''): array
{
    $errors = [];
    $isRecurring = eventforge_is_recurring_enabled($input['is_recurring_parent'] ?? 0);
    $type = eventforge_resolve_recurrence_type($input);

    $base = [
        'is_recurring_parent' => 0,
        'recurrence_type' => '',
        'recurrence_interval' => null,
        'recurrence_days' => '',
        'recurrence_week_of_month' => '',
        'recurrence_day_of_week' => '',
        'recurrence_end_date' => '',
    ];

    if (!$isRecurring) {
        return [
            'data' => $base,
            'errors' => [],
        ];
    }

    if ($type === '') {
        $errors[] = 'Please select a recurrence type.';
        return [
            'data' => $base,
            'errors' => $errors,
        ];
    }

    $interval = max(1, (int) ($input['recurrence_interval'] ?? 1));
    $endDate = trim((string) ($input['recurrence_end_date'] ?? ''));

    if ($endDate !== '' && !eventforge_is_valid_date_string($endDate)) {
        $errors[] = 'Recurrence end date must be a valid date.';
    }

    if ($endDate !== '' && $startDatetime !== '') {
        $startDateOnly = date('Y-m-d', strtotime($startDatetime));

        if ($startDateOnly > $endDate) {
            $errors[] = 'Recurrence end date cannot be before the event start date.';
        }
    }

    $data = $base;
    $data['is_recurring_parent'] = 1;
    $data['recurrence_type'] = $type;
    $data['recurrence_interval'] = $interval;
    $data['recurrence_end_date'] = $endDate;

    if ($type === 'daily') {
        return [
            'data' => $data,
            'errors' => $errors,
        ];
    }

    if ($type === 'weekly') {
        $days = eventforge_normalize_weekly_days($input['recurrence_days'] ?? []);

        if (empty($days)) {
            $errors[] = 'Weekly recurrence requires at least one selected day.';
        }

        $data['recurrence_days'] = implode(',', $days);

        return [
            'data' => $data,
            'errors' => $errors,
        ];
    }

    if ($type === 'monthly_nth') {
        $allowedWeeks = ['first', 'second', 'third', 'fourth', 'last'];
        $weekOfMonth = strtolower(trim((string) ($input['recurrence_week_of_month'] ?? '')));
        $dayOfWeek = strtoupper(trim((string) ($input['recurrence_day_of_week'] ?? '')));

        if (!in_array($weekOfMonth, $allowedWeeks, true)) {
            $errors[] = 'Monthly recurrence requires a valid week of month.';
        }

        if (!array_key_exists($dayOfWeek, eventforge_weekday_map())) {
            $errors[] = 'Monthly recurrence requires a valid day of week.';
        }

        $data['recurrence_week_of_month'] = $weekOfMonth;
        $data['recurrence_day_of_week'] = $dayOfWeek;

        return [
            'data' => $data,
            'errors' => $errors,
        ];
    }

    if ($type === 'annual') {
        return [
            'data' => $data,
            'errors' => $errors,
        ];
    }

    $errors[] = 'Unsupported recurrence type.';

    return [
        'data' => $base,
        'errors' => $errors,
    ];
}

function eventforge_recurrence_horizon(array $parent): DateTimeImmutable
{
    $today = new DateTimeImmutable(date('Y-m-d'));
    $type = strtolower((string) ($parent['recurrence_type'] ?? ''));

    if ($type === 'annual') {
        return $today->modify('+5 years');
    }

    return $today->modify('+1 year');
}

function eventforge_recurrence_end_limit(array $parent): DateTimeImmutable
{
    $horizon = eventforge_recurrence_horizon($parent);

    if (empty($parent['recurrence_end_date'])) {
        return $horizon;
    }

    $ruleEnd = new DateTimeImmutable((string) $parent['recurrence_end_date']);

    return $ruleEnd < $horizon ? $ruleEnd : $horizon;
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
    $categoryId = !empty($parent['category_id']) ? (int) $parent['category_id'] : 0;
    $categorySql = $categoryId > 0 ? (string) $categoryId : 'NULL';

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
            category_id,
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
            {$categorySql},
            '{$instanceDateEsc}'
        )
    ";

    mysqli_query($connection, $sql);
}

function eventforge_generate_daily(mysqli $connection, array $parent): void
{
    $start = new DateTimeImmutable(date('Y-m-d', strtotime((string) $parent['start_datetime'])));
    $endDate = eventforge_recurrence_end_limit($parent);
    $interval = max(1, (int) ($parent['recurrence_interval'] ?? 1));

    $cursor = $start;

    while ($cursor <= $endDate) {
        eventforge_insert_child_event($connection, $parent, $cursor->format('Y-m-d'));
        $cursor = $cursor->modify('+' . $interval . ' days');
    }
}

function eventforge_generate_weekly(mysqli $connection, array $parent): void
{
    $daysRaw = trim((string) ($parent['recurrence_days'] ?? ''));

    if ($daysRaw === '') {
        return;
    }

    $dayCodes = eventforge_normalize_weekly_days($daysRaw);
    $weekdayMap = eventforge_weekday_map();

    if (empty($dayCodes)) {
        return;
    }

    $start = new DateTimeImmutable(date('Y-m-d', strtotime((string) $parent['start_datetime'])));
    $endDate = eventforge_recurrence_end_limit($parent);
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
    $weekOfMonth = strtolower(trim((string) ($parent['recurrence_week_of_month'] ?? '')));
    $dayOfWeek = strtoupper(trim((string) ($parent['recurrence_day_of_week'] ?? '')));

    if ($weekOfMonth === '' || $dayOfWeek === '') {
        return;
    }

    $start = new DateTimeImmutable(date('Y-m-01', strtotime((string) $parent['start_datetime'])));
    $endDate = eventforge_recurrence_end_limit($parent);
    $interval = max(1, (int) ($parent['recurrence_interval'] ?? 1));

    $monthCursor = $start;
    $monthIndex = 0;

    while ($monthCursor <= $endDate) {
        if ($monthIndex % $interval === 0) {
            $year = (int) $monthCursor->format('Y');
            $month = (int) $monthCursor->format('m');

            $instanceDate = eventforge_nth_weekday_of_month($year, $month, $weekOfMonth, $dayOfWeek);

            if ($instanceDate !== null && $instanceDate <= $endDate->format('Y-m-d')) {
                eventforge_insert_child_event($connection, $parent, $instanceDate);
            }
        }

        $monthCursor = $monthCursor->modify('+1 month');
        $monthIndex++;
    }
}

function eventforge_annual_instance_date(int $year, int $month, int $day): string
{
    $monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
    $lastDayOfMonth = (int) $monthStart->modify('last day of this month')->format('d');
    $safeDay = min($day, $lastDayOfMonth);

    return sprintf('%04d-%02d-%02d', $year, $month, $safeDay);
}

function eventforge_generate_annual(mysqli $connection, array $parent): void
{
    $start = new DateTimeImmutable(date('Y-m-d', strtotime((string) $parent['start_datetime'])));
    $endDate = eventforge_recurrence_end_limit($parent);
    $interval = max(1, (int) ($parent['recurrence_interval'] ?? 1));

    $startYear = (int) $start->format('Y');
    $month = (int) $start->format('m');
    $day = (int) $start->format('d');

    $year = $startYear;

    while (true) {
        $instanceDate = eventforge_annual_instance_date($year, $month, $day);
        $instance = new DateTimeImmutable($instanceDate);

        if ($instance > $endDate) {
            break;
        }

        eventforge_insert_child_event($connection, $parent, $instanceDate);
        $year += $interval;
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

    if ($type === 'daily') {
        eventforge_generate_daily($connection, $parent);
        return;
    }

    if ($type === 'weekly') {
        eventforge_generate_weekly($connection, $parent);
        return;
    }

    if ($type === 'monthly_nth') {
        eventforge_generate_monthly_nth($connection, $parent);
        return;
    }

    if ($type === 'annual') {
        eventforge_generate_annual($connection, $parent);
        return;
    }
}