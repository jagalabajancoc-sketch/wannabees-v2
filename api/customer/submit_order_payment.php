<?php
session_start();
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['customer_rental_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$rental_id = intval($_SESSION['customer_rental_id']);
$room_id   = isset($_POST['room_id'])   ? intval($_POST['room_id'])   : 0;
$items     = isset($_POST['items'])     ? json_decode($_POST['items'], true) : null;
$payment_method         = isset($_POST['payment_method'])         ? strtoupper(trim($_POST['payment_method'])) : '';
$gcash_account_name     = isset($_POST['gcash_account_name'])     ? trim($_POST['gcash_account_name'])         : null;
$gcash_reference_number = isset($_POST['gcash_reference_number']) ? trim($_POST['gcash_reference_number'])     : null;

if ($room_id <= 0 || !is_array($items) || count($items) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

if (!in_array($payment_method, ['GCASH', 'CASH'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payment method']);
    exit;
}

if ($payment_method === 'GCASH') {
    if (empty($gcash_account_name) || empty($gcash_reference_number)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'GCash account name and reference number are required']);
        exit;
    }
}

// Verify rental belongs to session
$stmt = $mysqli->prepare("SELECT rental_id FROM rentals WHERE rental_id = ? AND room_id = ? AND ended_at IS NULL LIMIT 1");
$stmt->bind_param('ii', $rental_id, $room_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    $stmt->close();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Rental not found or room mismatch']);
    exit;
}
$stmt->close();

// Normalize and validate items
$validatedItems = [];
foreach ($items as $it) {
    $pid = isset($it['product_id']) ? intval($it['product_id']) : 0;
    $qty = isset($it['quantity'])   ? intval($it['quantity'])   : 0;
    if ($pid > 0 && $qty > 0) {
        $validatedItems[] = ['product_id' => $pid, 'quantity' => $qty];
    }
}
if (count($validatedItems) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No valid items provided']);
    exit;
}

$mysqli->begin_transaction();
try {
    $now = date('Y-m-d H:i:s');

    // Create order
    $stmt = $mysqli->prepare("INSERT INTO orders (rental_id, ordered_at, status) VALUES (?, ?, 'NEW')");
    $stmt->bind_param('is', $rental_id, $now);
    $stmt->execute();
    $order_id = $stmt->insert_id;
    $stmt->close();

    $orderTotal   = 0.0;
    $insertedCount = 0;
    foreach ($validatedItems as $it) {
        $pid = $it['product_id'];
        $qty = $it['quantity'];

        $stmt = $mysqli->prepare("SELECT price, stock_quantity, is_active FROM products WHERE product_id = ? LIMIT 1 FOR UPDATE");
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$prod) throw new Exception("Product {$pid} not found");
        if (intval($prod['is_active']) !== 1) throw new Exception("Product {$pid} is not available");
        if (intval($prod['stock_quantity']) < $qty) throw new Exception("Insufficient stock for product {$pid}");

        $price = floatval($prod['price']);
        $line  = round($price * $qty, 2);
        $orderTotal += $line;

        $stmt = $mysqli->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iiid', $order_id, $pid, $qty, $price);
        $stmt->execute();
        if ($stmt->affected_rows <= 0) throw new Exception('Failed to insert order item');
        $stmt->close();

        $stmt = $mysqli->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND stock_quantity >= ?");
        $stmt->bind_param('iii', $qty, $pid, $qty);
        $stmt->execute();
        if ($stmt->affected_rows <= 0) throw new Exception("Insufficient stock when updating product {$pid}");
        $stmt->close();

        $insertedCount++;
    }

    if ($insertedCount === 0) throw new Exception('No items were added to the order');

    // Update bill
    $stmt = $mysqli->prepare("SELECT bill_id, total_room_cost, total_orders_cost FROM bills WHERE rental_id = ? ORDER BY created_at DESC LIMIT 1 FOR UPDATE");
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$bill) throw new Exception('Bill not found');

    $bill_id   = intval($bill['bill_id']);
    $new_orders = round(floatval($bill['total_orders_cost']) + $orderTotal, 2);
    
    // Calculate grand total: extensions + orders (excludes base room cost)
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(cost), 0) as total_ext FROM rental_extensions WHERE rental_id = ?");
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $ext_sum = floatval($stmt->get_result()->fetch_assoc()['total_ext']);
    $stmt->close();
    
    $new_grand  = round($ext_sum + $new_orders, 2);
    $stmt = $mysqli->prepare("UPDATE bills SET total_orders_cost = ?, grand_total = ? WHERE bill_id = ?");
    $stmt->bind_param('ddi', $new_orders, $new_grand, $bill_id);
    $stmt->execute();
    $stmt->close();

    // Audit log
    $meta = json_encode(['order_total' => $orderTotal, 'items_count' => $insertedCount, 'payment_method' => $payment_method]);
    $stmt = $mysqli->prepare("INSERT INTO order_audit (order_id, action, user_id, role_id, meta, created_at) VALUES (?, 'CREATED', NULL, 4, ?, ?)");
    $stmt->bind_param('iss', $order_id, $meta, $now);
    $stmt->execute();
    $stmt->close();

    // Determine transaction status
    $tx_status = ($payment_method === 'GCASH') ? 'PENDING_CASHIER_VERIFICATION' : 'PENDING_CASH_COLLECTION';

    // Insert room_transaction (optional - if table exists)
    $transaction_id = 0;
    try {
        $stmt = $mysqli->prepare("INSERT INTO room_transactions (rental_id, room_id, transaction_type, reference_id, amount, payment_method, gcash_account_name, gcash_reference_number, status, created_at) VALUES (?, ?, 'ORDER', ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iiidsssss', $rental_id, $room_id, $order_id, $orderTotal, $payment_method, $gcash_account_name, $gcash_reference_number, $tx_status, $now);
        $stmt->execute();
        $transaction_id = $stmt->insert_id;
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        // Table doesn't exist - transactions feature not enabled
    }

    $mysqli->commit();

    echo json_encode([
        'success'        => true,
        'order_id'       => $order_id,
        'transaction_id' => $transaction_id,
        'order_total'    => round($orderTotal, 2),
        'bill_id'        => $bill_id,
        'status'         => $tx_status,
        'payment_method' => $payment_method
    ]);
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
