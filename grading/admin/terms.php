<?php
// admin/terms.php  -  semesters
// Main: KM | MC: next_term() logic (may bug dati, inayos ni KM)
require '../includes/auth.php';
require '../config/db.php';
require_role('admin');

// The latest semester = highest school year, then highest semester number.
function newest_term(PDO $pdo) {
  return $pdo->query("SELECT semester, school_year FROM terms ORDER BY school_year DESC, semester DESC LIMIT 1")->fetch();
}

// The semester that comes after the newest one:
// 1st -> 2nd (same school year), 2nd or Summer -> 1st of the next school year.
// MC: gawa ko to. tapos nag-error nung Summer. KM nag-ayos.
// KM: oo kasi 'Summer' = 3 at hindi siya 1st, kaya tama na yung 2nd or Summer = 1st ng susunod na school year.
function next_term($newest): array {
  // Empty table? Start with 1st Semester of the current school year.
  if (!$newest) return [1, current_school_year()];
  if ((int)$newest['semester'] === 1) return [2, $newest['school_year']];
  $start = (int)substr($newest['school_year'], 0, 4) + 1;
  return [1, $start . '-' . ($start + 1)];
}

// RM: simula ng lahat ng pag-submit ng form dito.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  if ($action === 'add_next') {
    [$sem, $sy] = next_term(newest_term($pdo));
    // INSERT IGNORE + rowCount(): if the semester already exists nothing is inserted and rowCount() is 0, so we show the 'already exists' message.
    $st = $pdo->prepare("INSERT IGNORE INTO terms (semester, school_year) VALUES (?, ?)");
    $st->execute([$sem, $sy]);
    flash($st->rowCount() ? 'Added ' . term_label($sem, $sy) . '. It is ready to use.' : 'That semester already exists.', $st->rowCount() ? 'ok' : 'error');
  } elseif ($action === 'add') {
    $sem = (int)($_POST['semester'] ?? 0);
    $sy  = trim($_POST['school_year'] ?? '');
    if (!isset(SEMESTERS[$sem]) || !valid_school_year($sy)) {
      flash('Pick a semester and a school year.', 'error');
    } else {
      $st = $pdo->prepare("INSERT IGNORE INTO terms (semester, school_year) VALUES (?, ?)");
      $st->execute([$sem, $sy]);
      flash($st->rowCount() ? 'Added ' . term_label($sem, $sy) . '. It is ready to use.' : 'That semester already exists.', $st->rowCount() ? 'ok' : 'error');
    }
  } elseif ($action === 'delete') {
    // A semester that still has enrollments cannot be deleted, otherwise grades would be orphaned.
    $id = (int)($_POST['id'] ?? 0);
    // IA: nag-try akong burahin yung may enrollments sa laptop ko, hindi pwede. okay. gumagana yung proteksyon
    $n = $pdo->prepare(
      "SELECT COUNT(*) FROM enrollments e JOIN terms t ON t.semester = e.semester AND t.school_year = e.school_year WHERE t.id = ?"
    );
    $n->execute([$id]);
    if ((int)$n->fetchColumn() > 0) {
      flash('Students are enrolled in that semester, so it cannot be deleted.', 'error');
    } else {
      $pdo->prepare("DELETE FROM terms WHERE id = ?")->execute([$id]);
      flash('Semester deleted.');
    }
  }
  redirect('terms.php');
}

// RM: yung 'enrolled' dito yung bilang na nakikita sa table. subquery ulit, gusto ko na to.
$terms = $pdo->query(
  "SELECT t.*, (SELECT COUNT(*) FROM enrollments e WHERE e.semester = t.semester AND e.school_year = t.school_year) AS enrolled
   FROM terms t ORDER BY t.school_year DESC, t.semester DESC"
)->fetchAll();
[$nsem, $nsy] = next_term(newest_term($pdo));

$title = 'Semesters';
$base  = '../';
require '../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>Semesters</h1>
    <p class="muted">A semester you add here shows up right away as a button on every faculty dashboard and in the Enrollments form.</p>
  </div>
</div>

<div class="panel">
  <h2>Start the next semester</h2>
  <p class="muted">One click. This adds <b><?= e(term_label($nsem, $nsy)) ?></b>.</p>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_next">
    <button>Add <?= e(term_label($nsem, $nsy)) ?></button>
  </form>
</div>

<div class="panel">
  <h2>Add a semester manually</h2>
  <form method="post" class="form-row">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="field"><label for="semester">Semester</label>
      <select id="semester" name="semester">
        <?php foreach (SEMESTERS as $n => $label): ?><option value="<?= $n ?>"><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label for="school_year">School year</label>
      <select id="school_year" name="school_year">
        <?php foreach (school_year_options() as $y): ?><option value="<?= e($y) ?>"<?= $y === current_school_year() ? ' selected' : '' ?>><?= e($y) ?></option><?php endforeach; ?>
      </select>
    </div>
    <button>Add semester</button>
  </form>
</div>

<div class="panel">
  <h2>All semesters</h2>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Semester</th><th class="num">Enrollments</th><th></th></tr></thead>
    <tbody>
    <?php if (!$terms): ?>
      <tr><td colspan="3" class="empty">No semesters yet. Use the button above to add the first one.</td></tr>
    <?php endif; ?>
    <?php foreach ($terms as $t): ?>
      <tr>
        <td><b><?= e(term_label($t['semester'], $t['school_year'])) ?></b></td>
        <td class="num"><?= (int)$t['enrolled'] ?></td>
        <td class="num">
          <?php if ((int)$t['enrolled'] === 0): ?>
          <form method="post" class="inline" onsubmit="return confirm('Delete this semester?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <button class="btn-danger">Delete</button>
          </form>
          <?php else: ?><small>In use</small><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php require '../includes/footer.php'; ?>
