<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 3) {
    header('Location: ../index.php');
    exit;
}

$billId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($billId <= 0) {
    header('Location: transactions.php');
    exit;
}

// Fetch bill details
$query = "
    SELECT 
        b.bill_id,
        b.rental_id,
        b.total_room_cost,
        b.total_orders_cost,
        b.grand_total,
        b.is_paid,
        b.created_at,
        rm.room_number,
        rm.room_id,
        rt.type_name,
        rt.price_per_hour,
        r.started_at,
        r.ended_at,
        TIMESTAMPDIFF(MINUTE, r.started_at, r.ended_at) as rental_minutes,
        p.payment_id,
        p.amount_paid,
        p.payment_method,
        p.reference_number,
        p.paid_at
    FROM bills b
    LEFT JOIN rentals r ON b.rental_id = r.rental_id
    LEFT JOIN rooms rm ON r.room_id = rm.room_id
    LEFT JOIN room_types rt ON rm.room_type_id = rt.room_type_id
    LEFT JOIN payments p ON b.bill_id = p.bill_id
    WHERE b.bill_id = ?
    LIMIT 1
";

$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $billId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: transactions.php');
    exit;
}

$bill = $result->fetch_assoc();
$stmt->close();

// Fetch order items for this bill
$orderQuery = "
    SELECT 
        oi.order_item_id,
        oi.quantity,
        oi.price,
        (oi.quantity * oi.price) as subtotal,
        p.product_name,
        p.category
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE o.rental_id = ?
    ORDER BY oi.order_item_id DESC
";

$orderStmt = $mysqli->prepare($orderQuery);
$orderStmt->bind_param('i', $bill['rental_id']);
$orderStmt->execute();
$orderItems = $orderStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$orderStmt->close();

// Calculate rental duration
$rentalHours = floor($bill['rental_minutes'] / 60);
$rentalMinutes = $bill['rental_minutes'] % 60;

