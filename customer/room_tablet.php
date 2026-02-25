<?php
// room_tablet.php - Updated to use permanent room account with guide button
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Get room from user's permanent assignment
$room_id = isset($_SESSION['room_id']) ? intval($_SESSION['room_id']) : 0;

if ($room_id <= 0) {
    die("Error: This account is not assigned to a room. Please contact the administrator.");
}

// Fetch room details
$roomSql = "
SELECT r.room_id, r.room_number, r.status, rt.type_name, rt.price_per_hour, rt.price_per_30min
FROM rooms r
JOIN room_types rt ON r.room_type_id = rt.room_type_id
WHERE r.room_id = ? LIMIT 1";
$stmt = $mysqli->prepare($roomSql);
$stmt->bind_param('i', $room_id);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$room) {
    die("Room not found");
}

// Fetch active rental
$rentalSql = "
SELECT rental_id, started_at, ended_at, total_minutes, is_active
FROM rentals
WHERE room_id = ? AND ended_at IS NULL
ORDER BY started_at DESC LIMIT 1";
$stmt = $mysqli->prepare($rentalSql);
$stmt->bind_param('i', $room_id);
$stmt->execute();
$rental = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch products
$productsSql = "SELECT product_id, product_name, price, stock_quantity, is_active FROM products WHERE is_active = 1 ORDER BY product_name ASC";
$productsRes = $mysqli->query($productsSql);
$products = [];
$drinks = [];
$snacks = [];
while ($p = $productsRes->fetch_assoc()) {
    $products[] = $p;
    // Simple categorization based on common naming
    $name = strtolower($p['product_name']);
    if (strpos($name, 'coke') !== false || strpos($name, 'sprite') !== false || 
        strpos($name, 'water') !== false || strpos($name, 'royal') !== false ||
        strpos($name, 'pepsi') !== false || strpos($name, 'gatorade') !== false) {
        $drinks[] = $p;
    } else {
        $snacks[] = $p;
    }
}

