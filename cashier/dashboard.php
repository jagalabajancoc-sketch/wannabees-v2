<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 3) {
    header('Location: ../index.php');
    exit;
}

$cashierId = intval($_SESSION['user_id']);
$cashierName = htmlspecialchars($_SESSION['display_name'] ?: $_SESSION['username']);

// Handle POST actions (before queries to avoid wasted work on redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_order_status') {
        $order_id = intval($_POST['order_id']);
        $new_status = strtoupper(trim($_POST['status']));
        $valid = ['NEW','PREPARING','READY_TO_DELIVER','DELIVERING','DELIVERED'];
        if ($order_id > 0 && in_array($new_status, $valid)) {
            $stmt = $mysqli->prepare("UPDATE orders SET status = ?, prepared_at = NOW() WHERE order_id = ?");
            $stmt->bind_param('si', $new_status, $order_id);
            $stmt->execute();
            $stmt->close();
            $now = date('Y-m-d H:i:s');
            $meta = json_encode(['to' => $new_status]);
            $stmt = $mysqli->prepare("INSERT INTO order_audit (order_id, action, user_id, role_id, meta, created_at) VALUES (?, 'STATUS_CHANGE', ?, 3, ?, ?)");
            $stmt->bind_param('iiss', $order_id, $cashierId, $meta, $now);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: dashboard.php');
        exit;
    }

    if ($action === 'mark_cleaning') {
        $room_id = intval($_POST['room_id']);
        $staff_name = trim($_POST['staff_name']);
        
        if ($room_id > 0 && !empty($staff_name)) {
            $mysqli->begin_transaction();
            try {
                $stmt = $mysqli->prepare("UPDATE rooms SET status = 'CLEANING' WHERE room_id = ? AND status != 'CLEANING'");
                $stmt->bind_param('i', $room_id);
                $stmt->execute();
                $stmt->close();
                $now = date('Y-m-d H:i:s');
                $stmt = $mysqli->prepare("INSERT INTO cleaning_logs (room_id, staff_name, verified_by, cleaned_at) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('isss', $room_id, $staff_name, $cashierName, $now);
                $stmt->execute();
                $stmt->close();
                $mysqli->commit();
            } catch (Exception $e) {
                $mysqli->rollback();
            }
        }
        header('Location: dashboard.php');
        exit;
    }

    if ($action === 'mark_available') {
        $room_id = intval($_POST['room_id']);
        $staff_name = trim($_POST['staff_name']);
        
        if ($room_id > 0 && !empty($staff_name)) {
            $mysqli->begin_transaction();
            try {
                $stmt = $mysqli->prepare("UPDATE rooms SET status = 'AVAILABLE' WHERE room_id = ? AND status = 'CLEANING'");
                $stmt->bind_param('i', $room_id);
                $stmt->execute();
                $stmt->close();
                $now = date('Y-m-d H:i:s');
                $stmt = $mysqli->prepare("INSERT INTO cleaning_logs (room_id, staff_name, verified_by, cleaned_at) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('isss', $room_id, $staff_name, $cashierName, $now);
                $stmt->execute();
                $stmt->close();
                $mysqli->commit();
            } catch (Exception $e) {
                $mysqli->rollback();
            }
        }
        header('Location: dashboard.php');
        exit;
    }
}

// Get active orders (pending/in-progress)
$activeOrdersSql = "
SELECT o.order_id, o.ordered_at, o.status, o.amount_tendered, o.change_amount,
       rm.room_number,
       GROUP_CONCAT(CONCAT(p.product_name, ' x', oi.quantity) SEPARATOR ', ') as items,
       SUM(oi.price * oi.quantity) as total
FROM orders o
JOIN order_items oi ON o.order_id = oi.order_id
JOIN products p ON oi.product_id = p.product_id
JOIN rentals r ON o.rental_id = r.rental_id
JOIN rooms rm ON r.room_id = rm.room_id
WHERE r.ended_at IS NULL AND o.status IN ('NEW','PREPARING','READY_TO_DELIVER','DELIVERING')
GROUP BY o.order_id
ORDER BY o.ordered_at ASC
LIMIT 50";
$activeOrdersResult = $mysqli->query($activeOrdersSql);
$activeOrders = [];
while ($row = $activeOrdersResult->fetch_assoc()) {
    $activeOrders[] = $row;
}

// Get all rooms
$roomsSql = "
SELECT r.room_id, r.room_number, r.status, r.is_active, rt.type_name, rt.price_per_hour, rt.price_per_30min,
       rent.rental_id, rent.started_at, rent.total_minutes
FROM rooms r
JOIN room_types rt ON r.room_type_id = rt.room_type_id
LEFT JOIN rentals rent ON rent.room_id = r.room_id AND rent.ended_at IS NULL
GROUP BY r.room_id
ORDER BY rt.price_per_hour ASC, r.room_number ASC";
$roomsResult = $mysqli->query($roomsSql);
if (!$roomsResult) {
    die('Query error: ' . $mysqli->error);
}
$rooms = [];
while ($row = $roomsResult->fetch_assoc()) {
    $rooms[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Dashboard - Wannabees KTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #212529;
            line-height: 1.5;
        }
        header {
            background: white;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .header-left { display: flex; align-items: center; gap: 1rem; }
        .header-left img { height: 40px; }
        .header-title { font-size: 1.125rem; font-weight: 600; }
        .header-subtitle { font-size: 0.875rem; color: #6c757d; }
.mobile-nav-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.25rem;
            cursor: pointer;
            color: #212529;
        }
        .header-nav { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .nav-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            color: #495057;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        .nav-btn:hover { background: #f8f9fa; }
        .nav-btn.active { background: #f2a20a; color: white; border-color: #f2a20a; }
        .nav-btn.logout { border-color: #e74c3c; color: #e74c3c; }
        .nav-btn.logout:hover { background: #fef5f5; border-color: #c0392b; color: #c0392b; }
        .nav-btn.logout:hover { background: #fef5f5; border-color: #c0392b; color: #c0392b; }
        @media (max-width: 768px) {
            .mobile-nav-toggle {
                display: block;
            }
            .header-nav {
                position: fixed;
                top: 60px;
                left: 0;
                right: 0;
                background: white;
                flex-direction: column;
                gap: 0;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease;
                z-index: 1000;
            }
            .header-nav.active {
                max-height: 400px;
            }
            .nav-btn {
                width: 100%;
                justify-content: flex-start;
                border-radius: 0;
                border: none;
                border-bottom: 1px solid #f0f0f0;
            }
            .nav-btn span {
                display: inline;
            }
            .nav-btn i {
                min-width: 20px;
            }
        }
        main {
            padding: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        .page-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        @media (max-width: 900px) { .page-grid { grid-template-columns: 1fr; } }
        .panel {
            background: white;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            overflow: hidden;
        }
        .panel-header {
            padding: 1rem 1.25rem;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .panel-header i { color: #f2a20a; }
        .panel-body { padding: 1rem; }
        .order-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-left: 3px solid #f2a20a;
        }
        .order-card:last-child { margin-bottom: 0; }
        .order-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
        .order-room { font-weight: 700; font-size: 1rem; }
        .order-status-badge {
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-NEW { background: #fff3cd; color: #856404; }
        .status-PREPARING { background: #cce5ff; color: #004085; }
        .status-READY_TO_DELIVER { background: #d4edda; color: #155724; }
        .status-DELIVERING { background: #d1ecf1; color: #0c5460; }
        .status-DELIVERED { background: #e2e3e5; color: #383d41; }
        .order-items { font-size: 0.8rem; color: #6c757d; margin-bottom: 0.4rem; }
        .order-money { font-size: 0.8rem; margin-bottom: 0.6rem; }
        .order-money span { font-weight: 600; }
        .order-actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .btn-status {
            padding: 0.35rem 0.75rem;
            border: none;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            background: #f2a20a;
            color: #2c2c2c;
            transition: background 0.2s;
        }
        .btn-status:hover { background: #f2a20a; }
        .btn-status.deliver { background: #28a745; color: white; }
        .btn-status.deliver:hover { background: #218838; }
        .empty-state { text-align: center; padding: 2rem; color: #6c757d; }
        .empty-state i { font-size: 2.5rem; margin-bottom: 0.75rem; color: #dee2e6; display: block; }
        .room-filters {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        @media (max-width: 600px) {
            .room-filters { grid-template-columns: 1fr; }
        }
        .room-filters input,
        .room-filters select {
            padding: 0.5rem 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 0.875rem;
            font-family: inherit;
        }
        .room-filters input:focus,
        .room-filters select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        .room-filters input::placeholder {
            color: #adb5bd;
        }
        .room-filter-tags {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        .filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.8rem;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            background: white;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .filter-tag input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            margin: 0;
        }
        .filter-tag:hover {
            border-color: #adb5bd;
            background: #f8f9fa;
        }
        .filter-tag input[type="checkbox"]:checked + label {
            font-weight: 600;
        }
        .filter-tag label {
            cursor: pointer;
            margin: 0;
            user-select: none;
        }
        .room-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 0.75rem;
        }
        .room-card {
            background: white;
            border-radius: 12px;
            padding: 1rem 0.75rem;
            border: 2px solid #e9ecef;
            text-align: center;
            transition: all 0.2s ease;
            cursor: default;
        }
        .room-card.occupied { cursor: pointer; background: #f0f7ff; border-color: #007bff; }
        .room-card.occupied:hover { border-color: #007bff; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .room-card.cleaning { cursor: pointer; }
        .room-card.cleaning:hover { background: #fff8e1; }
        .room-card.available { cursor: pointer; }
        .room-card.available:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(28, 167, 69, 0.3); border-color: #28a745; }
        .room-card.inactive { border-color: #adb5bd; background: #f5f5f5; cursor: default; }
        .room-card.inactive:hover { transform: none; box-shadow: none; background: #f5f5f5; border-color: #adb5bd; }
        .room-card.inactive .room-num { color: #999; }
        .room-card.inactive .room-status-text { background: #d9d9d9; color: #666; }
        .room-card.cleaning { border-color: #f2a20a; background: #fffbf0; }
        .room-card.available { border-color: #28a745; background: linear-gradient(135deg, #f0fff4 0%, #e0f8e9 100%); }
        .room-card.inactive.available { border-color: #adb5bd; background: #f5f5f5; }
        .room-card.inactive.available .room-status-text { display: none; }
        .room-num { font-size: 1.25rem; font-weight: 700; }
        .room-type { font-size: 0.7rem; color: #6c757d; margin: 0.2rem 0 0.5rem; }
        .room-status-text { font-size: 0.7rem; font-weight: 600; margin-bottom: 0.5rem; }
        .room-card.occupied .room-status-text { color: #007bff; }
        .room-card.cleaning .room-status-text { color: #f2a20a; }
        .room-card.available .room-status-text { color: #28a745; }
        .room-time {
            font-size: 0.9rem;
            font-weight: 700;
            color: #0d6efd;
            font-family: 'Courier New', monospace;
            margin: 0.5rem 0;
            padding: 0.4rem;
            background: rgba(13, 110, 253, 0.1);
            border-radius: 6px;
        }
        .btn-room {
            width: 100%;
            padding: 0.3rem;
            border: none;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.25rem;
        }
        .btn-cleaning { background: #17a2b8; color: white; }
        .btn-cleaning:hover { background: #138496; }
        .btn-available { background: #28a745; color: white; }
        .btn-available:hover { background: #218838; }
        .btn-billing { background: #f2a20a; color: white; }
        .btn-billing:hover { background: #d89209; }
        .summary-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .summary-card {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            text-align: center;
        }
        .summary-card .s-label { font-size: 0.7rem; color: #6c757d; text-transform: uppercase; }
        .summary-card .s-value { font-size: 1.75rem; font-weight: 700; }
        .s-new { color: #212529; }
        .s-occupied { color: #212529; }
        .s-cleaning { color: #212529; }
        .s-available { color: #212529; }
        .meta-info { font-size: 0.75rem; color: #6c757d; margin-top: 0.25rem; }
        .change-amount { color: #28a745; font-weight: 600; }
        /* Toast notifications */
        #toast-container { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
        .toast { background: #2c2c2c; color: white; padding: 0.75rem 1.25rem; border-radius: 8px; font-size: 0.875rem; box-shadow: 0 4px 12px rgba(0,0,0,0.2); opacity: 0; transform: translateY(10px); transition: all 0.3s ease; max-width: 300px; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { border-left: 4px solid #28a745; }
        .toast.warning { background: #ff9800; border-left: 4px solid #ff6f00; }
        .toast.timeout { background: #e74c3c; border-left: 3px solid #c0392b; }
        /* Permanent Timeout Alert Banner */
        #timeout-alert-banner { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 1rem 1.5rem; margin-bottom: 1.5rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(231,76,60,0.3); display: none; }
        #timeout-alert-banner.show { display: block; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .timeout-banner-header { display: flex; align-items: center; gap: 0.5rem; font-size: 1rem; font-weight: 700; margin-bottom: 0.75rem; }
        .timeout-banner-header i { font-size: 1.25rem; animation: pulse-icon 1.5s infinite; }
        @keyframes pulse-icon { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }
        .timeout-rooms-list { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .timeout-room-badge { background: rgba(255,255,255,0.2); padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.875rem; font-weight: 600; border: 1px solid rgba(255,255,255,0.3); cursor: pointer; transition: all 0.2s; }
        .timeout-room-badge:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); }
        /* Pulse animation for NEW orders */
        @keyframes pulse-badge { 0%,100% { opacity:1; } 50% { opacity:0.5; } }
        .status-NEW { animation: pulse-badge 1.5s ease-in-out infinite; }
        /* Pending Transactions */
        .tx-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 0.75rem; }
        .tx-card { background: #f8f9fa; border-radius: 6px; padding: 0.75rem; border-left: 3px solid #f2a20a; font-size: 0.85rem; }
        .tx-card.gcash { border-left-color: #007bff; }
        .tx-card.cash  { border-left-color: #28a745; }
        .tx-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
        .tx-room { font-weight: 700; font-size: 0.95rem; }
        .tx-badge { padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .tx-badge.gcash-badge { background: #cce5ff; color: #004085; }
        .tx-badge.cash-badge  { background: #d4edda; color: #155724; }
        .tx-detail { font-size: 0.8rem; color: #6c757d; margin-bottom: 0.5rem; }
        .tx-gcash-info { font-size: 0.8rem; background: #e9f2ff; padding: 0.4rem 0.6rem; border-radius: 6px; margin-bottom: 0.5rem; }
        .tx-actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .btn-tx { padding: 0.35rem 0.8rem; border: none; border-radius: 4px; font-size: 0.75rem; font-weight: 600; cursor: pointer; }
        .btn-approve  { background: #28a745; color: white; }
        .btn-approve:hover  { background: #218838; }
        .btn-reject   { background: #e74c3c; color: white; }
        .btn-reject:hover   { background: #c0392b; }
        .btn-reject:hover   { background: #c0392b; }
        .btn-collect  { background: #17a2b8; color: white; }
        .btn-collect:hover  { background: #138496; }
        .tx-notes-input { width: 100%; margin-top: 0.5rem; padding: 0.35rem 0.5rem; border: 1px solid #dee2e6; border-radius: 4px; font-size: 0.75rem; }
        /* Room update pulse animation */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); box-shadow: 0 4px 20px rgba(245, 197, 66, 0.4); }
            100% { transform: scale(1); }
        }
    </style>    <script>
        function toggleMobileNav() {
            const nav = document.getElementById('headerNav');
            if (nav) nav.classList.toggle('active');
        }
        document.addEventListener('click', function(e) {
            const nav = document.getElementById('headerNav');
            const toggle = document.querySelector('.mobile-nav-toggle');
            if (nav && toggle && !nav.contains(e.target) && !toggle.contains(e.target)) {
                nav.classList.remove('active');
            }
        });
    </script></head>
<body>
    <header>
        <div class="header-container">
            <div class="header-left">
                <img src="../assets/images/KTVL.png" alt="Logo" onerror="this.style.display='none'">
                <div>
                    <div class="header-title">Wannabees Family KTV</div>
                </div>
            </div>
            <button class="mobile-nav-toggle" onclick="toggleMobileNav()"><i class="fas fa-bars"></i></button>
            <nav class="header-nav" id="headerNav">
                <a href="dashboard.php" class="nav-btn active"><i class="fas fa-home"></i> <span>Dashboard</span></a>
                <a href="transactions.php" class="nav-btn"><i class="fas fa-history"></i> <span>Transactions</span></a>
                <a href="sales_report.php" class="nav-btn"><i class="fas fa-chart-line"></i> <span>Sales</span></a>
                <a href="guide.php" class="nav-btn"><i class="fas fa-book"></i> <span>Guide</span></a>
                <a href="settings.php" class="nav-btn"><i class="fas fa-cog"></i> <span>Settings</span></a>
                <a href="../auth/logout.php" class="nav-btn logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
            </nav>
        </div>
    </header>

    <main>
        <?php
        $newOrders = count(array_filter($activeOrders, fn($o) => $o['status'] === 'NEW'));
        $occupied = count(array_filter($rooms, fn($r) => $r['status'] === 'OCCUPIED'));
        $cleaning = count(array_filter($rooms, fn($r) => $r['status'] === 'CLEANING'));
        $available = count(array_filter($rooms, fn($r) => $r['status'] === 'AVAILABLE'));
        ?>
        <div class="summary-bar">
            <div class="summary-card">
                <div class="s-label">New Orders</div>
                <div class="s-value s-new"><?= $newOrders ?></div>
            </div>
            <div class="summary-card">
                <div class="s-label">Active Orders</div>
                <div class="s-value s-occupied"><?= count($activeOrders) ?></div>
            </div>
            <div class="summary-card">
                <div class="s-label">Rooms Occupied</div>
                <div class="s-value s-occupied"><?= $occupied ?></div>
            </div>
            <div class="summary-card">
                <div class="s-label">Rooms Cleaning</div>
                <div class="s-value s-cleaning"><?= $cleaning ?></div>
            </div>
            <div class="summary-card">
                <div class="s-label">Rooms Available</div>
                <div class="s-value s-available"><?= $available ?></div>
            </div>
        </div>

        <!-- Timeout Alert Banner -->
        <div id="timeout-alert-banner">
            <div class="timeout-banner-header">
                <i class="fas fa-exclamation-triangle"></i>
                <span>TIME OUT ALERT</span>
            </div>
            <div class="timeout-rooms-list" id="timeout-rooms-list">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>

        <div class="page-grid">
            <!-- Active Orders Panel -->
            <div class="panel">
                <div class="panel-header">
                    <i class="fas fa-utensils"></i> Active Orders (<?= count($activeOrders) ?>)
                    <span style="margin-left:auto;font-size:0.75rem;font-weight:400;color:#28a745;"><i class="fas fa-circle" style="font-size:0.5rem;animation:pulse-badge 2s infinite;"></i> Live</span>
                </div>
                <div class="panel-body" id="ordersPanel">
                    <?php if (empty($activeOrders)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No active orders right now</p>
                    </div>
                    <?php else: ?>
                        <?php
                        $nextStatus = ['NEW'=>'PREPARING','PREPARING'=>'READY_TO_DELIVER','READY_TO_DELIVER'=>'DELIVERING','DELIVERING'=>'DELIVERED'];
                        $nextLabel = ['NEW'=>'Start Preparing','PREPARING'=>'Ready to Deliver','READY_TO_DELIVER'=>'Delivering','DELIVERING'=>'Mark Delivered'];
                        foreach ($activeOrders as $order): ?>
                        <div class="order-card">
                            <div class="order-top">
                                <span class="order-room"><i class="fas fa-door-open"></i> Room <?= $order['room_number'] ?></span>
                                <span class="order-status-badge status-<?= $order['status'] ?>"><?= str_replace('_', ' ', $order['status']) ?></span>
                            </div>
                            <div class="order-items"><?= htmlspecialchars($order['items']) ?></div>
                            <div class="order-money">
                                Total: <span>₱<?= number_format($order['total'], 2) ?></span>
                                <?php if ($order['amount_tendered'] !== null): ?>
                                &nbsp;|&nbsp; Tendered: <span>₱<?= number_format($order['amount_tendered'], 2) ?></span>
                                &nbsp;|&nbsp; Change: <span style="color:#28a745">₱<?= number_format($order['change_amount'] ?? 0, 2) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="meta-info"><i class="fas fa-clock"></i> <?= date('g:i A', strtotime($order['ordered_at'])) ?> &nbsp; Order #<?= $order['order_id'] ?></div>
                            <?php if (isset($nextStatus[$order['status']])): ?>
                            <div class="order-actions" style="margin-top:0.5rem;">
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="update_order_status">
                                    <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                    <input type="hidden" name="status" value="<?= $nextStatus[$order['status']] ?>">
                                    <button type="submit" class="btn-status <?= $nextStatus[$order['status']] === 'DELIVERED' ? 'deliver' : '' ?>">
                                        <i class="fas fa-arrow-right"></i> <?= $nextLabel[$order['status']] ?>
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rooms Management Panel -->
            <div class="panel">
                <div class="panel-header">
                    <i class="fas fa-door-open"></i> Rooms
                </div>
                <div class="panel-body">
                    <div class="room-filters">
                        <input type="text" id="roomSearch" placeholder="Search by room number..." />
                        <select id="roomCategory">
                            <option value="">All Categories</option>
                            <?php
                            $categories = array_unique(array_map(fn($r) => $r['type_name'], $rooms));
                            sort($categories);
                            foreach ($categories as $category):
                            ?>
                            <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="room-filter-tags">
                        <label class="filter-tag">
                            <input type="checkbox" id="showCleaning" checked />
                            <label for="showCleaning">CLEANING</label>
                        </label>
                        <label class="filter-tag">
                            <input type="checkbox" id="showAvailable" checked />
                            <label for="showAvailable">AVAILABLE</label>
                        </label>
                        <label class="filter-tag">
                            <input type="checkbox" id="showNotAvailable" checked />
                            <label for="showNotAvailable">NOT AVAILABLE</label>
                        </label>
                        <label class="filter-tag">
                            <input type="checkbox" id="showTimeout" checked />
                            <label for="showTimeout">TIMEOUT</label>
                        </label>
                    </div>
                    <div class="room-grid" id="roomGrid">
                        <?php foreach ($rooms as $room): 
                            // Determine filter type
                            $filterType = 'occupied';
                            if ($room['is_active'] == 0) {
                                $filterType = 'not-available';
                            } elseif ($room['status'] === 'CLEANING') {
                                $filterType = 'cleaning';
                            } elseif ($room['status'] === 'AVAILABLE') {
                                $filterType = 'available';
                            } elseif ($room['status'] === 'OCCUPIED' && $room['started_at'] && $room['total_minutes']) {
                                // Check if room is timed out
                                $startTime = strtotime($room['started_at']);
                                $elapsedMinutes = (time() - $startTime) / 60;
                                if ($elapsedMinutes > $room['total_minutes']) {
                                    $filterType = 'timeout';
                                }
                            }
                        ?>
                        <div class="room-card <?= strtolower($room['status']) ?><?= $room['is_active'] == 0 ? ' inactive' : '' ?>" 
                             data-room-number="<?= $room['room_number'] ?>"
                             data-room-category="<?= htmlspecialchars($room['type_name']) ?>"
                             data-room-filter="<?= $filterType ?>"
                             <?php if ($room['is_active'] == 1): ?>
                             onclick="handleRoomClick(<?= $room['room_id'] ?>, <?= $room['room_number'] ?>, '<?= $room['status'] ?>', <?= $room['rental_id'] ?? 'null' ?>)"
                             <?php endif; ?>>
                            <div class="room-num"><?= $room['room_number'] ?></div>
                            <div class="room-type"><?= htmlspecialchars($room['type_name']) ?></div>
                            <?php if ($room['is_active'] == 0): ?>
                                <div style="padding:0.3rem;text-align:center;font-size:0.75rem;color:#999;font-weight:600;">Not Available</div>
                            <?php else: ?>
                                <?php if ($room['status'] === 'CLEANING'): ?>
                                    <div class="room-status-text">🧹 CLEANING</div>
                                <?php elseif ($room['status'] === 'OCCUPIED' && $room['started_at']): ?>
                                    <div class="room-time" data-started="<?= htmlspecialchars($room['started_at']) ?>">00:00:00</div>
                                <?php else: ?>
                                    <div class="room-status-text"><?= $room['status'] ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Room Payments Panel (full width below) -->
        <div class="panel" style="grid-column:1/-1;">
            <div class="panel-header">
                <i class="fas fa-credit-card"></i> Pending Room Payments
                <span style="margin-left:auto;font-size:0.75rem;font-weight:400;color:#28a745;"><i class="fas fa-circle" style="font-size:0.5rem;animation:pulse-badge 2s infinite;"></i> Live</span>
            </div>
            <div class="panel-body" id="pendingTxPanel">
                <div class="empty-state"><i class="fas fa-check-circle"></i><p>No pending payments</p></div>
            </div>
        </div>
    </div>
    </main>

    <!-- Toast Container -->
    <div id="toast-container"></div>

    <!-- QR Code Modal (shown after rental start) -->
    <div id="qrModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:1100;align-items:center;justify-content:center;padding:20px;">
        <div style="background:white;border-radius:16px;padding:2rem;max-width:400px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <h3 style="font-size:1.25rem;margin-bottom:0.25rem;">Room Access QR Code</h3>
            <p style="color:#6c757d;font-size:0.875rem;margin-bottom:1.25rem;">Room <strong id="qrRoomNumber"></strong> — Give this to the customer</p>
            <img id="qrImage" src="" alt="QR Code" style="width:220px;height:220px;border:4px solid #f2a20a;border-radius:12px;margin-bottom:1.25rem;">
            <div style="background:#fff9e6;border:2px solid #f2a20a;border-radius:10px;padding:1rem;margin-bottom:1.25rem;">
                <div style="font-size:0.8rem;color:#856404;margin-bottom:0.4rem;font-weight:600;">OTP CODE</div>
                <div id="qrOtpCode" style="font-size:2.5rem;font-weight:900;letter-spacing:0.4rem;color:#2c2c2c;font-family:monospace;"></div>
            </div>
            <div style="display:flex;gap:0.75rem;">
                <button onclick="window.print()" style="flex:1;padding:0.6rem;border:1px solid #dee2e6;border-radius:6px;background:white;cursor:pointer;font-size:0.875rem;">Print</button>
                <button onclick="closeQrModal()" style="flex:1;padding:0.6rem;border:none;border-radius:6px;background:#f2a20a;cursor:pointer;font-size:0.875rem;font-weight:600;">Done</button>
            </div>
        </div>
    </div>

    <!-- Start Rental Modal -->
    <div id="startRentalModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:20px;">
        <div style="background:white;border-radius:12px;padding:1.5rem;max-width:400px;width:100%;">
            <h3 style="margin-bottom:1.5rem;font-size:1.125rem;">Rental Payment — Room <span id="startRentalRoom"></span></h3>
            
            <!-- Duration Selection -->
            <div style="margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid #e9ecef;">
                <label style="font-size:0.875rem;font-weight:600;display:block;margin-bottom:0.75rem;">Select Duration</label>
                <div id="durationOptions" style="display:flex;flex-direction:column;gap:0.5rem;">
                    <!-- Will be populated by JS -->
                </div>
            </div>

            <!-- Price Display -->
            <div style="margin-bottom:1.5rem;padding:1rem;background:#f8f9fa;border-radius:8px;border:1px solid #e9ecef;">
                <div style="font-size:0.875rem;color:#6c757d;margin-bottom:0.5rem;">Total Cost</div>
                <div style="font-size:1.75rem;font-weight:700;color:#f2a20a;">₱<span id="rentalCost">0.00</span></div>
            </div>

            <!-- Payment Method Selection -->
            <div style="margin-bottom:1.5rem;">
                <label style="font-size:0.875rem;font-weight:600;display:block;margin-bottom:0.75rem;">Payment Method</label>
                <div style="display:flex;gap:0.5rem;">
                    <button class="rental-payment-btn selected" data-method="CASH" onclick="selectRentalPayment(this)" style="flex:1;padding:0.75rem;border:2px solid #e9ecef;border-radius:6px;background:white;cursor:pointer;font-size:0.875rem;font-weight:600;color:#495057;transition:all 0.2s;" onmouseover="this.style.borderColor='#adb5bd'" onmouseout="this.style.borderColor=this.classList.contains('selected') ? '#f2a20a' : '#e9ecef'">CASH</button>
                    <button class="rental-payment-btn" data-method="GCASH" onclick="selectRentalPayment(this)" style="flex:1;padding:0.75rem;border:2px solid #e9ecef;border-radius:6px;background:white;cursor:pointer;font-size:0.875rem;font-weight:600;color:#495057;transition:all 0.2s;" onmouseover="this.style.borderColor='#adb5bd'" onmouseout="this.style.borderColor=this.classList.contains('selected') ? '#f2a20a' : '#e9ecef'">GCASH</button>
                </div>
            </div>

            <!-- GCash Reference Number (shown only for GCash) -->
            <div id="gcashReferenceField" style="margin-bottom:1.5rem;display:none;">
                <label style="font-size:0.875rem;font-weight:600;display:block;margin-bottom:0.5rem;">GCash Reference Number <span style="color:#dc3545;">*</span></label>
                <input type="text" id="gcashReferenceNumber" placeholder="Enter GCash reference number" maxlength="100" style="width:100%;padding:0.75rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.875rem;font-family:monospace;"/>
                <small style="color:#6c757d;font-size:0.75rem;display:block;margin-top:0.25rem;">Required for GCash payments</small>
            </div>

            <!-- Payment Input -->
            <div style="margin-bottom:1.5rem;">
                <label style="font-size:0.875rem;font-weight:600;display:block;margin-bottom:0.5rem;">Payment Amount</label>
                <input type="number" id="paymentAmount" placeholder="Enter amount" min="0" step="0.01" style="width:100%;padding:0.75rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.875rem;"/>
            </div>

            <!-- Change Display -->
            <div style="margin-bottom:1.5rem;padding:1rem;background:#f0fff4;border-radius:8px;border:1px solid #d4edda;display:none;" id="changeDisplay">
                <div style="font-size:0.875rem;color:#155724;margin-bottom:0.5rem;">Change</div>
                <div style="font-size:1.5rem;font-weight:700;color:#28a745;">₱<span id="changeAmount">0.00</span></div>
            </div>

            <div style="display:flex;gap:0.75rem;">
                <button onclick="closeStartRentalModal()" style="flex:1;padding:0.75rem;border:1px solid #dee2e6;border-radius:6px;background:white;cursor:pointer;font-size:0.875rem;font-weight:600;">Cancel</button>
                <button onclick="confirmStartRental()" style="flex:1;padding:0.75rem;border:none;border-radius:6px;background:#f2a20a;cursor:pointer;font-size:0.875rem;font-weight:600;color:white;">Proceed to Payment</button>
            </div>
        </div>
    </div>

    <!-- Cleaning Log Modal -->
    <div id="cleaningModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:20px;">
        <div style="background:white;border-radius:12px;padding:1.5rem;max-width:400px;width:100%;">
            <h3 style="margin-bottom:1rem;font-size:1.125rem;"><span id="cleaningModalTitle">Log Cleaning</span> — Room <span id="cleaningModalRoom"></span></h3>
            <form id="cleaningForm" method="POST" style="display:flex;flex-direction:column;gap:1rem;">
                <input type="hidden" name="action" id="cleaningAction">
                <input type="hidden" name="room_id" id="cleaningRoomId">
                
                <div>
                    <label style="font-size:0.875rem;font-weight:600;display:block;margin-bottom:0.5rem;">Staff Name</label>
                    <input type="text" id="cleaningStaffName" name="staff_name" placeholder="Enter staff name who cleaned" style="width:100%;padding:0.5rem;border:1px solid #dee2e6;border-radius:6px;font-size:0.875rem;" required>
                </div>
                
                <div style="display:flex;gap:0.5rem;">
                    <button type="button" onclick="closeCleaningModal()" style="flex:1;padding:0.6rem;border:1px solid #dee2e6;border-radius:6px;background:white;cursor:pointer;font-size:0.875rem;">Cancel</button>
                    <button type="submit" style="flex:1;padding:0.6rem;border:none;border-radius:6px;background:#f2a20a;cursor:pointer;font-size:0.875rem;font-weight:600;">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Transaction/Payment Options Modal -->
    <div id="transactionModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:20px;">
        <div style="background:white;border-radius:12px;padding:1.5rem;max-width:500px;width:100%;">
            <h3 style="margin-bottom:1.5rem;font-size:1.125rem;">Payment Options — Room <span id="transactionModalRoom"></span></h3>
            
            <!-- Bill Section -->
            <div style="margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid #e9ecef;">
                <h4 style="font-size:0.95rem;font-weight:600;margin-bottom:1rem;">View Bill</h4>
                <button onclick="loadBillModal()" style="width:100%;padding:0.75rem;border:2px solid #212529;border-radius:6px;background:transparent;color:#212529;cursor:pointer;font-size:0.875rem;font-weight:600;transition:all 0.2s;" onmouseover="this.style.background='#212529';this.style.color='white'" onmouseout="this.style.background='transparent';this.style.color='#212529'" onmousedown="this.style.background='#000'" onmouseup="this.style.background='#212529'">
                    View/Print Bill
                </button>
            </div>

            <!-- Show QR Section -->
            <div style="margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid #e9ecef;">
                <h4 style="font-size:0.95rem;font-weight:600;margin-bottom:1rem;">Room Access</h4>
                <button onclick="showRentalQr()" style="width:100%;padding:0.75rem;border:2px solid #212529;border-radius:6px;background:transparent;color:#212529;cursor:pointer;font-size:0.875rem;font-weight:600;transition:all 0.2s;" onmouseover="this.style.background='#212529';this.style.color='white'" onmouseout="this.style.background='transparent';this.style.color='#212529'" onmousedown="this.style.background='#000'" onmouseup="this.style.background='#212529'">
                    Show QR & OTP
                </button>
            </div>

            <!-- Transaction Types -->
            <div style="margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid #e9ecef;">
                <h4 style="font-size:0.95rem;font-weight:600;margin-bottom:1rem;">Make a Transaction</h4>
                <div style="display:flex;gap:0.75rem;flex-direction:column;">
                    <button onclick="makeOrderTransaction()" style="width:100%;padding:0.75rem;border:2px solid #0d6efd;border-radius:6px;background:transparent;color:#0d6efd;cursor:pointer;font-size:0.875rem;font-weight:600;transition:all 0.2s;" onmouseover="this.style.background='#0d6efd';this.style.color='white'" onmouseout="this.style.background='transparent';this.style.color='#0d6efd'" onmousedown="this.style.background='#0a58ca'" onmouseup="this.style.background='#0d6efd'">
                        Make a Transaction
                    </button>
                </div>
            </div>

            <!-- End Rental Section -->
            <div style="margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid #e9ecef;">
                <h4 style="font-size:0.95rem;font-weight:600;margin-bottom:1rem;">End Rental</h4>
                <button onclick="endRentalFromModal()" style="width:100%;padding:0.75rem;border:2px solid #dc3545;border-radius:6px;background:transparent;color:#dc3545;cursor:pointer;font-size:0.875rem;font-weight:600;transition:all 0.2s;" onmouseover="this.style.background='#dc3545';this.style.color='white'" onmouseout="this.style.background='transparent';this.style.color='#dc3545'" onmousedown="this.style.background='#b02a37'" onmouseup="this.style.background='#dc3545'">
                    End Rental
                </button>
            </div>

            <div style="margin-top:1.5rem;">
                <button onclick="closeTransactionModal()" style="width:100%;padding:0.6rem;border:1px solid #dee2e6;border-radius:6px;background:white;cursor:pointer;font-size:0.875rem;font-weight:600;">Close</button>
            </div>
        </div>
    </div>

    <!-- Bill Modal -->
    <div id="billModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1100;align-items:center;justify-content:center;padding:20px;">
        <div style="background:white;border-radius:12px;padding:1.5rem;max-width:600px;width:100%;max-height:80vh;overflow-y:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
                <h3 style="font-size:1.25rem;">Room Bill</h3>
                <button onclick="closeBillModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#6c757d;">&times;</button>
            </div>
            <div id="billContent" style="margin-bottom:1.5rem;">
                <div style="text-align:center;padding:2rem;">
                    <p style="margin-top:1rem;color:#6c757d;">Loading bill...</p>
                </div>
            </div>
            <div style="display:flex;gap:0.75rem;border-top:1px solid #e9ecef;padding-top:1.5rem;">
                <button onclick="window.print()" style="flex:1;padding:0.6rem;border:1px solid #dee2e6;border-radius:6px;background:white;cursor:pointer;font-size:0.875rem;font-weight:600;">
                    Print
                </button>
                <button onclick="closeBillModal()" style="flex:1;padding:0.6rem;border:none;border-radius:6px;background:#f2a20a;cursor:pointer;font-size:0.875rem;font-weight:600;">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Toast notification helper
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.innerHTML = `${message}`;
            container.appendChild(toast);
            requestAnimationFrame(() => { requestAnimationFrame(() => { toast.classList.add('show'); }); });
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        function updateTimeoutBanner() {
            const banner = document.getElementById('timeout-alert-banner');
            const list = document.getElementById('timeout-rooms-list');
            
            if (timeoutRooms.size === 0) {
                banner.classList.remove('show');
                return;
            }
            
            list.innerHTML = '';
            timeoutRooms.forEach((data, roomId) => {
                const badge = document.createElement('div');
                badge.className = 'timeout-room-badge';
                badge.textContent = `Room ${data.room_number}`;
                badge.onclick = () => {
                    // Find and scroll to the room card
                    const cards = document.querySelectorAll('.room-card');
                    for (const card of cards) {
                        const roomNum = card.querySelector('.room-num');
                        if (roomNum && roomNum.textContent == data.room_number) {
                            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            card.style.animation = 'none';
                            setTimeout(() => {
                                card.style.animation = 'highlight-flash 1s ease';
                            }, 10);
                            break;
                        }
                    }
                };
                list.appendChild(badge);
            });
            
            banner.classList.add('show');
        }
        
        // Add highlight animation CSS
        const style = document.createElement('style');
        style.textContent = '@keyframes highlight-flash { 0%, 100% { box-shadow: 0 1px 3px rgba(0,0,0,0.1); } 50% { box-shadow: 0 0 20px rgba(231,76,60,0.6); } }';
        document.head.appendChild(style);

        // Room Click Handler
        function handleRoomClick(roomId, roomNumber, status, rentalId) {
            if (status === 'OCCUPIED' && rentalId !== null) {
                openTransactionModal(roomId, roomNumber, rentalId);
            } else if (status === 'CLEANING') {
                openCleaningModal(roomId, roomNumber, 'mark_available');
            } else if (status === 'AVAILABLE') {
                startRental(roomId, roomNumber);
            }
        }

        // Cleaning Modal Functions
        let cleaningModalData = { roomId: null, roomNumber: null, action: null };

        function openCleaningModal(roomId, roomNumber, action) {
            cleaningModalData.roomId = roomId;
            cleaningModalData.roomNumber = roomNumber;
            cleaningModalData.action = action;
            
            document.getElementById('cleaningRoomId').value = roomId;
            document.getElementById('cleaningAction').value = action;
            document.getElementById('cleaningModalRoom').textContent = roomNumber;
            document.getElementById('cleaningModalTitle').textContent = action === 'mark_cleaning' ? 'Mark Cleaning' : 'Mark Available';
            document.getElementById('cleaningStaffName').value = '';
            document.getElementById('cleaningStaffName').focus();
            document.getElementById('cleaningModal').style.display = 'flex';
        }

        function closeCleaningModal() {
            document.getElementById('cleaningModal').style.display = 'none';
            cleaningModalData = { roomId: null, roomNumber: null, action: null };
        }

        // Handle cleaning form submission
        document.getElementById('cleaningForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            form.submit();
        });

        // Transaction Modal Functions
        let transactionModalData = { roomId: null, roomNumber: null, rentalId: null };

        function openTransactionModal(roomId, roomNumber, rentalId) {
            transactionModalData.roomId = roomId;
            transactionModalData.roomNumber = roomNumber;
            transactionModalData.rentalId = rentalId;
            document.getElementById('transactionModalRoom').textContent = roomNumber;
            document.getElementById('transactionModal').style.display = 'flex';
        }

        function closeTransactionModal() {
            document.getElementById('transactionModal').style.display = 'none';
            closeBillModal();
            transactionModalData = { roomId: null, roomNumber: null, rentalId: null };
        }

        // Bill Modal Functions
        function loadBillModal() {
            const rentalId = transactionModalData.rentalId;
            const billContent = document.getElementById('billContent');
            billContent.innerHTML = '<div style="text-align:center;padding:2rem;"><p style="margin-top:1rem;color:#6c757d;">Loading bill...</p></div>';
            document.getElementById('billModal').style.display = 'flex';
            fetchBillContent(rentalId);
        }

        function closeBillModal() {
            document.getElementById('billModal').style.display = 'none';
        }

        async function fetchBillContent(rentalId) {
            try {
                const response = await fetch(`billing.php?rental_id=${rentalId}&ajax=1`);
                const html = await response.text();
                const billContent = document.getElementById('billContent');
                billContent.innerHTML = html;
            } catch (err) {
                const billContent = document.getElementById('billContent');
                billContent.innerHTML = '<div style="text-align:center;padding:2rem;color:#e74c3c;"><i class="fas fa-exclamation-circle" style="font-size:2rem;"></i><p style="margin-top:1rem;">Error loading bill</p></div>';
            }
        }

        // Transaction Functions
        async function createNewTransaction() {
            const rentalId = transactionModalData.rentalId;
            const roomNumber = transactionModalData.roomNumber;
            
            // Show a simple dialog for transaction creation
            const tendered = prompt(`Enter amount tendered for Room ${roomNumber}:\n\nFormat: 0.00`);
            if (tendered === null) return;
            
            const amount = parseFloat(tendered);
            if (isNaN(amount) || amount <= 0) {
                alert('Invalid amount');
                return;
            }
            
            try {
                const fd = new FormData();
                fd.append('rental_id', rentalId);
                fd.append('amount_tendered', amount);
                fd.append('transaction_type', 'PAYMENT');
                
                const response = await fetch('../api/cashier/get_pending_transactions.php', { method: 'POST', body: fd });
                const data = await response.json();
                
                if (data.success) {
                    showToast('Transaction recorded successfully');
                    closeTransactionModal();
                    refreshPendingTx();
                } else {
                    alert('Error: ' + (data.error || 'Could not record transaction'));
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            }
        }

        async function makeOrderTransaction() {
            const rentalId = transactionModalData.rentalId;
            closeTransactionModal();
            // Open the billing page to allow adding orders
            window.location.href = 'billing.php?rental_id=' + rentalId;
        }

        async function showRentalQr() {
            const rentalId = transactionModalData.rentalId;
            const roomNumber = transactionModalData.roomNumber;
            
            if (!rentalId) {
                alert('No rental found');
                return;
            }
            
            try {
                const res = await fetch('../api/rooms/get_rental_qr.php', {
                    method: 'POST',
                    body: new URLSearchParams({ rental_id: rentalId })
                });
                const data = await res.json();
                
                if (data.success) {
                    // Display QR modal
                    const qrSize = 250;
                    const qrImgUrl = `https://api.qrserver.com/v1/create-qr-code/?size=${qrSize}x${qrSize}&data=${encodeURIComponent(data.qr_url)}`;
                    document.getElementById('qrRoomNumber').textContent = roomNumber;
                    document.getElementById('qrOtpCode').textContent = data.otp_code;
                    document.getElementById('qrImage').src = qrImgUrl;
                    document.getElementById('qrModal').style.display = 'flex';
                } else {
                    alert('Error: ' + (data.error || 'Could not retrieve QR code'));
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            }
        }

        async function endRentalFromModal() {
            const roomId = transactionModalData.roomId;
            const roomNumber = transactionModalData.roomNumber;
            
            if (!roomId) {
                alert('No room selected');
                return;
            }
            
            if (!confirm(`Are you sure you want to end the rental for Room ${roomNumber}? The room will be set to Cleaning status.`)) {
                return;
            }
            
            try {
                const fd = new FormData();
                fd.append('room_id', roomId);
                
                const res = await fetch('../api/rooms/end_rental.php', { method: 'POST', body: fd });
                const data = await res.json();
                
                if (data.success) {
                    showToast(`✓ Room ${roomNumber} rental ended successfully!`, 'success');
                    closeTransactionModal();
                    
                    // Immediately update the room card to CLEANING and stop timer
                    const room = currentRoomsData.find(r => r.room_id === roomId);
                    if (room) {
                        // Clear timer interval immediately
                        const existing = timerIntervals.get(roomId);
                        if (existing) {
                            clearInterval(existing);
                            timerIntervals.delete(roomId);
                        }
                        timeoutAlerts.delete(roomId);
                        if (timeoutRooms.has(roomId)) {
                            timeoutRooms.delete(roomId);
                            updateTimeoutBanner();
                        }
                        
                        // Update room status immediately
                        room.status = 'CLEANING';
                        room.rental_id = null;
                        room.started_at = null;
                        room.is_active = 1;
                        
                        // Update the card display
                        updateRoomCard(room);
                    }
                    
                    // Refresh the dashboard from server
                    await checkRoomUpdates();
                } else {
                    alert('Error: ' + (data.error || 'Could not end rental'));
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            }
        }

        // AJAX refresh for orders panel every 10 seconds
        const nextStatusMap = {NEW:'PREPARING',PREPARING:'READY_TO_DELIVER',READY_TO_DELIVER:'DELIVERING',DELIVERING:'DELIVERED'};
        const nextLabelMap = {NEW:'Start Preparing',PREPARING:'Ready to Deliver',READY_TO_DELIVER:'Delivering',DELIVERING:'Mark Delivered'};
        function buildOrdersHtml(orders) {
            if (!orders || orders.length === 0) {
                return '<div class="empty-state"><i class="fas fa-check-circle"></i><p>No active orders right now</p></div>';
            }
            return orders.map(o => {
                const statusLabel = o.status.replace(/_/g,' ');
                const ns = nextStatusMap[o.status];
                const isDeliver = ns === 'DELIVERED';
                const moneyHtml = o.amount_tendered !== null
                    ? ` &nbsp;|&nbsp; Tendered: <span>₱${parseFloat(o.amount_tendered).toFixed(2)}</span> &nbsp;|&nbsp; Change: <span class="change-amount">₱${parseFloat(o.change_amount||0).toFixed(2)}</span>`
                    : '';
                const actionHtml = ns ? `<div class="order-actions" style="margin-top:0.5rem;">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="update_order_status">
                        <input type="hidden" name="order_id" value="${o.order_id}">
                        <input type="hidden" name="status" value="${ns}">
                        <button type="submit" class="btn-status${isDeliver?' deliver':''}">
                            <i class="fas fa-arrow-right"></i> ${nextLabelMap[o.status]}
                        </button>
                    </form></div>` : '';
                // MySQL returns 'YYYY-MM-DD HH:MM:SS'; replace space with 'T' for ISO 8601 compatibility
                const time = new Date(o.ordered_at.replace(' ','T'));
                const timeStr = time.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'});
                return `<div class="order-card">
                    <div class="order-top">
                        <span class="order-room"><i class="fas fa-door-open"></i> Room ${o.room_number}</span>
                        <span class="order-status-badge status-${o.status}">${statusLabel}</span>
                    </div>
                    <div class="order-items">${o.items||''}</div>
                    <div class="order-money">Total: <span>₱${parseFloat(o.total).toFixed(2)}</span>${moneyHtml}</div>
                    <div class="meta-info"><i class="fas fa-clock"></i> ${timeStr} &nbsp; Order #${o.order_id}</div>
                    ${actionHtml}
                </div>`;
            }).join('');
        }
        function refreshOrders() {
            fetch('../api/orders/get_pending_orders.php')
                .then(r => r.json())
                .then(data => {
                    if (data && data.orders !== undefined) {
                        const panel = document.getElementById('ordersPanel');
                        if (panel) panel.innerHTML = buildOrdersHtml(data.orders);
                    }
                })
                .catch(() => {});
        }
        setInterval(refreshOrders, 10000);

        let startRentalData = { roomId: null, roomNumber: null, pricePerHour: null, priceP30Min: null, selectedDuration: 60 };

        let rentalSelectedPaymentMethod = 'CASH';

        function selectRentalPayment(btn) {
            document.querySelectorAll('.rental-payment-btn').forEach(b => {
                b.classList.remove('selected');
                if (b.classList.contains('selected')) {
                    b.style.borderColor = '#e9ecef';
                    b.style.background = 'white';
                    b.style.color = '#495057';
                } else {
                    b.style.borderColor = '#e9ecef';
                    b.style.background = 'white';
                    b.style.color = '#495057';
                }
            });
            btn.classList.add('selected');
            btn.style.borderColor = '#f2a20a';
            btn.style.background = '#f2a20a';
            btn.style.color = 'white';
            rentalSelectedPaymentMethod = btn.dataset.method;
            
            // Show/hide GCash reference field
            const gcashField = document.getElementById('gcashReferenceField');
            const gcashInput = document.getElementById('gcashReferenceNumber');
            if (rentalSelectedPaymentMethod === 'GCASH') {
                gcashField.style.display = 'block';
                gcashInput.required = true;
            } else {
                gcashField.style.display = 'none';
                gcashInput.required = false;
                gcashInput.value = '';
            }
        }

        function startRental(roomId, roomNumber) {
            // Get room pricing from currentRoomsData (loaded on page)
            roomId = parseInt(roomId); // Ensure roomId is a number
            const roomData = currentRoomsData.find(r => parseInt(r.room_id) === roomId);
            if (!roomData) {
                alert('Room data not found for room ' + roomNumber);
                console.error('Looking for roomId:', roomId, 'in', currentRoomsData);
                return;
            }
            
            startRentalData.roomId = roomId;
            startRentalData.roomNumber = roomNumber;
            startRentalData.pricePerHour = parseFloat(roomData.price_per_hour);
            startRentalData.priceP30Min = parseFloat(roomData.price_per_30min) || (startRentalData.pricePerHour / 2);
            startRentalData.selectedDuration = 60; // Default to 1 hour
            
            document.getElementById('startRentalRoom').textContent = roomNumber;
            generateDurationOptions();
            updateCostDisplay();
            document.getElementById('startRentalModal').style.display = 'flex';
        }

        function generateDurationOptions() {
            const options = document.getElementById('durationOptions');
            const durations = [
                { minutes: 30, label: '30 Minutes' },
                { minutes: 60, label: '1 Hour' },
                { minutes: 120, label: '2 Hours' },
                { minutes: 180, label: '3 Hours' }
            ];
            
            options.innerHTML = durations.map(d => `
                <button type="button" onclick="selectDuration(${d.minutes})" 
                        style="padding:0.75rem;border:2px solid #dee2e6;border-radius:6px;background:white;cursor:pointer;font-size:0.875rem;font-weight:600;transition:all 0.2s;" 
                        id="duration-${d.minutes}"
                        class="duration-btn">
                    ${d.label}
                </button>
            `).join('');
            
            // Highlight default (1 hour)
            document.getElementById('duration-60').style.borderColor = '#f2a20a';
            document.getElementById('duration-60').style.background = '#fff9e6';
        }

        function selectDuration(minutes) {
            startRentalData.selectedDuration = minutes;
            
            // Update button styles
            document.querySelectorAll('.duration-btn').forEach(btn => {
                btn.style.borderColor = '#dee2e6';
                btn.style.background = 'white';
            });
            document.getElementById('duration-' + minutes).style.borderColor = '#f2a20a';
            document.getElementById('duration-' + minutes).style.background = '#fff9e6';
            
            updateCostDisplay();
        }

        function updateCostDisplay() {
            const cost = (startRentalData.priceP30Min * (startRentalData.selectedDuration / 30)).toFixed(2);
            document.getElementById('rentalCost').textContent = cost;
            document.getElementById('paymentAmount').value = '';
            document.getElementById('changeDisplay').style.display = 'none';
            document.getElementById('changeAmount').textContent = '0.00';
        }

        document.getElementById('paymentAmount').addEventListener('input', function() {
            const cost = parseFloat(document.getElementById('rentalCost').textContent);
            const payment = parseFloat(this.value) || 0;
            const change = (payment - cost).toFixed(2);
            
            if (payment >= cost) {
                document.getElementById('changeDisplay').style.display = 'block';
                document.getElementById('changeAmount').textContent = change;
            } else {
                document.getElementById('changeDisplay').style.display = 'none';
            }
        });

        function closeStartRentalModal() {
            document.getElementById('startRentalModal').style.display = 'none';
            document.getElementById('gcashReferenceNumber').value = '';
            document.getElementById('gcashReferenceField').style.display = 'none';
            startRentalData = { roomId: null, roomNumber: null, pricePerHour: null, priceP30Min: null, selectedDuration: 60 };
        }

        async function confirmStartRental() {
            const cost = parseFloat(document.getElementById('rentalCost').textContent);
            const payment = parseFloat(document.getElementById('paymentAmount').value) || 0;
            
            if (payment < cost) {
                alert(`Insufficient payment. Total cost is ₱${cost.toFixed(2)}, but received ₱${payment.toFixed(2)}`);
                return;
            }
            
            // Validate GCash reference number if payment method is GCASH
            if (rentalSelectedPaymentMethod === 'GCASH') {
                const refNum = document.getElementById('gcashReferenceNumber').value.trim();
                if (!refNum) {
                    alert('Please enter the GCash reference number');
                    document.getElementById('gcashReferenceNumber').focus();
                    return;
                }
            }
            
            const btn = event.target;
            btn.textContent = 'Processing...';
            btn.disabled = true;            
            
            try {
                const fd = new FormData();
                fd.append('room_id', startRentalData.roomId);
                fd.append('minutes', startRentalData.selectedDuration);
                fd.append('payment_method', rentalSelectedPaymentMethod);
                
                // Add reference number for GCASH
                if (rentalSelectedPaymentMethod === 'GCASH') {
                    fd.append('reference_number', document.getElementById('gcashReferenceNumber').value.trim());
                }
                
                const res = await fetch('../api/rooms/start_rental.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    closeStartRentalModal();
                    showQrModal(data);
                } else {
                    alert('Error: ' + (data.error || 'Could not start rental'));
                    btn.textContent = 'Proceed to Payment';
                    btn.disabled = false;
                }
            } catch (err) {
                alert('Network error: ' + err.message);
                btn.textContent = 'Proceed to Payment';
                btn.disabled = false;
            }
        }

        function showQrModal(data) {
            const qrSize = 250;
            const qrImgUrl = `https://api.qrserver.com/v1/create-qr-code/?size=${qrSize}x${qrSize}&data=${encodeURIComponent(data.qr_url)}`;
            const modal = document.getElementById('qrModal');
            document.getElementById('qrRoomNumber').textContent = data.room_number;
            document.getElementById('qrOtpCode').textContent = data.otp_code;
            document.getElementById('qrImage').src = qrImgUrl;
            modal.style.display = 'flex';
        }

        function closeQrModal() {
            document.getElementById('qrModal').style.display = 'none';
            showToast('Rental started successfully ✓');
            location.reload();
        }

        // Show toast for POST action feedback
        <?php if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['toast'])): ?>
        document.addEventListener('DOMContentLoaded', () => {
            showToast('<?= htmlspecialchars($_GET['toast']) ?> ✓');
        });
        <?php endif; ?>

        // Pending Room Transactions Panel
        function buildPendingTxHtml(transactions) {
            if (!transactions || transactions.length === 0) {
                return '<div class="empty-state"><i class="fas fa-check-circle"></i><p>No pending payments</p></div>';
            }
            const html = '<div class="tx-grid">' + transactions.map(tx => {
                const isGcash = tx.payment_method === 'GCASH';
                const typeLabel = tx.transaction_type === 'ORDER' ? '<i class="fas fa-utensils"></i> Order' : '<i class="fas fa-clock"></i> Extension';
                const gcashInfo = isGcash ? `<div class="tx-gcash-info"><i class="fas fa-mobile-alt"></i> <strong>${escHtml(tx.gcash_account_name||'')}</strong> &nbsp;|&nbsp; Ref: ${escHtml(tx.gcash_reference_number||'')}</div>` : '';
                const actions = isGcash
                    ? `<button class="btn-tx btn-approve" onclick="updateTx(${tx.transaction_id},'approve',this)"><i class="fas fa-check"></i> Approve</button>
                       <button class="btn-tx btn-reject"  onclick="updateTx(${tx.transaction_id},'reject',this)"><i class="fas fa-times"></i> Reject</button>`
                    : `<button class="btn-tx btn-collect" onclick="updateTx(${tx.transaction_id},'mark_collected',this)"><i class="fas fa-hand-holding-usd"></i> Mark Collected</button>`;
                return `<div class="tx-card ${isGcash ? 'gcash' : 'cash'}">
                    <div class="tx-top">
                        <span class="tx-room"><i class="fas fa-door-open"></i> Room ${tx.room_number}</span>
                        <span class="tx-badge ${isGcash ? 'gcash-badge' : 'cash-badge'}">${tx.payment_method}</span>
                    </div>
                    <div class="tx-detail">${typeLabel} &nbsp;•&nbsp; <strong>₱${parseFloat(tx.amount).toFixed(2)}</strong></div>
                    ${gcashInfo}
                    <input class="tx-notes-input" type="text" id="notes_${tx.transaction_id}" placeholder="Cashier notes (optional)">
                    <div class="tx-actions" style="margin-top:0.4rem;">${actions}</div>
                </div>`;
            }).join('') + '</div>';
            return html;
        }

        function escHtml(str) {
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        async function updateTx(txId, action, btn) {
            btn.disabled = true;
            const origText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            const notes = document.getElementById('notes_' + txId);
            const fd = new FormData();
            fd.append('transaction_id', txId);
            fd.append('action', action);
            fd.append('cashier_notes', notes ? notes.value : '');
            try {
                const res = await fetch('../api/cashier/update_transaction.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    showToast('Transaction updated: ' + data.new_status);
                    refreshPendingTx();
                } else {
                    alert('Error: ' + (data.error || 'Unknown'));
                    btn.disabled = false;
                    btn.innerHTML = origText;
                }
            } catch (err) {
                alert('Network error: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = origText;
            }
        }

        function refreshPendingTx() {
            fetch('../api/cashier/get_pending_transactions.php')
                .then(r => r.json())
                .then(data => {
                    if (data && data.transactions !== undefined) {
                        const panel = document.getElementById('pendingTxPanel');
                        if (panel) panel.innerHTML = buildPendingTxHtml(data.transactions);
                    }
                })
                .catch(() => {});
        }

        refreshPendingTx();
        setInterval(refreshPendingTx, 2000); // Real-time: poll every 2 seconds
        
        // Room search and filter
        function filterRooms() {
            const searchValue = document.getElementById('roomSearch').value.toLowerCase();
            const categoryValue = document.getElementById('roomCategory').value;
            const showCleaning = document.getElementById('showCleaning').checked;
            const showAvailable = document.getElementById('showAvailable').checked;
            const showNotAvailable = document.getElementById('showNotAvailable').checked;
            const showTimeout = document.getElementById('showTimeout').checked;
            const rooms = document.querySelectorAll('#roomGrid .room-card');
            
            rooms.forEach(room => {
                const roomNumber = room.getAttribute('data-room-number').toLowerCase();
                const roomCategory = room.getAttribute('data-room-category');
                const roomFilter = room.getAttribute('data-room-filter');
                
                const matchesSearch = roomNumber.includes(searchValue);
                const matchesCategory = !categoryValue || roomCategory === categoryValue;
                
                // Check filter tags
                let matchesFilter = true;
                if (roomFilter === 'cleaning' && !showCleaning) matchesFilter = false;
                if (roomFilter === 'available' && !showAvailable) matchesFilter = false;
                if (roomFilter === 'not-available' && !showNotAvailable) matchesFilter = false;
                if (roomFilter === 'timeout' && !showTimeout) matchesFilter = false;
                
                room.style.display = (matchesSearch && matchesCategory && matchesFilter) ? '' : 'none';
            });
        }
        
        document.getElementById('roomSearch').addEventListener('input', filterRooms);
        document.getElementById('roomCategory').addEventListener('change', filterRooms);
        document.getElementById('showCleaning').addEventListener('change', filterRooms);
        document.getElementById('showAvailable').addEventListener('change', filterRooms);
        document.getElementById('showNotAvailable').addEventListener('change', filterRooms);
        document.getElementById('showTimeout').addEventListener('change', filterRooms);
        
        // Real-time room updates
        let currentRoomsData = <?= json_encode($rooms) ?>;
        const timerIntervals = new Map();
        const timeoutAlerts = new Set(); // Track which rooms have been alerted
        const timeoutRooms = new Map(); // Track currently timed-out rooms for permanent display
        
        function startTimer(room) {
            const startedAt = room.started_at;
            const totalMinutes = room.total_minutes;
            
            console.log('startTimer called:', {room_id: room.room_id, room_number: room.room_number, startedAt, totalMinutes});
            
            if (!startedAt || !totalMinutes) {
                console.log('Missing startedAt or totalMinutes');
                return;
            }
            
            // Find the card and timeEl
            const cards = document.querySelectorAll('.room-card');
            let timeEl = null;
            let card = null;
            
            for (const c of cards) {
                const roomNum = c.querySelector('.room-num');
                if (roomNum && roomNum.textContent == room.room_number) {
                    card = c;
                    timeEl = c.querySelector('.room-time');
                    break;
                }
            }
            
            if (!timeEl) {
                console.log('No timeEl found for room', room.room_number);
                return;
            }
            
            console.log('Found timeEl, starting timer');
            
            // Clear existing interval
            const existing = timerIntervals.get(room.room_id);
            if (existing) {
                clearInterval(existing);
                timerIntervals.delete(room.room_id);
            }
            
            function updateTimer() {
                try {
                    const t = startedAt.split(/[- :]/);
                    console.log('Parsed time parts:', t);
                    const startDate = new Date(t[0], t[1]-1, t[2], t[3], t[4], t[5]);
                    const now = new Date();
                    const elapsedMs = now - startDate;
                    const elapsedSeconds = Math.floor(elapsedMs / 1000);
                    const safeElapsed = elapsedSeconds < 0 ? 0 : elapsedSeconds;
                    const totalSeconds = totalMinutes * 60;
                    const remainingSeconds = Math.max(0, totalSeconds - safeElapsed);
                    
                    const hours = Math.floor(safeElapsed / 3600);
                    const minutes = Math.floor((safeElapsed % 3600) / 60);
                    const seconds = safeElapsed % 60;
                    
                    const display = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
                    console.log('Timer update:', {elapsedSeconds, safeElapsed, display, startDate, now});
                    
                    if (remainingSeconds <= 0) {
                        timeEl.textContent = 'TIME OUT';
                        timeEl.style.background = 'rgba(231, 76, 60, 0.2)';
                        timeEl.style.color = '#e74c3c';
                        
                        if (!timeoutAlerts.has(room.room_id)) {
                            timeoutAlerts.add(room.room_id);
                            showToast(`⏰ Room ${room.room_number} time is OUT!`, 'timeout');
                        }
                        
                        // Add to permanent timeout display
                        if (!timeoutRooms.has(room.room_id)) {
                            timeoutRooms.set(room.room_id, {room_number: room.room_number, room_id: room.room_id});
                            updateTimeoutBanner();
                        }
                    } else if (remainingSeconds <= 300) {
                        if (!timeoutAlerts.has(room.room_id)) {
                            showToast(`⏰ Room ${room.room_number} only 5 minutes left!`, 'warning');
                            timeoutAlerts.add(room.room_id);
                        }
                        timeEl.style.background = 'rgba(255, 193, 7, 0.3)';
                        timeEl.style.color = '#ff9800';
                        timeEl.textContent = display;
                    } else {
                        timeEl.style.background = 'rgba(245, 197, 66, 0.1)';
                        timeEl.style.color = '#f2a20a';
                        timeEl.textContent = display;
                        
                        // Remove from timeout display if no longer timed out
                        if (timeoutRooms.has(room.room_id)) {
                            timeoutRooms.delete(room.room_id);
                            updateTimeoutBanner();
                        }
                    }
                } catch (e) {
                    console.error('Timer update error:', e, 'startedAt:', startedAt);
                }
            }
            
            updateTimer(); // Run once immediately
            const interval = setInterval(updateTimer, 1000);
            timerIntervals.set(room.room_id, interval);
            console.log('Timer started for room', room.room_id);
        }

        function startAllTimers() {
            console.log('startAllTimers called, checking', currentRoomsData.length, 'rooms');
            currentRoomsData.forEach(room => {
                if (room.status === 'OCCUPIED' && room.started_at && room.total_minutes) {
                    console.log('Starting timer for room', room.room_number);
                    startTimer(room);
                }
            });
        }
        
        async function checkRoomUpdates() {
            try {
                const res = await fetch('../api/rooms/rooms_api.php');
                const data = await res.json();
                if (data.success && data.rooms) {
                    updateRoomsIfChanged(data.rooms);
                }
            } catch (err) {
                console.error('Room update check failed:', err);
            }
        }
        
        function updateRoomsIfChanged(newRooms) {
            let hasChanges = false;
            
            newRooms.forEach(newRoom => {
                const oldRoom = currentRoomsData.find(r => r.room_id === newRoom.room_id);
                if (!oldRoom || oldRoom.status !== newRoom.status || oldRoom.rental_id !== newRoom.rental_id || oldRoom.started_at !== newRoom.started_at || oldRoom.is_active !== newRoom.is_active) {
                    hasChanges = true;
                    updateRoomCard(newRoom);
                }
            });
            
            if (hasChanges) {
                currentRoomsData = newRooms;
            }
        }
        
        function updateRoomCard(room) {
            const cards = document.querySelectorAll('.room-card');
            cards.forEach(card => {
                const roomNum = card.querySelector('.room-num');
                if (roomNum && roomNum.textContent == room.room_number) {
                    card.className = 'room-card ' + room.status.toLowerCase() + (room.is_active == 0 ? ' inactive' : '');
                    
                    // Get or create status text element
                    let statusEl = card.querySelector('.room-status-text');
                    if (!statusEl) {
                        statusEl = document.createElement('div');
                        statusEl.className = 'room-status-text';
                        card.appendChild(statusEl);
                    }
                    
                    // Format status text with appropriate emoji/formatting
                    if (room.status === 'CLEANING') {
                        statusEl.textContent = '🧹 CLEANING';
                    } else {
                        statusEl.textContent = room.status + (room.is_active == 0 ? ' • INACTIVE' : '');
                    }
                    
                    // Update or remove "Not Available" text
                    let notAvailableEl = card.querySelector('div[style*="color:#999;font-weight:600"]');
                    if (room.is_active == 0) {
                        if (!notAvailableEl) {
                            notAvailableEl = document.createElement('div');
                            notAvailableEl.style.padding = '0.3rem';
                            notAvailableEl.style.textAlign = 'center';
                            notAvailableEl.style.fontSize = '0.75rem';
                            notAvailableEl.style.color = '#999';
                            notAvailableEl.style.fontWeight = '600';
                            notAvailableEl.textContent = 'Not Available';
                            card.appendChild(notAvailableEl);
                        }
                    } else if (notAvailableEl) {
                        notAvailableEl.remove();
                    }
                    
                    // Update onclick handler based on room status and active state
                    card.onclick = null;
                    if (room.is_active == 1) {
                        if (room.status === 'OCCUPIED' && room.rental_id) {
                            card.onclick = function() { 
                                openTransactionModal(room.room_id, room.room_number, room.rental_id); 
                            };
                        } else if (room.status === 'CLEANING') {
                            card.onclick = function() { 
                                openCleaningModal(room.room_id, room.room_number, 'mark_available'); 
                            };
                        } else if (room.status === 'AVAILABLE') {
                            card.onclick = function() { 
                                startRental(room.room_id, room.room_number); 
                            };
                        }
                    }
                    
                    // Update or create timer for occupied rooms
                    let timeEl = card.querySelector('.room-time');
                    if (room.status === 'OCCUPIED' && room.started_at && room.total_minutes && room.is_active == 1) {
                        if (!timeEl) {
                            const newTimeEl = document.createElement('div');
                            newTimeEl.className = 'room-time';
                            card.insertBefore(newTimeEl, card.lastChild);
                            timeEl = newTimeEl;
                        }
                        // Clear any previous alerts for this room when rental changes
                        if (room.started_at && !currentRoomsData.find(r => r.room_id === room.room_id && r.started_at === room.started_at)) {
                            timeoutAlerts.delete(room.room_id);
                        }
                        startTimer(room);
                    } else if (timeEl) {
                        // Clear timer and remove element
                        const existing = timerIntervals.get(room.room_id);
                        if (existing) {
                            clearInterval(existing);
                            timerIntervals.delete(room.room_id);
                        }
                        timeoutAlerts.delete(room.room_id);
                        // Room no longer occupied, remove from timeout tracking
                        if (timeoutRooms.has(room.room_id)) {
                            timeoutRooms.delete(room.room_id);
                            updateTimeoutBanner();
                        }
                        timeEl.remove();
                    }
                }
            });
        }
        
        function generateRoomActions(room) {
            // Buttons are no longer used - room cards are fully clickable
            return '';
        }
        
        // Poll every 1 second for real-time updates
        setInterval(checkRoomUpdates, 1000);
        
        // Start timers for all occupied rooms on page load
        document.addEventListener('DOMContentLoaded', startAllTimers);
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', startAllTimers);
        } else {
            startAllTimers();
        }
    </script>
</body>
</html>