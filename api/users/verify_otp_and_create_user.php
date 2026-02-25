<?php
// verify_otp_and_create_user.php - Verify OTP and create user
session_start();
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

// Use PHPMailer for sending password
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../vendor/autoload.php';

// Function to generate a secure random password
function generateSecurePassword($length = 12) {
    $uppercase = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lowercase = 'abcdefghjkmnpqrstuvwxyz';
    $numbers = '23456789';
    $special = '!@#$%&*';
    
    $password = '';
    // Ensure at least one character from each set
    $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
    $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
    $password .= $numbers[random_int(0, strlen($numbers) - 1)];
    $password .= $special[random_int(0, strlen($special) - 1)];
    
    // Fill the rest with random characters from all sets
    $allChars = $uppercase . $lowercase . $numbers . $special;
    for ($i = 4; $i < $length; $i++) {
        $password .= $allChars[random_int(0, strlen($allChars) - 1)];
    }
    
    // Shuffle the password to randomize character positions
    return str_shuffle($password);
}

// Function to send password via email to new user
function sendPasswordEmail($email, $username, $displayName, $password) {
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
        
        // Content
        $name = $displayName ? htmlspecialchars($displayName) : htmlspecialchars($username);
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
                            <h2 style="margin: 0 0 20px 0; color: #333333; font-size: 24px;">Welcome to Wannabees KTV!</h2>
                            <p style="margin: 0 0 20px 0; color: #666666; font-size: 16px; line-height: 1.5;">
                                Hello ' . $name . ',
                            </p>
                            <p style="margin: 0 0 20px 0; color: #666666; font-size: 16px; line-height: 1.5;">
                                Your account has been successfully created. Here are your login credentials:
                            </p>
                            
                            <!-- Credentials Box -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0;">
                                <tr>
                                    <td style="background-color: #f8f9fa; border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px;">
                                        <table width="100%" cellpadding="8" cellspacing="0">
                                            <tr>
                                                <td style="color: #666; font-size: 14px; font-weight: 600; width: 120px;">Username:</td>
                                                <td style="color: #333; font-size: 14px; font-weight: 600;">' . htmlspecialchars($username) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="color: #666; font-size: 14px; font-weight: 600; padding-top: 12px;">Password:</td>
                                                <td style="color: #f2a20a; font-size: 16px; font-weight: bold; font-family: monospace; padding-top: 12px;">' . htmlspecialchars($password) . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <div style="background-color: #fff3e0; border-left: 4px solid #ff9800; padding: 16px; margin: 20px 0; border-radius: 4px;">
                                <p style="margin: 0; color: #e65100; font-size: 14px; line-height: 1.5;">
                                    <strong>⚠️ Important Security Notice:</strong><br>
                                    You must change this password on your first login. This is a temporary password for your security.
                                </p>
                            </div>
                            
                            <p style="margin: 20px 0; color: #666666; font-size: 14px; line-height: 1.5;">
                                <strong>Next Steps:</strong>
                            </p>
                            <ol style="color: #666666; font-size: 14px; line-height: 1.8; margin: 0; padding-left: 20px;">
                                <li>Visit the login page</li>
                                <li>Enter your username and temporary password</li>
                                <li>You will be prompted to create a new password</li>
                                <li>Choose a strong, memorable password</li>
                            </ol>
                            
                            <p style="margin: 20px 0 0 0; color: #999999; font-size: 13px; line-height: 1.5;">
                                If you did not request this account, please contact our support team immediately.
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
        
        $plainTextBody = "Welcome to Wannabees KTV!\n\n" .
                        "Hello $name,\n\n" .
                        "Your account has been successfully created. Here are your login credentials:\n\n" .
                        "Username: $username\n" .
                        "Password: $password\n\n" .
                        "⚠️ IMPORTANT SECURITY NOTICE:\n" .
                        "You must change this password on your first login. This is a temporary password for your security.\n\n" .
                        "Next Steps:\n" .
                        "1. Visit the login page\n" .
                        "2. Enter your username and temporary password\n" .
                        "3. You will be prompted to create a new password\n" .
                        "4. Choose a strong, memorable password\n\n" .
                        "If you did not request this account, please contact our support team immediately.\n\n" .
                        "© 2026 Wannabees KTV. All rights reserved.";
        
        $mail->isHTML(true);
        $mail->Subject = 'Your Wannabees KTV Account - Login Credentials';
        $mail->Body = $htmlBody;
        $mail->AltBody = $plainTextBody;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error but don't fail user creation
        error_log("Failed to send password email to $email. Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Only allow owners to create users
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

// Get form data
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$display_name = isset($_POST['display_name']) ? trim($_POST['display_name']) : '';
$role_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : 0;
$is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
$otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';

// Generate a secure random password
$generatedPassword = generateSecurePassword(12);

// Validate required fields (password is auto-generated)
if (empty($username)) {
    echo json_encode(['success' => false, 'error' => 'Username is required']);
    exit;
}

if (empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Email is required']);
    exit;
}

if (empty($display_name)) {
    echo json_encode(['success' => false, 'error' => 'Display name is required']);
    exit;
}

if (empty($role_id)) {
    echo json_encode(['success' => false, 'error' => 'Role is required']);
    exit;
}

if (empty($otp)) {
    echo json_encode(['success' => false, 'error' => 'OTP is required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit;
}

// Verify OTP
$stmt = $mysqli->prepare("
    SELECT otp_id, expires_at, is_used 
    FROM user_creation_otps 
    WHERE email = ? AND otp_code = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->bind_param('ss', $email, $otp);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'Invalid OTP']);
    exit;
}

$otpData = $result->fetch_assoc();
$stmt->close();

// Check if OTP is already used
if ($otpData['is_used'] == 1) {
    echo json_encode(['success' => false, 'error' => 'OTP has already been used']);
    exit;
}

// Check if OTP is expired
$currentTime = date('Y-m-d H:i:s');
if ($currentTime > $otpData['expires_at']) {
    echo json_encode(['success' => false, 'error' => 'OTP has expired. Please request a new one.']);
    exit;
}

// OTP is valid, now create the user
try {
    // Check if username already exists
    $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'error' => 'Username already exists']);
        exit;
    }
    $stmt->close();
    
    // Check if email already exists
    $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'error' => 'Email already exists']);
        exit;
    }
    $stmt->close();
    
    // Hash the generated password
    $hashedPassword = password_hash($generatedPassword, PASSWORD_DEFAULT);
    
    // Create user with email verified flag and must_change_password = 1
    $stmt = $mysqli->prepare("
        INSERT INTO users (username, email, password, display_name, role_id, is_active, email_verified, must_change_password) 
        VALUES (?, ?, ?, ?, ?, ?, 1, 1)
    ");
    $stmt->bind_param('ssssis', $username, $email, $hashedPassword, $display_name, $role_id, $is_active);
    
    if ($stmt->execute()) {
        $newUserId = $stmt->insert_id;
        $stmt->close();
        
        // Mark OTP as used
        $stmt = $mysqli->prepare("UPDATE user_creation_otps SET is_used = 1 WHERE otp_id = ?");
        $stmt->bind_param('i', $otpData['otp_id']);
        $stmt->execute();
        $stmt->close();
        
        // Send password via email to the new user
        sendPasswordEmail($email, $username, $display_name, $generatedPassword);
        
        echo json_encode([
            'success' => true, 
            'message' => 'User created successfully with verified email',
            'user_id' => $newUserId,
            'generated_password' => $generatedPassword,
            'username' => $username
        ]);
    } else {
        $stmt->close();
        throw new Exception('Failed to create user');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Error creating user: ' . $e->getMessage()
    ]);
}
?>
