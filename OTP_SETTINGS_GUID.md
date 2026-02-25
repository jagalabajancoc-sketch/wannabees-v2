# OTP Verification for Settings Changes - Implementation Guide

## Overview
This implementation adds OTP (One-Time Password) verification for security-sensitive operations in cashier and owner settings:
- **Email changes**: Requires OTP sent to the new email address
- **Password changes**: Requires OTP sent to the current email address

## Files Modified/Created

### Backend API Endpoints
1. **[api/auth/send_settings_otp.php](api/auth/send_settings_otp.php)** - NEW
   - Generates and sends 6-digit OTP for email/password changes
   - Stores OTP in database with action type ('email' or 'password')
   - Sends email via PHP mail()

2. **[api/auth/verify_settings_otp.php](api/auth/verify_settings_otp.php)** - NEW
   - Verifies OTP code and expiry time
   - Marks OTP as used after verification
   - Returns success/error status

3. **[api/users/update_profile.php](api/users/update_profile.php)** - MODIFIED
   - Now requires OTP for email changes
   - Validates OTP before allowing email update
   - Only display_name updates don't require OTP

4. **[api/users/change_password.php](api/users/change_password.php)** - MODIFIED
   - Now requires OTP for ALL password changes
   - Validates OTP and current password before update

### Frontend UI Pages
1. **[cashier/settings.php](cashier/settings.php)** - UPDATED
   - Added OTP input fields in profile section
   - Added "Change Email" button to enable email editing
   - Two-step flow: Send OTP → Enter Code → Confirm
   - Password change now requires OTP verification

2. **[owner/settings.php](owner/settings.php)** - UPDATED  
   - Same OTP functionality as cashier settings
   - Updated navigation for owner role

### Database Migrations
1. **[migration_add_settings_otp_columns.sql](migration_add_settings_otp_columns.sql)** - NEW
   - Adds `action` column to password_reset_otps table
   - Adds `metadata` column for storing additional data (e.g., new_email)
   - Required before running the system

## Setup Instructions

### 1. Run Database Migrations

Execute the migration script to add required columns to password_reset_otps table:

```sql
-- Run this in phpMyAdmin or MySQL client:
ALTER TABLE password_reset_otps
ADD COLUMN IF NOT EXISTS action VARCHAR(50) DEFAULT 'password_reset',
ADD COLUMN IF NOT EXISTS metadata JSON DEFAULT NULL;

UPDATE password_reset_otps SET action = 'password_reset' WHERE action IS NULL OR action = '';
```

Or use the migration file:
```bash
mysql -u[username] -p[password] [database_name] < migration_add_settings_otp_columns.sql
```

### 2. Verify Email Configuration

The system uses PHP `mail()` function. Ensure:
1. Mail function is enabled in php.ini
2. SMTP is configured for your environment
3. For development, test with [test_gmail_smtp.php](test_gmail_smtp.php)

### 3. Test the Implementation

#### For Cashier Users:
1. Log in as cashier (role_id = 3)
2. Go to Settings → Profile Information
3. Click "Change Email" button
4. Enter new email and click "Save Changes"
5. Check email for OTP code
6. Enter OTP in verification field
7. Confirm to complete email change

#### For Security (Password Change):
1. In Settings → Security section
2. Fill in current password and new password
3. Click "Change Password" 
4. OTP will be sent to current email
5. Enter OTP code to complete password change

## User Flow Diagrams

### Email Change Flow
```
User clicks "Change Email" 
    ↓
Email field becomes editable
    ↓
User enters new email & clicks "Save Changes"
    ↓
Frontend calls send_settings_otp.php (action='email', new_email=...)
    ↓
OTP generated and sent to NEW email address
    ↓
OTP input field appears
    ↓
User checks email & enters OTP
    ↓
Frontend calls update_profile.php with otp code
    ↓
Backend validates OTP and updates email
    ↓
Success message & page reload

(If user cancels, "Cancel" button resets form)
```

### Password Change Flow
```
User enters password details & clicks "Change Password"
    ↓
Frontend validates password match & length
    ↓
Frontend calls send_settings_otp.php (action='password')
    ↓
OTP generated and sent to CURRENT email address
    ↓
OTP input field appears
    ↓
User checks email & enters OTP
    ↓
Frontend submits form with current_password, new_password, otp
    ↓
Backend validates OTP, verifies current password
    ↓
If valid, password is updated
    ↓
Success message, form resets
```

