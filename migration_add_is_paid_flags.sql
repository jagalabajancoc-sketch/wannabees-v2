-- Add is_paid flag to orders and rental_extensions to track payment status
-- This allows multiple payment cycles during a single rental

-- Add is_paid column to orders table
ALTER TABLE `orders` 
ADD COLUMN `is_paid` TINYINT(1) NOT NULL DEFAULT 0 AFTER `assigned_at`;

-- Add is_paid column to rental_extensions table
ALTER TABLE `rental_extensions` 
ADD COLUMN `is_paid` TINYINT(1) NOT NULL DEFAULT 0 AFTER `extended_at`;

-- Mark all existing orders and extensions as unpaid (0) - they'll be marked paid when next payment is processed
-- No need to run UPDATE since DEFAULT 0 already sets them

-- Create index for faster queries on is_paid status
CREATE INDEX idx_orders_paid ON orders(rental_id, is_paid);
CREATE INDEX idx_extensions_paid ON rental_extensions(rental_id, is_paid);
