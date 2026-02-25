<?php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 1) { 
    header('Location: ../index.php'); 
}
$ownerName = htmlspecialchars($_SESSION['display_name'] ?: $_SESSION['username']);
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get total sales for selected date
$stmt = $mysqli->prepare("SELECT SUM(total_amount) AS total_sales, COUNT(*) AS transaction_count FROM transactions WHERE DATE(transaction_date) = ?");
$stmt->bind_param('s', $date);
$stmt->execute();
$dailyStats = $stmt->get_result()->fetch_assoc();
$stmt->close();
 
// Get transactions for selected date with user info
$stmt = $mysqli->prepare("SELECT t.transaction_id, t.bill_id, t.transaction_date, t.total_amount, t.user_id,
    b.rental_id, r.room_id, rm.room_number, p.payment_method, p.paid_at,
    u.username, u.display_name
    FROM transactions t
    JOIN bills b ON t.bill_id = b.bill_id
    JOIN rentals r ON b.rental_id = r.rental_id
    JOIN rooms rm ON r.room_id = rm.room_id
    LEFT JOIN payments p ON b.bill_id = p.bill_id
    LEFT JOIN users u ON t.user_id = u.user_id
    WHERE DATE(t.transaction_date) = ? 
    ORDER BY t.transaction_id DESC");
$stmt->bind_param('s', $date);
$stmt->execute();
$res = $stmt->get_result();
$transactions = [];
while ($row = $res->fetch_assoc()) $transactions[] = $row;
$stmt->close();
 
// Get monthly stats for chart (last 7 days)
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $chartDate = date('Y-m-d', strtotime("-$i days"));
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(total_amount), 0) AS amount FROM transactions WHERE DATE(transaction_date) = ?");
    $stmt->bind_param('s', $chartDate);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $chartData[] = [
        'date' => date('M d', strtotime($chartDate)),
        'amount' => floatval($result['amount'])
    ];
    $stmt->close();
}

