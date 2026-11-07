<?php
require '../includes/auth.php';
require '../config/db.php';
require_role('student');

$me = $pdo->prepare("SELECT u.student_id, u.full_name, u.year_level, u.section, c.code AS course_code, c.name AS course_name
                     FROM users u LEFT JOIN courses c ON c.id = u.course_id WHERE u.id = ?");
$me->execute([$_SESSION['user_id']]);
$me = $me->fetch();

$q = $pdo->prepare(
  "SELECT s.code, s.name, f.full_name AS professor,
          e.semester, e.school_year, e.prelim, e.midterm, e.semifinal, e.finals, e.enrolled_at, e.updated_at
   FROM enrollments e
   JOIN subjects s   ON s.id = e.subject_id
   LEFT JOIN users f ON f.id = e.faculty_id
   WHERE e.student_id = ? ORDER BY e.school_year, e.semester, s.code"
);
$q->execute([$_SESSION['user_id']]);
$rows = $q->fetchAll();

// Group the subjects by semester + school year (oldest first, thanks to the ORDER BY above).
$terms = [];
foreach ($rows as $r) {
  $k = $r['school_year'] . '-' . $r['semester'];
  $terms[$k]['label']  = term_label($r['semester'], $r['school_year']);
  $terms[$k]['rows'][] = $r;
}

$passed = $failed = $pending = 0;
foreach ($rows as $r) {
  [, $st] = grade_summary($r);
  if ($st === 'Passed') $passed++; elseif ($st === 'Failed') $failed++; else $pending++;
}

$title = 'My grades';
$base  = '../';
require '../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>My grades</h1>
    <p class="muted">1.00 is the highest grade. You pass a subject with an average of <?= number_format(PASSING_GRADE, 2) ?> or better.</p>
  </div>
</div>

<div class="panel summary">
  <div><b><?= e($me['full_name']) ?></b><span>Name</span></div>
  <div><b><?= e($me['student_id']) ?></b><span>Student ID</span></div>
  <div><b><?= e($me['course_code'] ?? '—') ?></b><span>Course</span></div>
  <div><b><?= e($me['year_level'] ?? '—') ?></b><span>Year level</span></div>
  <div><b><?= e($me['section'] ?? '—') ?></b><span>Section</span></div>
  <div><b><?= $passed ?></b><span>Passed</span></div>
  <div><b><?= $failed ?></b><span>Failed</span></div>
  <div><b><?= $pending ?></b><span>Incomplete</span></div>
</div>

<?php if (!$rows): ?>
  <div class="panel"><p class="muted" style="text-align:center;margin:10px 0">You are not enrolled in any subjects yet.</p></div>
<?php endif; ?>

<?php foreach ($terms as $term): ?>
<div class="panel">
  <h2><?= e($term['label']) ?></h2>
  <p class="muted" style="margin:0 0 12px"><?= count($term['rows']) ?> subject<?= count($term['rows']) == 1 ? '' : 's' ?></p>
  <div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Code</th><th>Subject</th><th>Professor</th>
        <?php foreach (TERMS as $label): ?><th class="num"><?= e($label) ?></th><?php endforeach; ?>
        <th class="num">Average</th><th>Status</th><th>Enrolled</th><th>Last updated</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($term['rows'] as $r): [$avg, $status] = grade_summary($r); ?>
      <tr>
        <td><b><?= e($r['code']) ?></b></td>
        <td><?= e($r['name']) ?></td>
        <td><?= e($r['professor'] ?? '—') ?></td>
        <?php foreach (array_keys(TERMS) as $t): ?>
          <td class="num"><?= fmt_grade($r[$t]) ?></td>
        <?php endforeach; ?>
        <td class="num avg"><?= $avg === null ? '—' : number_format($avg, 2) ?></td>
        <td><?= status_badge($status) ?></td>
        <td><small><?= e(date('M j, Y', strtotime($r['enrolled_at']))) ?></small></td>
        <td><small><?= e(date('M j, Y', strtotime($r['updated_at']))) ?></small></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endforeach; ?>
<?php require '../includes/footer.php'; ?>
