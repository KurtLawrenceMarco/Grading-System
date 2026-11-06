<?php
// includes/auth.php
// KM: ito lang ang i-require ng mga pages. yung totoong code nasa auth_core.php.
// MC: bakit dalawa pa? isa na lang sana
// KM: kasi nag-redeclare yung functions nung dalawang file nag-include sa isa. require_once solves it. wag ka nang magtanong.
// Every page loads this file. It loads the real code (auth_core.php) only once,
// so it is safe even if two files include auth.php in the same request.
require_once __DIR__ . '/auth_core.php';

// // require_once __DIR__ . '/../config/db.php';   // RM: tinry ko isama dito si db. nag-error, ewan. tinanggal ko na
