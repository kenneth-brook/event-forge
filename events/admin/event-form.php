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

    // Recurrence fields
    'parent_event_id' => '',
    'is_recurring_parent' => 0,
    'recurrence_type' => '',
    'recurrence_interval' => 1,
    'recurrence_days' => '',
    'recurrence_week_of_month' => '',
    'recurrence_day_of_week' => '',
    'recurrence_end_date' => '',
    'recurrence_count' => '',
    'recurrence_instance_date' => '',
];

if ($id > 0) {
    $sql = "SELECT * FROM events WHERE id = {$id} LIMIT 1";
    $result = mysqli_query($connection, $sql);

    if ($result && $row = mysqli_fetch_assoc($result)) {
        $event = array_merge($event, $row);
    }
}

$selectedDays = !empty($event['recurrence_days'])
    ? array_map('trim', explode(',', (string) $event['recurrence_days']))
    : [];

$weekdayOptions = [
    'SU' => 'Sunday',
    'MO' => 'Monday',
    'TU' => 'Tuesday',
    'WE' => 'Wednesday',
    'TH' => 'Thursday',
    'FR' => 'Friday',
    'SA' => 'Saturday',
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $id > 0 ? 'Edit Event' : 'Add Event' ?></title>
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 2rem;
      background: #f5f7fa;
      color: #1f2937;
    }

    .wrap {
      max-width: 900px;
      margin: 0 auto;
      background: #fff;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
    }

    label {
      display: block;
      margin: 1rem 0 .35rem;
      font-weight: 600;
    }

    input[type="text"],
    input[type="datetime-local"],
    input[type="date"],
    input[type="number"],
    input[type="url"],
    select,
    textarea {
      width: 100%;
      padding: .7rem;
      box-sizing: border-box;
    }

    textarea {
      min-height: 120px;
    }

    .actions {
      margin-top: 1.5rem;
    }

    .inline {
      display: flex;
      gap: 1rem;
      align-items: center;
      margin-top: 1rem;
      flex-wrap: wrap;
    }

    .inline label {
      margin: 0;
      font-weight: 400;
    }

    .checkbox-grid {
      display: flex;
      flex-wrap: wrap;
      gap: .75rem 1rem;
      margin-top: .5rem;
    }

    .checkbox-grid label {
      margin: 0;
      font-weight: 400;
    }

    hr {
      margin: 2rem 0;
      border: 0;
      border-top: 1px solid #d7dde5;
    }

    h2 {
      margin: 0 0 1rem;
      font-size: 1.25rem;
    }

    .note {
      margin-top: .75rem;
      color: #4b5563;
      font-size: .95rem;
    }

    .preview-image {
      max-width: 240px;
      height: auto;
      border-radius: 8px;
      display: block;
      margin-top: .5rem;
    }
  </style>
