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

function eventforge_index_exists(mysqli $connection, string $table, string $index): bool
{
    $tableEsc = mysqli_real_escape_string($connection, $table);
    $indexEsc = mysqli_real_escape_string($connection, $index);

    $sql = "
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = '{$tableEsc}'
          AND index_name = '{$indexEsc}'
        LIMIT 1
    ";

    $result = mysqli_query($connection, $sql);

    return $result && mysqli_num_rows($result) > 0;
}

function eventforge_get_migrations(): array
{
    return [
        1 => function (mysqli $connection): void {},
        2 => function (mysqli $connection): void {
            if (!eventforge_column_exists($connection, 'event_admin_users', 'is_suspended')) {
                if (!mysqli_query($connection, "ALTER TABLE event_admin_users ADD COLUMN is_suspended TINYINT(1) NOT NULL DEFAULT 0")) {
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
                if (!mysqli_query($connection, "ALTER TABLE events ADD COLUMN category_id INT UNSIGNED DEFAULT NULL")) {
                    throw new RuntimeException('Failed adding events.category_id: ' . mysqli_error($connection));
                }
            }

            if (!eventforge_index_exists($connection, 'events', 'idx_events_category_id')) {
                if (!mysqli_query($connection, "ALTER TABLE events ADD KEY idx_events_category_id (category_id)")) {
                    throw new RuntimeException('Failed adding idx_events_category_id: ' . mysqli_error($connection));
                }
            }

            $fkCheckResult = mysqli_query($connection, "
                SELECT 1
                FROM information_schema.table_constraints
                WHERE table_schema = DATABASE()
                  AND table_name = 'events'
                  AND constraint_name = 'fk_events_category'
                  AND constraint_type = 'FOREIGN KEY'
                LIMIT 1
            ");

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
                if (!mysqli_query($connection, "ALTER TABLE event_categories ADD COLUMN font_color VARCHAR(20) DEFAULT NULL")) {
                    throw new RuntimeException('Failed adding event_categories.font_color: ' . mysqli_error($connection));
                }
            }
        },
        5 => function (mysqli $connection): void {
            if (!eventforge_column_exists($connection, 'events', 'slug')) {
                if (!mysqli_query($connection, "ALTER TABLE events ADD COLUMN slug VARCHAR(255) DEFAULT NULL")) {
                    throw new RuntimeException('Failed adding events.slug: ' . mysqli_error($connection));
                }
            }

            if (!eventforge_index_exists($connection, 'events', 'idx_events_slug')) {
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

                if (!mysqli_query($connection, "UPDATE events SET slug = '{$slugEsc}' WHERE id = {$eventId} LIMIT 1")) {
                    throw new RuntimeException('Failed backfilling slug for event ' . $eventId . ': ' . mysqli_error($connection));
                }
            }
        },
        7 => function (mysqli $connection): void {
            $columns = [
                'address_line_1' => "ALTER TABLE events ADD COLUMN address_line_1 VARCHAR(255) DEFAULT NULL AFTER location",
                'address_line_2' => "ALTER TABLE events ADD COLUMN address_line_2 VARCHAR(255) DEFAULT NULL AFTER address_line_1",
                'address_city' => "ALTER TABLE events ADD COLUMN address_city VARCHAR(120) DEFAULT NULL AFTER address_line_2",
                'address_state' => "ALTER TABLE events ADD COLUMN address_state VARCHAR(120) DEFAULT NULL AFTER address_city",
                'address_postal_code' => "ALTER TABLE events ADD COLUMN address_postal_code VARCHAR(32) DEFAULT NULL AFTER address_state",
                'latitude' => "ALTER TABLE events ADD COLUMN latitude DECIMAL(10,7) DEFAULT NULL AFTER address_postal_code",
                'longitude' => "ALTER TABLE events ADD COLUMN longitude DECIMAL(10,7) DEFAULT NULL AFTER latitude",
            ];

            foreach ($columns as $column => $sql) {
                if (!eventforge_column_exists($connection, 'events', $column)) {
                    if (!mysqli_query($connection, $sql)) {
                        throw new RuntimeException('Failed adding events.' . $column . ': ' . mysqli_error($connection));
                    }
                }
            }

            if (!eventforge_index_exists($connection, 'events', 'idx_events_lat_lng')) {
                if (!mysqli_query($connection, "ALTER TABLE events ADD KEY idx_events_lat_lng (latitude, longitude)")) {
                    throw new RuntimeException('Failed adding idx_events_lat_lng: ' . mysqli_error($connection));
                }
            }
        },
        8 => function (mysqli $connection): void {
            $columns = [
                'external_source' => "ALTER TABLE events ADD COLUMN external_source VARCHAR(80) DEFAULT NULL AFTER external_url",
                'external_id' => "ALTER TABLE events ADD COLUMN external_id VARCHAR(191) DEFAULT NULL AFTER external_source",
                'external_hash' => "ALTER TABLE events ADD COLUMN external_hash CHAR(64) DEFAULT NULL AFTER external_id",
                'external_payload' => "ALTER TABLE events ADD COLUMN external_payload MEDIUMTEXT DEFAULT NULL AFTER external_hash",
                'external_synced_at' => "ALTER TABLE events ADD COLUMN external_synced_at DATETIME DEFAULT NULL AFTER external_payload",
            ];

            foreach ($columns as $column => $sql) {
                if (!eventforge_column_exists($connection, 'events', $column)) {
                    if (!mysqli_query($connection, $sql)) {
                        throw new RuntimeException('Failed adding events.' . $column . ': ' . mysqli_error($connection));
                    }
                }
            }

            if (!eventforge_index_exists($connection, 'events', 'idx_events_external_lookup')) {
                if (!mysqli_query($connection, "ALTER TABLE events ADD KEY idx_events_external_lookup (external_source, external_id)")) {
                    throw new RuntimeException('Failed adding idx_events_external_lookup: ' . mysqli_error($connection));
                }
            }

            if (!eventforge_index_exists($connection, 'events', 'idx_events_external_synced_at')) {
                if (!mysqli_query($connection, "ALTER TABLE events ADD KEY idx_events_external_synced_at (external_synced_at)")) {
                    throw new RuntimeException('Failed adding idx_events_external_synced_at: ' . mysqli_error($connection));
                }
            }
        },
        9 => function (mysqli $connection): void {
            if (!eventforge_column_exists($connection, 'events', 'event_cost')) {
                if (!mysqli_query($connection, "ALTER TABLE events ADD COLUMN event_cost VARCHAR(255) DEFAULT NULL AFTER external_url")) {
                    throw new RuntimeException('Failed adding events.event_cost: ' . mysqli_error($connection));
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

    if (!eventforge_set_system_value($connection, 'release_channel', EVENTFORGE_RELEASE_CHANNEL)) {
        throw new RuntimeException('Failed to update release_channel.');
    }
}
