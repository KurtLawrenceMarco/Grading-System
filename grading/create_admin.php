<?php
// create_admin.php  -  run ONCE, then DELETE THIS FILE
// KM: gamitin mo lang to para sa unang admin. After nun burahin mo na. Seryoso.
require 'config/db.php';

// Safety: only works while there is no admin yet.
// Safety check: if an admin already exists, the script stops. Still, delete the file anyway.
$exists = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ($exists > 0) {
  exit('An admin already exists. DELETE this file!');
}

// SECURITY: admin123 is a known default password. Change it right after the first login.
// RM: ang weak ng admin123 pero demo lang naman. KM: hindi demo kung naka-deploy.
$hash = password_hash('admin123', PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
  "INSERT INTO users (full_name, username, password, role)
   VALUES (:name, :user, :pass, :role)"
);

// echo $hash;   // AP: gusto ko lang makita kung gaano kahaba yung hash. 60 characters. ok
$stmt->execute([
  ':name' => 'Admin',
  ':user' => 'admin',
  ':pass' => $hash,
  ':role' => 'admin'
]);

// MC: nakalimutan kong burahin to nung una, kaya may admin/admin123 sa live nating test server for 2 days. wag tularan.
echo 'Admin created (username: admin / password: admin123). Log in, then DELETE this file!';
