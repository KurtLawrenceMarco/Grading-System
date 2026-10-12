<?php
require 'includes/auth.php';
require 'config/db.php';

if (!empty($_SESSION['role'])) {
  redirect($_SESSION['role'] . '/dashboard.php');
}

$error = '';
$typed = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $typed = trim($_POST['username'] ?? '');

  $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
  $stmt->execute([$typed]);
  $user = $stmt->fetch();

  // Same message for "no such user" and "wrong password" so nobody can guess usernames.
  if (!$user || !password_verify($_POST['password'] ?? '', $user['password'])) {
    $error = 'Wrong username or password.';
  } else {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['name']    = $user['full_name'];
    header("Location: {$user['role']}/dashboard.php");
    exit;
  }
}

$guest = true;
$title = 'Log in';
require 'includes/header.php';
?>
<div class="auth-card">
  <h1>Grading System</h1>
  <p class="muted">Log in with the account your school gave you.</p>

  <?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>

  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <div class="field">
      <label for="username">Username</label>
      <input id="username" name="username" autocomplete="off" value="<?= e($typed) ?>" required autofocus>
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input id="password" type="password" name="password" autocomplete="new-password" required>
    </div>
    <button>Log in</button>
  </form>
</div>
<?php require 'includes/footer.php'; ?>
