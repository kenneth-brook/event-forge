<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0) {
    mysqli_query($connection, "DELETE FROM events WHERE id = {$id} LIMIT 1");
}

header('Location: /events/admin/index.php');
exit;