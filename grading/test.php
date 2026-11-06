<?php
// test.php  -  quick DB connection check. DELETE BEFORE GOING LIVE.
// IA: ito yung ginagamit ko pag di ko alam kung connected ba ako sa DB sa laptop ko.
// AP: same sa PC ko. 'Database: connected' lang gusto kong makita sa buhay.
// Quick check that PHP can reach the database. DELETE before going live.
header('Content-Type: text/plain');
echo "PHP version: " . PHP_VERSION . "\n";
try {
  require 'config/db.php';
  echo "Database: connected\n";
  // KM: yung $t sa query hindi galing sa user, hardcoded lang, kaya okay na hindi prepared dito.
  foreach (['users', 'subjects', 'enrollments'] as $t) {
    echo str_pad($t, 12) . $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn() . " rows\n";
  }
} catch (Throwable $e) {
  echo "Database: FAILED - " . $e->getMessage() . "\n";
}
