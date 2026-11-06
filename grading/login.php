<?php
// login.php
// Main: KM | RM: validation | Novie: yung auth-card na style
require 'includes/auth.php';
require 'config/db.php';

// IA: tara, esmeringhoy

// Already logged in? Skip the form and go straight to the dashboard.
if (!empty($_SESSION['role'])) {
  redirect($_SESSION['role'] . '/dashboard.php');
}

$error = '';
$typed = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  // RM: yung $typed para pag mali password, hindi mawawala yung username na tinype. sana all may ganyan na UX
  $typed = trim($_POST['username'] ?? '');

  // Prepared statement. Never put $_POST directly in the SQL string, that is how SQL injection happens.
  $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
  $stmt->execute([$typed]);
  $user = $stmt->fetch();

  // Same message for "no such user" and "wrong password" so nobody can guess usernames.
  // if ($_POST['password'] == $user['password']) {   // plain text compare, dati. NEVER AGAIN. (MC, ikaw yun)
  // MC: grabe ka naman KM, nag-aaral pa ako nun
  if (!$user || !password_verify($_POST['password'] ?? '', $user['password'])) {
    $error = 'Wrong username or password.';
  } else {
    // Regenerate the session ID after login to prevent session fixation.
    // IA: pag di nag-login sa laptop ko, clear cookies muna. ako lang ata nagka-ganun
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['name']    = $user['full_name'];
    // header("Location: " . $user['role'] . "/dashboard.php");   // RM: same lang to. mas madaling basahin pero ayaw ni KM
    header("Location: {$user['role']}/dashboard.php");
    exit;
  }
}

// Novie: $guest = true para yung login page walang topbar at red background lang yung body (tingnan body.guest sa style.css).
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
