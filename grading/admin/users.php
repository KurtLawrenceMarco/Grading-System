<?php
// admin/users.php  -  add, edit and delete users
// Main: KM | RM: validation | MC: kunwari tumulong sa student ID | Novie: form layout
require '../includes/auth.php';
require '../config/db.php';
require_role('admin');

$courses   = $pdo->query("SELECT id, code, name FROM courses ORDER BY code")->fetchAll();
$courseIds = array_map('intval', array_column($courses, 'id'));

// Student IDs look like A-2021-14-568 (letter, year, 2 digits, 3 digits).
// KM: regex lang. letter, apat na digits, dalawang digits, tatlong digits. Yan yung format.
function valid_student_id(string $id): bool {
  return preg_match('/^[A-Z]-\d{4}-\d{2}-\d{3}$/', $id) === 1;
}

//WAG NYONG GALAWIN ITO
// Generates an ID (e.i, A-2021-14-568)
// The year comes from the enrollment date. The two numbers (14-568) are random.
// The letter stays "A" until all 100,000 numbers (00-000 to 99-999) of that year are used, then it moves to "B", and so on.
// KM: ulit, WAG NYONG GALAWIN YUNG generate_student_id. (nasa taas na yung warning pero alam ko di kayo nagbabasa)
// RM: kaya nga merong git. wala pala tayong git.
function generate_student_id(PDO $pdo, int $year): string {
  // 00-000 hanggang 99-999 = 100,000 combinations per letter. Pag puno na, lilipat sa B, tapos C, etc.
  $cap   = 100000;
  $count = $pdo->prepare("SELECT COUNT(*) FROM users WHERE student_id LIKE ?");
  $exist = $pdo->prepare("SELECT COUNT(*) FROM users WHERE student_id = ?");

  for ($c = 65; $c <= 90; $c++) {                      // A to Z
    $prefix = chr($c) . '-' . $year . '-';
    $count->execute([$prefix . '%']);
    $used = (int)$count->fetchColumn();
    if ($used >= $cap) continue;                       // letter is full, try the next one

    // Plenty of room: just try random numbers.
    if ($used < $cap * 0.9) {
      // $id = 'A-' . $year . '-' . rand(10, 99) . '-' . rand(100, 999);   // MC's version. Walang duplicate check kaya nag-collide
      // // echo $id; exit;   // IA: test lang para makita kung tama yung format
      for ($i = 0; $i < 50; $i++) {
        $id = $prefix . str_pad((string)random_int(0, 99), 2, '0', STR_PAD_LEFT)
                      . '-' . str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
        $exist->execute([$id]);
        if ((int)$exist->fetchColumn() === 0) return $id;
      }
    }

    // Nearly full: pick randomly from the numbers that are still free.
    $q = $pdo->prepare("SELECT SUBSTRING(student_id, 8) FROM users WHERE student_id LIKE ?");
    $q->execute([$prefix . '%']);
    $taken = array_flip($q->fetchAll(PDO::FETCH_COLUMN));
    $free  = [];
    for ($n = 0; $n < $cap; $n++) {
      $suffix = sprintf('%02d-%03d', intdiv($n, 1000), $n % 1000);
      if (!isset($taken[$suffix])) $free[] = $suffix;
    }
    if ($free) return $prefix . $free[array_rand($free)];
  }
  throw new RuntimeException('Could not generate a free student ID.');
}

