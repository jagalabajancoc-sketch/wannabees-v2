<?php
// reset_password.php - Reset password using OTP
session_start();
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
$newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';

// Validation
if (empty($email) || empty($otp) || empty($newPassword)) {
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email format']);
    exit;
}

if (strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters long']);
    exit;
}

// Verify OTP
$stmt = $mysqli->prepare("
    SELECT otp_id, user_id 
    FROM password_reset_otps 
    WHERE email = ? 
    AND otp_code = ? 
    AND is_used = 0 
    AND expires_at > NOW()
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->bind_param('ss', $email, $otp);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'Invalid or expired OTP']);
    exit;
}

$otpRecord = $result->fetch_assoc();
$stmt->close();

// Hash the new password
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

// Update user password
$stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE user_id = ?");
$stmt->bind_param('si', $hashedPassword, $otpRecord['user_id']);

if ($stmt->execute()) {
    $stmt->close();
    
    // Mark OTP as used
    $stmt = $mysqli->prepare("UPDATE password_reset_otps SET is_used = 1 WHERE otp_id = ?");
    $stmt->bind_param('i', $otpRecord['otp_id']);
    $stmt->execute();
    $stmt->close();
    
    // Delete old OTPs for this user
    $stmt = $mysqli->prepare("DELETE FROM password_reset_otps WHERE user_id = ? AND otp_id != ?");
    $stmt->bind_param('ii', $otpRecord['user_id'], $otpRecord['otp_id']);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Password reset successfully'
    ]);
} else {
    $stmt->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to reset password. Please try again.']);
}
?>
