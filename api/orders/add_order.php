<?php
session_start();
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit; }
if (!isset($_SESSION['user_id'])) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden']); exit; }

$room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
$items = isset($_POST['items']) ? json_decode($_POST['items'], true) : null; // expect JSON array [{product_id, quantity}, ...]
$amount_tendered = isset($_POST['amount_tendered']) ? floatval($_POST['amount_tendered']) : null;
if ($room_id <= 0 || !is_array($items) || count($items) === 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid input']); exit; }

// Normalize and validate items
$validatedItems = [];
foreach ($items as $it) {
    $pid = isset($it['product_id']) ? intval($it['product_id']) : 0;
    $qty = isset($it['quantity']) ? intval($it['quantity']) : 0;
    if ($pid > 0 && $qty > 0) $validatedItems[] = ['product_id' => $pid, 'quantity' => $qty];
}
if (count($validatedItems) === 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'No valid items provided']); exit; }
$items = $validatedItems;

// find active rental for the room
$stmt = $mysqli->prepare("SELECT rental_id FROM rentals WHERE room_id = ? AND ended_at IS NULL LIMIT 1");
$stmt->bind_param('i', $room_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) { echo json_encode(['success'=>false,'error'=>'No active rental for this room']); exit; }
$r = $res->fetch_assoc(); $rental_id = intval($r['rental_id']);
$stmt->close();

// Begin transaction
$mysqli->begin_transaction();
try {
    $now = date('Y-m-d H:i:s');
    $stmt = $mysqli->prepare("INSERT INTO orders (rental_id, ordered_at, status) VALUES (?, ?, 'NEW')");
    $stmt->bind_param('is', $rental_id, $now);
    $stmt->execute();
    $order_id = $stmt->insert_id;
    $stmt->close();

    $orderTotal = 0.0;
    $insertedCount = 0;
    foreach ($items as $it) {
        $pid = intval($it['product_id']);
        $qty = intval($it['quantity']);
        if ($pid <= 0 || $qty <= 0) continue;

        // fetch product price, stock and active flag (lock row for update)
        $stmt = $mysqli->prepare("SELECT product_name, price, stock_quantity, is_active FROM products WHERE product_id = ? LIMIT 1 FOR UPDATE");
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$prod) throw new Exception("Product {$pid} not found");
        if (intval($prod['is_active']) !== 1) throw new Exception("Product {$pid} is not available");
        if (intval($prod['stock_quantity']) < $qty) throw new Exception("Insufficient stock for product {$pid}");

        $price = floatval($prod['price']);
        $line = round($price * $qty, 2);
        $orderTotal += $line;

        // insert order item
        $stmt = $mysqli->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iiid', $order_id, $pid, $qty, $price);
        $stmt->execute();
        if ($stmt->affected_rows <= 0) throw new Exception('Failed to insert order item');
        $stmt->close();

        // deduct stock safely
        $stmt = $mysqli->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND stock_quantity >= ?");
        $stmt->bind_param('iii', $qty, $pid, $qty);
        $stmt->execute();
        if ($stmt->affected_rows <= 0) throw new Exception("Insufficient stock when updating product {$pid}");
        $stmt->close();

        $insertedCount++;
    }

    // ensure we added at least one item
    if ($insertedCount === 0) throw new Exception('No items were added to the order');

    // validate and store amount_tendered if provided
    $change_amount = null;
    if ($amount_tendered !== null) {
        if ($amount_tendered < $orderTotal) throw new Exception("Amount tendered (₱{$amount_tendered}) is less than the order total (₱{$orderTotal})");
        $change_amount = round($amount_tendered - $orderTotal, 2);
        $stmt = $mysqli->prepare("UPDATE orders SET amount_tendered = ?, change_amount = ? WHERE order_id = ?");
        $stmt->bind_param('ddi', $amount_tendered, $change_amount, $order_id);
        $stmt->execute();
        $stmt->close();
    }

    // update bill totals (add to total_orders_cost and grand_total)
    $stmt = $mysqli->prepare("SELECT bill_id, total_room_cost, total_orders_cost FROM bills WHERE rental_id = ? ORDER BY created_at DESC LIMIT 1 FOR UPDATE");
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$bill) throw new Exception('Bill not found');

    $bill_id = intval($bill['bill_id']);
    $new_orders = round(floatval($bill['total_orders_cost']) + $orderTotal, 2);
    
    // Calculate grand total: extensions + orders (only unpaid items, excludes already-paid room cost)
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(cost), 0) as total_ext FROM rental_extensions WHERE rental_id = ? AND is_paid = 0");
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $ext_sum = floatval($stmt->get_result()->fetch_assoc()['total_ext']);
    $stmt->close();
    
    $new_grand = round($ext_sum + $new_orders, 2);
    
    // Also update is_paid flag: if there are additional charges, bill becomes unpaid
    $is_paid = ($new_grand > 0) ? 0 : 1;

    $stmt = $mysqli->prepare("UPDATE bills SET total_orders_cost = ?, grand_total = ?, is_paid = ? WHERE bill_id = ?");
    $stmt->bind_param('ddii', $new_orders, $new_grand, $is_paid, $bill_id);
    $stmt->execute();
    $stmt->close();

    // write audit log
    $user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
    $role_id = isset($_SESSION['role_id']) ? intval($_SESSION['role_id']) : null;
    $meta = json_encode(['order_total' => $orderTotal, 'items_count' => $insertedCount]);
    $stmt = $mysqli->prepare("INSERT INTO order_audit (order_id, action, user_id, role_id, meta, created_at) VALUES (?, 'CREATED', ?, ?, ?, ?)");
    $stmt->bind_param('iiiss', $order_id, $user_id, $role_id, $meta, $now);
    $stmt->execute();
    $stmt->close();

    $mysqli->commit();

    // notify WebSocket server (optional/can fail silently)
    $notify = [
        'type' => 'new_order',
        'order_id' => $order_id,
        'rental_id' => $rental_id,
        'bill_id' => $bill_id,
        'order_total' => round($orderTotal,2),
        'timestamp' => $now
    ];
    $ws_url = 'http://127.0.0.1:8080/notify';
    // fire-and-forget via curl (timeout short)
    $ch = curl_init($ws_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notify));
    @curl_exec($ch);
    @curl_close($ch);

    echo json_encode(['success'=>true,'order_id'=>$order_id,'order_total'=>round($orderTotal,2),'bill_id'=>$bill_id,'total_orders'=>$new_orders,'grand_total'=>$new_grand,'amount_tendered'=>$amount_tendered,'change_amount'=>$change_amount]);
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error: '.$e->getMessage()]);
    exit;
}