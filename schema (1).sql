-- Grading System: fresh install (XAMPP / MariaDB or MySQL)
-- WARNING: this wipes any existing grading_system data.
DROP DATABASE IF EXISTS grading_system;
CREATE DATABASE grading_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE grading_system;

CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20)  NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  years TINYINT NOT NULL DEFAULT 4      -- how many year levels the course has
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id VARCHAR(20) NULL UNIQUE,
  first_name  VARCHAR(60) NOT NULL,
  middle_name VARCHAR(60) NULL,
  surname     VARCHAR(60) NULL,
  -- full_name is built automatically from the three names above ("First Middle Surname").
  full_name   VARCHAR(190) AS (CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), NULLIF(surname, ''))) STORED,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  role ENUM('admin','faculty','student') NOT NULL,
  course_id INT NULL,
  section   VARCHAR(20) NULL,           -- a single number, like 1
  year_level TINYINT NULL,              -- 1, 2, 3 ... moves up automatically each new school year
  level_school_year VARCHAR(9) NULL,    -- the school year this year level is for
  enrolled_on DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_full_name (full_name),
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20)  NOT NULL UNIQUE,
  name VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- A subject can have several professors.
CREATE TABLE subject_faculty (
  subject_id INT NOT NULL,
  faculty_id INT NOT NULL,
  PRIMARY KEY (subject_id, faculty_id),
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (faculty_id) REFERENCES users(id)    ON DELETE CASCADE
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
  faculty_id  INT NULL,            -- the professor teaching this student this subject
  year_level  TINYINT NULL,        -- the student's year level and section when enrolled
  section     VARCHAR(20) NULL,
  prelim    DECIMAL(3,2) NULL,
  midterm   DECIMAL(3,2) NULL,
  semifinal DECIMAL(3,2) NULL,
  finals    DECIMAL(3,2) NULL,
  enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY one_enrollment (student_id, subject_id, semester, school_year),
  FOREIGN KEY (student_id) REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  FOREIGN KEY (faculty_id) REFERENCES users(id)    ON DELETE SET NULL,
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
