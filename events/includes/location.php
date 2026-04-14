<?php
declare(strict_types=1);

require_once __DIR__ . '/system.php';

function eventforge_location_trim(?string $value): string
{
    return trim((string) $value);
}

function eventforge_normalize_address_input(array $input): array
{
    return [
        'address_line_1' => eventforge_location_trim($input['address_line_1'] ?? ''),
        'address_line_2' => eventforge_location_trim($input['address_line_2'] ?? ''),
        'address_city' => eventforge_location_trim($input['address_city'] ?? ''),
        'address_state' => eventforge_location_trim($input['address_state'] ?? ''),
        'address_postal_code' => eventforge_location_trim($input['address_postal_code'] ?? ''),
    ];
}

function eventforge_has_usable_address(array $address): bool
{
    return eventforge_location_trim($address['address_line_1'] ?? '') !== ''
        && eventforge_location_trim($address['address_city'] ?? '') !== ''
        && eventforge_location_trim($address['address_state'] ?? '') !== '';
}

function eventforge_build_geocoding_query(string $location, array $address): string
{
    $parts = [];

    if (eventforge_location_trim($location) !== '') {
        $parts[] = eventforge_location_trim($location);
    }

    foreach ([
        'address_line_1',
        'address_line_2',
        'address_city',
        'address_state',
        'address_postal_code',
    ] as $key) {
        $value = eventforge_location_trim($address[$key] ?? '');
        if ($value !== '') {
            $parts[] = $value;
        }
    }

    return implode(', ', $parts);
}

function eventforge_address_signature(array $address): string
{
    return strtolower(implode('|', [
        eventforge_location_trim($address['address_line_1'] ?? ''),
        eventforge_location_trim($address['address_line_2'] ?? ''),
        eventforge_location_trim($address['address_city'] ?? ''),
        eventforge_location_trim($address['address_state'] ?? ''),
        eventforge_location_trim($address['address_postal_code'] ?? ''),
    ]));
}

function eventforge_coordinates_are_valid($latitude, $longitude): bool
{
    if ($latitude === null || $longitude === null || $latitude === '' || $longitude === '') {
        return false;
    }

    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        return false;
    }

    $lat = (float) $latitude;
    $lng = (float) $longitude;

    return $lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0;
}

function eventforge_get_mapbox_geocoding_token(mysqli $connection): string
{
    return trim((string) (eventforge_get_system_value($connection, 'mapbox_geocoding_token') ?? ''));
}

function eventforge_geocode_with_mapbox(string $token, string $query): ?array
{
    $token = trim($token);
    $query = trim($query);

    if ($token === '' || $query === '') {
        return null;
    }

    $url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/' . rawurlencode($query) . '.json'
        . '?limit=1'
        . '&autocomplete=false'
        . '&types=address,poi,place,postcode'
        . '&access_token=' . rawurlencode($token);

    $response = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);

        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $statusCode < 200 || $statusCode >= 300) {
            return null;
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 12,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }
    }

    $decoded = json_decode((string) $response, true);

    if (!is_array($decoded) || empty($decoded['features'][0]['center'])) {
        return null;
    }

    $center = $decoded['features'][0]['center'];

    if (!is_array($center) || count($center) < 2) {
        return null;
    }

    $longitude = $center[0] ?? null;
    $latitude = $center[1] ?? null;

    if (!eventforge_coordinates_are_valid($latitude, $longitude)) {
        return null;
    }

    return [
        'latitude' => round((float) $latitude, 7),
        'longitude' => round((float) $longitude, 7),
    ];
}