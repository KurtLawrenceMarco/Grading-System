<?php
require '../includes/auth.php';
require '../config/db.php';
require_role('admin');

$count = fn($sql) => (int)$pdo->query($sql)->fetchColumn();
$stats = [
  'Faculty'     => $count("SELECT COUNT(*) FROM users WHERE role='faculty'"),
  'Students'    => $count("SELECT COUNT(*) FROM users WHERE role='student'"),
  'Courses'     => $count("SELECT COUNT(*) FROM courses"),
  'Subjects'    => $count("SELECT COUNT(*) FROM subjects"),
  'Enrollments' => $count("SELECT COUNT(*) FROM enrollments"),
];

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
