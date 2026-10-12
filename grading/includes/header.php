<?php
// Set $title and $base ('' for root pages, '../' for pages in a folder) before including.
$base  = $base  ?? '';
$title = $title ?? 'Grading System';
$guest = $guest ?? false;
$role  = $_SESSION['role'] ?? null;
$cur   = basename($_SERVER['SCRIPT_NAME']);

$links = [];
if ($role === 'admin') {
  $links = ['dashboard.php' => 'Overview', 'users.php' => 'Users', 'courses.php' => 'Courses',
            'subjects.php' => 'Subjects', 'enrollments.php' => 'Enrollments'];
} elseif ($role === 'faculty') {
  $links = ['dashboard.php' => 'My subjects'];
} elseif ($role === 'student') {
  $links = ['dashboard.php' => 'My grades'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Grading System</title>
<link rel="stylesheet" href="<?= $base ?>assets/style.css">
   <link rel="icon" href="<?= $base ?>assets/icon.png">
</head>
<body class="<?= $guest ? 'guest' : '' ?>">
<?php if (!$guest): ?>
<header class="topbar">
  <div class="topbar-inner">
    <span class="brand">Grading System</span>
    <nav>
      <?php foreach ($links as $href => $label): ?>
        <a href="<?= $href ?>" class="<?= $cur === $href ? 'active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="who">
      <span><?= e($_SESSION['name'] ?? '') ?> <small><?= e($role) ?></small></span>
      <a class="logout" href="<?= $base ?>logout.php">Log out</a>
    </div>
  </div>
</header>
<?php endif; ?>
<main class="<?= $guest ? 'auth-main' : 'page' ?>">
<?php
if (!empty($_SESSION['flash'])) {
  $f = $_SESSION['flash']; unset($_SESSION['flash']);
  echo '<div class="flash ' . e($f['type']) . '" role="status">' . e($f['msg']) . '</div>';
}
