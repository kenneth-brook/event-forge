<?php
declare(strict_types=1);

require __DIR__ . '/../includes/installer.php';

if (!eventforge_is_installed()) {
    header('Location: ' . eventforge_admin_path('setup.php'));
    exit;
}

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/recurrence.php';

require_login();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

$title = trim($_POST['title'] ?? '');
$startDatetime = trim($_POST['start_datetime'] ?? '');
$endDatetime = trim($_POST['end_datetime'] ?? '');
$allDay = isset($_POST['all_day']) ? 1 : 0;
$location = trim($_POST['location'] ?? '');
$summary = trim($_POST['summary'] ?? '');
$description = trim($_POST['description'] ?? '');
$externalUrl = trim($_POST['external_url'] ?? '');
$isPublished = isset($_POST['is_published']) ? 1 : 0;

$categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== ''
    ? (int) $_POST['category_id']
    : 0;
$categorySql = $categoryId > 0 ? (string) $categoryId : 'NULL';

/*
|--------------------------------------------------------------------------
| Recurrence fields
|--------------------------------------------------------------------------
*/
$isRecurringParent = isset($_POST['is_recurring_parent']) ? 1 : 0;
$recurrenceType = trim($_POST['recurrence_type'] ?? '');
$recurrenceInterval = max(1, (int) ($_POST['recurrence_interval'] ?? 1));
$recurrenceDays = $_POST['recurrence_days'] ?? [];
$recurrenceWeekOfMonth = trim($_POST['recurrence_week_of_month'] ?? '');
$recurrenceDayOfWeek = trim($_POST['recurrence_day_of_week'] ?? '');
$recurrenceEndDate = trim($_POST['recurrence_end_date'] ?? '');

if (is_array($recurrenceDays)) {
    $recurrenceDays = implode(',', array_map('trim', $recurrenceDays));
} else {
    $recurrenceDays = trim((string) $recurrenceDays);
}

if ($title === '' || $startDatetime === '') {
    exit('Title and start date/time are required.');
}

$slug = eventforge_unique_event_slug($connection, $title, $id);
$slugEsc = mysqli_real_escape_string($connection, $slug);

$titleEsc = mysqli_real_escape_string($connection, $title);
$startEsc = mysqli_real_escape_string($connection, str_replace('T', ' ', $startDatetime) . ':00');
$endEsc = $endDatetime !== ''
    ? "'" . mysqli_real_escape_string($connection, str_replace('T', ' ', $endDatetime) . ':00') . "'"
    : 'NULL';

$locationEsc = mysqli_real_escape_string($connection, $location);
$summaryEsc = mysqli_real_escape_string($connection, $summary);
$descriptionEsc = mysqli_real_escape_string($connection, $description);
$externalUrlEsc = mysqli_real_escape_string($connection, $externalUrl);

$recurrenceTypeEsc = mysqli_real_escape_string($connection, $recurrenceType);
$recurrenceDaysEsc = mysqli_real_escape_string($connection, $recurrenceDays);
$recurrenceWeekOfMonthEsc = mysqli_real_escape_string($connection, $recurrenceWeekOfMonth);
$recurrenceDayOfWeekEsc = mysqli_real_escape_string($connection, $recurrenceDayOfWeek);
$recurrenceEndDateSql = $recurrenceEndDate !== ''
    ? "'" . mysqli_real_escape_string($connection, $recurrenceEndDate) . "'"
    : 'NULL';

$recurrenceTypeSql = $recurrenceType !== '' ? "'{$recurrenceTypeEsc}'" : 'NULL';
$recurrenceDaysSql = $recurrenceDays !== '' ? "'{$recurrenceDaysEsc}'" : 'NULL';
$recurrenceWeekOfMonthSql = $recurrenceWeekOfMonth !== '' ? "'{$recurrenceWeekOfMonthEsc}'" : 'NULL';
$recurrenceDayOfWeekSql = $recurrenceDayOfWeek !== '' ? "'{$recurrenceDayOfWeekEsc}'" : 'NULL';

$imageSqlPart = '';
$pdfSqlPart = '';

