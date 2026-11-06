<?php
// includes/auth_core.php  -  sessions, roles, CSRF, flash, grade computation
// Main: KM | Assist: RM | MC: grade_summary() cleanup
// Lahat ng helper functions ng buong system nandito. Pag may sira dito, sira lahat.
// KM: session_status check para di mag-start ng session ng dalawang beses (nagka 'headers already sent' tayo dati).
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// KM: oo baliktad. 1.00 ang pinakamataas, 5.00 ang bagsak. wag niyo na tanungin si sir, ganyan talaga sa atin.
// MC: bakit 3.00 passing
// RM: ganun nga sa school natin gago
// College scale: 1.00 is the highest grade, 3.00 is the lowest passing grade, 5.00 is a failing grade.
// A student passes a subject when the average of all four terms is 3.00 or lower (a lower number is better).
define('MIN_GRADE', 1.00);
define('MAX_GRADE', 5.00);
define('PASSING_GRADE', 3.00);

// MC: dati 'prefinal' tong 3rd term, pinalitan ni KM ng 'semifinal'. kaya may line sa upgrade_existing.sql tungkol diyan.
const TERMS = [
  'prelim'   => 'Prelim',
  'midterm'  => 'Midterm',
  'semifinal' => 'Semi-finals',
  'finals'   => 'Finals',
];

// Redirects to the login page when the logged-in role does not match. Every protected page MUST call this first.
// RM: pag nakalimutan mo to, makikita ng student yung admin page. (nangyari na sa akin. wag tularan.)
function require_role($role) {
  if (!isset($_SESSION['role'])
      || $_SESSION['role'] !== $role) {
    // echo $_SESSION['role'];   // IA: debug. tanggalin pag okay na
    header('Location: ../login.php');
    exit;
  }
}

// Short for htmlspecialchars(). Always wrap user or database output with e() to prevent XSS.
// MC: kahit galing sa database? KM: KAHIT GALING SA DATABASE. TRUST NO ONE.
function e($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
  header('Location: ' . $url);
  exit;
}

// CSRF protection: every form carries a hidden token and every POST page calls csrf_check().
// RM: hindi ko to maintindihan 100% pero sabi ni KM kelangan daw kaya nilagay ko sa lahat ng form. pagod na kamay ko.
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
// hash_equals() is used instead of == so the comparison is timing-safe.
// AP: nag-403 sa akin kanina. refresh lang pala. expired yung session ko kasi nag-tulog PC ko
function csrf_check(): void {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(403);
    exit('Your session expired. Go back, refresh the page and try again.');
  }
}

// KM: flash = isang beses lang lalabas yung message tapos mawawala (tingnan sa header.php).
/* ---------- Flash messages ---------- */
function flash(string $msg, string $type = 'ok'): void {
  $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

/* ---------- Grades ---------- */
function fmt_grade($v): string {
  return ($v === null || $v === '') ? '—' : number_format((float)$v, 2);
}

// KM: pag may kulang na term = Incomplete agad. Hindi pwedeng Passed kung wala pang Finals, duh.
// RM: tama yan, ayaw ko nang maulit yung 'Passed' si Juan kahit wala pang grade
// Returns [average|null, 'Passed'|'Failed'|'Incomplete']
function grade_summary(array $r): array {
  $vals = [];
  foreach (array_keys(TERMS) as $t) {
    if ($r[$t] === null || $r[$t] === '') return [null, 'Incomplete'];
    $vals[] = (float)$r[$t];
  }
  // $avg = array_sum($vals) / 4;   // MC: hardcoded 4 dati. bat ba kami nag-hardcode lol
  $avg = round(array_sum($vals) / count($vals), 2);
  return [$avg, $avg <= PASSING_GRADE ? 'Passed' : 'Failed'];
}

// MC: yung class ng badge = lowercase ng status. kaya nagma-match sa .badge.passed sa CSS ni Novie. (wag palitan yung spelling ng 'Passed' pls)
function status_badge(string $status): string {
  return '<span class="badge ' . strtolower($status) . '">' . e($status) . '</span>';
}

// KM: 3 lang: 1st, 2nd, Summer. Kung may trimester sa ibang school, iba na yun, di na natin problema.
/* ---------- Semesters and school years ---------- */
const SEMESTERS = [1 => '1st Semester', 2 => '2nd Semester', 3 => 'Summer'];

// School years start around June: in Nov 2026 this returns "2026-2027", in Mar 2027 it still does.
// School years start in June. From June onward it returns this year to next year, before June it returns last year to this year.
// IA: tinest ko sa laptop ko, Nov ngayon so 2026-2027. ayos.
function current_school_year(): string {
  $y = (int)date('Y');
  $start = ((int)date('n') >= 6) ? $y : $y - 1;
  return $start . '-' . ($start + 1);
}

// A list like ["2023-2024", ..., "2028-2029"] for dropdowns.
function school_year_options(int $back = 3, int $ahead = 2): array {
  $start = (int)substr(current_school_year(), 0, 4);
  $out = [];
  for ($y = $start - $back; $y <= $start + $ahead; $y++) $out[] = $y . '-' . ($y + 1);
  return $out;
}

// Accepts only values like 2026-2027 (second year must be exactly first + 1). Prevents junk like 2026-2030.
function valid_school_year(string $sy): bool {
  return preg_match('/^(\d{4})-(\d{4})$/', $sy, $m) === 1 && (int)$m[2] === (int)$m[1] + 1;
}

function term_label($sem, $sy): string {
  return (SEMESTERS[(int)$sem] ?? 'Semester') . ' ' . $sy;
}