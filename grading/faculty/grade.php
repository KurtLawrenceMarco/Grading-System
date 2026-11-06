<?php
// faculty/grade.php  -  the grade sheet
// Main: KM | RM: server-side validation | MC: JS live preview | IA, AP: tested
require '../includes/auth.php';
require '../config/db.php';
require_role('faculty');

// Subject ID from the URL. Cast to int so nothing weird can get in.
$sid = (int)($_GET['subject'] ?? 0);

// Faculty can only open their own subjects.
$s = $pdo->prepare("SELECT id, code, name FROM subjects WHERE id = ? AND faculty_id = ?");
$s->execute([$sid, $_SESSION['user_id']]);
$subject = $s->fetch();
if (!$subject) {
  redirect('dashboard.php');
}

// Folder filter coming from the dashboard (course + section). No filter = every enrolled student.
// KM: filter by course and section galing sa dashboard folder. Pag walang filter, lahat ng enrolled ipapakita.
$filtered = isset($_GET['course']) || isset($_GET['section']);
$fc = null;
$fs = null;
$qs = 'subject=' . $sid;

// Semester + school year coming from the dashboard.
$sem = (int)($_GET['semester'] ?? 0);
$sy  = (string)($_GET['school_year'] ?? '');
// Semester + school year filter. valid_school_year() makes sure nobody types junk in the URL.
$termFiltered = isset(SEMESTERS[$sem]) && valid_school_year($sy);
if ($termFiltered) {
  $qs .= '&semester=' . $sem . '&school_year=' . urlencode($sy);
}
if ($filtered) {
  $fc = (($_GET['course'] ?? '') !== '') ? (int)$_GET['course'] : null;
  $fs = (($_GET['section'] ?? '') !== '') ? (string)$_GET['section'] : null;
  $qs .= '&course=' . ($fc ?? '') . '&section=' . urlencode($fs ?? '');
}

$folderLabel = '';
if ($filtered) {
  $code = 'No course';
  if ($fc) {
    $cq = $pdo->prepare("SELECT code FROM courses WHERE id = ?");
    $cq->execute([$fc]);
    $code = $cq->fetchColumn() ?: 'No course';
  }
  $folderLabel = $code . ' · ' . ($fs === null ? 'No section' : 'Section ' . $fs);
}

if ($termFiltered) {
  $folderLabel = term_label($sem, $sy) . ($folderLabel ? ' · ' . $folderLabel : '');
}

// Saving the grade sheet starts here.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $clean  = [];
  // RM: kapag may isang mali, WALANG masi-save. Hindi pwedeng kalahati lang.
  $errors = 0;

  foreach (($_POST['grade'] ?? []) as $eid => $terms) {
    foreach (array_keys(TERMS) as $t) {
      $v = trim($terms[$t] ?? '');
      if ($v === '') {
        $clean[(int)$eid][$t] = null;
      // Server-side validation. The HTML min/max can be bypassed by anyone, so we check again here (1.00 to 5.00).
      } elseif (is_numeric($v) && $v >= MIN_GRADE && $v <= MAX_GRADE) {
        $clean[(int)$eid][$t] = round((float)$v, 2);
      } else {
        $errors++;
      }
    }
  }

  // var_dump($clean); exit;   // AP: debug. nandito lang para makita kung ano yung na-submit
  if ($errors) {
    flash('Grades must be numbers from 1.00 to 5.00. Nothing was saved.', 'error');
  } else {
    // SECURITY: AND subject_id=? in the WHERE clause keeps a professor from editing grades of another subject by changing the enrollment ID in the form.
    $u = $pdo->prepare("UPDATE enrollments SET prelim=?, midterm=?, semifinal=?, finals=?
                        WHERE id=? AND subject_id=?");
    // All rows are saved together. If one fails, none are saved.
    $pdo->beginTransaction();
    foreach ($clean as $eid => $g) {
      $u->execute([$g['prelim'], $g['midterm'], $g['semifinal'], $g['finals'], $eid, $sid]);
    }
    $pdo->commit();
    flash('Grades saved.');
  }
  redirect('grade.php?' . $qs);
}

// echo $sql;   // IA: dito ako nag-debug nung hindi lumalabas yung students sa laptop ko. nakalimutan ko lang pala mag-enroll lol
$sql = "SELECT e.id, u.student_id, u.full_name, u.section, c.code AS course_code,
               e.prelim, e.midterm, e.semifinal, e.finals, e.updated_at
        FROM enrollments e
        JOIN users u ON u.id = e.student_id
        LEFT JOIN courses c ON c.id = u.course_id
        WHERE e.subject_id = ?";
