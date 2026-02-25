<?php
// get_bill.php - returns bill details (rental, orders, extensions) as JSON
error_reporting(0); // Suppress errors to ensure JSON output
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../../db.php';
} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>'Database connection failed']);
    exit;
}

$bill_id = isset($_GET['bill_id']) ? intval($_GET['bill_id']) : (isset($_POST['bill_id']) ? intval($_POST['bill_id']) : 0);
$rental_id = isset($_GET['rental_id']) ? intval($_GET['rental_id']) : (isset($_POST['rental_id']) ? intval($_POST['rental_id']) : 0);

if ($bill_id <= 0 && $rental_id <= 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Provide bill_id or rental_id']); exit; }

try {
    if ($bill_id > 0) {
        $stmt = $mysqli->prepare("SELECT bill_id, rental_id, total_room_cost, total_orders_cost, grand_total, is_paid, created_at FROM bills WHERE bill_id = ? LIMIT 1");
        $stmt->bind_param('i', $bill_id);
        $stmt->execute();
        $bill = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$bill) { echo json_encode(['success'=>false,'error'=>'Bill not found']); exit; }
        $rental_id = intval($bill['rental_id']);
    } else {
        $stmt = $mysqli->prepare("SELECT bill_id, rental_id, total_room_cost, total_orders_cost, grand_total, is_paid, created_at FROM bills WHERE rental_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param('i', $rental_id);
        $stmt->execute();
        $bill = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$bill) { echo json_encode(['success'=>false,'error'=>'Bill not found for rental']); exit; }
        $bill_id = intval($bill['bill_id']);
    }

    $stmt = $mysqli->prepare("SELECT r.rental_id, r.room_id, r.started_at, r.ended_at, r.total_minutes, rm.room_number, rt.type_name FROM rentals r JOIN rooms rm ON r.room_id = rm.room_id JOIN room_types rt ON rm.room_type_id = rt.room_type_id WHERE r.rental_id = ? LIMIT 1");
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $rental = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $orders = [];
    $stmt = $mysqli->prepare("
        SELECT oi.order_item_id, oi.order_id, oi.product_id, p.product_name, oi.quantity, oi.price, o.ordered_at, o.status
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        JOIN products p ON oi.product_id = p.product_id
        WHERE o.rental_id = ?
        ORDER BY o.ordered_at ASC
    ");
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $orders[] = $row;
    $stmt->close();

    $extensions = [];
    $stmt = $mysqli->prepare("SELECT extension_id, minutes_added, cost, extended_at FROM rental_extensions WHERE rental_id = ? ORDER BY extended_at ASC");
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $extensions[] = $row;
    $stmt->close();

    echo json_encode(['success'=>true,'bill'=>$bill,'rental'=>$rental,'orders'=>$orders,'extensions'=>$extensions]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error: ' . $e->getMessage()]);
}