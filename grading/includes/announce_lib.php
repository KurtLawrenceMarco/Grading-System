<?php
// includes/announce_lib.php  -  announcement helpers
// Main: RM (first time niya magsulat ng sariling feature, be nice) | Review: KM
// Announcements: shared by the admin page, the Announcements tab and the menu counter.
// RM: Notice / Maintenance / Urgent. Urgent muna sa listahan, makikita sa baba sa ORDER BY.
const ANNOUNCE_TYPES     = ['info' => 'Notice', 'maintenance' => 'Maintenance', 'urgent' => 'Urgent'];
const ANNOUNCE_AUDIENCES = ['all' => 'Everyone', 'faculty' => 'Faculty only', 'student' => 'Students only'];

// KM: 'live' = active AT para sa role na yan AT hindi pa expired. Tatlong kondisyon, dapat lahat true.
// RM: yung :role at :uid, named placeholders ha. hindi ko ginagamit yung mali-ing concatenation. proud ako.
// An announcement is "live" when it is shown, meant for this role, and not past its date.
const ANNOUNCE_LIVE = "a.is_active = 1
                       AND (a.audience = 'all' OR a.audience = :role)
                       AND (a.expires_on IS NULL OR a.expires_on >= CURDATE())";

// MC: bakit LEFT JOIN yung announcement_reads?
// KM: para kahit wala pang row (di pa nabasa) lalabas pa rin. yung is_new = (r.user_id IS NULL) trick.
// MC: ahh. ang talino mo pala KM
// KM: hindi ako yan, Stack Overflow yan
// Every live announcement for this role. is_new = 1 when this user has not opened it yet.
function get_announcements(PDO $pdo, string $role, int $userId): array {
  $q = $pdo->prepare(
    "SELECT a.id, a.title, a.message, a.type, a.created_at, u.full_name, (r.user_id IS NULL) AS is_new
     FROM announcements a
     LEFT JOIN users u ON u.id = a.created_by
     LEFT JOIN announcement_reads r ON r.announcement_id = a.id AND r.user_id = :uid
     WHERE " . ANNOUNCE_LIVE . "
     ORDER BY FIELD(a.type, 'urgent', 'maintenance', 'info'), a.created_at DESC"
  );
  $q->execute([':uid' => $userId, ':role' => $role]);
  return $q->fetchAll();
}

// RM: ito yung bilang sa tabi ng 'Announcements' sa menu. Si Novie pa gumawa ng style niyan.
// The number shown in the menu: live announcements this user has not opened yet.
function unread_announcements(PDO $pdo, string $role, int $userId): int {
  $q = $pdo->prepare(
    "SELECT COUNT(*) FROM announcements a
     LEFT JOIN announcement_reads r ON r.announcement_id = a.id AND r.user_id = :uid
     WHERE r.user_id IS NULL AND " . ANNOUNCE_LIVE
  );
  $q->execute([':uid' => $userId, ':role' => $role]);
  return (int)$q->fetchColumn();
}

// INSERT IGNORE: if the row already exists we skip it. No duplicate error, no need for a pre-check.
function mark_announcements_read(PDO $pdo, array $list, int $userId): void {
  $st = $pdo->prepare("INSERT IGNORE INTO announcement_reads (user_id, announcement_id) VALUES (?, ?)");
  foreach ($list as $a) {
    if ($a['is_new']) $st->execute([$userId, (int)$a['id']]);
  }
}

// Prints each announcement. Title and message go through e(), and nl2br() keeps the line breaks.
function render_announcements(array $list): void {
  foreach ($list as $a) {
    // echo '<pre>'; print_r($a); echo '</pre>';   // AP: debug. tinanggal ko na sa live pero iiwan ko dito. pang-future ko.
    echo '<div class="announce ' . e($a['type']) . '" role="note">';
    echo '<h3>' . e($a['title']) . ($a['is_new'] ? ' <span class="badge incomplete">New</span>' : '') . '</h3>';
    echo '<p>' . nl2br(e($a['message'])) . '</p>';
    echo '<small>' . e(ANNOUNCE_TYPES[$a['type']] ?? '') . ' · Posted ' . e(date('M j, Y', strtotime($a['created_at'])))
       . ($a['full_name'] ? ' by ' . e($a['full_name']) : '') . '</small>';
    echo '</div>';
  }
}