</head>
<body>
  <div class="wrap">
    <h1><?= $id > 0 ? 'Edit Event' : 'Add Event' ?></h1>
    <p><a href="/event-forge/events/admin/index.php">← Back to events</a></p>

    <form method="post" action="/event-forge/events/admin/save-event.php" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">

      <label for="title">Title</label>
      <input
        id="title"
        name="title"
        type="text"
        required
        value="<?= htmlspecialchars((string) $event['title']) ?>"
      >

      <label for="start_datetime">Start Date/Time</label>
      <input
        id="start_datetime"
        name="start_datetime"
        type="datetime-local"
        value="<?= !empty($event['start_datetime']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime((string) $event['start_datetime']))) : '' ?>"
      >

      <label for="end_datetime">End Date/Time</label>
      <input
        id="end_datetime"
        name="end_datetime"
        type="datetime-local"
        value="<?= !empty($event['end_datetime']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime((string) $event['end_datetime']))) : '' ?>"
      >

      <div class="inline">
        <label>
          <input type="checkbox" name="all_day" value="1" <?= !empty($event['all_day']) ? 'checked' : '' ?>>
          All Day
        </label>

        <label>
          <input type="checkbox" name="is_published" value="1" <?= !empty($event['is_published']) ? 'checked' : '' ?>>
          Published
        </label>
      </div>

      <label for="location">Location</label>
      <input
        id="location"
        name="location"
        type="text"
        value="<?= htmlspecialchars((string) $event['location']) ?>"
      >

      <label for="summary">Summary</label>
      <textarea id="summary" name="summary"><?= htmlspecialchars((string) $event['summary']) ?></textarea>

      <label for="description">Description</label>
      <textarea id="description" name="description"><?= htmlspecialchars((string) $event['description']) ?></textarea>

      <label for="external_url">External URL</label>
      <input
        id="external_url"
        name="external_url"
        type="url"
        value="<?= htmlspecialchars((string) $event['external_url']) ?>"
      >

      <label for="image">Image Upload</label>
      <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp">

      <?php if (!empty($event['image_path'])): ?>
        <p>Current image:</p>
        <img
          class="preview-image"
          src="<?= htmlspecialchars((string) $event['image_path']) ?>"
          alt="Current event image"
        >
      <?php endif; ?>

      <label for="pdf">PDF Upload</label>
      <input id="pdf" name="pdf" type="file" accept=".pdf">

      <?php if (!empty($event['pdf_path'])): ?>
        <p>
          Current PDF:
          <a href="<?= htmlspecialchars((string) $event['pdf_path']) ?>" target="_blank" rel="noopener">
            View current PDF
          </a>
        </p>
      <?php endif; ?>

      <hr>

      <h2>Recurrence</h2>

      <div class="inline">
        <label>
          <input type="checkbox" name="is_recurring_parent" value="1" <?= !empty($event['is_recurring_parent']) ? 'checked' : '' ?>>
          Recurring Event
        </label>
      </div>

      <label for="recurrence_type">Recurrence Type</label>
      <select id="recurrence_type" name="recurrence_type">
        <option value="">None</option>
        <option value="weekly" <?= ($event['recurrence_type'] ?? '') === 'weekly' ? 'selected' : '' ?>>Weekly</option>
        <option value="monthly_nth" <?= ($event['recurrence_type'] ?? '') === 'monthly_nth' ? 'selected' : '' ?>>Monthly (Nth Weekday)</option>
      </select>

      <label for="recurrence_interval">
        Repeat Every
        <span style="border: 2px solid red; border-radius: 50px; padding: 3px 7px;" title="For weekly recurrence: 1 means every week, 2 means every other week. For monthly recurrence: 1 means every month, 2 means every other month.">?</span>
      </label>
      <input
        id="recurrence_interval"
        name="recurrence_interval"
        type="number"
        min="1"
        value="<?= htmlspecialchars((string) ($event['recurrence_interval'] ?? 1)) ?>"
      >

      <p><strong>Weekly Days</strong></p>
      <div class="checkbox-grid">
        <?php foreach ($weekdayOptions as $code => $label): ?>
          <label>
            <input
              type="checkbox"
              name="recurrence_days[]"
              value="<?= htmlspecialchars($code) ?>"
              <?= in_array($code, $selectedDays, true) ? 'checked' : '' ?>
            >
            <?= htmlspecialchars($label) ?>
          </label>
        <?php endforeach; ?>
      </div>

      <p><strong>Monthly Nth Weekday</strong></p>

      <label for="recurrence_week_of_month">Week of Month</label>
      <select id="recurrence_week_of_month" name="recurrence_week_of_month">
        <option value="">Select</option>
        <option value="first" <?= ($event['recurrence_week_of_month'] ?? '') === 'first' ? 'selected' : '' ?>>First</option>
        <option value="second" <?= ($event['recurrence_week_of_month'] ?? '') === 'second' ? 'selected' : '' ?>>Second</option>
        <option value="third" <?= ($event['recurrence_week_of_month'] ?? '') === 'third' ? 'selected' : '' ?>>Third</option>
        <option value="fourth" <?= ($event['recurrence_week_of_month'] ?? '') === 'fourth' ? 'selected' : '' ?>>Fourth</option>
        <option value="last" <?= ($event['recurrence_week_of_month'] ?? '') === 'last' ? 'selected' : '' ?>>Last</option>
      </select>

      <label for="recurrence_day_of_week">Day of Week</label>
      <select id="recurrence_day_of_week" name="recurrence_day_of_week">
        <option value="">Select</option>
        <option value="SU" <?= ($event['recurrence_day_of_week'] ?? '') === 'SU' ? 'selected' : '' ?>>Sunday</option>
        <option value="MO" <?= ($event['recurrence_day_of_week'] ?? '') === 'MO' ? 'selected' : '' ?>>Monday</option>
        <option value="TU" <?= ($event['recurrence_day_of_week'] ?? '') === 'TU' ? 'selected' : '' ?>>Tuesday</option>
        <option value="WE" <?= ($event['recurrence_day_of_week'] ?? '') === 'WE' ? 'selected' : '' ?>>Wednesday</option>
        <option value="TH" <?= ($event['recurrence_day_of_week'] ?? '') === 'TH' ? 'selected' : '' ?>>Thursday</option>
        <option value="FR" <?= ($event['recurrence_day_of_week'] ?? '') === 'FR' ? 'selected' : '' ?>>Friday</option>
        <option value="SA" <?= ($event['recurrence_day_of_week'] ?? '') === 'SA' ? 'selected' : '' ?>>Saturday</option>
      </select>

      <label for="recurrence_end_date">Recurrence End Date</label>
      <input
        id="recurrence_end_date"
        name="recurrence_end_date"
        type="date"
        value="<?= !empty($event['recurrence_end_date']) ? htmlspecialchars((string) $event['recurrence_end_date']) : '' ?>"
      >

      <p class="note">
        Occurrences are generated up to one year ahead or the recurrence end date, whichever comes first.
      </p>

      <div class="actions">
        <button type="submit">Save Event</button>
      </div>
    </form>
  </div>
</body>
</html>