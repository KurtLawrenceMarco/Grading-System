<?php
require '../includes/auth.php';
require '../config/db.php';
require_role('admin');

// Does this professor teach this subject? (Set on the Subjects page.)
function prof_teaches(PDO $pdo, int $subjectId, int $facultyId): bool {
  $q = $pdo->prepare("SELECT COUNT(*) FROM subject_faculty WHERE subject_id = ? AND faculty_id = ?");
  $q->execute([$subjectId, $facultyId]);
  return (int)$q->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';
  [$sem, $sy] = array_pad(explode('|', $_POST['term'] ?? '', 2), 2, '');
  $sem    = (int)$sem;
  $sy     = trim($sy);
  $tq     = $pdo->prepare("SELECT COUNT(*) FROM terms WHERE semester = ? AND school_year = ?");
  $tq->execute([$sem, $sy]);
  $termOk = (int)$tq->fetchColumn() > 0;

  if ($action === 'add') {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['student_ids'] ?? [])))));
    $sub = (int)($_POST['subject_id'] ?? 0);
    $fid = (int)($_POST['faculty_id'] ?? 0);
    if (!$ids) {
      flash('Tick at least one student.', 'error');
    } elseif (!$sub || !$termOk) {
      flash('Pick a subject and a semester.', 'error');
    } elseif (!prof_teaches($pdo, $sub, $fid)) {
      flash('Pick a professor who teaches that subject. Assign professors on the Subjects page.', 'error');
    } else {
      $st = $pdo->prepare(
        "INSERT IGNORE INTO enrollments (student_id, subject_id, semester, school_year, faculty_id, year_level, section)
         SELECT id, ?, ?, ?, ?, year_level, section FROM users WHERE id = ? AND role = 'student'"
      );
      $pdo->beginTransaction();
      $n = 0;
      foreach ($ids as $stuId) {
        $st->execute([$sub, $sem, $sy, $fid, $stuId]);
        $n += $st->rowCount();
      }
      $pdo->commit();
      $skipped = count($ids) - $n;
      flash(($n > 0 ? "Enrolled $n student" . ($n == 1 ? '' : 's') . '.' : 'Nobody was enrolled.')
            . ($skipped > 0 ? " $skipped already enrolled in that subject for that semester." : ''),
            $n > 0 ? 'ok' : 'error');
    }
  } elseif ($action === 'add_section') {
    $sub = (int)($_POST['subject_id'] ?? 0);
    [$cid, $yr, $sec] = array_pad(explode('|', $_POST['class'] ?? '', 3), 3, '');
    $fid = (int)($_POST['faculty_id'] ?? 0);
    if (!$sub || !(int)$cid || (int)$yr < 1 || $sec === '' || !$termOk) {
      flash('Pick a section, a subject, a semester and a school year.', 'error');
    } elseif (!prof_teaches($pdo, $sub, $fid)) {
      flash('Pick a professor who teaches that subject. Assign professors on the Subjects page.', 'error');
    } else {
      $st = $pdo->prepare(
        "INSERT IGNORE INTO enrollments (student_id, subject_id, semester, school_year, faculty_id, year_level, section)
         SELECT id, ?, ?, ?, ?, year_level, section FROM users WHERE role = 'student' AND course_id = ? AND year_level = ? AND section = ?"
      );
      $st->execute([$sub, $sem, $sy, $fid, (int)$cid, (int)$yr, $sec]);
      $n = $st->rowCount();
      flash($n > 0 ? "Enrolled $n student" . ($n == 1 ? '' : 's') . '.' : 'Everyone in that section is already enrolled.');
    }
  } elseif ($action === 'set_professor') {
    $eid = (int)($_POST['id'] ?? 0);
    $fid = (int)($_POST['faculty_id'] ?? 0);
    $sq  = $pdo->prepare("SELECT subject_id FROM enrollments WHERE id = ?");
    $sq->execute([$eid]);
    $subId = (int)$sq->fetchColumn();
    if (!$subId || !prof_teaches($pdo, $subId, $fid)) {
      flash('Pick a professor who teaches that subject.', 'error');
    } else {
      $pdo->prepare("UPDATE enrollments SET faculty_id = ? WHERE id = ?")->execute([$fid, $eid]);
      flash('Professor updated.');
    }
  } elseif ($action === 'delete') {
    $pdo->prepare("DELETE FROM enrollments WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
    flash('Enrollment removed.');
  }
  redirect('enrollments.php');
}

