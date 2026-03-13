<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

require_login();

$sql = "
    SELECT id, title, start_datetime, end_datetime, is_published
    FROM events
    ORDER BY
        CASE WHEN start_datetime >= NOW() THEN 0 ELSE 1 END,
        start_datetime ASC
";
$result = mysqli_query($connection, $sql);
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
</style>
</head>
<body>
  <div class="topbar">
    <h1>Manage Events</h1>
    <div>
      <a class="button" href="/events/admin/event-form.php">Add Event</a>
      <a class="button" href="/events/admin/logout.php">Log Out</a>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Title</th>
        <th>Start</th>
        <th>End</th>
        <th>Published</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <tr>
          <td><?= htmlspecialchars($row['title']) ?></td>
          <td><?= htmlspecialchars($row['start_datetime']) ?></td>
          <td><?= htmlspecialchars((string) $row['end_datetime']) ?></td>
          <td>
            <?= (int)$row['is_published'] ? 'Yes' : 'No' ?>
            |
            <a href="/events/admin/toggle-publish.php?id=<?= (int)$row['id'] ?>">
                <?= (int)$row['is_published'] ? 'Unpublish' : 'Publish' ?>
            </a>
            </td>
            <td>
                <a href="/events/admin/event-form.php?id=<?= (int)$row['id'] ?>">Edit</a> |
                <a href="/events/admin/delete-event.php?id=<?= (int)$row['id'] ?>" onclick="return confirm('Delete this event?');">Delete</a>
            </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</body>
</html>