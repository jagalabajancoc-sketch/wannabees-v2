<?php
// toggle_user_status.php - Enable/disable user accounts
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
$is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 0;

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
    exit;
}

// Prevent owner from disabling their own account
if ($user_id == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'error' => 'You cannot disable your own account']);
    exit;
}

$stmt = $mysqli->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
$stmt->bind_param('ii', $is_active, $user_id);

if ($stmt->execute()) {
    $stmt->close();
    $action = $is_active ? 'enabled' : 'disabled';
    echo json_encode(['success' => true, 'message' => "User $action successfully"]);
} else {
    $err = $mysqli->error;
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $err]);
}
?>