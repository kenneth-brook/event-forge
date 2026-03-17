<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $sql = "
        UPDATE events
        SET is_independent_child = 1
        WHERE id = {$id}
          AND parent_event_id IS NOT NULL
          AND (is_recurring_parent = 0 OR is_recurring_parent IS NULL)
        LIMIT 1
    ";

    mysqli_query($connection, $sql);
}

header('Location: /event-forge/events/admin/index.php');
exit;