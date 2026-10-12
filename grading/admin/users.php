<?php
require '../includes/auth.php';
require '../config/db.php';
require_role('admin');

$courses   = $pdo->query("SELECT id, code, name FROM courses ORDER BY code")->fetchAll();
$courseIds = array_map('intval', array_column($courses, 'id'));

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
    $name = trim($_POST['full_name'] ?? '');
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';
    $cid  = (int)($_POST['course_id'] ?? 0) ?: null;
    $sec  = substr(strtoupper(trim($_POST['section'] ?? '')), 0, 20);

    if ($name === '' || $user === '' || !in_array($role, ['admin', 'faculty', 'student'], true)) {
      flash('Fill in every field.', 'error');
    } elseif (strlen($pass) < 8) {
      flash('Password must be at least 8 characters.', 'error');
    } elseif ($role === 'student' && ($cid === null || !in_array($cid, $courseIds, true) || $sec === '')) {
      flash('Pick a course and enter a section for the student. Add the course first if it is not listed.', 'error');
    } else {
      if ($role !== 'student') { $cid = null; $sec = ''; }
      try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
          "INSERT INTO users (full_name, username, password, role, course_id, section)
           VALUES (:name, :user, :pass, :role, :cid, :sec)"
        );
        $stmt->execute([
          ':name' => $name,
          ':user' => $user,
          ':pass' => password_hash($pass, PASSWORD_DEFAULT),
          ':role' => $role,
          ':cid'  => $cid,
          ':sec'  => $sec === '' ? null : $sec,
        ]);
        $id  = (int)$pdo->lastInsertId();
        $msg = "Added $name.";
        if ($role === 'student') {
          $sid = date('Y') . '-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
          $pdo->prepare("UPDATE users SET student_id = ? WHERE id = ?")->execute([$sid, $id]);
          $msg = "Added $name with student ID $sid.";
        }
        $pdo->commit();
        flash($msg);
      } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('Could not add user. That username is already taken.', 'error');
      }
    }
  } elseif ($action === 'assign_class') {
    $cid = (int)($_POST['course_id'] ?? 0) ?: null;
    $sec = substr(strtoupper(trim($_POST['section'] ?? '')), 0, 20);
    if ($cid !== null && !in_array($cid, $courseIds, true)) $cid = null;
    $pdo->prepare("UPDATE users SET course_id = ?, section = ? WHERE id = ? AND role = 'student'")
        ->execute([$cid, $sec === '' ? null : $sec, (int)($_POST['id'] ?? 0)]);
    flash('Course and section updated.');
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
    <div class="field class-field" style="max-width:150px"><label for="course_id">Course</label>
      <select id="course_id" name="course_id"><?= course_options($courses) ?></select>
    </div>
    <div class="field class-field" style="max-width:120px"><label for="section">Section</label>
      <input id="section" name="section" placeholder="3A" maxlength="20">
    </div>
    <button>Add user</button>
  </form>
</div>

<div class="panel">
  <h2>All users</h2>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Student ID</th><th>Name</th><th>Username</th><th>Role</th><th>Course and section</th><th>Created</th><th></th></tr></thead>
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
              <select name="course_id" style="width:110px" aria-label="Course for <?= e($u['full_name']) ?>"><?= course_options($courses, $u['course_id']) ?></select>
              <input name="section" value="<?= e($u['section'] ?? '') ?>" placeholder="Section" maxlength="20" style="width:90px" aria-label="Section for <?= e($u['full_name']) ?>">
              <button class="btn-light">Save</button>
            </form>
          <?php else: ?>—<?php endif; ?>
        </td>
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
}
roleSel.addEventListener('change', toggleClass);
toggleClass();
</script>
<?php require '../includes/footer.php'; ?>
