<?php
/**
 * OTP Settings Implementation Verification Script
 * Run this script to verify all components are in place and working
 */

session_start();
require_once __DIR__ . '/db.php';

$checks = [];
$allPass = true;

// 1. Check if password_reset_otps table exists
$result = $mysqli->query("DESCRIBE password_reset_otps");
if ($result && $result->num_rows > 0) {
    $checks['table_exists'] = ['pass' => true, 'message' => 'password_reset_otps table exists'];
    
    // Check required columns
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[$row['Field']] = $row['Type'];
    }
    
    $required_columns = ['otp_id', 'user_id', 'otp_code', 'expires_at', 'is_used', 'action', 'metadata'];
    $missing = [];
    foreach ($required_columns as $col) {
        if (!isset($columns[$col])) {
            $missing[] = $col;
            $allPass = false;
        }
    }
    
    if (empty($missing)) {
        $checks['table_columns'] = ['pass' => true, 'message' => 'All required columns present'];
    } else {
        $checks['table_columns'] = ['pass' => false, 'message' => 'Missing columns: ' . implode(', ', $missing)];
    }
} else {
    $checks['table_exists'] = ['pass' => false, 'message' => 'password_reset_otps table does not exist'];
    $allPass = false;
}

// 2. Check if API files exist
$api_files = [
    'api/auth/send_settings_otp.php',
    'api/auth/verify_settings_otp.php',
    'api/users/update_profile.php',
    'api/users/change_password.php'
];

foreach ($api_files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $checks["api_$file"] = ['pass' => true, 'message' => "$file exists"];
    } else {
        $checks["api_$file"] = ['pass' => false, 'message' => "$file NOT FOUND"];
        $allPass = false;
    }
}

// 3. Check if settings pages are updated
$settings_files = [
    'cashier/settings.php',
    'owner/settings.php'
];

foreach ($settings_files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        if (strpos($content, 'emailChangeMode') !== false && strpos($content, 'send_settings_otp.php') !== false) {
            $checks["ui_$file"] = ['pass' => true, 'message' => "$file has OTP UI"];
        } else {
            $checks["ui_$file"] = ['pass' => false, 'message' => "$file missing OTP implementation"];
            $allPass = false;
        }
    }
}

// 4. Check email configuration
$mail_config = ini_get('SMTP');
$mail_port = ini_get('smtp_port');
if ($mail_config || $mail_port) {
    $checks['mail_config'] = ['pass' => true, 'message' => "Mail configured (SMTP: $mail_config:$mail_port)"];
} else {
    $checks['mail_config'] = ['pass' => false, 'message' => 'Mail not configured - OTP emails may not send'];
}

// 5. Check email templates
$template_path = __DIR__ . '/api/auth/email_templates.php';
if (file_exists($template_path)) {
    $content = file_get_contents($template_path);
    if (strpos($content, 'otpEmailTemplate') !== false) {
        $checks['email_template'] = ['pass' => true, 'message' => 'Email template available'];
    } else {
        $checks['email_template'] = ['pass' => false, 'message' => 'Email template missing otpEmailTemplate method'];
        $allPass = false;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Settings - Verification</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
        }
        .status {
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 6px;
            border-left: 4px solid #ddd;
        }
        .status.pass {
            background: #d1e7dd;
            color: #0f5132;
            border-left-color: #51cf66;
        }
        .status.fail {
            background: #f8d7da;
            color: #842029;
            border-left-color: #ff6b6b;
        }
        .status-icon {
            font-weight: bold;
            margin-right: 10px;
        }
        .overall {
            margin-top: 30px;
            padding: 20px;
            border-radius: 6px;
            font-size: 18px;
            font-weight: 600;
        }
        .overall.pass {
            background: #d1e7dd;
            color: #0f5132;
        }
        .overall.fail {
            background: #f8d7da;
            color: #842029;
        }
        .guide {
            margin-top: 30px;
            padding: 20px;
            background: #e7f3ff;
            border-left: 4px solid #0066cc;
            border-radius: 6px;
            color: #003d7a;
        }
        .guide h3 {
            margin-top: 0;
        }
        .guide ol {
            padding-left: 20px;
        }
        .guide li {
            margin-bottom: 10px;
        }
        code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 OTP Settings Implementation - Verification Report</h1>
        
        <h2>Status Checks</h2>
        <?php foreach ($checks as $key => $check): ?>
            <div class="status <?= $check['pass'] ? 'pass' : 'fail' ?>">
                <span class="status-icon"><?= $check['pass'] ? '✓' : '✗' ?></span>
                <strong><?= ucfirst(str_replace('_', ' ', $key)) ?>:</strong>
                <?= $check['message'] ?>
            </div>
        <?php endforeach; ?>
        
        <div class="overall <?= $allPass ? 'pass' : 'fail' ?>">
            <?= $allPass ? '✓ All checks passed!' : '✗ Some checks failed - see above for details' ?>
        </div>
        
        <?php if (!$allPass): ?>
        <div class="guide">
            <h3>⚠️ Failed Verification - Next Steps</h3>
            <ol>
                <li><strong>Run Database Migration:</strong><br>
                    Execute this SQL in phpMyAdmin or MySQL client:
                    <code>ALTER TABLE password_reset_otps ADD COLUMN IF NOT EXISTS action VARCHAR(50), ADD COLUMN IF NOT EXISTS metadata JSON;</code>
                </li>
                <li><strong>Upload Missing Files:</strong><br>
                    Ensure all files in api/auth/ and api/users/ are uploaded
                </li>
                <li><strong>Configure Email:</strong><br>
                    <ul>
                        <li>Check php.ini SMTP settings</li>
                        <li>Test with <code>test_gmail_smtp.php</code></li>
                    </ul>
                </li>
                <li><strong>Reload Settings Pages:</strong><br>
                    Clear browser cache and reload cashier/settings.php and owner/settings.php
                </li>
                <li><strong>Re-run this verification script</strong></li>
            </ol>
        </div>
        <?php else: ?>
        <div class="guide">
            <h3>✓ Ready to Test OTP Features</h3>
            <ol>
                <li><strong>Log in as Cashier or Owner</strong></li>
                <li><strong>Navigate to Settings</strong></li>
                <li><strong>To test Email Change:</strong>
                    <ul>
                        <li>Click "Change Email" button</li>
                        <li>Enter new email address</li>
                        <li>Click "Save Changes"</li>
                        <li>Check email for OTP code</li>
                        <li>Enter OTP code and confirm</li>
                    </ul>
                </li>
                <li><strong>To test Password Change:</strong>
                    <ul>
                        <li>Enter current password</li>
                        <li>Enter new password</li>
                        <li>Click "Change Password"</li>
                        <li>Check email for OTP code</li>
                        <li>Enter OTP code</li>
                        <li>Password should be changed</li>
                    </ul>
                </li>
            </ol>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
