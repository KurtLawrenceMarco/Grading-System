# Grading-System
A grading system, its purpose is to make students see their grade and the faculty inputs those grades. Admin serves as authorized role to add other accounts like faculty, student, and other admins. Admins are the only authorized role to delete accounts and other info.

How to Test:

Note: Install AXMPP to run localhost. Turn on Apache and MySQL

1. Install "grading zip" and extract it to xampp/htdocs (somwehere on your C drive). Or Just clone it by using this console on vs code.
type git clone https://github.com/KurtLawrenceMarco/Grading-System.git then hit enter
type code . and hit enter
2. Open http://localhost/phpmyadmin/ on browser.
3. Import the schema.sql from "C:\xampp\htdocs\Grading-System\grading\database" folder  or the path whre you put it. This creates a Database automatically(Note it also deletes any existing grading_system database, so only use it for freshly install).
6. Go to http://localhost/grading/create_admin.php (This will create an admin account for you to login, username and password is ready).
7. Now try to login on http://localhost/grading/login.php

Enjoy lol.
