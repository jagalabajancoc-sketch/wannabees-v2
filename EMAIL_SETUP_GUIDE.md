# Email Configuration for Local Development (XAMPP)

## Quick Setup for Testing

Since XAMPP doesn't include a mail server by default, you need to configure one for testing the forgot password feature.

## Option 1: Papercut SMTP (Recommended for Windows)

**Papercut** is a simple SMTP server that catches all emails and displays them in a UI - no actual emails are sent.

### Installation:
1. Download: https://github.com/ChangemakerStudios/Papercut-SMTP/releases
2. Extract and run `Papercut.exe`
3. It will start catching emails on `localhost:25`

### Configure PHP (in `php.ini`):
```ini
[mail function]
SMTP = localhost
smtp_port = 25
sendmail_from = noreply@wannabeesktv.com
```

### Restart Apache:
- Stop and start Apache in XAMPP Control Panel

### Test:
- Use forgot password feature
- OTP email will appear in Papercut window

---

## Option 2: MailHog (Cross-platform)

**MailHog** is similar to Papercut but runs as a service.

### Installation:
1. Download: https://github.com/mailhog/MailHog/releases
2. Run `MailHog.exe`
3. Web UI: http://localhost:8025
4. SMTP: localhost:1025

### Configure PHP (in `php.ini`):
```ini
[mail function]
SMTP = localhost
smtp_port = 1025
sendmail_from = noreply@wannabeesktv.com
```

### Test:
- Use forgot password feature
- View emails at http://localhost:8025

---

## Option 3: Gmail SMTP (Real Emails)

**Warning:** Use Gmail App Passwords, not your actual password.

### Setup:
1. Enable 2-Step Verification in your Google Account
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Install PHPMailer (recommended over mail() for Gmail)

### Install PHPMailer:
```bash
composer require phpmailer/phpmailer
```

### Update `send_otp.php`:
```php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'your-email@gmail.com';
    $mail->Password   = 'your-app-password';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Recipients
    $mail->setFrom('noreply@wannabeesktv.com', 'Wannabees KTV');
    $mail->addAddress($email);

    // Content
    $mail->isHTML(false);
    $mail->Subject = 'Password Reset OTP - Wannabees KTV';
    $mail->Body    = "Your OTP is: $otp\n\nThis code will expire in 15 minutes.";

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'OTP sent successfully']);
} catch (Exception $e) {
    error_log("Email error: {$mail->ErrorInfo}");
    echo json_encode(['success' => false, 'error' => 'Failed to send OTP']);
}
```

---

## Finding php.ini in XAMPP

1. Open XAMPP Control Panel
2. Click "Config" next to Apache
3. Select "PHP (php.ini)"
4. Find `[mail function]` section (around line 950)
5. Update SMTP settings
6. Save and restart Apache

---

## Testing Email Delivery

After configuration:

```php
// Test script: test_email.php
<?php
$to = "your-email@example.com";
$subject = "Test Email";
$message = "This is a test email from XAMPP";
$headers = "From: noreply@wannabeesktv.com";

if (mail($to, $subject, $message, $headers)) {
    echo "Email sent successfully!";
} else {
    echo "Email failed to send.";
}
?>
```

Run this in your browser to verify email configuration works.

---

## Production Recommendations

For production deployment:

1. **Use a dedicated email service:**
   - SendGrid (12,000 free emails/month)
   - AWS SES (62,000 free emails/month)
   - Mailgun (5,000 free emails/month)

2. **Benefits:**
   - Better deliverability
   - SPF/DKIM authentication
   - Email tracking and analytics
   - Bounce handling
   - No spam folder issues

3. **Implementation:**
   - All services provide PHP SDKs
   - Usually just need to update send_otp.php
   - Keep same database structure

---

## Troubleshooting

**Mail not sending:**
- Check if firewall is blocking port 25/1025/587
- Verify SMTP server is running (Papercut/MailHog)
- Check Apache error logs: `xampp/apache/logs/error.log`
- Check PHP error logs: `xampp/php/logs/php_error_log`

**"Could not instantiate mail function":**
- php.ini not configured correctly
- Apache not restarted after php.ini changes
- sendmail_path not set (Windows)

**Emails go to spam:**
- Normal for localhost/development
- Use SPF records in production
- Use proper From domain in production
- Consider dedicated email service

---

## Current Configuration in send_otp.php

The code currently uses PHP's `mail()` function:

```php
$subject = "Password Reset OTP - Wannabees KTV";
$message = "Your OTP is: " . $otp;
$headers = "From: noreply@wannabeesktv.com\r\n";

mail($email, $subject, $message, $headers);
```

This will work once you configure SMTP in php.ini or use one of the tools above.
