<?php
// mark_available.php - set room status = AVAILABLE and add cleaning_logs entry
require_once __DIR__ . '/../../db.php';
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Invalid method']); exit; }
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || !in_array(intval($_SESSION['role_id']), [1,3])) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden']); exit; }

$room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
if ($room_id <= 0) { echo json_encode(['success'=>false,'error'=>'Invalid room']); exit; }

$staff_id = intval($_SESSION['user_id']);
$now = date('Y-m-d H:i:s');

$mysqli->begin_transaction();
try {
    $stmt = $mysqli->prepare("UPDATE rooms SET status = 'AVAILABLE' WHERE room_id = ? AND status = 'CLEANING'");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    $stmt = $mysqli->prepare("INSERT INTO cleaning_logs (room_id, staff_id, cleaned_at) VALUES (?, ?, ?)");
    $stmt->bind_param('iis', $room_id, $staff_id, $now);
    $stmt->execute();
    $log_id = $stmt->insert_id;
    $stmt->close();

    $mysqli->commit();
    echo json_encode(['success'=>true,'room_id'=>$room_id,'log_id'=>$log_id,'updated'=>$affected > 0]);
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error: '.$e->getMessage()]);
    exit;
}