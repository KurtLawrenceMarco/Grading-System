<?php
// credits.php  -  the people who made this site
// Novie: ako nag-layout nito. KM, RM, wag niyo galawin yung order, may pinagbasehan yan.
// KM: bakit ako una? Novie: Lead Programmer ka diba. KM: ah. sige. okay.
// MC: bakit ako pang-apat. RM: kasi ikaw yung Extra. MC: Debugger din ako. RM: extra ka pa rin.
// IA, AP: ayan, may pangalan na kayo sa site. wag na kayong magreklamo.
require 'includes/auth.php';

// Public page: guests (not logged in) can see it too, so there is no require_role() here.
$loggedIn = !empty($_SESSION['role']);

// To add or change a credit, edit this list. Name first, then the role.
$credits = [
  ['Kurt Lawrence Marco',   'Lead Programmer'],
  ['Raffy Andrei Mora',     'Assistant Programmer'],
  ['Novie Castillo',        'Layout Planner'],
  ['Melson Anthony Come',   'Extra Programmer (Debugger)'],
  ['Ian Gabriel Abad',      'Tester'],
  ['Ace Christian Placido', 'Tester'],
];

$guest = !$loggedIn;     // guests get the login-style page, logged-in users get the normal top bar
$title = 'Credits';
$base  = '';
require 'includes/header.php';
?>
<?php if ($loggedIn): ?>
<div class="page-head">
  <div>
    <h1>Credits</h1>
    <p class="muted">The people who built the Grading System.</p>
  </div>
</div>
<div class="panel">
  <div class="table-wrap">
  <table>
    <thead><tr><th>Name</th><th>Role</th></tr></thead>
    <tbody>
    <?php foreach ($credits as [$name, $role]): ?>
      <tr><td><b><?= e($name) ?></b></td><td><?= e($role) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<p><a href="<?= e($_SESSION['role']) ?>/dashboard.php">&larr; Back to my dashboard</a></p>
<?php else: ?>
<div class="auth-card">
  <h1>Credits</h1>
  <p class="muted">The people who built the Grading System.</p>
  <?php foreach ($credits as [$name, $role]): ?>
    <div class="field"><b><?= e($name) ?></b><small><?= e($role) ?></small></div>
  <?php endforeach; ?>
  <p style="margin:20px 0 0"><a href="login.php">&larr; Back to log in</a></p>
</div>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
