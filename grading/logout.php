<?php
// logout.php
// KM: buburahin natin lahat. session data, cookie, session mismo. Para hindi na ma-reuse pag nag-back button.
session_start();
// RM: bakit hindi na lang session_destroy()? KM: kasi hindi nito nabubura yung variables sa kasalukuyang request.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
// echo 'logged out';   // AP: test lang to sa PC ko. di ko na tinanggal para may proof
session_destroy();
header('Location: login.php');
exit;
