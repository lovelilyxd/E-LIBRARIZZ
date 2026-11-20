<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/validation.php';

// One form for CREATE (no id) and UPDATE (?id=).
$uid = require_login();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$book = null;
if ($id > 0) {
    $book = fetch_book($id);
    if (!$book) {
        flash('fail', 'That book was not found.');
        redirect('home.php');
    }
}
$editing = $book !== null;

$errors = [];
$form = [
    'title'       => $book['title'] ?? '',
    'label'       => $book['label'] ?? '',
    'author'      => $book['author'] ?? '',
    'category'    => $book['category'] ?? CATEGORIES[0],
    'section'     => $book['section'] ?? '',
    'description' => $book['description'] ?? '',
    'total_pages' => (string) ($book['total_pages'] ?? '100'),
    'color'       => $book['color'] ?? '#1f5f7a',
];

$coverDir = __DIR__ . '/uploads/covers';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        foreach ($form as $key => $_) {
            $form[$key] = trim((string) ($_POST[$key] ?? ''));
        }
        $errors = validate_book($form);

        // Optional cover image upload.
        $newCover = null;
        $upload = $_FILES['cover'] ?? null;
        if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($upload['error'] !== UPLOAD_ERR_OK) {
                $errors['cover'] = 'The image could not be uploaded.';
            } elseif ($upload['size'] > 2 * 1024 * 1024) {
                $errors['cover'] = 'Image must be 2 MB or smaller.';
            } else {
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
                $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
                if ($ext === null) {
                    $errors['cover'] = 'Cover must be a JPG, PNG or WebP image.';
                } else {
                    $newCover = ['name' => bin2hex(random_bytes(8)) . '.' . $ext, 'tmp' => $upload['tmp_name']];
                }
            }
        }

        if (!$errors) {
            try {
                $label = $form['label'] !== '' ? $form['label'] : $form['title'];
                $cover = $book['cover_image'] ?? null;
                $oldCover = null;

                if ($newCover) {
                    if (!is_dir($coverDir)) mkdir($coverDir, 0755, true);
                    if (!move_uploaded_file($newCover['tmp'], $coverDir . '/' . $newCover['name'])) {
                        throw new RuntimeException('Could not save the uploaded image.');
                    }
                    $oldCover = $cover;
                    $cover = $newCover['name'];
                } elseif ($editing && isset($_POST['remove_cover'])) {
                    $oldCover = $cover;
                    $cover = null;
                }

                $params = [
                    ':title'  => $form['title'],
                    ':label'  => $label,
                    ':author' => $form['author'],
                    ':cat'    => $form['category'],
                    ':sec'    => $form['section'],
                    ':desc'   => $form['description'],
                    ':pages'  => (int) $form['total_pages'],
                    ':color'  => $form['color'],
                    ':cover'  => $cover,
                ];

                if ($editing) {
                    $params[':id'] = $id;
                    db()->prepare('UPDATE books SET title=:title, label=:label, author=:author, category=:cat, section=:sec,
                                   description=:desc, total_pages=:pages, color=:color, cover_image=:cover WHERE id=:id')
                        ->execute($params);
                    $savedId = $id;
                    flash('ok', 'Book updated.');
                } else {
                    $params[':uid'] = $uid;
                    db()->prepare('INSERT INTO books (title, label, author, category, section, description, total_pages, color, cover_image, created_by)
                                   VALUES (:title, :label, :author, :cat, :sec, :desc, :pages, :color, :cover, :uid)')
                        ->execute($params);
                    $savedId = (int) db()->lastInsertId();
                    flash('ok', 'Book added to the catalog.');
                }

                if ($oldCover && preg_match('/^[a-f0-9]{16}\.(jpg|png|webp)$/', (string) $oldCover)) {
                    @unlink($coverDir . '/' . $oldCover);
                }
                redirect('book.php?id=' . $savedId);
            } catch (PDOException | RuntimeException $ex) {
                error_log($ex->getMessage());
                $errors['form'] = 'Something went wrong. Please try again later.';
            }
        }
    }
}

$activeNav = 'home';
$field = fn(string $k): string => e($errors[$k] ?? '');
?>
<!DOCTYPE html>
<html lang="en" <?= theme_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $editing ? 'Edit Book' : 'Add Book' ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app">
  <header class="app-header sub-header">
    <a href="<?= $editing ? 'book.php?id=' . $id : 'home.php' ?>" class="icon-btn back-btn" aria-label="Back">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    </a>
    <h2><?= $editing ? 'EDIT BOOK' : 'ADD BOOK' ?></h2>
    <span class="header-spacer"></span>
  </header>

  <main class="app-main profile-main">
    <?php if (!empty($errors['form'])): ?>
      <div class="message fail"><?= e($errors['form']) ?></div>
    <?php endif; ?>

    <form method="post" action="book_form.php<?= $editing ? '?id=' . $id : '' ?>" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

      <div class="field">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" maxlength="120" value="<?= e($form['title']) ?>" placeholder="Enter book title">
        <div class="error"><?= $field('title') ?></div>
      </div>

      <div class="field">
        <label for="author">Author</label>
        <input type="text" id="author" name="author" maxlength="80" value="<?= e($form['author']) ?>" placeholder="Enter author (optional)">
        <div class="error"><?= $field('author') ?></div>
      </div>

      <div class="field">
        <label for="category">Category</label>
        <select id="category" name="category">
          <?php foreach (CATEGORIES as $cat): ?>
            <option value="<?= e($cat) ?>"<?= $cat === $form['category'] ? ' selected' : '' ?>><?= e($cat) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="error"><?= $field('category') ?></div>
      </div>

      <div class="field">
        <label for="section">Section</label>
        <input type="text" id="section" name="section" maxlength="60" value="<?= e($form['section']) ?>" placeholder="e.g. Core Computing Courses">
        <div class="error"><?= $field('section') ?></div>
      </div>

      <div class="field">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="5" maxlength="1000" placeholder="What is this book about?"><?= e($form['description']) ?></textarea>
        <div class="error"><?= $field('description') ?></div>
      </div>

      <div class="field">
        <label for="total_pages">Total pages</label>
        <input type="text" inputmode="numeric" id="total_pages" name="total_pages" maxlength="4" value="<?= e($form['total_pages']) ?>">
        <div class="error"><?= $field('total_pages') ?></div>
      </div>

      <div class="field">
        <label for="label">Cover text</label>
        <input type="text" id="label" name="label" maxlength="60" value="<?= e($form['label']) ?>" placeholder="Shown on the cover (defaults to the title)">
        <div class="error"><?= $field('label') ?></div>
      </div>

      <div class="field">
        <label for="color">Cover colour</label>
        <input type="color" id="color" name="color" value="<?= e($form['color']) ?>">
        <div class="error"><?= $field('color') ?></div>
      </div>

      <div class="field">
        <label for="cover">Cover image (optional)</label>
        <input type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/webp">
        <div class="hint">JPG, PNG or WebP, up to 2 MB.</div>
        <div class="error"><?= $field('cover') ?></div>
        <?php if ($editing && !empty($book['cover_image'])): ?>
          <label class="check"><input type="checkbox" name="remove_cover" value="1"> Remove current cover image</label>
        <?php endif; ?>
      </div>

      <button class="submit" type="submit"><?= $editing ? 'SAVE CHANGES' : 'ADD BOOK' ?></button>
    </form>

    <a href="<?= $editing ? 'book.php?id=' . $id : 'home.php' ?>" class="btn-outline wide">CANCEL</a>
  </main>
</div>
</body>
</html>
