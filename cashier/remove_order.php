<?php
session_start();
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 3) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$order_item_id = isset($_POST['order_item_id']) ? intval($_POST['order_item_id']) : 0;
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$rental_id = isset($_POST['rental_id']) ? intval($_POST['rental_id']) : 0;

if ($order_item_id <= 0 && ($product_id <= 0 || $rental_id <= 0)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$mysqli->begin_transaction();
try {
    // Locate items to remove
    if ($order_item_id > 0) {
        $stmt = $mysqli->prepare("
            SELECT oi.order_item_id, oi.product_id, oi.quantity, oi.price, o.rental_id
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE oi.order_item_id = ?
            LIMIT 1 FOR UPDATE
        ");
        $stmt->bind_param('i', $order_item_id);
    } else {
        $stmt = $mysqli->prepare("
            SELECT oi.order_item_id, oi.product_id, oi.quantity, oi.price, o.rental_id
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE o.rental_id = ? AND oi.product_id = ?
            FOR UPDATE
        ");
        $stmt->bind_param('ii', $rental_id, $product_id);
    }

    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($items) === 0) {
        throw new Exception('Order items not found');
    }

    $total_qty = 0;
    $total_line = 0.0;
    foreach ($items as $it) {
        $total_qty += intval($it['quantity']);
        $total_line += floatval($it['price']) * intval($it['quantity']);
        $rental_id = intval($it['rental_id']);
        $product_id = intval($it['product_id']);
    }

    // Delete items
    if ($order_item_id > 0) {
        $stmt = $mysqli->prepare("DELETE FROM order_items WHERE order_item_id = ?");
        $stmt->bind_param('i', $order_item_id);
    } else {
        $stmt = $mysqli->prepare("
            DELETE oi FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            WHERE o.rental_id = ? AND oi.product_id = ?
        ");
        $stmt->bind_param('ii', $rental_id, $product_id);
    }
    $stmt->execute();
    $stmt->close();

    // Return stock
    $stmt = $mysqli->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
    $stmt->bind_param('ii', $total_qty, $product_id);
    $stmt->execute();
    $stmt->close();

    // Update bill totals
    $stmt = $mysqli->prepare("SELECT bill_id, total_orders_cost FROM bills WHERE rental_id = ? ORDER BY created_at DESC LIMIT 1 FOR UPDATE");
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($bill) {
        $new_orders = round(floatval($bill['total_orders_cost']) - $total_line, 2);
        if ($new_orders < 0) $new_orders = 0;  // Prevent negative

        $stmt = $mysqli->prepare("SELECT COALESCE(SUM(cost), 0) as total_ext FROM rental_extensions WHERE rental_id = ? AND is_paid = 0");
        $stmt->bind_param('i', $rental_id);
        $stmt->execute();
        $ext_sum = floatval($stmt->get_result()->fetch_assoc()['total_ext']);
        $stmt->close();

        $new_grand = round($ext_sum + $new_orders, 2);
        if ($new_grand < 0) $new_grand = 0;  // Prevent negative
        
        // Update is_paid flag: if no additional charges, bill is paid
        $is_paid = ($new_grand > 0) ? 0 : 1;

        $stmt = $mysqli->prepare("UPDATE bills SET total_orders_cost = ?, grand_total = ?, is_paid = ? WHERE bill_id = ?");
        $stmt->bind_param('ddii', $new_orders, $new_grand, $is_paid, $bill['bill_id']);
        $stmt->execute();
        $stmt->close();
    }

    $mysqli->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
