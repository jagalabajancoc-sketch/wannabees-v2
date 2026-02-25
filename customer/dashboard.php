<?php
session_start();
require_once __DIR__ . '/../db.php';

// Check if customer has valid session
if (!isset($_SESSION['customer_rental_id'])) {
    header('Location: ../index.php');
    exit;
}

$rental_id = intval($_SESSION['customer_rental_id']);
$room_id = intval($_SESSION['customer_room_id']);
$room_number = intval($_SESSION['customer_room_number']);

// Fetch rental details
$rentalSql = "
SELECT r.*, rm.room_number, rt.type_name, rt.price_per_hour, rt.price_per_30min, b.bill_id, b.grand_total, b.is_paid
FROM rentals r
JOIN rooms rm ON r.room_id = rm.room_id
JOIN room_types rt ON rm.room_type_id = rt.room_type_id
LEFT JOIN bills b ON r.rental_id = b.rental_id
WHERE r.rental_id = ? AND r.ended_at IS NULL
LIMIT 1";
$stmt = $mysqli->prepare($rentalSql);
$stmt->bind_param('i', $rental_id);
$stmt->execute();
$rental = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rental) {
    // Rental ended or invalid
    session_destroy();
    header('Location: ../index.php');
    exit;
}

// Fetch products
$productsSql = "SELECT product_id, product_name, price, stock_quantity, is_active, Category FROM products WHERE is_active = 1 AND stock_quantity > 0 ORDER BY Category, product_name ASC";
$productsRes = $mysqli->query($productsSql);
$products = [];
$categories = [];
while ($p = $productsRes->fetch_assoc()) {
    $products[] = $p;
    if (!isset($categories[$p['Category']])) {
        $categories[$p['Category']] = [];
    }
    $categories[$p['Category']][] = $p;
}

// Fetch customer's orders
$ordersSql = "
SELECT o.order_id, o.ordered_at, o.status,
       GROUP_CONCAT(CONCAT(p.product_name, ' x', oi.quantity) SEPARATOR ', ') as items,
       SUM(oi.price * oi.quantity) as total
FROM orders o
JOIN order_items oi ON o.order_id = oi.order_id
JOIN products p ON oi.product_id = p.product_id
WHERE o.rental_id = ?
GROUP BY o.order_id
ORDER BY o.ordered_at DESC";
$stmt = $mysqli->prepare($ordersSql);
$stmt->bind_param('i', $rental_id);
$stmt->execute();
$ordersRes = $stmt->get_result();
$customerOrders = [];
while ($order = $ordersRes->fetch_assoc()) {
    $customerOrders[] = $order;
}
$stmt->close();

