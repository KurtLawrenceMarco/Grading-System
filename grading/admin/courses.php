<?php
require '../includes/auth.php';
require '../config/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  if ($action === 'add') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    if ($code === '' || $name === '') {
      flash('Enter a course code and name.', 'error');
    } else {
      try {
        $pdo->prepare("INSERT INTO courses (code, name) VALUES (?, ?)")->execute([$code, $name]);
        flash("Added $code.");
      } catch (PDOException $e) {
        flash('Could not add course. That code already exists.', 'error');
      }
    }
  } elseif ($action === 'delete') {
    $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
    flash('Course deleted. Its students now show as having no course.');
  }
  redirect('courses.php');
}

$courses = $pdo->query(
  "SELECT c.*, (SELECT COUNT(*) FROM users u WHERE u.course_id = c.id) AS students
   FROM courses c ORDER BY c.code"
)->fetchAll();

$title = 'Courses';
$base  = '../';
require '../includes/header.php';
?>
<div class="page-head"><h1>Courses</h1></div>

<div class="panel">
  <h2>Add a course</h2>
  <form method="post" class="form-row">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="field" style="max-width:160px"><label for="code">Code</label><input id="code" name="code" placeholder="BSIT" required></div>
    <div class="field"><label for="name">Course name</label><input id="name" name="name" placeholder="BS Information Technology" required></div>
    <button>Add course</button>
  </form>
</div>

<div class="panel">
  <h2>All courses</h2>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Code</th><th>Name</th><th class="num">Students</th><th></th></tr></thead>
    <tbody>
    <?php if (!$courses): ?>
      <tr><td colspan="4" class="empty">No courses yet. Add one above.</td></tr>
    <?php endif; ?>
    <?php foreach ($courses as $c): ?>
      <tr>
        <td><b><?= e($c['code']) ?></b></td>
        <td><?= e($c['name']) ?></td>
        <td class="num"><?= (int)$c['students'] ?></td>
        <td class="num">
          <form method="post" class="inline"
                onsubmit="return confirm('Delete <?= e(addslashes($c['code'])) ?>? Its students will have no course until you reassign them.')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="btn-danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php require '../includes/footer.php'; ?>
