<?php
require '../includes/auth.php';
require '../config/db.php';
require_role('admin');

$faculty    = $pdo->query("SELECT id, full_name FROM users WHERE role='faculty' ORDER BY full_name")->fetchAll();
$facultyIds = array_map('intval', array_column($faculty, 'id'));

// The professors ticked in a form, limited to real faculty accounts.
function ticked_faculty(array $valid): array {
  $ids = array_map('intval', (array)($_POST['faculty_ids'] ?? []));
  return array_values(array_unique(array_intersect($ids, $valid)));
}

// Replaces the professor list of one subject.
function save_professors(PDO $pdo, int $subjectId, array $ids): void {
  $pdo->prepare("DELETE FROM subject_faculty WHERE subject_id = ?")->execute([$subjectId]);
  $ins = $pdo->prepare("INSERT INTO subject_faculty (subject_id, faculty_id) VALUES (?, ?)");
  foreach ($ids as $fid) $ins->execute([$subjectId, $fid]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  if ($action === 'add') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $name = trim($_POST['name'] ?? '');
    $units = round((float)($_POST['units'] ?? 3), 1);
    if ($code === '' || $name === '') {
      flash('Enter a subject code and name.', 'error');
    } elseif ($units < 0.5 || $units > 9.5) {
      flash('Units must be from 0.5 to 9.5.', 'error');
    } else {
      try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO subjects (code, name, units) VALUES (?,?,?)")->execute([$code, $name, $units]);
        save_professors($pdo, (int)$pdo->lastInsertId(), ticked_faculty($facultyIds));
        $pdo->commit();
        flash("Added $code.");
      } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('Could not add subject. That code already exists.', 'error');
      }
    }
  } elseif ($action === 'assign') {
    $pdo->beginTransaction();
    save_professors($pdo, (int)($_POST['id'] ?? 0), ticked_faculty($facultyIds));
    $pdo->commit();
    flash('Professors updated. Students already enrolled keep the professor they were enrolled with.');
  } elseif ($action === 'set_units') {
    $units = round((float)($_POST['units'] ?? 0), 1);
    if ($units < 0.5 || $units > 9.5) {
      flash('Units must be from 0.5 to 9.5.', 'error');
    } else {
      $pdo->prepare("UPDATE subjects SET units = ? WHERE id = ?")->execute([$units, (int)($_POST['id'] ?? 0)]);
      flash('Units updated.');
    }
  } elseif ($action === 'delete') {
    $pdo->prepare("DELETE FROM subjects WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
    flash('Subject deleted.');
  }
  redirect('subjects.php');
}

$subjects = $pdo->query(
  "SELECT s.id, s.code, s.name, s.units, (SELECT COUNT(*) FROM enrollments e WHERE e.subject_id = s.id) AS enrolled
   FROM subjects s ORDER BY s.code"
)->fetchAll();

$assigned = [];   // subject id => list of professor ids
foreach ($pdo->query("SELECT subject_id, faculty_id FROM subject_faculty")->fetchAll() as $r) {
  $assigned[(int)$r['subject_id']][] = (int)$r['faculty_id'];
}
$facultyName = array_column($faculty, 'full_name', 'id');

// A row of tick boxes, one per professor.
function faculty_checks(array $faculty, array $selected = []): string {
  if (!$faculty) return '<small>No faculty accounts yet. Add one on the Users page.</small>';
  $o = '';
  foreach ($faculty as $f) {
    $chk = in_array((int)$f['id'], $selected, true) ? ' checked' : '';
    $o  .= '<label class="check"><input type="checkbox" name="faculty_ids[]" value="' . (int)$f['id'] . '"' . $chk . '> ' . e($f['full_name']) . '</label>';
  }
  return $o;
}

$title = 'Subjects';
$base  = '../';
require '../includes/header.php';
?>
<style>
  .checks{display:flex;flex-wrap:wrap;gap:6px 18px}
  .field .check{display:flex;align-items:center;gap:6px;font-size:.92rem;font-weight:400;color:var(--page-text)}
  .check input{width:auto}
  .checks .check{display:flex;align-items:center;gap:6px;font-size:.92rem;color:var(--page-text)}
  .prof-edit summary{cursor:pointer;font-size:.82rem;color:var(--link)}
</style>

<div class="page-head"><h1>Subjects</h1></div>

<div class="panel">
  <h2>Add a subject</h2>
  <form method="post" class="form-row">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="field" style="max-width:160px"><label for="code">Code</label><input id="code" name="code" placeholder="IT101" required></div>
    <div class="field"><label for="name">Subject name</label><input id="name" name="name" placeholder="Intro to Programming" required></div>
    <div class="field" style="max-width:110px"><label for="units">Units</label><input id="units" type="number" name="units" step="0.5" min="0.5" max="9.5" value="3" required></div>
    <div class="field" style="flex-basis:100%"><label>Professors (tick everyone who teaches it)</label>
      <div class="checks"><?= faculty_checks($faculty) ?></div>
    </div>
    <button>Add subject</button>
  </form>
</div>

<div class="panel">
  <h2>All subjects</h2>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Code</th><th>Name</th><th class="num">Units</th><th>Professors</th><th class="num">Students</th><th></th></tr></thead>
    <tbody>
    <?php if (!$subjects): ?>
      <tr><td colspan="6" class="empty">No subjects yet. Add one above.</td></tr>
    <?php endif; ?>
    <?php foreach ($subjects as $s):
      $ids   = $assigned[(int)$s['id']] ?? [];
      $names = array_map(fn($id) => $facultyName[$id] ?? '', $ids); ?>
      <tr>
        <td><b><?= e($s['code']) ?></b></td>
        <td><?= e($s['name']) ?></td>
        <td class="num">
          <form method="post" class="form-row" style="flex-wrap:nowrap;gap:6px;justify-content:flex-end">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="set_units">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="btn-light">Save</button>
            <input type="number" name="units" step="0.5" min="0.5" max="9.5" value="<?= e(fmt_units($s['units'])) ?>" style="width:75px" aria-label="Units of <?= e($s['code']) ?>">
          </form>
        </td>
        <td>
          <?= $names ? e(implode(', ', $names)) : '<small>No professor yet</small>' ?>
          <details class="prof-edit">
            <summary>Change professors</summary>
            <form method="post" style="margin-top:8px">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="assign">
              <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
              <div class="checks"><?= faculty_checks($faculty, $ids) ?></div>
              <p style="margin:10px 0 0"><button class="btn-light">Save</button></p>
            </form>
          </details>
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
