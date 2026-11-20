<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$uid = require_login();

$categories = CATEGORIES;
$query = trim((string) ($_GET['q'] ?? ''));
$category = (string) ($_GET['cat'] ?? '');
if (!in_array($category, $categories, true)) {
    $category = '';
}

// READ: search the catalog by title / author and optional category.
$sql = 'SELECT * FROM books WHERE 1=1';
$args = [];
if ($category !== '') {
    $sql .= ' AND category = :cat';
    $args[':cat'] = $category;
}
if ($query !== '') {
    $like = '%' . addcslashes($query, '%_\\') . '%';
    $sql .= ' AND (title LIKE :q1 OR author LIKE :q2)';
    $args[':q1'] = $like;
    $args[':q2'] = $like;
}
$sql .= ' ORDER BY title';

$results = [];
try {
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    $results = $stmt->fetchAll();
} catch (PDOException $ex) {
    error_log($ex->getMessage());
}

$activeNav = 'search';
?>
<!DOCTYPE html>
<html lang="en" <?= theme_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Search</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app">
  <header class="search-header">
    <form method="get" action="search.php" class="search-bar" role="search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="21" y2="21"/></svg>
      <input type="search" name="q" value="<?= e($query) ?>" placeholder="Search for books" aria-label="Search for books">
      <?php if ($category !== ''): ?>
        <input type="hidden" name="cat" value="<?= e($category) ?>">
      <?php endif; ?>
    </form>

    <nav class="chips" aria-label="Categories">
      <?php foreach ($categories as $cat): ?>
        <?php
          $params = array_filter(['q' => $query, 'cat' => $cat === $category ? '' : $cat], fn($v) => $v !== '');
          $href = 'search.php' . ($params ? '?' . http_build_query($params) : '');
        ?>
        <a href="<?= e($href) ?>" class="chip<?= $cat === $category ? ' active' : '' ?>"><?= e($cat) ?></a>
      <?php endforeach; ?>
      <a href="search.php" class="chip chip-more" aria-label="All categories">&#8964;</a>
    </nav>
  </header>

  <main class="app-main">
    <?= flash_render() ?>
    <?php if ($results): ?>
      <div class="results">
        <?php foreach ($results as $book): ?>
          <a href="book.php?id=<?= (int) $book['id'] ?>" class="book">
            <?= cover_html($book) ?>
            <p><?= e($book['title']) ?></p>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="empty">No books found<?= $query !== '' ? ' for "' . e($query) . '"' : '' ?>.</p>
    <?php endif; ?>
    <a href="book_form.php" class="fab" aria-label="Add a book">+</a>
  </main>

  <?php require __DIR__ . '/partials/bottom_nav.php'; ?>
</div>
</body>
</html>
