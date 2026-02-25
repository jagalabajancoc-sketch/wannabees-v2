<?php
// change_password.php - Change own password (requires OTP)
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
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
$otp_code = isset($_POST['otp']) ? trim($_POST['otp']) : '';

if (empty($current_password) || empty($new_password)) {
    echo json_encode(['success' => false, 'error' => 'Current and new passwords are required']);
    exit;
}

if (empty($otp_code)) {
    echo json_encode(['success' => false, 'error' => 'OTP is required to change password']);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'error' => 'New password must be at least 6 characters']);
    exit;
}

// Verify current password
$stmt = $mysqli->prepare("SELECT password FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

// Verify current password
if (!password_verify($current_password, $user['password'])) {
    echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
    exit;
}

// Verify OTP
$stmt = $mysqli->prepare("SELECT otp_id, is_used FROM password_reset_otps WHERE user_id = ? AND action = 'password' AND otp_code = ? AND expires_at > NOW()");
$stmt->bind_param('is', $user_id, $otp_code);
$stmt->execute();
$otp_record = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$otp_record || $otp_record['is_used']) {
    echo json_encode(['success' => false, 'error' => 'Invalid or expired OTP']);
    exit;
}

// Mark OTP as used
$stmt = $mysqli->prepare("UPDATE password_reset_otps SET is_used = 1 WHERE otp_id = ?");
$stmt->bind_param('i', $otp_record['otp_id']);
$stmt->execute();
$stmt->close();

// Update password
try {
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $stmt->bind_param('si', $hashed_password, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
