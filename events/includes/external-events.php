<?php
declare(strict_types=1);

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/system.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/location.php';

function eventforge_external_event_providers(): array
{
    return [
        'chambermate' => [
            'label' => 'ChamberMate',
            'description' => 'Imports events from a ChamberMate getEventsInfo JSON feed.',
            'feed_url_placeholder' => 'https://api.chambermate.com/core/biz/webPresence/getEventsInfo?apiKey=...',
        ],
    ];
}

function eventforge_external_event_provider_exists(string $provider): bool
{
    return array_key_exists($provider, eventforge_external_event_providers());
}

function eventforge_external_events_enabled(mysqli $connection): bool
{
    return eventforge_get_system_flag($connection, 'external_events_enabled', false);
}

function eventforge_get_external_events_provider(mysqli $connection): string
{
    $provider = trim((string) (eventforge_get_system_value($connection, 'external_events_provider') ?? ''));

    return $provider !== '' && eventforge_external_event_provider_exists($provider) ? $provider : 'chambermate';
}

function eventforge_get_external_events_provider_definition(mysqli $connection): array
{
    $provider = eventforge_get_external_events_provider($connection);
    $providers = eventforge_external_event_providers();

    return $providers[$provider] ?? $providers['chambermate'];
}

function eventforge_get_external_events_feed_url(mysqli $connection): string
{
    return trim((string) (eventforge_get_system_value($connection, 'external_events_feed_url') ?? ''));
}

function eventforge_set_external_events_settings(mysqli $connection, bool $enabled, string $provider, string $feedUrl): void
{
    $provider = trim($provider) !== '' ? trim($provider) : 'chambermate';
    $feedUrl = trim($feedUrl);

    if (!eventforge_external_event_provider_exists($provider)) {
        throw new RuntimeException('Unsupported external event provider.');
    }

    if ($enabled && !eventforge_external_feed_url_is_safe($feedUrl)) {
        throw new RuntimeException('External feed URL is invalid or unsafe.');
    }

    eventforge_set_system_flag($connection, 'external_events_enabled', $enabled);
    eventforge_set_system_value($connection, 'external_events_provider', $provider);
    eventforge_set_system_value($connection, 'external_events_feed_url', $feedUrl);
}

function eventforge_external_array_get(array $item, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $item) && $item[$key] !== null && trim((string) $item[$key]) !== '') {
            return trim((string) $item[$key]);
        }
    }

    return $default;
}

function eventforge_clean_external_html_text(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $value = preg_replace_callback(
        '/<img\b[^>]*\balt=(["\'])(.*?)\1[^>]*>/is',
        static function (array $matches): string {
            return html_entity_decode((string) ($matches[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        },
        $value
    ) ?? $value;

    $value = preg_replace('/<br\s*\/?>/i', "\n", $value) ?? $value;
    $value = preg_replace('/<\/(p|div|section|article|h[1-6]|li|tr)>/i', "\n", $value) ?? $value;
    $value = preg_replace('/<li\b[^>]*>/i', '- ', $value) ?? $value;

    $value = strip_tags($value);
    $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $value);
    $value = str_replace(["\r\n", "\r"], "\n", $value);

    $lines = explode("\n", $value);
    $cleanLines = [];

    foreach ($lines as $line) {
        $line = preg_replace('/[ \t]+/', ' ', $line) ?? $line;
        $line = trim($line);

        $cleanLines[] = $line;
    }

    $value = implode("\n", $cleanLines);
    $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? $value;

    return trim($value);
}

function eventforge_external_text_excerpt(string $value, int $maxLength = 240): string
{
    $plain = eventforge_clean_external_html_text($value);
    $plain = preg_replace('/\s+/', ' ', $plain) ?? $plain;

    if ($plain === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($plain) <= $maxLength ? $plain : rtrim(mb_substr($plain, 0, $maxLength - 3)) . '...';
    }

    return strlen($plain) <= $maxLength ? $plain : rtrim(substr($plain, 0, $maxLength - 3)) . '...';
}

function eventforge_external_feed_url_is_safe(string $url): bool
{
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $parts = parse_url($url);

    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }

    $scheme = strtolower((string) $parts['scheme']);

    if (!in_array($scheme, ['https', 'http'], true)) {
        return false;
    }

    $host = strtolower((string) $parts['host']);

    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return false;
    }

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    return true;
}

