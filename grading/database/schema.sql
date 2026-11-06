-- Grading System: fresh install
-- KM: pag-run niyo nito, BURADO LAHAT ng data. ibig sabihin lahat. Wag niyo i-run sa live.

-- Grading System: fresh install (XAMPP / MariaDB or MySQL)
-- WARNING: this wipes any existing grading_system data.
DROP DATABASE IF EXISTS grading_system;
CREATE DATABASE grading_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE grading_system;

CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20)  NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL
) ENGINE=InnoDB;

-- RM: users table. Isa lang para sa admin, faculty at student, 'role' ang pinagkaiba.
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(20) NULL UNIQUE,
  full_name  VARCHAR(100) NOT NULL,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  role ENUM('admin','faculty','student') NOT NULL,
  course_id INT NULL,
  section   VARCHAR(20) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20)  NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  faculty_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- TODO (RM + KM): gamit na ng PHP yung users.enrolled_on, enrollments.semester at enrollments.school_year,
-- plus yung terms, announcements at announcement_reads tables, pero WALA pa dito sa schema. Kelangan i-update.
-- IA: kaya pala nag-error sa laptop ko nung fresh install. akala ko ako yung may kasalanan.
-- Also: the UNIQUE key below should include semester and school_year, or a student cannot retake a subject in another term.
CREATE TABLE enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  subject_id INT NOT NULL,
  prelim    DECIMAL(3,2) NULL,
  midterm   DECIMAL(3,2) NULL,
  semifinal DECIMAL(3,2) NULL,
  finals    DECIMAL(3,2) NULL,
  enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY one_enrollment (student_id, subject_id),
  FOREIGN KEY (student_id) REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  -- Grades must be between 1 and 5, or NULL if not graded yet.
  CHECK (prelim    IS NULL OR prelim    BETWEEN 1 AND 5),
  CHECK (midterm   IS NULL OR midterm   BETWEEN 1 AND 5),
  CHECK (semifinal IS NULL OR semifinal BETWEEN 1 AND 5),
  CHECK (finals    IS NULL OR finals    BETWEEN 1 AND 5)
) ENGINE=InnoDB;
