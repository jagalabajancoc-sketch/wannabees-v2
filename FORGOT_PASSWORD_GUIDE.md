# Forgot Password Feature - Implementation Guide

## Overview
The forgot password feature allows users to reset their password using an OTP (One-Time Password) sent to their registered email address.

## Files Created

### 1. Frontend
- **auth/forgot_password.php** - Two-step password reset interface
  - Step 1: Enter email to request OTP
  - Step 2: Enter OTP, new password, and confirm password
  - Features 90-second countdown timer
  - Auto-resend option when timer expires

### 2. Backend API
- **api/auth/send_otp.php** - Generates and sends 6-digit OTP via email
  - Validates email exists in database
  - Creates OTP with 15-minute expiry
  - Sends OTP to user's registered email
  
- **api/auth/reset_password.php** - Verifies OTP and updates password
  - Validates OTP is unused and not expired
  - Hashes new password securely
  - Marks OTP as used after successful reset

### 3. Database
- **migration_create_password_reset_otps.sql** - SQL migration file
  - Creates `password_reset_otps` table
  - Stores OTP codes with expiry tracking
  - Auto-creates on first use if not exists

### 4. Login Page Update
- **index.php** - Added "Forgot Password?" link below password field

## User Flow

1. **Request OTP**
   - User clicks "Forgot Password?" on login page
   - Enters registered email address
   - Clicks "SEND OTP"
   - System generates 6-digit OTP and sends it via email
   - Success message displayed

2. **Reset Password**
   - User checks email inbox for OTP
   - Enters OTP received via email
   - Creates new password (minimum 6 characters)
   - Confirms new password
   - Clicks "Submit"
   - 90-second timer counts down during this process

3. **Timer Expiry**
   - When timer reaches 0, "Resend OTP" option appears
   - Clicking resend returns to Step 1
   - User can request a new OTP via email

## Security Features

- ✅ OTP expires after 15 minutes
- ✅ OTP can only be used once
- ✅ OTP sent securely via email
- ✅ Passwords are hashed using PHP's password_hash()
- ✅ Email validation prevents invalid entries
- ✅ Old OTPs are deleted when new ones are generated
- ✅ Generic error messages prevent email enumeration

## Testing

**Email Configuration Required:**

The system uses PHP's built-in `mail()` function. Ensure your server has a mail transfer agent (MTA) configured:

- **XAMPP/Local Development**: Install and configure a mail server like [Papercut](https://github.com/ChangemakerStudios/Papercut-SMTP) or [MailHog](https://github.com/mailhog/MailHog) for testing
- **Production**: Ensure server's mail service is properly configured

### Test Flow:
1. Navigate to http://localhost/wannabees-v1-main/
2. Click "Forgot Password?"
3. Enter a registered email from your database
4. Click "SEND OTP"
5. Check email inbox for OTP (6-digit code)
6. Enter the OTP and set a new password
7. Login with the new password

## Production Deployment

✅ **Email sending is already configured!** The system uses PHP's `mail()` function with proper headers:

- From: noreply@wannabeesktv.com
- Reply-To: support@wannabeesktv.com
- Professional email template with company branding

### Recommended Email Service Upgrades

For better deliverability and tracking, consider upgrading to:

1. **PHPMailer** - More reliable SMTP support
2. **SendGrid** - Email API with tracking and analytics
3. **AWS SES** - Scalable email service
4. **Mailgun** - Developer-friendly email API

Example PHPMailer implementation available upon request.

## Database Cleanup

To automatically clean expired OTPs, add a cron job:

```sql
DELETE FROM password_reset_otps 
WHERE expires_at < NOW() OR is_used = 1;
```

Run this daily or weekly to keep the table clean.

## Troubleshooting

**Issue:** OTP not working
- Check that email exists in users table
- Verify user account is active (is_active = 1)
- Ensure OTP hasn't expired (15-minute window)
- Check database for password_reset_otps table

**Issue:** Timer not counting down
- Check browser console for JavaScript errors
- Ensure page isn't being refreshed

**Issue:** Password not updating
- Verify OTP is correct and unused
- Check password meets minimum 6 characters
- Ensure passwords match in both fields

## Design Consistency

The forgot password page matches the existing Wannabees KTV design:
- Orange gradient background (#f5c542 to #f2a20a)
- Blue primary buttons (#0d6efd)
- White card with rounded corners
- Font Awesome icons throughout
- Responsive design for mobile devices
