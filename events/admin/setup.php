<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/installer.php';

$error = '';
$step = 'db';

try {
    if (eventforge_config_exists()) {
        $config = eventforge_load_db_config();

        if ($config && eventforge_can_connect_with_config($config)) {
            $connection = eventforge_connect_with_config($config);

            if (eventforge_required_tables_exist($connection)) {
                $step = eventforge_admin_exists($connection) ? 'done' : 'admin';
            } else {
                $step = 'db';
            }

            mysqli_close($connection);
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if ($step === 'done') {
    header('Location: /event-forge/events/admin/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formStep = $_POST['setup_step'] ?? '';

    try {
        if ($formStep === 'db') {
            $config = [
                'host' => trim($_POST['db_host'] ?? ''),
                'name' => trim($_POST['db_name'] ?? ''),
                'user' => trim($_POST['db_user'] ?? ''),
                'pass' => (string) ($_POST['db_pass'] ?? ''),
                'charset' => 'utf8mb4',
                'port' => trim($_POST['db_port'] ?? '3306'),
            ];

            if ($config['host'] === '' || $config['name'] === '' || $config['user'] === '') {
                throw new RuntimeException('Host, database name, and username are required.');
            }

            eventforge_write_db_config($config);
            eventforge_run_initial_schema($config);

            header('Location: /event-forge/events/admin/setup.php?step=admin');
            exit;
        }

        if ($formStep === 'admin') {
            $username = trim($_POST['username'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

            if ($username === '' || $password === '') {
                throw new RuntimeException('Username and password are required.');
            }

            if ($password !== $confirmPassword) {
                throw new RuntimeException('Passwords do not match.');
            }

            $connection = eventforge_bootstrap_connection();

            if (!$connection) {
                throw new RuntimeException('Database is not configured.');
            }

            $usernameEsc = mysqli_real_escape_string($connection, $username);

            $checkSql = "
                SELECT id
                FROM event_admin_users
                WHERE username = '{$usernameEsc}'
                LIMIT 1
            ";

            $checkResult = mysqli_query($connection, $checkSql);

            if ($checkResult && mysqli_num_rows($checkResult) > 0) {
                throw new RuntimeException('That username already exists.');
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
                    'admin'
                )
            ";

            if (!mysqli_query($connection, $sql)) {
                throw new RuntimeException('Could not create admin user: ' . mysqli_error($connection));
            }

            mysqli_close($connection);

            header('Location: /event-forge/events/admin/login.php');
            exit;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (isset($_GET['step']) && $_GET['step'] === 'admin') {
    $step = 'admin';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Event Forge Setup</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 2rem;
      background: #f5f7fa;
      color: #1f2937;
    }

    .wrap {
      max-width: 700px;
      margin: 0 auto;
      background: #fff;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
    }

    h1 {
      margin-top: 0;
    }

    label {
      display: block;
      margin: 1rem 0 .35rem;
      font-weight: 600;
    }

    input[type="text"],
    input[type="password"],
    input[type="number"] {
      width: 100%;
      padding: .7rem;
      box-sizing: border-box;
    }

    .actions {
      margin-top: 1.5rem;
    }

    .error {
      color: #b00020;
      margin-bottom: 1rem;
      font-weight: 600;
    }

    .note {
      color: #4b5563;
      font-size: .95rem;
    }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Event Forge Setup</h1>

    <?php if ($error !== ''): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($step === 'db'): ?>
      <p class="note">Enter database connection details. Event Forge will write the configuration and create the required tables.</p>

      <form method="post">
        <input type="hidden" name="setup_step" value="db">

        <label for="db_host">Database Host</label>
        <input id="db_host" name="db_host" type="text" required>

        <label for="db_name">Database Name</label>
        <input id="db_name" name="db_name" type="text" required>

        <label for="db_user">Database Username</label>
        <input id="db_user" name="db_user" type="text" required>

        <label for="db_pass">Database Password</label>
        <input id="db_pass" name="db_pass" type="password">

        <label for="db_port">Database Port</label>
        <input id="db_port" name="db_port" type="number" value="3306" required>

        <div class="actions">
          <button type="submit">Save Database and Create Tables</button>
        </div>
      </form>
    <?php elseif ($step === 'admin'): ?>
      <p class="note">Create the first administrator account for this Event Forge installation.</p>

      <form method="post">
        <input type="hidden" name="setup_step" value="admin">

        <label for="username">Admin Username</label>
        <input id="username" name="username" type="text" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <label for="confirm_password">Confirm Password</label>
        <input id="confirm_password" name="confirm_password" type="password" required>

        <div class="actions">
          <button type="submit">Create Admin Account</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>