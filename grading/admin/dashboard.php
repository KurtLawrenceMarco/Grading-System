<?php
// admin/dashboard.php
// RM: ako gumawa nito. stats lang at steps. wala masyadong logic, wag niyo ako i-judge
require '../includes/auth.php';
require '../config/db.php';
require_role('admin');

// KM: arrow function para di paulit-ulit na query()->fetchColumn(). Pag COUNT(*) lang, ito na.
$count = fn($sql) => (int)$pdo->query($sql)->fetchColumn();
$stats = [
  'Faculty'     => $count("SELECT COUNT(*) FROM users WHERE role='faculty'"),
  'Students'    => $count("SELECT COUNT(*) FROM users WHERE role='student'"),
  'Courses'     => $count("SELECT COUNT(*) FROM courses"),
  'Subjects'    => $count("SELECT COUNT(*) FROM subjects"),
  // 'Announcements' => $count("SELECT COUNT(*) FROM announcements"),   // RM: idadagdag ko sana. error sa PC ni AP kasi wala pang table
  'Enrollments' => $count("SELECT COUNT(*) FROM enrollments"),
];

// Novie: $title ang lumalabas sa tab ng browser. Sinunod ko yung format 'Page · Grading System'.
$title = 'Admin';
$base  = '../';
require '../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>Admin dashboard</h1>
    <p class="muted">Welcome, <?= e($_SESSION['name']) ?></p>
  </div>
</div>

<div class="stats">
  <?php foreach ($stats as $label => $n): ?>
    <div class="stat"><b><?= $n ?></b><span><?= e($label) ?></span></div>
  <?php endforeach; ?>
</div>

<div class="panel">
  <h2>Set up the term</h2>
  <p>Work through these in order the first time:</p>
  <ol>
    <li><a href="courses.php">Add courses</a> (for example BSIT or BSEd).</li>
    <li><a href="users.php">Add users</a>: faculty, and students with their course and section. Students get an ID automatically.</li>
    <li><a href="subjects.php">Add subjects</a> and pick the professor who teaches each one.</li>
    <li><a href="enrollments.php">Enroll students</a> in subjects, one by one or a whole section at once.</li>
  </ol>
</div>
<?php require '../includes/footer.php'; ?>
