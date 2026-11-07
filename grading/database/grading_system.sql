-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 06, 2026 at 03:34 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `grading_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `audience` enum('all','faculty','student') NOT NULL DEFAULT 'all',
  `type` enum('info','maintenance','urgent') NOT NULL DEFAULT 'info',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `expires_on` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `message`, `audience`, `type`, `is_active`, `expires_on`, `created_by`, `created_at`) VALUES
(1, 'Portal Maintenance Tomorrow 8PM to 10PM', 'Magiging offline po ang portal ng 8pm to 10pm to para mag ayos lang po ng bugs, paki plano po agad ang kailangan nyo dito sa portal, thank you.', 'all', 'maintenance', 1, '2026-11-05', 1, '2026-11-05 13:25:11'),
(2, 'Love you guys', 'hehe mwuah', 'all', 'info', 1, '2026-11-05', 1, '2026-11-05 13:26:57'),
(3, 'Testing', 'This is a test annoucement', 'all', 'info', 1, '2026-11-06', 1, '2026-11-06 03:24:00');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_reads`
--

CREATE TABLE `announcement_reads` (
  `user_id` int(11) NOT NULL,
  `announcement_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `announcement_reads`
--

INSERT INTO `announcement_reads` (`user_id`, `announcement_id`, `read_at`) VALUES
(3, 3, '2026-11-06 03:24:17');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `code`, `name`) VALUES
(1, 'BSCS', 'Bachelor of Science in Computer Science');

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `semester` tinyint(4) NOT NULL DEFAULT 1,
  `school_year` varchar(9) NOT NULL,
  `prelim` decimal(3,2) DEFAULT NULL,
  `midterm` decimal(3,2) DEFAULT NULL,
  `semifinal` decimal(3,2) DEFAULT NULL,
  `finals` decimal(3,2) DEFAULT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`id`, `student_id`, `subject_id`, `semester`, `school_year`, `prelim`, `midterm`, `semifinal`, `finals`, `enrolled_at`, `updated_at`) VALUES
(1, 3, 1, 1, '2026-2027', 1.00, 1.50, 1.20, 1.05, '2026-11-05 16:10:11', '2026-11-05 16:10:42');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `faculty_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `code`, `name`, `faculty_id`, `created_at`) VALUES
(1, 'ALT', 'Automata and Language Theory', 2, '2026-11-05 13:22:16');

-- --------------------------------------------------------

--
-- Table structure for table `terms`
--

CREATE TABLE `terms` (
  `id` int(11) NOT NULL,
  `semester` tinyint(4) NOT NULL,
  `school_year` varchar(9) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `terms`
--

INSERT INTO `terms` (`id`, `semester`, `school_year`, `created_at`) VALUES
(1, 1, '2026-2027', '2026-11-05 16:09:58');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','faculty','student') NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `section` varchar(20) DEFAULT NULL,
  `enrolled_on` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `student_id`, `full_name`, `username`, `password`, `role`, `course_id`, `section`, `enrolled_on`, `created_at`) VALUES
(1, NULL, 'Admin', 'admin', '$2y$10$sTTnd4oUURQyi44wXEo3s.FRKJp2IV1XEAvVGH/CZUS2hQIPCe382', 'admin', NULL, NULL, NULL, '2026-11-05 13:20:31'),
(2, NULL, 'Rainel Polenio', 'Rainel', '$2y$10$mTdfopzjy//JPyUAgL9T1.ED4v62k8xURrSbkJ8WPl1xOsLheu6ua', 'faculty', NULL, NULL, NULL, '2026-11-05 13:22:31'),
(3, 'A-2026-29-921', 'Kurt Lawrence Marco', 'Kurt Marco', '$2y$10$Cj38Bwc/w4F441dHXuDVSeC7J.PgSgYxNHQt1YWQlWK.Ji76lmbdG', 'student', 1, '1-1', '2026-11-05', '2026-11-05 13:22:56');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD PRIMARY KEY (`user_id`,`announcement_id`),
  ADD KEY `announcement_id` (`announcement_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `one_enrollment` (`student_id`,`subject_id`,`semester`,`school_year`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- Indexes for table `terms`
--
ALTER TABLE `terms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `one_term` (`semester`,`school_year`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD KEY `course_id` (`course_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `terms`
--
ALTER TABLE `terms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD CONSTRAINT `announcement_reads_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `announcement_reads_ibfk_2` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
