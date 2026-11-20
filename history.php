<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$uid = require_login();

$filters = [
    'all' => 'All',
    'in_progress' => 'In progress',
    'finished' => 'Finished',
];
$filter = (string) ($_GET['filter'] ?? $_POST['filter'] ?? 'all');
if (!isset($filters[$filter])) {
    $filter = 'all';
}

// DELETE: remove one history entry, or clear everything.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    post_guard('history.php?filter=' . $filter);
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'delete') {
            $stmt = db()->prepare('DELETE FROM reading_history WHERE id = :id AND user_id = :u');
            $stmt->execute([':id' => (int) ($_POST['id'] ?? 0), ':u' => $uid]);
            flash('ok', 'History entry removed.');
        } elseif ($action === 'clear') {
            db()->prepare('DELETE FROM reading_history WHERE user_id = :u')->execute([':u' => $uid]);
            flash('ok', 'Reading history cleared.');
        }
    } catch (PDOException $ex) {
        error_log($ex->getMessage());
        flash('fail', 'Something went wrong. Please try again later.');
    }
    redirect('history.php?filter=' . $filter);
}

// READ: this user's reading history, newest first.
$sql = 'SELECT h.id, h.book_id, h.chapter, h.page, h.progress, h.status, h.read_at,
               b.title, b.label, b.color, b.cover_image
          FROM reading_history h
          JOIN books b ON b.id = h.book_id
         WHERE h.user_id = :u';
$args = [':u' => $uid];
if ($filter !== 'all') {
    $sql .= ' AND h.status = :s';
    $args[':s'] = $filter;
}
$sql .= ' ORDER BY h.read_at DESC, h.id DESC';

$items = [];
try {
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    $items = $stmt->fetchAll();
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    flash('fail', 'Could not load your history. Did you run setup.php?');
}

$activeNav = 'history';
?>
<!DOCTYPE html>
<html lang="en" <?= theme_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>History</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app">
  <header class="app-header">
    <h2>History</h2>
  </header>

  <div class="filter-row">
    <nav class="filters" aria-label="Filter history">
      <?php foreach ($filters as $key => $label): ?>
        <a href="history.php?filter=<?= e($key) ?>" class="filter<?= $key === $filter ? ' active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php if ($items): ?>
      <form method="post" action="history.php" onsubmit="return confirm('Clear your entire reading history?');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="clear">
        <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <button type="submit" class="clear-btn">Clear all</button>
      </form>
    <?php endif; ?>
  </div>

  <main class="app-main history-main">
    <?= flash_render() ?>

    <?php if ($items): ?>
      <?php foreach ($items as $h): ?>
        <article class="hist-item">
          <?= cover_html($h, 'hist-cover') ?>
          <div class="hist-body">
            <h3><?= e($h['title']) ?></h3>
            <p class="hist-meta">Chapter <?= (int) $h['chapter'] ?> · Page <?= (int) $h['page'] ?></p>
            <p class="hist-time"><?= e(format_time((string) $h['read_at'])) ?><?= $h['status'] === 'finished' ? ' · Finished' : '' ?></p>
            <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $h['progress'] ?>">
              <div class="progress-fill" style="width: <?= (int) $h['progress'] ?>%;"></div>
            </div>
          </div>
          <div class="hist-side">
            <a href="read.php?id=<?= (int) $h['book_id'] ?>" class="play-btn" aria-label="Continue reading">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M10 8.5v7l5.5-3.5z" fill="currentColor"/></svg>
            </a>
            <form method="post" action="history.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
              <input type="hidden" name="filter" value="<?= e($filter) ?>">
              <button type="submit" class="icon-btn small" aria-label="Remove this entry">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/></svg>
              </button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="empty">No <?= e(strtolower($filters[$filter])) ?> books yet.</p>
    <?php endif; ?>
  </main>

  <?php require __DIR__ . '/partials/bottom_nav.php'; ?>
</div>
</body>
</html>
