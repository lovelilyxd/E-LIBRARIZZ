<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

// Reader. Every page turn saves progress: it updates the library entry (UPDATE/CREATE)
// and writes a row to the reading history (CREATE).
$uid = require_login();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$book = fetch_book($id);
if (!$book) {
    flash('fail', 'That book was not found.');
    redirect('library.php');
}

$total = max(1, (int) $book['total_pages']);

$stmt = db()->prepare('SELECT status, current_page FROM library_items WHERE user_id = :u AND book_id = :b LIMIT 1');
$stmt->execute([':u' => $uid, ':b' => $id]);
$item = $stmt->fetch() ?: null;
$page = $item ? min($total, max(1, (int) $item['current_page'])) : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    post_guard('read.php?id=' . $id);

    $action = (string) ($_POST['action'] ?? '');
    $new = $page;
    if ($action === 'next')         $new = $page + 1;
    elseif ($action === 'prev')     $new = $page - 1;
    elseif ($action === 'restart')  $new = 1;
    elseif ($action === 'goto' && ctype_digit(trim((string) ($_POST['page'] ?? '')))) {
        $new = (int) trim((string) $_POST['page']);
    }
    $new = min($total, max(1, $new));

    try {
        $finished = $new >= $total;
        $progress = (int) round($new / $total * 100);
        $chapter  = intdiv($new - 1, CHAPTER_SIZE) + 1;

        db()->prepare(
            'INSERT INTO library_items (user_id, book_id, status, current_page)
             VALUES (:u, :b, :s, :p)
             ON DUPLICATE KEY UPDATE status = VALUES(status), current_page = VALUES(current_page), updated_at = CURRENT_TIMESTAMP'
        )->execute([':u' => $uid, ':b' => $id, ':s' => $finished ? 'archived' : 'current', ':p' => $new]);

        db()->prepare(
            'INSERT INTO reading_history (user_id, book_id, chapter, page, progress, status)
             VALUES (:u, :b, :c, :p, :g, :s)'
        )->execute([':u' => $uid, ':b' => $id, ':c' => $chapter, ':p' => $new, ':g' => $progress,
                    ':s' => $finished ? 'finished' : 'in_progress']);

        if ($finished && $new !== $page) {
            flash('ok', 'You finished this book. It moved to Archives.');
        }
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        flash('fail', 'Could not save your progress. Please try again later.');
    }
    redirect('read.php?id=' . $id);
}

$chapter  = intdiv($page - 1, CHAPTER_SIZE) + 1;
$progress = (int) round($page / $total * 100);
$activeNav = 'library';
?>
<!DOCTYPE html>
<html lang="en" <?= theme_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reading: <?= e($book['title']) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app">
  <header class="app-header sub-header">
    <a href="book.php?id=<?= $id ?>" class="icon-btn back-btn" aria-label="Back to book">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    </a>
    <h2>READING</h2>
    <span class="header-spacer"></span>
  </header>

  <main class="app-main reader-main">
    <?= flash_render() ?>

    <h1 class="detail-title"><?= e($book['title']) ?></h1>
    <p class="detail-meta">Chapter <?= $chapter ?> · Page <?= $page ?> of <?= $total ?></p>

    <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progress ?>">
      <div class="progress-fill" style="width: <?= $progress ?>%;"></div>
    </div>
    <p class="hist-time"><?= $progress ?>% complete</p>

    <section class="page-sheet">
      <h3>Chapter <?= $chapter ?></h3>
      <p><?= nl2br(e($book['description'])) ?></p>
      <p class="hint">Page <?= $page ?>. The reader tracks your progress; the book's full text is not stored in this demo.</p>
    </section>

    <form method="post" action="read.php?id=<?= $id ?>" class="pager">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <button type="submit" name="action" value="prev" class="pill-btn"<?= $page <= 1 ? ' disabled' : '' ?>>&larr; Prev</button>
      <button type="submit" name="action" value="next" class="pill-btn"<?= $page >= $total ? ' disabled' : '' ?>>Next &rarr;</button>
    </form>

    <form method="post" action="read.php?id=<?= $id ?>" class="goto-form">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="action" value="goto">
      <label for="page">Jump to page</label>
      <div class="goto-row">
        <input type="text" inputmode="numeric" id="page" name="page" maxlength="4" value="<?= $page ?>">
        <button type="submit" class="pill-btn">Go</button>
      </div>
    </form>
  </main>

  <?php require __DIR__ . '/partials/bottom_nav.php'; ?>
</div>
</body>
</html>