// RM: dropdown ng courses. 'No course' = 0 sa value, ginagawang NULL sa database.
function course_options(array $courses, $selected = null): string {
  $o = '<option value="0">No course</option>';
  foreach ($courses as $c) {
    $sel = ((int)$selected === (int)$c['id']) ? ' selected' : '';
    $o  .= '<option value="' . (int)$c['id'] . '"' . $sel . '>' . e($c['code']) . '</option>';
  }
  return $o;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  if ($action === 'add') {
    // RM: kinukuha lahat ng input. trim() lahat para walang space sa dulo (may nag-type ng 'admin ' sa dulo at hindi makapag-login, hays)
    $name = trim($_POST['full_name'] ?? '');
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $cid  = (int)($_POST['course_id'] ?? 0) ?: null;
    $sec  = substr(strtoupper(trim($_POST['section'] ?? '')), 0, 20);
    // Novie: yung 'Date enrolled' sa form, ako nag-hide sa non-students, tingnan yung script sa baba.
    // Date the student enrolled. Left blank = today, so it is tracked automatically.
    $enrolled = trim($_POST['enrolled_on'] ?? '');
    if ($enrolled === '') $enrolled = date('Y-m-d');
    $dt   = DateTime::createFromFormat('Y-m-d', $enrolled);
    $year = ($dt && $dt->format('Y-m-d') === $enrolled) ? (int)$dt->format('Y') : 0;
    // KM: yung year sa student ID galing sa Date Enrolled. Kaya kelangan valid yung date bago mag-generate.
    $stuId = '';

    if ($name === '' || $user === '' || !in_array($role, ['admin', 'faculty', 'student'], true)) {
      flash('Fill in every field.', 'error');
    // Passwords must be at least 8 characters.
    } elseif (strlen($pass) < 8) {
      flash('Password must be at least 8 characters.', 'error');
    } elseif ($role === 'student' && ($cid === null || !in_array($cid, $courseIds, true) || $sec === '')) {
      flash('Pick a course and enter a section for the student. Add the course first if it is not listed.', 'error');
    } elseif ($role === 'student' && ($year < 2000 || $year > (int)date('Y') + 1)) {
      flash('Enter a valid date enrolled for the student, for example 2021-08-15.', 'error');
    } else {
      if ($role !== 'student') { $cid = null; $sec = ''; } else { $stuId = generate_student_id($pdo, $year); }
      try {
        // KM: transaction kasi kung mag-fail yung insert, rollback para di maiwan nang kalahati.
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
          "INSERT INTO users (student_id, full_name, username, password, role, course_id, section, enrolled_on)
           VALUES (:sid, :name, :user, :pass, :role, :cid, :sec, :eon)"
        );
        $stmt->execute([
          ':sid'  => $stuId === '' ? null : $stuId,
          ':eon'  => $role === 'student' ? $enrolled : null,
          ':name' => $name,
          ':user' => $user,
          // Passwords are hashed with password_hash(). We never store or log the plain text password.
          ':pass' => password_hash($pass, PASSWORD_DEFAULT),
          ':role' => $role,
          ':cid'  => $cid,
          ':sec'  => $sec === '' ? null : $sec,
        ]);
        $msg = $role === 'student' ? "Added $name with student ID $stuId." : "Added $name.";
        $pdo->commit();
        flash($msg);
      } catch (PDOException $e) {
        // RM: dalawang posibleng error: student ID na gamit na, o username na gamit na. Sinusuri natin kung alin base sa message.
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash(stripos($e->getMessage(), 'student_id') !== false
              ? 'Could not add user. That student ID is already used.'
              : 'Could not add user. That username/fullname is already taken.', 'error');
      }
    }
  } elseif ($action === 'assign_class') {
    // IA: sa laptop ko tinest ko to, pwede mag-edit ng ID. pero kapag mali ang format, hindi papasa.
    $cid = (int)($_POST['course_id'] ?? 0) ?: null;
    $sec = substr(strtoupper(trim($_POST['section'] ?? '')), 0, 20);
    if ($cid !== null && !in_array($cid, $courseIds, true)) $cid = null;
    $uid   = (int)($_POST['id'] ?? 0);
    // We uppercase the ID, and only validate the format when it actually changed (old IDs stay valid).
    $stuId = strtoupper(trim($_POST['student_id'] ?? ''));
    $cur   = $pdo->prepare("SELECT student_id FROM users WHERE id = ? AND role = 'student'");
    $cur->execute([$uid]);
    $curId = $cur->fetchColumn();
    if ($curId === false) {
      flash('Student not found.', 'error');
    } elseif ($stuId !== (string)$curId && !valid_student_id($stuId)) {
      flash('Student ID must look like A-2024-568.', 'error');
    } else {
      try {
        $pdo->prepare("UPDATE users SET student_id = ?, course_id = ?, section = ? WHERE id = ? AND role = 'student'")
            ->execute([$stuId === '' ? null : $stuId, $cid, $sec === '' ? null : $sec, $uid]);
        flash('Student updated.');
      } catch (PDOException $e) {
        flash('That student ID is already used by another student.', 'error');
      }
    }
  } elseif ($action === 'delete') {
    // NOTE: An admin cannot delete their own account, otherwise the system could end up with no admin.
    // MC: ginawa ko yan! ...KM: hindi. ako gumawa. MC: ...oo nga pala
    $id = (int)($_POST['id'] ?? 0);
    if ($id === (int)$_SESSION['user_id']) {
      flash('You cannot delete your own account.', 'error');
    } else {
      $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
      flash('User deleted.');
    }
  }
  redirect('users.php');
}

