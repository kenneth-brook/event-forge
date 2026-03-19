<?php
declare(strict_types=1);

require __DIR__ . '/../includes/installer.php';

if (!eventforge_is_installed()) {
    header('Location: ' . eventforge_admin_path('setup.php'));
    exit;
}

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

require_login();

if (!can_manage_users()) {
    http_response_code(403);
    exit('Access denied.');
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $sql = "
        SELECT id, username, role
        FROM event_admin_users
        WHERE id = {$id}
        LIMIT 1
    ";

    $result = mysqli_query($connection, $sql);

    if ($result && $user = mysqli_fetch_assoc($result)) {
        $targetUsername = (string) $user['username'];
        $targetRole = (string) $user['role'];

        $canManageTarget = false;

        if (is_admin()) {
            $canManageTarget = $targetUsername !== current_admin_username()
                && in_array($targetRole, ['staff', 'staff_manager', 'admin'], true);
        } elseif (is_staff_manager()) {
            $canManageTarget = $targetUsername !== current_admin_username()
                && $targetRole === 'staff';
        }

        if ($canManageTarget) {
            mysqli_query($connection, "
                UPDATE event_admin_users
                SET is_suspended = 0
                WHERE id = {$id}
                LIMIT 1
            ");
        }
    }
}

header('Location: ' . eventforge_admin_path('settings.php'));
exit;