$cashierName = htmlspecialchars($_SESSION['display_name'] ?: $_SESSION['username']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Bill Details #<?= $billId ?> — Wannabees KTV</title>
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
            background: #f8f9fa;
            color: #212529;
            padding: 1rem;
        }

        header {
            background: white;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            margin-bottom: 2rem;
            border-radius: 8px;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #212529;
        }

        .back-link {
            color: #f2a20a;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .back-link:hover {
            color: #d4a71d;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .bill-card {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            margin-bottom: 2rem;
        }

        .bill-header {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .bill-header-item h3 {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #666;
            margin-bottom: 0.5rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .bill-header-item p {
            font-size: 1.5rem;
            font-weight: 700;
            color: #212529;
        }

        .status-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
        }

        .status-unpaid {
            background: #f8d7da;
            color: #721c24;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #212529;
            margin-bottom: 1rem;
            margin-top: 2rem;
            border-bottom: 2px solid #f2a20a;
            padding-bottom: 0.5rem;
        }

        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .detail-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
        }

        .detail-label {
            font-size: 0.875rem;
            color: #666;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .detail-value {
            font-size: 1rem;
            color: #212529;
            font-weight: 500;
        }

        .table-container {
            overflow-x: auto;
            margin-bottom: 2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f2a20a;
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .amount {
            font-weight: 700;
            color: #f2a20a;
            text-align: right;
        }

        .total-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 6px;
            margin-bottom: 2rem;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .total-row.final {
            font-size: 1.25rem;
            font-weight: 700;
            color: #212529;
            padding-top: 0.75rem;
            border-top: 2px solid #ddd;
        }

        .total-row.final .amount {
            color: #28a745;
            font-size: 1.5rem;
        }

        .label {
            font-weight: 600;
            color: #212529;
        }

        .actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #f0f0f0;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: #f2a20a;
            color: white;
        }

        .btn-primary:hover {
            background: #d4a71d;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 197, 66, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .empty-orders {
            text-align: center;
            padding: 2rem;
            color: #666;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .receipt-note {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: #1565c0;
        }

        @media print {
            body {
                background: white;
            }
            .actions, .receipt-note {
                display: none;
            }
            .bill-card {
                box-shadow: none;
                page-break-after: avoid;
            }
        }

        @media (max-width: 768px) {
            .bill-header {
                grid-template-columns: 1fr;
            }
            .details-grid {
                grid-template-columns: 1fr;
            }
            .bill-card {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <a href="transactions.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Transactions
            </a>
            <div class="header-title">Bill #<?= $billId ?></div>
        </div>
    </header>

    <div class="container">
        <div class="bill-card">
            <!-- Receipt Note -->
            <div class="receipt-note">
                <i class="fas fa-info-circle"></i> Keep this receipt for your records. For any questions or concerns, please contact the manager.
            </div>

            <!-- Bill Header Info -->
            <div class="bill-header">
                <div class="bill-header-item">
                    <h3>Bill ID</h3>
                    <p>#<?= $billId ?></p>
                </div>
                <div class="bill-header-item">
                    <h3>Status</h3>
                    <p><span class="status-badge <?= $bill['is_paid'] ? 'status-paid' : 'status-unpaid' ?>">
                        <?= $bill['is_paid'] ? 'PAID' : 'UNPAID' ?>
                    </span></p>
                </div>
                <div class="bill-header-item">
                    <h3>Date</h3>
                    <p><?= date('M d, Y', strtotime($bill['created_at'])) ?></p>
                </div>
            </div>

            <!-- Room Details -->
            <div class="section-title">
                <i class="fas fa-door-open"></i> Room Details
            </div>
            <div class="details-grid">
                <div class="detail-item">
                    <div class="detail-label">Room Number</div>
                    <div class="detail-value">Room <?= htmlspecialchars($bill['room_number']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Room Type</div>
                    <div class="detail-value"><?= htmlspecialchars($bill['type_name']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Start Time</div>
                    <div class="detail-value"><?= date('M d, Y h:i A', strtotime($bill['started_at'])) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">End Time</div>
                    <div class="detail-value"><?= date('M d, Y h:i A', strtotime($bill['ended_at'])) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Duration</div>
                    <div class="detail-value"><?= $rentalHours ?>h <?= $rentalMinutes ?>m</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Hourly Rate</div>
                    <div class="detail-value">₱<?= number_format($bill['price_per_hour'], 2) ?>/hr</div>
                </div>
            </div>

            <!-- Room Charges -->
            <div class="section-title">
                <i class="fas fa-receipt"></i> Room Charges
            </div>
            <div class="total-section">
                <div class="total-row">
                    <span class="label">Room Rental Cost:</span>
                    <span class="amount">₱<?= number_format($bill['total_room_cost'], 2) ?></span>
                </div>
            </div>

            <!-- Order Items -->
            <div class="section-title">
                <i class="fas fa-shopping-bag"></i> Order Items
            </div>
            <?php if (count($orderItems) > 0): ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th style="text-align: right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td><?= htmlspecialchars($item['category']) ?></td>
                                <td style="text-align: center;"><?= $item['quantity'] ?></td>
                                <td class="amount">₱<?= number_format($item['price'], 2) ?></td>
                                <td class="amount">₱<?= number_format($item['subtotal'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-orders">
                    <i class="fas fa-inbox"></i>
                    <p>No items ordered for this rental</p>
                </div>
            <?php endif; ?>

            <!-- Summary -->
            <div class="section-title">
                <i class="fas fa-calculator"></i> Summary
            </div>
            <div class="total-section">
                <div class="total-row">
                    <span class="label">Room Charges:</span>
                    <span class="amount">₱<?= number_format($bill['total_room_cost'], 2) ?></span>
                </div>
                <div class="total-row">
                    <span class="label">Orders Total:</span>
                    <span class="amount">₱<?= number_format($bill['total_orders_cost'], 2) ?></span>
                </div>
                <div class="total-row final">
                    <span class="label">Grand Total:</span>
                    <span class="amount">₱<?= number_format($bill['grand_total'], 2) ?></span>
                </div>
            </div>

            <!-- Payment Info -->
            <?php if ($bill['payment_id']): ?>
            <div class="section-title">
                <i class="fas fa-credit-card"></i> Payment Information
            </div>
            <div class="details-grid">
                <div class="detail-item">
                    <div class="detail-label">Payment Method</div>
                    <div class="detail-value"><?= htmlspecialchars($bill['payment_method']) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Reference Number</div>
                    <div class="detail-value">
                        <?php if ($bill['payment_method'] === 'GCASH' && !empty($bill['reference_number'])): ?>
                            <?= htmlspecialchars($bill['reference_number']) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Amount Paid</div>
                    <div class="detail-value">₱<?= number_format($bill['amount_paid'], 2) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Paid At</div>
                    <div class="detail-value"><?= date('M d, Y h:i A', strtotime($bill['paid_at'])) ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Change</div>
                    <div class="detail-value">₱<?= number_format($bill['amount_paid'] - $bill['grand_total'], 2) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="actions">
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <a href="transactions.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Transactions
                </a>
            </div>
        </div>
    </div>

    <script>
        // Prevent accidental page navigation
        window.addEventListener('beforeunload', function(e) {
            if (document.activeElement.tagName === 'A' && document.activeElement.target !== '_blank') {
                // Allow navigation
                return;
            }
        });
    </script>
</body>
</html>
