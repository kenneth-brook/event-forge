<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/installer.php';

if (!eventforge_is_installed()) {
    header('Location: ' . eventforge_admin_path('setup.php'));
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/system.php';

require_login();

$canManageUsers = can_manage_users();

$userResult = null;

if ($canManageUsers) {
    $userSql = "
        SELECT id, username, role, is_suspended, created_at
        FROM event_admin_users
        ORDER BY
            CASE
                WHEN role = 'admin' THEN 0
                WHEN role = 'staff_manager' THEN 1
                ELSE 2
            END,
            username ASC
    ";

    $userResult = mysqli_query($connection, $userSql);

    if (!$userResult) {
        exit('User query failed: ' . mysqli_error($connection));
    }
}

$categoryResult = mysqli_query($connection, "
    SELECT id, name, slug, color, is_active, created_at
    FROM event_categories
    ORDER BY name ASC
");

if (!$categoryResult) {
    exit('Category query failed: ' . mysqli_error($connection));
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

    .role-manager {
      background: #d7ecde;
      color: #1f2937;
    }

    .role-staff {
      background: #e8edf3;
      color: #1f2937;
    }

    .status-suspended {
      display: inline-block;
      color: #c62828;
      font-size: .8rem;
      font-weight: 700;
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
          <span class="account-chip__role"><?= htmlspecialchars(strtoupper(str_replace('_', ' ', current_admin_role()))) ?></span>
        </div>

        <a class="button" href="<?= htmlspecialchars(eventforge_admin_path('index.php')) ?>">Back to Events</a>
        <a class="button" href="<?= htmlspecialchars(eventforge_admin_path('logout.php')) ?>">Log Out</a>
      </div>
    </div>

    <?php if ($canManageUsers): ?>
      <div class="settings-section">
        <h2>User Management</h2>
        <p class="note">
          <?php if (is_admin()): ?>
            Admins can create, manage, suspend, unsuspend, and delete staff, staff manager, and admin accounts.
          <?php elseif (is_staff_manager()): ?>
            Staff managers can create, suspend, unsuspend, and delete staff accounts.
          <?php endif; ?>
        </p>

        <table>
          <thead>
            <tr>
              <th>Username</th>
              <th>Role</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($user = mysqli_fetch_assoc($userResult)): ?>
              <?php
              $targetUsername = (string) $user['username'];
              $targetRole = (string) $user['role'];
              $isSuspended = !empty($user['is_suspended']);
              $isCurrentUser = $targetUsername === current_admin_username();
              ?>
              <tr>
                <td><?= htmlspecialchars($targetUsername) ?></td>
                <td>
                  <span class="role-badge <?=
                    $targetRole === 'admin' ? 'role-admin' :
                    ($targetRole === 'staff_manager' ? 'role-manager' : 'role-staff')
                  ?>">
                    <?= htmlspecialchars(strtoupper(str_replace('_', ' ', $targetRole))) ?>
                  </span>
                </td>
                <td>
                  <?php if ($isSuspended): ?>
                    <span class="status-suspended">SUSPENDED</span>
                  <?php else: ?>
                    Active
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string) $user['created_at']) ?></td>
                <td>
                  <?php if ($isCurrentUser): ?>
                    <em>Current account</em>

                  <?php elseif (is_admin()): ?>
                    <?php if ($targetRole === 'staff'): ?>
                      <a href="<?= htmlspecialchars(eventforge_admin_path('toggle-user-role.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Promote this staff account to staff manager?');">
                        Make Staff Manager
                      </a>
                      |
                      <a href="<?= htmlspecialchars(eventforge_admin_path('make-admin.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Promote this account to admin?');">
                        Make Admin
                      </a>
                      |
                      <?php if ($isSuspended): ?>
                        <a href="<?= htmlspecialchars(eventforge_admin_path('unsuspend-user.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Unsuspend this account?');">
                          Unsuspend
                        </a>
                      <?php else: ?>
                        <a href="<?= htmlspecialchars(eventforge_admin_path('suspend-user.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Suspend this account?');">
                          Suspend
                        </a>
                      <?php endif; ?>
                      |
                      <a href="<?= htmlspecialchars(eventforge_admin_path('delete-user.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Delete this user?');">
                        Delete
                      </a>

                    <?php elseif ($targetRole === 'staff_manager'): ?>
                      <a href="<?= htmlspecialchars(eventforge_admin_path('make-staff.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Change this staff manager back to staff?');">
                        Make Staff
                      </a>
                      |
                      <a href="<?= htmlspecialchars(eventforge_admin_path('make-admin.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Promote this account to admin?');">
                        Make Admin
                      </a>
                      |
                      <?php if ($isSuspended): ?>
                        <a href="<?= htmlspecialchars(eventforge_admin_path('unsuspend-user.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Unsuspend this account?');">
                          Unsuspend
                        </a>
                      <?php else: ?>
                        <a href="<?= htmlspecialchars(eventforge_admin_path('suspend-user.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Suspend this account?');">
                          Suspend
                        </a>
                      <?php endif; ?>
                      |
                      <a href="<?= htmlspecialchars(eventforge_admin_path('delete-user.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Delete this user?');">
                        Delete
                      </a>

                    <?php elseif ($targetRole === 'admin'): ?>
                      <a href="<?= htmlspecialchars(eventforge_admin_path('make-staff-manager.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Change this admin to staff manager?');">
                        Make Staff Manager
                      </a>
                      |
                      <a href="<?= htmlspecialchars(eventforge_admin_path('make-staff.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Change this admin to staff?');">
                        Make Staff
                      </a>
                      |
                      <?php if ($isSuspended): ?>
                        <a href="<?= htmlspecialchars(eventforge_admin_path('unsuspend-user.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Unsuspend this account?');">
                          Unsuspend
                        </a>
                      <?php else: ?>
                        <a href="<?= htmlspecialchars(eventforge_admin_path('suspend-user.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Suspend this account?');">
                          Suspend
                        </a>
                      <?php endif; ?>
                      |
                      <a href="<?= htmlspecialchars(eventforge_admin_path('delete-user.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Delete this user?');">
                        Delete
                      </a>

                    <?php else: ?>
                      <em>No actions</em>
                    <?php endif; ?>

                  <?php elseif (is_staff_manager()): ?>
                    <?php if ($targetRole === 'staff'): ?>
                      <?php if ($isSuspended): ?>
                        <a href="<?= htmlspecialchars(eventforge_admin_path('unsuspend-user.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Unsuspend this account?');">
                          Unsuspend
                        </a>
                      <?php else: ?>
                        <a href="<?= htmlspecialchars(eventforge_admin_path('suspend-user.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Suspend this account?');">
                          Suspend
                        </a>
                      <?php endif; ?>
                      |
                      <a href="<?= htmlspecialchars(eventforge_admin_path('delete-user.php')) ?>?id=<?= (int) $user['id'] ?>" onclick="return confirm('Delete this user?');">
                        Delete
                      </a>
                    <?php else: ?>
                      <em>No actions</em>
                    <?php endif; ?>

                  <?php else: ?>
                    <em>No actions</em>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <div class="settings-section">
        <h2>Add User</h2>

        <form method="post" action="<?= htmlspecialchars(eventforge_admin_path('save-user.php')) ?>">
          <label for="username">Username</label>
          <input id="username" name="username" type="text" required>

          <label for="password">Password</label>
          <input id="password" name="password" type="password" required>

          <label for="role">Role</label>
          <select id="role" name="role" required>
            <?php if (can_create_staff_accounts()): ?>
              <option value="staff">Staff</option>
            <?php endif; ?>

            <?php if (can_create_staff_manager_accounts()): ?>
              <option value="staff_manager">Staff Manager</option>
            <?php endif; ?>

            <?php if (is_admin()): ?>
              <option value="admin">Admin</option>
            <?php endif; ?>
          </select>

          <div class="form-actions">
            <button type="submit">Create User</button>
          </div>
        </form>
      </div>
    <?php else: ?>
      <div class="settings-section">
        <h2>Account</h2>
        <p class="note">
          Account-level controls for your profile will be available here in a future update.
        </p>
      </div>
    <?php endif; ?>

    <div class="settings-section">
      <h2>Event Categories</h2>
      <p class="note">
        Categories can be assigned to events and used for color-coded display.
      </p>

      <p style="margin-top:1rem;">
        <a class="button" href="<?= htmlspecialchars(eventforge_admin_path('category-form.php')) ?>">Add Category</a>
      </p>

      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Slug</th>
            <th>Color</th>
            <th>Font</th>
            <th>Status</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($category = mysqli_fetch_assoc($categoryResult)): ?>
            <tr>
              <td><?= htmlspecialchars((string) $category['name']) ?></td>
              <td><?= htmlspecialchars((string) $category['slug']) ?></td>

              <td>
                <?php if (!empty($category['color'])): ?>
                  <span style="display:inline-block;width:18px;height:18px;border-radius:50%;background:<?= htmlspecialchars((string) $category['color']) ?>;vertical-align:middle;margin-right:.5rem;border:1px solid #ccc;"></span>
                  <?= htmlspecialchars((string) $category['color']) ?>
                <?php else: ?>
                  <em>None</em>
                <?php endif; ?>
              </td>

              <td>
                <?php if (!empty($category['font_color'])): ?>
                  <span style="display:inline-block;width:18px;height:18px;border-radius:50%;background:<?= htmlspecialchars((string) $category['font_color']) ?>;vertical-align:middle;margin-right:.5rem;border:1px solid #ccc;"></span>
                  <?= htmlspecialchars((string) $category['font_color']) ?>
                <?php else: ?>
                  <em>None</em>
                <?php endif; ?>
              </td>

              <td><?= !empty($category['is_active']) ? 'Active' : 'Inactive' ?></td>
              <td><?= htmlspecialchars((string) $category['created_at']) ?></td>
              <td>
                <a href="<?= htmlspecialchars(eventforge_admin_path('category-form.php')) ?>?id=<?= (int) $category['id'] ?>">Edit</a>
                |
                <a href="<?= htmlspecialchars(eventforge_admin_path('delete-category.php')) ?>?id=<?= (int) $category['id'] ?>" onclick="return confirm('Delete this category?');">Delete</a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>

    <div class="settings-section">
      <h2>General Controls</h2>

      <?php if (is_admin()): ?>
        <?php $publicCalendarUrl = eventforge_get_system_value($connection, 'public_calendar_url') ?? ''; ?>

        <form method="post" action="<?= htmlspecialchars(eventforge_admin_path('save-system-setting.php')) ?>">
          <input type="hidden" name="setting_key" value="public_calendar_url">

          <label for="public_calendar_url">Public Calendar Page URL</label>
          <input
            id="public_calendar_url"
            name="setting_value"
            type="url"
            value="<?= htmlspecialchars($publicCalendarUrl) ?>"
            placeholder="https://demo.365dtm.com/index.html"
          >

          <p class="note">
            This is the public page that contains the calendar. Shared event links will open this page and automatically display the matching event modal.
          </p>

          <div class="form-actions">
            <button type="submit">Save Public Calendar URL</button>
          </div>
        </form>
      <?php else: ?>
        <p class="note">
          Additional administrative configuration options are available to your administrator.
        </p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>