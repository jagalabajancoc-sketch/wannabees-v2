<?php
// verify_settings_otp.php - Verify OTP for settings changes
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
$otp_code = isset($_POST['otp']) ? trim($_POST['otp']) : '';
$action = isset($_POST['action']) ? trim($_POST['action']) : ''; // 'email' or 'password'

if (empty($otp_code) || !in_array($action, ['email', 'password'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid OTP or action']);
    exit;
}

try {
    // Find OTP
    $stmt = $mysqli->prepare("SELECT otp_id, otp_code, metadata, expires_at FROM password_reset_otps WHERE user_id = ? AND action = ? AND expires_at > NOW()");
    $stmt->bind_param('is', $user_id, $action);
    $stmt->execute();
    $otp_record = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$otp_record) {
        echo json_encode(['success' => false, 'error' => 'OTP expired or not found. Please request a new one.']);
        exit;
    }

    // Verify OTP code
    if ($otp_record['otp_code'] !== $otp_code) {
        echo json_encode(['success' => false, 'error' => 'Invalid OTP code']);
        exit;
    }

    // Mark OTP as verified/used
    $stmt = $mysqli->prepare("UPDATE password_reset_otps SET is_used = 1 WHERE otp_id = ?");
    $stmt->bind_param('i', $otp_record['otp_id']);
    $stmt->execute();
    $stmt->close();

    // Extract metadata if applicable
    $metadata = null;
    if (!empty($otp_record['metadata'])) {
        $metadata = json_decode($otp_record['metadata'], true);
    }

    echo json_encode([
        'success' => true,
        'message' => 'OTP verified successfully',
        'verified_at' => date('Y-m-d H:i:s'),
        'metadata' => $metadata
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>
