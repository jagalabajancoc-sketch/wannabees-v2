<?php
// send_otp.php - Send OTP for password reset
session_start();
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

// Use PHPMailer for Gmail SMTP
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/email_templates.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$captcha = isset($_POST['captcha']) ? trim($_POST['captcha']) : '';

if (empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Email is required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit;
}

// Validate captcha
if (empty($captcha)) {
    echo json_encode(['success' => false, 'error' => 'Please complete the security verification']);
    exit;
}

if (!isset($_SESSION['forgot_captcha_answer']) || intval($captcha) !== intval($_SESSION['forgot_captcha_answer'])) {
    echo json_encode(['success' => false, 'error' => 'Incorrect security verification. Please try again.']);
    exit;
}

// Clear captcha after validation
unset($_SESSION['forgot_captcha_answer']);
unset($_SESSION['forgot_captcha_question']);

// Check if email exists
$stmt = $mysqli->prepare("SELECT user_id, username, display_name FROM users WHERE email = ? AND is_active = 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    // Don't reveal whether email exists or not for security
    echo json_encode(['success' => false, 'error' => 'If this email is registered, an OTP will be sent']);
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Generate 6-digit OTP
$otp = sprintf('%06d', mt_rand(0, 999999));

// Store OTP in database with 15 minutes expiry
// First, create password_reset_otps table if it doesn't exist
$createTableQuery = "CREATE TABLE IF NOT EXISTS password_reset_otps (
    otp_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_used TINYINT(1) DEFAULT 0,
    INDEX idx_email (email),
    INDEX idx_otp (otp_code),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

$mysqli->query($createTableQuery);

// Delete any existing unused OTPs for this email
$stmt = $mysqli->prepare("DELETE FROM password_reset_otps WHERE email = ? AND is_used = 0");
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->close();

// Insert new OTP
$expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
$stmt = $mysqli->prepare("INSERT INTO password_reset_otps (user_id, email, otp_code, expires_at) VALUES (?, ?, ?, ?)");
$stmt->bind_param('isss', $user['user_id'], $email, $otp, $expiresAt);

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
        // Use Gmail account as From address (Gmail requires this for SMTP)
        $mail->setFrom('jaga.labajan.coc@phinmaed.com', 'Wannabees KTV');
        $mail->addReplyTo('noreply@wannabeesktv.com', 'Wannabees KTV');
        $mail->addAddress($email);
        
        // Content - Generate HTML email from template
        $htmlBody = EmailTemplates::otpEmailTemplate($otp, $user['display_name']);
        // Replace template variables
        $htmlBody = str_replace('{greeting}', 'Hello ' . htmlspecialchars($user['display_name']) . ',', $htmlBody);
        $htmlBody = str_replace('{otp}', $otp, $htmlBody);
        
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset OTP - Wannabees KTV';
        $mail->Body = $htmlBody;
        // Add plain text alternative for email clients that don't support HTML
        $mail->AltBody = EmailTemplates::otpEmailPlainText($otp, $user['display_name']);
        
        $mail->send();
        
        echo json_encode([
            'success' => true, 
            'message' => 'OTP sent to your email. Please check your inbox.'
        ]);
    } catch (Exception $e) {
        // Email failed but OTP is stored, log for debugging
        $errorMsg = "Failed to send OTP email to $email. Error: {$mail->ErrorInfo}";
        error_log($errorMsg);
        error_log("Exception: " . $e->getMessage());
        
        echo json_encode([
            'success' => true, 
            'message' => 'OTP generated. Please check your email.',
            'warning' => 'Email delivery may be delayed',
            'debug_error' => $mail->ErrorInfo  // For development debugging
        ]);
    }
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to generate OTP. Please try again.']);
}
?>