// Fetch bill if rental exists
$billData = null;
if ($rental) {
    $billSql = "SELECT bill_id, total_room_cost, total_orders_cost, grand_total, is_paid FROM bills WHERE rental_id = ? ORDER BY created_at DESC LIMIT 1";
    $stmt = $mysqli->prepare($billSql);
    $rentalId = intval($rental['rental_id']);
    $stmt->bind_param('i', $rentalId);
    $stmt->execute();
    $billData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch customer's orders for this rental
$customerOrders = [];
if ($rental) {
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
    $stmt->bind_param('i', $rentalId);
    $stmt->execute();
    $ordersRes = $stmt->get_result();
    while ($order = $ordersRes->fetch_assoc()) {
        $customerOrders[] = $order;
    }
    $stmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Room <?= $room['room_number'] ?> - Wannabees KTV</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: linear-gradient(180deg, #f5c542 0%, #f2a20a 100%);
      min-height: 100vh;
      color: #2c2c2c;
    }
    
    /* Header */
    header {
      background: white;
      padding: 20px 30px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .header-left {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .header-left img {
      height: 50px;
    }
    
    .header-title {
      font-size: 20px;
      font-weight: 700;
    }
    
    .header-subtitle {
      font-size: 14px;
      color: #666;
    }
    
    .header-nav {
      display: flex;
      gap: 10px;
    }
    
    .nav-btn {
      width: 50px;
      height: 50px;
      border: none;
      background: #f5f5f5;
      border-radius: 10px;
      cursor: pointer;
      font-size: 24px;
      color: #666;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s;
    }
    
    .nav-btn:hover {
      background: #e8e8e8;
      transform: scale(1.05);
    }
    
    .nav-btn.guide-btn {
      background: #3498db;
      color: white;
    }
    
    .nav-btn.guide-btn:hover {
      background: #2980b9;
    }
    
    /* Main Content */
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
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .room-title {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 5px;
    }
    
    .room-type {
      font-size: 14px;
      color: #666;
      margin-bottom: 20px;
    }
    
    .info-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 15px;
    }
    
    .info-box {
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      padding: 20px;
      border-radius: 12px;
      color: white;
      text-align: center;
    }
    
    .info-label {
      font-size: 13px;
      opacity: 0.9;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    
    .info-value {
      font-size: 28px;
      font-weight: 700;
      font-family: 'Courier New', monospace;
      letter-spacing: 1px;
    }
    
    .info-subtext {
      font-size: 12px;
      opacity: 0.8;
      margin-top: 5px;
    }
    
    .extend-btn {
      background: rgba(255,255,255,0.2);
      border: none;
      padding: 8px 15px;
      border-radius: 6px;
      color: white;
      font-size: 13px;
      cursor: pointer;
      margin-top: 10px;
      font-weight: 600;
      transition: all 0.3s;
    }
    
    .extend-btn:hover {
      background: rgba(255,255,255,0.3);
    }
    
    /* Order Menu */
    .order-section {
      background: white;
      border-radius: 16px;
      padding: 25px;
      margin-bottom: 25px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .section-title {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 20px;
    }
    
    .category-title {
      font-size: 16px;
      font-weight: 600;
      color: #f39c12;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .products-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 30px;
    }
    
    .product-card {
      background: white;
      border: 2px solid #f5c542;
      border-radius: 12px;
      padding: 20px;
      text-align: center;
      transition: all 0.3s;
    }
    
    .product-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 15px rgba(245,197,66,0.3);
    }
    
    .product-name {
      font-size: 15px;
      font-weight: 600;
      margin-bottom: 10px;
      min-height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .product-price {
      font-size: 22px;
      font-weight: 700;
      color: #f39c12;
      margin-bottom: 10px;
    }
    
    .product-stock {
      font-size: 12px;
      color: #999;
      margin-bottom: 15px;
    }
    
    .product-stock.out {
      color: #e74c3c;
      font-weight: 600;
    }
    
    .quantity-control {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-bottom: 12px;
    }
    
    .qty-btn {
      width: 35px;
      height: 35px;
      border: none;
      background: #f5c542;
      color: white;
      border-radius: 6px;
      cursor: pointer;
      font-size: 18px;
      font-weight: 700;
      transition: all 0.3s;
    }
    
    .qty-btn:hover {
      background: #f2a20a;
      transform: scale(1.1);
    }
    
    .qty-btn:disabled {
      background: #ddd;
      cursor: not-allowed;
      transform: none;
    }
    
    .qty-display {
      width: 50px;
      height: 35px;
      border: 2px solid #f5c542;
      border-radius: 6px;
      text-align: center;
      font-size: 18px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .add-btn {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      border: none;
      border-radius: 8px;
      color: white;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .add-btn:hover {
      transform: scale(1.05);
      box-shadow: 0 4px 12px rgba(245,197,66,0.4);
    }
    
    .add-btn:disabled {
      background: #ddd;
      cursor: not-allowed;
      transform: none;
    }
    
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
    }
    
    .cart-info {
      color: white;
    }
    
    .cart-label {
      font-size: 14px;
      opacity: 0.9;
      margin-bottom: 5px;
    }
    
    .cart-total {
      font-size: 28px;
      font-weight: 700;
    }
    
    .cart-items {
      font-size: 12px;
      opacity: 0.9;
    }
    
    .place-order-btn {
      padding: 15px 40px;
      background: white;
      border: none;
      border-radius: 10px;
      color: #27ae60;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    
    .place-order-btn:hover {
      transform: scale(1.05);
      box-shadow: 0 6px 15px rgba(0,0,0,0.2);
    }
    
    .place-order-btn:disabled {
      background: #ccc;
      color: #666;
      cursor: not-allowed;
      transform: none;
    }
    
    /* Modal */
    .modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.6);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }
    
    .modal.active {
      display: flex;
    }
    
    .modal-content {
      background: white;
      border-radius: 16px;
      padding: 0;
      width: 90%;
      max-width: 500px;
      max-height: 90vh;
      overflow: hidden;
    }
    
    .modal-header {
      padding: 25px;
      border-bottom: 1px solid #f0f0f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .modal-title {
      font-size: 22px;
      font-weight: 700;
    }
    
    .modal-close {
      background: none;
      border: none;
      font-size: 28px;
      cursor: pointer;
      color: #999;
      width: 35px;
      height: 35px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: all 0.3s;
    }
    
    .modal-close:hover {
      background: #f5f5f5;
      color: #333;
    }
    
    .modal-body {
      padding: 25px;
      max-height: 60vh;
      overflow-y: auto;
    }
    
    /* Extend Time Modal */
    .extend-options {
      display: grid;
      gap: 12px;
    }
    
    .extend-option {
      background: white;
      border: 2px solid #e0e0e0;
      border-radius: 12px;
      padding: 20px;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .extend-option:hover {
      border-color: #f5c542;
    }
    
    .extend-option.selected {
      border-color: #f5c542;
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      color: white;
    }
    
    .extend-time {
      font-size: 18px;
      font-weight: 700;
    }
    
    .extend-price {
      font-size: 20px;
      font-weight: 700;
    }
    
    .extend-subtext {
      font-size: 12px;
      opacity: 0.8;
    }
    
    /* Confirm Order Modal */
    .order-summary {
      margin-bottom: 25px;
    }
    
    .order-item {
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
      border-bottom: 1px solid #f0f0f0;
      font-size: 15px;
    }
    
    .order-item:last-child {
      border-bottom: none;
    }
    
    .order-item-name {
      font-weight: 600;
    }
    
    .order-item-price {
      color: #f39c12;
      font-weight: 700;
    }
    
    .order-total {
      background: #fff9e6;
      border: 2px solid #f5c542;
      border-radius: 12px;
      padding: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 20px;
    }
    
    .order-total-label {
      font-size: 18px;
      font-weight: 600;
    }
    
    .order-total-value {
      font-size: 32px;
      font-weight: 700;
      color: #f39c12;
    }
    
    /* Modal Actions */
    .modal-actions {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      padding: 20px 25px;
      border-top: 1px solid #f0f0f0;
      background: #f9f9f9;
    }
    
    .modal-btn {
      padding: 15px;
      border: none;
      border-radius: 10px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .btn-cancel {
      background: #e0e0e0;
      color: #2c2c2c;
    }
    
    .btn-confirm {
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      color: white;
    }
    
    .btn-confirm:hover {
      transform: scale(1.05);
      box-shadow: 0 4px 12px rgba(245,197,66,0.4);
    }
    
    .no-rental-message {
      text-align: center;
      padding: 60px 20px;
      color: white;
    }
    
    .no-rental-message i {
      font-size: 64px;
      margin-bottom: 20px;
      opacity: 0.8;
    }
    
    .no-rental-message h3 {
      font-size: 24px;
      margin-bottom: 10px;
    }

    .notification {
  position: fixed;
  top: 20px;
  right: 20px;
  background: white;
  padding: 20px 25px;
  border-radius: 12px;
  box-shadow: 0 8px 30px rgba(0,0,0,0.3);
  z-index: 2000;
  min-width: 350px;
  max-width: 500px;
  animation: slideInRight 0.4s ease-out;
  display: none;
}

.notification.active {
  display: block;
}

.notification.warning {
  border-left: 5px solid #e74c3c;
}

@keyframes slideInRight {
  from {
    transform: translateX(400px);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

.notification-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.notification-title {
  font-size: 18px;
  font-weight: 700;
  color: #e74c3c;
  display: flex;
  align-items: center;
  gap: 10px;
}

.notification-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #999;
}

.notification-body {
  font-size: 15px;
  color: #666;
  margin-bottom: 15px;
  line-height: 1.6;
}

.notification-actions {
  display: flex;
  gap: 10px;
}

.notif-btn {
  flex: 1;
  padding: 12px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s;
}

.notif-btn-secondary {
  background: #e8e8e8;
  color: #333;
}

.notif-btn-primary {
  background: #e74c3c;
  color: white;
}

.notif-btn:hover {
  transform: scale(1.05);
}

/* Warning state for time box */
.info-box.warning {
  background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.02); }
}

.order-monitor {
  background: white;
  border-radius: 16px;
  padding: 25px;
  margin-bottom: 25px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.monitor-title {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.order-track {
  margin-bottom: 20px;
}

.order-card {
  background: #f9f9f9;
  border-left: 4px solid #f5c542;
  padding: 15px;
  border-radius: 8px;
  margin-bottom: 10px;
}

.order-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.order-id {
  font-size: 14px;
  font-weight: 600;
  color: #666;
}

.order-status {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
}

.status-NEW {
  background: #fff3cd;
  color: #856404;
}

.status-PREPARING {
  background: #cce5ff;
  color: #004085;
}

.status-READY {
  background: #d4edda;
  color: #155724;
}

.status-READY_TO_DELIVER {
  background: #d4edda;
  color: #155724;
}

.status-DELIVERING {
  background: #d1ecf1;
  color: #0c5460;
}

.status-DELIVERED {
  background: #e2e3e5;
  color: #383d41;
}

.order-items {
  font-size: 14px;
  color: #666;
  margin-bottom: 8px;
}

.order-time {
  font-size: 12px;
  color: #999;
}

.empty-orders {
  text-align: center;
  padding: 30px;
  color: #999;
}

.empty-orders i {
  font-size: 40px;
  margin-bottom: 10px;
}

/* Bill Modal Styles */
.bill-section {
  margin-bottom: 25px;
  padding-bottom: 20px;
  border-bottom: 2px solid #f0f0f0;
}

.bill-section:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.bill-section-title {
  font-size: 18px;
  font-weight: 700;
  color: #2c2c2c;
  margin-bottom: 15px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.bill-section-title i {
  color: #f39c12;
}

.bill-row {
  display: flex;
  justify-content: space-between;
  padding: 12px 0;
  font-size: 15px;
  border-bottom: 1px dashed #e8e8e8;
}

.bill-row:last-child {
  border-bottom: none;
}

.bill-row.total {
  font-size: 24px;
  font-weight: 700;
  color: #2c2c2c;
  padding: 20px 0;
  border-top: 3px solid #f5c542;
  border-bottom: none;
  margin-top: 10px;
  background: linear-gradient(90deg, #fff9e6 0%, #ffffff 100%);
  padding: 20px;
  border-radius: 8px;
}

.bill-label {
  color: #666;
  font-weight: 500;
}

.bill-value {
  font-weight: 700;
  color: #2c2c2c;
}

.bill-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px;
  font-size: 14px;
  background: #f9f9f9;
  border-radius: 6px;
  margin-bottom: 8px;
}

.bill-item:last-child {
  margin-bottom: 0;
}

.bill-item-name {
  flex: 1;
  font-weight: 500;
  color: #333;
}

.bill-item-qty {
  margin: 0 20px;
  color: #999;
  font-weight: 600;
  min-width: 40px;
  text-align: center;
}

.bill-item-price {
  font-weight: 700;
  color: #f39c12;
  min-width: 80px;
  text-align: right;
}

.payment-status {
  text-align: center;
  padding: 20px;
  border-radius: 12px;
  margin-bottom: 25px;
  font-weight: 700;
  font-size: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.payment-status i {
  font-size: 24px;
}

.payment-status.paid {
  background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
  color: #155724;
  border: 2px solid #b1dfbb;
}

.payment-status.unpaid {
  background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
  color: #856404;
  border: 2px solid #ffd93d;
}

.modal-footer {
  display: flex;
  gap: 12px;
  padding: 20px;
  border-top: 2px solid #f0f0f0;
  background: #f9f9f9;
}

.modal-footer .modal-btn {
  flex: 1;
  padding: 15px;
  font-size: 16px;
  font-weight: 700;
  border-radius: 10px;
  transition: all 0.3s;
}

.modal-footer .btn-cancel {
  background: #e8e8e8;
  color: #666;
}

.modal-footer .btn-cancel:hover {
  background: #d4d4d4;
}

.modal-footer .btn-confirm {
  background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
  color: white;
  flex: 2;
}

.modal-footer .btn-confirm:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
}

.modal-footer .btn-confirm i {
  margin-right: 8px;
}
  </style>
</head>
<body>
  <header>
    <div class="header-left">
      <img src="../assets/images/KTVL.png" alt="logo">
      <div>
        <div class="header-title">Wannabees Family KTV</div>
        <div class="header-subtitle">Welcome, Customer</div>
      </div>
    </div>
    <div class="header-nav">
      <button class="nav-btn" onclick="location.href='room_tablet.php'" title="Home">
        <i class="fas fa-home"></i>
      </button>
      <button class="nav-btn guide-btn" onclick="location.href='guide_room.php'" title="Guide">
        <i class="fas fa-book-open"></i>
      </button>
    </div>
  </header>

  <main>
    <?php if ($rental): ?>
      <!-- Room Info Card -->
      <div class="room-info-card">
        <div class="room-title">Room <?= $room['room_number'] ?></div>
        <div class="room-type"><?= htmlspecialchars($room['type_name']) ?></div>
        
        <div class="info-grid">
          <div class="info-box">
            <div class="info-label">
              <i class="fas fa-clock"></i> Time Remaining
            </div>
            <div class="info-value" id="timeRemaining">--:--:--</div>
            <button class="extend-btn" onclick="openExtendModal()">
              <i class="fas fa-plus"></i> Extend Time
            </button>
          </div>
          
          <div class="info-box">
            <div class="info-label">
              <i class="fas fa-receipt"></i> Current Bill
            </div>
            <div class="info-value" id="currentBill">₱<?= $billData ? number_format($billData['grand_total'], 2) : '0.00' ?></div>
            <div class="info-subtext"><?= count($customerOrders) ?> order(s)</div>
            <button class="extend-btn" onclick="openBillModal()" style="background: rgba(46, 204, 113, 0.2);">
              <i class="fas fa-file-invoice-dollar"></i> View Bill
            </button>
          </div>
        </div>
      </div>

      <!-- Order Monitor -->
<div class="order-monitor">
  <div class="monitor-title">
    <i class="fas fa-list-check"></i> Your Orders
  </div>
  <div class="order-track" id="orderTrack">
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
            • Total: ₱<?= number_format($order['total'], 2) ?>
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

      <!-- Order Menu -->
      <div class="order-section">
        <div class="section-title">Order Menu</div>
        
        <?php if (count($drinks) > 0): ?>
          <div class="category-title">
            <i class="fas fa-glass-whiskey"></i> Drinks
          </div>
          <div class="products-grid">
            <?php foreach ($drinks as $p): ?>
              <div class="product-card">
                <div class="product-name"><?= htmlspecialchars($p['product_name']) ?></div>
                <div class="product-pric  e">₱<?= number_format($p['price'], 2) ?></div>
                <div class="product-stock <?= $p['stock_quantity'] <= 0 ? 'out' : '' ?>">
                  <?= $p['stock_quantity'] > 0 ? $p['stock_quantity'] . ' available' : 'Out of stock' ?>
                </div>
                
                <?php if ($p['stock_quantity'] > 0): ?>
                  <div class="quantity-control">
                    <button class="qty-btn" onclick="changeQty(<?= $p['product_id'] ?>, -1)">-</button>
                    <div class="qty-display" id="qty_<?= $p['product_id'] ?>">0</div>
                    <button class="qty-btn" onclick="changeQty(<?= $p['product_id'] ?>, 1)" data-max="<?= $p['stock_quantity'] ?>">+</button>
                  </div>
                  <button class="add-btn" onclick="addToCart(<?= $p['product_id'] ?>, '<?= htmlspecialchars($p['product_name']) ?>', <?= $p['price'] ?>, <?= $p['stock_quantity'] ?>)">
                    Add to Cart
                  </button>
                <?php else: ?>
                  <button class="add-btn" disabled>Out of Stock</button>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        
        <?php if (count($snacks) > 0): ?>
          <div class="category-title">
            <i class="fas fa-cookie-bite"></i> Snacks
          </div>
          <div class="products-grid">
            <?php foreach ($snacks as $p): ?>
              <div class="product-card">
                <div class="product-name"><?= htmlspecialchars($p['product_name']) ?></div>
                <div class="product-price">₱<?= number_format($p['price'], 2) ?></div>
                <div class="product-stock <?= $p['stock_quantity'] <= 0 ? 'out' : '' ?>">
                  <?= $p['stock_quantity'] > 0 ? $p['stock_quantity'] . ' available' : 'Out of stock' ?>
                </div>
                
                <?php if ($p['stock_quantity'] > 0): ?>
                  <div class="quantity-control">
                    <button class="qty-btn" onclick="changeQty(<?= $p['product_id'] ?>, -1)">-</button>
                    <div class="qty-display" id="qty_<?= $p['product_id'] ?>">0</div>
                    <button class="qty-btn" onclick="changeQty(<?= $p['product_id'] ?>, 1)" data-max="<?= $p['stock_quantity'] ?>">+</button>
                  </div>
                  <button class="add-btn" onclick="addToCart(<?= $p['product_id'] ?>, '<?= htmlspecialchars($p['product_name']) ?>', <?= $p['price'] ?>, <?= $p['stock_quantity'] ?>)">
                    Add to Cart
                  </button>
                <?php else: ?>
                  <button class="add-btn" disabled>Out of Stock</button>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Cart Section -->
      <div class="cart-section">
        <div class="cart-info">
          <div class="cart-label">Total Cart</div>
          <div class="cart-total">₱<span id="cartTotal">0.00</span></div>
          <div class="cart-items"><span id="cartCount">0</span> item(s)</div>
        </div>
        <button class="place-order-btn" id="placeOrderBtn" onclick="openConfirmModal()" disabled>
          Place Order
        </button>
      </div>
    <?php else: ?>
      <div class="no-rental-message">
        <i class="fas fa-door-closed"></i>
        <h3>No Active Rental</h3>
        <p>This room is currently not in use. Please contact staff to start a rental.</p>
      </div>
    <?php endif; ?>

    <div id="timeWarningNotif" class="notification warning">
  <div class="notification-header">
    <div class="notification-title">
      <i class="fas fa-exclamation-triangle"></i> Time Running Out!
    </div>
    <button class="notification-close" onclick="closeNotification()">×</button>
  </div>
  <div class="notification-body">
    Your rental time is almost up! Would you like to extend your session?
  </div>
  <div class="notification-actions">
    <button class="notif-btn notif-btn-secondary" onclick="closeNotification()">Not Now</button>
    <button class="notif-btn notif-btn-primary" onclick="closeNotification(); openExtendModal();">
      <i class="fas fa-clock"></i> Extend Time
    </button>
  </div>
</div>
  </main>

  <!-- Extend Time Modal -->
  <div id="extendModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title">Extend Your Time</div>
        <button class="modal-close" onclick="closeExtendModal()">×</button>
      </div>
      <div class="modal-body">
        <div class="extend-options" id="extendOptions"></div>
      </div>
      <div class="modal-actions">
        <button class="modal-btn btn-cancel" onclick="closeExtendModal()">Cancel</button>
        <button class="modal-btn btn-confirm" onclick="confirmExtend()" id="extendConfirmBtn" disabled>Extend</button>
      </div>
    </div>
  </div>

  <!-- Confirm Order Modal -->
  <div id="confirmModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <div class="modal-title">Confirm Order</div>
        <button class="modal-close" onclick="closeConfirmModal()">×</button>
      </div>
      <div class="modal-body">
        <div class="order-summary" id="orderSummary"></div>
        <div class="order-total">
          <span class="order-total-label">Total:</span>
          <span class="order-total-value">₱<span id="orderTotalValue">0.00</span></span>
        </div>
        <div style="margin-top:15px;">
          <label style="font-size:14px;font-weight:600;display:block;margin-bottom:6px;"><i class="fas fa-money-bill-wave"></i> Amount You Will Pay (₱)</label>
          <input type="number" id="amountTendered" min="0" step="0.01" placeholder="Enter amount" style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px;font-size:16px;">
          <div id="changeDisplay" style="margin-top:8px;font-size:14px;display:none;"></div>
          <div id="amountError" style="color:#e74c3c;font-size:13px;margin-top:4px;display:none;"></div>
        </div>
      </div>
      <div class="modal-actions">
        <button class="modal-btn btn-cancel" onclick="closeConfirmModal()">Cancel</button>
        <button class="modal-btn btn-confirm" onclick="confirmOrder()">Confirm Order</button>
      </div>
    </div>
  </div>

  <!-- Bill Modal -->
  <div id="billModal" class="modal">
    <div class="modal-content" style="max-width: 650px;">
      <div class="modal-header">
        <h3><i class="fas fa-file-invoice-dollar"></i> Bill Summary</h3>
        <button class="modal-close" onclick="closeBillModal()">×</button>
      </div>
      <div class="modal-body" id="billModalBody" style="max-height: 500px; overflow-y: auto;">
        <div style="text-align: center; padding: 50px 20px; color: #999;">
          <i class="fas fa-spinner fa-spin" style="font-size: 48px; color: #f5c542;"></i>
          <p style="margin-top: 20px; font-size: 16px;">Loading bill details...</p>
        </div>
      </div>
      <div class="modal-footer">
        <button class="modal-btn btn-cancel" onclick="closeBillModal()">Close</button>
      </div>
    </div>
  </div>

  <script>
    const roomId = <?= $room_id ?>;
    const hasRental = <?= $rental ? 'true' : 'false' ?>;
    
     <?php if ($rental): ?>
const rentalId = <?= $rental['rental_id'] ?>;
const startedAt = "<?= $rental['started_at'] ?>";
let totalMinutes = <?= $rental['total_minutes'] ?>;

let timerInterval;
let warningShown = false;

function updateTimer() {
  try {
    // Parse MySQL datetime properly
    const start = new Date(startedAt.replace(' ', 'T'));
    const totalMillis = totalMinutes * 60000;
    const end = new Date(start.getTime() + totalMillis);
    const now = new Date();
    const remaining = Math.max(0, Math.floor((end - now) / 1000));
    
    const hours = Math.floor(remaining / 3600);
    const minutes = Math.floor((remaining % 3600) / 60);
    const seconds = remaining % 60;
    
    const display = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    
    const elem = document.getElementById('timeRemaining');
    const timeBox = document.getElementById('timeBox');
    
    if (elem) {
      elem.textContent = display;
      
      // WARNING: Less than 5 minutes
      if (remaining <= 300 && remaining > 0) {
        timeBox.classList.add('warning');
        
        if (!warningShown) {
          showTimeWarning();
          warningShown = true;
        }
      } else {
        timeBox.classList.remove('warning');
      }
      
      // TIME EXPIRED
      if (remaining === 0) {
        clearInterval(timerInterval);
        alert('Your rental time has expired! Please proceed to checkout.');
      }
    }
  } catch (error) {
    console.error('Timer error:', error);
  }
}

// Start timer
updateTimer();
timerInterval = setInterval(updateTimer, 1000);
<?php endif; ?>
    
    // Cart management
    let cart = {};
    
    function changeQty(productId, delta) {
      const display = document.getElementById('qty_' + productId);
      const currentQty = parseInt(display.textContent) || 0;
      const newQty = Math.max(0, currentQty + delta);
      
      // Check max
      const plusBtn = display.nextElementSibling;
      const max = parseInt(plusBtn.dataset.max);
      
      if (newQty <= max) {
        display.textContent = newQty;
      }
    }
    
    function addToCart(productId, name, price, maxStock) {
      const qtyDisplay = document.getElementById('qty_' + productId);
      const qty = parseInt(qtyDisplay.textContent) || 0;
      
      if (qty <= 0) {
        alert('Please select a quantity');
        return;
      }
      
      if (cart[productId]) {
        cart[productId].quantity += qty;
      } else {
        cart[productId] = { name, price, quantity: qty };
      }
      
      // Check total doesn't exceed stock
      if (cart[productId].quantity > maxStock) {
        cart[productId].quantity = maxStock;
        alert(`Maximum ${maxStock} available for ${name}`);
      }
      
      // Reset display
      qtyDisplay.textContent = '0';
      
      updateCartDisplay();
    }
    
    function updateCartDisplay() {
      let total = 0;
      let count = 0;
      
      for (const id in cart) {
        const item = cart[id];
        total += item.price * item.quantity;
        count += item.quantity;
      }
      
      document.getElementById('cartTotal').textContent = total.toFixed(2);
      document.getElementById('cartCount').textContent = count;
      document.getElementById('placeOrderBtn').disabled = count === 0;
    }
    
    // Extend Time Modal
    function openExtendModal() {
      const price30 = <?= floatval($room['price_per_30min']) ?>;
      const options = [
        { minutes: 30, label: '30 Minutes', price: price30 },
        { minutes: 60, label: '1 Hour', price: price30 * 2 },
        { minutes: 120, label: '2 Hours', price: price30 * 4 },
        { minutes: 180, label: '3 Hours', price: price30 * 6 }
      ];
      
      const container = document.getElementById('extendOptions');
      container.innerHTML = '';
      
      options.forEach(opt => {
        const div = document.createElement('div');
        div.className = 'extend-option';
        div.dataset.minutes = opt.minutes;
        div.dataset.price = opt.price;
        div.innerHTML = `
          <div>
            <div class="extend-time">${opt.label}</div>
            <div class="extend-subtext">Extend your session</div>
          </div>
          <div class="extend-price">₱${opt.price.toFixed(2)}</div>
        `;
        div.onclick = () => selectExtend(div);
        container.appendChild(div);
      });
      
      document.getElementById('extendModal').classList.add('active');
    }
    
    function closeExtendModal() {
      document.getElementById('extendModal').classList.remove('active');
      document.querySelectorAll('.extend-option').forEach(o => o.classList.remove('selected'));
      document.getElementById('extendConfirmBtn').disabled = true;
    }
    
    function selectExtend(el) {
      document.querySelectorAll('.extend-option').forEach(o => o.classList.remove('selected'));
      el.classList.add('selected');
      document.getElementById('extendConfirmBtn').disabled = false;
    }
    
    async function confirmExtend() {
      const selected = document.querySelector('.extend-option.selected');
      if (!selected) return;
      
      const minutes = parseInt(selected.dataset.minutes);
      const btn = document.getElementById('extendConfirmBtn');
      btn.textContent = 'Processing...';
      btn.disabled = true;
      
      try {
        const formData = new FormData();
        formData.append('rental_id', rentalId);
        formData.append('minutes', minutes);
        
        const res = await fetch('../api/rooms/extend_time.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await res.json();
        
        if (data.success) {
          alert('Time extended successfully!');
          location.reload();
        } else {
          alert('Error: ' + (data.error || 'Unknown'));
          btn.textContent = 'Extend';
          btn.disabled = false;
        }
      } catch (err) {
        alert('Network error: ' + err.message);
        btn.textContent = 'Extend';
        btn.disabled = false;
      }
    }
    
    function openConfirmModal() {
      const summary = document.getElementById('orderSummary');
      let html = '';
      let total = 0;
      
      for (const id in cart) {
        const item = cart[id];
        const lineTotal = item.price * item.quantity;
        total += lineTotal;
        html += `
          <div class="order-item">
            <span class="order-item-name">${item.name} x${item.quantity}</span>
            <span class="order-item-price">₱${lineTotal.toFixed(2)}</span>
          </div>
        `;
      }
      
      summary.innerHTML = html;
      document.getElementById('orderTotalValue').textContent = total.toFixed(2);
      const amtInput = document.getElementById('amountTendered');
      amtInput.value = '';
      amtInput.min = total.toFixed(2);
      document.getElementById('changeDisplay').style.display = 'none';
      document.getElementById('amountError').style.display = 'none';
      amtInput.oninput = function() {
        const amt = parseFloat(this.value) || 0;
        const changeEl = document.getElementById('changeDisplay');
        const errEl = document.getElementById('amountError');
        if (amt > 0 && amt < total) {
          errEl.textContent = 'Amount is less than the total. Please enter at least ₱' + total.toFixed(2);
          errEl.style.display = 'block';
          changeEl.style.display = 'none';
        } else if (amt >= total) {
          errEl.style.display = 'none';
          const change = amt - total;
          changeEl.innerHTML = '<strong style="color:#27ae60">Change: ₱' + change.toFixed(2) + '</strong>';
          changeEl.style.display = 'block';
        } else {
          errEl.style.display = 'none';
          changeEl.style.display = 'none';
        }
      };
      document.getElementById('confirmModal').classList.add('active');
    }
    
    function closeConfirmModal() {
      document.getElementById('confirmModal').classList.remove('active');
    }
    
    async function confirmOrder() {
      const btn = event.target;
      const total = parseFloat(document.getElementById('orderTotalValue').textContent);
      const amtInput = document.getElementById('amountTendered');
      const amountTendered = parseFloat(amtInput.value);

      if (!amtInput.value || isNaN(amountTendered) || amountTendered < total) {
        document.getElementById('amountError').textContent = 'Please enter a valid amount of at least ₱' + total.toFixed(2);
        document.getElementById('amountError').style.display = 'block';
        return;
      }

      btn.textContent = 'Placing...';
      btn.disabled = true;
      
      const items = [];
      for (const id in cart) {
        items.push({
          product_id: parseInt(id),
          quantity: cart[id].quantity
        });
      }
      
      try {
        const formData = new FormData();
        formData.append('room_id', roomId);
        formData.append('items', JSON.stringify(items));
        formData.append('amount_tendered', amountTendered.toFixed(2));
        
        const res = await fetch('../api/orders/add_order.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await res.json();
        
        if (data.success) {
          const change = data.change_amount !== null ? parseFloat(data.change_amount).toFixed(2) : '0.00';
          alert('Order placed successfully!\nTotal: ₱' + parseFloat(data.order_total).toFixed(2) + '\nYour change: ₱' + change);
          cart = {};
          updateCartDisplay();
          closeConfirmModal();
          location.reload();
        } else {
          alert('Error: ' + (data.error || 'Unknown'));
          btn.textContent = 'Confirm Order';
          btn.disabled = false;
        }
      } catch (err) {
        alert('Network error: ' + err.message);
        btn.textContent = 'Confirm Order';
        btn.disabled = false;
      }
    }
    
    // Auto-refresh every 30 seconds
    setInterval(() => {
      location.reload();
    }, 30000);

    function showTimeWarning() {
  const notif = document.getElementById('timeWarningNotif');
  notif.classList.add('active');
  
  // Optional: Play alert sound
  try {
    const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBTGJ0fPTgjMGHm7A7+OZURE');
    audio.play().catch(e => console.log('Audio play failed:', e));
  } catch (e) {
    console.log('Audio not supported');
  }
}

function closeNotification() {
  document.getElementById('timeWarningNotif').classList.remove('active');
}

// Refresh bill and orders every 10 seconds
setInterval(async () => {
  try {
    // Update bill
    const billRes = await fetch(`../api/billing/get_bill.php?rental_id=${rentalId}`);
    const billData = await billRes.json();
    if (billData.success) {
      document.getElementById('currentBill').textContent = '₱' + parseFloat(billData.bill.grand_total).toFixed(2);
      
      // Update total minutes if extended
      if (billData.rental && billData.rental.total_minutes) {
        const newTotal = parseInt(billData.rental.total_minutes);
        if (newTotal > totalMinutes) {
          totalMinutes = newTotal;
          warningShown = false; // Reset warning for new time
        }
      }
      
      // Update order monitor
      if (billData.orders) {
        updateOrderMonitor(billData.orders);
      }
    }
  } catch (err) {
    console.error('Refresh error:', err);
  }
}, 10000);

function updateOrderMonitor(orders) {
  const track = document.getElementById('orderTrack');
  const statusLabels = {'NEW':'Order Received','PREPARING':'Preparing your order...','READY':'Ready for pickup','READY_TO_DELIVER':'Ready for pickup','DELIVERING':'Food is on the way!','DELIVERED':'Delivered'};

  if (!orders || orders.length === 0) {
    track.innerHTML = `
      <div class="empty-orders">
        <i class="fas fa-utensils"></i>
        <p>No orders yet.</p>
      </div>
    `;
    return;
  }

  let html = '';
  orders.forEach(order => {
    const label = statusLabels[order.status] || order.status;
    html += `
      <div class="order-card">
        <div class="order-header">
          <span class="order-id">Order #${order.order_id}</span>
          <span class="order-status status-${order.status}">${label}</span>
        </div>
        <div class="order-items">${order.items}</div>
        <div class="order-time">
          <i class="fas fa-clock"></i> ${order.ordered_at}
          • Total: ₱${parseFloat(order.total).toFixed(2)}
        </div>
      </div>
    `;
  });

  track.innerHTML = html;
}


// ===== BILL MODAL FUNCTIONS =====
async function openBillModal() {
  const modal = document.getElementById('billModal');
  const body = document.getElementById('billModalBody');
  
  modal.classList.add('active');
  
  // Show loading state
  body.innerHTML = `
    <div style="text-align: center; padding: 50px 20px; color: #999;">
      <i class="fas fa-spinner fa-spin" style="font-size: 48px; color: #f5c542;"></i>
      <p style="margin-top: 20px; font-size: 16px;">Loading bill details...</p>
    </div>
  `;
  
  try {
    const res = await fetch(`../api/billing/get_bill.php?rental_id=${rentalId}`);
    const data = await res.json();
    
    if (data.success && data.bill) {
      const bill = data.bill;
      const orders = data.orders || [];
      const rental = data.rental || {};
      
      let html = '';
      
      // Payment Status Banner
      if (bill.is_paid == 1) {
        html += `
          <div class="payment-status paid">
            <i class="fas fa-check-circle"></i>
            <span>This bill has been paid</span>
          </div>
        `;
      } else {
        html += `
          <div class="payment-status unpaid">
            <i class="fas fa-clock"></i>
            <span>Payment Pending — Please visit the cashier desk</span>
          </div>
        `;
      }
      
      // Room Charges Section
      const durationMinutes = rental.duration_minutes || totalMinutes;
      const hours = Math.floor(durationMinutes / 60);
      const mins = durationMinutes % 60;
      
      html += `
        <div class="bill-section">
          <div class="bill-section-title">
            <i class="fas fa-door-open"></i>
            <span>Room Charges</span>
          </div>
          <div class="bill-row">
            <span class="bill-label">Room Type</span>
            <span class="bill-value"><?= htmlspecialchars($room['type_name']) ?></span>
          </div>
          <div class="bill-row">
            <span class="bill-label">Room Number</span>
            <span class="bill-value">Room <?= $room['room_number'] ?></span>
          </div>
          <div class="bill-row">
            <span class="bill-label">Duration</span>
            <span class="bill-value">${hours}h ${mins}m</span>
          </div>
          <div class="bill-row">
            <span class="bill-label">Room Subtotal</span>
            <span class="bill-value">₱${parseFloat(bill.total_room_cost).toFixed(2)}</span>
          </div>
        </div>
      `;
      
      // Orders Section
      if (orders.length > 0) {
        html += `
          <div class="bill-section">
            <div class="bill-section-title">
              <i class="fas fa-utensils"></i>
              <span>Food & Beverages (${orders.length} items)</span>
            </div>
        `;
        
        orders.forEach(order => {
          const itemTotal = parseFloat(order.price || 0) * parseInt(order.quantity || 0);
          html += `
            <div class="bill-item">
              <span class="bill-item-name">${order.product_name}</span>
              <span class="bill-item-qty">×${order.quantity}</span>
              <span class="bill-item-price">₱${itemTotal.toFixed(2)}</span>
            </div>
          `;
        });
        
        html += `
          <div class="bill-row" style="margin-top: 15px;">
            <span class="bill-label">Orders Subtotal</span>
            <span class="bill-value">₱${parseFloat(bill.total_orders_cost).toFixed(2)}</span>
          </div>
        </div>
        `;
      } else {
        html += `
          <div class="bill-section">
            <div class="bill-section-title">
              <i class="fas fa-utensils"></i>
              <span>Food & Beverages</span>
            </div>
            <div style="text-align: center; padding: 30px 20px; color: #999; font-size: 14px;">
              <i class="fas fa-shopping-basket" style="font-size: 32px; margin-bottom: 10px; opacity: 0.5;"></i>
              <p>No orders placed</p>
            </div>
          </div>
        `;
      }
      
      // Grand Total
      html += `
        <div class="bill-section">
          <div class="bill-row total">
            <span><i class="fas fa-receipt"></i> GRAND TOTAL</span>
            <span>₱${parseFloat(bill.grand_total).toFixed(2)}</span>
          </div>
        </div>
      `;
      
      body.innerHTML = html;
    } else {
      body.innerHTML = `
        <div style="text-align: center; padding: 50px 20px; color: #e74c3c;">
          <i class="fas fa-exclamation-circle" style="font-size: 48px;"></i>
          <p style="margin-top: 20px; font-size: 16px;">${data.error || 'Unable to load bill'}</p>
        </div>
      `;
    }
  } catch (err) {
    console.error('Error loading bill:', err);
    body.innerHTML = `
      <div style="text-align: center; padding: 50px 20px; color: #e74c3c;">
        <i class="fas fa-exclamation-triangle" style="font-size: 48px;"></i>
        <p style="margin-top: 20px; font-size: 16px;">Network error: ${err.message}</p>
        <button class="extend-btn" onclick="openBillModal()" style="margin-top: 20px;">
          <i class="fas fa-sync-alt"></i> Retry
        </button>
      </div>
    `;
  }
}

function closeBillModal() {
  document.getElementById('billModal').classList.remove('active');
}

// Request Payment removed - customers should go to cashier desk directly
  </script>
</body>
</html>