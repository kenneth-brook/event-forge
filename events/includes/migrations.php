<?php
declare(strict_types=1);

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/system.php';

function eventforge_column_exists(mysqli $connection, string $table, string $column): bool
{
    $tableEsc = mysqli_real_escape_string($connection, $table);
    $columnEsc = mysqli_real_escape_string($connection, $column);

    $sql = "
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = '{$tableEsc}'
          AND column_name = '{$columnEsc}'
        LIMIT 1
    ";

    $result = mysqli_query($connection, $sql);

    return $result && mysqli_num_rows($result) > 0;
}

function eventforge_get_migrations(): array
{
    return [
        1 => function (mysqli $connection): void {
            // Initial schema baseline. No-op.
        },

        2 => function (mysqli $connection): void {
            if (!eventforge_column_exists($connection, 'event_admin_users', 'is_suspended')) {
                $sql = "
                    ALTER TABLE event_admin_users
                    ADD COLUMN is_suspended TINYINT(1) NOT NULL DEFAULT 0
                ";

                if (!mysqli_query($connection, $sql)) {
                    throw new RuntimeException('Failed adding is_suspended: ' . mysqli_error($connection));
                }
            }
        },
    ];
}

function eventforge_run_migrations(mysqli $connection): void
{
    $installedVersion = eventforge_get_schema_version($connection);
    $migrations = eventforge_get_migrations();

    ksort($migrations);

    foreach ($migrations as $version => $migration) {
        if ($version <= $installedVersion) {
            continue;
        }

        $migration($connection);

        if (!eventforge_set_system_value($connection, 'schema_version', (string) $version)) {
            throw new RuntimeException('Failed to update schema_version.');
        }
    }

    if (!eventforge_set_system_value($connection, 'app_version', EVENTFORGE_APP_VERSION)) {
        throw new RuntimeException('Failed to update app_version.');
    }
}