// Payment method breakdown
$stmt = $mysqli->prepare("
    SELECT p.payment_method, COUNT(*) as count, SUM(p.amount_paid) as total
    FROM payments p
    JOIN bills b ON p.bill_id = b.bill_id
    JOIN rentals r ON b.rental_id = r.rental_id
    WHERE DATE(r.started_at) = ?
    GROUP BY p.payment_method
");
$stmt->bind_param('s', $date);
$stmt->execute();
$paymentBreakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sales — Wannabees Family KTV</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #f0f0f0;
      color: #2c2c2c;
      min-height: 100vh; /* allow page to grow and scroll */
      overflow-x: hidden;
    }
    
    /* Header - Consistent with owner */
    header {
      background: #f5f5f5;
      padding: 10px 15px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      position: sticky;
      top: 0;
      z-index: 100;
      min-height: 60px;
    }
    
    .header-left {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-shrink: 0;
    }
    
    .header-left img {
      height: 35px;
      width: auto;
    }
    
    .header-title {
      font-size: 16px;
      font-weight: 600;
      line-height: 1.2;
    }
    
    .header-subtitle {
      font-size: 12px;
      color: #666;
    }
    
    .header-nav {
      display: flex;
      gap: 4px;
      flex-wrap: wrap;
      align-items: center;
    }
    
    .mobile-nav-toggle {
      display: none;
      background: none;
      border: none;
      font-size: 18px;
      cursor: pointer;
      color: #333;
    }
    
    .btn {
      padding: 7px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
      font-weight: 500;
      transition: all 0.2s ease;
      background: white;
      color: #555;
      white-space: nowrap;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      text-decoration: none;
    }
    
    .btn i {
      font-size: 10px;
    }
    
    .btn:hover {
      background: #f8f8f8;
      border-color: #bbb;
    }
    
    .btn-primary {
      background: #f2a20a;
      color: white;
      border-color: #f2a20a;
    }
    
    .btn-primary:hover {
      background: #d89209;
      border-color: #d89209;
    }
    
    .btn-danger {
      background: white;
      color: #e74c3c;
      border-color: #e74c3c;
    }
    
    .btn-danger:hover {
      background: #fef5f5;
      border-color: #c0392b;
      color: #c0392b;
    }
    
    .logout-form {
      display: inline-block;
    }
    
    .logout-form .btn {
      padding: 6px 10px;
      font-size: 12px;
    }
    
    /* Main Content */
    main {
      padding: 12px;
      min-height: calc(100vh - 60px); /* keep header visible but allow page to extend */
      display: flex;
      flex-direction: column;
    }
    
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
      flex-shrink: 0;
      flex-wrap: wrap;
      gap: 10px;
    }
    
    h1, .page-title {
      font-size: 18px;
      font-weight: 700;
      color: #2c2c2c;
    }
    
    .date-picker {
      display: flex;
      gap: 6px;
      align-items: center;
      background: white;
      padding: 6px 10px;
      border-radius: 4px;
      border: 1px solid #ddd;
    }
    
    .date-picker i {
      color: #999;
      font-size: 12px;
    }
    
    .date-picker input {
      border: none;
      font-size: 12px;
      padding: 4px;
      outline: none;
    }
    
    .date-picker button {
      padding: 5px 10px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
      font-weight: 500;
      background: #f2a20a;
      color: white;
      transition: background 0.2s;
    }
    
    .date-picker button:hover {
      background: #d89209;
    }
    
    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 10px;
      margin-bottom: 12px;
    }
    
    .stat-card {
      background: white;
      padding: 16px 12px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      text-align: center;
    }
    
    .stat-label {
      font-size: 11px;
      color: #666;
      text-transform: uppercase;
      letter-spacing: 0.3px;
      margin-bottom: 6px;
      font-weight: 600;
    }
    
    .stat-value {
      font-size: 20px;
      font-weight: 700;
      color: #2c2c2c;
    }
    
    /* Chart Section */
    .chart-section {
      background: white;
      padding: 16px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      margin-bottom: 12px;
    }
    
    .section-title {
      font-size: 14px;
      font-weight: 700;
      margin-bottom: 12px;
      color: #2c2c2c;
    }
    
    /* Payment Grid */
    .payment-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 10px;
    }
    
    .payment-card {
      background: #f8f8f8;
      padding: 12px;
      border-radius: 6px;
      text-align: center;
    }
    
    .payment-method-label {
      font-size: 12px;
      font-weight: 600;
      color: #666;
      margin-bottom: 6px;
      text-transform: uppercase;
    }
    
    .payment-amount {
      font-size: 18px;
      font-weight: 700;
      color: #f2a20a;
      margin-bottom: 4px;
    }
    
    .payment-count {
      font-size: 11px;
      color: #999;
    }
    
    /* Table Container */
    .table-container {
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      padding: 16px;
      overflow: hidden;
    }
    
    .table-header {
      margin-bottom: 12px;
    }
    
    .search-filter-bar {
      margin-bottom: 12px;
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
    }
    
    .search-filter-bar > div {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
      flex: 1;
    }
    
    .search-box {
      position: relative;
      flex: 1;
      min-width: 200px;
    }
    
    .search-box i {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
      font-size: 12px;
    }
    
    .search-box input {
      width: 100%;
      padding: 8px 12px 8px 32px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 12px;
    }
    
    .search-box input:focus {
      outline: none;
      border-color: #f2a20a;
      box-shadow: 0 0 0 2px rgba(242,162,10,0.1);
    }
    
    #paymentFilter {
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 12px;
    }
    
    #resultsCounter {
      font-size: 12px;
      color: #666;
      display: none;
    }
    
    /* Table Styling */
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }
    
    thead {
      background: #f8f8f8;
    }
    
    th {
      padding: 10px 12px;
      text-align: left;
      font-weight: 600;
      color: #555;
      font-size: 12px;
      border-bottom: 2px solid #e0e0e0;
    }
    
    td {
      padding: 10px 12px;
      border-bottom: 1px solid #f0f0f0;
    }
    
    tbody tr:hover {
      background: #f8f8f8;
    }
    
    .transaction-id {
      font-weight: 600;
      color: #666;
    }
    
    .room-badge {
      background: #f2a20a;
      color: white;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 600;
    }
    
    .user-name {
      color: #555;
    }
    
    .payment-method {
      background: #e3f2fd;
      color: #1976d2;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 600;
    }
    
    .amount {
      font-weight: 700;
      color: #27ae60;
    }
    
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #999;
    }
    
    .empty-state i {
      font-size: 48px;
      color: #ddd;
      margin-bottom: 12px;
    }
    
    .empty-state h3 {
      font-size: 16px;
      margin-bottom: 8px;
      color: #666;
    }
    
    .empty-state p {
      font-size: 13px;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
      .header-left img {
        height: 30px;
      }
      
      .header-title {
        font-size: 14px;
      }
      
      .header-subtitle {
        font-size: 11px;
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
      
      .mobile-nav-toggle {
        display: block;
      }
      
      .btn {
        width: 100%;
        justify-content: flex-start;
        border-radius: 0;
        border: none;
        border-bottom: 1px solid #f0f0f0;
      }
      
      .btn span {
        display: inline;
      }
      
      .btn i {
        font-size: 12px;
      }
      
      .header-nav .logout-form {
        width: 100%;
      }
      
      .header-nav .logout-form .btn {
        width: 100%;
        padding: 10px 12px;
      }
      
      main {
        padding: 10px;
      }
      
      h1 {
        font-size: 16px;
      }
      
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .table-container {
        overflow-x: auto;
      }
      
      table {
        min-width: 600px;
      }
      
      .page-header {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .date-picker {
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <header>
    <div class="header-left">
      <img src="../assets/images/KTVL.png" alt="Logo" onerror="this.style.display='none'">
      <div>
        <div class="header-title">Wannabees Family KTV</div>
      </div>
    </div>
    
    <button class="mobile-nav-toggle" onclick="toggleMobileNav()">
      <i class="fas fa-bars"></i>
    </button>
    
    <div class="header-nav" id="headerNav">
      <button class="btn" onclick="location.href='dashboard.php'"><i class="fas fa-door-open"></i> <span>Rooms</span></button>
      <button class="btn" onclick="location.href='inventory.php'"><i class="fas fa-box"></i> <span>Inventory</span></button>
      <button class="btn btn-primary"><i class="fas fa-dollar-sign"></i> <span>Sales</span></button>
      <button class="btn" onclick="location.href='pricing.php'"><i class="fas fa-tag"></i> <span>Pricing</span></button>
      <button class="btn" onclick="location.href='users.php'"><i class="fas fa-users"></i> <span>Users</span></button>
      <button class="btn" onclick="location.href='guide.php'"><i class="fas fa-book"></i> <span>Guide</span></button>
      <button class="btn" onclick="location.href='settings.php'"><i class="fas fa-cog"></i> <span>Settings</span></button>
      <form action="../auth/logout.php" method="post" class="logout-form">
        <button type="button" class="btn btn-danger" onclick="logoutNow(this)"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></button>
      </form>
    </div>
  </header>

  <main>
    <div class="page-header">
      <h1>Sales Report</h1>
      <form class="date-picker" method="get">
        <i class="fas fa-calendar"></i>
        <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" max="<?= date('Y-m-d') ?>">
        <button type="submit"><i class="fas fa-search"></i> View</button>
        <button type="button" onclick="location.href='?date=<?= date('Y-m-d') ?>'">Today</button>
      </form>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Sales</div>
        <div class="stat-value">₱<?= number_format(floatval($dailyStats['total_sales']), 2) ?></div>
      </div>
      
      <div class="stat-card">
        <div class="stat-label">Transactions</div>
        <div class="stat-value"><?= intval($dailyStats['transaction_count']) ?></div>
      </div>
      
      <div class="stat-card">
        <div class="stat-label">Average Bill</div>
        <div class="stat-value">
          ₱<?= $dailyStats['transaction_count'] > 0 ? number_format($dailyStats['total_sales'] / $dailyStats['transaction_count'], 2) : '0.00' ?>
        </div>
      </div>
    </div>

    <!-- 7-Day Trend Chart -->
    <div class="chart-section">
      <h3 class="section-title">7-Day Sales Trend</h3>
      <canvas id="salesChart" height="80"></canvas>
    </div>

    <!-- Payment Method Breakdown -->
    <?php if (count($paymentBreakdown) > 0): ?>
    <div class="chart-section">
      <h3 class="section-title">Payment Method Breakdown</h3>
      <div class="payment-grid">
        <?php foreach ($paymentBreakdown as $pm): ?>
          <div class="payment-card">
            <div class="payment-method-label"><?= htmlspecialchars($pm['payment_method']) ?></div>
            <div class="payment-amount">₱<?= number_format($pm['total'], 2) ?></div>
            <div class="payment-count"><?= $pm['count'] ?> transactions</div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Transactions Table -->
    <div class="table-container">
      <div class="table-header">
        <h2 class="section-title">Transactions for <?= date('F d, Y', strtotime($date)) ?></h2>
      </div>
      
      <!-- Search and Filter -->
      <div class="search-filter-bar">
        <div>
          <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Search transactions..." oninput="filterTransactions()">
          </div>
          <select id="paymentFilter" onchange="filterTransactions()">
            <option value="">All Payments</option>
            <option value="CASH">Cash</option>
            <option value="GCASH">GCash</option>
          </select>
          <button class="btn" onclick="clearFilters()">
            <i class="fas fa-times"></i> Clear
          </button>
          <div id="resultsCounter">
            <i class="fas fa-info-circle"></i> <span id="resultsCount">0</span> results
          </div>
        </div>
      </div>
      
      <?php if (count($transactions) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Room</th>
              <th>Time</th>
              <th>User</th>
              <th>Payment</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($transactions as $t): ?>
              <tr>
                <td><span class="transaction-id">#<?= $t['transaction_id'] ?></span></td>
                <td><span class="room-badge">Room <?= $t['room_number'] ?></span></td>
                <td><?= date('h:i A', strtotime($t['paid_at'] ?: $t['transaction_date'])) ?></td>
                <td><span class="user-name"><?= htmlspecialchars($t['display_name'] ?: $t['username'] ?: 'N/A') ?></span></td>
                <td><span class="payment-method"><?= htmlspecialchars($t['payment_method'] ?: 'CASH') ?></span></td>
                <td><span class="amount">₱<?= number_format($t['total_amount'], 2) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-receipt"></i>
          <h3>No Transactions</h3>
          <p>No transactions found for <?= date('F d, Y', strtotime($date)) ?></p>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <script>
    // 7-Day Sales Chart
    const chartData = <?= json_encode($chartData) ?>;
    const ctx = document.getElementById('salesChart').getContext('2d');
    
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: chartData.map(d => d.date),
        datasets: [{
          label: 'Daily Sales (₱)',
          data: chartData.map(d => d.amount),
          borderColor: '#f2a20a',
          backgroundColor: 'rgba(242, 162, 10, 0.1)',
          borderWidth: 2,
          fill: true,
          tension: 0.4,
          pointBackgroundColor: '#f2a20a',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 4,
          pointHoverRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 12,
            titleFont: { size: 13 },
            bodyFont: { size: 12 },
            callbacks: {
              label: function(context) {
                return 'Sales: ₱' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
              }
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return '₱' + value.toLocaleString();
              },
              font: {
                size: 11
              }
            },
            grid: {
              color: 'rgba(0, 0, 0, 0.05)'
            }
          },
          x: {
            ticks: {
              font: {
                size: 11
              }
            },
            grid: {
              display: false
            }
          }
        }
      }
    });
    
    // Filter Functions
    function clearFilters() {
      document.getElementById('searchInput').value = '';
      document.getElementById('paymentFilter').value = '';
      filterTransactions();
    }
    
    function filterTransactions() {
      const searchInput = document.getElementById('searchInput').value.toLowerCase();
      const paymentFilter = document.getElementById('paymentFilter').value.toLowerCase();
      
      const rows = document.querySelectorAll('tbody tr');
      let visibleCount = 0;
      
      rows.forEach(row => {
        const id = row.querySelector('.transaction-id').textContent.toLowerCase();
        const room = row.querySelector('.room-badge').textContent.toLowerCase();
        const user = row.querySelector('.user-name') ? row.querySelector('.user-name').textContent.toLowerCase() : '';
        const payment = row.querySelector('.payment-method') ? row.querySelector('.payment-method').textContent.toLowerCase() : '';
        const amount = row.querySelector('.amount').textContent.toLowerCase();
        
        const matchesSearch = id.includes(searchInput) || 
                            room.includes(searchInput) || 
                            user.includes(searchInput) ||
                            amount.includes(searchInput);
        
        const matchesPayment = !paymentFilter || payment.includes(paymentFilter);
        
        if (matchesSearch && matchesPayment) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });
      
      const resultsCounter = document.getElementById('resultsCounter');
      const resultsCount = document.getElementById('resultsCount');
      const isFiltering = searchInput || paymentFilter;
      
      if (isFiltering) {
        resultsCounter.style.display = 'block';
        resultsCount.textContent = visibleCount;
      } else {
        resultsCounter.style.display = 'none';
      }
    }

    function logoutNow(el) {
      const form = el && el.closest('form');
      if (navigator.sendBeacon) {
        navigator.sendBeacon('../auth/logout.php');
        setTimeout(() => { window.location.href = '../index.php'; }, 100);
        return;
      }
      if (form) form.submit();
      else {
        const f = document.createElement('form');
        f.method = 'POST';
        f.action = '../auth/logout.php';
        document.body.appendChild(f);
        f.submit();
      }
    }
    
    function toggleMobileNav() {
      const nav = document.getElementById('headerNav');
      nav.classList.toggle('active');
    }
    
    // Close mobile nav when clicking outside
    document.addEventListener('click', function(e) {
      const nav = document.getElementById('headerNav');
      const toggle = document.querySelector('.mobile-nav-toggle');
      
      if (nav && toggle && !nav.contains(e.target) && !toggle.contains(e.target)) {
        nav.classList.remove('active');
      }
    });
  </script>
</body>
</html>