<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/validation.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . HOME_PAGE);
    exit;
}

$errors = [];
$studentId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        $studentId = trim((string) ($_POST['student_id'] ?? ''));
        $password  = (string) ($_POST['password'] ?? '');

        if ($msg = validate_student_id($studentId)) $errors['student_id'] = $msg;
        if ($password === '')                       $errors['password'] = 'Password is required.';

        if (!$errors) {
            try {
                $stmt = db()->prepare('SELECT id, full_name, password_hash FROM users WHERE student_id = :sid LIMIT 1');
                $stmt->execute([':sid' => $studentId]);
                $user = $stmt->fetch();

      
                $hash = $user['password_hash'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
                $ok   = password_verify($password, $hash) && $user;

                if ($ok) {
                    session_regenerate_id(true);         
                    $_SESSION['user_id']   = (int) $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];

                    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                        $upd = db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
                        $upd->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $user['id']]);
                    }
                    header('Location: ' . HOME_PAGE);
                    exit;
                }
                $errors['form'] = 'Incorrect Student ID or password.';
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                $errors['form'] = 'Something went wrong. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" <?= theme_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign In</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="card">
  <?= brand() ?>

  <?php if (isset($_GET['registered'])): ?>
    <div class="message success">Account created! You can now sign in.</div>
  <?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?>
    <div class="message success">Your account was deleted.</div>
  <?php endif; ?>
  <?php if (isset($_GET['reset'])): ?>
    <div class="message success">Password updated! You can now sign in.</div>
  <?php endif; ?>
  <?php if (!empty($errors['form'])): ?>
    <div class="message fail"><?= e($errors['form']) ?></div>
  <?php endif; ?>

  <form method="post" action="login.php" data-validate novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="field">
      <label for="student_id">Student ID</label>
      <input type="text" inputmode="numeric" maxlength="8" id="student_id" name="student_id" data-rule="student_id"
             value="<?= e($studentId) ?>" placeholder="Enter Student ID" autocomplete="username">
      <div class="error"><?= e($errors['student_id'] ?? '') ?></div>
    </div>

    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" data-rule="required"
             placeholder="Enter your password" autocomplete="current-password">
      <div class="error"><?= e($errors['password'] ?? '') ?></div>
    </div>

    <button class="submit" type="submit">LOG IN</button>
  </form>

  <div class="divider">OR</div>
  <div class="alt">
    <a class="btn-outline" href="reset.php">Forgot Password</a>
    <a class="btn-outline" href="signup.php">Create an Account</a>
  </div>
</div>
<script src="assets/validate.js"></script>
</body>
</html>