## API Endpoints

### POST /api/auth/send_settings_otp.php
**Request:**
```javascript
FormData: {
  action: 'email' | 'password',
  new_email: 'user@example.com' // required only for 'email' action
}
```

**Response Success:**
```json
{
  "success": true,
  "message": "OTP sent to user@example.com",
  "otp_expiry": 600
}
```

**Response Error:**
```json
{
  "success": false,
  "error": "Invalid action" | "New email is required" | "Failed to send OTP"
}
```

### POST /api/auth/verify_settings_otp.php
**Request:**
```javascript
FormData: {
  action: 'email' | 'password',
  otp_code: '123456'
}
```

**Response:**
```json
{
  "success": true,
  "metadata": { "new_email": "user@example.com" } // only for email action
}
```

### POST /api/users/update_profile.php (Modified)
**Request for email change (requires OTP):**
```javascript
FormData: {
  display_name: 'John Doe',
  email: 'newemail@example.com',
  otp: '123456'
}
```

**Request for display name only (no OTP needed):**
```javascript
FormData: {
  display_name: 'John Doe'
}
```

### POST /api/users/change_password.php (Modified)
**Request:**
```javascript
FormData: {
  current_password: 'oldpass123',
  new_password: 'newpass456',
  otp: '123456'
}
```

## Database Schema

### password_reset_otps Table
```sql
CREATE TABLE password_reset_otps (
    otp_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    action VARCHAR(50) DEFAULT 'password_reset',      -- NEW
    metadata JSON DEFAULT NULL,                       -- NEW
    INDEX idx_email (email),
    INDEX idx_otp (otp_code),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
```

**Column Descriptions:**
- `action`: Type of OTP use ('password_reset', 'email', 'password')
- `metadata`: JSON field storing additional data (e.g., `{"new_email": "user@example.com"}`)

## Security Features

1. **OTP Expiration**: 10 minutes (600 seconds)
2. **One-time Use**: OTP marked as used after verification
3. **Action-specific**: Different OTPs for email vs password changes
4. **Email Verification**: Email change requires verification to new email
5. **Current Password Check**: Password change requires current password validation
6. **Email Validation**: Check that new email isn't already used by another user

## Troubleshooting

### OTP Not Received
1. Check that mail() function is working: Run [test_gmail_smtp.php](test_gmail_smtp.php)
2. Verify SMTP configuration in php.ini
3. Check spam/junk folders
4. Verify new email address is correct

### OTP "Invalid or Expired"
1. Wait 10 minutes - OTP expires after 10 minutes
2. Request a new OTP (old one is deleted when new one is sent)
3. Check that code is entered correctly

### Database Errors
1. Run migration: `ALTER TABLE password_reset_otps ADD COLUMN IF NOT EXISTS action VARCHAR(50);`
2. Verify columns exist: `DESCRIBE password_reset_otps;`
3. Check that user_id foreign key exists

### Email Change Confirmation Issues
1. Ensure "Change Email" button is clicked first
2. New email must be entered before clicking "Save Changes"
3. Verify new email is unique (not used by another user)

## Testing Checklist

- [ ] Migration ran successfully (columns added to password_reset_otps)
- [ ] Email/SMTP is configured and working
- [ ] Cashier can navigate to Settings
- [ ] Cashier can click "Change Email" button
- [ ] Cashier can enter new email and receive OTP
- [ ] Cashier can enter OTP and complete email change
- [ ] Owner can change password with OTP verification
- [ ] Invalid OTP codes are rejected
- [ ] Expired OTPs are rejected (wait 10+ minutes)
- [ ] OTP cannot be reused
- [ ] Email already in use is rejected
- [ ] Password change requires current password verification

## Related Files
- [FORGOT_PASSWORD_GUIDE.md](FORGOT_PASSWORD_GUIDE.md) - Password reset OTP system
- [api/auth/email_templates.php](api/auth/email_templates.php) - Email template class
- [api/users/create_user_with_otp.php](api/users/create_user_with_otp.php) - First-time password setup

