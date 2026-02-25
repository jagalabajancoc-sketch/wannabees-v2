<?php
/**
 * Email Configuration File
 * 
 * Configure your email service here. Choose one:
 * 1. SMTP Server (Gmail, SendGrid, etc.)
 * 2. MailHog (local development)
 * 3. Sendmail (Windows)
 */

return [
    // Choose service: 'smtp', 'mailhog', or 'sendmail'
    'service' => 'mailhog',
    
    // SMTP Configuration (if service = 'smtp')
    'smtp' => [
        'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'port' => getenv('SMTP_PORT') ?: 587,
        'username' => getenv('SMTP_USERNAME') ?: '', // Your email address
        'password' => getenv('SMTP_PASSWORD') ?: '', // App password, not regular password
        'encryption' => 'tls', // 'tls' or 'ssl'
    ],
    
    // MailHog Configuration (if service = 'mailhog') - For Local Development
    'mailhog' => [
        'host' => 'localhost',
        'port' => 1025,
    ],
    
    // Sender Information
    'from_email' => 'noreply@wannabees-ktv.local',
    'from_name' => 'Wannabees KTV',
];
?>
