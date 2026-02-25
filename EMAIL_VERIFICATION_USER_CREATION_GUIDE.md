# Email Verification for User Creation - Setup Guide

## Overview
This feature adds email verification with OTP (One-Time Password) when creating new users in the owner dashboard. Users must verify their email address before the account can be created.

## Features Added

### 1. API Endpoints
- **`api/users/send_user_creation_otp.php`**: Sends OTP to the user's email
- **`api/users/verify_otp_and_create_user.php`**: Verifies OTP and creates the user account

### 2. Database Tables
Two new tables will be created automatically when first used:
- **`user_creation_otps`**: Stores OTPs for email verification during user creation
- **`email_verified`** column added to `users` table

### 3. User Interface Updates
- Email address field in the user creation form
- "Send OTP" button to send verification code
- OTP input field that appears after sending OTP
- Real-time status messages for success/error feedback

## Setup Instructions

### Step 1: Run Database Migration
Run the following SQL migration to add the `email_verified` column:

```bash
# Navigate to your MySQL and run:
mysql -u root wannabees < migration_add_email_verified.sql
```

Or execute directly in phpMyAdmin:
```sql
ALTER TABLE `users` ADD COLUMN `email_verified` TINYINT(1) DEFAULT 0 AFTER `email`;
UPDATE `users` SET `email_verified` = 1 WHERE `email` IS NOT NULL AND `email` != '';
```

### Step 2: Configure Email Settings
Update the email credentials in both API files:
- `api/users/send_user_creation_otp.php` (lines 93-94)

Replace with your Gmail SMTP credentials:
```php
$mail->Username   = 'your-email@gmail.com';
$mail->Password   = 'your-app-password';
```

**Note**: You need to create a Gmail App Password (not your regular password):
1. Go to Google Account settings
2. Security → 2-Step Verification
3. App passwords → Generate new app password
4. Copy and use this password in the code

### Step 3: Test the Feature
1. Login as owner
2. Go to Users page
3. Click "Add New User"
4. Fill in the form including email address
5. Click "Send OTP" button
6. Check your email for the 6-digit OTP
7. Enter the OTP in the form
8. Complete the rest of the user details
9. Click "Create User"

## How It Works

### Workflow:
1. **Owner enters user details** → Fills username, email, display name, role
2. **Click "Send OTP"** → System sends 6-digit code to email
3. **OTP field appears** → Owner enters the code received via email
4. **Submit form** → System verifies OTP and creates user
5. **Password auto-generated** → Secure 12-character password created automatically
6. **Success modal** → Shows generated username and password to owner
7. **Email sent** → Password sent to user's email address
8. **First Login** → New user must change their password (see security note below)

### Security Features:
- OTP expires after 15 minutes
- OTP can only be used once
- Email must be unique in the system
- Only owners (role_id = 1) can create users
- Previous unused OTPs are deleted when new one is requested
- **Secure passwords auto-generated** (12 chars, mixed case, numbers, symbols)
- **Passwords emailed to users** with clear instructions
- **New users must change their password on first login** (see First-Time Password Change section)

### Email Verification Status:
- New users created with OTP: `email_verified = 1` and `must_change_password = 1`
- Auto-generated secure password sent to user's email
- Existing users (after migration): `email_verified = 1` (if they have email)
- Users created without email: `email_verified = 0`

## First-Time Password Change

**Important:** All newly created users will be required to change their password on first login for security purposes.

### Automatic Password Generation:
- System automatically generates a secure 12-character password when creating a user
- Password is displayed to the owner in a success modal (with copy-to-clipboard)
- Password is emailed to the user at their verified email address
- No need for owner to manually create passwords

### What happens:
1. New user receives email with temporary password
2. User logs in with username and temporary password
3. System automatically redirects to password change page
4. User must enter current (temporary) password and create new one
5. After successful password change, user can access the system

### For detailed information:
- See [AUTO_GENERATED_PASSWORD_GUIDE.md](AUTO_GENERATED_PASSWORD_GUIDE.md) for password generation details
- See [FIRST_TIME_PASSWORD_CHANGE_GUIDE.md](FIRST_TIME_PASSWORD_CHANGE_GUIDE.md) for complete documentation on the first-time password change feature.

## Troubleshooting

### OTP Email Not Received
1. Check spam/junk folder
2. Verify Gmail SMTP credentials in the API file
3. Check PHP error logs for detailed error messages
4. Ensure Gmail account has "Less secure app access" enabled OR use App Password

### Database Errors
1. Ensure migration was run successfully
2. Check that `users` table has `email_verified` column
3. Verify `user_creation_otps` table was created

### Form Validation Errors
- "Please send OTP first" → Must click "Send OTP" button before submitting
- "Invalid OTP" → Check the code entered matches the email
- "OTP expired" → Request new OTP (valid for 15 minutes only)
- "Email already exists" → Use a different email address

## Future Enhancements
- Add email verification for existing users
- Resend OTP functionality with countdown timer
- Email template customization
- SMS OTP option
- Rate limiting for OTP requests

## Files Modified
1. `owner/users.php` - Added email field and OTP verification UI
2. `api/users/send_user_creation_otp.php` - New API endpoint (created)
3. `api/users/verify_otp_and_create_user.php` - New API endpoint (created)
4. `migration_add_email_verified.sql` - Database migration (created)
5. `auth/first_time_password_change.php` - Password change page (created)
6. `api/users/first_time_password_change.php` - Password change API (created)
7. `auth/auth.php` - Updated to redirect new users to password change

## Support
For issues or questions, check the PHP error logs or debug console in your browser.
