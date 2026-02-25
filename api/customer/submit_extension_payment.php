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
$room_id   = intval($_SESSION['customer_room_id']);
$minutes   = isset($_POST['minutes']) ? intval($_POST['minutes']) : 0;
$payment_method         = isset($_POST['payment_method'])         ? strtoupper(trim($_POST['payment_method'])) : '';
$gcash_account_name     = isset($_POST['gcash_account_name'])     ? trim($_POST['gcash_account_name'])         : null;
$gcash_reference_number = isset($_POST['gcash_reference_number']) ? trim($_POST['gcash_reference_number'])     : null;

if ($minutes <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid minutes']);
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
$stmt = $mysqli->prepare("SELECT rental_id, room_id, total_minutes FROM rentals WHERE rental_id = ? AND ended_at IS NULL LIMIT 1 FOR UPDATE");
$stmt->bind_param('i', $rental_id);
$stmt->execute();
$rental = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$rental) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Rental not found or already ended']);
    exit;
}

// Get room type pricing
$stmt = $mysqli->prepare("SELECT rt.price_per_30min, rt.price_per_hour FROM room_types rt JOIN rooms r ON rt.room_type_id = r.room_type_id WHERE r.room_id = ? LIMIT 1");
$stmt->bind_param('i', $room_id);
$stmt->execute();
$rt = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$rt) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Room type not found']);
    exit;
}

$price30 = floatval($rt['price_per_30min']);
if ($price30 <= 0) $price30 = floatval($rt['price_per_hour']) / 2;
$cost = round($price30 * ($minutes / 30), 2);

$mysqli->begin_transaction();
try {
    $now = date('Y-m-d H:i:s');

    // Create extension
    $stmt = $mysqli->prepare("INSERT INTO rental_extensions (rental_id, minutes_added, cost, extended_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('iids', $rental_id, $minutes, $cost, $now);
    $stmt->execute();
    $extension_id = $stmt->insert_id;
    $stmt->close();

    // Update total_minutes
    $stmt = $mysqli->prepare("UPDATE rentals SET total_minutes = total_minutes + ? WHERE rental_id = ?");
    $stmt->bind_param('ii', $minutes, $rental_id);
    $stmt->execute();
    $stmt->close();

    // Update bill
    $stmt = $mysqli->prepare("SELECT bill_id, total_room_cost, total_orders_cost FROM bills WHERE rental_id = ? ORDER BY created_at DESC LIMIT 1 FOR UPDATE");
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$bill) throw new Exception('Bill not found');

    $bill_id  = intval($bill['bill_id']);
    $new_room  = round(floatval($bill['total_room_cost']) + $cost, 2);
    
    // Calculate grand total: extensions + orders (excludes base room cost)
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(cost), 0) as total_ext FROM rental_extensions WHERE rental_id = ?");
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $ext_sum = floatval($stmt->get_result()->fetch_assoc()['total_ext']);
    $stmt->close();
    
    $new_grand = round($ext_sum + floatval($bill['total_orders_cost']), 2);
    $stmt = $mysqli->prepare("UPDATE bills SET total_room_cost = ?, grand_total = ? WHERE bill_id = ?");
    $stmt->bind_param('ddi', $new_room, $new_grand, $bill_id);
    $stmt->execute();
    $stmt->close();

    // Determine transaction status
    $tx_status = ($payment_method === 'GCASH') ? 'PENDING_CASHIER_VERIFICATION' : 'PENDING_CASH_COLLECTION';

    // Insert room_transaction (optional - if table exists)
    $transaction_id = 0;
    try {
        $stmt = $mysqli->prepare("INSERT INTO room_transactions (rental_id, room_id, transaction_type, reference_id, amount, payment_method, gcash_account_name, gcash_reference_number, status, created_at) VALUES (?, ?, 'EXTENSION', ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iiidsssss', $rental_id, $room_id, $extension_id, $cost, $payment_method, $gcash_account_name, $gcash_reference_number, $tx_status, $now);
        $stmt->execute();
        $transaction_id = $stmt->insert_id;
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        // Table doesn't exist - transactions feature not enabled
    }

    $mysqli->commit();

    echo json_encode([
        'success'        => true,
        'extension_id'   => $extension_id,
        'transaction_id' => $transaction_id,
        'cost'           => $cost,
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
