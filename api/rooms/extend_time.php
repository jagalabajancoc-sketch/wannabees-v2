<?php
// extend_time.php - add rental extension, update rental.total_minutes and bills
session_start();
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    http_response_code(405); 
    echo json_encode(['success'=>false,'error'=>'Method not allowed']); 
    exit; 
}

// Allow Owner, Cashier, and Customer to extend time
if (!isset($_SESSION['user_id']) || !in_array(intval($_SESSION['role_id']), [1, 3, 4])) { 
    http_response_code(403); 
    echo json_encode(['success'=>false,'error'=>'Forbidden']); 
    exit; 
}

$rental_id = isset($_POST['rental_id']) ? intval($_POST['rental_id']) : 0;
$minutes = isset($_POST['minutes']) ? intval($_POST['minutes']) : 0;
if ($rental_id <= 0 || $minutes <= 0) { 
    echo json_encode(['success'=>false,'error'=>'Invalid input']); 
    exit; 
}

$stmt = $mysqli->prepare("SELECT rental_id, room_id, total_minutes FROM rentals WHERE rental_id = ? AND ended_at IS NULL LIMIT 1 FOR UPDATE");
$stmt->bind_param('i', $rental_id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$r) { 
    echo json_encode(['success'=>false,'error'=>'Rental not found or already ended']); 
    exit; 
}
$room_id = intval($r['room_id']);

$stmt = $mysqli->prepare("SELECT price_per_30min, price_per_hour FROM room_types rt JOIN rooms r ON rt.room_type_id = r.room_type_id WHERE r.room_id = ? LIMIT 1");
$stmt->bind_param('i', $room_id);
$stmt->execute();
$rt = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$rt) { 
    echo json_encode(['success'=>false,'error'=>'Room type not found']); 
    exit; 
}
$price30 = floatval($rt['price_per_30min']);
if ($price30 <= 0) $price30 = floatval($rt['price_per_hour']) / 2;
$cost = round($price30 * ($minutes / 30), 2);

$mysqli->begin_transaction();
try {
    $now = date('Y-m-d H:i:s');
    $stmt = $mysqli->prepare("INSERT INTO rental_extensions (rental_id, minutes_added, cost, extended_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('iids', $rental_id, $minutes, $cost, $now);
    $stmt->execute();
    $extension_id = $stmt->insert_id;
    $stmt->close();

    // update rentals.total_minutes
    $stmt = $mysqli->prepare("UPDATE rentals SET total_minutes = total_minutes + ? WHERE rental_id = ?");
    $stmt->bind_param('ii', $minutes, $rental_id);
    $stmt->execute();
    $stmt->close();

    // update bill totals
    $stmt = $mysqli->prepare("SELECT bill_id, total_room_cost, total_orders_cost FROM bills WHERE rental_id = ? ORDER BY created_at DESC LIMIT 1 FOR UPDATE");
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$bill) throw new Exception('Bill not found');

    $bill_id = intval($bill['bill_id']);
    $new_room = round(floatval($bill['total_room_cost']) + $cost, 2);
    
    // Calculate grand total: sum of unpaid extensions + orders (excludes base room cost)
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(cost), 0) as total_ext FROM rental_extensions WHERE rental_id = ? AND is_paid = 0");
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $ext_sum = floatval($stmt->get_result()->fetch_assoc()['total_ext']);
    $stmt->close();
    
    $new_grand = round($ext_sum + floatval($bill['total_orders_cost']), 2);
    
    // Update is_paid flag: if there are additional charges, bill becomes unpaid
    $is_paid = ($new_grand > 0) ? 0 : 1;
    
    $stmt = $mysqli->prepare("UPDATE bills SET total_room_cost = ?, grand_total = ?, is_paid = ? WHERE bill_id = ?");
    $stmt->bind_param('ddii', $new_room, $new_grand, $is_paid, $bill_id);
    $stmt->execute();
    $stmt->close();

    $mysqli->commit();
    echo json_encode(['success'=>true,'extension_id'=>$extension_id,'cost'=>$cost,'bill_id'=>$bill_id]);
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error: '.$e->getMessage()]);
    exit;
}