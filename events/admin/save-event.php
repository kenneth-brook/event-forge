<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/functions.php';

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

if ($title === '' || $startDatetime === '') {
    exit('Title and start date/time are required.');
}

$titleEsc = mysqli_real_escape_string($connection, $title);
$startEsc = mysqli_real_escape_string($connection, str_replace('T', ' ', $startDatetime) . ':00');
$endEsc = $endDatetime !== '' ? "'" . mysqli_real_escape_string($connection, str_replace('T', ' ', $endDatetime) . ':00') . "'" : 'NULL';
$locationEsc = mysqli_real_escape_string($connection, $location);
$summaryEsc = mysqli_real_escape_string($connection, $summary);
$descriptionEsc = mysqli_real_escape_string($connection, $description);
$externalUrlEsc = mysqli_real_escape_string($connection, $externalUrl);

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
        $imagePath = '/events/uploads/images/' . $imageFilename;
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
        $pdfPath = '/events/uploads/pdfs/' . $pdfFilename;
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
            is_published = {$isPublished}
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
            start_datetime,
            end_datetime,
            all_day,
            location,
            summary,
            description,
            image_path,
            pdf_path,
            external_url,
            is_published
        ) VALUES (
            '{$titleEsc}',
            '{$startEsc}',
            {$endEsc},
            {$allDay},
            '{$locationEsc}',
            '{$summaryEsc}',
            '{$descriptionEsc}',
            {$imageInsert},
            {$pdfInsert},
            '{$externalUrlEsc}',
            {$isPublished}
        )
    ";
}

if (!mysqli_query($connection, $sql)) {
    exit('Save failed: ' . mysqli_error($connection));
}

header('Location: /events/admin/index.php');
exit;