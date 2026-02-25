<?php
session_start();
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array(intval($_SESSION['role_id']), [1, 3])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$sql = "
SELECT 
    o.order_id,
    o.rental_id,
    o.ordered_at,
    o.status,
    o.amount_tendered,
    o.change_amount,
    r.room_id,
    rm.room_number,
    GROUP_CONCAT(
        CONCAT(p.product_name, ' x', oi.quantity) 
        SEPARATOR ', '
    ) as items,
    SUM(oi.price * oi.quantity) as total
FROM orders o
JOIN order_items oi ON o.order_id = oi.order_id
JOIN products p ON oi.product_id = p.product_id
JOIN rentals r ON o.rental_id = r.rental_id
JOIN rooms rm ON r.room_id = rm.room_id
WHERE r.ended_at IS NULL AND o.status IN ('NEW', 'PREPARING', 'READY_TO_DELIVER', 'DELIVERING')
GROUP BY o.order_id
ORDER BY o.ordered_at DESC
LIMIT 50";

$result = $mysqli->query($sql);
$orders = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = [
            'order_id' => intval($row['order_id']),
            'rental_id' => intval($row['rental_id']),
            'room_number' => intval($row['room_number']),
            'status' => $row['status'],
            'items' => $row['items'],
            'total' => floatval($row['total']),
            'amount_tendered' => $row['amount_tendered'] !== null ? floatval($row['amount_tendered']) : null,
            'change_amount' => $row['change_amount'] !== null ? floatval($row['change_amount']) : null,
            'ordered_at' => $row['ordered_at'],
            'time_ago' => getTimeAgo($row['ordered_at'])
        ];
    }
    $result->free();
}

echo json_encode(['success' => true, 'orders' => $orders]);

function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    return date('M d, g:i A', $time);
}