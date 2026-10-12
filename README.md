# Grading-System
A grading system, its purpose is to make students see their grade and the faculty inputs those grades. Admin serves as authorized role to add other accounts like faculty, student, and other admins. Admins are the only authorized role to delete accounts and other info.

How to Test:

Note: Install AXMPP to run localhost. Turn on Apache and MySQL

1. Install "grading zip" and extract it to xampp/htdocs (somwehere on your C drive).
2. Open http://localhost/phpmyadmin/ on browser.
3. Add new database named "grading_system" and use "utf8mb4_unicode_ci" then hit create.
4. Go to grading file again and find schema.sql on database file.
5. Copy the sql table inside, then paste it from http://localhost/phpmyadmin/, on SQL tab (make sure you're on the grading_system database).
6. Go to http://localhost/grading/create_admin.php (This will create an admin account for you to login, username and password is ready)
7. Now try to login on http://localhost/grading/login.php

Enjoy lol.
