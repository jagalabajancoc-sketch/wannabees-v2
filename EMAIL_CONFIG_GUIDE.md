# Email Configuration Guide for Wannabees KTV

Your OTP email system is now ready, but needs a mail service configured. Choose one of these options:

## Option 1: MailHog (Easiest for Local Development) ✅ RECOMMENDED

1. **Download MailHog**
   - Visit: https://github.com/mailhog/MailHog/releases
   - Download: `MailHog_windows_amd64.exe`

2. **Run MailHog**
   - Double-click `MailHog_windows_amd64.exe`
   - It will start listening on `localhost:1025`
   - Open browser: http://localhost:8025 to view sent emails

3. **mail_config.php is already set to use MailHog!**
   ```php
   'service' => 'mailhog',
   ```

4. **Test it**
   - Request an OTP in cashier/owner settings
   - Check http://localhost:8025 to see the email

---

## Option 2: Gmail SMTP (For Production)

1. **Enable 2-Factor Authentication on Gmail**
   - Go to: https://myaccount.google.com/security
   - Enable 2-Step Verification

2. **Create App Password**
   - In Google Security settings, find "App passwords"
   - Select: Mail → Windows Computer
   - Copy the generated 16-character password

3. **Update mail_config.php**
   ```php
   'service' => 'smtp',
   'smtp' => [
       'host' => 'smtp.gmail.com',
       'port' => 587,
       'username' => 'your-email@gmail.com',
       'password' => 'xxxx xxxx xxxx xxxx',  // 16-char app password
       'encryption' => 'tls',
   ],
   ```

4. **Test it**
   - Requests an OTP - it should arrive in email

---

## Option 3: SendGrid (Alternative Production Service)

1. **Sign up & get API key**
   - Visit: https://sendgrid.com
   - Get your API key from settings

2. **Update mail_config.php**
   ```php
   'service' => 'smtp',
   'smtp' => [
       'host' => 'smtp.sendgrid.net',
       'port' => 587,
       'username' => 'apikey',
       'password' => 'SG.your_api_key_here',
       'encryption' => 'tls',
   ],
   ```

---

## Option 4: Office 365 / Outlook

```php
'service' => 'smtp',
'smtp' => [
    'host' => 'smtp.office365.com',
    'port' => 587,
    'username' => 'your-email@company.com',
    'password' => 'your-password',
    'encryption' => 'tls',
],
```

---

## ⚠️ TROUBLESHOOTING

**Issue: "Email service not configured"**
- Check [mail_config.php](mail_config.php) - make sure `'service'` matches your setup
- Verify SMTP host and port are correct
- Check username/password are valid

**Issue: MailHog won't start**
- Make sure no other service is using port 1025
- Windows Firewall might be blocking - check settings
- Try running as Administrator

**Issue: Gmail says "Less secure app"**
- Don't use your regular password!
- Use **App Password** (16 characters) instead
- You need 2-Factor Authentication enabled

**Issue: Want to see test emails?**
- Check [otp_emails.log](otp_emails.log) for debug info
- This file logs all mail failures for troubleshooting

---

## Testing Commands

```bash
# Windows PowerShell - Test SMTP connection
Test-NetConnection -ComputerName smtp.gmail.com -Port 587

# Test MailHog connection  
Test-NetConnection -ComputerName localhost -Port 1025
```

---

## Current Configuration

Edit [mail_config.php](mail_config.php) to change settings. Current:
- **Service**: `mailhog` (local development)
- **Host**: `localhost`
- **Port**: `1025`

This is perfect for local testing. For production, switch to Option 2 (Gmail) or Option 3 (SendGrid).

---

## Questions?

If emails still aren't sending:
1. Check your `mail_config.php` service type matches your setup
2. Verify the host/port/credentials are correct
3. Check [otp_emails.log](otp_emails.log) for error messages
4. Enable debug mode in [send_settings_otp.php](api/auth/send_settings_otp.php) to see detailed errors
