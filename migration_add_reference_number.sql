-- Add reference_number column to payments table for GCash transactions
ALTER TABLE `payments` 
ADD COLUMN `reference_number` VARCHAR(100) DEFAULT NULL AFTER `payment_method`;
