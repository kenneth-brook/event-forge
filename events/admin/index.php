<?php
declare(strict_types=1);

require __DIR__ . '/../includes/installer.php';

if (!eventforge_is_installed()) {
    header('Location: ' . eventforge_admin_path('setup.php'));
    exit;
}

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/auth.php';

require_login();

function eventforge_parse_admin_datetime(?string $value): ?DateTimeImmutable
{
    if ($value === null) {
        return null;
    }

    $value = trim($value);

    if ($value === '') {
        return null;
    }

    try {
        return new DateTimeImmutable($value);
    } catch (Throwable $exception) {
        return null;
    }
}

function eventforge_get_admin_effective_end(?DateTimeImmutable $start, ?DateTimeImmutable $end): ?DateTimeImmutable
{
    return $end ?? $start;
}

function eventforge_classify_admin_window(?DateTimeImmutable $start, ?DateTimeImmutable $end, DateTimeImmutable $now): array
{
    $effectiveEnd = eventforge_get_admin_effective_end($start, $end);

    if ($start instanceof DateTimeImmutable && $effectiveEnd instanceof DateTimeImmutable) {
        if ($start <= $now && $effectiveEnd >= $now) {
            return [
                'section' => 'ongoing',
                'sort_timestamp' => $effectiveEnd->getTimestamp(),
                'is_ongoing' => true,
            ];
        }

        if ($start > $now) {
            return [
                'section' => 'upcoming',
                'sort_timestamp' => $start->getTimestamp(),
                'is_ongoing' => false,
            ];
        }
    }

    if ($effectiveEnd instanceof DateTimeImmutable) {
        return [
            'section' => 'past',
            'sort_timestamp' => $effectiveEnd->getTimestamp(),
            'is_ongoing' => false,
        ];
    }

    if ($start instanceof DateTimeImmutable) {
        return [
            'section' => 'past',
            'sort_timestamp' => $start->getTimestamp(),
            'is_ongoing' => false,
        ];
    }

    return [
        'section' => 'past',
        'sort_timestamp' => 0,
        'is_ongoing' => false,
    ];
}

function eventforge_build_admin_row_meta(array $row, DateTimeImmutable $now): array
{
    $start = eventforge_parse_admin_datetime($row['start_datetime'] ?? null);
    $end = eventforge_parse_admin_datetime($row['end_datetime'] ?? null);

    $timing = eventforge_classify_admin_window($start, $end, $now);

    return [
        'section' => $timing['section'],
        'sort_timestamp' => $timing['sort_timestamp'],
        'is_ongoing' => $timing['is_ongoing'],
        'display_start' => (string) ($row['start_datetime'] ?? ''),
        'display_end' => (string) ($row['end_datetime'] ?? ''),
        'context_label' => '',
    ];
}

function eventforge_admin_section_priority(string $section): int
{
    switch ($section) {
        case 'ongoing':
            return 0;

        case 'upcoming':
            return 1;

        case 'past':
            return 2;

        default:
            return 3;
    }
}

function eventforge_compare_prepared_admin_rows(array $a, array $b): int
{
    $sectionCompare = eventforge_admin_section_priority($a['meta']['section'])
        <=> eventforge_admin_section_priority($b['meta']['section']);

    if ($sectionCompare !== 0) {
        return $sectionCompare;
    }

    $aTimestamp = (int) ($a['meta']['sort_timestamp'] ?? 0);
    $bTimestamp = (int) ($b['meta']['sort_timestamp'] ?? 0);

    if (($a['meta']['section'] ?? '') === 'past') {
        $timestampCompare = $bTimestamp <=> $aTimestamp;
    } else {
        $timestampCompare = $aTimestamp <=> $bTimestamp;
    }

    if ($timestampCompare !== 0) {
        return $timestampCompare;
    }

    $startCompare = strcmp(
        (string) ($a['row']['start_datetime'] ?? ''),
        (string) ($b['row']['start_datetime'] ?? '')
    );

    if ($startCompare !== 0) {
        return $startCompare;
    }

    $titleCompare = strcmp(
        (string) ($a['row']['title'] ?? ''),
        (string) ($b['row']['title'] ?? '')
    );

    if ($titleCompare !== 0) {
        return $titleCompare;
    }

    return ((int) ($a['row']['id'] ?? 0)) <=> ((int) ($b['row']['id'] ?? 0));
}

