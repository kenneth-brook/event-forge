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

$userId = (int) ($_GET['id'] ?? 0);

if ($userId <= 0) {
    exit('A valid user ID is required.');
}

$userSql = "
    SELECT id, username, role, is_suspended
    FROM event_admin_users
    WHERE id = {$userId}
    LIMIT 1
";

$userResult = mysqli_query($connection, $userSql);

if (!$userResult || mysqli_num_rows($userResult) !== 1) {
    exit('User not found.');
}

$user = mysqli_fetch_assoc($userResult);

$status = trim((string) ($_GET['status'] ?? ''));
$error = '';
$message = '';

if ($status === 'success') {
    $message = 'Password updated successfully.';
} elseif ($status === 'required') {
    $error = 'Password and confirmation are required.';
} elseif ($status === 'mismatch') {
    $error = 'Password and confirmation do not match.';
} elseif ($status === 'hash') {
    $error = 'Could not create password hash.';
} elseif ($status === 'save') {
    $error = 'Password update failed.';
} elseif ($status === 'csrf') {
    $error = 'Security token check failed. Please try again.';
} elseif ($status === 'weak') {
    $error = trim((string) ($_GET['message'] ?? 'Password does not meet requirements.'));
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Change User Password | Event Forge Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 24px;
            color: #222;
            background: #f5f7fa;
        }

        .wrap {
            max-width: 680px;
            margin: 0 auto;
            background: #fff;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
        }

        .card {
            border: 1px solid #d7dde5;
            border-radius: 8px;
            padding: 20px;
            background: #fff;
        }

        .notice {
            padding: 12px 14px;
            border-radius: 6px;
            margin-bottom: 16px;
        }

        .notice.error {
            background: #fdeaea;
            color: #8a1f1f;
            border: 1px solid #f4b8b8;
        }

        .notice.success {
            background: #eaf7ea;
            color: #1d6b2f;
            border: 1px solid #b9e0c0;
        }

        label {
            display: block;
            margin: 1rem 0 .35rem;
            font-weight: 600;
        }

        input[type="text"] {
            width: 100%;
            padding: .7rem;
            box-sizing: border-box;
            border: 1px solid #bbb;
            border-radius: 6px;
        }

        .help {
            font-size: 14px;
            color: #555;
            margin-top: .35rem;
            margin-bottom: 14px;
        }

        .password-box {
            background: #f7f7f7;
            border: 1px solid #ddd;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 16px;
            word-break: break-all;
            display: none;
        }

        .meta {
            margin-bottom: 16px;
        }

        .row {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }

        .button {
            display: inline-block;
            padding: .5rem .8rem;
            border: 1px solid #333;
            text-decoration: none;
            background: #fff;
            color: #111;
            border-radius: 6px;
            cursor: pointer;
        }

        .button-primary {
            background: #3f6244;
            border-color: #3f6244;
            color: #fff;
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Admin Password Change</h1>

    <div class="meta">
        <strong>User:</strong> <?= htmlspecialchars((string) $user['username']) ?><br>
        <strong>Role:</strong> <?= htmlspecialchars((string) $user['role']) ?><br>
        <strong>Suspended:</strong> <?= ((int) ($user['is_suspended'] ?? 0) === 1) ? 'Yes' : 'No' ?>
    </div>

    <?php if ($error !== ''): ?>
        <div class="notice error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($message !== ''): ?>
        <div class="notice success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card">
        <div id="generated-password-box" class="password-box">
            <strong>Generated password:</strong><br>
            <span id="generated-password-text"></span>
        </div>

        <form method="post" action="<?= htmlspecialchars(eventforge_admin_path('save-user-password.php')) ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(eventforge_csrf_token()) ?>">
            <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">

            <label for="new_password">New Password</label>
            <input
                type="text"
                id="new_password"
                name="new_password"
                autocomplete="new-password"
                required
            >
            <div class="help">Use at least <?= eventforge_password_min_length() ?> characters with upper, lower, number, and special character.</div>

            <label for="confirm_password">Confirm New Password</label>
            <input
                type="text"
                id="confirm_password"
                name="confirm_password"
                autocomplete="new-password"
                required
            >

            <div class="row">
                <button class="button button-primary" type="submit">Save Password</button>
                <button class="button" type="button" id="generate-password-button">Generate Password</button>
                <a class="button" href="<?= htmlspecialchars(eventforge_admin_path('settings.php')) ?>">Back</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const button = document.getElementById('generate-password-button');
    const passwordBox = document.getElementById('generated-password-box');
    const passwordText = document.getElementById('generated-password-text');

    function randomChar(chars) {
        const array = new Uint32Array(1);
        window.crypto.getRandomValues(array);
        return chars[array[0] % chars.length];
    }

    function shuffleString(value) {
        const chars = value.split('');
        for (let i = chars.length - 1; i > 0; i--) {
            const array = new Uint32Array(1);
            window.crypto.getRandomValues(array);
            const j = array[0] % (i + 1);
            const temp = chars[i];
            chars[i] = chars[j];
            chars[j] = temp;
        }
        return chars.join('');
    }

    function generatePassword(length) {
        const lower = 'abcdefghjkmnpqrstuvwxyz';
        const upper = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        const numbers = '23456789';
        const symbols = '!@#$%^&*()-_=+[]{}';
        const all = lower + upper + numbers + symbols;

        let password = '';
        password += randomChar(lower);
        password += randomChar(upper);
        password += randomChar(numbers);
        password += randomChar(symbols);

        while (password.length < length) {
            password += randomChar(all);
        }

        return shuffleString(password);
    }

    button.addEventListener('click', function () {
        const password = generatePassword(16);
        newPasswordInput.value = password;
        confirmPasswordInput.value = password;
        passwordText.textContent = password;
        passwordBox.style.display = 'block';
    });
})();
</script>
</body>
</html>