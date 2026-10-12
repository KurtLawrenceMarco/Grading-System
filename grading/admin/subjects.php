<?php
require '../includes/auth.php';
require '../config/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';
  $fid    = (int)($_POST['faculty_id'] ?? 0) ?: null;

  if ($action === 'add') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    if ($code === '' || $name === '') {
      flash('Enter a subject code and name.', 'error');
    } else {
      try {
        $pdo->prepare("INSERT INTO subjects (code, name, faculty_id) VALUES (?,?,?)")
            ->execute([$code, $name, $fid]);
        flash("Added $code.");
      } catch (PDOException $e) {
        flash('Could not add subject. That code already exists.', 'error');
      }
    }
  } elseif ($action === 'assign') {
    $pdo->prepare("UPDATE subjects SET faculty_id = ? WHERE id = ?")
        ->execute([$fid, (int)($_POST['id'] ?? 0)]);
    flash('Professor updated.');
  } elseif ($action === 'delete') {
    $pdo->prepare("DELETE FROM subjects WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
    flash('Subject deleted.');
  }
  redirect('subjects.php');
}

$faculty  = $pdo->query("SELECT id, full_name FROM users WHERE role='faculty' ORDER BY full_name")->fetchAll();
$subjects = $pdo->query(
  "SELECT s.*, (SELECT COUNT(*) FROM enrollments e WHERE e.subject_id = s.id) AS enrolled
   FROM subjects s ORDER BY s.code"
)->fetchAll();

function faculty_options(array $faculty, $selected = null): string {
  $o = '<option value="0">No professor</option>';
  foreach ($faculty as $f) {
    $sel = ((int)$selected === (int)$f['id']) ? ' selected' : '';
    $o  .= '<option value="' . (int)$f['id'] . '"' . $sel . '>' . e($f['full_name']) . '</option>';
  }
  return $o;
}

$title = 'Subjects';
$base  = '../';
require '../includes/header.php';
?>
<div class="page-head"><h1>Subjects</h1></div>

<div class="panel">
  <h2>Add a subject</h2>
  <form method="post" class="form-row">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="field" style="max-width:160px"><label for="code">Code</label><input id="code" name="code" placeholder="IT101" required></div>
    <div class="field"><label for="name">Subject name</label><input id="name" name="name" placeholder="Intro to Programming" required></div>
    <div class="field"><label for="faculty_id">Professor</label>
      <select id="faculty_id" name="faculty_id"><?= faculty_options($faculty) ?></select>
    </div>
    <button>Add subject</button>
  </form>
</div>

<div class="panel">
  <h2>All subjects</h2>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Code</th><th>Name</th><th>Professor</th><th class="num">Students</th><th></th></tr></thead>
    <tbody>
    <?php if (!$subjects): ?>
      <tr><td colspan="5" class="empty">No subjects yet. Add one above.</td></tr>
    <?php endif; ?>
    <?php foreach ($subjects as $s): ?>
      <tr>
        <td><b><?= e($s['code']) ?></b></td>
        <td><?= e($s['name']) ?></td>
        <td>
          <form method="post" class="form-row" style="flex-wrap:nowrap">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="assign">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <select name="faculty_id" aria-label="Professor for <?= e($s['code']) ?>"><?= faculty_options($faculty, $s['faculty_id']) ?></select>
            <button class="btn-light">Save</button>
          </form>
        </td>
        <td class="num"><?= (int)$s['enrolled'] ?></td>
        <td class="num">
          <form method="post" class="inline"
                onsubmit="return confirm('Delete <?= e(addslashes($s['code'])) ?>? All grades in this subject will be deleted too.')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
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
