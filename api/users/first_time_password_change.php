<?php
// first_time_password_change.php - Handle first-time password change for new users
session_start();
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
$otp = isset($_POST['otp']) ? trim($_POST['otp']) : '';

if (empty($current_password) || empty($new_password)) {
    echo json_encode(['success' => false, 'error' => 'Current and new passwords are required']);
    exit;
}

if (empty($otp)) {
    echo json_encode(['success' => false, 'error' => 'OTP verification is required']);
    exit;
}

if (strlen($new_password) < 8) {
    echo json_encode(['success' => false, 'error' => 'New password must be at least 8 characters']);
    exit;
}

// Check if passwords are the same
if ($current_password === $new_password) {
    echo json_encode(['success' => false, 'error' => 'New password must be different from current password']);
    exit;
}

// Get user details
$stmt = $mysqli->prepare("SELECT email, password, role_id, must_change_password FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

// Verify this is actually a first-time password change
if ($user['must_change_password'] != 1) {
    echo json_encode(['success' => false, 'error' => 'This account does not require password change']);
    exit;
}

// Verify OTP from password_reset_otps table
$stmt = $mysqli->prepare("
    SELECT otp_id, expires_at, is_used 
    FROM password_reset_otps 
    WHERE user_id = ? AND otp_code = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->bind_param('is', $user_id, $otp);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'Invalid OTP. Please request a new one.']);
    exit;
}

$otpData = $result->fetch_assoc();
$stmt->close();

// Check if OTP is already used
if ($otpData['is_used'] == 1) {
    echo json_encode(['success' => false, 'error' => 'OTP has already been used. Please request a new one.']);
    exit;
}

// Check if OTP is expired
$currentTime = date('Y-m-d H:i:s');
if ($currentTime > $otpData['expires_at']) {
    echo json_encode(['success' => false, 'error' => 'OTP has expired. Please request a new one.']);
    exit;
}

// Verify current password
if (!password_verify($current_password, $user['password'])) {
    echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
    exit;
}

// Update password and clear must_change_password flag
try {
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE user_id = ?");
    $stmt->bind_param('si', $hashed_password, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Mark OTP as used
    $stmt = $mysqli->prepare("UPDATE password_reset_otps SET is_used = 1 WHERE otp_id = ?");
    $stmt->bind_param('i', $otpData['otp_id']);
    $stmt->execute();
    $stmt->close();
    
    // Determine redirect URL based on role
    $role = intval($user['role_id']);
    $redirect = '../index.php';
    
    if ($role === 1) {
        $redirect = '../owner/dashboard.php';
    } elseif ($role === 3) {
        $redirect = '../cashier/dashboard.php';
    } elseif ($role === 4) {
        $redirect = '../customer/room_tablet.php';
    } else {
        $redirect = '../customer/fallback.php';
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Password changed successfully',
        'redirect' => $redirect
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
