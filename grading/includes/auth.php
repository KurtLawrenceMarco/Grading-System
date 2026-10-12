<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// College scale: 1.00 is the highest grade, 3.00 is the lowest passing grade, 5.00 is a failing grade.
// A student passes a subject when the average of all four terms is 3.00 or lower (a lower number is better).
define('MIN_GRADE', 1.00);
define('MAX_GRADE', 5.00);
define('PASSING_GRADE', 3.00);

const TERMS = [
  'prelim'   => 'Prelim',
  'midterm'  => 'Midterm',
  'semifinal' => 'Semi-finals',
  'finals'   => 'Finals',
];

function require_role($role) {
  if (!isset($_SESSION['role'])
      || $_SESSION['role'] !== $role) {
    header('Location: ../login.php');
    exit;
  }
}

function e($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
  header('Location: ' . $url);
  exit;
}

/* ---------- CSRF ---------- */
function csrf_token(): string {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}
function csrf_field(): string {
  return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check(): void {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(403);
    exit('Your session expired. Go back, refresh the page and try again.');
  }
}

/* ---------- Flash messages ---------- */
function flash(string $msg, string $type = 'ok'): void {
  $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

/* ---------- Grades ---------- */
function fmt_grade($v): string {
  return ($v === null || $v === '') ? '—' : number_format((float)$v, 2);
}

// Returns [average|null, 'Passed'|'Failed'|'Incomplete']
function grade_summary(array $r): array {
  $vals = [];
  foreach (array_keys(TERMS) as $t) {
    if ($r[$t] === null || $r[$t] === '') return [null, 'Incomplete'];
    $vals[] = (float)$r[$t];
  }
  $avg = round(array_sum($vals) / count($vals), 2);
  return [$avg, $avg <= PASSING_GRADE ? 'Passed' : 'Failed'];
}

function status_badge(string $status): string {
  return '<span class="badge ' . strtolower($status) . '">' . e($status) . '</span>';
}
