<?php
require '../includes/auth.php';
require '../config/db.php';
require_role('admin');

$courses    = $pdo->query("SELECT id, code, name, years FROM courses ORDER BY code")->fetchAll();
$courseIds  = array_map('intval', array_column($courses, 'id'));
$courseYears = array_column($courses, 'years', 'id');          // course id => number of year levels
$maxYears   = $courses ? max(array_map('intval', array_column($courses, 'years'))) : 4;

// The school year a new student's year level belongs to: the newest semester's school year.
function latest_school_year(PDO $pdo): string {
  return (string)($pdo->query("SELECT MAX(school_year) FROM terms")->fetchColumn() ?: current_school_year());
}

// Student IDs look like A-2021-14-568 (letter, year, 2 digits, 3 digits).
function valid_student_id(string $id): bool {
  return preg_match('/^[A-Z]-\d{4}-\d{2}-\d{3}$/', $id) === 1;
}

//WAG NYONG GALAWIN ITO (Marco)
// Generates an ID (e.i, A-2021-14-568)
// The year comes from the enrollment date. The two numbers (14-568) are random.
// The letter stays "A" until all 100,000 numbers (00-000 to 99-999) of that year are used, then it moves to "B", and so on.
function generate_student_id(PDO $pdo, int $year): string {
  $cap   = 100000;
  $count = $pdo->prepare("SELECT COUNT(*) FROM users WHERE student_id LIKE ?");
  $exist = $pdo->prepare("SELECT COUNT(*) FROM users WHERE student_id = ?");

  for ($c = 65; $c <= 90; $c++) {                      // A to Z
    $prefix = chr($c) . '-' . $year . '-';
    $count->execute([$prefix . '%']);
    $used = (int)$count->fetchColumn();
    if ($used >= $cap) continue;                       // letter is full, try the next one

    // Plenty of room, just try random numbers.
    if ($used < $cap * 0.9) {
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

function year_options(int $max, $selected = null): string {
  $o = '<option value="0">Year</option>';
  for ($y = 1; $y <= $max; $y++) $o .= '<option value="' . $y . '"' . ((int)$selected === $y ? ' selected' : '') . '>' . $y . '</option>';
  return $o;
}

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
    $first   = mb_substr(trim($_POST['first_name'] ?? ''), 0, 60);
    $middle  = mb_substr(trim($_POST['middle_name'] ?? ''), 0, 60);
    $surname = mb_substr(trim($_POST['surname'] ?? ''), 0, 60);
    $name    = trim(preg_replace('/\s+/', ' ', "$first $middle $surname"));   // for the success message
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $cid  = (int)($_POST['course_id'] ?? 0) ?: null;
    $sec  = substr(trim($_POST['section'] ?? ''), 0, 20);
    $yr   = (int)($_POST['year_level'] ?? 0);
    // Date the student enrolled. Left blank = today, so it is tracked automatically.
    $enrolled = trim($_POST['enrolled_on'] ?? '');
    if ($enrolled === '') $enrolled = date('Y-m-d');
    $dt   = DateTime::createFromFormat('Y-m-d', $enrolled);
    $year = ($dt && $dt->format('Y-m-d') === $enrolled) ? (int)$dt->format('Y') : 0;
    $stuId = '';

    //lala haha

    if ($first === '' || $surname === '' || $user === '' || !in_array($role, ['admin', 'faculty', 'student'], true)) {
      flash('Fill in every field.', 'error');
    } elseif (strlen($pass) < 8) {
      flash('Password must be at least 8 characters.', 'error');
    } elseif ($role === 'student' && ($cid === null || !in_array($cid, $courseIds, true))) {
      flash('Pick a course for the student. Add the course first if it is not listed.', 'error');
    } elseif ($role === 'student' && ($yr < 1 || $yr > (int)($courseYears[$cid] ?? 4))) {
      flash('Pick a year level that this course has.', 'error');
    } elseif ($role === 'student' && (!ctype_digit($sec) || strlen($sec) > 2 || (int)$sec < 1)) {
      flash('Section must be a number, like 1.', 'error');
    } elseif ($role === 'student' && ($year < 2000 || $year > (int)date('Y') + 1)) {
      flash('Enter a valid date enrolled for the student, for example 2021-08-15.', 'error');
    } else {
      $levelSy = null;
      if ($role !== 'student') { $cid = null; $sec = ''; $yr = null; }
      else { $stuId = generate_student_id($pdo, $year); $sec = (string)(int)$sec; $levelSy = latest_school_year($pdo); }
      try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
          "INSERT INTO users (student_id, first_name, middle_name, surname, username, password, role, course_id, section, year_level, level_school_year, enrolled_on)
           VALUES (:sid, :first, :middle, :surname, :user, :pass, :role, :cid, :sec, :yr, :lsy, :eon)"
        );
        $stmt->execute([
          ':sid'  => $stuId === '' ? null : $stuId,
          ':eon'  => $role === 'student' ? $enrolled : null,
          ':first'   => $first,
          ':middle'  => $middle === '' ? null : $middle,
          ':surname' => $surname,
          ':user' => $user,
          ':pass' => password_hash($pass, PASSWORD_DEFAULT),
          ':role' => $role,
          ':cid'  => $cid,
          ':sec'  => $sec === '' ? null : $sec,
          ':yr'   => $yr,
          ':lsy'  => $levelSy,
        ]);
        $msg = $role === 'student' ? "Added $name with student ID $stuId." : "Added $name.";
        $pdo->commit();
        flash($msg);
      } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // MySQL says which unique key was broken, e.g. "for key 'users.full_name'".
        preg_match("/for key '([^']+)'/", $e->getMessage(), $km);
        $key = $km[1] ?? '';
        if (stripos($key, 'full_name') !== false) {
          flash("Could not add user. Someone named $name already exists. If it is a different person, add a middle name.", 'error');
        } elseif (stripos($key, 'student_id') !== false) {
          flash('Could not add user. That student ID is already used.', 'error');
        } else {
          flash('Could not add user. That username is already taken.', 'error');
        }
      }
    }
  } elseif ($action === 'rename') {
    $first   = mb_substr(trim($_POST['first_name'] ?? ''), 0, 60);
    $middle  = mb_substr(trim($_POST['middle_name'] ?? ''), 0, 60);
    $surname = mb_substr(trim($_POST['surname'] ?? ''), 0, 60);
    if ($first === '' || $surname === '') {
      flash('Enter at least a first name and a surname.', 'error');
    } else {
      try {
        $pdo->prepare("UPDATE users SET first_name = ?, middle_name = ?, surname = ? WHERE id = ?")
            ->execute([$first, $middle === '' ? null : $middle, $surname, (int)($_POST['id'] ?? 0)]);
        flash('Name updated.');
      } catch (PDOException $e) {
        flash('Someone else already has that name. If it is a different person, add a middle name.', 'error');
      }
    }
  } elseif ($action === 'assign_class') {
    $cid = (int)($_POST['course_id'] ?? 0) ?: null;
    $sec = substr(trim($_POST['section'] ?? ''), 0, 20);
    $yr  = (int)($_POST['year_level'] ?? 0) ?: null;
    if ($cid !== null && !in_array($cid, $courseIds, true)) $cid = null;
    $uid   = (int)($_POST['id'] ?? 0);
    $stuId = strtoupper(trim($_POST['student_id'] ?? ''));
    $cur   = $pdo->prepare("SELECT student_id FROM users WHERE id = ? AND role = 'student'");
    $cur->execute([$uid]);
    $curId = $cur->fetchColumn();
    if ($curId === false) {
      flash('Student not found.', 'error');
    } elseif ($sec !== '' && (!ctype_digit($sec) || strlen($sec) > 2)) {
      flash('Section must be a number, like 1.', 'error');
    } elseif ($yr !== null && $cid !== null && $yr > (int)($courseYears[$cid] ?? 4)) {
      flash('That course does not have that year level.', 'error');
    } elseif ($stuId !== (string)$curId && !valid_student_id($stuId)) {
      flash('Student ID must look like A-2021-14-568.', 'error');
    } else {
      try {
        $pdo->prepare("UPDATE users SET student_id = ?, course_id = ?, year_level = ?, section = ?, level_school_year = ? WHERE id = ? AND role = 'student'")
            ->execute([$stuId === '' ? null : $stuId, $cid, $yr, $sec === '' ? null : (string)(int)$sec, latest_school_year($pdo), $uid]);
        flash('Student updated.');
      } catch (PDOException $e) {
        flash('That student ID is already used by another student.', 'error');
      }
    }
  } elseif ($action === 'delete') {
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

$users = $pdo->query("SELECT * FROM users ORDER BY FIELD(role,'admin','faculty','student'), full_name")->fetchAll();

$title = 'Users';
$base  = '../';
require '../includes/header.php';
?>
<style>
  /* Show/hide password eye. It stays visible whenever something is typed, focused or not. */
  .pw-wrap{position:relative}
  .pw-wrap input{padding-right:44px}
  .pw-wrap input::-ms-reveal,.pw-wrap input::-ms-clear{display:none} /* hide Edge's own eye so there is only one */
  .pw-toggle{position:absolute;right:4px;top:50%;transform:translateY(-50%);display:flex;padding:6px;line-height:0;
             background:none;border:0;color:var(--muted-text);cursor:pointer}
  .pw-toggle:hover{background:none;color:var(--link)}
  .pw-toggle[hidden]{display:none}
  .name-edit summary{cursor:pointer;font-size:.82rem;color:var(--link)}
</style>

<div class="page-head"><h1>Users</h1></div>

<div class="panel">
  <h2>Add a user</h2>
  <?php if (!$courses): ?>
    <p class="muted">To add students, <a href="courses.php">add a course</a> first.</p>
  <?php endif; ?>
  <form method="post" class="form-row" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="field"><label for="first_name">First name</label><input id="first_name" name="first_name" maxlength="60" autocomplete="off" required></div>
    <div class="field"><label for="middle_name">Middle name (optional)</label><input id="middle_name" name="middle_name" maxlength="60" autocomplete="off"></div>
    <div class="field"><label for="surname">Surname</label><input id="surname" name="surname" maxlength="60" autocomplete="off" required></div>
    <div class="field"><label for="username">Username</label><input id="username" name="username" autocomplete="off" required></div>
    <div class="field"><label for="password">Password (8+ characters)</label>
      <div class="pw-wrap">
        <input id="password" type="password" name="password" minlength="8" autocomplete="new-password" required>
        <button type="button" class="pw-toggle" id="pw-toggle" aria-label="Show password" aria-pressed="false" hidden></button>
      </div>
    </div>
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
    <div class="field class-field" style="max-width:110px"><label for="year_level">Year level</label>
      <select id="year_level" name="year_level"><?= year_options($maxYears, 1) ?></select>
    </div>
    <div class="field class-field" style="max-width:110px"><label for="section">Section</label>
      <input id="section" name="section" placeholder="1" maxlength="2" inputmode="numeric" pattern="[0-9]{1,2}" title="A number, like 1">
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
        <td>
          <?= e($u['full_name']) ?>
          <details class="name-edit">
            <summary>Edit name</summary>
            <form method="post" class="form-row" style="flex-wrap:nowrap;gap:6px;margin-top:6px">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="rename">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <input name="first_name" value="<?= e($u['first_name'] ?? '') ?>" placeholder="First name" maxlength="60" style="width:120px" aria-label="First name of <?= e($u['full_name']) ?>" required>
              <input name="middle_name" value="<?= e($u['middle_name'] ?? '') ?>" placeholder="Middle" maxlength="60" style="width:110px" aria-label="Middle name of <?= e($u['full_name']) ?>">
              <input name="surname" value="<?= e($u['surname'] ?? '') ?>" placeholder="Surname" maxlength="60" style="width:120px" aria-label="Surname of <?= e($u['full_name']) ?>" required>
              <button class="btn-light">Save</button>
            </form>
          </details>
        </td>
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
              <select name="year_level" style="width:75px" aria-label="Year level for <?= e($u['full_name']) ?>"><?= year_options($maxYears, $u['year_level']) ?></select>
              <input name="section" value="<?= e($u['section'] ?? '') ?>" placeholder="Sec" maxlength="2" inputmode="numeric" style="width:60px" aria-label="Section for <?= e($u['full_name']) ?>">
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
const roleSel = document.getElementById('role');
const classFields = document.querySelectorAll('.class-field');
function toggleClass() {
  const isStudent = roleSel.value === 'student';
  classFields.forEach(f => f.style.display = isStudent ? '' : 'none');
  document.getElementById('enrolled_on').required = isStudent;
}
roleSel.addEventListener('change', toggleClass);
toggleClass();

// Show/hide password: the eye appears as soon as something is typed and stays until the box is empty.
(function () {
  const pw = document.getElementById('password');
  const btn = document.getElementById('pw-toggle');
  const svg = p => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + p + '</svg>';
  const EYE     = svg('<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>');
  const EYE_OFF = svg('<path d="M17.94 17.94A10.94 10.94 0 0 1 12 19C5 19 1 12 1 12a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A10.9 10.9 0 0 1 12 5c7 0 11 7 11 7a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>');

  function setShown(show) {
    pw.type = show ? 'text' : 'password';
    btn.innerHTML = show ? EYE_OFF : EYE;
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  }
  function sync() {
    btn.hidden = pw.value === '';
    if (pw.value === '') setShown(false);   // empty box: back to hidden, ready for next time
  }

  btn.addEventListener('click', () => { setShown(pw.type === 'password'); pw.focus(); });
  pw.addEventListener('input', sync);
  pw.addEventListener('change', sync);
  setShown(false);
  sync();
})();
</script>
<script src="<?= $base ?>assets/password-toggle.js"></script>
<?php require '../includes/footer.php'; ?>