try {
    $imageFilename = upload_file(
        $_FILES['image'] ?? [],
        ['jpg', 'jpeg', 'png', 'webp'],
        __DIR__ . '/../uploads/images',
        'event-image'
    );

    if ($imageFilename !== null) {
        $imagePath = eventforge_upload_path('images/' . $imageFilename);
        $imageEsc = mysqli_real_escape_string($connection, $imagePath);
        $imageSqlPart = ", image_path = '{$imageEsc}'";
    }

    $pdfFilename = upload_file(
        $_FILES['pdf'] ?? [],
        ['pdf'],
        __DIR__ . '/../uploads/pdfs',
        'event-pdf'
    );

    if ($pdfFilename !== null) {
        $pdfPath = eventforge_upload_path('pdfs/' . $pdfFilename);
        $pdfEsc = mysqli_real_escape_string($connection, $pdfPath);
        $pdfSqlPart = ", pdf_path = '{$pdfEsc}'";
    }
} catch (Throwable $e) {
    exit('Upload error: ' . $e->getMessage());
}

if ($id > 0) {
    $sql = "
        UPDATE events SET
            title = '{$titleEsc}',
            start_datetime = '{$startEsc}',
            end_datetime = {$endEsc},
            all_day = {$allDay},
            location = '{$locationEsc}',
            summary = '{$summaryEsc}',
            description = '{$descriptionEsc}',
            external_url = '{$externalUrlEsc}',
            category_id = {$categorySql},
            is_published = {$isPublished},
            is_recurring_parent = {$isRecurringParent},
            recurrence_type = {$recurrenceTypeSql},
            recurrence_interval = {$recurrenceInterval},
            recurrence_days = {$recurrenceDaysSql},
            recurrence_week_of_month = {$recurrenceWeekOfMonthSql},
            recurrence_day_of_week = {$recurrenceDayOfWeekSql},
            slug = '{$slugEsc}',
            recurrence_end_date = {$recurrenceEndDateSql}
            {$imageSqlPart}
            {$pdfSqlPart}
        WHERE id = {$id}
        LIMIT 1
    ";
} else {
    $imageInsert = 'NULL';
    $pdfInsert = 'NULL';

    if (!empty($imageSqlPart)) {
        preg_match("/image_path = '([^']+)'/", $imageSqlPart, $m);
        $imageInsert = isset($m[1]) ? "'" . $m[1] . "'" : 'NULL';
    }

    if (!empty($pdfSqlPart)) {
        preg_match("/pdf_path = '([^']+)'/", $pdfSqlPart, $m);
        $pdfInsert = isset($m[1]) ? "'" . $m[1] . "'" : 'NULL';
    }

    $sql = "
        INSERT INTO events (
            title,
            slug,
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
            is_recurring_parent,
            recurrence_type,
            recurrence_interval,
            recurrence_days,
            recurrence_week_of_month,
            recurrence_day_of_week,
            recurrence_end_date,
            category_id
        ) VALUES (
            '{$titleEsc}',
            '{$slugEsc}',
            '{$startEsc}',
            {$endEsc},
            {$allDay},
            '{$locationEsc}',
            '{$summaryEsc}',
            '{$descriptionEsc}',
            {$imageInsert},
            {$pdfInsert},
            '{$externalUrlEsc}',
            {$isPublished},
            {$isRecurringParent},
            {$recurrenceTypeSql},
            {$recurrenceInterval},
            {$recurrenceDaysSql},
            {$recurrenceWeekOfMonthSql},
            {$recurrenceDayOfWeekSql},
            {$recurrenceEndDateSql},
            {$categorySql}
        )
    ";
}

if (!mysqli_query($connection, $sql)) {
    exit('Save failed: ' . mysqli_error($connection));
}

$savedId = $id > 0 ? $id : (int) mysqli_insert_id($connection);

/*
|--------------------------------------------------------------------------
| Recurrence generation / cleanup
|--------------------------------------------------------------------------
|
| Recurring parents generate normal child events.
| Non-recurring events or recurring rules turned off will clear children.
|
*/
if ($savedId > 0) {
    if ($isRecurringParent === 1) {
        eventforge_generate_recurrence($connection, $savedId);
    } else {
        eventforge_delete_future_children($connection, $savedId);
    }
}

header('Location: ' . eventforge_admin_path('index.php'));
exit;