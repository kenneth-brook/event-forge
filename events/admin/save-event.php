<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/installer.php';

if (!eventforge_is_installed()) {
    header('Location: ' . eventforge_admin_path('setup.php'));
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/location.php';
require_once __DIR__ . '/../includes/recurrence.php';

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
$address = eventforge_normalize_address_input($_POST);

$categoryId = isset($_POST['category_id']) && $_POST['category_id'] !== ''
    ? (int) $_POST['category_id']
    : 0;
$categorySql = $categoryId > 0 ? (string) $categoryId : 'NULL';

if ($title === '' || $startDatetime === '') {
    exit('Title and start date/time are required.');
}

$recurrenceNormalized = eventforge_normalize_recurrence_input(
    [
        'is_recurring_parent' => $_POST['is_recurring_parent'] ?? '0',
        'recurrence_type' => $_POST['recurrence_type'] ?? '',
        'annual_mode' => $_POST['annual_mode'] ?? 'date',
        'annual_pattern_mode' => $_POST['annual_pattern_mode'] ?? 'same_date',
        'annual_recurrence_week_of_month' => $_POST['annual_recurrence_week_of_month'] ?? '',
        'annual_recurrence_day_of_week' => $_POST['annual_recurrence_day_of_week'] ?? '',
        'recurrence_interval' => $_POST['recurrence_interval'] ?? 1,
        'recurrence_days' => $_POST['recurrence_days'] ?? [],
        'recurrence_week_of_month' => $_POST['recurrence_week_of_month'] ?? '',
        'recurrence_day_of_week' => $_POST['recurrence_day_of_week'] ?? '',
        'recurrence_end_date' => $_POST['recurrence_end_date'] ?? '',
    ],
    $startDatetime
);

if (!empty($recurrenceNormalized['errors'])) {
    exit(implode(' ', $recurrenceNormalized['errors']));
}

$recurrence = $recurrenceNormalized['data'];

$isRecurringParent = (int) $recurrence['is_recurring_parent'];
$recurrenceType = (string) $recurrence['recurrence_type'];
$recurrenceInterval = $recurrence['recurrence_interval'];
$recurrenceDays = (string) $recurrence['recurrence_days'];
$recurrenceWeekOfMonth = (string) $recurrence['recurrence_week_of_month'];
$recurrenceDayOfWeek = (string) $recurrence['recurrence_day_of_week'];
$recurrenceEndDate = (string) $recurrence['recurrence_end_date'];

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

$addressLine1Esc = mysqli_real_escape_string($connection, $address['address_line_1']);
$addressLine2Esc = mysqli_real_escape_string($connection, $address['address_line_2']);
$addressCityEsc = mysqli_real_escape_string($connection, $address['address_city']);
$addressStateEsc = mysqli_real_escape_string($connection, $address['address_state']);
$addressPostalCodeEsc = mysqli_real_escape_string($connection, $address['address_postal_code']);

$recurrenceTypeEsc = mysqli_real_escape_string($connection, $recurrenceType);
$recurrenceDaysEsc = mysqli_real_escape_string($connection, $recurrenceDays);
$recurrenceWeekOfMonthEsc = mysqli_real_escape_string($connection, $recurrenceWeekOfMonth);
$recurrenceDayOfWeekEsc = mysqli_real_escape_string($connection, $recurrenceDayOfWeek);

$recurrenceTypeSql = $recurrenceType !== '' ? "'{$recurrenceTypeEsc}'" : 'NULL';
$recurrenceIntervalSql = $recurrenceInterval !== null ? (string) ((int) $recurrenceInterval) : 'NULL';
$recurrenceDaysSql = $recurrenceDays !== '' ? "'{$recurrenceDaysEsc}'" : 'NULL';
$recurrenceWeekOfMonthSql = $recurrenceWeekOfMonth !== '' ? "'{$recurrenceWeekOfMonthEsc}'" : 'NULL';
$recurrenceDayOfWeekSql = $recurrenceDayOfWeek !== '' ? "'{$recurrenceDayOfWeekEsc}'" : 'NULL';
$recurrenceEndDateSql = $recurrenceEndDate !== ''
    ? "'" . mysqli_real_escape_string($connection, $recurrenceEndDate) . "'"
    : 'NULL';

$existingEvent = null;
$existingAddressSignature = '';
$existingLatitude = null;
$existingLongitude = null;

if ($id > 0) {
    $existingResult = mysqli_query($connection, "
        SELECT
            id,
            address_line_1,
            address_line_2,
            address_city,
            address_state,
            address_postal_code,
            latitude,
            longitude
        FROM events
        WHERE id = {$id}
        LIMIT 1
    ");

    if ($existingResult && ($existingRow = mysqli_fetch_assoc($existingResult))) {
        $existingEvent = $existingRow;
        $existingAddressSignature = eventforge_address_signature($existingRow);
        $existingLatitude = $existingRow['latitude'];
        $existingLongitude = $existingRow['longitude'];
    }
}

$currentAddressSignature = eventforge_address_signature($address);
$addressChanged = $existingEvent === null || $currentAddressSignature !== $existingAddressSignature;

$latitude = null;
$longitude = null;

if (eventforge_has_usable_address($address)) {
    if (!$addressChanged && eventforge_coordinates_are_valid($existingLatitude, $existingLongitude)) {
        $latitude = round((float) $existingLatitude, 7);
        $longitude = round((float) $existingLongitude, 7);
    } else {
        $geocodingToken = eventforge_get_mapbox_geocoding_token($connection);
        $query = eventforge_build_geocoding_query($location, $address);
        $geocoded = eventforge_geocode_with_mapbox($geocodingToken, $query);

        if (is_array($geocoded)) {
            $latitude = $geocoded['latitude'];
            $longitude = $geocoded['longitude'];
        }
    }
}

$latitudeSql = eventforge_coordinates_are_valid($latitude, $longitude)
    ? number_format((float) $latitude, 7, '.', '')
    : 'NULL';
$longitudeSql = eventforge_coordinates_are_valid($latitude, $longitude)
    ? number_format((float) $longitude, 7, '.', '')
    : 'NULL';

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
            address_line_1 = '{$addressLine1Esc}',
            address_line_2 = '{$addressLine2Esc}',
            address_city = '{$addressCityEsc}',
            address_state = '{$addressStateEsc}',
            address_postal_code = '{$addressPostalCodeEsc}',
            latitude = {$latitudeSql},
            longitude = {$longitudeSql},
            summary = '{$summaryEsc}',
            description = '{$descriptionEsc}',
            external_url = '{$externalUrlEsc}',
            category_id = {$categorySql},
            is_published = {$isPublished},
            is_recurring_parent = {$isRecurringParent},
            recurrence_type = {$recurrenceTypeSql},
            recurrence_interval = {$recurrenceIntervalSql},
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
            address_line_1,
            address_line_2,
            address_city,
            address_state,
            address_postal_code,
            latitude,
            longitude,
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
            '{$addressLine1Esc}',
            '{$addressLine2Esc}',
            '{$addressCityEsc}',
            '{$addressStateEsc}',
            '{$addressPostalCodeEsc}',
            {$latitudeSql},
            {$longitudeSql},
            '{$summaryEsc}',
            '{$descriptionEsc}',
            {$imageInsert},
            {$pdfInsert},
            '{$externalUrlEsc}',
            {$isPublished},
            {$isRecurringParent},
            {$recurrenceTypeSql},
            {$recurrenceIntervalSql},
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