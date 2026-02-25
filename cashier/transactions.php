<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 3) {
    header('Location: ../index.php');
    exit;
}

// Get filters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$paymentMethod = $_GET['payment_method'] ?? 'all';
$searchBill = $_GET['search'] ?? '';

// Build query
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
        rt.type_name,
        p.amount_paid,
        p.payment_method,
        p.reference_number,
        p.paid_at,
        r.started_at,
        r.ended_at
    FROM bills b
    LEFT JOIN rentals r ON b.rental_id = r.rental_id
    LEFT JOIN rooms rm ON r.room_id = rm.room_id
    LEFT JOIN room_types rt ON rm.room_type_id = rt.room_type_id
    LEFT JOIN payments p ON b.bill_id = p.bill_id
    WHERE DATE(b.created_at) BETWEEN ? AND ?
";

$params = [$startDate, $endDate];
$types = 'ss';

if ($paymentMethod !== 'all') {
    $query .= " AND p.payment_method = ?";
    $params[] = $paymentMethod;
    $types .= 's';
}

if (!empty($searchBill)) {
    $query .= " AND (b.bill_id = ? OR rm.room_number = ?)";
    $params[] = intval($searchBill);
    $params[] = intval($searchBill);
    $types .= 'ii';
}

$query .= " ORDER BY b.created_at DESC";

$stmt = $mysqli->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Summary stats
$totalRevenue = array_sum(array_column($transactions, 'grand_total'));
$totalPaid = array_sum(array_column($transactions, 'amount_paid'));
$totalOrders = array_sum(array_column($transactions, 'total_orders_cost'));

