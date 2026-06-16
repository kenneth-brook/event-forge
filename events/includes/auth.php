<?php
declare(strict_types=1);

require_once __DIR__ . '/passwords.php';

session_start();

function is_logged_in(): bool
{
    return !empty($_SESSION['events_admin_logged_in']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        require_once __DIR__ . '/installer.php';
        header('Location: ' . eventforge_admin_path('login.php'));
        exit;
    }
}

function current_admin_username(): string
{
    return (string) ($_SESSION['events_admin_username'] ?? '');
}

function current_admin_role(): string
{
    return (string) ($_SESSION['events_admin_role'] ?? 'staff');
}

function is_admin(): bool
{
    return current_admin_role() === 'admin';
}

function is_staff_manager(): bool
{
    return current_admin_role() === 'staff_manager';
}

function can_manage_users(): bool
{
    return in_array(current_admin_role(), ['staff_manager', 'admin'], true);
}

function can_create_staff_accounts(): bool
{
    return in_array(current_admin_role(), ['staff_manager', 'admin'], true);
}

function can_create_staff_manager_accounts(): bool
{
    return is_admin();
}

function can_manage_staff_accounts(): bool
{
    return in_array(current_admin_role(), ['staff_manager', 'admin'], true);
}

function can_manage_admin_accounts(): bool
{
    return is_admin();
}

function require_admin(): void
{
    if (!is_admin()) {
        http_response_code(403);
        exit('Access denied.');
    }
}

function eventforge_csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(eventforge_csrf_token(), ENT_QUOTES, 'UTF-8')
        . '">';
}

function eventforge_require_post_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }

    if (!eventforge_verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Security token check failed.');
    }
}

function eventforge_admin_post_action(
    string $file,
    int $id,
    string $label,
    string $confirmMessage = '',
    string $title = ''
): string {
    $action = htmlspecialchars(eventforge_admin_path($file), ENT_QUOTES, 'UTF-8');
    $labelEsc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $titleAttr = $title !== ''
        ? ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"'
        : '';
    $confirmJson = $confirmMessage !== '' ? json_encode($confirmMessage) : '';
    $confirmAttr = $confirmMessage !== ''
        ? ' onclick="return confirm(' . htmlspecialchars($confirmJson !== false ? $confirmJson : '""', ENT_QUOTES, 'UTF-8') . ');"'
        : '';

    return '<form class="inline-action" method="post" action="' . $action . '">'
        . eventforge_csrf_input()
        . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="link-button"' . $titleAttr . $confirmAttr . '>'
        . $labelEsc
        . '</button>'
        . '</form>';
}
