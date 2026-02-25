<?php
session_start();
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id']) || !in_array(intval($_SESSION['role_id']), [1, 3])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$transactions = [];
try {
    $stmt = $mysqli->prepare("
        SELECT rt.transaction_id, rt.rental_id, rt.room_id, rt.transaction_type, rt.reference_id,
               rt.amount, rt.payment_method, rt.gcash_account_name, rt.gcash_reference_number,
               rt.status, rt.cashier_notes, rt.created_at,
               rm.room_number
        FROM room_transactions rt
        JOIN rooms rm ON rt.room_id = rm.room_id
        WHERE rt.status IN ('PENDING_CASHIER_VERIFICATION', 'PENDING_CASH_COLLECTION')
        ORDER BY rt.created_at ASC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    // Table doesn't exist - return empty array
}

echo json_encode(['success' => true, 'transactions' => $transactions]);
exit;
