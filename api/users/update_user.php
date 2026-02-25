<?php
// update_user.php - Update user details
session_start();
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
$display_name = isset($_POST['display_name']) ? trim($_POST['display_name']) : null;
$email = isset($_POST['email']) ? trim($_POST['email']) : null;
$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$role_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : 4;
$room_id = isset($_POST['room_id']) && $_POST['room_id'] !== '' ? intval($_POST['room_id']) : null;
$is_active = isset($_POST['is_active']) ? 1 : 0;

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit;
}

// Prevent owner from disabling their own account
if ($user_id == $_SESSION['user_id'] && $is_active == 0) {
    echo json_encode(['success' => false, 'error' => 'You cannot disable your own account']);
    exit;
}

// If email is provided, check if it's already used by another user
if (!empty($email)) {
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
}

// If role is Customer/Tablet (role_id = 4) and room_id is provided, check if room is already assigned to another user
if ($role_id === 4 && $room_id !== null) {
    $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE room_id = ? AND role_id = 4 AND user_id != ?");
    $stmt->bind_param('ii', $room_id, $user_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'error' => 'This room is already assigned to another user']);
        exit;
    }
    $stmt->close();
}

// Build update query
if (!empty($password) && strlen($password) >= 6) {
    // Update with new password
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("UPDATE users SET display_name = ?, email = ?, password = ?, role_id = ?, room_id = ?, is_active = ? WHERE user_id = ?");
    $stmt->bind_param('sssiiii', $display_name, $email, $hash, $role_id, $room_id, $is_active, $user_id);
} else {
    // Update without changing password
    $stmt = $mysqli->prepare("UPDATE users SET display_name = ?, email = ?, role_id = ?, room_id = ?, is_active = ? WHERE user_id = ?");
    $stmt->bind_param('ssiiii', $display_name, $email, $role_id, $room_id, $is_active, $user_id);
}

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'User updated successfully']);
} else {
    $err = $mysqli->error;
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $err]);
}
?>