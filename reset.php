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
$phone = '';
$step = empty($_SESSION['reset_user_id']) ? 'verify' : 'password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!csrf_valid()) {
        $errors['form'] = 'Your session expired. Please try again.';
    } elseif ($action === 'verify') {
        $studentId = trim((string) ($_POST['student_id'] ?? ''));
        $phone     = trim((string) ($_POST['phone'] ?? ''));

        if ($msg = validate_student_id($studentId)) $errors['student_id'] = $msg;
        if (!preg_match('/^\d{11}$/', $phone))      $errors['phone'] = 'Phone number must be exactly 11 digits.';

        if (!$errors) {
            try {
                $stmt = db()->prepare('SELECT id FROM users WHERE student_id = :sid AND phone = :phone LIMIT 1');
                $stmt->execute([':sid' => $studentId, ':phone' => $phone]);
                $user = $stmt->fetch();

                if ($user) {
                    session_regenerate_id(true);
                    $_SESSION['reset_user_id'] = (int) $user['id'];
                    header('Location: reset.php');
                    exit;
                }
                $errors['form'] = 'No account matches that Student ID and phone number.';
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                $errors['form'] = 'Something went wrong. Please try again later.';
            }
        }
    } elseif ($action === 'password') {
        if (empty($_SESSION['reset_user_id'])) {
            header('Location: reset.php');
            exit;
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['confirm'] ?? '');

        if ($msg = validate_password($password))          $errors['password'] = $msg;
        if ($msg = validate_confirm($confirm, $password)) $errors['confirm'] = $msg;

        if (!$errors) {
            try {
                $upd = db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id');
                $upd->execute([
                    ':h'  => password_hash($password, PASSWORD_DEFAULT),
                    ':id' => $_SESSION['reset_user_id'],
                ]);
                unset($_SESSION['reset_user_id']);
                header('Location: login.php?reset=1');
                exit;
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
<title>Reset Password</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="card">
  <?= brand() ?>

  <?php if (!empty($errors['form'])): ?>
    <div class="message fail"><?= e($errors['form']) ?></div>
  <?php endif; ?>

  <?php if ($step === 'verify'): ?>
  <form method="post" action="reset.php" data-validate novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="verify">

    <div class="field">
      <label for="student_id">Student ID</label>
      <input type="text" inputmode="numeric" maxlength="8" id="student_id" name="student_id" data-rule="student_id"
             value="<?= e($studentId) ?>" placeholder="Enter Student ID" autocomplete="username">
      <div class="error"><?= e($errors['student_id'] ?? '') ?></div>
    </div>

    <div class="field">
      <label for="phone">Phone Number</label>
      <input type="tel" inputmode="numeric" maxlength="11" id="phone" name="phone" data-rule="phone"
             value="<?= e($phone) ?>" placeholder="Enter registered phone number" autocomplete="tel">
      <div class="error"><?= e($errors['phone'] ?? '') ?></div>
    </div>

    <button class="submit" type="submit">Reset Password</button>
  </form>
  <?php else: ?>
  <form method="post" action="reset.php" data-validate novalidate>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="password">

    <div class="field">
      <label for="password">New Password</label>
      <input type="password" id="password" name="password" data-rule="password"
             placeholder="Enter new password" autocomplete="new-password">
      <div class="error"><?= e($errors['password'] ?? '') ?></div>
    </div>

    <div class="field">
      <label for="confirm">Confirm Password</label>
      <input type="password" id="confirm" name="confirm" data-rule="confirm"
             placeholder="Re-enter new password" autocomplete="new-password">
      <div class="error"><?= e($errors['confirm'] ?? '') ?></div>
    </div>

    <button class="submit" type="submit">Save Password</button>
  </form>
  <?php endif; ?>

  <div class="divider">OR</div>
  <div class="alt center">
    <a class="btn-outline" href="login.php">Return to Sign-in</a>
  </div>
</div>
<script src="assets/validate.js"></script>
</body>
</html>