function eventforge_fetch_external_feed_json(string $url): array
{
    if (!eventforge_external_feed_url_is_safe($url)) {
        throw new RuntimeException('External feed URL is invalid or unsafe.');
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);

        if ($ch === false) {
            throw new RuntimeException('Could not initialize external feed request.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'EventForge/' . EVENTFORGE_APP_VERSION,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($body === '' || $status < 200 || $status >= 300) {
            throw new RuntimeException('External feed request failed. HTTP ' . $status . ($error !== '' ? ': ' . $error : ''));
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'timeout' => 20,
                'ignore_errors' => false,
                'header' => "Accept: application/json\r\nUser-Agent: EventForge/" . EVENTFORGE_APP_VERSION . "\r\n",
            ],
        ]);

        $body = (string) @file_get_contents($url, false, $context);

        if ($body === '') {
            throw new RuntimeException('External feed request failed.');
        }
    }

    $decoded = json_decode($body, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('External feed did not return valid JSON.');
    }

    return $decoded;
}

function eventforge_extract_external_event_items(array $payload): array
{
    if (isset($payload['data']['events']) && is_array($payload['data']['events'])) {
        return $payload['data']['events'];
    }

    if (isset($payload['events']) && is_array($payload['events'])) {
        return $payload['events'];
    }

    if (isset($payload['Events']) && is_array($payload['Events'])) {
        return $payload['Events'];
    }

    if (isset($payload['results']) && is_array($payload['results'])) {
        return $payload['results'];
    }

    return array_keys($payload) === range(0, count($payload) - 1) ? $payload : [];
}

function eventforge_external_parse_datetime(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return '';
    }
}

function eventforge_chambermate_image_url(array $item): string
{
    $storageKey = eventforge_external_array_get($item, ['avatarStorageKey']);

    if ($storageKey === '') {
        return '';
    }

    return 'https://api.chambermate.com/core/app/storage/directView?k=' . str_replace(' ', '%20', $storageKey);
}

function eventforge_chambermate_company_from_associations(array $item): array
{
    if (empty($item['associations']) || !is_array($item['associations'])) {
        return [];
    }

    foreach ($item['associations'] as $association) {
        if (!is_array($association)) {
            continue;
        }

        if (($association['activityAssociationTypeCode'] ?? '') === 'COMPANY' && isset($association['company']) && is_array($association['company'])) {
            return $association['company'];
        }
    }

    return [];
}

function eventforge_non_empty_external_description($value): bool
{
    return trim((string) $value) !== '';
}


function eventforge_external_cost_candidate_from_value($value): string
{
    if (is_scalar($value)) {
        return eventforge_clean_external_html_text((string) $value);
    }

    return '';
}

