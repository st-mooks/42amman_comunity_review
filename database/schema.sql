
CREATE DATABASE IF NOT EXISTS voting_system_42;
USE voting_system_42;


CREATE TABLE IF NOT EXISTS users (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(150) NOT NULL,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('student', 'staff') NOT NULL DEFAULT 'student',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_status` (`status`)
);


CREATE TABLE IF NOT EXISTS voting_windows (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME NOT NULL,
    `min_votes_per_student` INT NOT NULL DEFAULT 1,
    `max_votes_per_student` INT NOT NULL DEFAULT 5,
    `status` ENUM('upcoming', 'active', 'closed') NOT NULL DEFAULT 'upcoming',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_voting_time_order` CHECK (`end_time` > `start_time`),
    CONSTRAINT `chk_votes_limit_positive` CHECK (`min_votes_per_student` >= 0 AND `max_votes_per_student` >= `min_votes_per_student`),
    INDEX `idx_voting_window_status` (`status`),
    INDEX `idx_voting_window_dates` (`start_time`, `end_time`)
);


CREATE TABLE IF NOT EXISTS ratings (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `voter_id` INT NOT NULL,
    `voter_to_id` INT NOT NULL,
    `voting_window_id` INT NOT NULL,
    `stars` TINYINT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('active', 'flagged', 'cancelled') NOT NULL DEFAULT 'active',
    CONSTRAINT `chk_no_self_rating` CHECK (`voter_id` <> `voter_to_id`),
    CONSTRAINT `chk_stars_range` CHECK (`stars` BETWEEN 1 AND 3),
    CONSTRAINT `unique_voter_per_window` UNIQUE (`voter_id`, `voter_to_id`, `voting_window_id`),
    CONSTRAINT `fk_ratings_voter` FOREIGN KEY (`voter_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ratings_voter_to` FOREIGN KEY (`voter_to_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ratings_voting_window` FOREIGN KEY (`voting_window_id`) REFERENCES `voting_windows`(`id`) ON DELETE CASCADE,
    INDEX `idx_ratings_window` (`voting_window_id`),
    INDEX `idx_ratings_status` (`status`)
);


CREATE TABLE IF NOT EXISTS labels (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `stars_level` TINYINT NOT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_label_stars_level` CHECK (`stars_level` BETWEEN 1 AND 3),
    INDEX `idx_labels_stars` (`stars_level`),
    INDEX `idx_labels_status` (`status`)
);


CREATE TABLE IF NOT EXISTS rating_labels (
    `rating_id` INT NOT NULL,
    `label_id` INT NOT NULL,
    PRIMARY KEY (`rating_id`, `label_id`),
    CONSTRAINT `fk_rating_labels_rating` FOREIGN KEY (`rating_id`) REFERENCES `ratings`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rating_labels_label` FOREIGN KEY (`label_id`) REFERENCES `labels`(`id`) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS abuse_flags (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `abuse_type` VARCHAR(100) NOT NULL,
    `reason_explanation` TEXT NOT NULL,
    `detected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `review_status` ENUM('pending', 'reviewed', 'rejected', 'confirmed') NOT NULL DEFAULT 'pending',
    `reviewed_by_staff_id` INT NULL,
    `reviewed_at` DATETIME NULL,
    CONSTRAINT `fk_abuse_flags_staff` FOREIGN KEY (`reviewed_by_staff_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_abuse_review_status` (`review_status`)
);


CREATE TABLE IF NOT EXISTS abuse_flag_students (
    `abuse_flag_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `role_in_activity` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`abuse_flag_id`, `student_id`),
    CONSTRAINT `fk_abuse_flag_students_flag` FOREIGN KEY (`abuse_flag_id`) REFERENCES `abuse_flags`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_abuse_flag_students_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS audit_logs (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `staff_id` INT NOT NULL,
    `action_type` VARCHAR(100) NOT NULL,
    `target_id` INT NULL,
    `description` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_logs_staff` FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_audit_logs_staff` (`staff_id`),
    INDEX `idx_audit_logs_action` (`action_type`)
);


INSERT INTO labels (`title`, `description`, `stars_level`, `status`) VALUES
('Clean Code', 'كود نظيف ومنظم', 3, 'active'),
('Good Collaboration', 'تعاون ممتاز مع الفريق', 2, 'active'),
('Helpful Peer', 'مساعدة زملاء الدراسة', 1, 'active')
ON DUPLICATE KEY UPDATE `title`=`title`;