$params = [$sid];
if ($filtered) {
  // <=> is the NULL-safe equals. It lets us match students who have no course or no section (NULL = NULL is normally false in SQL).
  $sql .= " AND u.course_id <=> ? AND u.section <=> ?";
  $params[] = $fc;
  $params[] = $fs;
}
if ($termFiltered) {
  $sql .= " AND e.semester = ? AND e.school_year = ?";
  $params[] = $sem;
  $params[] = $sy;
}
$sql .= " ORDER BY c.code, u.section, u.full_name";
$rows = $pdo->prepare($sql);
$rows->execute($params);
$rows = $rows->fetchAll();

$title = $subject['code'];
$base  = '../';
require '../includes/header.php';
?>
<p><a href="dashboard.php<?= $termFiltered ? '?' . e(http_build_query(['semester' => $sem, 'school_year' => $sy])) : '' ?>">&larr; Back to my folders</a></p>
<div class="page-head">
  <div>
    <h1><?= e($subject['code']) ?> · <?= e($subject['name']) ?></h1>
    <?php if ($folderLabel): ?><p><b><?= e($folderLabel) ?></b></p><?php endif; ?>
    <p class="muted">Grades run from 1.00 (highest) to 5.00. The lowest passing average is <?= number_format(PASSING_GRADE, 2) ?>; anything above that fails. Leave a term blank if it is not graded yet.</p>
  </div>
</div>

<form method="post" class="panel">
  <?= csrf_field() ?>
  <div class="table-wrap">
  <table id="sheet">
    <thead>
      <tr>
        <th>Student ID</th><th>Name</th><th>Course and section</th>
        <?php foreach (TERMS as $label): ?><th class="num"><?= e($label) ?></th><?php endforeach; ?>
        <th class="num">Average</th><th>Status</th><th>Last updated</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="10" class="empty">No students are enrolled here yet. An admin can enroll them.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $r): [$avg, $status] = grade_summary($r); ?>
      <tr>
        <td><?= e($r['student_id']) ?></td>
        <td><?= e($r['full_name']) ?></td>
        <td><?= $r['course_code'] ? e($r['course_code'] . ' ' . $r['section']) : '—' ?></td>
        <?php foreach (array_keys(TERMS) as $t): ?>
          <td class="num">
            <input class="grade-input" type="number" min="1" max="5" step="0.01"
                   name="grade[<?= (int)$r['id'] ?>][<?= $t ?>]"
                   value="<?= $r[$t] === null ? '' : e(number_format((float)$r[$t], 2, '.', '')) ?>"
                   aria-label="<?= e(TERMS[$t] . ' grade for ' . $r['full_name']) ?>">
          </td>
        <?php endforeach; ?>
        <td class="num avg"><?= $avg === null ? '—' : number_format($avg, 2) ?></td>
        <td><?= status_badge($status) ?></td>
        <td><small><?= e(date('M j, Y', strtotime($r['updated_at']))) ?></small></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if ($rows): ?><p style="margin:18px 0 0"><button>Save grades</button></p><?php endif; ?>
</form>

<script>
// Live preview of average + status while typing (the server recalculates on save).
// MC: live preview ng average at status habang nagta-type. Sa server pa rin ang final computation. Gawa ko to, ang cool diba. (RM: oo)
const PASSING = <?= (float)PASSING_GRADE ?>;
document.querySelectorAll('#sheet tbody tr').forEach(tr => {
  const inputs = tr.querySelectorAll('.grade-input');
  if (!inputs.length) return;
  const update = () => {
    const vals = [...inputs].map(i => i.value.trim());
    const avgCell = tr.querySelector('.avg'), badge = tr.querySelector('.badge');
    // console.log(vals);   // MC: debug sa browser. F12 lang
    if (vals.some(v => v === '' || isNaN(v))) {
      avgCell.textContent = '—'; badge.className = 'badge incomplete'; badge.textContent = 'Incomplete'; return;
    }
    const avg = Math.round(vals.reduce((a, v) => a + parseFloat(v), 0) / vals.length * 100) / 100;
    avgCell.textContent = avg.toFixed(2);
    const ok = avg <= PASSING;
    badge.className = 'badge ' + (ok ? 'passed' : 'failed');
    badge.textContent = ok ? 'Passed' : 'Failed';
  };
  inputs.forEach(i => i.addEventListener('input', update));
});
</script>
<?php require '../includes/footer.php'; ?>
