<?php
// admin/subjects.php
// KM + RM. Si RM yung nag-code ng dropdown ng professor, siya rin yung nag-ayos ng 'No professor' option.
require '../includes/auth.php';
require '../config/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';
  // 0 means 'No professor', so it becomes NULL in the database.
  $fid    = (int)($_POST['faculty_id'] ?? 0) ?: null;

  if ($action === 'add') {
    // RM: pareho ng courses.php yung flow. copy-paste with love
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
    // Reassigning the professor does not touch enrollments or grades.
    $pdo->prepare("UPDATE subjects SET faculty_id = ? WHERE id = ?")
        ->execute([$fid, (int)($_POST['id'] ?? 0)]);
    flash('Professor updated.');
  } elseif ($action === 'delete') {
    // KM: ON DELETE CASCADE sa enrollments, kaya lahat ng grades sa subject na to mawawala rin. Nandun na yung warning sa confirm().
    // IA: tinest ko to sa laptop ko. nawala nga yung grades. ayos. (di ko alam kung ayos)
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

// Builds the <option> list for the professor dropdown. Used in the add form and in every row.
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
    <div class="field" style="max-width:160px"><label for="code">Code</label><input id="code" name="code" placeholder="IT101" autocomplete="off" required></div>
    <div class="field"><label for="name">Subject name</label><input id="name" name="name" placeholder="Intro to Programming" autocomplete="off" required></div>
    <div class="field"><label for="faculty_id">Professor</label>
      <select id="faculty_id" name="faculty_id"><?= faculty_options($faculty) ?></select>
    </div>
    <button>Add subject</button>
  </form>
 <p id="subject-hint" class="muted" style="margin:10px 0 0" aria-live="polite"></p>
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

<?php
// Existing subjects for the type-ahead (JSON_HEX_* keeps the data safe inside a <script> tag).
$suggest = json_encode(
  array_map(function ($s) { return ['code' => $s['code'], 'name' => $s['name']]; }, $subjects),
  JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
) ?: '[]';
?>
<script>
// Type-ahead for the Code and Subject name fields in "Add a subject".
(function () {
  var SUBJECTS = <?= $suggest ?>;
  var codeEl = document.getElementById('code');
  var nameEl = document.getElementById('name');
  var hint   = document.getElementById('subject-hint');
  var boxes  = [];

  // Warn when the typed code already exists
  function updateHint() {
    var c = codeEl.value.trim().toLowerCase();
    var exists = c !== '' && SUBJECTS.some(function (s) { return s.code.toLowerCase() === c; });
    hint.textContent = exists
      ? codeEl.value.trim().toUpperCase() + ' already exists. To change its professor, use the table below.'
      : '';
  }

  function pick(s) {
    codeEl.value = s.code;
    nameEl.value = s.name;
    boxes.forEach(function (b) { b.close(); });
    updateHint();
  }

  function attach(input) {
    var wrap = document.createElement('div');
    var list = document.createElement('ul');
    var shown = [];    // subjects currently listed
    var active = -1;   // highlighted row

    wrap.className = 'suggest-wrap';
    list.className = 'suggest-list';
    list.id = input.id + '-suggest';
    list.setAttribute('role', 'listbox');
    list.hidden = true;
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', list.id);
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    wrap.appendChild(list);

    function mark(i) {
      var items = list.querySelectorAll('.suggest-item');
      active = i;
      items.forEach(function (li, n) { li.classList.toggle('active', n === i); });
      if (items[i]) items[i].scrollIntoView({ block: 'nearest' });
    }

    function close() {
      list.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      active = -1;
    }

    // A subject matches
    function render() {
      var q = input.value.trim().toLowerCase();
      shown = SUBJECTS.filter(function (s) { return (s.code + ' ' + s.name).toLowerCase().indexOf(q) !== -1; });
      list.innerHTML = '';
      shown.forEach(function (s) {
        var li = document.createElement('li');
        var b  = document.createElement('b');
        li.className = 'suggest-item';
        li.setAttribute('role', 'option');
        b.textContent = s.code;
        li.appendChild(b);
        li.appendChild(document.createTextNode(' · ' + s.name));
        li.addEventListener('mousedown', function (ev) { ev.preventDefault(); pick(s); });
        list.appendChild(li);
      });
      if (!shown.length && q !== '') {
        var note = document.createElement('li');
        note.className = 'suggest-note';
        note.textContent = 'No match. Fill in both fields to add it as a new subject.';
        list.appendChild(note);
      }
      list.hidden = list.children.length === 0;
      input.setAttribute('aria-expanded', String(!list.hidden));
      mark(-1);
    }

    input.addEventListener('focus', render);
    input.addEventListener('click', function () { if (list.hidden) render(); });
    input.addEventListener('input', render);
    input.addEventListener('blur', close);
    input.addEventListener('keydown', function (ev) {
      if (ev.key === 'ArrowDown') {
        ev.preventDefault();
        if (list.hidden) render();
        mark(Math.min(active + 1, shown.length - 1));
      } else if (ev.key === 'ArrowUp') {
        ev.preventDefault();
        mark(Math.max(active - 1, -1));
      } else if (ev.key === 'Enter' && !list.hidden && shown[active]) {
        ev.preventDefault();        // pick the row instead of submitting the form
        pick(shown[active]);
      } else if (ev.key === 'Escape') {
        close();
      }
    });

    boxes.push({ close: close });
  }

  attach(codeEl);
  attach(nameEl);
  codeEl.addEventListener('input', updateHint);
})();
</script>
<?php require '../includes/footer.php'; ?>