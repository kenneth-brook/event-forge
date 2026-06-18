<?php
declare(strict_types=1);

require __DIR__ . '/../includes/installer.php';

if (!eventforge_is_installed()) {
    header('Location: ' . eventforge_admin_path('setup.php'));
    exit;
}

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/recurrence.php';

require_login();

function eventforge_selected_option($actual, string $expected): string
{
    return (string) $actual === $expected ? 'selected' : '';
}

function eventforge_checked_option(array $values, string $expected): string
{
    return in_array($expected, $values, true) ? 'checked' : '';
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$event = [
    'id' => 0,
    'title' => '',
    'start_datetime' => '',
    'end_datetime' => '',
    'all_day' => 0,
    'location' => '',
    'address_line_1' => '',
    'address_line_2' => '',
    'address_city' => '',
    'address_state' => '',
    'address_postal_code' => '',
    'latitude' => '',
    'longitude' => '',
    'summary' => '',
    'description' => '',
    'image_path' => '',
    'pdf_path' => '',
    'external_url' => '',
    'event_cost' => '',
    'is_published' => 1,
    'category_id' => '',
    'is_recurring_parent' => 0,
    'recurrence_type' => '',
    'recurrence_interval' => 1,
    'recurrence_days' => '',
    'recurrence_week_of_month' => '',
    'recurrence_day_of_week' => '',
    'recurrence_end_date' => '',
];

if ($id > 0) {
    $sql = "SELECT * FROM events WHERE id = {$id} LIMIT 1";
    $result = mysqli_query($connection, $sql);

    if ($result && $row = mysqli_fetch_assoc($result)) {
        $event = array_merge($event, $row);
    }
}

$categoryResult = mysqli_query($connection, "
    SELECT id, name
    FROM event_categories
    WHERE is_active = 1
    ORDER BY name ASC
");

if (!$categoryResult) {
    error_log('Event Forge event form category query failed: ' . mysqli_error($connection));
    exit('Unable to load categories.');
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
    body { font-family: Arial, sans-serif; padding:2rem; background:#f5f7fa; color:#1f2937; }
    .wrap { max-width:900px; margin:0 auto; background:#fff; padding:2rem; border-radius:12px; box-shadow:0 10px 24px rgba(0,0,0,.08); }
    label { display:block; margin:1rem 0 .35rem; font-weight:600; }
    input[type="text"], input[type="datetime-local"], input[type="date"], input[type="number"], input[type="url"], select, textarea { width:100%; padding:.7rem; box-sizing:border-box; }
    textarea { min-height:120px; }
    .inline { display:flex; gap:1rem; align-items:center; margin-top:1rem; flex-wrap:wrap; }
    .inline label { margin:0; font-weight:400; }
    .field-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1rem; }
    .field-grid .full { grid-column:1 / -1; }
    .checkbox-grid { display:flex; flex-wrap:wrap; gap:.75rem 1rem; margin-top:.5rem; }
    .checkbox-grid label { margin:0; font-weight:400; }
    .note { margin-top:.75rem; color:#4b5563; font-size:.95rem; }
    .location-status { margin-top:1rem; padding:.85rem 1rem; border-radius:10px; border:1px solid #d7dde5; background:#f8fafc; color:#334155; font-size:.95rem; }
    .preview-image { max-width:240px; height:auto; border-radius:8px; display:block; margin-top:.5rem; }
    hr { margin:2rem 0; border:0; border-top:1px solid #d7dde5; }
    .actions { margin-top:1.5rem; }
    @media(max-width:720px){ .field-grid{grid-template-columns:1fr;} .field-grid .full{grid-column:auto;} }
  </style>
</head>
<body>
  <div class="wrap">
    <h1><?= $id > 0 ? 'Edit Event' : 'Add Event' ?></h1>
    <p><a href="<?= htmlspecialchars(eventforge_admin_path('index.php')) ?>">← Back to events</a></p>

    <form method="post" action="<?= htmlspecialchars(eventforge_admin_path('save-event.php')) ?>" enctype="multipart/form-data">
      <?= eventforge_csrf_input() ?>
      <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">

      <label for="title">Title</label>
      <input id="title" name="title" type="text" required value="<?= htmlspecialchars((string) $event['title']) ?>">

      <label for="start_datetime">Start Date/Time</label>
      <input id="start_datetime" name="start_datetime" type="datetime-local" value="<?= !empty($event['start_datetime']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime((string) $event['start_datetime']))) : '' ?>">

      <label for="end_datetime">End Date/Time</label>
      <input id="end_datetime" name="end_datetime" type="datetime-local" value="<?= !empty($event['end_datetime']) ? htmlspecialchars(date('Y-m-d\TH:i', strtotime((string) $event['end_datetime']))) : '' ?>">

      <div class="inline">
        <label><input type="checkbox" name="all_day" value="1" <?= !empty($event['all_day']) ? 'checked' : '' ?>> All Day</label>
        <label><input type="checkbox" name="is_published" value="1" <?= !empty($event['is_published']) ? 'checked' : '' ?>> Published</label>
      </div>

      <label for="location">Location</label>
      <input id="location" name="location" type="text" value="<?= htmlspecialchars((string) $event['location']) ?>">

      <hr>
      <h2>Address / Map Location</h2>
      <p class="note">Enter a usable address to geocode and save latitude/longitude when this event is created or updated.</p>

      <div class="field-grid">
        <div class="full"><label for="address_line_1">Address Line 1</label><input id="address_line_1" name="address_line_1" type="text" value="<?= htmlspecialchars((string) $event['address_line_1']) ?>"></div>
        <div class="full"><label for="address_line_2">Address Line 2</label><input id="address_line_2" name="address_line_2" type="text" value="<?= htmlspecialchars((string) $event['address_line_2']) ?>"></div>
        <div><label for="address_city">City</label><input id="address_city" name="address_city" type="text" value="<?= htmlspecialchars((string) $event['address_city']) ?>"></div>
        <div><label for="address_state">State</label><input id="address_state" name="address_state" type="text" value="<?= htmlspecialchars((string) $event['address_state']) ?>"></div>
        <div><label for="address_postal_code">Postal Code</label><input id="address_postal_code" name="address_postal_code" type="text" value="<?= htmlspecialchars((string) $event['address_postal_code']) ?>"></div>
      </div>

      <?php if ($event['latitude'] !== '' && $event['longitude'] !== ''): ?>
        <div class="location-status"><strong>Saved Coordinates:</strong> <?= htmlspecialchars((string) $event['latitude']) ?>, <?= htmlspecialchars((string) $event['longitude']) ?></div>
      <?php else: ?>
        <div class="location-status">No saved coordinates yet.</div>
      <?php endif; ?>

      <label for="category_id">Category</label>
      <select id="category_id" name="category_id">
        <option value="">None</option>
        <?php while ($category = mysqli_fetch_assoc($categoryResult)): ?>
          <option value="<?= (int) $category['id'] ?>" <?= !empty($event['category_id']) && (int) $event['category_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $category['name']) ?></option>
        <?php endwhile; ?>
      </select>

      <label for="event_cost">Cost / Admission</label>
      <input id="event_cost" name="event_cost" type="text" value="<?= htmlspecialchars((string) ($event['event_cost'] ?? '')) ?>" placeholder="Free, $10, Members only, Donations accepted...">

      <label for="summary">Summary</label>
      <textarea id="summary" name="summary"><?= htmlspecialchars((string) $event['summary']) ?></textarea>

      <label for="description">Description</label>
      <textarea id="description" name="description"><?= htmlspecialchars((string) $event['description']) ?></textarea>

      <label for="external_url">External URL</label>
      <input id="external_url" name="external_url" type="url" value="<?= htmlspecialchars((string) $event['external_url']) ?>">

      <label for="image">Image Upload</label>
      <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp">
      <?php if (!empty($event['image_path'])): ?><p>Current image:</p><img class="preview-image" src="<?= htmlspecialchars((string) $event['image_path']) ?>" alt="Current event image"><?php endif; ?>

      <label for="pdf">PDF Upload</label>
      <input id="pdf" name="pdf" type="file" accept=".pdf">
      <?php if (!empty($event['pdf_path'])): ?><p>Current PDF: <a href="<?= htmlspecialchars((string) $event['pdf_path']) ?>" target="_blank" rel="noopener">View current PDF</a></p><?php endif; ?>

      <hr>
      <h2>Recurrence</h2>
      <p class="note">Use only for normal generated repeat events. Leave as No for one-time events.</p>

      <label for="is_recurring_parent">Does this event repeat?</label>
      <select id="is_recurring_parent" name="is_recurring_parent">
        <option value="0" <?= empty($event['is_recurring_parent']) ? 'selected' : '' ?>>No</option>
        <option value="1" <?= !empty($event['is_recurring_parent']) ? 'selected' : '' ?>>Yes</option>
      </select>

      <label for="recurrence_type">Recurrence Type</label>
      <select id="recurrence_type" name="recurrence_type">
        <option value="">None</option>
        <option value="daily" <?= eventforge_selected_option($event['recurrence_type'], 'daily') ?>>Daily</option>
        <option value="weekly" <?= eventforge_selected_option($event['recurrence_type'], 'weekly') ?>>Weekly</option>
        <option value="monthly_nth" <?= eventforge_selected_option($event['recurrence_type'], 'monthly_nth') ?>>Monthly</option>
        <option value="annual" <?= eventforge_selected_option($event['recurrence_type'], 'annual') ?>>Annual</option>
      </select>

      <label for="recurrence_interval">Repeat Every</label>
      <input id="recurrence_interval" name="recurrence_interval" type="number" min="1" value="<?= htmlspecialchars((string) ($event['recurrence_interval'] ?? 1)) ?>">

      <p><strong>Weekly Repeat On</strong></p>
      <div class="checkbox-grid">
        <?php foreach ($weekdayOptions as $code => $label): ?>
          <label><input type="checkbox" name="recurrence_days[]" value="<?= htmlspecialchars($code) ?>" <?= eventforge_checked_option($selectedDays, $code) ?>> <?= htmlspecialchars($label) ?></label>
        <?php endforeach; ?>
      </div>

      <label for="recurrence_week_of_month">Week of Month</label>
      <select id="recurrence_week_of_month" name="recurrence_week_of_month">
        <option value="">Select</option>
        <option value="first" <?= eventforge_selected_option($event['recurrence_week_of_month'], 'first') ?>>First</option>
        <option value="second" <?= eventforge_selected_option($event['recurrence_week_of_month'], 'second') ?>>Second</option>
        <option value="third" <?= eventforge_selected_option($event['recurrence_week_of_month'], 'third') ?>>Third</option>
        <option value="fourth" <?= eventforge_selected_option($event['recurrence_week_of_month'], 'fourth') ?>>Fourth</option>
        <option value="last" <?= eventforge_selected_option($event['recurrence_week_of_month'], 'last') ?>>Last</option>
      </select>

      <label for="recurrence_day_of_week">Day of Week</label>
      <select id="recurrence_day_of_week" name="recurrence_day_of_week">
        <option value="">Select</option>
        <?php foreach ($weekdayOptions as $code => $label): ?>
          <option value="<?= htmlspecialchars($code) ?>" <?= eventforge_selected_option($event['recurrence_day_of_week'], $code) ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>

      <input type="hidden" name="annual_pattern_mode" value="same_date">
      <input type="hidden" name="annual_mode" value="date">
      <input type="hidden" name="annual_recurrence_week_of_month" value="">
      <input type="hidden" name="annual_recurrence_day_of_week" value="">

      <label for="recurrence_end_date">Recurrence End Date</label>
      <input id="recurrence_end_date" name="recurrence_end_date" type="date" value="<?= !empty($event['recurrence_end_date']) ? htmlspecialchars((string) $event['recurrence_end_date']) : '' ?>">

      <div class="actions">
        <button type="submit">Save Event</button>
      </div>
    </form>
  </div>
</body>
</html>