$students = $pdo->query(
  "SELECT u.id, u.student_id, u.full_name, u.year_level, u.section, c.code AS course_code, c.name AS course_name
   FROM users u LEFT JOIN courses c ON c.id = u.course_id
   WHERE u.role='student' ORDER BY c.code IS NULL, c.code, u.year_level, u.section, u.full_name"
)->fetchAll();
$subjects = $pdo->query("SELECT id, code, name FROM subjects ORDER BY code")->fetchAll();
$classes  = $pdo->query(
  "SELECT u.course_id, c.code, u.year_level, u.section, COUNT(*) AS n
   FROM users u JOIN courses c ON c.id = u.course_id
   WHERE u.role='student' AND u.section IS NOT NULL AND u.year_level IS NOT NULL
   GROUP BY u.course_id, c.code, u.year_level, u.section ORDER BY c.code, u.year_level, u.section"
)->fetchAll();
$rows = $pdo->query(
  "SELECT e.id, e.enrolled_at, e.semester, e.school_year, u.student_id, u.full_name, e.year_level, e.section, c.code AS course_code, s.code, s.name, s.id AS subject_id, e.faculty_id, f.full_name AS professor
   FROM enrollments e
   JOIN users u    ON u.id = e.student_id
   JOIN subjects s ON s.id = e.subject_id
   LEFT JOIN courses c ON c.id = u.course_id
   LEFT JOIN users f   ON f.id = e.faculty_id
   ORDER BY e.school_year DESC, e.semester, s.code, c.code, e.year_level, e.section, u.full_name"
)->fetchAll();

// Professors of each subject: subject id => [ {id, name}, ... ]
$profsBySubject = [];
foreach ($pdo->query("SELECT sf.subject_id, u.id, u.full_name FROM subject_faculty sf JOIN users u ON u.id = sf.faculty_id ORDER BY u.full_name")->fetchAll() as $r) {
  $profsBySubject[(int)$r['subject_id']][] = ['id' => (int)$r['id'], 'name' => $r['full_name']];
}

$terms = $pdo->query("SELECT semester, school_year FROM terms ORDER BY school_year DESC, semester DESC")->fetchAll();

// One "Semester" dropdown with the semesters added on the Semesters page. The newest is selected.
function term_fields(string $id, array $terms): string {
  $o = '';
  foreach ($terms as $t) {
    $o .= '<option value="' . (int)$t['semester'] . '|' . e($t['school_year']) . '">' . e(term_label($t['semester'], $t['school_year'])) . '</option>';
  }
  return '<div class="field" style="max-width:260px"><label for="term_' . $id . '">Semester</label><select id="term_' . $id . '" name="term">' . $o . '</select></div>';
}

// Professor dropdown. The script at the bottom fills it with the professors of the chosen subject.
function prof_field(string $id, string $subjectSelectId): string {
  return '<div class="field" style="max-width:250px"><label for="prof_' . $id . '">Professor</label>'
       . '<select id="prof_' . $id . '" name="faculty_id" data-subject="' . $subjectSelectId . '" required></select></div>';
}

$title = 'Enrollments';
$base  = '../';
require '../includes/header.php';
?>
<style>
  .pick-list{max-height:320px;overflow:auto;border:1px solid var(--input-border);border-radius:var(--radius);background:var(--input-bg)}
  .pick-row{display:flex;align-items:center;gap:10px;padding:8px 12px;border-bottom:1px solid var(--table-line);cursor:pointer}
  .pick-row:last-of-type{border-bottom:0}
  .pick-row:hover{background:var(--table-row-hover)}
  .pick-row input{width:auto;margin:0}
  .pick-row[hidden]{display:none}
  .pick-folder{border-bottom:1px solid var(--table-line)}
  .pick-folder[hidden]{display:none}
  .pick-folder > summary{display:flex;align-items:center;gap:10px;padding:9px 12px;cursor:pointer;font-weight:600;list-style:none}
  .pick-folder > summary::-webkit-details-marker{display:none}
  .pick-folder > summary::before{content:"\25B8";color:var(--folder-arrow);transition:transform .15s}
  .pick-folder[open] > summary::before{transform:rotate(90deg)}
  .pick-folder > summary small{font-weight:400;color:var(--muted-text)}
  .pick-folder-count{margin-left:auto;font-size:.85rem;font-weight:400;color:var(--muted-text)}
  .pick-folder-all{padding:3px 10px;font-size:.82rem}
  .pick-folder .pick-row{padding-left:34px}
  .pick-bar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:10px 0}
  .pick-bar span{color:var(--muted-text);font-size:.9rem;margin-right:auto}
  #pick-submit:disabled{opacity:.5;cursor:not-allowed}
