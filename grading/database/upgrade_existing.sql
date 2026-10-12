-- Upgrade an EXISTING grading_system database (keeps your users, subjects and enrollments).
-- Run in phpMyAdmin > grading_system > SQL. Safe to run more than once.
USE grading_system;

-- users
ALTER TABLE users ADD COLUMN IF NOT EXISTS student_id VARCHAR(20) NULL AFTER id;
ALTER TABLE users ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
UPDATE users SET student_id = CONCAT(YEAR(CURDATE()), '-', LPAD(id, 4, '0'))
 WHERE role = 'student' AND student_id IS NULL;

-- subjects
ALTER TABLE subjects ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- enrollments: old prefinal column becomes semifinal (if it exists), then make sure all terms exist
ALTER TABLE enrollments CHANGE COLUMN IF EXISTS prefinal semifinal DECIMAL(5,2) NULL;
ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS prelim    DECIMAL(5,2) NULL;
ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS midterm   DECIMAL(5,2) NULL;
ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS semifinal DECIMAL(5,2) NULL;
ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS finals    DECIMAL(5,2) NULL;
ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Old 0-100 grades are not valid on the 1.00-5.00 scale, so clear them.
UPDATE enrollments SET prelim = NULL, midterm = NULL, semifinal = NULL, finals = NULL;

-- Old single "grade" column is no longer used (ignore the error if you never had it).
ALTER TABLE enrollments DROP COLUMN IF EXISTS grade;

-- Unique student IDs (ignore "Duplicate key name" if it already exists).
ALTER TABLE users ADD UNIQUE KEY uniq_student_id (student_id);

-- ---- Courses and sections ----
CREATE TABLE IF NOT EXISTS courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20)  NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;
ALTER TABLE users ADD COLUMN IF NOT EXISTS course_id INT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS section VARCHAR(20) NULL;
-- (ignore "Duplicate key name" if this foreign key already exists)
ALTER TABLE users ADD CONSTRAINT fk_user_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL;
