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

if (!can_manage_users()) {
    http_response_code(403);
    exit('Access denied.');
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$category = [
    'id' => 0,
    'name' => '',
    'slug' => '',
    'color' => '#3F6244',
    'font_color' => '#FFFFFF',
    'is_active' => 1,
];

if ($id > 0) {
    $result = mysqli_query($connection, "
        SELECT id, name, slug, color, font_color, is_active
        FROM event_categories
        WHERE id = {$id}
        LIMIT 1
    ");

    if ($result && $row = mysqli_fetch_assoc($result)) {
        $category = array_merge($category, $row);

        if (empty($category['color'])) {
            $category['color'] = '#3F6244';
        }

        if (empty($category['font_color'])) {
            $category['font_color'] = '#FFFFFF';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $id > 0 ? 'Edit Category' : 'Add Category' ?></title>
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 2rem;
      background: #f5f7fa;
      color: #1f2937;
    }

    .wrap {
      max-width: 760px;
      margin: 0 auto;
      background: #fff;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 10px 24px rgba(0,0,0,.08);
    }

    .top-actions {
      margin-bottom: 1rem;
    }

    label {
      display: block;
      margin: 1rem 0 .35rem;
      font-weight: 600;
    }

    input[type="text"],
    input[type="color"] {
      width: 100%;
      padding: .7rem;
      box-sizing: border-box;
    }

    .row {
      display: grid;
      grid-template-columns: 140px 1fr;
      gap: 1rem;
      align-items: end;
      margin-top: 1rem;
    }

    .actions {
      margin-top: 1.5rem;
    }

    .preview {
      margin-top: 1.5rem;
      padding: 1rem;
      border: 1px solid #d7dde5;
      border-radius: 10px;
      background: #f8fafc;
    }

    .preview-pill {
      display: inline-block;
      padding: .45rem .8rem;
      border-radius: 999px;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top-actions">
      <a href="<?= htmlspecialchars(eventforge_admin_path('settings.php')) ?>">← Back to Settings</a>
    </div>

    <h1><?= $id > 0 ? 'Edit Category' : 'Add Category' ?></h1>

    <form method="post" action="<?= htmlspecialchars(eventforge_admin_path('save-category.php')) ?>">
      <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">

      <label for="category_name">Name</label>
      <input
        id="category_name"
        name="name"
        type="text"
        required
        value="<?= htmlspecialchars((string) $category['name']) ?>"
      >

      <div class="row">
        <div>
          <label for="category_color_picker">Background</label>
          <input
            id="category_color_picker"
            name="color_picker"
            type="color"
            value="<?= htmlspecialchars((string) $category['color']) ?>"
            style="height:44px;padding:0;"
          >
        </div>

        <div>
          <label for="category_color">Background Hex</label>
          <input
            id="category_color"
            name="color"
            type="text"
            value="<?= htmlspecialchars((string) $category['color']) ?>"
            placeholder="#3F6244"
          >
        </div>
      </div>

      <div class="row">
        <div>
          <label for="category_font_color_picker">Font Color</label>
          <input
            id="category_font_color_picker"
            name="font_color_picker"
            type="color"
            value="<?= htmlspecialchars((string) $category['font_color']) ?>"
            style="height:44px;padding:0;"
          >
        </div>

        <div>
          <label for="category_font_color">Font Hex</label>
          <input
            id="category_font_color"
            name="font_color"
            type="text"
            value="<?= htmlspecialchars((string) $category['font_color']) ?>"
            placeholder="#FFFFFF"
          >
        </div>
      </div>

      <label style="margin-top:1rem;">
        <input type="checkbox" name="is_active" value="1" <?= !empty($category['is_active']) ? 'checked' : '' ?>>
        Active
      </label>

      <div class="preview">
        <strong>Preview:</strong>
        <div style="margin-top:.75rem;">
          <span
            id="category_preview"
            class="preview-pill"
            style="background:<?= htmlspecialchars((string) $category['color']) ?>; color:<?= htmlspecialchars((string) $category['font_color']) ?>;"
          >
            <?= htmlspecialchars((string) ($category['name'] !== '' ? $category['name'] : 'Category Preview')) ?>
          </span>
        </div>
      </div>

      <div class="actions">
        <button type="submit">Save Category</button>
      </div>
    </form>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const bgPicker = document.getElementById('category_color_picker');
    const bgHex = document.getElementById('category_color');
    const fontPicker = document.getElementById('category_font_color_picker');
    const fontHex = document.getElementById('category_font_color');
    const nameInput = document.getElementById('category_name');
    const preview = document.getElementById('category_preview');

    const updatePreview = () => {
      if (!preview) return;

      const bg = bgHex.value.trim();
      const fg = fontHex.value.trim();
      const name = nameInput.value.trim();

      if (/^#[0-9A-Fa-f]{6}$/.test(bg)) {
        preview.style.background = bg;
      }

      if (/^#[0-9A-Fa-f]{6}$/.test(fg)) {
        preview.style.color = fg;
      }

      preview.textContent = name !== '' ? name : 'Category Preview';
    };

    if (bgPicker && bgHex) {
      bgPicker.addEventListener('input', () => {
        bgHex.value = bgPicker.value.toUpperCase();
        updatePreview();
      });

      bgHex.addEventListener('input', () => {
        const value = bgHex.value.trim();
        if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
          bgPicker.value = value;
        }
        updatePreview();
      });
    }

    if (fontPicker && fontHex) {
      fontPicker.addEventListener('input', () => {
        fontHex.value = fontPicker.value.toUpperCase();
        updatePreview();
      });

      fontHex.addEventListener('input', () => {
        const value = fontHex.value.trim();
        if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
          fontPicker.value = value;
        }
        updatePreview();
      });
    }

    if (nameInput) {
      nameInput.addEventListener('input', updatePreview);
    }
  });
  </script>
</body>
</html>