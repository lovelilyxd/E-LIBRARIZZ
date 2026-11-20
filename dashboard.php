<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$uid = require_login();

// READ: the signed-in user's profile and library counts.
$stmt = db()->prepare('SELECT full_name, student_id, username FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $uid]);
$user = $stmt->fetch();
if (!$user) {
    $_SESSION = [];
    redirect('login.php');
}

$counts = ['saved' => 0, 'current' => 0, 'archived' => 0];
$stmt = db()->prepare('SELECT status, COUNT(*) AS n FROM library_items WHERE user_id = :u GROUP BY status');
$stmt->execute([':u' => $uid]);
foreach ($stmt->fetchAll() as $row) {
    $counts[$row['status']] = (int) $row['n'];
}

$activeNav = 'menu';
?>
<!DOCTYPE html>
<html lang="en" <?= theme_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Menu</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app">
  <header class="app-header">
    <h2>Menu</h2>
  </header>

  <main class="app-main profile-main">
    <?= flash_render() ?>

    <div class="avatar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.5-7 8-7s8 3 8 7"/></svg>
    </div>
    <h2 class="center"><?= e($user['full_name']) ?></h2>
    <p class="center muted">ID <?= e($user['student_id']) ?><?= $user['username'] ? ' · @' . e($user['username']) : '' ?></p>

    <div class="stat-row">
      <a href="library.php?tab=current"><strong><?= $counts['current'] ?></strong><span>Current</span></a>
      <a href="library.php?tab=saves"><strong><?= $counts['saved'] ?></strong><span>Saved</span></a>
      <a href="library.php?tab=archives"><strong><?= $counts['archived'] ?></strong><span>Archived</span></a>
    </div>

    <a href="edit_profile.php" class="btn-outline wide menu-link">EDIT PROFILE</a>
    <a href="theme.php" class="btn-outline wide menu-link">APPEARANCE</a>
    <a href="book_form.php" class="btn-outline wide menu-link">ADD A BOOK</a>

    <form method="post" action="logout.php">
      <?= csrf_field() ?>
      <button class="submit" type="submit">SIGN OUT</button>
    </form>
  </main>

  <?php require __DIR__ . '/partials/bottom_nav.php'; ?>
</div>
</body>
</html>
