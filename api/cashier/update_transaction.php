<?php
session_start();
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id']) || !in_array(intval($_SESSION['role_id']), [1, 3])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$cashier_id     = intval($_SESSION['user_id']);
$transaction_id = isset($_POST['transaction_id']) ? intval($_POST['transaction_id']) : 0;
$action         = isset($_POST['action'])         ? strtolower(trim($_POST['action'])) : '';
$cashier_notes  = isset($_POST['cashier_notes'])  ? trim($_POST['cashier_notes'])      : null;

if ($transaction_id <= 0 || !in_array($action, ['approve', 'reject', 'mark_collected', 'complete'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

// Fetch transaction
try {
    $stmt = $mysqli->prepare("SELECT * FROM room_transactions WHERE transaction_id = ? LIMIT 1 FOR UPDATE");
    $stmt->bind_param('i', $transaction_id);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Transaction system not available']);
    exit;
}
if (!$tx) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Transaction not found']);
    exit;
}

$current_status = $tx['status'];
$new_status     = null;
$update_payment = false;

switch ($action) {
    case 'approve':
        if ($current_status !== 'PENDING_CASHIER_VERIFICATION') {
            echo json_encode(['success' => false, 'error' => 'Transaction is not pending cashier verification']);
            exit;
        }
        $new_status = 'APPROVED';
        $update_payment = true;
        break;

    case 'reject':
        if ($current_status !== 'PENDING_CASHIER_VERIFICATION') {
            echo json_encode(['success' => false, 'error' => 'Transaction is not pending cashier verification']);
            exit;
        }
        $new_status = 'REJECTED';
        break;

    case 'mark_collected':
        if ($current_status !== 'PENDING_CASH_COLLECTION') {
            echo json_encode(['success' => false, 'error' => 'Transaction is not pending cash collection']);
            exit;
        }
        $new_status = 'PAID';
        $update_payment = true;
        break;

    case 'complete':
        if (!in_array($current_status, ['APPROVED', 'PAID'])) {
            echo json_encode(['success' => false, 'error' => 'Transaction cannot be completed from current status']);
            exit;
        }
        $new_status = 'COMPLETED';
        break;
}

$mysqli->begin_transaction();
try {
    $now = date('Y-m-d H:i:s');

    // Update transaction status
    $stmt = $mysqli->prepare("UPDATE room_transactions SET status = ?, cashier_id = ?, cashier_notes = ?, updated_at = ? WHERE transaction_id = ?");
    $stmt->bind_param('sissi', $new_status, $cashier_id, $cashier_notes, $now, $transaction_id);
    $stmt->execute();
    $stmt->close();

    // If approved/paid, update bills and insert into payments table
    if ($update_payment) {
        $rental_id = intval($tx['rental_id']);
        $amount    = floatval($tx['amount']);

        // Get bill
        $stmt = $mysqli->prepare("SELECT bill_id FROM bills WHERE rental_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param('i', $rental_id);
        $stmt->execute();
        $bill = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($bill) {
            $bill_id = intval($bill['bill_id']);

            // Insert payment record
            $pm = $tx['payment_method'];
            $stmt = $mysqli->prepare("INSERT INTO payments (bill_id, amount_paid, payment_method, paid_at) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('idss', $bill_id, $amount, $pm, $now);
            $stmt->execute();
            $stmt->close();

            // Mark bill as paid if total payments cover the grand_total
            $stmt = $mysqli->prepare("SELECT grand_total FROM bills WHERE bill_id = ?");
            $stmt->bind_param('i', $bill_id);
            $stmt->execute();
            $billRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $stmt = $mysqli->prepare("SELECT COALESCE(SUM(amount_paid), 0) AS total_paid FROM payments WHERE bill_id = ?");
            $stmt->bind_param('i', $bill_id);
            $stmt->execute();
            $payRow = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($billRow && $payRow && floatval($payRow['total_paid']) >= floatval($billRow['grand_total'])) {
                $stmt = $mysqli->prepare("UPDATE bills SET is_paid = 1 WHERE bill_id = ?");
                $stmt->bind_param('i', $bill_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    $mysqli->commit();
    echo json_encode(['success' => true, 'transaction_id' => $transaction_id, 'new_status' => $new_status]);
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