function eventforge_build_admin_parent_meta(array $parentRow, array $childRows, DateTimeImmutable $now): array
{
    if (count($childRows) === 0) {
        return eventforge_build_admin_row_meta($parentRow, $now);
    }

    $preparedChildren = [];

    foreach ($childRows as $childRow) {
        $preparedChildren[] = [
            'row' => $childRow,
            'meta' => eventforge_build_admin_row_meta($childRow, $now),
        ];
    }

    usort($preparedChildren, 'eventforge_compare_prepared_admin_rows');

    $reference = $preparedChildren[0];
    $section = (string) ($reference['meta']['section'] ?? 'past');

    $contextLabel = 'Last child';

    if ($section === 'ongoing') {
        $contextLabel = 'Ongoing child';
    } elseif ($section === 'upcoming') {
        $contextLabel = 'Next child';
    }

    return [
        'section' => $section,
        'sort_timestamp' => (int) ($reference['meta']['sort_timestamp'] ?? 0),
        'is_ongoing' => !empty($reference['meta']['is_ongoing']),
        'display_start' => (string) ($reference['row']['start_datetime'] ?? ''),
        'display_end' => (string) ($reference['row']['end_datetime'] ?? ''),
        'context_label' => $contextLabel,
    ];
}

$sql = "
    SELECT
        id,
        title,
        slug,
        start_datetime,
        end_datetime,
        is_published,
        is_recurring_parent,
        parent_event_id,
        is_independent_child,
        is_canceled
    FROM events
    ORDER BY
        start_datetime ASC,
        id ASC
";

$result = mysqli_query($connection, $sql);

if (!$result) {
    exit('Query failed: ' . mysqli_error($connection));
}

$allRows = [];
$childrenByParent = [];

while ($row = mysqli_fetch_assoc($result)) {
    $allRows[] = $row;

    if (!empty($row['parent_event_id'])) {
        $parentId = (int) $row['parent_event_id'];

        if (!isset($childrenByParent[$parentId])) {
            $childrenByParent[$parentId] = [];
        }

        $childrenByParent[$parentId][] = $row;
    }
}

$now = new DateTimeImmutable('now');
$preparedTopLevelRows = [];

foreach ($allRows as $row) {
    $hasParent = !empty($row['parent_event_id']);

    if ($hasParent) {
        continue;
    }

    $isParent = !empty($row['is_recurring_parent']);
    $rowId = (int) $row['id'];

    if ($isParent) {
        $meta = eventforge_build_admin_parent_meta($row, $childrenByParent[$rowId] ?? [], $now);
    } else {
        $meta = eventforge_build_admin_row_meta($row, $now);
    }

    $preparedChildren = [];

    if ($isParent && isset($childrenByParent[$rowId])) {
        foreach ($childrenByParent[$rowId] as $childRow) {
            $preparedChildren[] = [
                'row' => $childRow,
                'meta' => eventforge_build_admin_row_meta($childRow, $now),
            ];
        }

        usort($preparedChildren, 'eventforge_compare_prepared_admin_rows');
    }

    $preparedTopLevelRows[] = [
        'row' => $row,
        'meta' => $meta,
        'children' => $preparedChildren,
    ];
}

