<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/installer.php';
require_once __DIR__ . '/../includes/system.php';
require_once __DIR__ . '/../includes/functions.php';

if (!eventforge_is_installed()) {
    header('Location: ' . eventforge_admin_path('setup.php'));
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    exit('Event not found.');
}

$sql = "
    SELECT
        e.*,
        c.name AS category_name,
        c.color AS category_color,
        c.font_color AS category_font_color
    FROM events e
    LEFT JOIN event_categories c ON e.category_id = c.id
    WHERE e.id = {$id}
    LIMIT 1
";

$result = mysqli_query($connection, $sql);

if (!$result || !($event = mysqli_fetch_assoc($result))) {
    exit('Event not found.');
}

$publicUrl = eventforge_build_public_event_url(
    $connection,
    (int) $event['id'],
    !empty($event['slug']) ? (string) $event['slug'] : null
);

$publicUrlMessage = $publicUrl !== ''
    ? $publicUrl
    : 'This feature requires further setup, please contact your administrator for assistance.';

$isCanceled = !empty($event['is_canceled']);
$isRecurringParent = !empty($event['is_recurring_parent']);
$isChild = !empty($event['parent_event_id']);
$isIndependentChild = !empty($event['is_independent_child']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>View Event</title>
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
      box-shadow: 0 10px 24px rgba(0,0,0,.08);
    }

    .top-actions {
      margin-bottom: 1rem;
    }

    .meta {
      margin: 1rem 0;
      color: #4b5563;
    }

    .block {
      margin-top: 1.25rem;
    }

    .pill {
      display: inline-block;
      padding: .35rem .7rem;
      border-radius: 999px;
      font-size: .9rem;
      font-weight: 700;
    }

    .url-box {
      margin-top: .5rem;
      padding: .75rem;
      background: #f8fafc;
      border: 1px solid #d7dde5;
      border-radius: 8px;
      word-break: break-all;
      font-family: monospace;
    }

    .preview-image {
      max-width: 320px;
      height: auto;
      border-radius: 8px;
      display: block;
      margin-top: .5rem;
    }

    .button {
      display: inline-block;
      padding: .5rem .8rem;
      border: 1px solid #333;
      text-decoration: none;
      background: #fff;
      color: #111;
      border-radius: 6px;
      margin-right: .5rem;
      cursor: pointer;
    }

    .canceled {
      color: #c62828;
      font-weight: 700;
      margin-left: .5rem;
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top-actions">
      <a class="button" href="<?= htmlspecialchars(eventforge_admin_path('index.php')) ?>">Back to Events</a>
      <a class="button" href="<?= htmlspecialchars(eventforge_admin_path('event-form.php')) ?>?id=<?= (int) $event['id'] ?>">Edit Event</a>
    </div>

    <?php if (!empty($event['category_name'])): ?>
      <div
        class="pill"
        style="
          background: <?= htmlspecialchars(!empty($event['category_color']) ? (string) $event['category_color'] : '#e8edf3') ?>;
          color: <?= htmlspecialchars(!empty($event['category_font_color']) ? (string) $event['category_font_color'] : '#1f2937') ?>;
        "
      >
        <?= htmlspecialchars((string) $event['category_name']) ?>
      </div>
    <?php endif; ?>

    <h1>
      <?= htmlspecialchars((string) $event['title']) ?>
      <?php if ($isCanceled): ?>
        <span class="canceled">CANCELED</span>
      <?php endif; ?>
    </h1>

    <div class="meta">
      <?php if (!empty($event['start_datetime'])): ?>
        <div><strong>Starts:</strong> <?= htmlspecialchars((string) $event['start_datetime']) ?></div>
      <?php endif; ?>

      <?php if (!empty($event['end_datetime'])): ?>
        <div><strong>Ends:</strong> <?= htmlspecialchars((string) $event['end_datetime']) ?></div>
      <?php endif; ?>

      <?php if (!empty($event['location'])): ?>
        <div><strong>Location:</strong> <?= htmlspecialchars((string) $event['location']) ?></div>
      <?php endif; ?>

      <div>
        <strong>Type:</strong>
        <?php if ($isRecurringParent): ?>
          Recurring Parent
        <?php elseif ($isChild && $isIndependentChild): ?>
          Independent Child
        <?php elseif ($isChild): ?>
          Generated Child
        <?php else: ?>
          Single Event
        <?php endif; ?>
      </div>
    </div>

    <div class="block">
      <h2>Public URL</h2>
      <div class="url-box" id="event-public-url"><?= htmlspecialchars($publicUrlMessage) ?></div>

      <?php if ($publicUrl !== ''): ?>
        <p style="margin-top:.75rem;">
          <button type="button" class="button" onclick="copyEventUrl()">Copy URL</button>
          <a class="button" href="<?= htmlspecialchars($publicUrl) ?>" target="_blank" rel="noopener">Open Public Link</a>
        </p>
      <?php endif; ?>
    </div>

    <?php if (!empty($event['summary'])): ?>
      <div class="block">
        <h2>Summary</h2>
        <p><?= nl2br(htmlspecialchars((string) $event['summary'])) ?></p>
      </div>
    <?php endif; ?>

    <?php if (!empty($event['description'])): ?>
      <div class="block">
        <h2>Description</h2>
        <div><?= nl2br(htmlspecialchars((string) $event['description'])) ?></div>
      </div>
    <?php endif; ?>

    <?php if (!empty($event['image_path'])): ?>
      <div class="block">
        <h2>Image</h2>
        <img class="preview-image" src="<?= htmlspecialchars((string) $event['image_path']) ?>" alt="<?= htmlspecialchars((string) $event['title']) ?>">
      </div>
    <?php endif; ?>

    <?php if (!empty($event['pdf_path'])): ?>
      <div class="block">
        <h2>PDF</h2>
        <p><a href="<?= htmlspecialchars((string) $event['pdf_path']) ?>" target="_blank" rel="noopener">View PDF</a></p>
      </div>
    <?php endif; ?>

    <?php if (!empty($event['external_url'])): ?>
      <div class="block">
        <h2>External Link</h2>
        <p><a href="<?= htmlspecialchars((string) $event['external_url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) $event['external_url']) ?></a></p>
      </div>
    <?php endif; ?>
  </div>

  <script>
    function copyEventUrl() {
      const el = document.getElementById('event-public-url');
      if (!el) return;

      const url = el.textContent.trim();

      if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => {
          alert('Event URL copied!');
        }).catch(() => {
          fallbackCopy(url);
        });
      } else {
        fallbackCopy(url);
      }
    }

    function fallbackCopy(url) {
      const input = document.createElement('input');
      input.value = url;
      document.body.appendChild(input);
      input.select();
      document.execCommand('copy');
      document.body.removeChild(input);
      alert('Event URL copied!');
    }
  </script>
</body>
</html>