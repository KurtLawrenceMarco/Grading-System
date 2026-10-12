<?php
require '../includes/auth.php';
require '../config/db.php';
require_role('faculty');

// One row per course + section + subject that this professor teaches.
$q = $pdo->prepare(
  "SELECT c.id AS course_id, c.code AS course_code, c.name AS course_name, u.section,
          s.id AS subject_id, s.code, s.name, COUNT(*) AS students
   FROM enrollments e
   JOIN subjects s ON s.id = e.subject_id
   JOIN users u    ON u.id = e.student_id
   LEFT JOIN courses c ON c.id = u.course_id
   WHERE s.faculty_id = ?
   GROUP BY c.id, c.code, c.name, u.section, s.id, s.code, s.name
   ORDER BY c.code IS NULL, c.code, u.section IS NULL, u.section, s.code"
);
$q->execute([$_SESSION['user_id']]);

// Build the folder tree: course > section > subjects.
$tree = [];
foreach ($q->fetchAll() as $r) {
  $ck = (int)($r['course_id'] ?? 0);
  $sk = (string)($r['section'] ?? '');
  $tree[$ck]['code'] = $r['course_code'] ?? 'No course assigned';
  $tree[$ck]['name'] = $r['course_name'] ?? '';
  $tree[$ck]['sections'][$sk][] = $r;
}

// Subjects of this professor that nobody is enrolled in yet.
$empty = $pdo->prepare(
  "SELECT code, name FROM subjects s
   WHERE s.faculty_id = ? AND NOT EXISTS (SELECT 1 FROM enrollments e WHERE e.subject_id = s.id)
   ORDER BY code"
);
$empty->execute([$_SESSION['user_id']]);
$empty = $empty->fetchAll();

$title = 'Faculty';
$base  = '../';
require '../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>Faculty dashboard</h1>
    <p class="muted">Welcome, <?= e($_SESSION['name']) ?>. Open a course, then a section, then pick a subject to grade.</p>
  </div>
</div>

<?php if (!$tree && !$empty): ?>
  <div class="panel muted">No subjects are assigned to you yet. Ask an admin to assign one.</div>
<?php endif; ?>

<?php $first = true; foreach ($tree as $cid => $course): ?>
  <details class="folder" <?= $first ? 'open' : '' ?>>
    <summary>
      <span>📁 <?= e($course['code']) ?></span>
      <small><?= e($course['name']) ?></small>
    </summary>
    <div class="folder-body">
      <?php foreach ($course['sections'] as $sec => $subjects): ?>
        <details class="folder sub" <?= $first ? 'open' : '' ?>>
          <summary><span>📁 <?= $sec === '' ? 'No section' : 'Section ' . e($sec) ?></span>
            <small><?= count($subjects) ?> subject<?= count($subjects) == 1 ? '' : 's' ?></small>
          </summary>
          <div class="folder-body">
            <?php foreach ($subjects as $s):
              $href = 'grade.php?' . http_build_query([
                'subject' => $s['subject_id'],
                'course'  => $cid ?: '',
                'section' => $sec,
              ]); ?>
              <a class="subject-row" href="<?= e($href) ?>">
                <span><b><?= e($s['code']) ?></b> · <?= e($s['name']) ?></span>
                <small><?= (int)$s['students'] ?> student<?= $s['students'] == 1 ? '' : 's' ?></small>
              </a>
            <?php endforeach; ?>
          </div>
        </details>
      <?php endforeach; ?>
    </div>
  </details>
<?php $first = false; endforeach; ?>

<?php if ($empty): ?>
  <details class="folder">
    <summary><span>📁 Subjects with no students yet</span></summary>
    <div class="folder-body">
      <?php foreach ($empty as $s): ?>
        <div class="subject-row"><span><b><?= e($s['code']) ?></b> · <?= e($s['name']) ?></span><small>Ask an admin to enroll students</small></div>
      <?php endforeach; ?>
    </div>
  </details>
<?php endif; ?>
<?php require '../includes/footer.php'; ?>
