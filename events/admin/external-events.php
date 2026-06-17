<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/installer.php';

if (!eventforge_is_installed()) {
    header('Location: ' . eventforge_admin_path('setup.php'));
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/system.php';
require_once __DIR__ . '/../includes/external-events.php';

require_login();

if (!can_sync_external_events() && !is_admin()) {
    http_response_code(403);
    exit('Access denied.');
}

$enabled = eventforge_external_events_enabled($connection);
$provider = eventforge_get_external_events_provider($connection);
$providerDefinition = eventforge_get_external_events_provider_definition($connection);
$providers = eventforge_external_event_providers();
$feedUrl = eventforge_get_external_events_feed_url($connection);
$lastSyncAt = eventforge_get_system_value($connection, 'external_events_last_sync_at') ?? '';
$lastSyncStatsRaw = eventforge_get_system_value($connection, 'external_events_last_sync_stats') ?? '';
$lastSyncStats = json_decode($lastSyncStatsRaw, true);
$status = trim((string) ($_GET['status'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>External Event Sync</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 2rem; background: #f5f7fa; color: #1f2937; }
    .wrap { max-width: 900px; margin: 0 auto; background: #fff; padding: 2rem; border-radius: 12px; box-shadow: 0 10px 24px rgba(0,0,0,.08); }
    .topbar { display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap; margin-bottom:1.5rem; }
    .button, button { display:inline-block; padding:.55rem .85rem; border:1px solid #333; background:#fff; color:#111; border-radius:6px; text-decoration:none; cursor:pointer; }
    .button-primary { background:#3f6244; color:#fff; border-color:#3f6244; }
    label { display:block; margin:1rem 0 .35rem; font-weight:600; }
    input[type="url"], select { width:100%; padding:.7rem; box-sizing:border-box; }
    .toggle-row { display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; margin-top:1rem; }
    .notice { margin-bottom:1rem; padding:.85rem 1rem; border-radius:8px; background:#edf8ef; border:1px solid #b7ddbe; color:#1f4d28; font-weight:600; }
    .error { margin-bottom:1rem; padding:.85rem 1rem; border-radius:8px; background:#fee2e2; border:1px solid #fca5a5; color:#7f1d1d; font-weight:600; }
    .card { border:1px solid #d7dde5; border-radius:10px; padding:1rem; background:#fafbfc; margin-top:1rem; }
    .note { color:#4b5563; font-size:.95rem; }
    code { background:#eef2f7; padding:.15rem .35rem; border-radius:4px; }
    dl { display:grid; grid-template-columns:160px 1fr; gap:.5rem 1rem; }
    dt { font-weight:700; }
    dd { margin:0; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <h1>External Event Sync</h1>
      <div>
        <a class="button" href="<?= htmlspecialchars(eventforge_admin_path('index.php')) ?>">Back to Events</a>
        <a class="button" href="<?= htmlspecialchars(eventforge_admin_path('logout.php')) ?>">Log Out</a>
      </div>
    </div>

    <?php if ($status === 'settings-saved'): ?>
      <div class="notice">External event settings saved.</div>
    <?php elseif ($status === 'synced'): ?>
      <div class="notice">External events synced.</div>
    <?php elseif ($status === 'sync-error'): ?>
      <div class="error">External event sync failed. Check the PHP error log for details.</div>
    <?php elseif ($status === 'settings-error'): ?>
      <div class="error">External event settings could not be saved. Check the PHP error log for details.</div>
    <?php endif; ?>

    <div class="card">
      <h2>Current Status</h2>
      <dl>
        <dt>Service</dt>
        <dd><?= $enabled ? 'Active' : 'Inactive' ?></dd>
        <dt>Selected Provider</dt>
        <dd><?= htmlspecialchars((string) ($providerDefinition['label'] ?? $provider)) ?></dd>
        <dt>Feed URL</dt>
        <dd><?= $feedUrl !== '' ? htmlspecialchars($feedUrl) : '<em>Not configured</em>' ?></dd>
        <dt>Last Sync</dt>
        <dd><?= $lastSyncAt !== '' ? htmlspecialchars($lastSyncAt) : '<em>Never</em>' ?></dd>
      </dl>

      <?php if (is_array($lastSyncStats)): ?>
        <h3>Last Sync Results</h3>
        <dl>
          <dt>Provider</dt><dd><?= htmlspecialchars((string) ($lastSyncStats['provider'] ?? '')) ?></dd>
          <dt>Fetched</dt><dd><?= (int) ($lastSyncStats['fetched'] ?? 0) ?></dd>
          <dt>Inserted</dt><dd><?= (int) ($lastSyncStats['inserted'] ?? 0) ?></dd>
          <dt>Updated</dt><dd><?= (int) ($lastSyncStats['updated'] ?? 0) ?></dd>
          <dt>Unchanged</dt><dd><?= (int) ($lastSyncStats['unchanged'] ?? 0) ?></dd>
          <dt>Skipped</dt><dd><?= (int) ($lastSyncStats['skipped'] ?? 0) ?></dd>
        </dl>
      <?php endif; ?>
    </div>

    <?php if (is_admin()): ?>
      <div class="card">
        <h2>Admin Configuration</h2>
        <p class="note">Turn on external event sync, choose the provider, and provide that provider's feed URL. Imported events are stored unpublished until reviewed.</p>

        <form method="post" action="<?= htmlspecialchars(eventforge_admin_path('save-external-events-settings.php')) ?>">
          <?= eventforge_csrf_input() ?>

          <div class="toggle-row">
            <label style="margin:0;">
              <input type="checkbox" name="external_events_enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
              Enable external event sync
            </label>
          </div>

          <label for="external_events_provider">External Event Service</label>
          <select id="external_events_provider" name="external_events_provider">
            <?php foreach ($providers as $providerKey => $definition): ?>
              <option
                value="<?= htmlspecialchars($providerKey) ?>"
                data-placeholder="<?= htmlspecialchars((string) ($definition['feed_url_placeholder'] ?? '')) ?>"
                data-description="<?= htmlspecialchars((string) ($definition['description'] ?? '')) ?>"
                <?= $provider === $providerKey ? 'selected' : '' ?>
              >
                <?= htmlspecialchars((string) ($definition['label'] ?? $providerKey)) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="note" id="external-provider-description">
            <?= htmlspecialchars((string) ($providerDefinition['description'] ?? '')) ?>
          </p>

          <label for="external_events_feed_url">Feed URL</label>
          <input
            id="external_events_feed_url"
            name="external_events_feed_url"
            type="url"
            value="<?= htmlspecialchars($feedUrl) ?>"
            placeholder="<?= htmlspecialchars((string) ($providerDefinition['feed_url_placeholder'] ?? '')) ?>"
          >

          <p class="note">ChamberMate images are imported as remote URLs using <code>avatarStorageKey</code>. Future providers can define their own mapping in their adapter.</p>

          <div class="toggle-row">
            <button type="submit" class="button-primary">Save External Event Settings</button>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <?php if (can_sync_external_events() && $enabled): ?>
      <div class="card">
        <h2>Sync Other Events</h2>
        <p class="note">This pulls the selected provider feed, updates existing imported events, and adds new events as unpublished drafts.</p>
        <form method="post" action="<?= htmlspecialchars(eventforge_admin_path('sync-external-events.php')) ?>" onsubmit="return confirm('Sync external events now? Imported events will remain unpublished until reviewed.');">
          <?= eventforge_csrf_input() ?>
          <button type="submit" class="button-primary">Sync Other Events</button>
        </form>
      </div>
    <?php elseif (can_sync_external_events()): ?>
      <div class="card">
        <h2>Sync Other Events</h2>
        <p class="note">External event sync is not active.</p>
      </div>
    <?php endif; ?>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const providerSelect = document.getElementById('external_events_provider');
      const feedUrlInput = document.getElementById('external_events_feed_url');
      const description = document.getElementById('external-provider-description');

      if (!providerSelect) return;

      function updateProviderHelp() {
        const selected = providerSelect.options[providerSelect.selectedIndex];
        if (!selected) return;

        if (feedUrlInput && feedUrlInput.value.trim() === '') {
          feedUrlInput.placeholder = selected.getAttribute('data-placeholder') || '';
        }

        if (description) {
          description.textContent = selected.getAttribute('data-description') || '';
        }
      }

      providerSelect.addEventListener('change', updateProviderHelp);
      updateProviderHelp();
    });
  </script>
</body>
</html>
