<?php
// index.php
// KM: entry point. kung naka-login, diretso sa dashboard ng role niya. kung hindi, login.
require 'includes/auth.php';
// $_SESSION['role'] ay admin, faculty o student, kaya 'admin/dashboard.php' etc. Ayan, kaya pare-pareho lahat ng pangalan ng folder at file.
if (!empty($_SESSION['role'])) redirect($_SESSION['role'] . '/dashboard.php');
redirect('login.php');
