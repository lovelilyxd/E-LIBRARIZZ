<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$uid = require_login();

$tabs = [
    'current' => 'Current Reads',
    'archives' => 'Archives',
    'saves' => 'Saves',
];
$statusFor = ['current' => 'current', 'archives' => 'archived', 'saves' => 'saved'];
$statusLabels = ['saved' => 'Saves', 'current' => 'Current Reads', 'archived' => 'Archives'];

$tab = (string) ($_GET['tab'] ?? 'saves');
if (!isset($tabs[$tab])) {
    $tab = 'saves';
}

// READ: this user's books in the chosen tab.
$items = [];
try {
    $stmt = db()->prepare(
        'SELECT li.status, li.current_page, b.*
           FROM library_items li
           JOIN books b ON b.id = li.book_id
          WHERE li.user_id = :u AND li.status = :s
          ORDER BY li.updated_at DESC'
    );
    $stmt->execute([':u' => $uid, ':s' => $statusFor[$tab]]);
    $items = $stmt->fetchAll();
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    flash('fail', 'Could not load your library. Did you run setup.php?');
}

$returnTo = 'library.php?tab=' . $tab;
$activeNav = 'library';
?>
<!DOCTYPE html>
<html lang="en" <?= theme_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Library</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app">
  <header class="app-header library-header">
    <h2>Library</h2>
    <div class="app-header-actions">
      <a href="search.php" class="icon-btn" aria-label="Find books to add">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
      </a>
      <details class="menu">
        <summary class="icon-btn" aria-label="More options">
          <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
        </summary>
        <div class="menu-panel">
          <p class="menu-user"><?= e((string) ($_SESSION['user_name'] ?? '')) ?></p>
          <a href="edit_profile.php" class="menu-item">Edit profile</a>
          <a href="theme.php" class="menu-item">Appearance</a>
          <form method="post" action="logout.php">
            <?= csrf_field() ?>
            <button type="submit" class="menu-item">Sign out</button>
          </form>
        </div>
      </details>
    </div>
  </header>

  <nav class="lib-tabs" aria-label="Library sections">
    <?php foreach ($tabs as $key => $label): ?>
      <a href="library.php?tab=<?= e($key) ?>" class="lib-tab<?= $key === $tab ? ' active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>

  <main class="app-main lib-main">
    <?= flash_render() ?>

    <?php if ($items): ?>
      <?php foreach ($items as $item): ?>
        <?php $bid = (int) $item['id']; ?>
        <article class="lib-item">
          <a href="book.php?id=<?= $bid ?>" class="lib-cover-link"><?= cover_html($item, 'lib-cover') ?></a>
          <div class="lib-body">
            <h3><a href="book.php?id=<?= $bid ?>"><?= e($item['title']) ?></a></h3>
            <p><?= e($item['description']) ?></p>

            <div class="lib-meta">
              <span class="page-badge">Page <?= (int) $item['current_page'] ?></span>
            </div>

            <div class="lib-actions">
              <?php if ($item['status'] === 'archived'): ?>
                <form method="post" action="read.php?id=<?= $bid ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= $bid ?>">
                  <input type="hidden" name="action" value="restart">
                  <button type="submit" class="pill-btn">Read Again</button>
                </form>
              <?php else: ?>
                <a href="read.php?id=<?= $bid ?>" class="pill-btn"><?= $item['status'] === 'current' ? 'Continue' : 'Read' ?></a>
              <?php endif; ?>

              <form method="post" action="library_action.php" class="move-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="move">
                <input type="hidden" name="book_id" value="<?= $bid ?>">
                <input type="hidden" name="return" value="<?= e($returnTo) ?>">
                <select name="status" aria-label="Move to" onchange="this.form.submit()">
                  <?php foreach ($statusLabels as $val => $label): ?>
                    <option value="<?= e($val) ?>"<?= $val === $item['status'] ? ' selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <noscript><button type="submit" class="pill-btn">Move</button></noscript>
              </form>

              <form method="post" action="library_action.php" onsubmit="return confirm('Remove this book from your library?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="book_id" value="<?= $bid ?>">
                <input type="hidden" name="return" value="<?= e($returnTo) ?>">
                <button type="submit" class="icon-btn small" aria-label="Remove from library">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                </button>
              </form>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="empty">No books in <?= e(strtolower($tabs[$tab])) ?> yet.<br><a href="search.php">Find a book to add</a>.</p>
    <?php endif; ?>
  </main>

  <?php require __DIR__ . '/partials/bottom_nav.php'; ?>
</div>
</body>
</html>
