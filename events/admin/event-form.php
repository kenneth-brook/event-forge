<?php
declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$event = [
    'id' => 0,
    'title' => '',
    'start_datetime' => '',
    'end_datetime' => '',
    'all_day' => 0,
    'location' => '',
    'summary' => '',
    'description' => '',
    'image_path' => '',
    'pdf_path' => '',
    'external_url' => '',
    'is_published' => 1,
];

if ($id > 0) {
    $sql = "SELECT * FROM events WHERE id = {$id} LIMIT 1";
    $result = mysqli_query($connection, $sql);

    if ($result && $row = mysqli_fetch_assoc($result)) {
        $event = $row;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $id > 0 ? 'Edit Event' : 'Add Event' ?></title>
  <style>
    body { font-family: Arial, sans-serif; padding: 2rem; }
    .wrap { max-width: 800px; margin: 0 auto; }
    label { display:block; margin: 1rem 0 .35rem; }
    input[type="text"], input[type="datetime-local"], input[type="url"], textarea {
      width: 100%;
      padding: .7rem;
    }
    textarea { min-height: 120px; }
    .actions { margin-top: 1.5rem; }
    .inline { display:flex; gap:1rem; align-items:center; margin-top:1rem; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1><?= $id > 0 ? 'Edit Event' : 'Add Event' ?></h1>
    <p><a href="/events/admin/index.php">← Back to events</a></p>

    <form method="post" action="/events/admin/save-event.php" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= (int)$event['id'] ?>">

      <label for="title">Title</label>
      <input id="title" name="title" type="text" required value="<?= htmlspecialchars((string)$event['title']) ?>">

      <label for="start_datetime">Start Date/Time</label>
      <input id="start_datetime" name="start_datetime" type="datetime-local"
        value="<?= !empty($event['start_datetime']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime((string)$event['start_datetime']))) : '' ?>">

      <label for="end_datetime">End Date/Time</label>
      <input id="end_datetime" name="end_datetime" type="datetime-local"
        value="<?= !empty($event['end_datetime']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime((string)$event['end_datetime']))) : '' ?>">

      <div class="inline">
        <label><input type="checkbox" name="all_day" value="1" <?= !empty($event['all_day']) ? 'checked' : '' ?>> All Day</label>
        <label><input type="checkbox" name="is_published" value="1" <?= !empty($event['is_published']) ? 'checked' : '' ?>> Published</label>
      </div>

      <label for="location">Location</label>
      <input id="location" name="location" type="text" value="<?= htmlspecialchars((string)$event['location']) ?>">

      <label for="summary">Summary</label>
      <textarea id="summary" name="summary"><?= htmlspecialchars((string)$event['summary']) ?></textarea>

      <label for="description">Description</label>
      <textarea id="description" name="description"><?= htmlspecialchars((string)$event['description']) ?></textarea>

      <label for="external_url">External URL</label>
      <input id="external_url" name="external_url" type="url" value="<?= htmlspecialchars((string)$event['external_url']) ?>">

      <label for="image">Image Upload</label>
      <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp">

      <?php if (!empty($event['image_path'])): ?>
        <p>Current image:</p>
        <p>
            <img
            src="<?= htmlspecialchars((string)$event['image_path']) ?>"
            alt="Current event image"
            style="max-width: 240px; height: auto; border-radius: 8px; display: block;"
            >
        </p>
        <?php endif; ?>

      <label for="pdf">PDF Upload</label>
      <input id="pdf" name="pdf" type="file" accept=".pdf">

      <?php if (!empty($event['pdf_path'])): ?>
        <p>
            Current PDF:
            <a href="<?= htmlspecialchars((string)$event['pdf_path']) ?>" target="_blank" rel="noopener">
            View current PDF
            </a>
        </p>
        <?php endif; ?>

      <div class="actions">
        <button type="submit">Save Event</button>
      </div>
    </form>
  </div>
</body>
</html>