<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

require_login();

$sql = "
    SELECT
        id,
        title,
        start_datetime,
        end_datetime,
        is_published,
        is_recurring_parent,
        parent_event_id,
        is_independent_child,
        is_canceled
    FROM events
    ORDER BY
        CASE
            WHEN is_recurring_parent = 1 THEN id
            WHEN parent_event_id IS NOT NULL THEN parent_event_id
            ELSE id
        END ASC,
        CASE
            WHEN is_recurring_parent = 1 THEN 0
            WHEN parent_event_id IS NOT NULL THEN 1
            ELSE 0
        END ASC,
        start_datetime ASC
";

$result = mysqli_query($connection, $sql);

if (!$result) {
    exit('Query failed: ' . mysqli_error($connection));
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Events</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 2rem;
      background: #f5f7fa;
      color: #1f2937;
    }

    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      gap: 1rem;
      flex-wrap: wrap;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
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

    .child-row td {
      background: #f8fbf8;
    }

    .child-row td:first-child {
      border-left: 4px solid #3f6244;
    }

    .independent-child-row td {
      background: #f4fbf7;
    }

    .independent-child-row td:first-child {
      border-left: 4px solid #167151;
    }

    .series-toggle {
      background: none;
      border: 0;
      cursor: pointer;
      font-size: 1rem;
      margin-right: .35rem;
      color: #3f6244;
      font-weight: bold;
    }

    a.button {
      display: inline-block;
      padding: .5rem .8rem;
      border: 1px solid #333;
      text-decoration: none;
      background: #fff;
      color: #111;
      border-radius: 6px;
    }

    a {
      color: #0b5cab;
    }

    .status-canceled {
      color: #c62828;
      font-weight: bold;
      margin-left: .5rem;
    }

    .title-canceled {
      text-decoration: line-through;
      opacity: .75;
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
  </style>
</head>
<body>
  <div class="topbar">
  <h1>Manage Events</h1>

  <div class="topbar-right">
    <div class="account-chip">
      <span class="account-chip__name"><?= htmlspecialchars(current_admin_username()) ?></span>
      <span class="account-chip__role"><?= htmlspecialchars(strtoupper(current_admin_role())) ?></span>
    </div>

    <a class="button" href="/event-forge/events/admin/event-form.php">Add Event</a>

    <?php if (is_admin()): ?>
      <a class="button" href="/event-forge/events/admin/settings.php">Settings</a>
    <?php endif; ?>

    <a class="button" href="/event-forge/events/admin/logout.php">Log Out</a>
  </div>
</div>

  <table>
    <thead>
      <tr>
        <th>Series</th>
        <th>Title</th>
        <th>Start</th>
        <th>End</th>
        <th>Published</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <?php
        $isParent = !empty($row['is_recurring_parent']);
        $hasParent = !empty($row['parent_event_id']);
        $isIndependent = !empty($row['is_independent_child']);
        $isCanceled = !empty($row['is_canceled']);
        ?>
        <tr
          <?php if ($hasParent && !$isParent): ?>
            class="child-row<?= $isIndependent ? ' independent-child-row' : '' ?>"
            data-child-of="<?= (int) $row['parent_event_id'] ?>"
            style="display:none;"
          <?php endif; ?>
        >
          <td>
            <?php if ($isParent): ?>
              <button
                type="button"
                class="series-toggle"
                data-parent-id="<?= (int) $row['id'] ?>"
                aria-expanded="false"
                title="Show or hide generated child events"
              >▸</button>
              <span title="Recurring parent event. Editing this updates the generated child events." style="color:#f3be11; font-weight:bold;">
                ★ Parent
              </span>

            <?php elseif ($hasParent && $isIndependent): ?>
              <span title="Independent child event. Originally generated by a recurring parent, now maintained separately." style="color:#167151; padding-left:1.5rem; font-weight:bold;">
                ↳ Independent
              </span>

            <?php elseif ($hasParent): ?>
              <span title="Generated child event from a recurring parent." style="color:#3f6244; padding-left:1.5rem;">
                ↳ Child
              </span>

            <?php else: ?>
              <span title="Single standalone event.">Single</span>
            <?php endif; ?>
          </td>

          <td>
            <?php if ($isCanceled): ?>
              <span class="title-canceled"><?= htmlspecialchars((string) $row['title']) ?></span>
              <span class="status-canceled">CANCELED</span>
            <?php else: ?>
              <?= htmlspecialchars((string) $row['title']) ?>
            <?php endif; ?>
          </td>

          <td><?= htmlspecialchars((string) $row['start_datetime']) ?></td>
          <td><?= htmlspecialchars((string) ($row['end_datetime'] ?? '')) ?></td>

          <td>
            <?= !empty($row['is_published']) ? 'Yes' : 'No' ?>
            |
            <a href="/event-forge/events/admin/toggle-publish.php?id=<?= (int) $row['id'] ?>">
              <?= !empty($row['is_published']) ? 'Unpublish' : 'Publish' ?>
            </a>
          </td>

          <td>
            <?php if ($hasParent && !$isIndependent && !$isParent): ?>
              <div><em>Generated from series</em></div>
              <div>
                <a
                  href="/event-forge/events/admin/make-independent.php?id=<?= (int) $row['id'] ?>"
                  title="This child will remain grouped under the parent series, but future series updates will not overwrite it."
                  onclick="return confirm('Make this generated child independent? Future parent changes will no longer overwrite this event.');"
                >
                  Make Independent
                </a>
                |
                <?php if ($isCanceled): ?>
                  <a href="/event-forge/events/admin/uncancel-event.php?id=<?= (int) $row['id'] ?>" onclick="return confirm('Mark this event as active again?');">
                    Uncancel
                  </a>
                <?php else: ?>
                  <a href="/event-forge/events/admin/cancel-event.php?id=<?= (int) $row['id'] ?>" onclick="return confirm('Cancel this event? This will also make it independent from the series.');">
                    Cancel
                  </a>
                <?php endif; ?>
              </div>

            <?php elseif ($isParent): ?>
              <a href="/event-forge/events/admin/event-form.php?id=<?= (int) $row['id'] ?>">Edit</a> |
              <a href="/event-forge/events/admin/delete-event.php?id=<?= (int) $row['id'] ?>" onclick="return confirm('Delete this event and its generated children?');">Delete</a>

            <?php else: ?>
              <a href="/event-forge/events/admin/event-form.php?id=<?= (int) $row['id'] ?>">Edit</a> |
              <?php if ($isCanceled): ?>
                <a href="/event-forge/events/admin/uncancel-event.php?id=<?= (int) $row['id'] ?>" onclick="return confirm('Mark this event as active again?');">Uncancel</a> |
              <?php else: ?>
                <a href="/event-forge/events/admin/cancel-event.php?id=<?= (int) $row['id'] ?>" onclick="return confirm('Cancel this event?');">Cancel</a> |
              <?php endif; ?>
              <a href="/event-forge/events/admin/delete-event.php?id=<?= (int) $row['id'] ?>" onclick="return confirm('Delete this event?');">Delete</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const toggles = document.querySelectorAll('.series-toggle');

      toggles.forEach((toggle) => {
        toggle.addEventListener('click', () => {
          const parentId = toggle.dataset.parentId;
          const childRows = document.querySelectorAll(`.child-row[data-child-of="${parentId}"]`);
          const isExpanded = toggle.getAttribute('aria-expanded') === 'true';

          childRows.forEach((row) => {
            row.style.display = isExpanded ? 'none' : 'table-row';
          });

          toggle.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
          toggle.textContent = isExpanded ? '▸' : '▾';
        });
      });
    });
  </script>
</body>
</html>