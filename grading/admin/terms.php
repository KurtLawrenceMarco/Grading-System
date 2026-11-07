<?php
require '../includes/auth.php';
require '../config/db.php';
require_role('admin');

function newest_term(PDO $pdo) {
  return $pdo->query("SELECT semester, school_year FROM terms ORDER BY school_year DESC, semester DESC LIMIT 1")->fetch();
}

// The semester that comes after the newest one:
// 1st -> 2nd (same school year), 2nd or Summer -> 1st of the next school year.
function next_term($newest): array {
  if (!$newest) return [1, current_school_year()];
  if ((int)$newest['semester'] === 1) return [2, $newest['school_year']];
  $start = (int)substr($newest['school_year'], 0, 4) + 1;
  return [1, $start . '-' . ($start + 1)];
}

// A new 1st semester (when an earlier 1st semester already exists) starts a new school year:
// every student whose year level is for an older school year moves up one year level.
// Students already in their course's last year stay where they are.
function promote_students(PDO $pdo, string $sy): int {
  $q = $pdo->prepare("SELECT COUNT(*) FROM terms WHERE semester = 1 AND school_year < ?");
  $q->execute([$sy]);
  if ((int)$q->fetchColumn() === 0) return 0;
  $st = $pdo->prepare(
    "UPDATE users u LEFT JOIN courses c ON c.id = u.course_id
     SET u.year_level = u.year_level + 1, u.level_school_year = ?
     WHERE u.role = 'student' AND u.year_level IS NOT NULL
       AND (u.level_school_year IS NULL OR u.level_school_year < ?)
       AND u.year_level < COALESCE(c.years, 4)"
  );
  $st->execute([$sy, $sy]);
  return $st->rowCount();
}

function after_term_insert(PDO $pdo, bool $inserted, int $sem, string $sy): void {
  if (!$inserted) { flash('That semester already exists.', 'error'); return; }
  $msg = 'Added ' . term_label($sem, $sy) . '. It is ready to use.';
  if ($sem === 1) {
    $n = promote_students($pdo, $sy);
    if ($n > 0) $msg .= " $n student" . ($n == 1 ? '' : 's') . ' moved up one year level.';
  }
  flash($msg);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  if ($action === 'add_next') {
    [$sem, $sy] = next_term(newest_term($pdo));
    $st = $pdo->prepare("INSERT IGNORE INTO terms (semester, school_year) VALUES (?, ?)");
    $st->execute([$sem, $sy]);
    after_term_insert($pdo, $st->rowCount() > 0, $sem, $sy);
  } elseif ($action === 'add') {
    $sem = (int)($_POST['semester'] ?? 0);
    $sy  = trim($_POST['school_year'] ?? '');
    if (!isset(SEMESTERS[$sem]) || !valid_school_year($sy)) {
      flash('Pick a semester and a school year.', 'error');
    } else {
      $st = $pdo->prepare("INSERT IGNORE INTO terms (semester, school_year) VALUES (?, ?)");
      $st->execute([$sem, $sy]);
      after_term_insert($pdo, $st->rowCount() > 0, $sem, $sy);
    }
  } elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
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

$terms = $pdo->query(
  "SELECT t.*, (SELECT COUNT(*) FROM enrollments e WHERE e.semester = t.semester AND e.school_year = t.school_year) AS enrolled
   FROM terms t ORDER BY t.school_year DESC, t.semester DESC"
)->fetchAll();
[$nsem, $nsy] = next_term(newest_term($pdo));

$title = 'Semesters';
$base  = '../';
require '../includes/header.php';
?>
<style>
  /* School year picker: a short box that scrolls, instead of one very tall list. */
  .sy-pick{position:relative}
  .sy-btn,.sy-btn:hover{width:100%;display:flex;justify-content:space-between;align-items:center;gap:8px;text-align:left;
    font-weight:400;background:var(--input-bg);color:var(--input-text);border:1px solid var(--input-border)}
  .sy-list{position:absolute;z-index:20;left:0;right:0;top:100%;margin:4px 0 0;padding:4px 0;list-style:none;
    max-height:220px;overflow-y:auto;background:var(--input-bg);border:1px solid var(--input-border);
    border-radius:var(--radius);box-shadow:0 6px 18px rgba(0,0,0,.18)}
  .sy-list[hidden]{display:none}
  .sy-list li{padding:7px 12px;cursor:pointer;color:var(--input-text)}
  .sy-list li:hover,.sy-list li:focus{background:var(--table-row-hover);outline:0}
  .sy-list li[aria-selected="true"]{font-weight:700;color:var(--link)}
</style>

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
    <div class="field"><label for="sy-btn">School year</label>
      <div class="sy-pick" id="sy-pick">
        <input type="hidden" name="school_year" id="sy-value" value="<?= e(current_school_year()) ?>">
        <button type="button" class="sy-btn" id="sy-btn" aria-haspopup="listbox" aria-expanded="false">
          <span id="sy-label"><?= e(current_school_year()) ?></span><span aria-hidden="true">&#9662;</span>
        </button>
        <ul class="sy-list" id="sy-list" role="listbox" hidden>
          <?php foreach (school_year_options() as $y): ?>
            <li role="option" tabindex="-1" data-value="<?= e($y) ?>"<?= $y === current_school_year() ? ' aria-selected="true"' : '' ?>><?= e($y) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
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
<script>
// School year picker (scrolling list).
(function () {
  const pick = document.getElementById('sy-pick');
  if (!pick) return;
  const btn = document.getElementById('sy-btn'), list = document.getElementById('sy-list');
  const val = document.getElementById('sy-value'), label = document.getElementById('sy-label');
  const opts = [...list.querySelectorAll('li')];

  function open() {
    list.hidden = false;
    btn.setAttribute('aria-expanded', 'true');
    const sel = list.querySelector('[aria-selected="true"]');
    if (sel) list.scrollTop = sel.offsetTop - list.clientHeight / 2 + sel.offsetHeight / 2;   // start at the chosen year
  }
  function close() { list.hidden = true; btn.setAttribute('aria-expanded', 'false'); }
  function choose(li) {
    opts.forEach(o => o.removeAttribute('aria-selected'));
    li.setAttribute('aria-selected', 'true');
    val.value = li.dataset.value;
    label.textContent = li.dataset.value;
    close();
    btn.focus();
  }

  btn.addEventListener('click', () => (list.hidden ? open() : close()));
  btn.addEventListener('keydown', ev => {
    if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
      ev.preventDefault();
      open();
      (list.querySelector('[aria-selected="true"]') || opts[0]).focus();
    }
  });
  list.addEventListener('click', ev => { const li = ev.target.closest('li'); if (li) choose(li); });
  list.addEventListener('keydown', ev => {
    const i = opts.indexOf(document.activeElement);
    if (ev.key === 'ArrowDown') { ev.preventDefault(); (opts[i + 1] || opts[i]).focus(); }
    else if (ev.key === 'ArrowUp') { ev.preventDefault(); (opts[i - 1] || opts[i]).focus(); }
    else if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); if (i >= 0) choose(opts[i]); }
    else if (ev.key === 'Escape') { close(); btn.focus(); }
  });
  document.addEventListener('click', ev => { if (!pick.contains(ev.target)) close(); });
})();
</script>
<?php require '../includes/footer.php'; ?>