usort($preparedTopLevelRows, 'eventforge_compare_prepared_admin_rows');
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

    th,
    td {
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

    .ongoing-row td {
      background: #eef8f0;
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

    .status-ongoing {
      display: inline-block;
      margin-left: .5rem;
      padding: .15rem .45rem;
      border-radius: 999px;
      background: #167151;
      color: #fff;
      font-size: .75rem;
      font-weight: 700;
      letter-spacing: .03em;
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

    .series-note,
    .date-note {
      display: block;
      margin-top: .35rem;
      font-size: .8rem;
      color: #5b6470;
    }

    .past-divider td {
      background: #e9edf2 !important;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #374151;
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

      <a class="button" href="<?= htmlspecialchars(eventforge_admin_path('event-form.php')) ?>">Add Event</a>

      <?php if (is_admin()): ?>
        <a class="button" href="<?= htmlspecialchars(eventforge_admin_path('settings.php')) ?>">Settings</a>
      <?php endif; ?>

      <a class="button" href="<?= htmlspecialchars(eventforge_admin_path('logout.php')) ?>">Log Out</a>
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
      <?php
      $pastDividerInserted = false;
      ?>

      <?php foreach ($preparedTopLevelRows as $item): ?>
        <?php
        $row = $item['row'];
        $meta = $item['meta'];
        $childItems = $item['children'];

        $isParent = !empty($row['is_recurring_parent']);
        $hasParent = !empty($row['parent_event_id']);
        $isIndependent = !empty($row['is_independent_child']);
        $isCanceled = !empty($row['is_canceled']);
        $isOngoing = !empty($meta['is_ongoing']);

        if (!$pastDividerInserted && ($meta['section'] ?? '') === 'past') {
            $pastDividerInserted = true;
            ?>
            <tr class="past-divider">
              <td colspan="6">Past Events</td>
            </tr>
            <?php
        }
        ?>

        <tr class="<?= $isOngoing ? 'ongoing-row' : '' ?>">
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

              <?php if (($meta['context_label'] ?? '') !== ''): ?>
                <span class="series-note"><?= htmlspecialchars((string) $meta['context_label']) ?></span>
              <?php endif; ?>

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

            <?php if ($isOngoing): ?>
              <span class="status-ongoing">ONGOING</span>
            <?php endif; ?>
          </td>

          <td>
            <?= htmlspecialchars((string) ($meta['display_start'] ?? '')) ?>
            <?php if ($isParent && ($meta['context_label'] ?? '') !== ''): ?>
              <span class="date-note"><?= htmlspecialchars((string) $meta['context_label']) ?></span>
            <?php endif; ?>
          </td>

          <td><?= htmlspecialchars((string) ($meta['display_end'] ?? '')) ?></td>

          <td>
            <?= !empty($row['is_published']) ? 'Yes' : 'No' ?>
            |
            <a href="<?= htmlspecialchars(eventforge_admin_path('toggle-publish.php')) ?>?id=<?= (int) $row['id'] ?>">
              <?= !empty($row['is_published']) ? 'Unpublish' : 'Publish' ?>
            </a>
          </td>

          <td>
            <?php if ($hasParent && !$isIndependent && !$isParent): ?>
              <div><em>Generated from series</em></div>
              <div>
                <a href="<?= htmlspecialchars(eventforge_admin_path('view-event.php')) ?>?id=<?= (int) $row['id'] ?>">View</a>
                |
                <a
                  href="<?= htmlspecialchars(eventforge_admin_path('make-independent.php')) ?>?id=<?= (int) $row['id'] ?>"
                  title="This child will remain grouped under the parent series, but future parent changes will not overwrite it."
                  onclick="return confirm('Make this generated child independent? Future parent changes will no longer overwrite this event.');"
                >
                  Make Independent
                </a>
                |
                <?php if ($isCanceled): ?>
                  <a href="<?= htmlspecialchars(eventforge_admin_path('uncancel-event.php')) ?>?id=<?= (int) $row['id'] ?>" onclick="return confirm('Mark this event as active again?');">
                    Uncancel
                  </a>
                <?php else: ?>
                  <a href="<?= htmlspecialchars(eventforge_admin_path('cancel-event.php')) ?>?id=<?= (int) $row['id'] ?>" onclick="return confirm('Cancel this event? This will also make it independent from the series.');">
                    Cancel
                  </a>
                <?php endif; ?>
              </div>

            <?php elseif ($isParent): ?>
              <a href="<?= htmlspecialchars(eventforge_admin_path('view-event.php')) ?>?id=<?= (int) $row['id'] ?>">View</a> |
              <a href="<?= htmlspecialchars(eventforge_admin_path('event-form.php')) ?>?id=<?= (int) $row['id'] ?>">Edit</a> |
              <a href="<?= htmlspecialchars(eventforge_admin_path('delete-event.php')) ?>?id=<?= (int) $row['id'] ?>" onclick="return confirm('Delete this event and its generated children?');">Delete</a>

            <?php else: ?>
              <a href="<?= htmlspecialchars(eventforge_admin_path('view-event.php')) ?>?id=<?= (int) $row['id'] ?>">View</a> |
              <a href="<?= htmlspecialchars(eventforge_admin_path('event-form.php')) ?>?id=<?= (int) $row['id'] ?>">Edit</a> |
              <?php if ($isCanceled): ?>
                <a href="<?= htmlspecialchars(eventforge_admin_path('uncancel-event.php')) ?>?id=<?= (int) $row['id'] ?>" onclick="return confirm('Mark this event as active again?');">Uncancel</a> |
              <?php else: ?>
                <a href="<?= htmlspecialchars(eventforge_admin_path('cancel-event.php')) ?>?id=<?= (int) $row['id'] ?>" onclick="return confirm('Cancel this event?');">Cancel</a> |
              <?php endif; ?>
              <a href="<?= htmlspecialchars(eventforge_admin_path('delete-event.php')) ?>?id=<?= (int) $row['id'] ?>" onclick="return confirm('Delete this event?');">Delete</a>
            <?php endif; ?>
          </td>
        </tr>

        <?php foreach ($childItems as $childItem): ?>
          <?php
          $childRow = $childItem['row'];
          $childMeta = $childItem['meta'];
          $childIsIndependent = !empty($childRow['is_independent_child']);
          $childIsCanceled = !empty($childRow['is_canceled']);
          $childIsOngoing = !empty($childMeta['is_ongoing']);
          ?>
          <tr
            class="child-row<?= $childIsIndependent ? ' independent-child-row' : '' ?><?= $childIsOngoing ? ' ongoing-row' : '' ?>"
            data-child-of="<?= (int) $row['id'] ?>"
            style="display:none;"
          >
            <td>
              <?php if ($childIsIndependent): ?>
                <span title="Independent child event. Originally generated by a recurring parent, now maintained separately." style="color:#167151; padding-left:1.5rem; font-weight:bold;">
                  ↳ Independent
                </span>
              <?php else: ?>
                <span title="Generated child event from a recurring parent." style="color:#3f6244; padding-left:1.5rem;">
                  ↳ Child
                </span>
              <?php endif; ?>
            </td>

            <td>
              <?php if ($childIsCanceled): ?>
                <span class="title-canceled"><?= htmlspecialchars((string) $childRow['title']) ?></span>
                <span class="status-canceled">CANCELED</span>
              <?php else: ?>
                <?= htmlspecialchars((string) $childRow['title']) ?>
              <?php endif; ?>

              <?php if ($childIsOngoing): ?>
                <span class="status-ongoing">ONGOING</span>
              <?php endif; ?>
            </td>

            <td><?= htmlspecialchars((string) ($childMeta['display_start'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string) ($childMeta['display_end'] ?? '')) ?></td>

            <td>
              <?= !empty($childRow['is_published']) ? 'Yes' : 'No' ?>
              |
              <a href="<?= htmlspecialchars(eventforge_admin_path('toggle-publish.php')) ?>?id=<?= (int) $childRow['id'] ?>">
                <?= !empty($childRow['is_published']) ? 'Unpublish' : 'Publish' ?>
              </a>
            </td>

            <td>
              <?php if (!$childIsIndependent): ?>
                <div><em>Generated from series</em></div>
                <div>
                  <a href="<?= htmlspecialchars(eventforge_admin_path('view-event.php')) ?>?id=<?= (int) $childRow['id'] ?>">View</a>
                  |
                  <a
                    href="<?= htmlspecialchars(eventforge_admin_path('make-independent.php')) ?>?id=<?= (int) $childRow['id'] ?>"
                    title="This child will remain grouped under the parent series, but future parent changes will not overwrite it."
                    onclick="return confirm('Make this generated child independent? Future parent changes will no longer overwrite this event.');"
                  >
                    Make Independent
                  </a>
                  |
                  <?php if ($childIsCanceled): ?>
                    <a href="<?= htmlspecialchars(eventforge_admin_path('uncancel-event.php')) ?>?id=<?= (int) $childRow['id'] ?>" onclick="return confirm('Mark this event as active again?');">
                      Uncancel
                    </a>
                  <?php else: ?>
                    <a href="<?= htmlspecialchars(eventforge_admin_path('cancel-event.php')) ?>?id=<?= (int) $childRow['id'] ?>" onclick="return confirm('Cancel this event? This will also make it independent from the series.');">
                      Cancel
                    </a>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <a href="<?= htmlspecialchars(eventforge_admin_path('view-event.php')) ?>?id=<?= (int) $childRow['id'] ?>">View</a> |
                <a href="<?= htmlspecialchars(eventforge_admin_path('event-form.php')) ?>?id=<?= (int) $childRow['id'] ?>">Edit</a> |
                <?php if ($childIsCanceled): ?>
                  <a href="<?= htmlspecialchars(eventforge_admin_path('uncancel-event.php')) ?>?id=<?= (int) $childRow['id'] ?>" onclick="return confirm('Mark this event as active again?');">Uncancel</a> |
                <?php else: ?>
                  <a href="<?= htmlspecialchars(eventforge_admin_path('cancel-event.php')) ?>?id=<?= (int) $childRow['id'] ?>" onclick="return confirm('Cancel this event?');">Cancel</a> |
                <?php endif; ?>
                <a href="<?= htmlspecialchars(eventforge_admin_path('delete-event.php')) ?>?id=<?= (int) $childRow['id'] ?>" onclick="return confirm('Delete this event?');">Delete</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
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