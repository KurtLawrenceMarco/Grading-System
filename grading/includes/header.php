<?php
// includes/header.php  -  top bar + menu + flash message
// Layout/colors: Novie | PHP bits: KM, RM
// Novie: wag niyong galawin yung classes ng topbar, kahit isang letra. may alignment akong inayos ng 2 oras diyan.
// KM: noted madam
// Set $title and $base ('' for root pages, '../' for pages in a folder) before including.
$base  = $base  ?? '';
$title = $title ?? 'Grading System';
$guest = $guest ?? false;
$role  = $_SESSION['role'] ?? null;
$cur   = basename($_SERVER['SCRIPT_NAME']);

// RM: menu per role. admin marami, faculty at student isa lang (tapos yung Announcements sa baba).
$links = [];
// $links['reports.php'] = 'Reports';   // MC: planned feature, wala pa. natulog ako eh
if ($role === 'admin') {
  $links = ['dashboard.php' => 'Overview', 'users.php' => 'Users', 'courses.php' => 'Courses',
            'subjects.php' => 'Subjects', 'terms.php' => 'Semesters', 'enrollments.php' => 'Enrollments', 'announcements.php' => 'Announcements'];
} elseif ($role === 'faculty') {
  $links = ['dashboard.php' => 'My subjects'];
} elseif ($role === 'student') {
  $links = ['dashboard.php' => 'My grades'];
}

// RM: yung bilang sa tabi ng Announcements. kung wala pang nabasa, may (3) o kung ilan.
// Announcements tab for faculty and students, with the number they have not opened yet.
if ($role === 'faculty' || $role === 'student') {
  // RM: try/catch kasi pag wala pang announcements table (di pa nag-run ng sql si AP), mag-white screen buong site.
  // AP: nangyari nga sa akin. wala akong makitang error, puro puti. 20 minutes ako dun.
  $unread = 0;
  try {
    if (isset($pdo)) {
      require_once __DIR__ . '/announce_lib.php';
      $unread = unread_announcements($pdo, $role, (int)($_SESSION['user_id'] ?? 0));
    }
  } catch (Throwable $e) { $unread = 0; }
  // $links['my_announcements.php'] = 'Announcements';   // RM: ito yung luma, walang bilang pa
  $links['my_announcements.php'] = 'Announcements' . ($unread > 0 ? ' (' . $unread . ')' : '');
}

// colors.css holds every color. The ?v= part makes the browser reload it whenever the file is saved.
// Novie: yung ?v=filemtime sa baba para pag nag-save ako ng colors.css, auto reload agad sa browser. di na ako mag-Ctrl+F5 ng sampung beses.
$colorsFile = __DIR__ . '/../assets/colors.css';
?>
<?php // Novie: simula ng HTML. sakin na to mula dito hanggang sa dulo ng topbar. ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Grading System</title>
<link rel="stylesheet" href="<?= $base ?>assets/colors.css?v=<?= @filemtime($colorsFile) ?>">
<link rel="stylesheet" href="<?= $base ?>assets/style.css?v=<?= @filemtime(__DIR__ . '/../assets/style.css') ?>">
   <link rel="icon" href="<?= $base ?>assets/icon.png">
</head>
<body class="<?= $guest ? 'guest' : '' ?>">
<?php if (!$guest): ?>
<?php // Novie: yung topbar. brand sa kaliwa, menu sa gitna, user at logout sa kanan. wag niyong lagyan ng inline style please ?>
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
<?php // Novie: dito papasok yung laman ng bawat page. nagsasara sa footer.php ?>
<main class="<?= $guest ? 'auth-main' : 'page' ?>">
<?php
// KM: flash message: ipapakita tapos buburahin agad sa session (unset), kaya isang beses lang lalabas.
if (!empty($_SESSION['flash'])) {
  $f = $_SESSION['flash']; unset($_SESSION['flash']);
  echo '<div class="flash ' . e($f['type']) . '" role="status">' . e($f['msg']) . '</div>';
}
