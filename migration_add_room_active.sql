-- Migration: Add is_active column to rooms table
-- This allows owners to deactivate rooms without deleting them

ALTER TABLE `rooms` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `status`;

-- Update existing data
UPDATE `rooms` SET `is_active` = 1 WHERE `is_active` IS NULL;
