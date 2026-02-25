<?php
// end_rental.php - end active rental for a room (simple)
session_start();
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'error'=>'Invalid request']);
    exit;
}

// Allow Owner and Cashier only
if (!isset($_SESSION['user_id']) || !in_array(intval($_SESSION['role_id']), [1, 3])) { 
    http_response_code(403); 
    echo json_encode(['success'=>false,'error'=>'Forbidden']); 
    exit; 
}

$room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
if ($room_id <= 0) {
    echo json_encode(['success'=>false,'error'=>'Invalid room']);
    exit;
}

// find active rental
$s = $mysqli->prepare("SELECT rental_id FROM rentals WHERE room_id = ? AND ended_at IS NULL ORDER BY started_at DESC LIMIT 1");
$s->bind_param('i',$room_id);
$s->execute();
$s->bind_result($rental_id);
if (!$s->fetch()) {
    $s->close();
    echo json_encode(['success'=>false,'error'=>'No active rental']);
    exit;
}
$s->close();

// update rental ended_at
$ended = date('Y-m-d H:i:s');
$u = $mysqli->prepare("UPDATE rentals SET ended_at = ? , is_active = 0 WHERE rental_id = ?");
$u->bind_param('si', $ended, $rental_id);
$u->execute();
$u->close();

// Change room status to CLEANING (not available while being cleaned)
$ru = $mysqli->prepare("UPDATE rooms SET status = 'CLEANING' WHERE room_id = ?");
$ru->bind_param('i', $room_id);
$ru->execute();
$ru->close();

echo json_encode(['success'=>true]);