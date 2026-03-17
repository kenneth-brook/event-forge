<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $checkSql = "
        SELECT id, is_recurring_parent
        FROM events
        WHERE id = {$id}
        LIMIT 1
    ";

    $checkResult = mysqli_query($connection, $checkSql);

    if ($checkResult && $row = mysqli_fetch_assoc($checkResult)) {
        $isRecurringParent = !empty($row['is_recurring_parent']);

        if ($isRecurringParent) {
            // Delete all children tied to this recurring parent first
            mysqli_query($connection, "
                DELETE FROM events
                WHERE parent_event_id = {$id}
            ");
        }

        // Delete the selected row itself
        mysqli_query($connection, "
            DELETE FROM events
            WHERE id = {$id}
            LIMIT 1
        ");
    }
}

header('Location: /event-forge/events/admin/index.php');
exit;