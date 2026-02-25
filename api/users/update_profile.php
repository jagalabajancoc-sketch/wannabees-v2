<?php
// update_profile.php - Update own profile (requires OTP for email changes)
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
$display_name = isset($_POST['display_name']) ? trim($_POST['display_name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$otp_code = isset($_POST['otp']) ? trim($_POST['otp']) : '';

// Get current user to check if email is changing
$stmt = $mysqli->prepare("SELECT email FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$current_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$email_is_changing = !empty($email) && $email !== $current_user['email'];

// If email is changing, OTP is required
if ($email_is_changing) {
    if (empty($otp_code)) {
        echo json_encode(['success' => false, 'error' => 'OTP is required to change email']);
        exit;
    }

    // Verify OTP
    $stmt = $mysqli->prepare("SELECT otp_id, is_used FROM password_reset_otps WHERE user_id = ? AND action = 'email' AND otp_code = ? AND expires_at > NOW()");
    $stmt->bind_param('is', $user_id, $otp_code);
    $stmt->execute();
    $otp_record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$otp_record || $otp_record['is_used']) {
        echo json_encode(['success' => false, 'error' => 'Invalid or expired OTP']);
        exit;
    }

    // Check if new email is already used by another user
    $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $stmt->bind_param('si', $email, $user_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'error' => 'Email already used by another user']);
        exit;
    }
    $stmt->close();

    // Mark OTP as used
    $stmt = $mysqli->prepare("UPDATE password_reset_otps SET is_used = 1 WHERE otp_id = ?");
    $stmt->bind_param('i', $otp_record['otp_id']);
    $stmt->execute();
    $stmt->close();
}

try {
    $stmt = $mysqli->prepare("UPDATE users SET display_name = ?, email = ? WHERE user_id = ?");
    $stmt->bind_param('ssi', $display_name, $email, $user_id);
    $stmt->execute();
    $stmt->close();

    // Update session
    $_SESSION['display_name'] = $display_name;

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