</style>

<div class="page-head"><h1>Enrollments</h1></div>

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
          <option value="<?= (int)$c['course_id'] . '|' . (int)$c['year_level'] . '|' . e($c['section']) ?>"><?= e(class_label($c['code'], $c['year_level'], $c['section'])) ?> (<?= (int)$c['n'] ?> student<?= $c['n'] == 1 ? '' : 's' ?>)</option>
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
    <?= prof_field('sec', 'subject_sec') ?>
    <?= term_fields('sec', $terms) ?>
    <button>Enroll section</button>
  </form>
</div>

<div class="panel">
  <h2>Enroll selected students</h2>
  <form method="post" id="pick-form" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="form-row">
      <div class="field"><label for="subject_id">Subject</label>
        <select id="subject_id" name="subject_id" required>
          <?php foreach ($subjects as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= e($s['code'] . ' · ' . $s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?= prof_field('one', 'subject_id') ?>
      <?= term_fields('one', $terms) ?>
    </div>

    <div class="field" style="margin-top:16px"><label for="pick-search">Students</label>
      <input id="pick-search" type="search" placeholder="Search by name, student ID, course or section" autocomplete="off">
    </div>
    <div class="pick-bar">
      <span id="pick-count">0 selected</span>
      <button type="button" class="btn-light" id="pick-all">Select all shown</button>
      <button type="button" class="btn-light" id="pick-none">Clear</button>
    </div>
    <?php
      // Group the students by course, one folder each.
      $byCourse = [];
      foreach ($students as $st0) {
        $k = $st0['course_code'] ?? '';
        $byCourse[$k]['name']   = $st0['course_name'] ?? '';
        $byCourse[$k]['rows'][] = $st0;
      }
    ?>
    <div class="pick-list" id="pick-list">
      <?php foreach ($byCourse as $code => $grp): ?>
        <details class="pick-folder">
          <summary>
            <span>📁 <?= $code === '' ? 'No course' : e($code) ?></span>
            <small><?= e($grp['name']) ?></small>
            <span class="pick-folder-count"><span class="pick-sel">0</span> of <?= count($grp['rows']) ?> selected</span>
            <button type="button" class="btn-light pick-folder-all" title="Tick everyone in this folder">Select all</button>
          </summary>
          <?php foreach ($grp['rows'] as $s):
            $where = $s['course_code'] ? class_label($s['course_code'], $s['year_level'], $s['section']) : 'No course'; ?>
            <label class="pick-row" data-search="<?= e(mb_strtolower($s['full_name'] . ' ' . $s['student_id'] . ' ' . $where)) ?>">
              <input type="checkbox" name="student_ids[]" value="<?= (int)$s['id'] ?>">
              <span><b><?= e($s['full_name']) ?></b> <small><?= e(($s['student_id'] ?? '—') . ' · ' . $where) ?></small></span>
            </label>
          <?php endforeach; ?>
        </details>
      <?php endforeach; ?>
      <p class="empty muted" id="pick-empty" hidden style="padding:18px;text-align:center;margin:0">No students match your search.</p>
    </div>
    <p style="margin:16px 0 0"><button id="pick-submit" disabled>Enroll selected students</button></p>
  </form>
</div>
<?php endif; ?>

<div class="panel">
  <h2>Current enrollments</h2>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Term</th><th>Subject</th><th>Student ID</th><th>Student</th><th>Course and section</th><th>Professor</th><th>Enrolled</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="8" class="empty">Nobody is enrolled yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e(term_label($r['semester'], $r['school_year'])) ?></td>
        <td><b><?= e($r['code']) ?></b> <small><?= e($r['name']) ?></small></td>
        <td><?= e($r['student_id']) ?></td>
        <td><?= e($r['full_name']) ?></td>
        <td><?= e(class_label($r['course_code'], $r['year_level'], $r['section']) ?: '—') ?></td>
        <td>
          <?php $plist = $profsBySubject[(int)$r['subject_id']] ?? []; ?>
          <form method="post" class="form-row" style="flex-wrap:nowrap;gap:6px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="set_professor">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <select name="faculty_id" style="min-width:150px" aria-label="Professor for <?= e($r['full_name'] . ' in ' . $r['code']) ?>">
              <?php if (!$r['faculty_id']): ?>
                <option value="">No professor</option>
              <?php elseif (!in_array((int)$r['faculty_id'], array_column($plist, 'id'), true)): ?>
                <option value="<?= (int)$r['faculty_id'] ?>" selected><?= e($r['professor']) ?></option>
              <?php endif; ?>
              <?php foreach ($plist as $p): ?>
                <option value="<?= $p['id'] ?>"<?= (int)$r['faculty_id'] === $p['id'] ? ' selected' : '' ?>><?= e($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn-light">Save</button>
          </form>
        </td>
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
<script>
// Each Professor dropdown lists only the professors of the subject picked next to it.
const PROFS = <?= json_encode($profsBySubject, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
document.querySelectorAll('select[data-subject]').forEach(sel => {
  const subject = document.getElementById(sel.dataset.subject);
  if (!subject) return;
  const fill = () => {
    const list = PROFS[subject.value] || [];
    sel.innerHTML = '';
    if (!list.length) { sel.add(new Option('No professor yet. Assign one on the Subjects page.', '')); return; }
    list.forEach(p => sel.add(new Option(p.name, p.id)));
  };
  subject.addEventListener('change', fill);
  fill();
});
</script>
<script>
// Course folders + search + tick boxes for "Enroll selected students".
(function () {
  const list = document.getElementById('pick-list');
  if (!list) return;
  const search  = document.getElementById('pick-search');
  const folders = [...list.querySelectorAll('.pick-folder')];
  const rows    = [...list.querySelectorAll('.pick-row')];
  const count   = document.getElementById('pick-count');
  const submit  = document.getElementById('pick-submit');
  const empty   = document.getElementById('pick-empty');
  const box     = r => r.querySelector('input');

  function updateCount() {
    count.textContent = rows.filter(r => box(r).checked).length + ' selected';
    submit.disabled = !rows.some(r => box(r).checked);
    folders.forEach(f => {
      f.querySelector('.pick-sel').textContent = [...f.querySelectorAll('.pick-row')].filter(r => box(r).checked).length;
    });
  }

  // Typing in the search box hides other folders and opens the ones with matches.
  function filter() {
    const words = search.value.toLowerCase().split(/\s+/).filter(Boolean);
    folders.forEach(f => {
      let shown = 0;
      f.querySelectorAll('.pick-row').forEach(r => {
        const ok = words.every(w => r.dataset.search.includes(w));
        r.hidden = !ok;
        if (ok) shown++;
      });
      f.hidden = shown === 0;
      f.open = words.length ? shown > 0 : !!f.querySelector('input:checked');
    });
    empty.hidden = folders.some(f => !f.hidden);
  }

  search.addEventListener('input', filter);
  list.addEventListener('change', updateCount);

  // "Select all" on a folder: tick everyone in it who matches the search.
  list.addEventListener('click', ev => {
    const btn = ev.target.closest('.pick-folder-all');
    if (!btn) return;
    ev.preventDefault();                      // do not open or close the folder
    const f = btn.closest('.pick-folder');
    f.querySelectorAll('.pick-row').forEach(r => { if (!r.hidden) box(r).checked = true; });
    updateCount();
  });

  // "Select all shown": everyone you can see in the open folders.
  document.getElementById('pick-all').addEventListener('click', () => {
    folders.forEach(f => { if (f.open && !f.hidden) f.querySelectorAll('.pick-row').forEach(r => { if (!r.hidden) box(r).checked = true; }); });
    updateCount();
  });
  document.getElementById('pick-none').addEventListener('click', () => {
    rows.forEach(r => box(r).checked = false);
    updateCount();
  });

  // Pressing Enter in the search box should not submit the form.
  search.addEventListener('keydown', ev => { if (ev.key === 'Enter') ev.preventDefault(); });
  updateCount();
})();
</script>
<?php require '../includes/footer.php'; ?>
