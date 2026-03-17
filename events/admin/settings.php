<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

require_login();
require_admin();

$userSql = "
    SELECT id, username, role, created_at
    FROM event_admin_users
    ORDER BY
        CASE WHEN role = 'admin' THEN 0 ELSE 1 END,
        username ASC
";

$userResult = mysqli_query($connection, $userSql);

if (!$userResult) {
    exit('User query failed: ' . mysqli_error($connection));
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Event Forge Settings</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 2rem;
      background: #f5f7fa;
      color: #1f2937;
    }

    .wrap {
      max-width: 1000px;
      margin: 0 auto;
      background: #fff;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
    }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      flex-wrap: wrap;
      margin-bottom: 1.5rem;
    }

    .topbar-right {
      display: flex;
      align-items: center;
      gap: .75rem;
      flex-wrap: wrap;
    }

    .account-chip {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      background: #ffffff;
      border: 1px solid #d7dde5;
      border-radius: 999px;
      padding: .45rem .75rem;
    }

    .account-chip__name {
      font-weight: 600;
      color: #1f2937;
    }

    .account-chip__role {
      display: inline-block;
      background: #3f6244;
      color: #ffffff;
      font-size: .75rem;
      font-weight: 700;
      letter-spacing: .03em;
      border-radius: 999px;
      padding: .2rem .5rem;
    }

    .button {
      display: inline-block;
      padding: .5rem .8rem;
      border: 1px solid #333;
      text-decoration: none;
      background: #fff;
      color: #111;
      border-radius: 6px;
    }

    .settings-section {
      margin-top: 2rem;
      padding-top: 1.5rem;
      border-top: 1px solid #d7dde5;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
      margin-top: 1rem;
    }

    th, td {
      border: 1px solid #ddd;
      padding: .75rem;
      text-align: left;
      vertical-align: top;
    }

    th {
      background: #f0f3f6;
    }

    tr:nth-child(even) td {
      background: #fafbfc;
    }

    form {
      margin-top: 1rem;
    }

    label {
      display: block;
      margin: 1rem 0 .35rem;
      font-weight: 600;
    }

    input[type="text"],
    input[type="password"],
    select {
      width: 100%;
      padding: .7rem;
      box-sizing: border-box;
    }

    .form-actions {
      margin-top: 1.5rem;
    }

    .note {
      color: #4b5563;
      font-size: .95rem;
    }

    .role-badge {
      display: inline-block;
      border-radius: 999px;
      padding: .2rem .55rem;
      font-size: .75rem;
      font-weight: 700;
      letter-spacing: .03em;
    }

    .role-admin {
      background: #3f6244;
      color: #fff;
    }

    .role-staff {
      background: #dbe7dc;
      color: #1f2937;
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <h1>Settings</h1>

      <div class="topbar-right">
        <div class="account-chip">
          <span class="account-chip__name"><?= htmlspecialchars(current_admin_username()) ?></span>
          <span class="account-chip__role"><?= htmlspecialchars(strtoupper(current_admin_role())) ?></span>
        </div>

        <a class="button" href="/event-forge/events/admin/index.php">Back to Events</a>
        <a class="button" href="/event-forge/events/admin/logout.php">Log Out</a>
      </div>
    </div>

    <div class="settings-section">
      <h2>User Management</h2>
      <p class="note">Admins can create staff or additional admin accounts from here.</p>

      <table>
        <thead>
          <tr>
            <th>Username</th>
            <th>Role</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($user = mysqli_fetch_assoc($userResult)): ?>
            <tr>
              <td><?= htmlspecialchars((string) $user['username']) ?></td>
              <td>
                <span class="role-badge <?= $user['role'] === 'admin' ? 'role-admin' : 'role-staff' ?>">
                  <?= htmlspecialchars(strtoupper((string) $user['role'])) ?>
                </span>
              </td>
              <td><?= htmlspecialchars((string) $user['created_at']) ?></td>
              <td>
                <?php if ((string) $user['username'] !== current_admin_username()): ?>
                  <?php if ((string) $user['role'] === 'admin'): ?>
                    <a href="/event-forge/events/admin/toggle-user-role.php?id=<?= (int) $user['id'] ?>" onclick="return confirm('Change this admin to staff?');">Make Staff</a>
                  <?php else: ?>
                    <a href="/event-forge/events/admin/toggle-user-role.php?id=<?= (int) $user['id'] ?>" onclick="return confirm('Change this staff user to admin?');">Make Admin</a>
                  <?php endif; ?>
                  |
                  <a href="/event-forge/events/admin/delete-user.php?id=<?= (int) $user['id'] ?>" onclick="return confirm('Delete this user?');">Delete</a>
                <?php else: ?>
                  <em>Current account</em>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <div class="settings-section">
      <h2>Add User</h2>

      <form method="post" action="/event-forge/events/admin/save-user.php">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" required>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <label for="role">Role</label>
        <select id="role" name="role" required>
          <option value="staff">Staff</option>
          <option value="admin">Admin</option>
        </select>

        <div class="form-actions">
          <button type="submit">Create User</button>
        </div>
      </form>
    </div>

    <div class="settings-section">
      <h2>Feature Controls</h2>
      <div class="note">
        This area is reserved for module toggles, account-level settings, display defaults, branding options, and staff-only controls.
      </div>
    </div>
  </div>
</body>
</html>