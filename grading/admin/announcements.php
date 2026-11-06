<?php
// admin/announcements.php
// Main: KM | Review/bugfix: RM | Style: NC
require '../includes/auth.php';
require '../includes/announce_lib.php';
require '../config/db.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Every POST is checked for a CSRF token before anything else happens.
  csrf_check();
  $action = $_POST['action'] ?? '';

  if ($action === 'add') {
    // KM: mb_substr hindi substr, para hindi mapunit yung mga Filipino characters (ñ, etc.). Limit 150 title, 2000 message para di maghulog ng nobela ang admin.
    $title    = mb_substr(trim($_POST['title'] ?? ''), 0, 150);
    $message  = mb_substr(trim($_POST['message'] ?? ''), 0, 2000);
    $audience = $_POST['audience'] ?? '';
    $type     = $_POST['type'] ?? '';
    $until    = trim($_POST['expires_on'] ?? '');
    // Optional expiry date. DateTime::createFromFormat plus the format() comparison rejects fake dates like 2026-02-31.
    $dt       = $until === '' ? null : DateTime::createFromFormat('Y-m-d', $until);

    if ($title === '' || $message === '') {
      flash('Enter a title and a message.', 'error');
    } elseif (!isset(ANNOUNCE_AUDIENCES[$audience]) || !isset(ANNOUNCE_TYPES[$type])) {
      // IA: Paano ba toh haha bat kelangan ko pa mag code rin
      flash('Pick who it is for and what kind of announcement it is.', 'error');
    } elseif ($until !== '' && (!$dt || $dt->format('Y-m-d') !== $until || $until < date('Y-m-d'))) {
      flash('"Show until" must be today or a later date.', 'error');
    } else {
      // RM: INSERT. yung created_by = kung sino nag-login na admin para may 'Posted by' sa baba ng announcement.
      $pdo->prepare(
        "INSERT INTO announcements (title, message, audience, type, expires_on, created_by) VALUES (?,?,?,?,?,?)"
      )->execute([$title, $message, $audience, $type, $until === '' ? null : $until, $_SESSION['user_id']]);
      flash('Announcement posted. ' . ANNOUNCE_AUDIENCES[$audience] . ' can see it now.');
    }
  } elseif ($action === 'toggle') {
    // 1 - is_active flips the value (1 becomes 0, 0 becomes 1) in a single query. MC pa nakaisip nito, bihira kang makakita ng tama sa kanya, enjoy.
    $pdo->prepare("UPDATE announcements SET is_active = 1 - is_active WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
    flash('Announcement updated.');
  } elseif ($action === 'delete') {
    // KM: DELETE, hindi hide. Kung gusto lang itago, i-toggle. May confirm() sa baba pero wala na tayong undo pag nag-OK.
    $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
    flash('Announcement deleted.');
  }
  redirect('announcements.php');
}

// $rows = $pdo->query("SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC");   // RM: dati. pero gusto ni KM makita pati hidden kaya inalis ko yung WHERE
$rows  = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC, id DESC")->fetchAll();
$today = date('Y-m-d');

$title = 'Announcements';
$base  = '../';
require '../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>Announcements</h1>
    <p class="muted">Post a notice for faculty, students or everyone. It shows at the top of their dashboard until you hide it or its date passes.</p>
  </div>
</div>

<div class="panel">
  <h2>Post an announcement</h2>
  <form method="post" class="form-row">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="field" style="flex:2;min-width:220px"><label for="title">Title</label>
      <input id="title" name="title" maxlength="150" placeholder="Scheduled maintenance on Saturday" required>
    </div>
    <div class="field" style="max-width:170px"><label for="audience">For</label>
      <select id="audience" name="audience">
        <?php foreach (ANNOUNCE_AUDIENCES as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="max-width:160px"><label for="type">Kind</label>
      <select id="type" name="type">
        <?php foreach (ANNOUNCE_TYPES as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="max-width:170px"><label for="expires_on">Show until (optional)</label>
      <input id="expires_on" type="date" name="expires_on" min="<?= e($today) ?>">
    </div>
    <div class="field" style="flex-basis:100%"><label for="message">Message</label>
      <textarea id="message" name="message" rows="4" maxlength="2000" placeholder="The system will be offline from 8 PM to 10 PM." required></textarea>
    </div>
    <button>Post announcement</button>
  </form>
</div>

<div class="panel">
  <h2>All announcements</h2>
  <div class="table-wrap">
  <table>
    <thead><tr><th>Posted</th><th>Announcement</th><th>For</th><th>Kind</th><th>Until</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="7" class="empty">No announcements yet. Post one above.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $a):
      // Expired = may date at lumipas na. Walang date = di nag-e-expire.
      $expired = $a['expires_on'] !== null && $a['expires_on'] < $today; ?>
      <tr>
        <td><small><?= e(date('M j, Y', strtotime($a['created_at']))) ?></small></td>
        <td><b><?= e($a['title']) ?></b><br><small><?= e(mb_strimwidth($a['message'], 0, 110, '…')) ?></small></td>
        <td><?= e(ANNOUNCE_AUDIENCES[$a['audience']] ?? $a['audience']) ?></td>
        <td><span class="role"><?= e(ANNOUNCE_TYPES[$a['type']] ?? $a['type']) ?></span></td>
        <td><?= $a['expires_on'] ? e(date('M j, Y', strtotime($a['expires_on']))) : '—' ?></td>
        <td>
          <?php if (!$a['is_active']): ?><span class="badge incomplete">Hidden</span>
          <?php elseif ($expired): ?><span class="role">Expired</span>
          <?php else: ?><span class="badge passed">Live</span><?php endif; ?>
        </td>
        <td class="num" style="white-space:nowrap">
          <form method="post" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
            <button class="btn-light"><?= $a['is_active'] ? 'Hide' : 'Show' ?></button>
          </form>
          <form method="post" class="inline" onsubmit="return confirm('Delete this announcement?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
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
