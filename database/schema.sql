-- =====================================================================
-- 42 Amman Peer Rating Platform — database schema (MariaDB / MySQL)
--
-- Import into an EMPTY database you already created, e.g.:
--   mariadb -u rating -p voting_system_42 < database/schema.sql
-- Then load demo data with:  php database/seed.php
--
-- Original design: Raghad (ERD + tables). Final-phase changes are marked
-- with "CHANGED:" or "NEW:" so the team can see what moved and why.
-- =====================================================================

SET NAMES utf8mb4;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- A voting window is "open" when status = 'active' AND NOW() is between
-- start_time and end_time. Staff can close it early by setting 'closed'.
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- status: active    = counts toward the student's average
--         flagged   = suspicious, still counts until staff decide
--         cancelled = invalidated by staff (excluded from averages)
CREATE TABLE IF NOT EXISTS ratings (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `voter_id` INT NOT NULL,
    `voter_to_id` INT NOT NULL,
    `voting_window_id` INT NOT NULL,
    `stars` TINYINT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('active', 'flagged', 'cancelled') NOT NULL DEFAULT 'active',
    -- NEW: who invalidated the vote and when (audit trail for staff)
    `invalidated_by` INT NULL,
    `invalidated_at` DATETIME NULL,
    CONSTRAINT `chk_no_self_rating` CHECK (`voter_id` <> `voter_to_id`),
    CONSTRAINT `chk_stars_range` CHECK (`stars` BETWEEN 1 AND 3),
    CONSTRAINT `unique_voter_per_window` UNIQUE (`voter_id`, `voter_to_id`, `voting_window_id`),
    CONSTRAINT `fk_ratings_voter` FOREIGN KEY (`voter_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ratings_voter_to` FOREIGN KEY (`voter_to_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ratings_voting_window` FOREIGN KEY (`voting_window_id`) REFERENCES `voting_windows`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ratings_invalidated_by` FOREIGN KEY (`invalidated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_ratings_window` (`voting_window_id`),
    INDEX `idx_ratings_status` (`status`),
    INDEX `idx_ratings_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- CHANGED: a label can now be offered for a RANGE of star levels
-- (min_stars..max_stars) instead of exactly one level. The spec says
-- 2-star ratings get "some of the 3-star labels" and 1-star ratings also get
-- the small-issue labels, so one label must be able to appear at several levels.
-- NEW: category drives the colour in the UI and the "serious vote" abuse check.
CREATE TABLE IF NOT EXISTS labels (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `category` ENUM('positive', 'minor', 'serious') NOT NULL DEFAULT 'positive',
    `min_stars` TINYINT NOT NULL,
    `max_stars` TINYINT NOT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `chk_label_stars_range` CHECK (`min_stars` BETWEEN 1 AND 3 AND `max_stars` BETWEEN 1 AND 3 AND `min_stars` <= `max_stars`),
    INDEX `idx_labels_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- CHANGED: label FK is RESTRICT (was CASCADE). Deleting a label used to wipe it
-- silently from past ratings; now labels are disabled (status = 'inactive') instead.
CREATE TABLE IF NOT EXISTS rating_labels (
    `rating_id` INT NOT NULL,
    `label_id` INT NOT NULL,
    PRIMARY KEY (`rating_id`, `label_id`),
    CONSTRAINT `fk_rating_labels_rating` FOREIGN KEY (`rating_id`) REFERENCES `ratings`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rating_labels_label` FOREIGN KEY (`label_id`) REFERENCES `labels`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- NEW: every vote ATTEMPT is logged, including rejected ones (duplicate,
-- over the limit, window closed...). Without this, "repeated votes" and
-- "too fast / too often" could not be detected, because rejected votes
-- never reach the ratings table.
CREATE TABLE IF NOT EXISTS vote_attempts (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `voter_id` INT NOT NULL,
    `target_id` INT NULL,
    `voting_window_id` INT NULL,
    `outcome` VARCHAR(30) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_attempts_voter` FOREIGN KEY (`voter_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_attempts_window` (`voting_window_id`),
    INDEX `idx_attempts_pair` (`voter_id`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Abuse cases are DETECTED live (api/staff/abuse.php). This table stores the
-- staff DECISION about each case, keyed by a stable case_key, so a case that
-- was dismissed or confirmed keeps that status every time detection re-runs.
CREATE TABLE IF NOT EXISTS abuse_flags (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    -- NEW: stable identifier of a detected case, e.g. "pair:1:12-15"
    `case_key` VARCHAR(191) NULL UNIQUE,
    `abuse_type` VARCHAR(100) NOT NULL,
    `reason_explanation` TEXT NOT NULL,
    `detected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `review_status` ENUM('pending', 'reviewed', 'rejected', 'confirmed') NOT NULL DEFAULT 'pending',
    `reviewed_by_staff_id` INT NULL,
    `reviewed_at` DATETIME NULL,
    CONSTRAINT `fk_abuse_flags_staff` FOREIGN KEY (`reviewed_by_staff_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_abuse_review_status` (`review_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS abuse_flag_students (
    `abuse_flag_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `role_in_activity` VARCHAR(100) NOT NULL,
    PRIMARY KEY (`abuse_flag_id`, `student_id`),
    CONSTRAINT `fk_abuse_flag_students_flag` FOREIGN KEY (`abuse_flag_id`) REFERENCES `abuse_flags`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_abuse_flag_students_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- NEW: which exact votes belong to a case, so staff can invalidate them.
CREATE TABLE IF NOT EXISTS abuse_flag_ratings (
    `abuse_flag_id` INT NOT NULL,
    `rating_id` INT NOT NULL,
    PRIMARY KEY (`abuse_flag_id`, `rating_id`),
    CONSTRAINT `fk_abuse_flag_ratings_flag` FOREIGN KEY (`abuse_flag_id`) REFERENCES `abuse_flags`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_abuse_flag_ratings_rating` FOREIGN KEY (`rating_id`) REFERENCES `ratings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- Labels from the hackathon spec.
--   positive (3★, some also at 2★) · minor issues (2★ and 1★) · serious (1★ only)
-- The first three rows are the team's original seed labels, kept and
-- corrected ("Helpful Peer" was a 1-star label by mistake).
-- ---------------------------------------------------------------------
INSERT INTO labels (`title`, `description`, `category`, `min_stars`, `max_stars`) VALUES
('Clean Code',            'كود نظيف ومنظم',                     'positive', 3, 3),
('Good Collaboration',    'تعاون ممتاز مع الفريق',               'positive', 2, 3),
('Helpful Peer',          'مساعدة زملاء الدراسة',                'positive', 2, 3),
('Helpful',               'Goes out of their way to help',       'positive', 2, 3),
('Good teammate',         'Easy and reliable to work with',      'positive', 2, 3),
('Community contributor', 'Gives back to the 42 community',      'positive', 3, 3),
('Respectful',            'Respectful in every interaction',     'positive', 2, 3),
('Fair',                  'Fair, honest evaluations',            'positive', 2, 3),
('Thorough',              'Careful and detailed',                'positive', 3, 3),
('Clear explainer',       'Explains concepts clearly',           'positive', 3, 3),
('Helpful feedback',      'Feedback that actually helps',        'positive', 3, 3),
('Well prepared',         'Came ready for the evaluation',       'positive', 3, 3),
('Knows their code',      'Understands every line they wrote',   'positive', 3, 3),
('Open to feedback',      'Takes criticism well',                'positive', 2, 3),
('Unreliable teammate',   'Hard to count on',                    'minor',    1, 2),
('Disruptive',            'Disturbs others while they work',     'minor',    1, 2),
('Rushed',                'Hurried through the evaluation',      'minor',    1, 2),
('Late',                  'Arrived late',                        'minor',    1, 2),
('Defensive',             'Rejects feedback',                    'minor',    1, 2),
('Aggressive behavior',   'Hostile or threatening behavior',     'serious',  1, 1),
('Disrespectful',         'Insulting or demeaning',              'serious',  1, 1),
('Bad evaluator',         'Evaluation was unfair or careless',   'serious',  1, 1),
('Unprepared',            'Came to the evaluation unprepared',   'serious',  1, 1)
ON DUPLICATE KEY UPDATE `title` = `title`;
