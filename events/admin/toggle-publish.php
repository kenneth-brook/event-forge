<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
eventforge_require_post_csrf();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id > 0) {
    $sql = "
        UPDATE events
        SET is_published = CASE WHEN is_published = 1 THEN 0 ELSE 1 END
        WHERE id = {$id}
        LIMIT 1
    ";

    mysqli_query($connection, $sql);
}

header('Location: ' . eventforge_admin_path('index.php'));
exit;
