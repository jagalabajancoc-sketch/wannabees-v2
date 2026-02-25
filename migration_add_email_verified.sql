-- Migration: Add email_verified column to users table
-- Date: 2026-02-24
-- Description: Adds email_verified flag to track if user's email has been verified

ALTER TABLE `users` ADD COLUMN `email_verified` TINYINT(1) DEFAULT 0 AFTER `email`;

-- Set existing users with emails as verified (backward compatibility)
UPDATE `users` SET `email_verified` = 1 WHERE `email` IS NOT NULL AND `email` != '';

-- Note: The user_creation_otps table will be created automatically by the API when first used