function eventforge_external_find_cost_recursive(array $item): string
{
    $preferredKeys = [
        'cost',
        'eventCost',
        'event_cost',
        'costText',
        'cost_text',
        'fee',
        'fees',
        'price',
        'admission',
        'registrationFee',
        'registration_fee',
    ];

    foreach ($preferredKeys as $key) {
        if (array_key_exists($key, $item)) {
            $candidate = eventforge_external_cost_candidate_from_value($item[$key]);

            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    foreach ($item as $key => $value) {
        $keyText = strtolower((string) $key);
        $looksLikeCost = strpos($keyText, 'cost') !== false
            || strpos($keyText, 'fee') !== false
            || strpos($keyText, 'price') !== false
            || strpos($keyText, 'admission') !== false;

        if ($looksLikeCost) {
            $candidate = eventforge_external_cost_candidate_from_value($value);

            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    foreach ($item as $value) {
        if (is_array($value)) {
            $candidate = eventforge_external_find_cost_recursive($value);

            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    return '';
}

function eventforge_chambermate_cost_text(array $item): string
{
    return eventforge_external_find_cost_recursive($item);
}

function eventforge_normalize_chambermate_event(array $item): ?array
{
    $externalId = eventforge_external_array_get($item, ['activityKey']);
    $title = eventforge_clean_external_html_text(eventforge_external_array_get($item, ['eventName']));
    $startDatetime = eventforge_external_parse_datetime(eventforge_external_array_get($item, ['startDateTime']));

    if ($title === '' || $startDatetime === '') {
        return null;
    }

    if ($externalId === '') {
        $externalId = hash('sha256', strtolower($title . '|' . $startDatetime));
    }

    $eventDescription = eventforge_clean_external_html_text(eventforge_external_array_get($item, ['eventDescription']));
    $eventFullDescription = eventforge_clean_external_html_text(eventforge_external_array_get($item, ['eventFullDescription']));
    $descriptionParts = array_filter([$eventDescription, $eventFullDescription], 'eventforge_non_empty_external_description');
    $description = implode("\n\n", $descriptionParts);

    $summary = eventforge_clean_external_html_text(eventforge_external_array_get($item, ['seoDescription']));

    if ($summary === '' && $eventDescription !== '') {
        $summary = eventforge_external_text_excerpt($eventDescription);
    }

    $address = isset($item['address']) && is_array($item['address']) ? $item['address'] : [];
    $company = eventforge_chambermate_company_from_associations($item);
    $location = eventforge_clean_external_html_text(eventforge_external_array_get($address, ['name']));

    if ($location === '') {
        $location = eventforge_clean_external_html_text(eventforge_external_array_get($company, ['companyName']));
    }

    $payloadJson = json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return [
        'external_source' => 'chambermate',
        'external_id' => $externalId,
        'title' => $title,
        'start_datetime' => $startDatetime,
        'end_datetime' => eventforge_external_parse_datetime(eventforge_external_array_get($item, ['endDateTime'])) ?: null,
        'all_day' => !empty($item['noTimes']) ? 1 : 0,
        'location' => $location,
        'address_line_1' => eventforge_clean_external_html_text(eventforge_external_array_get($address, ['street1'])),
        'address_line_2' => eventforge_clean_external_html_text(eventforge_external_array_get($address, ['street2'])),
        'address_city' => eventforge_clean_external_html_text(eventforge_external_array_get($address, ['city'])),
        'address_state' => eventforge_clean_external_html_text(eventforge_external_array_get($address, ['stateCode', 'stateName'])),
        'address_postal_code' => eventforge_clean_external_html_text(eventforge_external_array_get($address, ['zip'])),
        'summary' => $summary,
        'description' => $description,
        'image_path' => eventforge_chambermate_image_url($item),
        'external_url' => eventforge_external_array_get($item, ['eventDetailUrl', 'eventUrl', 'registrationUrl', 'learnMoreURL']),
        'event_cost' => eventforge_chambermate_cost_text($item),
        'external_payload' => $payloadJson !== false ? $payloadJson : '',
    ];
}

function eventforge_normalize_external_event(string $provider, array $item): ?array
{
    switch ($provider) {
        case 'chambermate':
            return eventforge_normalize_chambermate_event($item);

        default:
            throw new RuntimeException('Unsupported external event provider.');
    }
}

function eventforge_external_address_for_geocoding(array $event): array
{
    return [
        'address_line_1' => (string) ($event['address_line_1'] ?? ''),
        'address_line_2' => (string) ($event['address_line_2'] ?? ''),
        'address_city' => (string) ($event['address_city'] ?? ''),
        'address_state' => (string) ($event['address_state'] ?? ''),
        'address_postal_code' => (string) ($event['address_postal_code'] ?? ''),
    ];
}

function eventforge_geocode_external_event(mysqli $connection, array $event): array
{
    $event['latitude'] = null;
    $event['longitude'] = null;

    $address = eventforge_external_address_for_geocoding($event);

    if (!eventforge_has_usable_address($address)) {
        return $event;
    }

    $token = eventforge_get_mapbox_geocoding_token($connection);

    if ($token === '') {
        return $event;
    }

    $query = eventforge_build_geocoding_query((string) ($event['location'] ?? ''), $address);
    $coordinates = eventforge_geocode_with_mapbox($token, $query);

    if ($coordinates === null) {
        return $event;
    }

    $event['latitude'] = $coordinates['latitude'];
    $event['longitude'] = $coordinates['longitude'];

    return $event;
}

function eventforge_external_event_hash(array $event): string
{
    $hashFields = [
        'title',
        'start_datetime',
        'end_datetime',
        'all_day',
        'location',
        'address_line_1',
        'address_line_2',
        'address_city',
        'address_state',
        'address_postal_code',
        'summary',
        'description',
        'image_path',
        'external_url',
        'event_cost',
        'latitude',
        'longitude',
    ];

    $data = [];

    foreach ($hashFields as $field) {
        $data[$field] = (string) ($event[$field] ?? '');
    }

    return hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
}

function eventforge_sql_nullable_string(mysqli $connection, $value): string
{
    if ($value === null || trim((string) $value) === '') {
        return 'NULL';
    }

    return "'" . mysqli_real_escape_string($connection, (string) $value) . "'";
}

function eventforge_sql_string(mysqli $connection, $value): string
{
    return "'" . mysqli_real_escape_string($connection, (string) $value) . "'";
}

function eventforge_sql_coordinate($value): string
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return 'NULL';
    }

    return number_format((float) $value, 7, '.', '');
}

function eventforge_find_existing_external_event(mysqli $connection, string $source, string $externalId): ?array
{
    $sourceSql = mysqli_real_escape_string($connection, $source);
    $externalIdSql = mysqli_real_escape_string($connection, $externalId);

    $result = mysqli_query($connection, "
        SELECT id, external_hash, slug
        FROM events
        WHERE external_source = '{$sourceSql}'
          AND external_id = '{$externalIdSql}'
        LIMIT 1
    ");

    if (!$result) {
        throw new RuntimeException('External event lookup failed: ' . mysqli_error($connection));
    }

    $row = mysqli_fetch_assoc($result);

    mysqli_free_result($result);

    return $row ?: null;
}

function eventforge_import_external_event(mysqli $connection, array $event): string
{
    $event = eventforge_geocode_external_event($connection, $event);

    $hash = eventforge_external_event_hash($event);
    $source = (string) $event['external_source'];
    $externalId = (string) $event['external_id'];
    $existing = eventforge_find_existing_external_event($connection, $source, $externalId);

    $title = (string) $event['title'];
    $start = (string) $event['start_datetime'];
    $end = $event['end_datetime'] !== null ? (string) $event['end_datetime'] : null;
    $allDay = (int) $event['all_day'];
    $location = (string) ($event['location'] ?? '');
    $address1 = (string) ($event['address_line_1'] ?? '');
    $address2 = (string) ($event['address_line_2'] ?? '');
    $city = (string) ($event['address_city'] ?? '');
    $state = (string) ($event['address_state'] ?? '');
    $postal = (string) ($event['address_postal_code'] ?? '');
    $summary = (string) ($event['summary'] ?? '');
    $description = (string) ($event['description'] ?? '');
    $imagePath = (string) ($event['image_path'] ?? '');
    $externalUrl = (string) ($event['external_url'] ?? '');
    $eventCost = (string) ($event['event_cost'] ?? '');
    $payload = (string) ($event['external_payload'] ?? '');
    $latitude = $event['latitude'] ?? null;
    $longitude = $event['longitude'] ?? null;

    $titleSql = eventforge_sql_string($connection, $title);
    $startSql = eventforge_sql_string($connection, $start);
    $endSql = eventforge_sql_nullable_string($connection, $end);
    $locationSql = eventforge_sql_string($connection, $location);
    $address1Sql = eventforge_sql_string($connection, $address1);
    $address2Sql = eventforge_sql_string($connection, $address2);
    $citySql = eventforge_sql_string($connection, $city);
    $stateSql = eventforge_sql_string($connection, $state);
    $postalSql = eventforge_sql_string($connection, $postal);
    $latitudeSql = eventforge_sql_coordinate($latitude);
    $longitudeSql = eventforge_sql_coordinate($longitude);
    $summarySql = eventforge_sql_string($connection, $summary);
    $descriptionSql = eventforge_sql_string($connection, $description);
    $imagePathSql = eventforge_sql_string($connection, $imagePath);
    $externalUrlSql = eventforge_sql_string($connection, $externalUrl);
    $eventCostSql = eventforge_sql_string($connection, $eventCost);
    $sourceSql = eventforge_sql_string($connection, $source);
    $externalIdSql = eventforge_sql_string($connection, $externalId);
    $hashSql = eventforge_sql_string($connection, $hash);
    $payloadSql = eventforge_sql_string($connection, $payload);

    if ($existing) {
        $eventId = (int) $existing['id'];

        if ((string) ($existing['external_hash'] ?? '') === $hash) {
            $sql = "
                UPDATE events
                SET external_synced_at = NOW(),
                    external_payload = {$payloadSql}
                WHERE id = {$eventId}
                LIMIT 1
            ";

            if (!mysqli_query($connection, $sql)) {
                throw new RuntimeException('External event touch update failed: ' . mysqli_error($connection));
            }

            return 'unchanged';
        }

        $slug = trim((string) ($existing['slug'] ?? ''));

        if ($slug === '') {
            $slug = eventforge_unique_event_slug($connection, $title, $eventId);
        }

        $slugSql = eventforge_sql_string($connection, $slug);

        $sql = "
            UPDATE events
            SET title = {$titleSql},
                slug = {$slugSql},
                start_datetime = {$startSql},
                end_datetime = {$endSql},
                all_day = {$allDay},
                location = {$locationSql},
                address_line_1 = {$address1Sql},
                address_line_2 = {$address2Sql},
                address_city = {$citySql},
                address_state = {$stateSql},
                address_postal_code = {$postalSql},
                latitude = {$latitudeSql},
                longitude = {$longitudeSql},
                summary = {$summarySql},
                description = {$descriptionSql},
                image_path = {$imagePathSql},
                external_url = {$externalUrlSql},
                event_cost = {$eventCostSql},
                external_hash = {$hashSql},
                external_payload = {$payloadSql},
                external_synced_at = NOW()
            WHERE id = {$eventId}
            LIMIT 1
        ";

        if (!mysqli_query($connection, $sql)) {
            throw new RuntimeException('External event update failed: ' . mysqli_error($connection));
        }

        return 'updated';
    }

    $slug = eventforge_unique_event_slug($connection, $title);
    $slugSql = eventforge_sql_string($connection, $slug);

    $sql = "
        INSERT INTO events (
            title, slug, start_datetime, end_datetime, all_day, location,
            address_line_1, address_line_2, address_city, address_state,
            address_postal_code, latitude, longitude, summary, description,
            image_path, external_url, event_cost, external_source, external_id,
            external_hash, external_payload, external_synced_at, is_published, is_canceled
        ) VALUES (
            {$titleSql}, {$slugSql}, {$startSql}, {$endSql}, {$allDay}, {$locationSql},
            {$address1Sql}, {$address2Sql}, {$citySql}, {$stateSql},
            {$postalSql}, {$latitudeSql}, {$longitudeSql}, {$summarySql}, {$descriptionSql},
            {$imagePathSql}, {$externalUrlSql}, {$eventCostSql}, {$sourceSql}, {$externalIdSql},
            {$hashSql}, {$payloadSql}, NOW(), 0, 0
        )
    ";

    if (!mysqli_query($connection, $sql)) {
        throw new RuntimeException('External event insert failed: ' . mysqli_error($connection));
    }

    return 'inserted';
}

function eventforge_sync_external_events(mysqli $connection): array
{
    if (!eventforge_external_events_enabled($connection)) {
        throw new RuntimeException('External event sync is not active.');
    }

    $provider = eventforge_get_external_events_provider($connection);
    $feedUrl = eventforge_get_external_events_feed_url($connection);

    if (!eventforge_external_event_provider_exists($provider)) {
        throw new RuntimeException('Unsupported external event provider.');
    }

    if ($feedUrl === '') {
        throw new RuntimeException('External event feed URL is not configured.');
    }

    $payload = eventforge_fetch_external_feed_json($feedUrl);
    $items = eventforge_extract_external_event_items($payload);

    $stats = [
        'provider' => $provider,
        'fetched' => count($items),
        'inserted' => 0,
        'updated' => 0,
        'unchanged' => 0,
        'skipped' => 0,
    ];

    foreach ($items as $item) {
        if (!is_array($item)) {
            $stats['skipped']++;
            continue;
        }

        $event = eventforge_normalize_external_event($provider, $item);

        if ($event === null) {
            $stats['skipped']++;
            continue;
        }

        $result = eventforge_import_external_event($connection, $event);

        if (isset($stats[$result])) {
            $stats[$result]++;
        }
    }

    eventforge_set_system_value($connection, 'external_events_last_sync_at', date('Y-m-d H:i:s'));
    eventforge_set_system_value($connection, 'external_events_last_sync_stats', json_encode($stats, JSON_UNESCAPED_SLASHES) ?: '');

    return $stats;
}
