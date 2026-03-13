<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

require_login();
require_admin();

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
        if ((string) $user['username'] !== current_admin_username()) {
            $newRole = ((string) $user['role'] === 'admin') ? 'staff' : 'admin';
            $newRoleEsc = mysqli_real_escape_string($connection, $newRole);

            mysqli_query($connection, "
                UPDATE event_admin_users
                SET role = '{$newRoleEsc}'
                WHERE id = {$id}
                LIMIT 1
            ");
        }
    }
}

header('Location: /events/admin/settings.php');
exit;