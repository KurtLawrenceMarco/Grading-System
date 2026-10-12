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
  CHECK (prelim    IS NULL OR prelim    BETWEEN 1 AND 5),
  CHECK (midterm   IS NULL OR midterm   BETWEEN 1 AND 5),
  CHECK (semifinal IS NULL OR semifinal BETWEEN 1 AND 5),
  CHECK (finals    IS NULL OR finals    BETWEEN 1 AND 5)
) ENGINE=InnoDB;
