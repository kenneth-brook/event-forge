<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/installer.php';

if (!eventforge_is_installed()) {
    header('Location: ' . eventforge_admin_path('setup.php'));
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/passwords.php';

require_login();

if (!is_admin()) {
    http_response_code(403);
    exit('Access denied.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . eventforge_admin_path('settings.php'));
    exit;
}

if (!eventforge_verify_csrf_token($_POST['csrf_token'] ?? null)) {
    $userId = (int) ($_POST['user_id'] ?? 0);
    header('Location: ' . eventforge_admin_path('change-user-password.php?id=' . $userId . '&status=csrf'));
    exit;
}

$userId = (int) ($_POST['user_id'] ?? 0);
$newPassword = (string) ($_POST['new_password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');

if ($userId <= 0) {
    exit('A valid user ID is required.');
}

if ($newPassword === '' || $confirmPassword === '') {
    header('Location: ' . eventforge_admin_path('change-user-password.php?id=' . $userId . '&status=required'));
    exit;
}

if ($newPassword !== $confirmPassword) {
    header('Location: ' . eventforge_admin_path('change-user-password.php?id=' . $userId . '&status=mismatch'));
    exit;
}

$errors = eventforge_validate_new_password($newPassword);
if (!empty($errors)) {
    header('Location: ' . eventforge_admin_path('change-user-password.php?id=' . $userId . '&status=weak&message=' . urlencode($errors[0])));
    exit;
}

$checkSql = "
    SELECT id
    FROM event_admin_users
    WHERE id = {$userId}
    LIMIT 1
";

$checkResult = mysqli_query($connection, $checkSql);

if (!$checkResult || mysqli_num_rows($checkResult) !== 1) {
    exit('User not found.');
}

$newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

if ($newPasswordHash === false) {
    header('Location: ' . eventforge_admin_path('change-user-password.php?id=' . $userId . '&status=hash'));
    exit;
}

$newPasswordHashEsc = mysqli_real_escape_string($connection, $newPasswordHash);

$updateSql = "
    UPDATE event_admin_users
    SET password_hash = '{$newPasswordHashEsc}'
    WHERE id = {$userId}
    LIMIT 1
";

if (!mysqli_query($connection, $updateSql)) {
    header('Location: ' . eventforge_admin_path('change-user-password.php?id=' . $userId . '&status=save'));
    exit;
}

header('Location: ' . eventforge_admin_path('change-user-password.php?id=' . $userId . '&status=success'));
exit;