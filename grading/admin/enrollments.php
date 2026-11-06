<?php
// admin/enrollments.php
// Main: KM | RM: enroll-by-section feature (siya may gawa, bow) | AP at IA: tested
require '../includes/auth.php';
require '../config/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';
  // The Semester dropdown sends a single value like '1|2026-2027'. We split it into the semester number and the school year.
  [$sem, $sy] = array_pad(explode('|', $_POST['term'] ?? '', 2), 2, '');
  $sem    = (int)$sem;
  $sy     = trim($sy);
  $tq     = $pdo->prepare("SELECT COUNT(*) FROM terms WHERE semester = ? AND school_year = ?");
  $tq->execute([$sem, $sy]);
  // $termOk = true;   // KM: dati, bypass lang para mag-test. DI NA GAMIT. tinanggal na yung bypass
  $termOk = (int)$tq->fetchColumn() > 0;

  if ($action === 'add') {
    // Enroll one student.
    $stu = (int)($_POST['student_id'] ?? 0);
    $sub = (int)($_POST['subject_id'] ?? 0);
    if (!$stu || !$sub || !$termOk) {
      flash('Pick a student, a subject, a semester and a school year.', 'error');
    } else {
      try {
        $pdo->prepare("INSERT INTO enrollments (student_id, subject_id, semester, school_year) VALUES (?,?,?,?)")->execute([$stu, $sub, $sem, $sy]);
        flash('Student enrolled.');
      } catch (PDOException $e) {
        // The unique key on (student, subject, semester, school year) is what throws this error.
        // AP: nag-try ako dalawang beses, lumabas yan. nice
        flash('That student is already enrolled in this subject for that semester.', 'error');
      }
    }
  } elseif ($action === 'add_section') {
    // RM: ito yung pinaka-proud ko. isang click, buong section na-enroll. dati isa-isa, 40 students, nagreklamo si MC.
    // MC: 40 minutes ako nun
    $sub = (int)($_POST['subject_id'] ?? 0);
    [$cid, $sec] = array_pad(explode('|', $_POST['class'] ?? '', 2), 2, '');
    if (!$sub || !(int)$cid || $sec === '' || !$termOk) {
      flash('Pick a section, a subject, a semester and a school year.', 'error');
    } else {
      // INSERT ... SELECT: copies every student of that course and section straight into enrollments in one query.
      // IGNORE means students who are already enrolled are skipped instead of throwing an error.
      $st = $pdo->prepare(
        "INSERT IGNORE INTO enrollments (student_id, subject_id, semester, school_year)
         SELECT id, ?, ?, ? FROM users WHERE role = 'student' AND course_id = ? AND section = ?"
      );
      $st->execute([$sub, $sem, $sy, (int)$cid, $sec]);
      // rowCount() tells us how many new rows were really inserted.
      $n = $st->rowCount();
      flash($n > 0 ? "Enrolled $n student" . ($n == 1 ? '' : 's') . '.' : 'Everyone in that section is already enrolled.');
    }
  } elseif ($action === 'delete') {
    $pdo->prepare("DELETE FROM enrollments WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
    flash('Enrollment removed.');
  }
  redirect('enrollments.php');
}

// RM: yung mga dropdown, galing lahat sa mga query na to. lima sila.
$students = $pdo->query(
  "SELECT u.id, u.student_id, u.full_name, u.section, c.code AS course_code
   FROM users u LEFT JOIN courses c ON c.id = u.course_id
   WHERE u.role='student' ORDER BY u.full_name"
)->fetchAll();
$subjects = $pdo->query("SELECT id, code, name FROM subjects ORDER BY code")->fetchAll();
// One row per course and section, with the number of students. This feeds the 'Enroll a whole section' dropdown.
$classes  = $pdo->query(
  "SELECT u.course_id, c.code, u.section, COUNT(*) AS n
   FROM users u JOIN courses c ON c.id = u.course_id
   WHERE u.role='student' AND u.section IS NOT NULL
   GROUP BY u.course_id, c.code, u.section ORDER BY c.code, u.section"
)->fetchAll();
// // echo $pdo->query("SELECT COUNT(*) FROM enrollments")->fetchColumn();   // IA: check kung ilan, para sure
$rows = $pdo->query(
  "SELECT e.id, e.enrolled_at, e.semester, e.school_year, u.student_id, u.full_name, u.section, c.code AS course_code, s.code, s.name
   FROM enrollments e
   JOIN users u    ON u.id = e.student_id
   JOIN subjects s ON s.id = e.subject_id
   LEFT JOIN courses c ON c.id = u.course_id
   ORDER BY e.school_year DESC, e.semester, s.code, c.code, u.section, u.full_name"
)->fetchAll();

$terms = $pdo->query("SELECT semester, school_year FROM terms ORDER BY school_year DESC, semester DESC")->fetchAll();

// One "Semester" dropdown with the semesters added on the Semesters page. The newest is selected.
// KM: reusable na piece, dalawang form ang gumagamit. Id yung parameter para di magkapareho ang id sa HTML.
function term_fields(string $id, array $terms): string {
  $o = '';
  foreach ($terms as $t) {
    $o .= '<option value="' . (int)$t['semester'] . '|' . e($t['school_year']) . '">' . e(term_label($t['semester'], $t['school_year'])) . '</option>';
  }
  return '<div class="field" style="max-width:260px"><label for="term_' . $id . '">Semester</label><select id="term_' . $id . '" name="term">' . $o . '</select></div>';
}

$title = 'Enrollments';
$base  = '../';
require '../includes/header.php';
?>
<div class="page-head"><h1>Enrollments</h1></div>

<?php // Nothing to enroll yet? Show a hint instead of empty forms. ?>
<?php if (!$students || !$subjects || !$terms): ?>
  <div class="panel"><p class="muted">You need at least one <a href="users.php">student</a>, one <a href="subjects.php">subject</a> and one <a href="terms.php">semester</a> first.</p></div>
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
    <?= term_fields('sec', $terms) ?>
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
    <?= term_fields('one', $terms) ?>
    <button>Enroll student</button>
  </form>
</div>
<?php endif; ?>

<div class="panel">
  <h2>Current enrollments</h2>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Term</th><th>Subject</th><th>Student ID</th><th>Student</th><th>Course and section</th><th>Enrolled</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="7" class="empty">Nobody is enrolled yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e(term_label($r['semester'], $r['school_year'])) ?></td>
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
