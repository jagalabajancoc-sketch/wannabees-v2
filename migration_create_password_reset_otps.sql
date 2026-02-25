-- Migration: Create password_reset_otps table for forgot password functionality
-- Date: 2025
-- Description: Stores OTP codes for password reset functionality with expiry

CREATE TABLE IF NOT EXISTS password_reset_otps (
    otp_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    INDEX idx_email (email),
    INDEX idx_otp (otp_code),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Note: This table is automatically created by the send_otp.php API if it doesn't exist,
-- but this migration can be run manually if preferred.
