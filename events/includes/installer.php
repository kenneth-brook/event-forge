<?php
declare(strict_types=1);

require_once __DIR__ . '/schema.php';

function eventforge_config_path(): string
{
    return dirname(__DIR__) . '/config/db.php';
}

function eventforge_config_exists(): bool
{
    return is_file(eventforge_config_path());
}

function eventforge_load_db_config(): ?array
{
    $path = eventforge_config_path();

    if (!is_file($path)) {
        return null;
    }

    $config = require $path;

    if (!is_array($config)) {
        return null;
    }

    return $config;
}

function eventforge_write_db_config(array $config): void
{
    $path = eventforge_config_path();
    $dir = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create config directory.');
    }

    $host = var_export((string) ($config['host'] ?? ''), true);
    $name = var_export((string) ($config['name'] ?? ''), true);
    $user = var_export((string) ($config['user'] ?? ''), true);
    $pass = var_export((string) ($config['pass'] ?? ''), true);
    $charset = var_export((string) ($config['charset'] ?? 'utf8mb4'), true);
    $port = var_export((string) ($config['port'] ?? '3306'), true);

    $content = <<<PHP
<?php
declare(strict_types=1);

return [
    'host' => {$host},
    'name' => {$name},
    'user' => {$user},
    'pass' => {$pass},
    'charset' => {$charset},
    'port' => {$port},
];
PHP;

    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException('Could not write DB config file.');
    }
}

function eventforge_connect_with_config(array $config): mysqli
{
    $host = (string) ($config['host'] ?? '');
    $name = (string) ($config['name'] ?? '');
    $user = (string) ($config['user'] ?? '');
    $pass = (string) ($config['pass'] ?? '');
    $port = (string) ($config['port'] ?? '3306');

    mysqli_report(MYSQLI_REPORT_OFF);

    $connection = mysqli_connect($host, $user, $pass, $name, (int) $port);

    if (!$connection) {
        throw new RuntimeException('Database connection failed: ' . mysqli_connect_error());
    }

    $charset = (string) ($config['charset'] ?? 'utf8mb4');
    mysqli_set_charset($connection, $charset);

    return $connection;
}

function eventforge_can_connect_with_config(array $config): bool
{
    try {
        $connection = eventforge_connect_with_config($config);
        mysqli_close($connection);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function eventforge_required_tables_exist(mysqli $connection): bool
{
    $requiredTables = [
        'event_admin_users',
        'events',
        'eventforge_system',
    ];

    foreach ($requiredTables as $table) {
        $tableEsc = mysqli_real_escape_string($connection, $table);

        $sql = "
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = '{$tableEsc}'
            LIMIT 1
        ";

        $result = mysqli_query($connection, $sql);

        if (!$result || mysqli_num_rows($result) === 0) {
            return false;
        }
    }

    return true;
}

function eventforge_admin_exists(mysqli $connection): bool
{
    $sql = "
        SELECT id
        FROM event_admin_users
        WHERE role = 'admin'
        LIMIT 1
    ";

    $result = mysqli_query($connection, $sql);

    return $result && mysqli_num_rows($result) > 0;
}

function eventforge_is_installed(): bool
{
    if (!eventforge_config_exists()) {
        return false;
    }

    $config = eventforge_load_db_config();

    if (!$config) {
        return false;
    }

    try {
        $connection = eventforge_connect_with_config($config);

        $tablesExist = eventforge_required_tables_exist($connection);
        $adminExists = $tablesExist ? eventforge_admin_exists($connection) : false;

        mysqli_close($connection);

        return $tablesExist && $adminExists;
    } catch (Throwable $e) {
        return false;
    }
}

function eventforge_bootstrap_connection(): ?mysqli
{
    if (!eventforge_config_exists()) {
        return null;
    }

    $config = eventforge_load_db_config();

    if (!$config) {
        return null;
    }

    return eventforge_connect_with_config($config);
}

function eventforge_run_initial_schema(array $config): void
{
    $connection = eventforge_connect_with_config($config);

    try {
        eventforge_run_schema_setup($connection);
    } finally {
        mysqli_close($connection);
    }
}