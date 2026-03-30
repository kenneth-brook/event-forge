<?php
declare(strict_types=1);

require __DIR__ . '/includes/installer.php';

if (!eventforge_is_installed()) {
    http_response_code(500);
    exit('Event Forge is not installed.');
}

require __DIR__ . '/includes/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$slug = trim($_GET['slug'] ?? '');

if ($id <= 0) {
    http_response_code(404);
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
      AND e.is_published = 1
    LIMIT 1
";

$result = mysqli_query($connection, $sql);

if (!$result || !($event = mysqli_fetch_assoc($result))) {
    http_response_code(404);
    exit('Event not found.');
}

$canonicalSlug = (string) ($event['slug'] ?? '');

if ($canonicalSlug !== '' && $slug !== $canonicalSlug) {
    header('Location: ' . eventforge_public_path('event.php') . '?id=' . (int) $event['id'] . '&slug=' . urlencode($canonicalSlug), true, 301);
    exit;
}

$title = (string) $event['title'];
$start = !empty($event['start_datetime']) ? strtotime((string) $event['start_datetime']) : false;
$end = !empty($event['end_datetime']) ? strtotime((string) $event['end_datetime']) : false;
$isCanceled = !empty($event['is_canceled']);
$categoryColor = (string) ($event['category_color'] ?? '');
$categoryFontColor = (string) ($event['category_font_color'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 2rem;
      background: #f5f7fa;
      color: #1f2937;
    }

    .wrap {
      max-width: 860px;
      margin: 0 auto;
      background: #fff;
      padding: 2rem;
      border-radius: 14px;
      box-shadow: 0 10px 24px rgba(0,0,0,.08);
    }

    .category-pill {
      display: inline-block;
      padding: .35rem .7rem;
      border-radius: 999px;
      font-size: .9rem;
      font-weight: 700;
      margin-bottom: 1rem;
    }

    .canceled {
      color: #c62828;
      font-weight: 700;
      margin-left: .5rem;
    }

    .meta {
      color: #4b5563;
      margin-bottom: 1rem;
    }

    .image {
      max-width: 100%;
      height: auto;
      border-radius: 10px;
      margin: 1rem 0;
    }

    a.button {
      display: inline-block;
      padding: .55rem .9rem;
      border: 1px solid #333;
      border-radius: 6px;
      text-decoration: none;
      color: #111;
      background: #fff;
      margin-top: 1rem;
    }
  </style>
</head>
<body>
  <div class="wrap">
    <?php if (!empty($event['category_name'])): ?>
      <div
        class="category-pill"
        style="
          background: <?= htmlspecialchars($categoryColor !== '' ? $categoryColor : '#e8edf3') ?>;
          color: <?= htmlspecialchars($categoryFontColor !== '' ? $categoryFontColor : '#1f2937') ?>;
        "
      >
        <?= htmlspecialchars((string) $event['category_name']) ?>
      </div>
    <?php endif; ?>

    <h1>
      <?= htmlspecialchars($title) ?>
      <?php if ($isCanceled): ?>
        <span class="canceled">CANCELED</span>
      <?php endif; ?>
    </h1>

    <div class="meta">
      <?php if ($start): ?>
        <div>
          <strong>Starts:</strong>
          <?= htmlspecialchars(date('l, F j, Y g:i A', $start)) ?>
        </div>
      <?php endif; ?>

      <?php if ($end): ?>
        <div>
          <strong>Ends:</strong>
          <?= htmlspecialchars(date('l, F j, Y g:i A', $end)) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($event['location'])): ?>
        <div>
          <strong>Location:</strong>
          <?= htmlspecialchars((string) $event['location']) ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($event['summary'])): ?>
      <p><?= nl2br(htmlspecialchars((string) $event['summary'])) ?></p>
    <?php endif; ?>

    <?php if (!empty($event['image_path'])): ?>
      <img class="image" src="<?= htmlspecialchars((string) $event['image_path']) ?>" alt="<?= htmlspecialchars($title) ?>">
    <?php endif; ?>

    <?php if (!empty($event['description'])): ?>
      <div><?= nl2br(htmlspecialchars((string) $event['description'])) ?></div>
    <?php endif; ?>

    <?php if (!empty($event['pdf_path'])): ?>
      <p>
        <a class="button" href="<?= htmlspecialchars((string) $event['pdf_path']) ?>" target="_blank" rel="noopener">
          View Event PDF
        </a>
      </p>
    <?php endif; ?>

    <?php if (!empty($event['external_url'])): ?>
      <p>
        <a class="button" href="<?= htmlspecialchars((string) $event['external_url']) ?>" target="_blank" rel="noopener">
          Visit External Link
        </a>
      </p>
    <?php endif; ?>
  </div>
</body>
</html>