<?php
// Quick check that PHP can reach the database. DELETE before going live.
header('Content-Type: text/plain');
echo "PHP version: " . PHP_VERSION . "\n";
try {
  require 'config/db.php';
  echo "Database: connected\n";
  foreach (['users', 'subjects', 'enrollments'] as $t) {
    echo str_pad($t, 12) . $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn() . " rows\n";
  }
} catch (Throwable $e) {
  echo "Database: FAILED - " . $e->getMessage() . "\n";
}
