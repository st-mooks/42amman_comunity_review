CREATE DATABASE IF NOT EXISTS voitng_42;
USE voitng_42;


CREATE TABLE IF NOT EXISTS user (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role` VARCHAR(50),
    `passowrd` VARCHAR(255),
    `email` VARCHAR(255),
    `first_name` VARCHAR(100),
    `last_name` VARCHAR(100),
    `intra_name` VARCHAR(100)
);


CREATE TABLE IF NOT EXISTS user_r (
    `id` INT PRIMARY KEY,
    `phone_number` VARCHAR(50),
    CONSTRAINT `fk_user_r_user` FOREIGN KEY (`id`) REFERENCES `user`(`id`) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS voitng_window (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255),
    `stutes` VARCHAR(50),
    `max_votes` INT,
    `min_votes` INT,
    `start_time` DATETIME,
    `end_time` DATETIME
);


CREATE TABLE IF NOT EXISTS labels (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255),
    `stars` INT,
    `statues` VARCHAR(50)
);


CREATE TABLE IF NOT EXISTS flag (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `flag_type` VARCHAR(100),
    `severity` VARCHAR(50),
    `status` VARCHAR(50),
    `created_at` DATETIME
);


CREATE TABLE IF NOT EXISTS rating (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `voliter_id` INT,
    `voliter_to_id` INT,
    `status` VARCHAR(50),
    `stars` INT,
    CONSTRAINT `fk_rating_voliter` FOREIGN KEY (`voliter_id`) REFERENCES `user`(`id`),
    CONSTRAINT `fk_rating_voliter_to` FOREIGN KEY (`voliter_to_id`) REFERENCES `user`(`id`)
);

CREATE TABLE IF NOT EXISTS rating_labels (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `rating_id` INT,
    `label_id` INT,
    CONSTRAINT `fk_rating_labels_rating` FOREIGN KEY (`rating_id`) REFERENCES `rating`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rating_labels_label` FOREIGN KEY (`label_id`) REFERENCES `labels`(`id`) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS audit_logs (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT,
    `action` VARCHAR(255),
    `target_id` INT,
    `target_type` VARCHAR(100),
    `created_at` DATETIME,
    CONSTRAINT `fk_audit_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `user`(`id`)
);

CREATE TABLE IF NOT EXISTS abuse_flag_students (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `abuse_flag_id` INT,
    CONSTRAINT `fk_abuse_user` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_abuse_flag` FOREIGN KEY (`abuse_flag_id`) REFERENCES `flag`(`id`) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS auth_tokens (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `expires_at` DATETIME,
    `created_at` DATETIME,
    `user_id` INT,
    `token` VARCHAR(255),
    CONSTRAINT `fk_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `user`(`id`) ON DELETE CASCADE
);


INSERT INTO user (`role`, `passowrd`, `email`, `first_name`, `last_name`, `intra_name`) VALUES
('admin', '123456', 'admin@42.jo', 'Raghad', 'Alshrawneh', 'ralshraw'),
('student', 'password123', 'student1@42.jo', 'Ahmad', 'Ali', 'aali'),
('student', 'password456', 'student2@42.jo', 'Sara', 'Mohammad', 'smohammad');


INSERT INTO user_r (`id`, `phone_number`) VALUES
(1, '+962790000001'),
(2, '+962780000002'),
(3, '+962770000003');

-- 3. إضافة نافذة تصويت في جدول voitng_window
INSERT INTO voitng_window (`title`, `stutes`, `max_votes`, `min_votes`, `start_time`, `end_time`) VALUES
('Best Project Vote 2026', 'active', 5, 1, '2026-11-01 08:00:00', '2026-11-10 23:59:59');


INSERT INTO labels (`title`, `stars`, `statues`) VALUES
('Clean Code', 5, 'active'),
('Great UI Design', 4, 'active'),
('Fast Performance', 5, 'active');
-- هاي دانا عشان   لو بدك تشوفي شو داخل جداول بسشيلي الكومنت 
-- SELECT * FROM user;
-- SELECT * FROM user_r;
-- select * from labels;