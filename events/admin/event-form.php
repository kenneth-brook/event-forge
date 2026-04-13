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

if (!function_exists('eventforge_resolve_recurrence_type')) {
    function eventforge_resolve_recurrence_type(array $event): string
    {
        $type = strtolower(trim((string) ($event['recurrence_type'] ?? '')));

        if ($type === 'monthly') {
            $type = 'monthly_nth';
        }

        if (in_array($type, ['daily', 'weekly', 'monthly_nth', 'annual'], true)) {
            return $type;
        }

        $hasMonthlyParts = trim((string) ($event['recurrence_week_of_month'] ?? '')) !== ''
            && trim((string) ($event['recurrence_day_of_week'] ?? '')) !== '';

        if ($hasMonthlyParts) {
            return 'monthly_nth';
        }

        $daysRaw = $event['recurrence_days'] ?? '';
        $days = is_array($daysRaw)
            ? $daysRaw
            : explode(',', (string) $daysRaw);

        foreach ($days as $day) {
            if (trim((string) $day) !== '') {
                return 'weekly';
            }
        }

        return '';
    }
}

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
    'category_id' => '',
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

$categoryResult = mysqli_query($connection, "
    SELECT id, name
    FROM event_categories
    WHERE is_active = 1
    ORDER BY name ASC
");

if (!$categoryResult) {
    exit('Category query failed: ' . mysqli_error($connection));
}

$resolvedRecurrenceType = eventforge_resolve_recurrence_type($event);
$isRecurringSelected = !empty($event['is_recurring_parent']) ? '1' : '0';

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

$annualPatternMode = 'same_date';
if (
    $resolvedRecurrenceType === 'annual'
    && (
        trim((string) ($event['recurrence_week_of_month'] ?? '')) !== ''
        || trim((string) ($event['recurrence_day_of_week'] ?? '')) !== ''
    )
) {
    $annualPatternMode = 'nth_weekday';
}

$annualAnchorLabel = '';
if (!empty($event['start_datetime'])) {
    $annualAnchorLabel = date('F j', strtotime((string) $event['start_datetime']));
}
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

    .help-badge {
      display: inline-block;
      border: 1px solid #3f6244;
      border-radius: 999px;
      padding: 2px 8px;
      font-size: .85rem;
      line-height: 1;
      color: #3f6244;
      cursor: help;
      vertical-align: middle;
      margin-left: .35rem;
    }

    .recurrence-shell {
      border: 1px solid #d7dde5;
      border-radius: 12px;
      padding: 1rem 1rem 1.25rem;
      background: #fafbfd;
    }

    .recurrence-step {
      margin-top: 1rem;
    }

    .recurrence-section {
      margin-top: 1rem;
      padding: 1rem;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      background: #fff;
    }

    .recurrence-section[hidden],
    .recurrence-step[hidden],
    .annual-pattern-fields[hidden] {
      display: none !important;
    }

    .section-title {
      margin: 0 0 .5rem;
      font-size: 1rem;
      font-weight: 700;
    }
  </style>
</head>
<body>
  <div class="wrap">
    <h1><?= $id > 0 ? 'Edit Event' : 'Add Event' ?></h1>
    <p><a href="<?= htmlspecialchars(eventforge_admin_path('index.php')) ?>">← Back to events</a></p>

    <form method="post" action="<?= htmlspecialchars(eventforge_admin_path('save-event.php')) ?>" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">

      <label for="title">Title</label>
      <input id="title" name="title" type="text" required value="<?= htmlspecialchars((string) $event['title']) ?>">

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
      <input id="location" name="location" type="text" value="<?= htmlspecialchars((string) $event['location']) ?>">

      <label for="category_id">Category</label>
      <select id="category_id" name="category_id">
        <option value="">None</option>
        <?php while ($category = mysqli_fetch_assoc($categoryResult)): ?>
          <option
            value="<?= (int) $category['id'] ?>"
            <?= !empty($event['category_id']) && (int) $event['category_id'] === (int) $category['id'] ? 'selected' : '' ?>
          >
            <?= htmlspecialchars((string) $category['name']) ?>
          </option>
        <?php endwhile; ?>
      </select>

      <label for="summary">Summary</label>
      <textarea id="summary" name="summary"><?= htmlspecialchars((string) $event['summary']) ?></textarea>

      <label for="description">Description</label>
      <textarea id="description" name="description"><?= htmlspecialchars((string) $event['description']) ?></textarea>

      <label for="external_url">External URL</label>
      <input id="external_url" name="external_url" type="url" value="<?= htmlspecialchars((string) $event['external_url']) ?>">

      <label for="image">Image Upload</label>
      <input id="image" name="image" type="file" accept=".jpg,.jpeg,.png,.webp">

      <?php if (!empty($event['image_path'])): ?>
        <p>Current image:</p>
        <img class="preview-image" src="<?= htmlspecialchars((string) $event['image_path']) ?>" alt="Current event image">
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

      <div class="recurrence-shell">
        <div class="recurrence-step">
          <label for="is_recurring_parent">Does this event repeat?</label>
          <select id="is_recurring_parent" name="is_recurring_parent">
            <option value="0" <?= $isRecurringSelected === '0' ? 'selected' : '' ?>>No</option>
            <option value="1" <?= $isRecurringSelected === '1' ? 'selected' : '' ?>>Yes</option>
          </select>
          <p class="note">Choose “Yes” only for the parent event that defines the series.</p>
        </div>

        <div class="recurrence-step" id="recurrence-type-step" hidden>
          <label for="recurrence_type">Recurrence Type</label>
          <select id="recurrence_type" name="recurrence_type">
            <option value="">Select recurrence type</option>
            <option value="daily" <?= $resolvedRecurrenceType === 'daily' ? 'selected' : '' ?>>Daily</option>
            <option value="weekly" <?= $resolvedRecurrenceType === 'weekly' ? 'selected' : '' ?>>Weekly</option>
            <option value="monthly_nth" <?= $resolvedRecurrenceType === 'monthly_nth' ? 'selected' : '' ?>>Monthly</option>
            <option value="annual" <?= $resolvedRecurrenceType === 'annual' ? 'selected' : '' ?>>Annual</option>
          </select>
        </div>

        <div class="recurrence-step" id="recurrence-common-step" hidden>
          <div class="recurrence-section">
            <p class="section-title">Repeat Settings</p>

            <label for="recurrence_interval">Repeat Every</label>
            <input
              id="recurrence_interval"
              name="recurrence_interval"
              type="number"
              min="1"
              value="<?= htmlspecialchars((string) ($event['recurrence_interval'] ?? 1)) ?>"
            >

            <label for="recurrence_end_date">Recurrence End Date</label>
            <input
              id="recurrence_end_date"
              name="recurrence_end_date"
              type="date"
              value="<?= !empty($event['recurrence_end_date']) ? htmlspecialchars((string) $event['recurrence_end_date']) : '' ?>"
            >

            <p class="note" id="recurrence-horizon-note">
              Occurrences are generated ahead based on the selected recurrence type.
            </p>
          </div>
        </div>

        <div class="recurrence-section" id="recurrence-daily-section" data-recurrence-section="daily" hidden>
          <p class="section-title">Daily Pattern</p>
          <p class="note">This event will repeat every selected number of days from the start date.</p>
        </div>

        <div class="recurrence-section" id="recurrence-weekly-section" data-recurrence-section="weekly" hidden>
          <p class="section-title">Weekly Pattern</p>
          <p><strong>Repeat On</strong></p>
          <div class="checkbox-grid">
            <?php foreach ($weekdayOptions as $code => $label): ?>
              <label>
                <input
                  type="checkbox"
                  name="recurrence_days[]"
                  value="<?= htmlspecialchars($code) ?>"
                  <?= in_array($code, $selectedDays, true) ? 'checked' : '' ?>
                  data-recurrence-weekly
                >
                <?= htmlspecialchars($label) ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="recurrence-section" id="recurrence-monthly-section" data-recurrence-section="monthly_nth" hidden>
          <p class="section-title">Monthly Pattern</p>

          <label for="recurrence_week_of_month">Week of Month</label>
          <select id="recurrence_week_of_month" name="recurrence_week_of_month" data-recurrence-monthly>
            <option value="">Select</option>
            <option value="first" <?= ($event['recurrence_week_of_month'] ?? '') === 'first' ? 'selected' : '' ?>>First</option>
            <option value="second" <?= ($event['recurrence_week_of_month'] ?? '') === 'second' ? 'selected' : '' ?>>Second</option>
            <option value="third" <?= ($event['recurrence_week_of_month'] ?? '') === 'third' ? 'selected' : '' ?>>Third</option>
            <option value="fourth" <?= ($event['recurrence_week_of_month'] ?? '') === 'fourth' ? 'selected' : '' ?>>Fourth</option>
            <option value="last" <?= ($event['recurrence_week_of_month'] ?? '') === 'last' ? 'selected' : '' ?>>Last</option>
          </select>

          <label for="recurrence_day_of_week">Day of Week</label>
          <select id="recurrence_day_of_week" name="recurrence_day_of_week" data-recurrence-monthly>
            <option value="">Select</option>
            <option value="SU" <?= ($event['recurrence_day_of_week'] ?? '') === 'SU' ? 'selected' : '' ?>>Sunday</option>
            <option value="MO" <?= ($event['recurrence_day_of_week'] ?? '') === 'MO' ? 'selected' : '' ?>>Monday</option>
            <option value="TU" <?= ($event['recurrence_day_of_week'] ?? '') === 'TU' ? 'selected' : '' ?>>Tuesday</option>
            <option value="WE" <?= ($event['recurrence_day_of_week'] ?? '') === 'WE' ? 'selected' : '' ?>>Wednesday</option>
            <option value="TH" <?= ($event['recurrence_day_of_week'] ?? '') === 'TH' ? 'selected' : '' ?>>Thursday</option>
            <option value="FR" <?= ($event['recurrence_day_of_week'] ?? '') === 'FR' ? 'selected' : '' ?>>Friday</option>
            <option value="SA" <?= ($event['recurrence_day_of_week'] ?? '') === 'SA' ? 'selected' : '' ?>>Saturday</option>
          </select>
        </div>

        <div class="recurrence-section" id="recurrence-annual-section" data-recurrence-section="annual" hidden>
          <p class="section-title">Annual Pattern</p>

          <label for="annual_pattern_mode">Annual Pattern Style</label>
          <select id="annual_pattern_mode" name="annual_pattern_mode">
            <option value="same_date" <?= $annualPatternMode === 'same_date' ? 'selected' : '' ?>>Same date each year</option>
            <option value="nth_weekday" <?= $annualPatternMode === 'nth_weekday' ? 'selected' : '' ?>>Nth weekday of the month</option>
          </select>

          <div class="annual-pattern-fields" id="annual-same-date-fields">
            <p class="note">
              Uses the event start date as the yearly anchor<?= $annualAnchorLabel !== '' ? ': ' . htmlspecialchars($annualAnchorLabel) : '' ?>.
            </p>
          </div>

          <div class="annual-pattern-fields" id="annual-nth-weekday-fields" hidden>
            <label for="annual_recurrence_week_of_month">Week of Month</label>
            <select id="annual_recurrence_week_of_month" name="annual_recurrence_week_of_month">
              <option value="">Select</option>
              <option value="first" <?= ($event['recurrence_week_of_month'] ?? '') === 'first' ? 'selected' : '' ?>>First</option>
              <option value="second" <?= ($event['recurrence_week_of_month'] ?? '') === 'second' ? 'selected' : '' ?>>Second</option>
              <option value="third" <?= ($event['recurrence_week_of_month'] ?? '') === 'third' ? 'selected' : '' ?>>Third</option>
              <option value="fourth" <?= ($event['recurrence_week_of_month'] ?? '') === 'fourth' ? 'selected' : '' ?>>Fourth</option>
              <option value="last" <?= ($event['recurrence_week_of_month'] ?? '') === 'last' ? 'selected' : '' ?>>Last</option>
            </select>

            <label for="annual_recurrence_day_of_week">Day of Week</label>
            <select id="annual_recurrence_day_of_week" name="annual_recurrence_day_of_week">
              <option value="">Select</option>
              <option value="SU" <?= ($event['recurrence_day_of_week'] ?? '') === 'SU' ? 'selected' : '' ?>>Sunday</option>
              <option value="MO" <?= ($event['recurrence_day_of_week'] ?? '') === 'MO' ? 'selected' : '' ?>>Monday</option>
              <option value="TU" <?= ($event['recurrence_day_of_week'] ?? '') === 'TU' ? 'selected' : '' ?>>Tuesday</option>
              <option value="WE" <?= ($event['recurrence_day_of_week'] ?? '') === 'WE' ? 'selected' : '' ?>>Wednesday</option>
              <option value="TH" <?= ($event['recurrence_day_of_week'] ?? '') === 'TH' ? 'selected' : '' ?>>Thursday</option>
              <option value="FR" <?= ($event['recurrence_day_of_week'] ?? '') === 'FR' ? 'selected' : '' ?>>Friday</option>
              <option value="SA" <?= ($event['recurrence_day_of_week'] ?? '') === 'SA' ? 'selected' : '' ?>>Saturday</option>
            </select>
          </div>

          <p class="note">Annual recurrences generate up to 5 years ahead or the recurrence end date, whichever comes first.</p>
        </div>
      </div>

      <div class="actions">
        <button type="submit">Save Event</button>
      </div>
    </form>
  </div>

  <script>
    (function () {
      const recurringSelect = document.getElementById('is_recurring_parent');
      const typeStep = document.getElementById('recurrence-type-step');
      const commonStep = document.getElementById('recurrence-common-step');
      const typeSelect = document.getElementById('recurrence_type');
      const sections = document.querySelectorAll('[data-recurrence-section]');
      const horizonNote = document.getElementById('recurrence-horizon-note');

      const weeklyInputs = Array.from(document.querySelectorAll('[data-recurrence-weekly]'));
      const monthlyInputs = Array.from(document.querySelectorAll('[data-recurrence-monthly]'));
      const annualPatternMode = document.getElementById('annual_pattern_mode');
      const annualSameDateFields = document.getElementById('annual-same-date-fields');
      const annualNthWeekdayFields = document.getElementById('annual-nth-weekday-fields');
      const annualWeekOfMonth = document.getElementById('annual_recurrence_week_of_month');
      const annualDayOfWeek = document.getElementById('annual_recurrence_day_of_week');

      function disableInputs(inputs, disabled) {
        inputs.forEach(function (input) {
          input.disabled = disabled;
        });
      }

      function updateAnnualPatternUi() {
        if (!annualPatternMode || typeSelect.value !== 'annual' || recurringSelect.value !== '1') {
          if (annualSameDateFields) annualSameDateFields.hidden = false;
          if (annualNthWeekdayFields) annualNthWeekdayFields.hidden = true;
          if (annualWeekOfMonth) annualWeekOfMonth.disabled = true;
          if (annualDayOfWeek) annualDayOfWeek.disabled = true;
          return;
        }

        const mode = annualPatternMode.value;

        if (mode === 'nth_weekday') {
          annualSameDateFields.hidden = true;
          annualNthWeekdayFields.hidden = false;
          annualWeekOfMonth.disabled = false;
          annualDayOfWeek.disabled = false;
        } else {
          annualSameDateFields.hidden = false;
          annualNthWeekdayFields.hidden = true;
          annualWeekOfMonth.disabled = true;
          annualDayOfWeek.disabled = true;
        }
      }

      function updateRecurrenceUi() {
        const isRecurring = recurringSelect.value === '1';
        const type = typeSelect.value;

        typeStep.hidden = !isRecurring;
        commonStep.hidden = !isRecurring || type === '';

        if (!isRecurring) {
          typeSelect.disabled = true;
          sections.forEach(function (section) {
            section.hidden = true;
          });

          disableInputs(weeklyInputs, true);
          disableInputs(monthlyInputs, true);

          if (annualPatternMode) annualPatternMode.disabled = true;
          if (annualWeekOfMonth) annualWeekOfMonth.disabled = true;
          if (annualDayOfWeek) annualDayOfWeek.disabled = true;

          horizonNote.textContent = 'Occurrences are generated ahead based on the selected recurrence type.';
          updateAnnualPatternUi();
          return;
        }

        typeSelect.disabled = false;

        sections.forEach(function (section) {
          const sectionType = section.getAttribute('data-recurrence-section');
          section.hidden = sectionType !== type;
        });

        disableInputs(weeklyInputs, type !== 'weekly');
        disableInputs(monthlyInputs, type !== 'monthly_nth');

        if (annualPatternMode) {
          annualPatternMode.disabled = type !== 'annual';
        }

        switch (type) {
          case 'daily':
            horizonNote.textContent = 'Daily recurrences generate up to 1 year ahead or the end date, whichever comes first.';
            break;
          case 'weekly':
            horizonNote.textContent = 'Weekly recurrences generate up to 1 year ahead or the end date, whichever comes first.';
            break;
          case 'monthly_nth':
            horizonNote.textContent = 'Monthly recurrences generate up to 1 year ahead or the end date, whichever comes first.';
            break;
          case 'annual':
            horizonNote.textContent = 'Annual recurrences generate up to 5 years ahead or the end date, whichever comes first.';
            break;
          default:
            horizonNote.textContent = 'Occurrences are generated ahead based on the selected recurrence type.';
            break;
        }

        updateAnnualPatternUi();
      }

      recurringSelect.addEventListener('change', updateRecurrenceUi);
      typeSelect.addEventListener('change', updateRecurrenceUi);

      if (annualPatternMode) {
        annualPatternMode.addEventListener('change', updateAnnualPatternUi);
      }

      updateRecurrenceUi();
    })();
  </script>
</body>
</html>