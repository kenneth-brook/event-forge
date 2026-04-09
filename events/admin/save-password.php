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

$currentUsername = current_admin_username();

if ($currentUsername === '') {
    http_response_code(403);
    exit('Access denied.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . eventforge_admin_path('change-password.php'));
    exit;
}

if (!eventforge_verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ' . eventforge_admin_path('change-password.php?status=csrf'));
    exit;
}

$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    header('Location: ' . eventforge_admin_path('change-password.php?status=required'));
    exit;
}

if ($newPassword !== $confirmPassword) {
    header('Location: ' . eventforge_admin_path('change-password.php?status=mismatch'));
    exit;
}

$errors = eventforge_validate_new_password($newPassword);
if (!empty($errors)) {
    header('Location: ' . eventforge_admin_path('change-password.php?status=weak&message=' . urlencode($errors[0])));
    exit;
}

$currentUsernameEsc = mysqli_real_escape_string($connection, $currentUsername);

$userSql = "
    SELECT id, username, password_hash, is_suspended
    FROM event_admin_users
    WHERE username = '{$currentUsernameEsc}'
    LIMIT 1
";

$userResult = mysqli_query($connection, $userSql);

if (!$userResult || mysqli_num_rows($userResult) !== 1) {
    header('Location: ' . eventforge_admin_path('change-password.php?status=save'));
    exit;
}

$user = mysqli_fetch_assoc($userResult);

if ((int) ($user['is_suspended'] ?? 0) === 1) {
    http_response_code(403);
    exit('Access denied.');
}

$existingHash = (string) ($user['password_hash'] ?? '');

if ($existingHash === '' || !password_verify($currentPassword, $existingHash)) {
    header('Location: ' . eventforge_admin_path('change-password.php?status=current'));
    exit;
}

if (password_verify($newPassword, $existingHash)) {
    header('Location: ' . eventforge_admin_path('change-password.php?status=same'));
    exit;
}

$newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

if ($newPasswordHash === false) {
    header('Location: ' . eventforge_admin_path('change-password.php?status=hash'));
    exit;
}

$newPasswordHashEsc = mysqli_real_escape_string($connection, $newPasswordHash);
$userId = (int) $user['id'];

$updateSql = "
    UPDATE event_admin_users
    SET password_hash = '{$newPasswordHashEsc}'
    WHERE id = {$userId}
    LIMIT 1
";

if (!mysqli_query($connection, $updateSql)) {
    header('Location: ' . eventforge_admin_path('change-password.php?status=save'));
    exit;
}

session_regenerate_id(true);

header('Location: ' . eventforge_admin_path('change-password.php?status=success'));
exit;