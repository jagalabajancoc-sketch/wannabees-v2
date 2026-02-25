<?php
// send_settings_otp.php - Send OTP for settings changes (email/password)
session_start();
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/email_templates.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$action = isset($_POST['action']) ? trim($_POST['action']) : ''; // 'email' or 'password'
$new_email = isset($_POST['new_email']) ? trim($_POST['new_email']) : '';

if (!in_array($action, ['email', 'password'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

// For email change, new_email is required
if ($action === 'email' && empty($new_email)) {
    echo json_encode(['success' => false, 'error' => 'New email is required']);
    exit;
}

// Get user info
$stmt = $mysqli->prepare("SELECT user_id, email, username FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

// Generate OTP
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiry = date('Y-m-d H:i:s', time() + 600); // 10 minutes

try {
    // Check if OTP already exists for this user and action
    $stmt = $mysqli->prepare("SELECT otp_id FROM password_reset_otps WHERE user_id = ? AND action = ? AND expires_at > NOW()");
    $stmt->bind_param('is', $user_id, $action);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // Delete existing OTP
        $stmt = $mysqli->prepare("DELETE FROM password_reset_otps WHERE otp_id = ?");
        $stmt->bind_param('i', $existing['otp_id']);
        $stmt->execute();
        $stmt->close();
    }

    // Determine recipient email and action label
    $recipient_email = $action === 'email' ? $new_email : $user['email'];
    $action_label = $action === 'email' ? 'Email Change' : 'Password Change';

    // Store OTP with action and new email if applicable
    $metadata = $action === 'email' ? json_encode(['new_email' => $new_email]) : null;
    
    // For password changes, don't include metadata in the insert since it will be NULL
    if ($action === 'password') {
        // Insert without metadata
        $stmt = $mysqli->prepare("INSERT INTO password_reset_otps (user_id, email, otp_code, action, expires_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('issss', $user_id, $recipient_email, $otp, $action, $expiry);
    } else {
        // Insert with metadata for email changes
        $stmt = $mysqli->prepare("INSERT INTO password_reset_otps (user_id, email, otp_code, action, metadata, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isssss', $user_id, $recipient_email, $otp, $action, $metadata, $expiry);
    }
    
    $stmt->execute();
    
    if ($stmt->error) {
        $stmt->close();
        throw new Exception("Database error: " . $stmt->error);
    }
    $stmt->close();

    // Send OTP via email using PHPMailer
    $subject = "Your $action_label OTP - Wannabees KTV";
    $body = EmailTemplates::otpEmailTemplate($otp, $user['username']);

    $mail = new PHPMailer(true);
    
    try {
        // Server settings - Gmail SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'jaga.labajan.coc@phinmaed.com';  // Gmail address
        $mail->Password   = 'tuxv gcbd hcsu xhzg';            // Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('jaga.labajan.coc@phinmaed.com', 'Wannabees KTV');
        $mail->addReplyTo('noreply@wannabeesktv.com', 'Wannabees KTV');
        $mail->addAddress($recipient_email);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = EmailTemplates::otpEmailPlainText($otp, $user['username']);
        
        $mail->send();
        
        echo json_encode([
            'success' => true,
            'message' => "OTP sent to $recipient_email",
            'otp_expiry' => 600 // 10 minutes in seconds
        ]);
    } catch (Exception $mail_error) {
        // If mail fails, log it and return error
        error_log("Mail send error: " . $mail_error->getMessage());
        
        // Also log OTP for debugging
        $log_file = __DIR__ . '/../../otp_emails.log';
        $log_entry = date('Y-m-d H:i:s') . " | To: $recipient_email | Code: $otp | Action: $action | Error: " . $mail_error->getMessage() . "\n";
        @file_put_contents($log_file, $log_entry, FILE_APPEND);
        
        echo json_encode([
            'success' => false,
            'error' => 'Failed to send OTP. Please try again.',
            'debug_error' => $mail_error->getMessage()
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    error_log("OTP Send Error: " . $e->getMessage());
}
?>
