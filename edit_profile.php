<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/validation.php';

$uid = require_login();

$errors = [];
$deleteError = '';
$success = isset($_GET['saved']);

$stmt = db()->prepare('SELECT full_name, student_id, username, phone, password_hash FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $uid]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION = [];
    redirect('login.php');
}

$form = [
    'name'     => $user['full_name'],
    'username' => (string) ($user['username'] ?? ''),
    'phone'    => (string) ($user['phone'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'update');

    if (!csrf_valid()) {
        $errors['form'] = 'Your session expired. Please try again.';

    } elseif ($action === 'delete_account') {
        // DELETE: permanently remove the account (library and history cascade).
        $pw = (string) ($_POST['delete_password'] ?? '');
        if ($pw === '' || !password_verify($pw, $user['password_hash'])) {
            $deleteError = 'Enter your current password to delete your account.';
        } else {
            try {
                db()->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $uid]);
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $p = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
                }
                session_destroy();
                redirect('login.php?deleted=1');
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                $deleteError = 'Something went wrong. Please try again later.';
            }
        }

    } else {
        // UPDATE: name, username, phone and (optionally) password.
        $form['name']     = trim((string) ($_POST['name'] ?? ''));
        $form['username'] = trim((string) ($_POST['username'] ?? ''));
        $form['phone']    = trim((string) ($_POST['phone'] ?? ''));
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if ($msg = validate_name($form['name']))         $errors['name'] = $msg;
        if ($msg = validate_username($form['username'])) $errors['username'] = $msg;
        if ($msg = validate_phone($form['phone']))       $errors['phone'] = $msg;

        $changePassword = $current !== '' || $new !== '' || $confirm !== '';
        if ($changePassword) {
            if ($current === '') {
                $errors['current_password'] = 'Enter your current password.';
            } elseif (!password_verify($current, $user['password_hash'])) {
                $errors['current_password'] = 'Current password is incorrect.';
            }
            if ($msg = validate_password($new))          $errors['new_password'] = $msg;
            if ($msg = validate_confirm($confirm, $new)) $errors['confirm_password'] = $msg;
        }

        if (!$errors) {
            try {
                $sql = 'UPDATE users SET full_name = :name, username = :username, phone = :phone'
                     . ($changePassword ? ', password_hash = :hash' : '') . ' WHERE id = :id';
                $args = [
                    ':name'     => $form['name'],
                    ':username' => $form['username'],
                    ':phone'    => $form['phone'],
                    ':id'       => $uid,
                ];
                if ($changePassword) {
                    $args[':hash'] = password_hash($new, PASSWORD_DEFAULT);
                }
                db()->prepare($sql)->execute($args);
                $_SESSION['user_name'] = $form['name'];
                redirect('edit_profile.php?saved=1');
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    $errors['username'] = 'That username is already taken.';
                } else {
                    error_log($ex->getMessage());
                    $errors['form'] = 'Something went wrong. Please try again later.';
                }
            }
        }
    }
}

$activeNav = 'menu';
?>
<!DOCTYPE html>
<html lang="en" <?= theme_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Profile</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app">
  <header class="app-header sub-header">
    <a href="dashboard.php" class="icon-btn back-btn" aria-label="Back">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    </a>
    <h2>EDIT PROFILE</h2>
    <span class="header-spacer"></span>
  </header>

  <main class="app-main profile-main">
    <div class="avatar">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="8" r="4"/><path d="M4 21c0-4 3-7 7-7s7 3 7 7"/><line x1="19" y1="5" x2="19" y2="11"/><line x1="16" y1="8" x2="22" y2="8"/></svg>
    </div>

    <?php if ($success): ?>
      <div class="message success">Profile updated.</div>
    <?php endif; ?>
    <?php if (!empty($errors['form'])): ?>
      <div class="message fail"><?= e($errors['form']) ?></div>
    <?php endif; ?>

    <form method="post" action="edit_profile.php" data-validate novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">

      <h3 class="section-title">PERSONAL INFORMATION</h3>

      <div class="field">
        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" data-rule="name" value="<?= e($form['name']) ?>" placeholder="Enter full name">
        <div class="error"><?= e($errors['name'] ?? '') ?></div>
      </div>

      <div class="field">
        <label for="student_id">Student ID</label>
        <input type="text" id="student_id" value="<?= e($user['student_id']) ?>" disabled>
      </div>

      <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" data-rule="username" maxlength="15" value="<?= e($form['username']) ?>" placeholder="Enter username">
        <div class="error"><?= e($errors['username'] ?? '') ?></div>
      </div>

      <div class="field">
        <label for="phone">Phone Number</label>
        <input type="tel" inputmode="numeric" id="phone" name="phone" data-rule="phone" maxlength="11" value="<?= e($form['phone']) ?>" placeholder="Enter phone number">
        <div class="error"><?= e($errors['phone'] ?? '') ?></div>
      </div>

      <h3 class="section-title">CHANGE PASSWORD</h3>

      <div class="field">
        <label for="current_password">Current Password</label>
        <input type="password" id="current_password" name="current_password" placeholder="Enter current password" autocomplete="current-password">
        <div class="error"><?= e($errors['current_password'] ?? '') ?></div>
      </div>

      <div class="field">
        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" placeholder="Enter new password" autocomplete="new-password">
        <div class="error"><?= e($errors['new_password'] ?? '') ?></div>
      </div>

      <div class="field">
        <label for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" autocomplete="new-password">
        <div class="error"><?= e($errors['confirm_password'] ?? '') ?></div>
      </div>

      <button class="submit" type="submit">SAVE CHANGES</button>
    </form>

    <a href="dashboard.php" class="btn-outline wide">CANCEL</a>

    <form method="post" action="edit_profile.php" class="danger-zone" onsubmit="return confirm('Delete your account permanently? Your library and history will be erased.');">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_account">
      <h3 class="section-title">DELETE ACCOUNT</h3>
      <div class="field">
        <label for="delete_password">Current Password</label>
        <input type="password" id="delete_password" name="delete_password" placeholder="Confirm with your password" autocomplete="current-password">
        <div class="error"><?= e($deleteError) ?></div>
      </div>
      <button type="submit" class="btn-outline wide btn-danger">DELETE MY ACCOUNT</button>
    </form>
  </main>
</div>
<script src="assets/validate.js"></script>
</body>
</html>