// FIELD() sorts by role order (admin, faculty, student) then by name.
// IA: sa laptop ko, nag-error to nung lumang XAMPP. MySQL version issue pala. nag-update ako.
$users = $pdo->query("SELECT * FROM users ORDER BY FIELD(role,'admin','faculty','student'), full_name")->fetchAll();

$title = 'Users';
$base  = '../';
require '../includes/header.php';
?>
<div class="page-head"><h1>Users</h1></div>

<div class="panel">
  <h2>Add a user</h2>
  <?php if (!$courses): ?>
    <p class="muted">To add students, <a href="courses.php">add a course</a> first.</p>
  <?php endif; ?>
  <form method="post" class="form-row">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="field"><label for="full_name">Full name</label><input id="full_name" name="full_name" required></div>
    <div class="field"><label for="username">Username</label><input id="username" name="username" required></div>
    <div class="field"><label for="password">Password (8+ characters)</label><input id="password" type="password" name="password" minlength="8" required></div>
    <div class="field" style="max-width:150px"><label for="role">Role</label>
      <select id="role" name="role" required>
        <option value="student">Student</option>
        <option value="faculty">Faculty</option>
        <option value="admin">Admin</option>
      </select>
    </div>
    <div class="field class-field" style="max-width:170px"><label for="enrolled_on">Date enrolled</label>
      <input id="enrolled_on" type="date" name="enrolled_on" value="<?= date('Y-m-d') ?>" title="Today by default. The year of this date becomes the year in the student ID.">
    </div>
    <div class="field class-field" style="max-width:150px"><label for="course_id">Course</label>
      <select id="course_id" name="course_id"><?= course_options($courses) ?></select>
    </div>
    <div class="field class-field" style="max-width:120px"><label for="section">Section</label>
      <input id="section" name="section" placeholder="(example: 1-1)" maxlength="20">
    </div>
    <button>Add user</button>
  </form>
</div>

<div class="panel">
  <h2>All users</h2>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Student ID</th><th>Name</th><th>Username</th><th>Role</th><th>Course and section</th><th>Enrolled</th><th>Created</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['student_id'] ?? '—') ?></td>
        <td><?= e($u['full_name']) ?></td>
        <td><?= e($u['username']) ?></td>
        <td><span class="role"><?= e($u['role']) ?></span></td>
        <td>
          <?php if ($u['role'] === 'student'): ?>
            <form method="post" class="form-row" style="flex-wrap:nowrap;gap:6px">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="assign_class">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <input name="student_id" value="<?= e($u['student_id'] ?? '') ?>" placeholder="A-2021-14-568" maxlength="13" style="width:135px" aria-label="Student ID for <?= e($u['full_name']) ?>">
              <select name="course_id" style="width:110px" aria-label="Course for <?= e($u['full_name']) ?>"><?= course_options($courses, $u['course_id']) ?></select>
              <input name="section" value="<?= e($u['section'] ?? '') ?>" placeholder="Section" maxlength="20" style="width:90px" aria-label="Section for <?= e($u['full_name']) ?>">
              <button class="btn-light">Save</button>
            </form>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td><?= !empty($u['enrolled_on']) ? e(date('M j, Y', strtotime($u['enrolled_on']))) : '—' ?></td>
        <td><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
        <td class="num">
          <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
            <form method="post" class="inline"
                  onsubmit="return confirm('Delete <?= e(addslashes($u['full_name'])) ?>? Their grades will be deleted too.')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button class="btn-danger">Delete</button>
            </form>
          <?php else: ?><small>You</small><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<script>
// Course and section only apply to students.
// Novie: yung course, section at date enrolled, lalabas lang pag Student ang role. Kasi ang weird kung may course ang admin.
const roleSel = document.getElementById('role');
const classFields = document.querySelectorAll('.class-field');
function toggleClass() {
  const isStudent = roleSel.value === 'student';
  classFields.forEach(f => f.style.display = isStudent ? '' : 'none');
  document.getElementById('enrolled_on').required = isStudent;
}
roleSel.addEventListener('change', toggleClass);
toggleClass();
</script>
<?php require '../includes/footer.php'; ?>
