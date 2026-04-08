<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    mysqli_query($connection, "
        UPDATE events
        SET is_canceled = 0
        WHERE id = {$id}
          AND (is_recurring_parent = 0 OR is_recurring_parent IS NULL)
        LIMIT 1
    ");
}

header('Location: /event-forge/events/admin/index.php');
exit;