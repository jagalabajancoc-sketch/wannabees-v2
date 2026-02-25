# First-Time Password Change - Setup Guide

## Overview
This feature ensures that newly created user accounts must change their password on first login for security purposes. Users cannot access the system until they change their temporary password.

## How It Works

### User Creation Flow:
1. **Owner creates user** → Fills form with email verification (OTP)
2. **System creates account** → Sets `must_change_password = 1` flag
3. **User logs in** → System detects the flag after authentication
4. **Forced redirect** → User sent to password change page
5. **Password changed** → Flag cleared, user can access system

### Security Features:
- ✅ User must authenticate with temporary password first
- ✅ Cannot bypass password change requirement
- ✅ Must create password different from temporary one
- ✅ Minimum 8 characters enforced
- ✅ Password confirmation required
- ✅ Real-time client-side validation
- ✅ Auto-redirect to appropriate dashboard after change

## Files Added/Modified

### New Files Created:
1. **`auth/first_time_password_change.php`** - Password change interface
2. **`api/users/first_time_password_change.php`** - Password change API endpoint

### Modified Files:
1. **`api/users/verify_otp_and_create_user.php`** - Sets `must_change_password = 1` when creating users
2. **`auth/auth.php`** - Checks flag after login and redirects if needed

## Database Schema

The `users` table already has the required column:
```sql
`must_change_password` TINYINT(1) DEFAULT 0
```

- `0` = Normal user, can login freely
- `1` = Must change password on next login

## Complete User Journey

### For Owners (Creating Users):
1. Go to Users page
2. Click "Add New User"
3. Fill in user details including email
4. Click "Send OTP"
5. Enter OTP received via email
6. Complete form and submit
7. **User created with temporary password and `must_change_password = 1`**

### For New Users (First Login):
1. Navigate to login page
2. Enter username and **temporary password** (provided by owner)
3. Click "Login"
4. **Automatically redirected to password change page**
5. Enter current (temporary) password
6. Create new password (min 8 characters)
7. Confirm new password
8. Submit
9. **Flag cleared, redirected to dashboard based on role**

## Password Requirements

✓ **Minimum 8 characters**
✓ **Must be different from temporary password**
✓ **Must match confirmation**
✓ **Should contain letters and numbers (recommended)**

## User Interface Features

### Password Change Page:
- Welcome message with username
- Security notice explaining requirement
- Three password fields:
  - Current (temporary) password
  - New password
  - Confirm new password
- Password visibility toggle (eye icon)
- Clear password requirements displayed
- Real-time validation
- Success/error messages
- Logout option

## API Endpoints

### `POST /api/users/first_time_password_change.php`

**Purpose:** Changes password for first-time users and clears the flag

**Authentication:** Session required (`user_id`)

**Request Parameters:**
- `current_password` (string, required) - Current temporary password
- `new_password` (string, required) - New password (min 8 chars)

**Response:**
```json
{
  "success": true,
  "message": "Password changed successfully",
  "redirect": "../owner/dashboard.php"
}
```

**Error Response:**
```json
{
  "success": false,
  "error": "Current password is incorrect"
}
```

## Security Considerations

### Protection Against:
1. **Bypassing password change** - Session check + database flag validation
2. **Reusing temporary password** - Comparison validation
3. **Weak passwords** - 8 character minimum enforced
4. **Unauthorized access** - Must be logged in but with flag set
5. **Multiple attempts** - No account lockout (uses existing security)

### Best Practices:
- Temporary passwords should be strong and random
- Owner should securely communicate temporary password
- Consider adding password strength indicator
- Consider adding password expiry for temporary passwords

## Testing the Feature

### Test Case 1: New User Creation
1. Create new user with email verification
2. Verify `must_change_password = 1` in database
3. Verify user receives temporary password

### Test Case 2: First Login
1. Login with temporary credentials
2. Verify redirect to password change page
3. Verify cannot access other pages without changing password

### Test Case 3: Password Change
1. Enter incorrect current password → Should show error
2. Enter same password as current → Should show error
3. Enter password < 8 chars → Should show error
4. Enter mismatched passwords → Should show error
5. Enter valid new password → Should succeed and redirect

### Test Case 4: Post-Change Access
1. Verify `must_change_password = 0` in database
2. Logout and login with new password
3. Verify direct access to dashboard (no redirect)

## Troubleshooting

### User Cannot Login After Password Change
**Cause:** Session might be stale
**Solution:** Clear browser cache and cookies, try again

### Redirect Loop
**Cause:** Flag not cleared after password change
**Solution:** Check database, manually set `must_change_password = 0`

### "Unauthorized" Error
**Cause:** Session not set properly
**Solution:** Ensure login successful before accessing password change page

### Password Won't Change
**Cause:** Current password incorrect
**Solution:** Verify the temporary password is correct

## Future Enhancements

- [ ] Password strength meter
- [ ] Temporary password expiry (e.g., expires in 24 hours)
- [ ] Email notification with temporary password
- [ ] Password history (prevent reusing old passwords)
- [ ] Account activation link via email
- [ ] Two-factor authentication for new accounts
- [ ] Configurable password policy (complexity requirements)

## Integration with Existing Features

### Works With:
✅ Email verification system
✅ Role-based access control
✅ Session management
✅ Password reset functionality

### Compatible With:
✅ Owner dashboard user creation
✅ All user roles (Owner, Cashier, Customer)
✅ Mobile responsive design
✅ Existing authentication flow

## Summary

This feature adds an essential security layer by forcing new users to change their temporary password on first login. The implementation:

1. **Seamless** - Automatically detects and redirects
2. **Secure** - Validates current password and enforces requirements
3. **User-friendly** - Clear instructions and visual feedback
4. **Role-aware** - Redirects to correct dashboard after change
5. **Maintainable** - Simple flag-based logic, easy to debug

The user experience is smooth and the security is robust, ensuring that all new accounts start with a user-chosen password rather than a temporary one.
