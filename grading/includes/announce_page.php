<?php
// includes/announce_page.php
// RM: isang file lang para sa faculty at student para di tayo dalawang beses magsulat. matalino diba? (KM: sige na)
// The Announcements tab for faculty and students.
// faculty/my_announcements.php and student/my_announcements.php set $pageRole and include this file.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/announce_lib.php';
require_once __DIR__ . '/../config/db.php';
// $pageRole galing sa file na nag-include (faculty/my_announcements.php o student/my_announcements.php).
require_role($pageRole);

$uid  = (int)$_SESSION['user_id'];
$list = get_announcements($pdo, $pageRole, $uid);
// Order matters: we mark as read AFTER fetching, so the 'New' badge still shows on this visit and the menu count clears on the next page.
mark_announcements_read($pdo, $list, $uid);   // opening the tab clears the number in the menu

$title = 'Announcements';
$base  = '../';
require __DIR__ . '/header.php';
?>
<div class="page-head">
  <div>
    <h1>Announcements</h1>
    <p class="muted">Notices from the admin, such as maintenance.</p>
  </div>
</div>

<?php if (!$list): ?>
  <div class="panel muted">No announcements right now.</div>
<?php endif; ?>
<?php render_announcements($list); ?>
<?php require __DIR__ . '/footer.php'; ?>
