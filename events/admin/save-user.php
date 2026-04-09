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

if (!can_manage_users()) {
    http_response_code(403);
    exit('Access denied.');
}

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$role = trim((string) ($_POST['role'] ?? 'staff'));

if ($username === '' || $password === '') {
    exit('Username and password are required.');
}

$passwordErrors = eventforge_validate_new_password($password);

if (!empty($passwordErrors)) {
    exit($passwordErrors[0]);
}

$allowedRoles = [];

if (can_create_staff_accounts()) {
    $allowedRoles[] = 'staff';
}

if (can_create_staff_manager_accounts()) {
    $allowedRoles[] = 'staff_manager';
}

if (is_admin()) {
    $allowedRoles[] = 'admin';
}

if (!in_array($role, $allowedRoles, true)) {
    $role = 'staff';
}

$usernameEsc = mysqli_real_escape_string($connection, $username);
$roleEsc = mysqli_real_escape_string($connection, $role);

$checkSql = "
    SELECT id
    FROM event_admin_users
    WHERE username = '{$usernameEsc}'
    LIMIT 1
";

$checkResult = mysqli_query($connection, $checkSql);

if (!$checkResult) {
    exit('User lookup failed: ' . mysqli_error($connection));
}

if (mysqli_num_rows($checkResult) > 0) {
    exit('That username already exists.');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

if ($passwordHash === false) {
    exit('Could not create password hash.');
}

$passwordHashEsc = mysqli_real_escape_string($connection, $passwordHash);

$sql = "
    INSERT INTO event_admin_users (
        username,
        password_hash,
        role,
        is_suspended
    ) VALUES (
        '{$usernameEsc}',
        '{$passwordHashEsc}',
        '{$roleEsc}',
        0
    )
";

if (!mysqli_query($connection, $sql)) {
    exit('User save failed: ' . mysqli_error($connection));
}

header('Location: ' . eventforge_admin_path('settings.php'));
exit;