$cashierName = htmlspecialchars($_SESSION['display_name'] ?: $_SESSION['username']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Transaction History</title>
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
      min-height: 100vh;
    }
    
    header {
      background: #ffffff;
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
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
    }
    
    .header-left {
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    
    .header-left img {
      height: 40px;
    }
    
    .header-title {
      font-size: 1.125rem;
      font-weight: 600;
      color: #212529;
    }

    .header-subtitle {
      font-size: 0.875rem;
      color: #6c757d;
    }
    
    .header-nav {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }
    
    .mobile-nav-toggle {
      display: none;
      background: none;
      border: none;
      font-size: 1.25rem;
      cursor: pointer;
      color: #212529;
    }

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
      transition: all 0.2s;
    }
    .nav-btn:hover { background: #f8f9fa; border-color: #adb5bd; }
    .nav-btn.active { background: #f2a20a; color: white; border-color: #f2a20a; }
    .nav-btn.logout { border-color: #e74c3c; color: #e74c3c; }
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
      max-width: 1400px;
      margin: 0 auto;
      padding: 2rem 1.5rem;
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    
    .stat-card {
      background: white;
      padding: 1.5rem;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
      text-align: center;
    }
    
    .stat-icon {
      font-size: 2rem;
      color: #f2a20a;
      margin-bottom: 0.5rem;
    }
    
    .stat-value {
      font-size: 1.75rem;
      font-weight: 700;
      color: #212529;
    }
    
    .stat-label {
      font-size: 0.875rem;
      color: #6c757d;
      margin-top: 0.5rem;
    }
    
    .filter-card {
      background: white;
      padding: 1.5rem;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
      margin-bottom: 2rem;
    }
    
    .filter-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 1rem;
      margin-bottom: 1rem;
    }
    
    .filter-group {
      display: flex;
      flex-direction: column;
    }
    
    .filter-label {
      font-size: 0.875rem;
      font-weight: 600;
      color: #212529;
      margin-bottom: 0.5rem;
    }
    
    .filter-input,
    .filter-select {
      padding: 0.5rem;
      border: 1px solid #dee2e6;
      border-radius: 6px;
      font-size: 0.875rem;
      font-family: inherit;
    }
    
    .filter-input:focus,
    .filter-select:focus {
      outline: none;
      border-color: #f2a20a;
      box-shadow: 0 0 0 3px rgba(242, 162, 10, 0.1);
    }
    
    .filter-actions {
      display: flex;
      gap: 0.5rem;
    }
    
    .btn-filter {
      flex: 1;
      padding: 0.5rem 1rem;
      background: #f2a20a;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
    }
    
    .btn-filter:hover {
      background: #f2a20a;
    }
    
    .btn-reset {
      padding: 0.5rem 1rem;
      background: #e9ecef;
      color: #212529;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.2s;
    }
    
    .btn-reset:hover {
      background: #dee2e6;
    }
    
    .transactions-table {
      background: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
    }
    
    thead {
      background: #f8f9fa;
      color: #212529;
      border-bottom: 2px solid #e9ecef;
    }
    
    th {
      padding: 1rem;
      text-align: left;
      font-weight: 600;
      font-size: 0.875rem;
      letter-spacing: 0.5px;
    }
    
    td {
      padding: 1rem;
      border-bottom: 1px solid #e9ecef;
      font-size: 0.875rem;
    }
    
    tbody tr:hover {
      background: #fafbfc;
    }
    
    tbody tr:last-child td {
      border-bottom: none;
    }
    
    .bill-id {
      font-weight: 600;
      color: #000000;
    }
    
    .room-info {
      font-weight: 600;
      color: #212529;
    }
    
    .badge {
      display: inline-block;
      padding: 0.4rem 0.8rem;
      border-radius: 20px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
    }
    
    .badge-paid {
      background: #d4edda;
      color: #155724;
    }
    
    .badge-unpaid {
      background: #f8d7da;
      color: #721c24;
    }
    
    .badge-cash {
      background: #cfe2ff;
      color: #084298;
    }
    
    .badge-gcash {
      background: #e7d4f5;
      color: #5a189a;
    }
    
    .amount {
      font-weight: 700;
      color: #666;
    }
    
    .empty-state {
      text-align: center;
      padding: 3rem;
      color: #666;
    }
    
    .empty-state i {
      font-size: 3rem;
      color: #ddd;
      margin-bottom: 1rem;
    }
    
    .row-details {
      cursor: pointer;
      padding: 0.25rem 0.5rem;
      border-radius: 4px;
      color: #000000;
      transition: all 0.2s;
    }
    
    .row-details:hover {
      background: #f8f9fa;
    }
    
    @media (max-width: 768px) {
      table {
        font-size: 0.75rem;
      }
      
      th, td {
        padding: 0.75rem 0.5rem;
      }
      
      .filter-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
  <script>
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
  </script>
</head>
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
        <a href="dashboard.php" class="nav-btn"><i class="fas fa-home"></i> <span>Dashboard</span></a>
        <a href="transactions.php" class="nav-btn active"><i class="fas fa-history"></i> <span>Transactions</span></a>
        <a href="sales_report.php" class="nav-btn"><i class="fas fa-chart-line"></i> <span>Sales</span></a>
        <a href="guide.php" class="nav-btn"><i class="fas fa-book"></i> <span>Guide</span></a>
        <a href="settings.php" class="nav-btn"><i class="fas fa-cog"></i> <span>Settings</span></a>
        <a href="../auth/logout.php" class="nav-btn logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
      </nav>
    </div>
  </header>

  <main>
    <!-- Statistics -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
        <div class="stat-value"><?= count($transactions) ?></div>
        <div class="stat-label">Total Transactions</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-value">₱<?= number_format($totalRevenue, 2) ?></div>
        <div class="stat-label">Total Revenue</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-value">₱<?= number_format($totalPaid, 2) ?></div>
        <div class="stat-label">Paid Amount</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-utensils"></i></div>
        <div class="stat-value">₱<?= number_format($totalOrders, 2) ?></div>
        <div class="stat-label">Orders Revenue</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
      <form method="GET" id="filterForm">
        <div class="filter-grid">
          <div class="filter-group">
            <label class="filter-label">From Date</label>
            <input type="date" name="start_date" class="filter-input" value="<?= htmlspecialchars($startDate) ?>">
          </div>
          <div class="filter-group">
            <label class="filter-label">To Date</label>
            <input type="date" name="end_date" class="filter-input" value="<?= htmlspecialchars($endDate) ?>">
          </div>
          <div class="filter-group">
            <label class="filter-label">Payment Method</label>
            <select name="payment_method" class="filter-select">
              <option value="all" <?= $paymentMethod === 'all' ? 'selected' : '' ?>>All Methods</option>
              <option value="CASH" <?= $paymentMethod === 'CASH' ? 'selected' : '' ?>>Cash</option>
              <option value="GCASH" <?= $paymentMethod === 'GCASH' ? 'selected' : '' ?>>GCash</option>
            </select>
          </div>
          <div class="filter-group">
            <label class="filter-label">Search (Bill/Room)</label>
            <input type="text" name="search" class="filter-input" placeholder="Enter bill ID or room #" value="<?= htmlspecialchars($searchBill) ?>">
          </div>
        </div>
        <div class="filter-actions">
          <button type="submit" class="btn-filter">
            <i class="fas fa-search"></i>
            Apply Filters
          </button>
          <a href="transactions.php" class="btn-reset">
            <i class="fas fa-redo"></i>
            Reset
          </a>
        </div>
      </form>
    </div>

    <!-- Transactions Table -->
    <div class="transactions-table">
      <?php if (count($transactions) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Bill ID</th>
              <th>Room</th>
              <th>Date</th>
              <th>Room Cost</th>
              <th>Orders</th>
              <th>Total</th>
              <th>Status</th>
              <th>Payment Method</th>
              <th>Reference #</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($transactions as $t): ?>
            <tr>
              <td><span class="bill-id">#<?= $t['bill_id'] ?></span></td>
              <td>
                <span class="room-info">Room <?= htmlspecialchars($t['room_number']) ?></span><br>
                <small style="color: #666;"><?= htmlspecialchars($t['type_name']) ?></small>
              </td>
              <td><?= date('M d, Y', strtotime($t['created_at'])) ?><br>
                  <small style="color: #666;"><?= date('h:i A', strtotime($t['created_at'])) ?></small>
              </td>
              <td>₱<?= number_format($t['total_room_cost'], 2) ?></td>
              <td>₱<?= number_format($t['total_orders_cost'], 2) ?></td>
              <td><span class="amount">₱<?= number_format($t['grand_total'], 2) ?></span></td>
              <td>
                <?php if ($t['is_paid']): ?>
                  <span class="badge badge-paid">Paid</span>
                <?php else: ?>
                  <span class="badge badge-unpaid">Unpaid</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($t['payment_method']): ?>
                  <span class="badge badge-<?= strtolower($t['payment_method']) ?>">
                    <?= htmlspecialchars($t['payment_method']) ?>
                  </span>
                <?php else: ?>
                  <span style="color: #999;">N/A</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($t['payment_method'] === 'GCASH' && $t['reference_number']): ?>
                  <span style="color: #333; font-family: monospace; font-size: 0.85rem;"><?= htmlspecialchars($t['reference_number']) ?></span>
                <?php else: ?>
                  <span style="color: #999;">-</span>
                <?php endif; ?>
              </td>
              <td>
                <a href="view_bill.php?id=<?= $t['bill_id'] ?>" class="row-details" title="View Details">
                  <i class="fas fa-eye"></i>
                </a>
                <a href="print_receipt.php?id=<?= $t['bill_id'] ?>" class="row-details" title="Print" target="_blank">
                  <i class="fas fa-print"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-search"></i>
          <p>No transactions found matching your filters.</p>
        </div>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>