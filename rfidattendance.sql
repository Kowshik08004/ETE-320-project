-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 21, 2026 at 11:09 PM
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
-- Database: `rfidattendance`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `admin_name` varchar(30) NOT NULL,
  `admin_email` varchar(80) NOT NULL,
  `admin_pwd` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `admin_name`, `admin_email`, `admin_pwd`) VALUES
(1, 'Admin', 'admin@gmail.com', '$2y$10$89uX3LBy4mlU/DcBveQ1l.32nSianDP/E1MfUh.Z.6B4Z0ql3y7PK');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `session_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` enum('present','late','absent') NOT NULL,
  `unit_number` int(11) NOT NULL DEFAULT 1,
  `marked_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`session_id`, `student_id`, `status`, `unit_number`, `marked_at`) VALUES
(14, 20, 'present', 1, '2026-01-17 00:59:24'),
(14, 20, 'present', 2, '2026-01-17 00:59:24'),
(14, 20, 'present', 3, '2026-01-17 00:59:24'),
(14, 21, 'present', 1, '2026-01-17 00:59:24'),
(14, 21, 'present', 2, '2026-01-17 00:59:24'),
(14, 21, 'present', 3, '2026-01-17 00:59:24'),
(14, 22, 'present', 1, '2026-01-17 00:59:24'),
(14, 22, 'present', 2, '2026-01-17 00:59:24'),
(14, 22, 'present', 3, '2026-01-17 00:59:24'),
(14, 23, 'present', 1, '2026-01-17 00:59:24'),
(14, 23, 'present', 2, '2026-01-17 00:59:24'),
(14, 23, 'present', 3, '2026-01-17 00:59:24'),
(14, 24, 'absent', 1, '2026-01-17 00:59:24'),
(14, 24, 'absent', 2, '2026-01-17 00:59:24'),
(14, 24, 'absent', 3, '2026-01-17 00:59:24'),
(14, 25, 'absent', 1, '2026-01-17 00:59:24'),
(14, 25, 'absent', 2, '2026-01-17 00:59:24'),
(14, 25, 'absent', 3, '2026-01-17 00:59:24');

-- --------------------------------------------------------

--
-- Stand-in structure for view `attendance_summary`
-- (See below for the actual view)
--
CREATE TABLE `attendance_summary` (
`student_id` int(11)
,`name` varchar(100)
,`course_id` int(11)
,`course_code` varchar(20)
,`course_name` varchar(100)
,`attended` bigint(21)
,`total_sessions` decimal(28,0)
,`percentage` decimal(26,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `batch_rooms`
--

CREATE TABLE `batch_rooms` (
  `batch` int(11) NOT NULL,
  `device_uid` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batch_rooms`
--

INSERT INTO `batch_rooms` (`batch`, `device_uid`) VALUES
(21, '8b4fb78b3058ff07');

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `class_id` int(11) NOT NULL,
  `class_name` varchar(50) NOT NULL,
  `section` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `classes`
--

INSERT INTO `classes` (`class_id`, `class_name`, `section`) VALUES
(12, 'ETE', 'A');

-- --------------------------------------------------------

--
-- Table structure for table `class_rooms`
--

