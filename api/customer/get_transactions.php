<?php
session_start();
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

$transactions = [];
try {
    $stmt = $mysqli->prepare("
        SELECT transaction_id, transaction_type, reference_id, amount, payment_method,
               gcash_account_name, gcash_reference_number, status, cashier_notes, created_at, updated_at
        FROM room_transactions
        WHERE rental_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->bind_param('i', $rental_id);
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
