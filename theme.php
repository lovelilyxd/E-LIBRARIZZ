<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$cookieOptions = [
    'expires' => time() + 60 * 60 * 24 * 365,
    'path' => '/',
    'httponly' => false,
    'samesite' => 'Lax',
];

$theme = (string) ($_COOKIE['theme'] ?? 'dark');
if (!in_array($theme, ['light', 'dark'], true)) {
    $theme = 'dark';
}
$options = [
    'pure_black' => ($_COOKIE['pure_black'] ?? '') === '1',
    'time_adaptive' => ($_COOKIE['time_adaptive'] ?? '') === '1',
    'light_adaptive' => ($_COOKIE['light_adaptive'] ?? '') === '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    $theme = (string) ($_POST['theme'] ?? 'dark');
    if (!in_array($theme, ['light', 'dark'], true)) {
        $theme = 'dark';
    }
    $options = [
        'pure_black' => isset($_POST['pure_black']),
        'time_adaptive' => isset($_POST['time_adaptive']),
        'light_adaptive' => isset($_POST['light_adaptive']),
    ];

    setcookie('theme', $theme, $cookieOptions);
    foreach ($options as $key => $on) {
        setcookie($key, $on ? '1' : '0', $cookieOptions);
    }

    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" <?= theme_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Appearance</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app">
  <header class="app-header sub-header">
    <a href="dashboard.php" class="icon-btn back-btn" aria-label="Back">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    </a>
    <h2>APPEARANCE</h2>
    <span class="header-spacer"></span>
  </header>
  <main class="app-main theme-main">
    <form method="post" action="theme.php" id="theme-form">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="theme-choices">
        <label class="theme-option">
          <input type="radio" name="theme" value="light" <?= $theme === 'light' ? 'checked' : '' ?>>
          <span class="theme-card light">
            <span class="pill"></span>
            <span class="block"></span>
            <span class="bar"></span>
          </span>
          <span class="theme-name">Light Mode</span>
          <span class="dot" aria-hidden="true"></span>
        </label>

        <label class="theme-option">
          <input type="radio" name="theme" value="dark" <?= $theme === 'dark' ? 'checked' : '' ?>>
          <span class="theme-card dark">
            <span class="pill"></span>
            <span class="block"></span>
            <span class="bar"></span>
          </span>
          <span class="theme-name">Dark Mode</span>
          <span class="dot" aria-hidden="true"></span>
        </label>
      </div>

      <div class="theme-toggles">
        <label class="switch-row">
          <span>Pure black Dark Mode</span>
          <input type="checkbox" name="pure_black" <?= $options['pure_black'] ? 'checked' : '' ?>>
          <span class="switch" aria-hidden="true"></span>
        </label>
        <label class="switch-row">
          <span>Time Adaptive</span>
          <input type="checkbox" name="time_adaptive" <?= $options['time_adaptive'] ? 'checked' : '' ?>>
          <span class="switch" aria-hidden="true"></span>
        </label>
        <label class="switch-row">
          <span>Light Adaptive</span>
          <input type="checkbox" name="light_adaptive" <?= $options['light_adaptive'] ? 'checked' : '' ?>>
          <span class="switch" aria-hidden="true"></span>
        </label>
      </div>

      <button class="submit proceed" type="submit">PROCEED</button>
    </form>
  </main>
</div>
</body>
</html>