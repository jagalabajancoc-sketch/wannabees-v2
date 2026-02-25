<?php
session_start();
require_once __DIR__ . '/../../db.php';

if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 1) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_id'])) {
    $room_id = intval($_POST['room_id']);
    
    // Get current status
    $check = $mysqli->prepare("SELECT is_active FROM rooms WHERE room_id = ?");
    $check->bind_param('i', $room_id);
    $check->execute();
    $result = $check->get_result();
    $room = $result->fetch_assoc();
    $check->close();
    
    if (!$room) {
        echo json_encode(['success' => false, 'message' => 'Room not found']);
        exit;
    }
    
    // Toggle status
    $new_status = $room['is_active'] ? 0 : 1;
    $stmt = $mysqli->prepare("UPDATE rooms SET is_active = ? WHERE room_id = ?");
    $stmt->bind_param('ii', $new_status, $room_id);
    $stmt->execute();
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'is_active' => $new_status,
        'message' => $new_status ? 'Room activated' : 'Room deactivated'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>
