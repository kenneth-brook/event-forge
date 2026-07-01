<?php
declare(strict_types=1);

require_once __DIR__ . '/installer.php';

function eventforge_render_upcoming_events_component(array $options = []): string
{
    $id = isset($options['id']) && trim((string) $options['id']) !== ''
        ? trim((string) $options['id'])
        : 'eventforge-upcoming-events';

    $title = isset($options['title'])
        ? trim((string) $options['title'])
        : 'Upcoming Events';

    $limit = isset($options['limit']) ? (int) $options['limit'] : 10;
    $limit = max(1, min(100, $limit));

    $maxHeight = isset($options['max_height']) && trim((string) $options['max_height']) !== ''
        ? trim((string) $options['max_height'])
        : '420px';

    $width = isset($options['width']) && trim((string) $options['width']) !== ''
        ? trim((string) $options['width'])
        : 'auto';

    $includeCanceled = !empty($options['include_canceled']);

    $includeAssets = array_key_exists('include_assets', $options)
        ? (bool) $options['include_assets']
        : true;

    $autoScroll = array_key_exists('auto_scroll', $options)
        ? (bool) $options['auto_scroll']
        : true;

    $pauseOnHover = array_key_exists('pause_on_hover', $options)
        ? (bool) $options['pause_on_hover']
        : true;

    $scrollSpeed = isset($options['scroll_speed'])
        ? (float) $options['scroll_speed']
        : 1.0;

    $scrollSpeed = max(0.25, min(10.0, $scrollSpeed));

    $minScrollItems = isset($options['min_scroll_items'])
        ? (int) $options['min_scroll_items']
        : 3;

    $minScrollItems = max(2, min(25, $minScrollItems));

    $display = isset($options['display']) && trim((string) $options['display']) !== ''
        ? trim((string) $options['display'])
        : 'upcoming';

    $source = isset($options['source']) && trim((string) $options['source']) !== ''
        ? trim((string) $options['source'])
        : eventforge_public_path('api.php');

    $emptyMessage = isset($options['empty_message']) && trim((string) $options['empty_message']) !== ''
        ? trim((string) $options['empty_message'])
        : 'No upcoming events are currently scheduled.';

    $classes = isset($options['class']) && trim((string) $options['class']) !== ''
        ? ' ' . trim((string) $options['class'])
        : '';

    $attrs = [
        'id' => $id,
        'class' => 'eventforge-upcoming-events' . $classes,
        'data-eventforge-upcoming' => 'true',
        'data-source' => $source,
        'data-display' => $display,
        'data-limit' => (string) $limit,
        'data-empty-message' => $emptyMessage,
        'data-auto-scroll' => $autoScroll ? 'true' : 'false',
        'data-pause-on-hover' => $pauseOnHover ? 'true' : 'false',
        'data-scroll-speed' => rtrim(rtrim(number_format($scrollSpeed, 2, '.', ''), '0'), '.'),
        'data-min-scroll-items' => (string) $minScrollItems,
        'style' => '--ef-upcoming-max-height: ' . $maxHeight . '; --ef-upcoming-width: ' . $width . ';',
    ];

    if ($includeCanceled) {
        $attrs['data-include-canceled'] = 'true';
    }

    $attrText = '';

    foreach ($attrs as $key => $value) {
        $attrText .= ' ' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8')
            . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
    }

    ob_start();

    if ($includeAssets) {
        ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(eventforge_asset_path('css/upcoming-events.css'), ENT_QUOTES, 'UTF-8') ?>">
        <?php
    }
    ?>

    <section<?= $attrText ?>>
        <?php if ($title !== ''): ?>
            <h2 class="eventforge-upcoming-events__heading">
                <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
            </h2>
        <?php endif; ?>

        <div class="eventforge-upcoming-events__status" data-upcoming-status>
            Loading upcoming events...
        </div>

        <div
            class="eventforge-upcoming-events__list"
            data-upcoming-list
            aria-live="polite"
            hidden
        ></div>
    </section>

    <?php
    if ($includeAssets) {
        ?>
        <script src="<?= htmlspecialchars(eventforge_asset_path('js/upcoming-events.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
        <?php
    }

    return trim((string) ob_get_clean());
}
