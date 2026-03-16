<?php
declare(strict_types=1);

require_once __DIR__ . '/version.php';

function eventforge_get_system_value(mysqli $connection, string $key): ?string
{
    $keyEsc = mysqli_real_escape_string($connection, $key);

    $sql = "
        SELECT system_value
        FROM eventforge_system
        WHERE system_key = '{$keyEsc}'
        LIMIT 1
    ";

    $result = mysqli_query($connection, $sql);

    if ($result && $row = mysqli_fetch_assoc($result)) {
        return isset($row['system_value']) ? (string) $row['system_value'] : null;
    }

    return null;
}

function eventforge_set_system_value(mysqli $connection, string $key, string $value): bool
{
    $keyEsc = mysqli_real_escape_string($connection, $key);
    $valueEsc = mysqli_real_escape_string($connection, $value);

    $sql = "
        INSERT INTO eventforge_system (system_key, system_value)
        VALUES ('{$keyEsc}', '{$valueEsc}')
        ON DUPLICATE KEY UPDATE system_value = '{$valueEsc}'
    ";

    return (bool) mysqli_query($connection, $sql);
}

function eventforge_get_schema_version(mysqli $connection): int
{
    $value = eventforge_get_system_value($connection, 'schema_version');

    return $value !== null ? (int) $value : 0;
}

function eventforge_get_app_version(mysqli $connection): string
{
    $value = eventforge_get_system_value($connection, 'app_version');

    return $value !== null ? $value : '0.0.0';
}