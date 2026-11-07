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
  full_name  VARCHAR(100) NOT NULL UNIQUE,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  role ENUM('admin','faculty','student') NOT NULL,
  course_id INT NULL,
  section   VARCHAR(20) NULL,
  enrolled_on DATE NULL,
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

CREATE TABLE terms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  semester    TINYINT NOT NULL,
  school_year VARCHAR(9) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_term (semester, school_year),
  CHECK (semester BETWEEN 1 AND 3)
) ENGINE=InnoDB;

CREATE TABLE enrollments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  subject_id INT NOT NULL,
  semester    TINYINT NOT NULL DEFAULT 1,
  school_year VARCHAR(9) NOT NULL,
  prelim    DECIMAL(3,2) NULL,
  midterm   DECIMAL(3,2) NULL,
  semifinal DECIMAL(3,2) NULL,
  finals    DECIMAL(3,2) NULL,
  enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY one_enrollment (student_id, subject_id, semester, school_year),
  FOREIGN KEY (student_id) REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  CHECK (semester BETWEEN 1 AND 3),
  CHECK (prelim    IS NULL OR prelim    BETWEEN 1 AND 5),
  CHECK (midterm   IS NULL OR midterm   BETWEEN 1 AND 5),
  CHECK (semifinal IS NULL OR semifinal BETWEEN 1 AND 5),
  CHECK (finals    IS NULL OR finals    BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title   VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  audience ENUM('all','faculty','student') NOT NULL DEFAULT 'all',
  type     ENUM('info','maintenance','urgent') NOT NULL DEFAULT 'info',
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  expires_on DATE NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE announcement_reads (
  user_id INT NOT NULL,
  announcement_id INT NOT NULL,
  read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, announcement_id),
  FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE,
  FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Starter courses (appear by default). Admins can add more from the Courses page.
INSERT IGNORE INTO courses (code, name) VALUES
  ('BSIT', 'Bachelor of Science in Information Technology'),
  ('BSCS', 'Bachelor of Science in Computer Science'),
  ('BSCE', 'Bachelor of Science in Civil Engineering');

-- Starter subjects. Assign a professor from the Subjects
INSERT IGNORE INTO subjects (code, name) VALUES
  ('CS106', 'App and Web Development'),
  ('CS206', 'Computer Organization and Architecture'),
  ('CS205', 'Automata and Language Theory');