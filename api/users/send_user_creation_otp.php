<?php
// send_user_creation_otp.php - Send OTP for email verification when creating a new user
session_start();
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

// Use PHPMailer for Gmail SMTP
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../auth/email_templates.php';

// Only allow owners to send OTP for user creation
if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$username = isset($_POST['username']) ? trim($_POST['username']) : '';

if (empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Email is required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit;
}

// Check if email already exists in the system
$stmt = $mysqli->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'This email is already registered']);
    exit;
}
$stmt->close();

// Generate 6-digit OTP
$otp = sprintf('%06d', mt_rand(0, 999999));

// Store OTP in a temporary table for user creation verification
// Create table if it doesn't exist
$createTableQuery = "CREATE TABLE IF NOT EXISTS user_creation_otps (
    otp_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    INDEX idx_email (email),
    INDEX idx_otp (otp_code),
    INDEX idx_expires (expires_at)
)";

$mysqli->query($createTableQuery);

// Delete any existing unused OTPs for this email
$stmt = $mysqli->prepare("DELETE FROM user_creation_otps WHERE email = ? AND is_used = 0");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->close();

// Insert new OTP
$expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
$stmt = $mysqli->prepare("INSERT INTO user_creation_otps (email, otp_code, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $email, $otp, $expiresAt);

if ($stmt->execute()) {
    $stmt->close();
    
    // Send OTP via email using PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jaga.labajan.coc@phinmaed.com';  // TODO: Replace with your Gmail address
        $mail->Password   = 'tuxv gcbd hcsu xhzg';     // TODO: Replace with your Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('jaga.labajan.coc@phinmaed.com', 'Wannabees KTV');
        $mail->addReplyTo('noreply@wannabeesktv.com', 'Wannabees KTV');
        $mail->addAddress($email);
        
        // Content - Create HTML email
        $displayName = $username ? htmlspecialchars($username) : 'New User';
        $htmlBody = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #f2a20a; padding: 30px 20px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px;">Wannabees KTV</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="margin: 0 0 20px 0; color: #333333; font-size: 24px;">Email Verification</h2>
                            <p style="margin: 0 0 20px 0; color: #666666; font-size: 16px; line-height: 1.5;">
                                Hello ' . $displayName . ',
                            </p>
                            <p style="margin: 0 0 20px 0; color: #666666; font-size: 16px; line-height: 1.5;">
                                An account is being created for you at Wannabees KTV. To verify your email address, please use the following One-Time Password (OTP):
                            </p>
                            
                            <!-- OTP Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 30px 0;">
                                <tr>
                                    <td align="center">
                                        <div style="background-color: #f8f9fa; border: 2px solid #f2a20a; border-radius: 8px; padding: 20px; display: inline-block;">
                                            <span style="font-size: 36px; font-weight: bold; color: #f2a20a; letter-spacing: 8px;">' . $otp . '</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 0 0 20px 0; color: #666666; font-size: 16px; line-height: 1.5;">
                                This OTP is valid for <strong>15 minutes</strong>. Please do not share this code with anyone.
                            </p>
                            
                            <p style="margin: 0; color: #999999; font-size: 14px; line-height: 1.5;">
                                If you did not request this verification, please ignore this email.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center;">
                            <p style="margin: 0; color: #999999; font-size: 12px;">
                                © 2026 Wannabees KTV. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        $plainTextBody = "Email Verification - Wannabees KTV\n\n" .
                        "Hello $displayName,\n\n" .
                        "An account is being created for you at Wannabees KTV. To verify your email address, please use the following One-Time Password (OTP):\n\n" .
                        "OTP: $otp\n\n" .
                        "This OTP is valid for 15 minutes. Please do not share this code with anyone.\n\n" .
                        "If you did not request this verification, please ignore this email.\n\n" .
                        "© 2026 Wannabees KTV. All rights reserved.";
        
        $mail->isHTML(true);
        $mail->Subject = 'Email Verification - Wannabees KTV';
        $mail->Body = $htmlBody;
        $mail->AltBody = $plainTextBody;
        
        $mail->send();
        
        echo json_encode([
            'success' => true, 
            'message' => 'OTP sent to ' . $email . '. Please check your inbox.'
        ]);
    } catch (Exception $e) {
        // Email failed but OTP is stored, log for debugging
        $errorMsg = "Failed to send OTP email to $email. Error: {$mail->ErrorInfo}";
        error_log($errorMsg);
        error_log("Exception: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'error' => 'Failed to send email. Please try again or check the email address.',
            'debug_error' => $mail->ErrorInfo  // For development debugging
        ]);
    }
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to generate OTP. Please try again.']);
}
?>
