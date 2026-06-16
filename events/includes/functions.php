<?php
declare(strict_types=1);

function upload_file(array $file, array $allowedExtensions, string $targetDir, string $prefix): ?string
{
    if (empty($file['name']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed.');
    }

    $originalName = $file['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $tmpName = (string) ($file['tmp_name'] ?? '');

    if (!in_array($extension, $allowedExtensions, true)) {
        throw new RuntimeException('Invalid file type.');
    }

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Invalid upload.');
    }

    $maxBytes = $extension === 'pdf'
        ? 10 * 1024 * 1024
        : 5 * 1024 * 1024;
    $fileSize = isset($file['size']) ? (int) $file['size'] : filesize($tmpName);

    if ($fileSize <= 0 || $fileSize > $maxBytes) {
        throw new RuntimeException('File is too large.');
    }

    $allowedMimeTypes = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
    ];

    $detectedMime = '';

    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = (string) $finfo->file($tmpName);
    }

    if (
        $detectedMime === ''
        || !in_array($detectedMime, $allowedMimeTypes[$extension] ?? [], true)
    ) {
        throw new RuntimeException('Invalid file content.');
    }

    if ($extension !== 'pdf' && getimagesize($tmpName) === false) {
        throw new RuntimeException('Invalid image file.');
    }

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Could not create upload directory.');
    }

    $filename = $prefix . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = rtrim($targetDir, '/') . '/' . $filename;

    if (!move_uploaded_file($tmpName, $destination)) {
        throw new RuntimeException('Could not move uploaded file.');
    }

    return $filename;
}

function eventforge_slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) $text, '-');

    return $text !== '' ? $text : 'event';
}

function eventforge_unique_event_slug(mysqli $connection, string $title, int $excludeId = 0): string
{
    $baseSlug = eventforge_slugify($title);
    $slug = $baseSlug;
    $counter = 2;

    while (true) {
        $slugEsc = mysqli_real_escape_string($connection, $slug);

        $sql = "
            SELECT id
            FROM events
            WHERE slug = '{$slugEsc}'
        ";

        if ($excludeId > 0) {
            $sql .= " AND id != {$excludeId}";
        }

        $sql .= " LIMIT 1";

        $result = mysqli_query($connection, $sql);

        if ($result && mysqli_num_rows($result) === 0) {
            return $slug;
        }

        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
}

function eventforge_build_public_event_url_from_base(string $calendarUrl, int $eventId, ?string $slug = null): string
{
    $calendarUrl = trim($calendarUrl);

    if ($calendarUrl === '') {
        return '';
    }

    $separator = strpos($calendarUrl, '?') !== false ? '&' : '?';
    $url = rtrim($calendarUrl, '/') . $separator . 'event_id=' . $eventId;

    if ($slug !== null && $slug !== '') {
        $url .= '&slug=' . urlencode($slug);
    }

    return $url;
}

function eventforge_build_public_event_url(mysqli $connection, int $eventId, ?string $slug = null): string
{
    return eventforge_build_public_event_url_from_base(
        (string) (eventforge_get_system_value($connection, 'public_calendar_url') ?? ''),
        $eventId,
        $slug
    );
}

function eventforge_build_qr_service_url(string $url, int $size = 240, int $margin = 16): string
{
    $url = trim($url);

    if ($url === '') {
        return '';
    }

    $size = max(120, min(1000, $size));
    $margin = max(0, min(64, $margin));

    return 'https://api.qrserver.com/v1/create-qr-code/?size='
        . $size . 'x' . $size
        . '&format=png'
        . '&margin=' . $margin
        . '&data=' . rawurlencode($url);
}

function eventforge_build_qr_filename(int $eventId, ?string $slug = null): string
{
    $base = $slug !== null && trim($slug) !== ''
        ? eventforge_slugify($slug)
        : 'event-' . $eventId;

    return $base . '-qr.png';
}
