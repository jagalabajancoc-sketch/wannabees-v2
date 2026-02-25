-- Migration: Remove Mid-tier and update Premium pricing
-- Keep only Regular and Premium room types
-- Premium will be 199

-- Update Premium (room_type_id = 3) to have price 199
UPDATE `room_types` SET `type_name` = 'Premium', `price_per_hour` = 199.00, `price_per_30min` = 100.00 WHERE `room_type_id` = 3;

-- Delete Mid-tier (room_type_id = 2)
DELETE FROM `room_types` WHERE `room_type_id` = 2;
