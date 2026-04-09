<?php
declare(strict_types=1);

require_once __DIR__ . '/system.php';
require_once __DIR__ . '/auth.php';

function eventforge_calendar_theme_definitions(): array
{
    return [
        'theme_calendar_surface_color' => [
            'label' => 'Calendar Surface',
            'description' => 'Main calendar background or card surface.',
            'default' => '#FFFFFF',
        ],
        'theme_calendar_text_color' => [
            'label' => 'Calendar Text',
            'description' => 'Default text color used across the calendar UI.',
            'default' => '#1F2937',
        ],
        'theme_calendar_border_color' => [
            'label' => 'Calendar Borders',
            'description' => 'Border and divider color for calendar sections.',
            'default' => '#D7DDE5',
        ],
        'theme_calendar_header_background_color' => [
            'label' => 'Header Background',
            'description' => 'Top toolbar or calendar header background.',
            'default' => '#3F6244',
        ],
        'theme_calendar_header_text_color' => [
            'label' => 'Header Text',
            'description' => 'Text color used on the calendar header background.',
            'default' => '#FFFFFF',
        ],
        'theme_calendar_button_background_color' => [
            'label' => 'Button Background',
            'description' => 'Default action button background color.',
            'default' => '#FFFFFF',
        ],
        'theme_calendar_button_text_color' => [
            'label' => 'Button Text',
            'description' => 'Default action button text color.',
            'default' => '#111111',
        ],
        'theme_calendar_today_highlight_color' => [
            'label' => 'Today Highlight',
            'description' => 'Highlight color used for today or active date emphasis.',
            'default' => '#F3BE11',
        ],
        'theme_calendar_modal_gradient_start_color' => [
            'label' => 'Modal Gradient Start',
            'description' => 'Starting color for the event modal background gradient.',
            'default' => '#167151',
        ],
        'theme_calendar_modal_gradient_end_color' => [
            'label' => 'Modal Gradient End',
            'description' => 'Ending color for the event modal background gradient.',
            'default' => '#3F6244',
        ],
        'theme_calendar_modal_text_color' => [
            'label' => 'Modal Text',
            'description' => 'Text color for event detail modal or detail panel.',
            'default' => '#FFFFFF',
        ],
    ];
}

function eventforge_calendar_theme_defaults(): array
{
    $defaults = [];

    foreach (eventforge_calendar_theme_definitions() as $key => $definition) {
        $defaults[$key] = (string) $definition['default'];
    }

    return $defaults;
}

function eventforge_normalize_hex_color(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    if (!preg_match('/^#(?:[0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/', $value)) {
        return null;
    }

    return strtoupper($value);
}

function eventforge_get_calendar_theme(mysqli $connection): array
{
    $theme = eventforge_calendar_theme_defaults();

    foreach (eventforge_calendar_theme_definitions() as $key => $definition) {
        $stored = eventforge_get_system_value($connection, $key);

        if ($stored === null || trim($stored) === '') {
            continue;
        }

        $normalized = eventforge_normalize_hex_color($stored);

        if ($normalized !== null) {
            $theme[$key] = $normalized;
        }
    }

    return $theme;
}

function eventforge_calendar_theme_to_css_variables(array $theme): array
{
    return [
        '--ef-calendar-surface-color' => (string) ($theme['theme_calendar_surface_color'] ?? '#FFFFFF'),
        '--ef-calendar-text-color' => (string) ($theme['theme_calendar_text_color'] ?? '#1F2937'),
        '--ef-calendar-border-color' => (string) ($theme['theme_calendar_border_color'] ?? '#D7DDE5'),
        '--ef-calendar-header-background-color' => (string) ($theme['theme_calendar_header_background_color'] ?? '#3F6244'),
        '--ef-calendar-header-text-color' => (string) ($theme['theme_calendar_header_text_color'] ?? '#FFFFFF'),
        '--ef-calendar-button-background-color' => (string) ($theme['theme_calendar_button_background_color'] ?? '#FFFFFF'),
        '--ef-calendar-button-text-color' => (string) ($theme['theme_calendar_button_text_color'] ?? '#111111'),
        '--ef-calendar-today-highlight-color' => (string) ($theme['theme_calendar_today_highlight_color'] ?? '#F3BE11'),
        '--ef-calendar-modal-gradient-start-color' => (string) ($theme['theme_calendar_modal_gradient_start_color'] ?? '#167151'),
        '--ef-calendar-modal-gradient-end-color' => (string) ($theme['theme_calendar_modal_gradient_end_color'] ?? '#3F6244'),
        '--ef-calendar-modal-text-color' => (string) ($theme['theme_calendar_modal_text_color'] ?? '#FFFFFF'),
    ];
}

function eventforge_can_manage_calendar_theme(mysqli $connection): bool
{
    if (is_admin()) {
        return true;
    }

    if (is_staff_manager()) {
        return eventforge_get_system_flag(
            $connection,
            'permissions_allow_staff_manager_calendar_theme',
            false
        );
    }

    return false;
}