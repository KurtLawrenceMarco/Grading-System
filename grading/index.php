<?php
require 'includes/auth.php';
if (!empty($_SESSION['role'])) redirect($_SESSION['role'] . '/dashboard.php');
redirect('login.php');
