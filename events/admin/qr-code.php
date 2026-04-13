<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/installer.php';
require_once __DIR__ . '/../includes/system.php';
require_once __DIR__ . '/../includes/functions.php';

if (!eventforge_is_installed()) {
    http_response_code(500);
    exit('Event Forge is not installed.');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

function eventforge_fetch_remote_binary(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_USERAGENT => 'Event Forge QR/0.6.1',
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body !== false && $status >= 200 && $status < 300) {
            return $body;
        }
    }

    if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN) || ini_get('allow_url_fopen') === '1') {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'ignore_errors' => true,
                'header' => "User-Agent: Event Forge QR/0.6.1\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body !== false) {
            return $body;
        }
    }

    return null;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$size = isset($_GET['size']) ? (int) $_GET['size'] : 240;
$download = isset($_GET['download']) && $_GET['download'] === '1';

if ($id <= 0) {
    http_response_code(404);
    exit('Event not found.');
}

$size = max(120, min(1000, $size));

$sql = "
    SELECT id, slug
    FROM events
    WHERE id = {$id}
    LIMIT 1
";

$result = mysqli_query($connection, $sql);

if (!$result || !($event = mysqli_fetch_assoc($result))) {
    http_response_code(404);
    exit('Event not found.');
}

$publicUrl = eventforge_build_public_event_url(
    $connection,
    (int) $event['id'],
    !empty($event['slug']) ? (string) $event['slug'] : null
);

if ($publicUrl === '') {
    http_response_code(404);
    exit('Public event URL is not available for this event.');
}

$remoteQrUrl = eventforge_build_qr_service_url($publicUrl, $size);
$qrImage = eventforge_fetch_remote_binary($remoteQrUrl);

if ($qrImage === null) {
    http_response_code(502);
    exit('Could not generate QR code image.');
}

$filename = eventforge_build_qr_filename(
    (int) $event['id'],
    !empty($event['slug']) ? (string) $event['slug'] : null
);

header('Content-Type: image/png');
header('Content-Length: ' . strlen($qrImage));
header('Cache-Control: private, max-age=300');

if ($download) {
    header('Content-Disposition: attachment; filename="' . $filename . '"');
} else {
    header('Content-Disposition: inline; filename="' . $filename . '"');
}

echo $qrImage;
exit;