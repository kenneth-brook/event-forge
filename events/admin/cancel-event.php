<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $sql = "
        SELECT id, parent_event_id, is_recurring_parent, is_independent_child
        FROM events
        WHERE id = {$id}
        LIMIT 1
    ";

    $result = mysqli_query($connection, $sql);

    if ($result && $row = mysqli_fetch_assoc($result)) {
        $isParent = !empty($row['is_recurring_parent']);
        $hasParent = !empty($row['parent_event_id']);
        $isIndependent = !empty($row['is_independent_child']);

        // If this is a generated child, promote it to independent first
        if ($hasParent && !$isIndependent && !$isParent) {
            mysqli_query($connection, "
                UPDATE events
                SET is_independent_child = 1
                WHERE id = {$id}
                LIMIT 1
            ");
        }

        // Do not allow canceling recurring parent rows from this action
        if (!$isParent) {
            mysqli_query($connection, "
                UPDATE events
                SET is_canceled = 1
                WHERE id = {$id}
                LIMIT 1
            ");
        }
    }
}

header('Location: /events/admin/index.php');
exit;