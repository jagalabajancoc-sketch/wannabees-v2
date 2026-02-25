-- Migration: Add columns to password_reset_otps table for settings OTP functionality
-- This adds support for tracking OTP action type (email/password) and metadata (new_email)

ALTER TABLE password_reset_otps
ADD COLUMN IF NOT EXISTS action VARCHAR(50) DEFAULT 'password_reset',
ADD COLUMN IF NOT EXISTS metadata JSON DEFAULT NULL;

-- Update existing records to have default action for backwards compatibility
UPDATE password_reset_otps SET action = 'password_reset' WHERE action IS NULL OR action = '';
