<?php
declare(strict_types=1);

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/system.php';
require_once __DIR__ . '/functions.php';

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

        3 => function (mysqli $connection): void {
            $tableSql = "
                CREATE TABLE IF NOT EXISTS event_categories (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    slug VARCHAR(120) DEFAULT NULL,
                    color VARCHAR(20) DEFAULT NULL,
                    is_active TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_event_categories_name (name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";

            if (!mysqli_query($connection, $tableSql)) {
                throw new RuntimeException('Failed creating event_categories: ' . mysqli_error($connection));
            }

            if (!eventforge_column_exists($connection, 'events', 'category_id')) {
                $sql = "
                    ALTER TABLE events
                    ADD COLUMN category_id INT UNSIGNED DEFAULT NULL
                ";

                if (!mysqli_query($connection, $sql)) {
                    throw new RuntimeException('Failed adding events.category_id: ' . mysqli_error($connection));
                }
            }

            $indexCheckSql = "
                SELECT 1
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                AND table_name = 'events'
                AND index_name = 'idx_events_category_id'
                LIMIT 1
            ";
            $indexCheckResult = mysqli_query($connection, $indexCheckSql);

            if (!$indexCheckResult || mysqli_num_rows($indexCheckResult) === 0) {
                if (!mysqli_query($connection, "ALTER TABLE events ADD KEY idx_events_category_id (category_id)")) {
                    throw new RuntimeException('Failed adding idx_events_category_id: ' . mysqli_error($connection));
                }
            }

            $fkCheckSql = "
                SELECT 1
                FROM information_schema.table_constraints
                WHERE table_schema = DATABASE()
                AND table_name = 'events'
                AND constraint_name = 'fk_events_category'
                AND constraint_type = 'FOREIGN KEY'
                LIMIT 1
            ";
            $fkCheckResult = mysqli_query($connection, $fkCheckSql);

            if (!$fkCheckResult || mysqli_num_rows($fkCheckResult) === 0) {
                $fkSql = "
                    ALTER TABLE events
                    ADD CONSTRAINT fk_events_category
                    FOREIGN KEY (category_id)
                    REFERENCES event_categories(id)
                    ON DELETE SET NULL
                ";

                if (!mysqli_query($connection, $fkSql)) {
                    throw new RuntimeException('Failed adding fk_events_category: ' . mysqli_error($connection));
                }
            }
        },

        4 => function (mysqli $connection): void {
            if (!eventforge_column_exists($connection, 'event_categories', 'font_color')) {
                $sql = "
                    ALTER TABLE event_categories
                    ADD COLUMN font_color VARCHAR(20) DEFAULT NULL
                ";

                if (!mysqli_query($connection, $sql)) {
                    throw new RuntimeException('Failed adding event_categories.font_color: ' . mysqli_error($connection));
                }
            }
        },

        5 => function (mysqli $connection): void {
            if (!eventforge_column_exists($connection, 'events', 'slug')) {
                $sql = "
                    ALTER TABLE events
                    ADD COLUMN slug VARCHAR(255) DEFAULT NULL
                ";

                if (!mysqli_query($connection, $sql)) {
                    throw new RuntimeException('Failed adding events.slug: ' . mysqli_error($connection));
                }
            }

            $indexCheckSql = "
                SELECT 1
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                AND table_name = 'events'
                AND index_name = 'idx_events_slug'
                LIMIT 1
            ";
            $indexCheckResult = mysqli_query($connection, $indexCheckSql);

            if (!$indexCheckResult || mysqli_num_rows($indexCheckResult) === 0) {
                if (!mysqli_query($connection, "ALTER TABLE events ADD KEY idx_events_slug (slug)")) {
                    throw new RuntimeException('Failed adding idx_events_slug: ' . mysqli_error($connection));
                }
            }
        },

        6 => function (mysqli $connection): void {
            $result = mysqli_query($connection, "
                SELECT id, title
                FROM events
                WHERE slug IS NULL OR slug = ''
                ORDER BY id ASC
            ");

            if (!$result) {
                throw new RuntimeException('Failed selecting events for slug backfill: ' . mysqli_error($connection));
            }

            while ($row = mysqli_fetch_assoc($result)) {
                $eventId = (int) $row['id'];
                $title = (string) ($row['title'] ?? '');

                $slug = eventforge_unique_event_slug($connection, $title, $eventId);
                $slugEsc = mysqli_real_escape_string($connection, $slug);

                $updateSql = "
                    UPDATE events
                    SET slug = '{$slugEsc}'
                    WHERE id = {$eventId}
                    LIMIT 1
                ";

                if (!mysqli_query($connection, $updateSql)) {
                    throw new RuntimeException('Failed backfilling slug for event ' . $eventId . ': ' . mysqli_error($connection));
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