// Fetch room transactions for this rental (handle missing table gracefully)
$roomTransactions = [];
try {
    $txSql = "SELECT transaction_id, transaction_type, amount, payment_method, gcash_account_name, gcash_reference_number, status, created_at FROM room_transactions WHERE rental_id = ? ORDER BY created_at DESC";
    $stmt = $mysqli->prepare($txSql);
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $txRes = $stmt->get_result();
    while ($tx = $txRes->fetch_assoc()) {
        $roomTransactions[] = $tx;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    // Table doesn't exist yet - transactions feature not enabled
    $roomTransactions = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room <?= $room_number ?> - Wannabees KTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #2c2c2c;
            line-height: 1.5;
        }
        header {
            background: white;
            padding: 15px 25px;
            border-bottom: 1px solid #e9ecef;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-left { display: flex; align-items: center; gap: 15px; }
        .header-left img { height: 45px; }
        .header-title { font-size: 18px; font-weight: 700; }
        .header-subtitle { font-size: 13px; color: #666; }
        .header-nav { display: flex; gap: 10px; }
        .nav-btn {
            padding: 10px 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nav-btn:hover { background: #f8f9fa; }
        .nav-btn.guide { background: #3498db; color: white; border-color: #3498db; }
        .nav-btn.guide:hover { background: #2980b9; }

        main {
            padding: 25px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Room Info Card */
        .room-info-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .room-title { font-size: 22px; font-weight: 700; margin-bottom: 5px; }
        .room-type { font-size: 14px; color: #666; margin-bottom: 20px; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .info-box {
            background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
            padding: 20px;
            border-radius: 12px;
            color: white;
            text-align: center;
        }
        .info-box.bill { background: linear-gradient(135deg, #27ae60 0%, #229954 100%); }
        .info-box.warning { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.02); } }
        .info-label { font-size: 13px; opacity: 0.9; margin-bottom: 8px; }
        .info-value { font-size: 28px; font-weight: 700; font-family: monospace; }
        .info-subtext { font-size: 12px; opacity: 0.8; margin-top: 5px; }
        .extend-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            font-size: 13px;
            cursor: pointer;
            margin-top: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .extend-btn:hover { background: rgba(255,255,255,0.3); }

        /* Payment Request Status */
        .payment-status-banner {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .payment-status-banner.pending { background: #fff3cd; border-color: #ffc107; }
        .payment-status-banner.assigned { background: #d1ecf1; border-color: #17a2b8; }
        .payment-status-banner i { font-size: 24px; }
        .payment-status-banner.pending i { color: #856404; }
        .payment-status-banner.assigned i { color: #0c5460; }

        /* Order Monitor */
        .order-monitor {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .monitor-title { font-size: 18px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .order-card {
            background: #f8f9fa;
            border-left: 4px solid #f5c542;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .order-id { font-size: 14px; font-weight: 600; color: #666; }
        .order-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-NEW { background: #fff3cd; color: #856404; }
        .status-PREPARING { background: #cce5ff; color: #004085; }
        .status-READY { background: #d4edda; color: #155724; }
        .status-READY_TO_DELIVER { background: #d4edda; color: #155724; }
        .status-DELIVERING { background: #d1ecf1; color: #0c5460; }
        .status-DELIVERED { background: #e2e3e5; color: #383d41; }
        .order-items { font-size: 14px; color: #666; margin-bottom: 5px; }
        .order-time { font-size: 12px; color: #999; }
        .empty-orders { text-align: center; padding: 40px; color: #999; }
        .empty-orders i { font-size: 48px; margin-bottom: 15px; }

        /* Products Section */
        .products-section {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .section-title { font-size: 18px; font-weight: 700; margin-bottom: 20px; }
        .category-title {
            font-size: 16px;
            font-weight: 600;
            color: #f39c12;
            margin: 25px 0 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
        }
        .product-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            transition: all 0.2s;
        }
        .product-card:hover { border-color: #f5c542; box-shadow: 0 4px 12px rgba(245,197,66,0.2); }
        .product-name { font-size: 14px; font-weight: 600; margin-bottom: 8px; min-height: 40px; display: flex; align-items: center; justify-content: center; }
        .product-price { font-size: 20px; font-weight: 700; color: #f39c12; margin-bottom: 8px; }
        .product-stock { font-size: 12px; color: #999; margin-bottom: 12px; }
        .quantity-control { display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 12px; }
        .qty-btn {
            width: 32px;
            height: 32px;
            border: none;
            background: #f5c542;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 700;
        }
        .qty-btn:hover { background: #f2a20a; }
        .qty-display { width: 45px; height: 32px; border: 2px solid #f5c542; border-radius: 6px; text-align: center; font-size: 16px; font-weight: 700; display: flex; align-items: center; justify-content: center; }
        .add-btn {
            width: 100%;
            padding: 10px;
            background: #f5c542;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .add-btn:hover { background: #f2a20a; }
        .add-btn:disabled { background: #ddd; cursor: not-allowed; }

        /* Cart Section */
        .cart-section {
            background: #27ae60;
            padding: 20px 25px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: sticky;
            bottom: 25px;
            margin-bottom: 25px;
        }
        .cart-info { color: white; }
        .cart-label { font-size: 14px; opacity: 0.9; margin-bottom: 5px; }
        .cart-total { font-size: 28px; font-weight: 700; }
        .cart-items { font-size: 12px; opacity: 0.9; }
        .place-order-btn {
            padding: 15px 40px;
            background: white;
            border: none;
            border-radius: 10px;
            color: #27ae60;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .place-order-btn:hover { transform: scale(1.05); box-shadow: 0 6px 15px rgba(0,0,0,0.2); }
        .place-order-btn:disabled { background: #ccc; color: #666; cursor: not-allowed; transform: none; }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow: hidden;
        }
        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-title { font-size: 20px; font-weight: 700; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #999; }
        .modal-close:hover { color: #333; }
        .modal-body { padding: 25px; max-height: 60vh; overflow-y: auto; }
        .modal-actions { display: flex; gap: 12px; padding: 20px 25px; border-top: 1px solid #e9ecef; background: #f9f9f9; }
        .modal-btn { flex: 1; padding: 15px; border: none; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer; }
        .btn-cancel { background: #e0e0e0; color: #333; }
        .btn-confirm { background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%); color: white; }
        .btn-confirm:hover { transform: scale(1.02); }

        /* Extend Options */
        .extend-options { display: grid; gap: 12px; }
        .extend-option {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .extend-option:hover { border-color: #f5c542; }
        .extend-option.selected { border-color: #f5c542; background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%); color: white; }
        .extend-time { font-size: 18px; font-weight: 700; }
        .extend-price { font-size: 22px; font-weight: 700; }
        .extend-subtext { font-size: 12px; opacity: 0.7; }

        /* Order Summary */
        .order-summary { margin-bottom: 20px; }
        .order-item-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #e9ecef; }
        .order-item-row:last-child { border-bottom: none; }
        .order-total-box {
            background: #fff9e6;
            border: 2px solid #f5c542;
            border-radius: 12px;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }
        .order-total-value { font-size: 28px; font-weight: 700; color: #f39c12; }

        /* Bill Modal */
        .bill-section { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #e9ecef; }
        .bill-section:last-child { border-bottom: none; }
        .bill-section-title { font-size: 16px; font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .bill-row { display: flex; justify-content: space-between; padding: 10px 0; font-size: 14px; }
        .bill-label { color: #666; }
        .bill-value { font-weight: 700; }
        .bill-total { font-size: 24px; font-weight: 700; color: #f39c12; }
        .payment-status { text-align: center; padding: 15px; border-radius: 10px; margin-bottom: 20px; font-weight: 700; }
        .payment-status.paid { background: #d4edda; color: #155724; }
        .payment-status.unpaid { background: #fff3cd; color: #856404; }

        /* Notification */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.3);
            z-index: 2000;
            display: none;
            max-width: 400px;
        }
        .notification.active { display: block; animation: slideIn 0.3s ease-out; }
        @keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .notification.warning { border-left: 4px solid #e74c3c; }

        @media (max-width: 768px) {
            header { padding: 10px 15px; }
            .header-title { font-size: 16px; }
            main { padding: 15px; }
            .products-grid { grid-template-columns: repeat(2, 1fr); }
            .cart-section { flex-direction: column; gap: 15px; text-align: center; }
        }

        /* Payment method selection */
        .payment-method-options { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .payment-method-option {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px 15px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s;
        }
        .payment-method-option:hover { border-color: #f5c542; }
        .payment-method-option.selected { border-color: #f5c542; background: linear-gradient(135deg,#f5c542,#f2a20a); color: white; }
        .payment-method-option i { font-size: 28px; margin-bottom: 8px; display: block; }
        .payment-method-option .pm-label { font-size: 15px; font-weight: 700; }
        .gcash-fields { margin-top: 15px; display: none; }
        .gcash-fields.visible { display: block; }
        .form-field { margin-bottom: 12px; }
        .form-field label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; }
        .form-field input { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; }
        .form-field input:focus { outline: none; border-color: #f5c542; }
        .cash-note { background: #fff9e6; border: 2px solid #f5c542; border-radius: 10px; padding: 15px; margin-top: 10px; font-size: 14px; color: #856404; display: none; }
        .cash-note.visible { display: block; }

        /* My Transactions */
        .transactions-section {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .tx-card {
            background: #f8f9fa;
            border-left: 4px solid #f5c542;
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 10px;
        }
        .tx-card:last-child { margin-bottom: 0; }
        .tx-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .tx-type { font-size: 14px; font-weight: 600; }
        .tx-badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .tx-badge.pending-verification { background: #fff3cd; color: #856404; }
        .tx-badge.pending-collection  { background: #fde3cc; color: #7d3c00; }
        .tx-badge.approved             { background: #d4edda; color: #155724; }
        .tx-badge.rejected             { background: #f8d7da; color: #721c24; }
        .tx-badge.paid                 { background: #d4edda; color: #155724; }
        .tx-badge.completed            { background: #d4edda; color: #155724; }
        .tx-detail { font-size: 13px; color: #555; }
    </style>
</head>
<body>
    <header>
        <div class="header-left">
            <img src="../assets/images/KTVL.png" alt="logo" onerror="this.style.display='none'">
            <div>
                <div class="header-title">Wannabees Family KTV</div>
                <div class="header-subtitle">Room <?= $room_number ?> - Welcome!</div>
            </div>
        </div>
        <div class="header-nav">
            <button class="nav-btn guide" onclick="location.href='guide_room.php'">
                <i class="fas fa-book"></i> Guide
            </button>
            <button class="nav-btn" onclick="logout()">
                <i class="fas fa-sign-out-alt"></i> Exit
            </button>
        </div>
    </header>

    <main>
        <!-- Room Info Card -->
        <div class="room-info-card">
            <div class="room-title"><i class="fas fa-door-open"></i> Room <?= $room_number ?></div>
            <div class="room-type"><?= htmlspecialchars($rental['type_name']) ?></div>

            <div class="info-grid">
                <div class="info-box" id="timeBox">
                    <div class="info-label"><i class="fas fa-clock"></i> Time Remaining</div>
                    <div class="info-value" id="timeRemaining">--:--:--</div>
                    <button class="extend-btn" onclick="openExtendModal()">
                        <i class="fas fa-plus"></i> Extend Time
                    </button>
                </div>

                <div class="info-box bill">
                    <div class="info-label"><i class="fas fa-receipt"></i> Current Bill</div>
                    <div class="info-value" id="currentBill">P<?= number_format($rental['grand_total'] ?? 0, 2) ?></div>
                    <div class="info-subtext"><?= count($customerOrders) ?> order(s)</div>
                    <button class="extend-btn" onclick="openBillModal()" style="background: rgba(255,255,255,0.2);">
                        <i class="fas fa-file-invoice-dollar"></i> View Bill
                    </button>
                </div>
            </div>
        </div>

        <!-- Order Monitor -->
        <div class="order-monitor">
            <div class="monitor-title"><i class="fas fa-list-check"></i> Your Orders</div>
            <div id="orderTrack">
                <?php if (count($customerOrders) > 0): ?>
                    <?php foreach ($customerOrders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <span class="order-id">Order #<?= $order['order_id'] ?></span>
                            <span class="order-status status-<?= $order['status'] ?>"><?php
                                $statusLabels = ['NEW'=>'Order Received','PREPARING'=>'Preparing your order...','READY'=>'Ready for pickup','READY_TO_DELIVER'=>'Ready for pickup','DELIVERING'=>'Food is on the way!','DELIVERED'=>'Delivered'];
                                echo $statusLabels[$order['status']] ?? $order['status'];
                            ?></span>
                        </div>
                        <div class="order-items"><?= htmlspecialchars($order['items']) ?></div>
                        <div class="order-time">
                            <i class="fas fa-clock"></i> <?= date('g:i A', strtotime($order['ordered_at'])) ?>
                            • Total: P<?= number_format($order['total'], 2) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-orders">
                        <i class="fas fa-utensils"></i>
                        <p>No orders yet. Browse the menu below!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- My Transactions Section -->
        <?php if (!empty($roomTransactions)): ?>
        <div class="transactions-section" id="txSection">
            <div class="monitor-title"><i class="fas fa-receipt"></i> My Transactions</div>
            <div id="txList">
                <?php
                $txStatusLabels = [
                    'PENDING_CASHIER_VERIFICATION' => ['label' => 'Pending Verification', 'class' => 'pending-verification'],
                    'PENDING_CASH_COLLECTION'      => ['label' => 'Pending Collection',   'class' => 'pending-collection'],
                    'APPROVED'                     => ['label' => 'Approved',              'class' => 'approved'],
                    'REJECTED'                     => ['label' => 'Rejected',              'class' => 'rejected'],
                    'PAID'                         => ['label' => 'Paid',                  'class' => 'paid'],
                    'COMPLETED'                    => ['label' => 'Completed',             'class' => 'completed'],
                ];
                foreach ($roomTransactions as $tx):
                    $sl = $txStatusLabels[$tx['status']] ?? ['label' => $tx['status'], 'class' => ''];
                ?>
                <div class="tx-card">
                    <div class="tx-top">
                        <span class="tx-type">
                            <i class="fas <?= $tx['transaction_type'] === 'ORDER' ? 'fa-utensils' : 'fa-clock' ?>"></i>
                            <?= $tx['transaction_type'] === 'ORDER' ? 'Order Payment' : 'Time Extension' ?>
                        </span>
                        <span class="tx-badge <?= $sl['class'] ?>"><?= $sl['label'] ?></span>
                    </div>
                    <div class="tx-detail">
                        ₱<?= number_format($tx['amount'], 2) ?> via <?= htmlspecialchars($tx['payment_method']) ?>
                        <?php if ($tx['payment_method'] === 'GCASH' && $tx['gcash_reference_number']): ?>
                            &nbsp;• Ref: <?= htmlspecialchars($tx['gcash_reference_number']) ?>
                        <?php endif; ?>
                        &nbsp;• <?= date('g:i A', strtotime($tx['created_at'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Products Section -->
        <div class="products-section">
            <div class="section-title"><i class="fas fa-shopping-basket"></i> Order Menu</div>

            <?php foreach ($categories as $category => $items): ?>
            <div class="category-title">
                <i class="fas <?= getCategoryIcon($category) ?>"></i> <?= htmlspecialchars($category) ?>
            </div>
            <div class="products-grid">
                <?php foreach ($items as $p): ?>
                <div class="product-card">
                    <div class="product-name"><?= htmlspecialchars($p['product_name']) ?></div>
                    <div class="product-price">P<?= number_format($p['price'], 2) ?></div>
                    <div class="product-stock"><?= $p['stock_quantity'] ?> available</div>
                    <div class="quantity-control">
                        <button class="qty-btn" onclick="changeQty(<?= $p['product_id'] ?>, -1)">-</button>
                        <div class="qty-display" id="qty_<?= $p['product_id'] ?>">0</div>
                        <button class="qty-btn" onclick="changeQty(<?= $p['product_id'] ?>, 1)" data-max="<?= $p['stock_quantity'] ?>">+</button>
                    </div>
                    <button class="add-btn" onclick="addToCart(<?= $p['product_id'] ?>, '<?= htmlspecialchars($p['product_name']) ?>', <?= $p['price'] ?>, <?= $p['stock_quantity'] ?>)">
                        Add to Order
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Cart Section -->
        <div class="cart-section">
            <div class="cart-info">
                <div class="cart-label">Order Total</div>
                <div class="cart-total">P<span id="cartTotal">0.00</span></div>
                <div class="cart-items"><span id="cartCount">0</span> item(s)</div>
            </div>
            <button class="place-order-btn" id="placeOrderBtn" onclick="openConfirmModal()" disabled>
                <i class="fas fa-paper-plane"></i> Place Order
            </button>
        </div>
    </main>

    <!-- Extend Time Modal -->
    <div id="extendModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title"><i class="fas fa-clock"></i> Extend Your Time</div>
                <button class="modal-close" onclick="closeExtendModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="extend-options" id="extendOptions"></div>

                <!-- Payment Method for Extension -->
                <div style="margin-top:20px;" id="extendPaymentSection" style="display:none;">
                    <div style="font-size:14px;font-weight:700;margin-bottom:12px;"><i class="fas fa-wallet"></i> Select Payment Method</div>
                    <div class="payment-method-options">
                        <div class="payment-method-option" id="pmGcashExtend" onclick="selectExtendPayment('GCASH')">
                            <i class="fas fa-mobile-alt"></i>
                            <span class="pm-label">GCash</span>
                        </div>
                        <div class="payment-method-option" id="pmCashExtend" onclick="selectExtendPayment('CASH')">
                            <i class="fas fa-money-bill-wave"></i>
                            <span class="pm-label">Cash</span>
                        </div>
                    </div>
                    <div class="gcash-fields" id="gcashFieldsExtend">
                        <div class="form-field">
                            <label><i class="fas fa-user"></i> GCash Account Name</label>
                            <input type="text" id="extendGcashName" placeholder="e.g. Juan dela Cruz">
                        </div>
                        <div class="form-field">
                            <label><i class="fas fa-hashtag"></i> GCash Reference Number</label>
                            <input type="text" id="extendGcashRef" placeholder="e.g. 1234567890">
                        </div>
                    </div>
                    <div class="cash-note" id="cashNoteExtend">
                        <i class="fas fa-info-circle"></i> Our staff will come to your room to collect payment.
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="modal-btn btn-cancel" onclick="closeExtendModal()">Cancel</button>
                <button class="modal-btn btn-confirm" onclick="confirmExtend()" id="extendConfirmBtn" disabled>Extend</button>
            </div>
        </div>
    </div>

    <!-- Confirm Order Modal (with payment method) -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title"><i class="fas fa-check-circle"></i> Confirm Order</div>
                <button class="modal-close" onclick="closeConfirmModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="order-summary" id="orderSummary"></div>
                <div class="order-total-box">
                    <span>Total:</span>
                    <span class="order-total-value">₱<span id="orderTotalValue">0.00</span></span>
                </div>

                <!-- Payment Method -->
                <div style="margin-top:20px;">
                    <div style="font-size:14px;font-weight:700;margin-bottom:12px;"><i class="fas fa-wallet"></i> Select Payment Method</div>
                    <div class="payment-method-options">
                        <div class="payment-method-option" id="pmGcashOrder" onclick="selectOrderPayment('GCASH')">
                            <i class="fas fa-mobile-alt"></i>
                            <span class="pm-label">GCash</span>
                        </div>
                        <div class="payment-method-option" id="pmCashOrder" onclick="selectOrderPayment('CASH')">
                            <i class="fas fa-money-bill-wave"></i>
                            <span class="pm-label">Cash</span>
                        </div>
                    </div>
                    <div class="gcash-fields" id="gcashFieldsOrder">
                        <div class="form-field">
                            <label><i class="fas fa-user"></i> GCash Account Name</label>
                            <input type="text" id="orderGcashName" placeholder="e.g. Juan dela Cruz">
                        </div>
                        <div class="form-field">
                            <label><i class="fas fa-hashtag"></i> GCash Reference Number</label>
                            <input type="text" id="orderGcashRef" placeholder="e.g. 1234567890">
                        </div>
                    </div>
                    <div class="cash-note" id="cashNoteOrder">
                        <i class="fas fa-info-circle"></i> Our staff will come to your room to collect payment.
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="modal-btn btn-cancel" onclick="closeConfirmModal()">Cancel</button>
                <button class="modal-btn btn-confirm" onclick="confirmOrder()" id="orderConfirmBtn">Place Order</button>
            </div>
        </div>
    </div>

    <!-- Bill Modal -->
    <div id="billModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <div class="modal-title"><i class="fas fa-file-invoice-dollar"></i> Bill Summary</div>
                <button class="modal-close" onclick="closeBillModal()">&times;</button>
            </div>
            <div class="modal-body" id="billModalBody">
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 40px; color: #f5c542;"></i>
                    <p style="margin-top: 15px;">Loading bill...</p>
                </div>
            </div>
            <div class="modal-actions">
                <button class="modal-btn btn-cancel" onclick="closeBillModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Time Warning Notification -->
    <div id="timeWarning" class="notification warning">
        <h4 style="color: #e74c3c; margin-bottom: 10px;"><i class="fas fa-exclamation-triangle"></i> Time Running Out!</h4>
        <p>Your rental time is almost up. Would you like to extend?</p>
        <div style="margin-top: 15px; display: flex; gap: 10px;">
            <button class="modal-btn btn-cancel" onclick="closeNotification()" style="flex: 1;">Not Now</button>
            <button class="modal-btn btn-confirm" onclick="closeNotification(); openExtendModal();" style="flex: 1;">Extend Time</button>
        </div>
    </div>

    <script>
        const rentalId = <?= $rental_id ?>;
        const roomId = <?= $room_id ?>;
        let totalMinutes = <?= $rental['total_minutes'] ?>;
        let startedAt = "<?= $rental['started_at'] ?>";
        let warningShown = false;
        let cart = {};

        // Timer
        function updateTimer() {
            const start = new Date(startedAt.replace(' ', 'T'));
            const end = new Date(start.getTime() + totalMinutes * 60000);
            const now = new Date();
            const remaining = Math.max(0, Math.floor((end - now) / 1000));

            const hours = Math.floor(remaining / 3600);
            const mins = Math.floor((remaining % 3600) / 60);
            const secs = remaining % 60;

            document.getElementById('timeRemaining').textContent = 
                `${String(hours).padStart(2,'0')}:${String(mins).padStart(2,'0')}:${String(secs).padStart(2,'0')}`;

            const timeBox = document.getElementById('timeBox');
            if (remaining <= 300 && remaining > 0) {
                timeBox.classList.add('warning');
                if (!warningShown) {
                    document.getElementById('timeWarning').classList.add('active');
                    warningShown = true;
                }
            } else {
                timeBox.classList.remove('warning');
            }

            if (remaining === 0) {
                alert('Your rental time has expired!');
                location.reload();
            }
        }
        updateTimer();
        setInterval(updateTimer, 1000);

        // Cart functions
        function changeQty(productId, delta) {
            const display = document.getElementById('qty_' + productId);
            let qty = parseInt(display.textContent) + delta;
            const max = parseInt(display.nextElementSibling.dataset.max);
            if (qty < 0) qty = 0;
            if (qty > max) qty = max;
            display.textContent = qty;
        }

        function addToCart(productId, name, price, maxStock) {
            const qty = parseInt(document.getElementById('qty_' + productId).textContent);
            if (qty <= 0) return;

            if (!cart[productId]) cart[productId] = { name, price, quantity: 0 };
            cart[productId].quantity += qty;

            if (cart[productId].quantity > maxStock) {
                cart[productId].quantity = maxStock;
                alert(`Maximum ${maxStock} available for ${name}`);
            }

            document.getElementById('qty_' + productId).textContent = '0';
            updateCartDisplay();
        }

        function updateCartDisplay() {
            let total = 0, count = 0;
            for (const id in cart) {
                total += cart[id].price * cart[id].quantity;
                count += cart[id].quantity;
            }
            document.getElementById('cartTotal').textContent = total.toFixed(2);
            document.getElementById('cartCount').textContent = count;
            document.getElementById('placeOrderBtn').disabled = count === 0;
        }

        // Extend Modal
        let selectedExtend = null;
        let selectedExtendPaymentMethod = null;
        function openExtendModal() {
            const price30 = <?= floatval($rental['price_per_30min']) ?>;
            const options = [
                { minutes: 30, label: '30 Minutes', price: price30 },
                { minutes: 60, label: '1 Hour', price: price30 * 2 },
                { minutes: 120, label: '2 Hours', price: price30 * 4 },
                { minutes: 180, label: '3 Hours', price: price30 * 6 }
            ];

            const container = document.getElementById('extendOptions');
            container.innerHTML = options.map(opt => `
                <div class="extend-option" data-minutes="${opt.minutes}" data-price="${opt.price}" onclick="selectExtend(this)">
                    <div>
                        <div class="extend-time">${opt.label}</div>
                        <div class="extend-subtext">Extend your session</div>
                    </div>
                    <div class="extend-price">₱${opt.price.toFixed(2)}</div>
                </div>
            `).join('');

            selectedExtendPaymentMethod = null;
            document.getElementById('pmGcashExtend').classList.remove('selected');
            document.getElementById('pmCashExtend').classList.remove('selected');
            document.getElementById('gcashFieldsExtend').classList.remove('visible');
            document.getElementById('cashNoteExtend').classList.remove('visible');
            document.getElementById('extendGcashName').value = '';
            document.getElementById('extendGcashRef').value = '';
            document.getElementById('extendConfirmBtn').disabled = true;
            document.getElementById('extendModal').classList.add('active');
        }

        function closeExtendModal() {
            document.getElementById('extendModal').classList.remove('active');
            document.querySelectorAll('.extend-option').forEach(o => o.classList.remove('selected'));
            selectedExtend = null;
            selectedExtendPaymentMethod = null;
            document.getElementById('extendConfirmBtn').disabled = true;
        }

        function selectExtend(el) {
            document.querySelectorAll('.extend-option').forEach(o => o.classList.remove('selected'));
            el.classList.add('selected');
            selectedExtend = { minutes: el.dataset.minutes, price: el.dataset.price };
            updateExtendConfirmBtn();
        }

        function selectExtendPayment(method) {
            selectedExtendPaymentMethod = method;
            document.getElementById('pmGcashExtend').classList.toggle('selected', method === 'GCASH');
            document.getElementById('pmCashExtend').classList.toggle('selected', method === 'CASH');
            document.getElementById('gcashFieldsExtend').classList.toggle('visible', method === 'GCASH');
            document.getElementById('cashNoteExtend').classList.toggle('visible', method === 'CASH');
            updateExtendConfirmBtn();
        }

        function updateExtendConfirmBtn() {
            document.getElementById('extendConfirmBtn').disabled = !(selectedExtend && selectedExtendPaymentMethod);
        }

        async function confirmExtend() {
            if (!selectedExtend || !selectedExtendPaymentMethod) return;
            const btn = document.getElementById('extendConfirmBtn');

            if (selectedExtendPaymentMethod === 'GCASH') {
                const name = document.getElementById('extendGcashName').value.trim();
                const ref  = document.getElementById('extendGcashRef').value.trim();
                if (!name || !ref) {
                    alert('Please enter your GCash account name and reference number.');
                    return;
                }
            }

            btn.textContent = 'Processing...';
            btn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('minutes', selectedExtend.minutes);
                formData.append('payment_method', selectedExtendPaymentMethod);
                if (selectedExtendPaymentMethod === 'GCASH') {
                    formData.append('gcash_account_name', document.getElementById('extendGcashName').value.trim());
                    formData.append('gcash_reference_number', document.getElementById('extendGcashRef').value.trim());
                }

                const res = await fetch('../api/customer/submit_extension_payment.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    const msg = selectedExtendPaymentMethod === 'GCASH'
                        ? 'Extension submitted! Awaiting cashier verification.'
                        : 'Extension submitted! Staff will collect payment.';
                    alert(msg);
                    closeExtendModal();
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                    btn.textContent = 'Extend';
                    btn.disabled = false;
                }
            } catch (err) {
                alert('Network error: ' + err.message);
                btn.textContent = 'Extend';
                btn.disabled = false;
            }
        }

        // Confirm Order Modal
        let selectedOrderPaymentMethod = null;
        function openConfirmModal() {
            const summary = document.getElementById('orderSummary');
            let html = '', total = 0;

            for (const id in cart) {
                const item = cart[id];
                const lineTotal = item.price * item.quantity;
                total += lineTotal;
                html += `<div class="order-item-row"><span>${item.name} x${item.quantity}</span><span>₱${lineTotal.toFixed(2)}</span></div>`;
            }

            summary.innerHTML = html;
            document.getElementById('orderTotalValue').textContent = total.toFixed(2);
            selectedOrderPaymentMethod = null;
            document.getElementById('pmGcashOrder').classList.remove('selected');
            document.getElementById('pmCashOrder').classList.remove('selected');
            document.getElementById('gcashFieldsOrder').classList.remove('visible');
            document.getElementById('cashNoteOrder').classList.remove('visible');
            document.getElementById('orderGcashName').value = '';
            document.getElementById('orderGcashRef').value = '';
            document.getElementById('confirmModal').classList.add('active');
        }

        function selectOrderPayment(method) {
            selectedOrderPaymentMethod = method;
            document.getElementById('pmGcashOrder').classList.toggle('selected', method === 'GCASH');
            document.getElementById('pmCashOrder').classList.toggle('selected', method === 'CASH');
            document.getElementById('gcashFieldsOrder').classList.toggle('visible', method === 'GCASH');
            document.getElementById('cashNoteOrder').classList.toggle('visible', method === 'CASH');
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('active');
            selectedOrderPaymentMethod = null;
        }

        async function confirmOrder() {
            if (!selectedOrderPaymentMethod) {
                alert('Please select a payment method.');
                return;
            }

            if (selectedOrderPaymentMethod === 'GCASH') {
                const name = document.getElementById('orderGcashName').value.trim();
                const ref  = document.getElementById('orderGcashRef').value.trim();
                if (!name || !ref) {
                    alert('Please enter your GCash account name and reference number.');
                    return;
                }
            }

            const btn = document.getElementById('orderConfirmBtn');
            btn.textContent = 'Placing...';
            btn.disabled = true;

            const items = [];
            for (const id in cart) {
                items.push({ product_id: parseInt(id), quantity: cart[id].quantity });
            }

            try {
                const formData = new FormData();
                formData.append('room_id', roomId);
                formData.append('items', JSON.stringify(items));
                formData.append('payment_method', selectedOrderPaymentMethod);
                if (selectedOrderPaymentMethod === 'GCASH') {
                    formData.append('gcash_account_name', document.getElementById('orderGcashName').value.trim());
                    formData.append('gcash_reference_number', document.getElementById('orderGcashRef').value.trim());
                }

                const res = await fetch('../api/customer/submit_order_payment.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    const msg = selectedOrderPaymentMethod === 'GCASH'
                        ? 'Order placed! GCash payment pending cashier verification.'
                        : 'Order placed! Staff will collect cash payment.';
                    alert(msg);
                    cart = {};
                    updateCartDisplay();
                    closeConfirmModal();
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                    btn.textContent = 'Place Order';
                    btn.disabled = false;
                }
            } catch (err) {
                alert('Network error: ' + err.message);
                btn.textContent = 'Place Order';
                btn.disabled = false;
            }
        }

        // Bill Modal
        async function openBillModal() {
            document.getElementById('billModal').classList.add('active');
            const body = document.getElementById('billModalBody');
            body.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 40px; color: #f5c542;"></i><p>Loading...</p></div>';

            try {
                const res = await fetch(`../api/billing/get_bill.php?rental_id=${rentalId}`);
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Response text:', text);
                    throw new Error('Invalid JSON response from server');
                }

                if (data.success) {
                    const bill = data.bill;
                    let html = '';

                    if (bill.is_paid == 1) {
                        html += '<div class="payment-status paid"><i class="fas fa-check-circle"></i> This bill has been paid</div>';
                    } else {
                        html += '<div class="payment-status unpaid"><i class="fas fa-clock"></i> Payment Pending</div>';
                    }

                    html += `
                        <div class="bill-section">
                            <div class="bill-section-title"><i class="fas fa-door-open"></i> Room Charges</div>
                            <div class="bill-row"><span class="bill-label">Duration</span><span class="bill-value">${data.rental.total_minutes} minutes</span></div>
                            <div class="bill-row"><span class="bill-label">Room Cost</span><span class="bill-value">P${parseFloat(bill.total_room_cost).toFixed(2)}</span></div>
                        </div>
                    `;

                    if (data.orders && data.orders.length > 0) {
                        html += '<div class="bill-section"><div class="bill-section-title"><i class="fas fa-utensils"></i> Orders</div>';
                        data.orders.forEach(order => {
                            html += `<div class="bill-row"><span class="bill-label">${order.product_name} x${order.quantity}</span><span class="bill-value">P${(order.price * order.quantity).toFixed(2)}</span></div>`;
                        });
                        html += `<div class="bill-row" style="margin-top: 10px;"><span class="bill-label">Orders Subtotal</span><span class="bill-value">P${parseFloat(bill.total_orders_cost).toFixed(2)}</span></div></div>`;
                    }

                    html += `
                        <div class="bill-section">
                            <div class="bill-row" style="font-size: 22px;">
                                <span><i class="fas fa-receipt"></i> GRAND TOTAL</span>
                                <span class="bill-total">P${parseFloat(bill.grand_total).toFixed(2)}</span>
                            </div>
                        </div>
                    `;

                    body.innerHTML = html;
                } else {
                    body.innerHTML = '<div style="text-align: center; color: #e74c3c;"><i class="fas fa-exclamation-circle" style="font-size: 40px;"></i><p>Error loading bill</p><p style="font-size: 14px; color: #666;">' + (data.error || 'Unknown error') + '</p></div>';
                }
            } catch (err) {
                console.error('Bill loading error:', err);
                body.innerHTML = '<div style="text-align: center; color: #e74c3c;"><i class="fas fa-exclamation-circle" style="font-size: 40px;"></i><p>Error loading bill</p><p style="font-size: 14px; color: #666;">' + err.message + '</p></div>';
            }
        }

        function closeBillModal() {
            document.getElementById('billModal').classList.remove('active');
        }

        // Request Payment removed - customers should go to cashier desk directly

        function closeNotification() {
            document.getElementById('timeWarning').classList.remove('active');
        }

        function logout() {
            if (confirm('Exit room dashboard?')) {
                location.href = '../auth/logout.php';
            }
        }

        // Auto refresh
        setInterval(() => location.reload(), 30000);
    </script>
</body>
</html>
<?php
function getCategoryIcon($category) {
    $icons = [
        'Beverages' => 'fa-glass-whiskey',
        'Snacks' => 'fa-cookie-bite',
        'Noodles' => 'fa-bowl-food',
        'Other' => 'fa-box'
    ];
    return $icons[$category] ?? 'fa-utensils';
}
?>