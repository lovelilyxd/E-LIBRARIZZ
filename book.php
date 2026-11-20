<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$uid = require_login();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$book = fetch_book($id);
if (!$book) {
    flash('fail', 'That book was not found.');
    redirect('home.php');
}

// DELETE: remove the book from the catalog (library entries and history cascade).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    post_guard('book.php?id=' . $id);
    if ((string) ($_POST['action'] ?? '') === 'delete') {
        try {
            db()->prepare('DELETE FROM books WHERE id = :id')->execute([':id' => $id]);
            $img = (string) ($book['cover_image'] ?? '');
            if ($img !== '' && preg_match('/^[a-f0-9]{16}\.(jpg|png|webp)$/', $img)) {
                @unlink(__DIR__ . '/uploads/covers/' . $img);
            }
            flash('ok', '"' . $book['title'] . '" was deleted.');
            redirect('home.php');
        } catch (PDOException $ex) {
            error_log($ex->getMessage());
            flash('fail', 'Could not delete the book. Please try again later.');
            redirect('book.php?id=' . $id);
        }
    }
    redirect('book.php?id=' . $id);
}

// READ: this user's library entry for the book (if any).
$stmt = db()->prepare('SELECT status, current_page FROM library_items WHERE user_id = :u AND book_id = :b LIMIT 1');
$stmt->execute([':u' => $uid, ':b' => $id]);
$item = $stmt->fetch() ?: null;

$statusLabels = ['saved' => 'Saves', 'current' => 'Current Reads', 'archived' => 'Archives'];
$returnTo = 'book.php?id=' . $id;
$activeNav = 'home';
?>
<!DOCTYPE html>
<html lang="en" <?= theme_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($book['title']) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app">
  <header class="app-header sub-header">
    <a href="home.php" class="icon-btn back-btn" aria-label="Back">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    </a>
    <h2>BOOK DETAILS</h2>
    <span class="header-spacer"></span>
  </header>

  <main class="app-main detail-main">
    <?= flash_render() ?>

    <div class="detail-cover"><?= cover_html($book, 'cover big') ?></div>
    <h1 class="detail-title"><?= e($book['title']) ?></h1>
    <p class="detail-meta">
      <?= $book['author'] !== '' ? e($book['author']) . ' · ' : '' ?><?= e($book['category']) ?> · <?= (int) $book['total_pages'] ?> pages
    </p>
    <p class="detail-desc"><?= nl2br(e($book['description'])) ?></p>

    <?php if ($item): ?>
      <p class="detail-status">In your library: <strong><?= e($statusLabels[$item['status']]) ?></strong> · Page <?= (int) $item['current_page'] ?></p>
    <?php endif; ?>

    <div class="detail-actions">
      <a class="submit" href="read.php?id=<?= $id ?>"><?= $item && $item['status'] === 'current' ? 'CONTINUE READING' : 'START READING' ?></a>

      <?php if (!$item): ?>
        <form method="post" action="library_action.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="book_id" value="<?= $id ?>">
          <input type="hidden" name="status" value="saved">
          <input type="hidden" name="return" value="<?= e($returnTo) ?>">
          <button type="submit" class="btn-outline wide">SAVE TO LIBRARY</button>
        </form>
      <?php else: ?>
        <form method="post" action="library_action.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="remove">
          <input type="hidden" name="book_id" value="<?= $id ?>">
          <input type="hidden" name="return" value="<?= e($returnTo) ?>">
          <button type="submit" class="btn-outline wide">REMOVE FROM LIBRARY</button>
        </form>
      <?php endif; ?>

      <a class="btn-outline wide" href="book_form.php?id=<?= $id ?>">EDIT BOOK</a>

      <form method="post" action="book.php?id=<?= $id ?>" onsubmit="return confirm('Delete this book for everyone? This also removes it from every library and history.');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="btn-outline wide btn-danger">DELETE BOOK</button>
      </form>
    </div>
  </main>

  <?php require __DIR__ . '/partials/bottom_nav.php'; ?>
</div>
</body>
</html>
