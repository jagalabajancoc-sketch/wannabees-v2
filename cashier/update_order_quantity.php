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
$change = isset($_POST['change']) ? intval($_POST['change']) : 0;

if (($order_item_id <= 0 && ($product_id <= 0 || $rental_id <= 0)) || $change == 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$mysqli->begin_transaction();
try {
    // Get order item details
    if ($order_item_id > 0) {
        $stmt = $mysqli->prepare("
            SELECT oi.order_item_id, oi.order_id, oi.product_id, oi.quantity, oi.price,
                   o.rental_id, p.stock_quantity
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            JOIN products p ON oi.product_id = p.product_id
            WHERE oi.order_item_id = ?
            LIMIT 1 FOR UPDATE
        ");
        $stmt->bind_param('i', $order_item_id);
    } else {
        $stmt = $mysqli->prepare("
            SELECT oi.order_item_id, oi.order_id, oi.product_id, oi.quantity, oi.price,
                   o.rental_id, p.stock_quantity
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.order_id
            JOIN products p ON oi.product_id = p.product_id
            WHERE o.rental_id = ? AND oi.product_id = ?
            ORDER BY oi.order_item_id DESC
            LIMIT 1 FOR UPDATE
        ");
        $stmt->bind_param('ii', $rental_id, $product_id);
    }
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item) {
        throw new Exception('Order item not found');
    }

    $targetOrderItemId = intval($item['order_item_id']);
    $new_quantity = intval($item['quantity']) + $change;

    if ($new_quantity <= 0) {
        // Delete the item
        $stmt = $mysqli->prepare("DELETE FROM order_items WHERE order_item_id = ?");
        $stmt->bind_param('i', $targetOrderItemId);
        $stmt->execute();
        $stmt->close();

        // Return stock
        $stmt = $mysqli->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
        $qty_to_return = intval($item['quantity']);
        $stmt->bind_param('ii', $qty_to_return, $item['product_id']);
        $stmt->execute();
        $stmt->close();

        $line_total_change = -1 * (floatval($item['price']) * intval($item['quantity']));
    } else {
        // Check stock availability if increasing quantity
        if ($change > 0) {
            if (intval($item['stock_quantity']) < $change) {
                throw new Exception('Insufficient stock available');
            }
        }

        // Update quantity
        $stmt = $mysqli->prepare("UPDATE order_items SET quantity = ? WHERE order_item_id = ?");
        $stmt->bind_param('ii', $new_quantity, $targetOrderItemId);
        $stmt->execute();
        $stmt->close();

        // Update product stock
        $stock_change = -1 * $change; // Negative of change (increase order = decrease stock)
        $stmt = $mysqli->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
        $stmt->bind_param('ii', $stock_change, $item['product_id']);
        $stmt->execute();
        $stmt->close();

        $line_total_change = floatval($item['price']) * $change;
    }

    // Update bill
    $stmt = $mysqli->prepare("
        SELECT bill_id, total_room_cost, total_orders_cost, grand_total
        FROM bills
        WHERE rental_id = ?
        ORDER BY created_at DESC
        LIMIT 1 FOR UPDATE
    ");
    $stmt->bind_param('i', $item['rental_id']);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($bill) {
        $new_orders_cost = round(floatval($bill['total_orders_cost']) + $line_total_change, 2);
        
        // Calculate grand total: unpaid extensions + orders (excludes base room cost)
        $stmt = $mysqli->prepare("SELECT COALESCE(SUM(cost), 0) as total_ext FROM rental_extensions WHERE rental_id = ? AND is_paid = 0");
        $stmt->bind_param('i', $item['rental_id']);
        $stmt->execute();
        $ext_sum = floatval($stmt->get_result()->fetch_assoc()['total_ext']);
        $stmt->close();
        
        $new_grand_total = round($ext_sum + $new_orders_cost, 2);
        
        // Update is_paid flag: if there are additional charges, bill becomes unpaid
        $is_paid = ($new_grand_total > 0) ? 0 : 1;

        $stmt = $mysqli->prepare("UPDATE bills SET total_orders_cost = ?, grand_total = ?, is_paid = ? WHERE bill_id = ?");
        $stmt->bind_param('ddii', $new_orders_cost, $new_grand_total, $is_paid, $bill['bill_id']);
        $stmt->execute();
        $stmt->close();
    }

    $mysqli->commit();
    echo json_encode(['success' => true, 'message' => 'Quantity updated successfully']);
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
