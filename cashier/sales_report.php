<?php
// sales_report.php - Enhanced Sales Report with Analytics
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 3) { 
    header('Location: ../index.php'); 
}

$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get total sales for selected date
$stmt = $mysqli->prepare("SELECT SUM(total_amount) AS total_sales, COUNT(*) AS transaction_count FROM transactions WHERE transaction_date = ?");
$stmt->bind_param('s', $date);
$stmt->execute();
$dailyStats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get transactions for selected date
$stmt = $mysqli->prepare("SELECT t.transaction_id, t.bill_id, t.transaction_date, t.total_amount, 
    b.rental_id, r.room_id, rm.room_number, p.payment_method, p.paid_at
    FROM transactions t
    JOIN bills b ON t.bill_id = b.bill_id
    JOIN rentals r ON b.rental_id = r.rental_id
    JOIN rooms rm ON r.room_id = rm.room_id
    LEFT JOIN payments p ON b.bill_id = p.bill_id
    WHERE t.transaction_date = ? 
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
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(total_amount), 0) AS amount FROM transactions WHERE transaction_date = ?");
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
$paymentBreakdown = $mysqli->query("
    SELECT p.payment_method, COUNT(*) as count, SUM(p.amount_paid) as total
    FROM payments p
    JOIN bills b ON p.bill_id = b.bill_id
    JOIN rentals r ON b.rental_id = r.rental_id
    WHERE DATE(r.started_at) = '$date'
    GROUP BY p.payment_method
")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Sales Report — Wannabees KTV</title>
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
      background: #f8f9fa;
      color: #212529;
      line-height: 1.5;
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
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }
    
    .header-left {
      display: flex;
      align-items: center;
      gap: 1rem;
      flex: 1;
      min-width: 0;
    }
    
    .header-left img {
      height: 40px;
      flex-shrink: 0;
    }
    
    .header-info {
      min-width: 0;
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
      align-items: center;
      flex-shrink: 0;
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
      background: #ffffff;
      cursor: pointer;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      color: #495057;
      text-decoration: none;
      white-space: nowrap;
    }
    
    .nav-btn:hover {
      background: #f8f9fa;
      border-color: #adb5bd;
    }
    
    .nav-btn.active {
      background: #f2a20a;
      color: white;
      border-color: #f2a20a;
    }
    
    .nav-btn.logout {
      border-color: #dc3545;
      color: #dc3545;
    }
    
    .nav-btn.logout:hover {
      background: #dc3545;
      color: #ffffff;
    }
    
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
    
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
      gap: 1rem;
      flex-wrap: wrap;
    }
    
    .page-title {
      font-size: 1.5rem;
      font-weight: 600;
      color: #212529;
    }
    
    .date-picker {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      background: white;
      padding: 0.75rem;
      border-radius: 8px;
      border: 1px solid #e9ecef;
    }
    
    .date-picker input {
      border: 1px solid #dee2e6;
      padding: 0.5rem;
      border-radius: 6px;
      font-size: 0.875rem;
    }
    
    .date-picker button {
      padding: 0.5rem 1rem;
      background: #f2a20a;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      font-size: 0.875rem;
      transition: all 0.2s;
    }
    
    .date-picker button:hover {
      background: #f2a20a;
    }
    
    .date-picker button.today-btn {
      background: #6c757d;
    }
    
    .date-picker button.today-btn:hover {
      background: #5a6268;
    }
    
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem;
    }
    
    .stat-card {
      background: white;
      padding: 1.5rem;
      border-radius: 8px;
      border: 1px solid #e9ecef;
    }
    
    .stat-label {
      font-size: 0.875rem;
      font-weight: 500;
      color: #6c757d;
      margin-bottom: 0.5rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      color: #212529;
    }
    
    .chart-section {
      background: white;
      padding: 1.5rem;
      border-radius: 8px;
      border: 1px solid #e9ecef;
      margin-bottom: 1.5rem;
    }
    
    .section-title {
      font-size: 1rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
      color: #212529;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .payment-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-top: 1rem;
    }
    
    .payment-card {
      background: #f8f9fa;
      padding: 1.25rem;
      border-radius: 6px;
      border-left: 3px solid #f2a20a;
    }
    
    .payment-method-label {
      font-size: 0.875rem;
      color: #6c757d;
      margin-bottom: 0.5rem;
      font-weight: 500;
    }
    
    .payment-amount {
      font-size: 1.5rem;
      font-weight: 700;
      color: #212529;
    }
    
    .payment-count {
      font-size: 0.75rem;
      color: #6c757d;
      margin-top: 0.25rem;
    }
    
    .table-container {
      background: white;
      border-radius: 8px;
      border: 1px solid #e9ecef;
      overflow: hidden;
    }
    
    .table-header {
      padding: 1.5rem;
      border-bottom: 1px solid #e9ecef;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
    }
    
    thead {
      background: #f8f9fa;
    }
    
    th {
      padding: 1rem 1.25rem;
      text-align: left;
      font-size: 0.75rem;
      font-weight: 600;
      color: #6c757d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    tbody tr {
      border-bottom: 1px solid #f1f3f5;
    }
    
    tbody tr:hover {
      background: #f8f9fa;
    }
    
    td {
      padding: 1rem 1.25rem;
      font-size: 0.875rem;
    }
    
    .transaction-id {
      font-weight: 600;
      color: #495057;
    }
    
    .room-badge {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      background: #e9ecef;
      color: #495057;
      border-radius: 4px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    
    .amount {
      font-weight: 700;
      color: #212529;
    }
    
    .payment-method {
      padding: 0.25rem 0.5rem;
      background: #f8f9fa;
      color: #495057;
      border-radius: 4px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    
    .empty-state {
      text-align: center;
      padding: 3rem 1rem;
    }
    
    .empty-state i {
      font-size: 3rem;
      color: #dee2e6;
      margin-bottom: 1rem;
    }
    
    .empty-state h3 {
      font-size: 1.125rem;
      margin-bottom: 0.5rem;
      color: #6c757d;
      font-weight: 500;
    }
    
    .empty-state p {
      color: #adb5bd;
      font-size: 0.875rem;
    }
    
    @media (max-width: 768px) {
      .page-header {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .date-picker {
        width: 100%;
        flex-wrap: wrap;
      }
      
      .stats-grid {
        grid-template-columns: 1fr;
      }
      
      .table-container {
        overflow-x: auto;
      }
      
      table {
        min-width: 600px;
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
        <img src="../assets/images/KTVL.png" alt="logo" onerror="this.style.display='none'">
        <div class="header-info">
          <div class="header-title">Wannabees Family KTV</div>
        </div>
      </div>
      <button class="mobile-nav-toggle" onclick="toggleMobileNav()"><i class="fas fa-bars"></i></button>
      <nav class="header-nav" id="headerNav">
        <a href="dashboard.php" class="nav-btn">
          <i class="fas fa-home"></i>
          <span>Dashboard</span>
        </a>
        <a href="transactions.php" class="nav-btn">
          <i class="fas fa-history"></i>
          <span>Transactions</span>
        </a>
        <a href="sales_report.php" class="nav-btn active">
          <i class="fas fa-chart-line"></i>
          <span>Sales</span>
        </a>
        <a href="guide.php" class="nav-btn">
          <i class="fas fa-book"></i>
          <span>Guide</span>
        </a>
        <a href="settings.php" class="nav-btn">
          <i class="fas fa-cog"></i>
          <span>Settings</span>
        </a>
        <a href="../auth/logout.php" class="nav-btn logout">
          <i class="fas fa-sign-out-alt"></i>
          <span>Logout</span>
        </a>
      </nav>
    </div>
  </header>

  <main>
    <div class="page-header">
      <h1 class="page-title">Sales Report</h1>
      <form class="date-picker" method="get">
        <i class="fas fa-calendar"></i>
        <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" max="<?= date('Y-m-d') ?>">
        <button type="submit"><i class="fas fa-search"></i> View</button>
        <button type="button" class="today-btn" onclick="location.href='?date=<?= date('Y-m-d') ?>'">Today</button>
      </form>
    </div>

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

    <div class="chart-section">
      <h3 class="section-title">
        <i class="fas fa-chart-area"></i> 7-Day Sales Trend
      </h3>
      <canvas id="salesChart" height="80"></canvas>
    </div>

    <?php if (count($paymentBreakdown) > 0): ?>
    <div class="chart-section">
      <h3 class="section-title">
        <i class="fas fa-credit-card"></i> Payment Method Breakdown
      </h3>
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

    <div class="table-container">
      <div class="table-header">
        <h3 class="section-title">
          <i class="fas fa-list"></i> Transactions for <?= date('F d, Y', strtotime($date)) ?>
        </h3>
      </div>
      
      <?php if (count($transactions) > 0): ?>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Room</th>
              <th>Time</th>
              <th>Payment Method</th>
              <th>Amount</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($transactions as $t): ?>
              <tr>
                <td><span class="transaction-id">#<?= $t['transaction_id'] ?></span></td>
                <td><span class="room-badge">Room <?= $t['room_number'] ?></span></td>
                <td><?= date('h:i A', strtotime($t['paid_at'] ?: $t['transaction_date'])) ?></td>
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
          tension: 0.3,
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
            titleFont: { size: 14, weight: 'bold' },
            bodyFont: { size: 13 },
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
              }
            },
            grid: {
              color: 'rgba(0, 0, 0, 0.05)'
            }
          },
          x: {
            grid: {
              display: false
            }
          }
        }
      }
    });
  </script>
</body>
</html>