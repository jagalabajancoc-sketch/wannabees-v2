<?php
// Test OTP sending manually
session_start();

// Simulate a login session
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'testuser';

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api/auth/email_templates.php';

echo "OTP Send Diagnostic Test\n";
echo "========================\n\n";

// 1. Check email templates class
echo "1. Checking EmailTemplates class...\n";
if (class_exists('EmailTemplates')) {
    echo "   ✓ EmailTemplates class found\n";
    if (method_exists('EmailTemplates', 'otpEmailTemplate')) {
        echo "   ✓ otpEmailTemplate method exists\n";
    } else {
        echo "   ✗ otpEmailTemplate method NOT found\n";
    }
} else {
    echo "   ✗ EmailTemplates class NOT found\n";
}

// 2. Check database connectivity
echo "\n2. Checking database...\n";
$result = $mysqli->query("SELECT COUNT(*) as count FROM password_reset_otps");
if ($result) {
    $row = $result->fetch_assoc();
    echo "   ✓ Database accessible, " . $row['count'] . " OTP records exist\n";
} else {
    echo "   ✗ Database error: " . $mysqli->error . "\n";
}

// 3. Test OTP generation
echo "\n3. Testing OTP generation...\n";
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
echo "   ✓ Generated OTP: $otp\n";

// 4. Test email generation
echo "\n4. Testing email template...\n";
try {
    $emailBody = EmailTemplates::otpEmailTemplate($otp, 'Test User');
    if (!empty($emailBody)) {
        echo "   ✓ Email template generated (" . strlen($emailBody) . " bytes)\n";
    } else {
        echo "   ✗ Email template is empty\n";
    }
} catch (Exception $e) {
    echo "   ✗ Email template error: " . $e->getMessage() . "\n";
}

// 5. Simulate OTP insertion
echo "\n5. Testing OTP database insertion...\n";
$user_id = 1;
$test_email = 'test@example.com';
$metadata = json_encode(['new_email' => $test_email]);
$expiry = date('Y-m-d H:i:s', time() + 600);

try {
    $stmt = $mysqli->prepare("INSERT INTO password_reset_otps (user_id, email, otp_code, action, metadata, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo "   ✗ Prepare failed: " . $mysqli->error . "\n";
    } else {
        $stmt->bind_param('isssss', $user_id, $test_email, $otp, $action, $metadata, $expiry);
        $action = 'email';
        $stmt->bind_param('isssss', $user_id, $test_email, $otp, $action, $metadata, $expiry);
        
        if ($stmt->execute()) {
            echo "   ✓ OTP inserted successfully\n";
            // Clean up test record
            $mysqli->query("DELETE FROM password_reset_otps WHERE otp_code = '$otp'");
        } else {
            echo "   ✗ Insert failed: " . $stmt->error . "\n";
        }
        $stmt->close();
    }
} catch (Exception $e) {
    echo "   ✗ Exception: " . $e->getMessage() . "\n";
}

// 6. Test mail function
echo "\n6. Testing mail function availability...\n";
if (function_exists('mail')) {
    echo "   ✓ mail() function is available\n";
    $mail_cfg_value = ini_get('sendmail_path');
    $smtp_cfg = ini_get('SMTP');
    $smtp_port = ini_get('smtp_port');
    echo "   Mail config - sendmail_path: " . ($mail_cfg_value ? $mail_cfg_value : 'NOT SET') . "\n";
    echo "   Mail config - SMTP: " . ($smtp_cfg ? $smtp_cfg : 'localhost') . "\n";
    echo "   Mail config - smtp_port: " . ($smtp_port ? $smtp_port : 'NOT SET') . "\n";
} else {
    echo "   ✗ mail() function is NOT available\n";
}

echo "\n7. SUMMARY:\n";
echo "   If all checks pass but OTP still fails, check:\n";
echo "   - Server error logs (Apache/PHP)\n";
echo "   - Check that 'From' email is valid\n";
echo "   - Firewall/SMTP settings\n";

?>
