<?php
require '../includes/auth.php';
require '../config/db.php';
require_role('faculty');

// One row per course + section + subject that this professor teaches.
$q = $pdo->prepare(
  "SELECT c.id AS course_id, c.code AS course_code, c.name AS course_name, e.year_level, e.section, e.semester, e.school_year,
          s.id AS subject_id, s.code, s.name, COUNT(*) AS students
   FROM enrollments e
   JOIN subjects s ON s.id = e.subject_id
   JOIN users u    ON u.id = e.student_id
   LEFT JOIN courses c ON c.id = u.course_id
   WHERE e.faculty_id = ?
   GROUP BY e.school_year, e.semester, c.id, c.code, c.name, e.year_level, e.section, s.id, s.code, s.name
   ORDER BY e.school_year DESC, e.semester DESC, c.code IS NULL, c.code, e.year_level, e.section IS NULL, e.section, s.code"
);
$q->execute([$_SESSION['user_id']]);

// Build the folder tree: semester + school year > course > section > subjects.
$tree = [];
foreach ($q->fetchAll() as $r) {
  $tk = $r['school_year'] . '|' . $r['semester'];
  $ck = (int)($r['course_id'] ?? 0);
  $sk = ($r['year_level'] ?? '') . '|' . ($r['section'] ?? '');
  $tree[$tk]['label'] = term_label($r['semester'], $r['school_year']);
  $tree[$tk]['courses'][$ck]['code'] = $r['course_code'] ?? 'No course assigned';
  $tree[$tk]['courses'][$ck]['name'] = $r['course_name'] ?? '';
  $tree[$tk]['courses'][$ck]['sections'][$sk][] = $r;
}

// Subjects of this professor that nobody is enrolled in yet.
$empty = $pdo->prepare(
  "SELECT s.code, s.name FROM subjects s
   JOIN subject_faculty sf ON sf.subject_id = s.id
   WHERE sf.faculty_id = ?
     AND NOT EXISTS (SELECT 1 FROM enrollments e WHERE e.subject_id = s.id AND e.faculty_id = sf.faculty_id)
   ORDER BY s.code"
);
$empty->execute([$_SESSION['user_id']]);
$empty = $empty->fetchAll();

// Semester buttons: every semester the admin has added, newest first.
$termList = [];
foreach ($pdo->query("SELECT semester, school_year FROM terms")->fetchAll() as $t) {
  $termList[$t['school_year'] . '|' . $t['semester']] = term_label($t['semester'], $t['school_year']);
}
foreach ($tree as $k => $t) { if (!isset($termList[$k])) $termList[$k] = $t['label']; }
krsort($termList);

// Which semester is open: the button that was clicked, else the newest one where this professor has students.
$want = ($_GET['school_year'] ?? '') . '|' . (int)($_GET['semester'] ?? 0);
if (isset($termList[$want])) {
  $selected = $want;
} else {
  $selected = null;
  foreach (array_keys($termList) as $k) { if (isset($tree[$k])) { $selected = $k; break; } }
  if ($selected === null) $selected = array_key_first($termList);
}
$term = $selected !== null ? ($tree[$selected] ?? null) : null;

$title = 'Faculty';
$base  = '../';
require '../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>Faculty dashboard</h1>
    <p class="muted">Welcome, <?= e($_SESSION['name']) ?>. Pick a semester, then open a course, then a section, then pick a subject to grade.</p>
  </div>
</div>

<?php if (!$tree && !$empty): ?>
  <div class="panel muted">No subjects are assigned to you yet. Ask an admin to assign one.</div>
<?php endif; ?>

<?php if ($termList): ?>
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px">
  <?php foreach ($termList as $k => $label): [$ksy, $ksem] = explode('|', $k); ?>
    <a class="btn <?= $k === $selected ? '' : 'btn-light' ?>"
       href="dashboard.php?<?= e(http_build_query(['semester' => $ksem, 'school_year' => $ksy])) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($termList && !$term && ($tree || $empty)): ?>
  <div class="panel muted">You have no students in <?= e($termList[$selected]) ?> yet.</div>
<?php endif; ?>

<?php if ($term): $first = true; foreach ($term['courses'] as $cid => $course): ?>
  <details class="folder" <?= $first ? 'open' : '' ?>>
    <summary>
      <span>📁 <?= e($course['code']) ?></span>
      <small><?= e($course['name']) ?></small>
    </summary>
    <div class="folder-body">
      <?php foreach ($course['sections'] as $sec => $subjects):
        [$yr, $sc] = explode('|', $sec, 2);
        $secLabel = ($yr === '' && $sc === '') ? 'No section' : 'Section ' . e(trim($yr . '-' . $sc, '-')); ?>
        <details class="folder sub" <?= $first ? 'open' : '' ?>>
          <summary><span>📁 <?= $secLabel ?></span>
            <small><?= count($subjects) ?> subject<?= count($subjects) == 1 ? '' : 's' ?></small>
          </summary>
          <div class="folder-body">
            <?php foreach ($subjects as $s):
              $href = 'grade.php?' . http_build_query([
                'subject'     => $s['subject_id'],
                'course'      => $cid ?: '',
                'year'        => $yr,
                'section'     => $sc,
                'semester'    => $s['semester'],
                'school_year' => $s['school_year'],
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
  <?php $first = false; endforeach; endif; ?>

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
