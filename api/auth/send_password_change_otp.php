<?php
// send_password_change_otp.php - Send OTP for first-time password change verification
session_start();
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

// Use PHPMailer for Gmail SMTP
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/email_templates.php';

// Must be logged in with must_change_password flag
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$user_id = intval($_SESSION['user_id']);

// Get user details
$stmt = $mysqli->prepare("SELECT email, display_name, username, must_change_password FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Verify user must change password
if ($user['must_change_password'] != 1) {
    echo json_encode(['success' => false, 'error' => 'Password change not required']);
    exit;
}

$email = $user['email'];
if (empty($email)) {
    echo json_encode(['success' => false, 'error' => 'No email associated with this account']);
    exit;
}

// Generate 6-digit OTP
$otp = sprintf('%06d', mt_rand(0, 999999));

// Store OTP in password_reset_otps table (reuse existing table)
$expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

// Delete any existing unused OTPs for this email
$stmt = $mysqli->prepare("DELETE FROM password_reset_otps WHERE email = ? AND is_used = 0");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->close();

// Insert new OTP
$stmt = $mysqli->prepare("INSERT INTO password_reset_otps (user_id, email, otp_code, expires_at) VALUES (?, ?, ?, ?)");
$stmt->bind_param('isss', $user_id, $email, $otp, $expiresAt);

if ($stmt->execute()) {
    $stmt->close();
    
    // Send OTP via email using PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jaga.labajan.coc@phinmaed.com';
        $mail->Password   = 'tuxv gcbd hcsu xhzg';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('jaga.labajan.coc@phinmaed.com', 'Wannabees KTV');
        $mail->addReplyTo('noreply@wannabeesktv.com', 'Wannabees KTV');
        $mail->addAddress($email);
        
        // Content
        $displayName = $user['display_name'] ?: $user['username'];
        $htmlBody = EmailTemplates::otpEmailTemplate($otp, $displayName);
        $mail->isHTML(true);
        $mail->Subject = 'Password Change Verification - Wannabees KTV';
        $mail->Body = $htmlBody;
        $mail->AltBody = EmailTemplates::otpEmailPlainText($otp, $displayName);
        
        $mail->send();
        
        echo json_encode([
            'success' => true, 
            'message' => 'OTP sent to your email. Please check your inbox.',
            'email_hint' => substr($email, 0, 3) . '***' . substr($email, strpos($email, '@'))
        ]);
    } catch (Exception $e) {
        error_log("Failed to send OTP email to $email. Error: {$mail->ErrorInfo}");
        
        echo json_encode([
            'success' => false,
            'error' => 'Failed to send OTP email. Please try again.',
            'debug_error' => $mail->ErrorInfo
        ]);
    }
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to generate OTP. Please try again.']);
}
?>