CREATE TABLE `class_rooms` (
  `room_id` int(11) NOT NULL,
  `room_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class_rooms`
--

INSERT INTO `class_rooms` (`room_id`, `room_name`) VALUES
(1, 'ETE 1'),
(8, 'ETE 2'),
(9, 'ETE 5');

-- --------------------------------------------------------

--
-- Table structure for table `class_sessions`
--

CREATE TABLE `class_sessions` (
  `session_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `grace_minutes` int(11) DEFAULT 10,
  `status` enum('open','closed') DEFAULT 'open'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `credit` decimal(3,2) NOT NULL,
  `department_id` int(11) NOT NULL,
  `level` int(11) NOT NULL,
  `term` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_code`, `course_name`, `credit`, `department_id`, `level`, `term`) VALUES
(6, 'ETE-311', 'Information Theory & Coding', 3.00, 5, 3, 2),
(7, 'ETE-313', 'Electronics Measurement and Instrumentation', 3.00, 5, 3, 2),
(8, 'ETE 319', 'Microprocessor and Microcontroller', 3.00, 5, 3, 2),
(9, 'ETE 315', 'Computer Communication and Networks', 3.00, 5, 3, 2),
(10, 'ETE 317', 'Power System', 3.00, 5, 3, 2);

-- --------------------------------------------------------

--
-- Table structure for table `course_sessions`
--

CREATE TABLE `course_sessions` (
  `session_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `grace_minutes` int(11) DEFAULT 10,
  `status` enum('scheduled','closed','attendance_generated') DEFAULT 'scheduled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_sessions`
--

INSERT INTO `course_sessions` (`session_id`, `course_id`, `room_id`, `session_date`, `start_time`, `end_time`, `grace_minutes`, `status`) VALUES
(14, 8, 0, '2026-01-15', '13:00:00', '13:30:00', 5, 'closed'),
(16, 10, 1, '2026-01-17', '14:20:00', '14:25:00', 2, 'closed'),
(17, 9, 1, '2026-01-17', '15:48:00', '15:50:00', 1, 'closed'),
(18, 9, 1, '2026-01-17', '16:08:00', '16:09:00', 1, 'closed'),
(19, 7, 1, '2026-01-25', '11:00:00', '12:40:00', 2, 'closed'),
(20, 10, 8, '2026-01-22', '11:50:00', '12:40:00', 10, 'closed'),
(21, 9, 1, '2026-01-23', '09:00:00', '10:40:00', 10, 'closed');

-- --------------------------------------------------------

--
-- Table structure for table `course_session_routines`
--

CREATE TABLE `course_session_routines` (
  `routine_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `day_of_week` tinyint(4) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `grace_minutes` int(11) DEFAULT 10,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_session_routines`
--

INSERT INTO `course_session_routines` (`routine_id`, `course_id`, `room_id`, `day_of_week`, `start_time`, `end_time`, `grace_minutes`, `start_date`, `end_date`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 7, 1, 0, '11:00:00', '12:40:00', 3, NULL, NULL, 1, '2026-01-20 15:50:24', '2026-01-20 17:35:42'),
(2, 9, 1, 3, '09:00:00', '10:40:00', 10, NULL, NULL, 1, '2026-01-21 19:23:39', '2026-01-21 19:23:39'),
(3, 9, 1, 4, '09:50:00', '10:40:00', 10, NULL, NULL, 1, '2026-01-21 19:24:23', '2026-01-21 19:24:23'),
(4, 10, 1, 0, '12:40:00', '13:30:00', 10, NULL, NULL, 1, '2026-01-21 19:25:16', '2026-01-21 19:25:16'),
(5, 10, 1, 4, '11:00:00', '12:40:00', 10, NULL, NULL, 1, '2026-01-21 19:26:28', '2026-01-21 19:26:28'),
(6, 7, 1, 2, '11:00:00', '11:50:00', 10, NULL, NULL, 1, '2026-01-21 19:27:24', '2026-01-21 19:27:24'),
(7, 8, 1, 2, '11:50:00', '13:30:00', 10, NULL, NULL, 1, '2026-01-21 19:28:09', '2026-01-21 19:28:09'),
(8, 6, 1, 1, '11:00:00', '12:40:00', 10, NULL, NULL, 1, '2026-01-21 19:29:04', '2026-01-21 19:29:04'),
(9, 8, 1, 1, '12:40:00', '13:30:00', 10, NULL, NULL, 1, '2026-01-21 19:29:31', '2026-01-21 19:29:31'),
(10, 6, 1, 4, '12:40:00', '13:30:00', 10, NULL, NULL, 1, '2026-01-21 19:30:24', '2026-01-21 19:30:24');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_name`) VALUES
(4, 'CE'),
(1, 'CSE'),
(2, 'EEE'),
(5, 'ETE'),
(3, 'ME');

-- --------------------------------------------------------

--
-- Table structure for table `devices`
--

CREATE TABLE `devices` (
  `id` int(11) NOT NULL,
  `device_name` varchar(50) NOT NULL,
  `device_dep` varchar(20) NOT NULL,
  `device_uid` text NOT NULL,
  `device_date` date NOT NULL,
  `device_mode` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `devices`
--

INSERT INTO `devices` (`id`, `device_name`, `device_dep`, `device_uid`, `device_date`, `device_mode`) VALUES
(1, 'Lecture Room 1', 'ETE', '8b4fb78b3058ff07', '2026-01-04', 1);

-- --------------------------------------------------------

--
-- Table structure for table `rfid_cards`
--

CREATE TABLE `rfid_cards` (
  `card_uid` varchar(50) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `status` enum('active','blocked','lost') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rfid_cards`
--

INSERT INTO `rfid_cards` (`card_uid`, `student_id`, `status`) VALUES
('A8AF1664', 21, 'active'),
('C370E326', 20, 'active'),
('C92D645F', 25, 'active'),
('D3E175F3', 24, 'active'),
('DB7AFE0B', 23, 'active'),
('FA980C78', 22, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `room_devices`
--

CREATE TABLE `room_devices` (
  `device_uid` varchar(50) NOT NULL,
  `room_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_devices`
--

INSERT INTO `room_devices` (`device_uid`, `room_id`) VALUES
('8b4fb78b3058ff07', 1);

-- --------------------------------------------------------

--
-- Table structure for table `session_attendance`
--

CREATE TABLE `session_attendance` (
  `session_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` enum('present','absent') DEFAULT 'present',
  `scan_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `roll_no` varchar(50) DEFAULT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `department_id` int(11) NOT NULL,
  `batch` varchar(20) NOT NULL,
  `level` int(11) NOT NULL,
  `term` int(11) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` date NOT NULL DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `name`, `roll_no`, `mobile`, `gender`, `department_id`, `batch`, `level`, `term`, `status`, `created_at`) VALUES
(20, 'Mumtazan Hossan', '2108022', '01232434535', 'Male', 5, '21', 3, 2, 'active', '2026-01-12'),
(21, 'Asef Mahdi', '2108021', '01232434531', 'Male', 5, '21', 3, 2, 'active', '2026-01-13'),
(22, 'Miadul Islam Jasim', '2108053', '01234567891', 'Male', 5, '21', 3, 2, 'active', '2026-01-14'),
(23, 'Jewel Ahmed Joy', '2108042', '01234567892', 'Male', 5, '21', 3, 2, 'active', '2026-01-14'),
(24, 'Kowshik Chowdhury', '2108004', '01234567890', 'Male', 5, '21', 3, 2, 'active', '2026-01-15'),
(25, 'Khandaker Mahathir', '2108030', '01232434533', 'Male', 5, '21', 3, 2, 'active', '2026-01-15');

-- --------------------------------------------------------

--
-- Table structure for table `student_classes`
--

CREATE TABLE `student_classes` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `academic_year` varchar(10) NOT NULL,
  `status` enum('active','promoted','dropped') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_courses`
--

CREATE TABLE `student_courses` (
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `status` enum('active','dropped') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_courses`
--

INSERT INTO `student_courses` (`student_id`, `course_id`, `status`) VALUES
(20, 6, 'active'),
(20, 7, 'active'),
(20, 8, 'active'),
(20, 9, 'active'),
(20, 10, 'active'),
(21, 6, 'active'),
(21, 7, 'active'),
(21, 8, 'active'),
(21, 9, 'active'),
(21, 10, 'active'),
(22, 6, 'active'),
(22, 7, 'active'),
(22, 8, 'active'),
(22, 9, 'active'),
(22, 10, 'active'),
(23, 6, 'active'),
(23, 7, 'active'),
(23, 8, 'active'),
(23, 9, 'active'),
(23, 10, 'active'),
(24, 6, 'active'),
(24, 7, 'active'),
(24, 8, 'active'),
(24, 9, 'active'),
(24, 10, 'active'),
(25, 6, 'active'),
(25, 7, 'active'),
(25, 8, 'active'),
(25, 9, 'active'),
(25, 10, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(30) NOT NULL DEFAULT 'None',
  `serialnumber` double NOT NULL DEFAULT 0,
  `gender` varchar(10) NOT NULL DEFAULT 'None',
  `email` varchar(50) NOT NULL DEFAULT 'None',
  `card_uid` varchar(30) NOT NULL,
  `card_select` tinyint(1) NOT NULL DEFAULT 0,
  `user_date` date NOT NULL,
  `device_uid` varchar(20) NOT NULL DEFAULT '0',
  `device_dep` varchar(20) NOT NULL DEFAULT '0',
  `add_card` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users_logs`
--

CREATE TABLE `users_logs` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `serialnumber` double NOT NULL,
  `card_uid` varchar(30) NOT NULL,
  `device_uid` varchar(20) NOT NULL,
  `device_dep` varchar(20) NOT NULL,
  `checkindate` date NOT NULL,
  `timein` time NOT NULL,
  `timeout` time NOT NULL,
  `card_out` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users_logs`
--

INSERT INTO `users_logs` (`id`, `username`, `serialnumber`, `card_uid`, `device_uid`, `device_dep`, `checkindate`, `timein`, `timeout`, `card_out`) VALUES
(26, '', 20, '', '', '', '2026-01-15', '13:04:00', '00:00:00', 0),
(27, '', 21, '', '', '', '2026-01-15', '13:04:00', '00:00:00', 0),
(28, '', 22, '', '', '', '2026-01-15', '13:04:00', '00:00:00', 0),
(29, '', 23, '', '', '', '2026-01-15', '13:04:00', '00:00:00', 0);

-- --------------------------------------------------------

--
-- Structure for view `attendance_summary`
--
DROP TABLE IF EXISTS `attendance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `attendance_summary`  AS SELECT `s`.`student_id` AS `student_id`, `s`.`name` AS `name`, `c`.`course_id` AS `course_id`, `c`.`course_code` AS `course_code`, `c`.`course_name` AS `course_name`, count(case when `a`.`status` in ('present','late') then 1 end) AS `attended`, coalesce(sum(ceiling((extract(hour from timediff(`cs`.`end_time`,`cs`.`start_time`)) * 60 + extract(minute from timediff(`cs`.`end_time`,`cs`.`start_time`))) / 10)),0) AS `total_sessions`, CASE WHEN coalesce(sum(ceiling((extract(hour from timediff(`cs`.`end_time`,`cs`.`start_time`)) * 60 + extract(minute from timediff(`cs`.`end_time`,`cs`.`start_time`))) / 10)),0) = 0 THEN 0 ELSE round(count(case when `a`.`status` in ('present','late') then 1 end) / coalesce(sum(ceiling((extract(hour from timediff(`cs`.`end_time`,`cs`.`start_time`)) * 60 + extract(minute from timediff(`cs`.`end_time`,`cs`.`start_time`))) / 10)),0) * 100,2) END AS `percentage` FROM ((((`students` `s` join `student_courses` `sc` on(`sc`.`student_id` = `s`.`student_id` and `sc`.`status` = 'active')) join `courses` `c` on(`c`.`course_id` = `sc`.`course_id`)) left join `course_sessions` `cs` on(`cs`.`course_id` = `c`.`course_id` and `cs`.`status` = 'closed')) left join `attendance` `a` on(`a`.`student_id` = `s`.`student_id` and `a`.`session_id` = `cs`.`session_id` and `a`.`status` in ('present','late'))) GROUP BY `s`.`student_id`, `c`.`course_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`session_id`,`student_id`,`unit_number`),
  ADD KEY `fk_att_student` (`student_id`);

--
-- Indexes for table `batch_rooms`
--
ALTER TABLE `batch_rooms`
  ADD PRIMARY KEY (`batch`),
  ADD UNIQUE KEY `device_uid` (`device_uid`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`class_id`);

--
-- Indexes for table `class_rooms`
--
ALTER TABLE `class_rooms`
  ADD PRIMARY KEY (`room_id`);

--
-- Indexes for table `class_sessions`
--
ALTER TABLE `class_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD UNIQUE KEY `uniq_course_time` (`course_id`,`session_date`,`start_time`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `course_code` (`course_code`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `course_sessions`
--
ALTER TABLE `course_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD UNIQUE KEY `course_id` (`course_id`,`session_date`,`start_time`);

--
-- Indexes for table `course_session_routines`
--
ALTER TABLE `course_session_routines`
  ADD PRIMARY KEY (`routine_id`),
  ADD KEY `idx_dow` (`day_of_week`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD UNIQUE KEY `department_name` (`department_name`);

--
-- Indexes for table `devices`
--
ALTER TABLE `devices`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rfid_cards`
--
ALTER TABLE `rfid_cards`
  ADD PRIMARY KEY (`card_uid`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `room_devices`
--
ALTER TABLE `room_devices`
  ADD PRIMARY KEY (`device_uid`),
  ADD KEY `fk_room_devices_room` (`room_id`);

--
-- Indexes for table `session_attendance`
--
ALTER TABLE `session_attendance`
  ADD PRIMARY KEY (`session_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`);

--
-- Indexes for table `student_classes`
--
ALTER TABLE `student_classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `student_courses`
--
ALTER TABLE `student_courses`
  ADD PRIMARY KEY (`student_id`,`course_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users_logs`
--
ALTER TABLE `users_logs`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `class_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `class_rooms`
--
ALTER TABLE `class_rooms`
  MODIFY `room_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `class_sessions`
--
ALTER TABLE `class_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `course_sessions`
--
ALTER TABLE `course_sessions`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `course_session_routines`
--
ALTER TABLE `course_session_routines`
  MODIFY `routine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `devices`
--
ALTER TABLE `devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `student_classes`
--
ALTER TABLE `student_classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users_logs`
--
ALTER TABLE `users_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_att_session` FOREIGN KEY (`session_id`) REFERENCES `course_sessions` (`session_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_att_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `class_sessions`
--
ALTER TABLE `class_sessions`
  ADD CONSTRAINT `fk_session_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE;

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE CASCADE;

--
-- Constraints for table `course_sessions`
--
ALTER TABLE `course_sessions`
  ADD CONSTRAINT `fk_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE;

--
-- Constraints for table `rfid_cards`
--
ALTER TABLE `rfid_cards`
  ADD CONSTRAINT `rfid_cards_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`);

--
-- Constraints for table `room_devices`
--
ALTER TABLE `room_devices`
  ADD CONSTRAINT `fk_room_devices_room` FOREIGN KEY (`room_id`) REFERENCES `class_rooms` (`room_id`) ON DELETE CASCADE;

--
-- Constraints for table `session_attendance`
--
ALTER TABLE `session_attendance`
  ADD CONSTRAINT `session_attendance_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `course_sessions` (`session_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `session_attendance_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_classes`
--
ALTER TABLE `student_classes`
  ADD CONSTRAINT `student_classes_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`),
  ADD CONSTRAINT `student_classes_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`class_id`);

--
-- Constraints for table `student_courses`
--
ALTER TABLE `student_courses`
  ADD CONSTRAINT `student_courses_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
