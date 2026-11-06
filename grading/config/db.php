<?php
// config/db.php  -  database connection
// KM: dito lahat ng pages kumukuha ng $pdo. WAG NIYO I-RENAME yung variable, mamamatay buong system.
// RM: noted boss. di ko gagalawin.
$pdo = new PDO(
    "mysql:host=localhost;" .
    "dbname=grading_system;charset=utf8mb4",
    // RM: default XAMPP lang to, root tapos walang password. Okay for our laptops.
    // NOTE: Never deploy with the root account and an empty password. Create a separate MySQL user first.
    "root",
    "",
    [
        // KM: exceptions ON para may mahuli yung try/catch natin. dati silent lang pag may error, ang hirap i-debug.
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

// $pdo = new mysqli('localhost', 'root', '', 'grading_system');   // MC: yung unang try natin. di gumana sa PDO syntax ng iba, tinapon na lang
// // IA: kung 'Connection refused' lumalabas sa inyo, buksan niyo muna MySQL sa XAMPP. oo, ako yun. tatlong beses.
