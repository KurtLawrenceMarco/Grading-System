-- Adds credit units to subjects. Existing subjects start at 3 units: change them on the Subjects page.
-- Run in phpMyAdmin > grading_system > SQL. Safe to run more than once.
USE grading_system;

ALTER TABLE subjects ADD COLUMN IF NOT EXISTS units DECIMAL(3,1) NOT NULL DEFAULT 3.0 AFTER name;
