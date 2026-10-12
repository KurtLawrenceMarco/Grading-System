<?php
require '../includes/auth.php';
require '../config/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  if ($action === 'add') {
    $stu = (int)($_POST['student_id'] ?? 0);
    $sub = (int)($_POST['subject_id'] ?? 0);
    if (!$stu || !$sub) {
      flash('Pick a student and a subject.', 'error');
    } else {
      try {
        $pdo->prepare("INSERT INTO enrollments (student_id, subject_id) VALUES (?,?)")->execute([$stu, $sub]);
        flash('Student enrolled.');
      } catch (PDOException $e) {
        flash('That student is already enrolled in this subject.', 'error');
      }
    }
  } elseif ($action === 'add_section') {
    $sub = (int)($_POST['subject_id'] ?? 0);
    [$cid, $sec] = array_pad(explode('|', $_POST['class'] ?? '', 2), 2, '');
    if (!$sub || !(int)$cid || $sec === '') {
      flash('Pick a section and a subject.', 'error');
    } else {
      $st = $pdo->prepare(
        "INSERT IGNORE INTO enrollments (student_id, subject_id)
         SELECT id, ? FROM users WHERE role = 'student' AND course_id = ? AND section = ?"
      );
      $st->execute([$sub, (int)$cid, $sec]);
      $n = $st->rowCount();
      flash($n > 0 ? "Enrolled $n student" . ($n == 1 ? '' : 's') . '.' : 'Everyone in that section is already enrolled.');
    }
  } elseif ($action === 'delete') {
    $pdo->prepare("DELETE FROM enrollments WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
    flash('Enrollment removed.');
  }
  redirect('enrollments.php');
}

$students = $pdo->query(
  "SELECT u.id, u.student_id, u.full_name, u.section, c.code AS course_code
   FROM users u LEFT JOIN courses c ON c.id = u.course_id
   WHERE u.role='student' ORDER BY u.full_name"
)->fetchAll();
$subjects = $pdo->query("SELECT id, code, name FROM subjects ORDER BY code")->fetchAll();
$classes  = $pdo->query(
  "SELECT u.course_id, c.code, u.section, COUNT(*) AS n
   FROM users u JOIN courses c ON c.id = u.course_id
   WHERE u.role='student' AND u.section IS NOT NULL
   GROUP BY u.course_id, c.code, u.section ORDER BY c.code, u.section"
)->fetchAll();
$rows = $pdo->query(
  "SELECT e.id, e.enrolled_at, u.student_id, u.full_name, u.section, c.code AS course_code, s.code, s.name
   FROM enrollments e
   JOIN users u    ON u.id = e.student_id
   JOIN subjects s ON s.id = e.subject_id
   LEFT JOIN courses c ON c.id = u.course_id
   ORDER BY s.code, c.code, u.section, u.full_name"
)->fetchAll();

$title = 'Enrollments';
$base  = '../';
require '../includes/header.php';
?>
<div class="page-head"><h1>Enrollments</h1></div>

<?php if (!$students || !$subjects): ?>
  <div class="panel"><p class="muted">You need at least one <a href="users.php">student</a> and one <a href="subjects.php">subject</a> first.</p></div>
<?php else: ?>
<div class="panel">
  <h2>Enroll a whole section</h2>
  <form method="post" class="form-row">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_section">
    <div class="field"><label for="class">Course and section</label>
      <select id="class" name="class" required>
        <?php foreach ($classes as $c): ?>
          <option value="<?= (int)$c['course_id'] . '|' . e($c['section']) ?>"><?= e($c['code'] . ' ' . $c['section']) ?> (<?= (int)$c['n'] ?> student<?= $c['n'] == 1 ? '' : 's' ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label for="subject_sec">Subject</label>
      <select id="subject_sec" name="subject_id" required>
        <?php foreach ($subjects as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= e($s['code'] . ' · ' . $s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button>Enroll section</button>
  </form>
</div>

<div class="panel">
  <h2>Enroll one student</h2>
  <form method="post" class="form-row">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="field"><label for="student_id">Student</label>
      <select id="student_id" name="student_id" required>
        <?php foreach ($students as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= e($s['student_id'] . ' · ' . $s['full_name'] . ($s['course_code'] ? ' (' . $s['course_code'] . ' ' . $s['section'] . ')' : '')) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label for="subject_id">Subject</label>
      <select id="subject_id" name="subject_id" required>
        <?php foreach ($subjects as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= e($s['code'] . ' · ' . $s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button>Enroll student</button>
  </form>
</div>
<?php endif; ?>

<div class="panel">
  <h2>Current enrollments</h2>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Subject</th><th>Student ID</th><th>Student</th><th>Course and section</th><th>Enrolled</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="6" class="empty">Nobody is enrolled yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><b><?= e($r['code']) ?></b> <small><?= e($r['name']) ?></small></td>
        <td><?= e($r['student_id']) ?></td>
        <td><?= e($r['full_name']) ?></td>
        <td><?= $r['course_code'] ? e($r['course_code'] . ' ' . $r['section']) : '—' ?></td>
        <td><?= e(date('M j, Y', strtotime($r['enrolled_at']))) ?></td>
        <td class="num">
          <form method="post" class="inline"
                onsubmit="return confirm('Remove this enrollment? Their grades for the subject will be deleted.')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn-danger">Remove</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php require '../includes/footer.php'; ?>
