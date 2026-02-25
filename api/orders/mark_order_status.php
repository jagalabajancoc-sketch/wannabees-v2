<?php
// mark_order_status.php - change order status with permission checks, write audit + notify WS
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit; }
if (!isset($_SESSION['user_id']) || !in_array(intval($_SESSION['role_id']), [1,3])) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden']); exit; }

$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
$status = isset($_POST['status']) ? strtoupper(trim($_POST['status'])) : '';
$valid = ['NEW','PREPARING','READY_TO_DELIVER','DELIVERING','DELIVERED'];
if ($order_id <= 0 || !in_array($status, $valid)) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid input']); exit; }

$user_id = intval($_SESSION['user_id']);
$user_role = intval($_SESSION['role_id']);
$now = date('Y-m-d H:i:s');

$mysqli->begin_transaction();
try {
    $stmt = $mysqli->prepare("SELECT order_id, status, assigned_staff_id FROM orders WHERE order_id = ? LIMIT 1 FOR UPDATE");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) throw new Exception('Order not found');
    $current = $row['status'];
    $assigned = $row['assigned_staff_id'] ? intval($row['assigned_staff_id']) : null;

    // Transition map for new flow; READY→DELIVERED kept for backward compat with pre-migration orders
    $allowed = ['NEW' => ['PREPARING'], 'PREPARING' => ['READY_TO_DELIVER'], 'READY_TO_DELIVER' => ['DELIVERING'], 'DELIVERING' => ['DELIVERED'], 'READY' => ['DELIVERED']];

    if (!isset($allowed[$current]) || !in_array($status, $allowed[$current])) throw new Exception("Invalid transition {$current} -> {$status}");

    $stmt = $mysqli->prepare("UPDATE orders SET status = ?, prepared_at = ? WHERE order_id = ?");
    $stmt->bind_param('ssi', $status, $now, $order_id);
    $stmt->execute();
    $stmt->close();

    // write audit
    $meta = json_encode(['from' => $current, 'to' => $status]);
    $stmt = $mysqli->prepare("INSERT INTO order_audit (order_id, action, user_id, role_id, meta, created_at) VALUES (?, 'STATUS_CHANGE', ?, ?, ?, ?)");
    $stmt->bind_param('iisss', $order_id, $user_id, $user_role, $meta, $now);
    $stmt->execute();
    $stmt->close();

    $mysqli->commit();

    // notify WS
    $notify = ['type' => 'status_change', 'order_id' => $order_id, 'from' => $current, 'to' => $status, 'by' => $user_id, 'at' => $now];
    $ch = curl_init('http://127.0.0.1:8080/notify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notify));
    @curl_exec($ch); @curl_close($ch);

    echo json_encode(['success'=>true,'order_id'=>$order_id,'status'=>$status]);
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    exit;
}