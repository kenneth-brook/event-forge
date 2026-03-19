<?php
declare(strict_types=1);

require_once __DIR__ . '/installer.php';
require_once __DIR__ . '/migrations.php';

$connection = eventforge_bootstrap_connection();

if (!$connection) {
    http_response_code(500);
    exit('Database is not configured.');
}

if (!eventforge_required_tables_exist($connection)) {
    http_response_code(500);
    exit('Database tables are missing.');
}

try {
    eventforge_run_migrations($connection);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Database migration failed: ' . $e->getMessage());
}