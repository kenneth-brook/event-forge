<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
eventforge_require_post_csrf();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id > 0) {
    mysqli_query($connection, "
        UPDATE events
        SET is_canceled = 0
        WHERE id = {$id}
          AND (is_recurring_parent = 0 OR is_recurring_parent IS NULL)
        LIMIT 1
    ");
}

header('Location: ' . eventforge_admin_path('index.php'));
exit;
