<?php
declare(strict_types=1);

require __DIR__ . '/../includes/installer.php';

if (!eventforge_is_installed()) {
    header('Location: /event-forge/events/admin/setup.php');
    exit;
}

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    header('Location: /event-forge/events/admin/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $usernameSafe = mysqli_real_escape_string($connection, $username);

    $sql = "SELECT id, username, password_hash, role FROM event_admin_users WHERE username = '{$usernameSafe}' LIMIT 1";
    $result = mysqli_query($connection, $sql);

    if ($result && $user = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['events_admin_logged_in'] = true;
            $_SESSION['events_admin_username'] = $user['username'];
            $_SESSION['events_admin_role'] = $user['role'] ?? 'staff';

            header('Location: /event-forge/events/admin/index.php');
            exit;
        }
    }

    $error = 'Invalid username or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Events Admin Login</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 2rem; }
    .wrap { max-width: 420px; margin: 3rem auto; }
    label { display:block; margin: 1rem 0 .35rem; }
    input { width:100%; padding:.7rem; }
    button { margin-top:1rem; padding:.7rem 1rem; }
    .error { color: #b00020; margin-top: 1rem; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Events Admin</h1>

    <?php if ($error): ?>
      <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post">
      <label for="username">Username</label>
      <input id="username" name="username" required>

      <label for="password">Password</label>
      <input id="password" name="password" type="password" required>

      <button type="submit">Log In</button>
    </form>
  </div>
</body>
</html>