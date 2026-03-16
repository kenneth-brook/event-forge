<?php
declare(strict_types=1);

require_once __DIR__ . '/installer.php';

$connection = eventforge_bootstrap_connection();

if (!$connection) {
    http_response_code(500);
    exit('Database is not configured.');
}