<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

require_login();
require_admin();

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role = trim($_POST['role'] ?? 'staff');

if ($username === '' || $password === '') {
    exit('Username and password are required.');
}

if (!in_array($role, ['admin', 'staff'], true)) {
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

if ($checkResult && mysqli_num_rows($checkResult) > 0) {
    exit('That username already exists.');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$passwordHashEsc = mysqli_real_escape_string($connection, $passwordHash);

$sql = "
    INSERT INTO event_admin_users (
        username,
        password_hash,
        role
    ) VALUES (
        '{$usernameEsc}',
        '{$passwordHashEsc}',
        '{$roleEsc}'
    )
";

if (!mysqli_query($connection, $sql)) {
    exit('User save failed: ' . mysqli_error($connection));
}

header('Location: /event-forge/events/admin/settings.php');
exit;