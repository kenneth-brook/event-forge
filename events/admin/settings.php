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
require_once __DIR__ . '/../includes/theme.php';

require_login();

$canManageUsers = can_manage_users();
$canManageCalendarTheme = eventforge_can_manage_calendar_theme($connection);
$staffManagerThemeAllowed = eventforge_get_system_flag(
    $connection,
    'permissions_allow_staff_manager_calendar_theme',
    false
);
$calendarTheme = eventforge_get_calendar_theme($connection);
$themeDefinitions = eventforge_calendar_theme_definitions();

$status = trim((string) ($_GET['status'] ?? ''));

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
    SELECT id, name, slug, color, font_color, is_active, created_at
    FROM event_categories
    ORDER BY name ASC
");

if (!$categoryResult) {
    exit('Category query failed: ' . mysqli_error($connection));
}

$publicCalendarUrl = eventforge_get_system_value($connection, 'public_calendar_url') ?? '';
$mapboxPublicToken = eventforge_get_system_value($connection, 'mapbox_public_token') ?? '';
$mapboxGeocodingToken = eventforge_get_system_value($connection, 'mapbox_geocoding_token') ?? '';
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
      max-width: 1100px;
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
      cursor: pointer;
    }

    .button--secondary {
      border-color: #d7dde5;
    }

    .settings-section {
      margin-top: 2rem;
      padding-top: 1.5rem;
      border-top: 1px solid #d7dde5;
    }

    .notice {
      margin-bottom: 1rem;
      padding: .85rem 1rem;
      border-radius: 8px;
      background: #edf8ef;
      border: 1px solid #b7ddbe;
      color: #1f4d28;
      font-weight: 600;
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
    input[type="url"],
    select {
      width: 100%;
      padding: .7rem;
      box-sizing: border-box;
    }

    input[type="color"] {
      width: 100%;
      height: 2.9rem;
      border: 1px solid #d7dde5;
      border-radius: 8px;
      background: #fff;
      padding: .2rem;
      box-sizing: border-box;
    }

    .form-actions {
      margin-top: 1.5rem;
      display: flex;
      gap: .75rem;
      flex-wrap: wrap;
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

    .account-actions {
      margin-top: 1rem;
      display: flex;
      gap: .75rem;
      flex-wrap: wrap;
    }

    .toggle-card {
      border: 1px solid #d7dde5;
      border-radius: 10px;
      padding: 1rem;
      background: #fafbfc;
      margin-top: 1rem;
    }

    .toggle-row {
      display: flex;
      align-items: center;
      gap: .75rem;
      flex-wrap: wrap;
    }

    .theme-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 1rem;
      margin-top: 1rem;
    }

    .theme-field {
      border: 1px solid #d7dde5;
      border-radius: 10px;
      padding: 1rem;
      background: #fafbfc;
    }

    .theme-field label {
      margin-top: 0;
    }

    .theme-field__default {
      margin-top: .5rem;
      color: #4b5563;
      font-size: .85rem;
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

        <a class="button" href="<?= htmlspecialchars(eventforge_admin_path('change-password.php')) ?>">Change My Password</a>
        <a class="button" href="<?= htmlspecialchars(eventforge_admin_path('index.php')) ?>">Back to Events</a>
        <a class="button" href="<?= htmlspecialchars(eventforge_admin_path('logout.php')) ?>">Log Out</a>
      </div>
    </div>

    <?php if ($status === 'general-saved'): ?>
      <div class="notice">General setting saved.</div>
    <?php elseif ($status === 'permissions-saved'): ?>
      <div class="notice">Calendar theme permission setting saved.</div>
    <?php elseif ($status === 'theme-saved'): ?>
      <div class="notice">Calendar theme saved.</div>
    <?php elseif ($status === 'map-saved'): ?>
      <div class="notice">Map settings saved.</div>
    <?php endif; ?>

    <div class="settings-section">
      <h2>My Account</h2>
      <p class="note">
        Manage your own account credentials here.
      </p>

      <div class="account-actions">
        <a class="button" href="<?= htmlspecialchars(eventforge_admin_path('change-password.php')) ?>">Change My Password</a>
      </div>
    </div>

    <?php if ($canManageUsers): ?>
      <div class="settings-section">
        <h2>User Management</h2>
        <p class="note">
          <?php if (is_admin()): ?>
            Admins can create, manage, suspend, unsuspend, delete, and change passwords for staff, staff manager, and admin accounts.
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
              $targetId = (int) $user['id'];
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
                    <a href="<?= htmlspecialchars(eventforge_admin_path('change-password.php')) ?>">Change Password</a>
                    |
                    <em>Current account</em>

                  <?php elseif (is_admin()): ?>
                    <a href="<?= htmlspecialchars(eventforge_admin_path('change-user-password.php')) ?>?id=<?= $targetId ?>">
                      Change Password
                    </a>
                    |

                    <?php if ($targetRole === 'staff'): ?>
                      <a href="<?= htmlspecialchars(eventforge_admin_path('make-staff-manager.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Promote this staff account to staff manager?');">
                        Make Staff Manager
                      </a>
                      |
                      <a href="<?= htmlspecialchars(eventforge_admin_path('make-admin.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Promote this account to admin?');">
                        Make Admin
                      </a>
                      |
                      <?php if ($isSuspended): ?>
                        <a href="<?= htmlspecialchars(eventforge_admin_path('unsuspend-user.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Unsuspend this account?');">
                          Unsuspend
                        </a>
                      <?php else: ?>
                        <a href="<?= htmlspecialchars(eventforge_admin_path('suspend-user.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Suspend this account?');">
                          Suspend
                        </a>
                      <?php endif; ?>
                      |
                      <a href="<?= htmlspecialchars(eventforge_admin_path('delete-user.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Delete this user?');">
                        Delete
                      </a>

                    <?php elseif ($targetRole === 'staff_manager'): ?>
                      <a href="<?= htmlspecialchars(eventforge_admin_path('make-staff.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Change this staff manager back to staff?');">
                        Make Staff
                      </a>
                      |
                      <a href="<?= htmlspecialchars(eventforge_admin_path('make-admin.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Promote this account to admin?');">
                        Make Admin
                      </a>
                      |
                      <?php if ($isSuspended): ?>
                        <a href="<?= htmlspecialchars(eventforge_admin_path('unsuspend-user.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Unsuspend this account?');">
                          Unsuspend
                        </a>
                      <?php else: ?>
                        <a href="<?= htmlspecialchars(eventforge_admin_path('suspend-user.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Suspend this account?');">
                          Suspend
                        </a>
                      <?php endif; ?>
                      |
                      <a href="<?= htmlspecialchars(eventforge_admin_path('delete-user.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Delete this user?');">
                        Delete
                      </a>

                    <?php elseif ($targetRole === 'admin'): ?>
                      <a href="<?= htmlspecialchars(eventforge_admin_path('make-staff-manager.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Change this admin to staff manager?');">
                        Make Staff Manager
                      </a>
                      |
                      <a href="<?= htmlspecialchars(eventforge_admin_path('make-staff.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Change this admin to staff?');">
                        Make Staff
                      </a>
                      |
                      <?php if ($isSuspended): ?>
                        <a href="<?= htmlspecialchars(eventforge_admin_path('unsuspend-user.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Unsuspend this account?');">
                          Unsuspend
                        </a>
                      <?php else: ?>
                        <a href="<?= htmlspecialchars(eventforge_admin_path('suspend-user.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Suspend this account?');">
                          Suspend
                        </a>
                      <?php endif; ?>
                      |
                      <a href="<?= htmlspecialchars(eventforge_admin_path('delete-user.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Delete this user?');">
                        Delete
                      </a>

                    <?php else: ?>
                      <em>No actions</em>
                    <?php endif; ?>

                  <?php elseif (is_staff_manager()): ?>
                    <?php if ($targetRole === 'staff'): ?>
                      <?php if ($isSuspended): ?>
                        <a href="<?= htmlspecialchars(eventforge_admin_path('unsuspend-user.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Unsuspend this account?');">
                          Unsuspend
                        </a>
                      <?php else: ?>
                        <a href="<?= htmlspecialchars(eventforge_admin_path('suspend-user.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Suspend this account?');">
                          Suspend
                        </a>
                      <?php endif; ?>
                      |
                      <a href="<?= htmlspecialchars(eventforge_admin_path('delete-user.php')) ?>?id=<?= $targetId ?>" onclick="return confirm('Delete this user?');">
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
          Use the password option above to manage your account credentials.
        </p>

        <div class="account-actions">
          <a class="button" href="<?= htmlspecialchars(eventforge_admin_path('change-password.php')) ?>">Change My Password</a>
        </div>
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
      <h2>Calendar Theme</h2>
      <p class="note">
        These colors are stored as centralized system settings so the calendar UI, event modal, and future branding controls can all read from one source of truth.
      </p>

      <?php if (is_admin()): ?>
        <div class="toggle-card">
          <form method="post" action="<?= htmlspecialchars(eventforge_admin_path('save-system-setting.php')) ?>">
            <input type="hidden" name="setting_key" value="permissions_allow_staff_manager_calendar_theme">
            <input type="hidden" name="setting_value" value="0">

            <div class="toggle-row">
              <label style="margin:0;">
                <input
                  type="checkbox"
                  name="setting_value"
                  value="1"
                  <?= $staffManagerThemeAllowed ? 'checked' : '' ?>
                >
                Allow staff managers to manage calendar color settings
              </label>

              <button type="submit" class="button">Save Permission</button>
            </div>
          </form>
        </div>
      <?php endif; ?>

      <?php if ($canManageCalendarTheme): ?>
        <form method="post" action="<?= htmlspecialchars(eventforge_admin_path('save-calendar-theme.php')) ?>">
          <div class="theme-grid">
            <?php foreach ($themeDefinitions as $key => $definition): ?>
              <?php
              $currentValue = (string) ($calendarTheme[$key] ?? $definition['default']);
              $defaultValue = (string) $definition['default'];
              ?>
              <div class="theme-field">
                <label for="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars((string) $definition['label']) ?></label>
                <input
                  id="<?= htmlspecialchars($key) ?>"
                  name="<?= htmlspecialchars($key) ?>"
                  type="color"
                  value="<?= htmlspecialchars($currentValue) ?>"
                  data-default-color="<?= htmlspecialchars($defaultValue) ?>"
                  required
                >
                <div class="note"><?= htmlspecialchars((string) $definition['description']) ?></div>
                <div class="theme-field__default">Default: <?= htmlspecialchars($defaultValue) ?></div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="form-actions">
            <button type="submit" class="button">Save Calendar Theme</button>
            <button type="button" class="button button--secondary" id="theme-defaults-button">Use Defaults</button>
          </div>
        </form>
      <?php elseif (is_staff_manager()): ?>
        <p class="note">
          Your administrator has not enabled calendar theme management for staff managers.
        </p>
      <?php else: ?>
        <p class="note">
          Calendar theme controls are available to administrators.
        </p>
      <?php endif; ?>
    </div>

    <div class="settings-section">
      <h2>General Controls</h2>

      <?php if (is_admin()): ?>
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

    <div class="settings-section">
      <h2>Map Location</h2>

      <?php if (is_admin()): ?>
        <form method="post" action="<?= htmlspecialchars(eventforge_admin_path('save-system-setting.php')) ?>">
          <input type="hidden" name="settings_group" value="map-settings">

          <label for="mapbox_public_token">Mapbox Public Token</label>
          <input
            id="mapbox_public_token"
            name="mapbox_public_token"
            type="text"
            value="<?= htmlspecialchars($mapboxPublicToken) ?>"
            autocomplete="off"
          >

          <label for="mapbox_geocoding_token">Mapbox Geocoding Token</label>
          <input
            id="mapbox_geocoding_token"
            name="mapbox_geocoding_token"
            type="password"
            value="<?= htmlspecialchars($mapboxGeocodingToken) ?>"
            autocomplete="new-password"
          >

          <p class="note">
            The public token is used in the browser for the map display. The geocoding token is used server-side only when an event is saved with a usable address.
          </p>

          <div class="form-actions">
            <button type="submit">Save Map Settings</button>
          </div>
        </form>
      <?php else: ?>
        <p class="note">
          Map and geocoding credentials can be managed by administrators.
        </p>
      <?php endif; ?>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const defaultsButton = document.getElementById('theme-defaults-button');

      if (!defaultsButton) {
        return;
      }

      defaultsButton.addEventListener('click', function () {
        document.querySelectorAll('input[type="color"][data-default-color]').forEach(function (input) {
          input.value = input.getAttribute('data-default-color') || '#FFFFFF';
        });
      });
    });
  </script>
</body>
</html>