 -- Database schema for admin panel
-- Run this SQL script to set up the required tables

-- Create admins table
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin (email: admin@learninindia.com, password: admin123)
-- Change this password immediately after first login!
-- To generate a new hash, run: php generate_admin_hash.php
INSERT INTO `admins` (`email`, `password`) 
VALUES ('admin@learninindia.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy')
ON DUPLICATE KEY UPDATE email=email;

-- Add profile_image and certificate_path columns to users table
-- Note: If columns already exist, you'll get an error - that's okay, just skip these lines
-- For MySQL 5.7+, you can check first:
-- SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'users' AND COLUMN_NAME = 'profile_image';

ALTER TABLE `users` 
ADD COLUMN `profile_image` VARCHAR(255) DEFAULT NULL;

ALTER TABLE `users` 
ADD COLUMN `certificate_path` VARCHAR(255) DEFAULT NULL;

-- Note: The password hash above is for 'admin123'
-- You should change this after first login!

