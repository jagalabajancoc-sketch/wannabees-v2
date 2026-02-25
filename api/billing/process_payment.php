<?php
// process_payment.php - process bill payment, end rental, mark room CLEANING, record transactions
ob_start();
require_once __DIR__ . '/../../db.php';
session_start();
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit; }
if (!isset($_SESSION['user_id'])) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden']); exit; }

$bill_id = isset($_POST['bill_id']) ? intval($_POST['bill_id']) : 0;
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;
$payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'CASH';
$reference_number = isset($_POST['reference_number']) ? trim($_POST['reference_number']) : null;
$cashier_id = intval($_SESSION['user_id']);

if ($bill_id <= 0 || $amount <= 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid request: bill_id and amount are required']); exit; }

if ($payment_method === 'GCASH' && empty($reference_number)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Reference number is required for GCash payments']);
    exit;
}

$mysqli->begin_transaction();
try {
    $stmt = $mysqli->prepare("SELECT b.rental_id, b.grand_total, b.is_paid, rm.room_number FROM bills b LEFT JOIN rentals r ON b.rental_id = r.rental_id LEFT JOIN rooms rm ON r.room_id = rm.room_id WHERE b.bill_id = ? LIMIT 1 FOR UPDATE");
    $stmt->bind_param('i', $bill_id);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$bill) throw new Exception('Bill not found');

    $rental_id = intval($bill['rental_id']);
    
    // Recalculate grand_total correctly: only unpaid extensions + orders (excludes base room cost)
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(cost), 0) as total_ext FROM rental_extensions WHERE rental_id = ? AND is_paid = 0");
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $ext_sum = floatval($stmt->get_result()->fetch_assoc()['total_ext']);
    $stmt->close();
    
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(oi.price * oi.quantity), 0) as total_orders FROM order_items oi JOIN orders o ON oi.order_id = o.order_id WHERE o.rental_id = ? AND o.is_paid = 0");
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $orders_sum = floatval($stmt->get_result()->fetch_assoc()['total_orders']);
    $stmt->close();
    
    $grand_total = round($ext_sum + $orders_sum, 2);
    
    // Check if already paid (based on recalculated grand_total)
    if ($grand_total < 0.01) {
        throw new Exception('Bill already paid (no outstanding charges)');
    }
    
    // Update bill with correct grand_total
    $stmt = $mysqli->prepare("UPDATE bills SET grand_total = ? WHERE bill_id = ?");
    $stmt->bind_param('di', $grand_total, $bill_id);
    $stmt->execute();
    $stmt->close();

    if ($amount < $grand_total - 0.005) { // 0.005 tolerance to handle floating-point rounding (half-cent)
        throw new Exception('Underpayment: amount paid (₱' . number_format($amount, 2) . ') is less than grand total (₱' . number_format($grand_total, 2) . ')');
    }
    $change_amount = round($amount - $grand_total, 2);
    $room_number = $bill['room_number'];

    $now = date('Y-m-d H:i:s');
    $stmt = $mysqli->prepare("INSERT INTO payments (bill_id, amount_paid, payment_method, reference_number, paid_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('idsss', $bill_id, $amount, $payment_method, $reference_number, $now);
    $stmt->execute();
    $payment_id = $stmt->insert_id;
    $stmt->close();

    $date = date('Y-m-d');
    $stmt = $mysqli->prepare("INSERT INTO transactions (bill_id, transaction_date, total_amount) VALUES (?, ?, ?)");
    $stmt->bind_param('isd', $bill_id, $date, $amount);
    $stmt->execute();
    $transaction_id = $stmt->insert_id;
    $stmt->close();

    // Mark all current orders and extensions as paid
    $stmt = $mysqli->prepare("UPDATE orders o SET o.is_paid = 1 WHERE o.rental_id = ? AND o.is_paid = 0");
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("UPDATE rental_extensions SET is_paid = 1 WHERE rental_id = ? AND is_paid = 0");
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $stmt->close();

    // Reset bill totals to 0 since all current charges are now paid
    $stmt = $mysqli->prepare("UPDATE bills SET total_orders_cost = 0, grand_total = 0, is_paid = 1 WHERE bill_id = ?");
    $stmt->bind_param('i', $bill_id);
    $stmt->execute();
    $stmt->close();

    // Note: Rental and room status are NOT changed - customer can continue using the room

    $mysqli->commit();
    echo json_encode([
        'success' => true,
        'bill_id' => $bill_id,
        'payment_id' => $payment_id,
        'transaction_id' => $transaction_id,
        'amount_paid' => $amount,
        'grand_total' => $grand_total,
        'change_amount' => $change_amount,
        'room_number' => $room_number
    ]);
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    exit;
}