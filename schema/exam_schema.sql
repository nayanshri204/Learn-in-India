-- Database schema for Online Exam Module
-- Run this SQL script to set up the required tables for the exam system

-- Create exams table
CREATE TABLE IF NOT EXISTS `exams` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `duration_minutes` INT(11) NOT NULL,
  `start_date` DATETIME NOT NULL,
  `end_date` DATETIME NOT NULL,
  `total_marks` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `passing_marks` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_start_date` (`start_date`),
  KEY `idx_end_date` (`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create exam_questions table
CREATE TABLE IF NOT EXISTS `exam_questions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `exam_id` INT(11) NOT NULL,
  `question_text` TEXT NOT NULL,
  `option_a` VARCHAR(500) NOT NULL,
  `option_b` VARCHAR(500) NOT NULL,
  `option_c` VARCHAR(500) NOT NULL,
  `option_d` VARCHAR(500) NOT NULL,
  `correct_answer` ENUM('A', 'B', 'C', 'D') NOT NULL,
  `marks` DECIMAL(10,2) NOT NULL DEFAULT 1,
  `question_order` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_exam_id` (`exam_id`),
  CONSTRAINT `fk_exam_questions_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create exam_assignments table
CREATE TABLE IF NOT EXISTS `exam_assignments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `exam_id` INT(11) NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `course` VARCHAR(100) DEFAULT NULL,
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_exam_user` (`exam_id`, `user_id`),
  UNIQUE KEY `unique_exam_course` (`exam_id`, `course`),
  KEY `idx_exam_id` (`exam_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_course` (`course`),
  CONSTRAINT `fk_exam_assignments_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_assignments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create exam_attempts table
CREATE TABLE IF NOT EXISTS `exam_attempts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `exam_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `started_at` DATETIME NOT NULL,
  `submitted_at` DATETIME DEFAULT NULL,
  `time_spent_seconds` INT(11) DEFAULT NULL,
  `total_marks` DECIMAL(10,2) DEFAULT NULL,
  `obtained_marks` DECIMAL(10,2) DEFAULT NULL,
  `status` ENUM('in_progress', 'submitted', 'timeout') DEFAULT 'in_progress',
  PRIMARY KEY (`id`),
  KEY `idx_exam_id` (`exam_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_exam_attempts_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_attempts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create exam_answers table
CREATE TABLE IF NOT EXISTS `exam_answers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` INT(11) NOT NULL,
  `question_id` INT(11) NOT NULL,
  `selected_answer` ENUM('A', 'B', 'C', 'D') DEFAULT NULL,
  `is_correct` TINYINT(1) DEFAULT NULL,
  `marks_obtained` DECIMAL(10,2) DEFAULT 0,
  `answered_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attempt_question` (`attempt_id`, `question_id`),
  KEY `idx_attempt_id` (`attempt_id`),
  KEY `idx_question_id` (`question_id`),
  CONSTRAINT `fk_exam_answers_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_exam_answers_question` FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
