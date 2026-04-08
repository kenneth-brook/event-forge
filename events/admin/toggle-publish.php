<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    $sql = "
        UPDATE events
        SET is_published = CASE WHEN is_published = 1 THEN 0 ELSE 1 END
        WHERE id = {$id}
        LIMIT 1
    ";

    mysqli_query($connection, $sql);
}

header('Location: /event-forge/events/admin/index.php');
exit;