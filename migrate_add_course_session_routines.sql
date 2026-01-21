-- Creates recurring weekly routines for auto-generating course sessions.
CREATE TABLE IF NOT EXISTS `course_session_routines` (
  `routine_id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Sun',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `grace_minutes` int(11) DEFAULT 10,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`routine_id`),
  KEY `idx_dow` (`day_of_week`),
  KEY `idx_active` (`is_active`),
  KEY `idx_course` (`course_id`),
  KEY `idx_room` (`room_id`),
  CONSTRAINT `fk_routine_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_routine_room` FOREIGN KEY (`room_id`) REFERENCES `class_rooms` (`room_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
