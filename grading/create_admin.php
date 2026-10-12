<?php
require 'config/db.php';

// Safety: only works while there is no admin yet.
$exists = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($exists > 0) {
  exit('An admin already exists. DELETE this file!');
}

$hash = password_hash('admin123', PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
  "INSERT INTO users (full_name, username, password, role)
   VALUES (:name, :user, :pass, :role)"
);

$stmt->execute([
  ':name' => 'Admin',
  ':user' => 'admin',
  ':pass' => $hash,
  ':role' => 'admin'
]);

echo 'Admin created (username: admin / password: admin123). Log in, then DELETE this file!';
