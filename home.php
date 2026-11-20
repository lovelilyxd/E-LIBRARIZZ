<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$uid = require_login();

// READ: every book in the catalog, grouped by section.
$sections = [];
try {
    $rows = db()->query('SELECT * FROM books ORDER BY section, title')->fetchAll();
    foreach ($rows as $book) {
        $sections[$book['section']][] = $book;
    }
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    flash('fail', 'Could not load the catalog. Did you run setup.php?');
}

$activeNav = 'home';
?>
<!DOCTYPE html>
<html lang="en" <?= theme_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Home</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app">
  <header class="app-header">
    <div>
      <p class="greeting">Hello,</p>
      <h2><?= e((string) ($_SESSION['user_name'] ?? '')) ?></h2>
    </div>
    <a href="book_form.php" class="btn-pill" aria-label="Add a book">+ Add book</a>
  </header>

  <main class="app-main">
    <?= flash_render() ?>

    <?php if (!$sections): ?>
      <p class="empty">The catalog is empty. <a href="book_form.php">Add the first book</a>.</p>
    <?php endif; ?>

    <?php foreach ($sections as $name => $books): ?>
      <h3 class="section-title"><?= e((string) $name) ?></h3>
      <div class="results">
        <?php foreach ($books as $book): ?>
          <a href="book.php?id=<?= (int) $book['id'] ?>" class="book">
            <?= cover_html($book) ?>
            <p><?= e($book['title']) ?></p>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </main>

  <?php require __DIR__ . '/partials/bottom_nav.php'; ?>
</div>
</body>
</html>
