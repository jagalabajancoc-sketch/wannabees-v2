-- Migration: Update cleaning_logs to store staff name and cashier instead of staff_id
-- This removes the dependency on the deleted staff role

-- Drop the foreign key constraint on staff_id
ALTER TABLE `cleaning_logs` DROP FOREIGN KEY `cleaning_logs_ibfk_2`;

-- Modify the table structure
ALTER TABLE `cleaning_logs`
  DROP COLUMN `staff_id`,
  ADD COLUMN `staff_name` VARCHAR(100) NOT NULL AFTER `room_id`,
  ADD COLUMN `verified_by` VARCHAR(100) NULL AFTER `staff_name`;

-- The table will now have: cleaning_id, room_id, staff_name, verified_by, cleaned_at
