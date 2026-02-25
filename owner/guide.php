<?php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id'])) { 
    header('Location: ../index.php'); 
    exit; 
}
$ownerName = htmlspecialchars($_SESSION['display_name'] ?: $_SESSION['username']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>System Guide — Wannabees KTV</title>
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
      background: #f0f0f0;
      color: #2c2c2c;
      height: 100vh;
      overflow-x: hidden;
    }
    
    /* Header - Consistent with inventory */
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
      width: 36px;
      height: 36px;
      object-fit: contain;
      display: block;
      margin-right: .1px;
      border-radius: 6px;
    }

    .header-title {
      font-size: 16px;
      font-weight: 600;
      line-height: 1.2;
    }
    
    .header-subtitle {
      font-size: 12px;
      color: #666;
      display: none;
    }
    
    .header-nav {
      display: flex;
      gap: 4px;
      flex-wrap: wrap;
      align-items: center;
    }
    
    .header-actions {
      display: flex;
      gap: 8px;
      align-items: center;
      margin-left: 12px;
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
    
    main {
      padding: 30px;
      max-width: 1200px;
      margin: 0 auto;
      height: calc(100vh - 60px);
      overflow-y: auto;
      animation: fadeIn 0.6s ease-out;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    
    .hero-section {
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      border-radius: 16px;
      padding: 40px;
      text-align: center;
      margin-bottom: 40px;
      color: white;
      box-shadow: 0 8px 30px rgba(242,162,10,0.3);
    }
    
    .hero-title {
      font-size: 36px;
      font-weight: 700;
      margin-bottom: 10px;
    }
    
    .hero-subtitle {
      font-size: 18px;
      opacity: 0.95;
    }
    
    .roles-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 25px;
      margin-bottom: 40px;
    }
    
    .role-card {
      background: white;
      border-radius: 16px;
      padding: 30px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      animation: slideUp 0.5s ease-out;
    }
    
    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .role-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    }
    
    .role-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 5px;
      background: linear-gradient(90deg, #f5c542, #f2a20a);
    }
    
    .role-icon {
      width: 70px;
      height: 70px;
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 20px;
      color: white;
      font-size: 32px;
    }
    
    .role-title {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 15px;
      color: #2c2c2c;
    }
    
    .role-description {
      color: #666;
      line-height: 1.6;
      margin-bottom: 20px;
    }
    
    .role-features {
      list-style: none;
    }
    
    .role-features li {
      padding: 10px 0;
      border-top: 1px solid #f0f0f0;
      display: flex;
      align-items: center;
      gap: 10px;
      color: #555;
    }
    
    .role-features li:first-child {
      border-top: none;
    }
    
    .role-features i {
      color: #27ae60;
      font-size: 16px;
    }

    .workflow-section {
      background: white;
      border-radius: 16px;
      padding: 40px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      margin-bottom: 40px;
    }
    
    .section-title {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 30px;
      text-align: center;
      color: #2c2c2c;
    }
    
    .workflow-steps {
      position: relative;
    }
    
    .workflow-step {
      display: flex;
      gap: 25px;
      margin-bottom: 40px;
      position: relative;
    }
    
    .workflow-step:last-child {
      margin-bottom: 0;
    }
    
    .step-number {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      font-weight: 700;
      flex-shrink: 0;
      box-shadow: 0 4px 15px rgba(242,162,10,0.3);
    }
    
    .step-content {
      flex: 1;
    }
    
    .step-title {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 8px;
      color: #2c2c2c;
    }
    
    .step-description {
      color: #666;
      margin-bottom: 15px;
    }
    
    .step-details ul {
      list-style: none;
      padding-left: 0;
    }
    
    .step-details li {
      padding: 8px 0 8px 30px;
      position: relative;
      color: #555;
      line-height: 1.6;
    }
    
    .step-details li:before {
      content: "→";
      position: absolute;
      left: 10px;
      color: #f2a20a;
      font-weight: 700;
    }

    .status-legend {
      background: white;
      border-radius: 16px;
      padding: 30px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      margin-bottom: 40px;
    }
    
    .legend-items {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }
    
    .legend-item {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 15px;
      background: #f9f9f9;
      border-radius: 10px;
    }
    
    .legend-color {
      width: 60px;
      height: 60px;
      border-radius: 10px;
      flex-shrink: 0;
    }
    
    .legend-color.available {
      background: #27ae60;
    }
    
    .legend-color.occupied {
      background: #e74c3c;
    }
    
    .legend-color.cleaning {
      background: #3498db;
    }
    
    .legend-label {
      font-size: 18px;
      font-weight: 700;
      color: #2c2c2c;
    }
    
    .legend-desc {
      font-size: 13px;
      color: #666;
    }

    .tips-section {
      background: white;
      border-radius: 16px;
      padding: 40px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    
    .tips-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }
    
    .tip-card {
      background: #f9f9f9;
      border-radius: 12px;
      padding: 20px;
      transition: all 0.3s ease;
      border-left: 4px solid #f2a20a;
    }
    
    .tip-card:hover {
      transform: translateX(5px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    
    .tip-icon {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 20px;
      margin-bottom: 15px;
    }
    
    .tip-title {
      font-size: 16px;
      font-weight: 700;
      margin-bottom: 8px;
      color: #2c2c2c;
    }
    
    .tip-text {
      font-size: 13px;
      line-height: 1.6;
      color: #666;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .header-subtitle {
        display: block;
      }
      
      .hero-title {
        font-size: 24px;
      }
      
      .hero-subtitle {
        font-size: 14px;
      }
      
      main {
        padding: 15px;
      }
      
      .workflow-step {
        flex-direction: column;
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
    }
  </style>
</head>
<body>
  <header>
    <div class="header-left">
      <img src="../assets/images/KTVL.png" alt="Wannabees KTV" onerror="this.style.display='none'">
      <div>
        <div class="header-title">Wannabees Family KTV</div>
      </div>
    </div>
    <button class="mobile-nav-toggle" onclick="toggleMobileNav()">
      <i class="fas fa-bars"></i>
    </button>
    
    <div class="header-nav" id="headerNav">
      <button class="btn" onclick="location.href='dashboard.php'">
        <i class="fas fa-door-open"></i> <span>Rooms</span>
      </button>
      <button class="btn" onclick="location.href='inventory.php'">
        <i class="fas fa-box"></i> <span>Inventory</span>
      </button>
      <button class="btn" onclick="location.href='sales_report.php'">
        <i class="fas fa-dollar-sign"></i> <span>Sales</span>
      </button>
      <button class="btn" onclick="location.href='pricing.php'">
        <i class="fas fa-tag"></i> <span>Pricing</span>
      </button>
      <button class="btn" onclick="location.href='users.php'">
        <i class="fas fa-users"></i> <span>Users</span>
      </button>
      <button class="btn btn-primary">
        <i class="fas fa-book"></i> <span>Guide</span>
      </button>
      <button class="btn" onclick="location.href='settings.php'">
        <i class="fas fa-cog"></i> <span>Settings</span>
      </button>
      <form method="post" action="../auth/logout.php" class="logout-form">
        <button type="button" class="btn btn-danger" onclick="logoutNow(this)">
          <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </button>
      </form>
    </div>
  </header>

  <main>
    <div class="hero-section">
      <h1 class="hero-title">Wannabees KTV System Guide</h1>
      <p class="hero-subtitle">Complete workflow and role overview</p>
    </div>

    <h2 class="section-title"><i class="fas fa-users"></i> System Roles</h2>
    <div class="roles-grid">
      <div class="role-card">
        <div class="role-icon"><i class="fas fa-crown"></i></div>
        <h3 class="role-title">Owner / Manager</h3>
        <p class="role-description">Full system control and business management</p>
        <ul class="role-features">
          <li><i class="fas fa-check-circle"></i> Start and end rentals</li>
          <li><i class="fas fa-check-circle"></i> Process payments</li>
          <li><i class="fas fa-check-circle"></i> View sales reports</li>
          <li><i class="fas fa-check-circle"></i> Manage inventory</li>
          <li><i class="fas fa-check-circle"></i> Configure pricing</li>
          <li><i class="fas fa-check-circle"></i> Create users</li>
        </ul>
      </div>

      <div class="role-card">
        <div class="role-icon"><i class="fas fa-cash-register"></i></div>
        <h3 class="role-title">Cashier</h3>
        <p class="role-description">Central system controller — manages orders, rooms, and billing</p>
        <ul class="role-features">
          <li><i class="fas fa-check-circle"></i> Process payments and billing</li>
          <li><i class="fas fa-check-circle"></i> Manage food/drink orders</li>
          <li><i class="fas fa-check-circle"></i> Update order status</li>
          <li><i class="fas fa-check-circle"></i> Mark rooms as cleaning/available</li>
          <li><i class="fas fa-check-circle"></i> Coordinate with staff via radio</li>
        </ul>
      </div>

      <div class="role-card">
        <div class="role-icon"><i class="fas fa-tablet-alt"></i></div>
        <h3 class="role-title">Customer (Tablet)</h3>
        <p class="role-description">In-room service and time extension</p>
        <ul class="role-features">
          <li><i class="fas fa-check-circle"></i> Order food and drinks</li>
          <li><i class="fas fa-check-circle"></i> Enter payment amount when ordering</li>
          <li><i class="fas fa-check-circle"></i> Track order status in real-time</li>
          <li><i class="fas fa-check-circle"></i> Extend rental time</li>
          <li><i class="fas fa-check-circle"></i> View current bill</li>
        </ul>
      </div>
    </div>

    <div class="workflow-section">
      <h2 class="section-title"><i class="fas fa-tasks"></i> Complete Workflow Process</h2>
      
      <div class="workflow-steps">
        <div class="workflow-step">
          <div class="step-number">1</div>
          <div class="step-content">
            <h3 class="step-title">Customer Arrival</h3>
            <p class="step-description">Customer arrives and requests a room</p>
            <div class="step-details">
              <ul>
                <li>Owner checks available rooms (green status)</li>
                <li>Selects room size based on party size</li>
                <li>Confirms room availability</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="workflow-step">
          <div class="step-number">2</div>
          <div class="step-content">
            <h3 class="step-title">Start Rental</h3>
            <p class="step-description">Owner initiates the rental session</p>
            <div class="step-details">
              <ul>
                <li>Click on available (green) room</li>
                <li>Select duration (30 min, 1 hr, 2 hrs, 3 hrs)</li>
                <li>Confirm rental - Room turns RED (Occupied)</li>
                <li>Timer starts automatically</li>
                <li>Initial bill is created</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="workflow-step">
          <div class="step-number">3</div>
          <div class="step-content">
            <h3 class="step-title">Customer Uses Tablet</h3>
            <p class="step-description">In-room tablet allows customers to order and extend</p>
            <div class="step-details">
              <ul>
                <li>View remaining time on screen</li>
                <li>Browse menu and order food/drinks</li>
                <li>Orders are added to bill automatically</li>
                <li>Extend time if needed (added to bill)</li>
                <li>View current bill summary</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="workflow-step">
          <div class="step-number">4</div>
          <div class="step-content">
            <h3 class="step-title">Check Out & Payment</h3>
            <p class="step-description">Customer finishes and pays the bill</p>
            <div class="step-details">
              <ul>
                <li>Customer requests to check out</li>
                <li>Owner/Cashier clicks occupied room</li>
                <li>Reviews complete bill (room + orders + extensions)</li>
                <li>Selects payment method (CASH/GCASH)</li>
                <li>Processes payment</li>
                <li>Room turns BLUE (Cleaning)</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="workflow-step">
          <div class="step-number">5</div>
          <div class="step-content">
            <h3 class="step-title">Room Cleaning</h3>
            <p class="step-description">Cashier marks room for cleaning; staff cleans physically</p>
            <div class="step-details">
              <ul>
                <li>Cashier marks room as CLEANING from dashboard</li>
                <li>Staff is notified via radio</li>
                <li>Staff enters room, cleans and tidies</li>
                <li>Restocks amenities if needed</li>
              </ul>
            </div>
          </div>
        </div>

        <div class="workflow-step">
          <div class="step-number">6</div>
          <div class="step-content">
            <h3 class="step-title">Mark Available</h3>
            <p class="step-description">Cashier marks room ready for next customer</p>
            <div class="step-details">
              <ul>
                <li>Staff signals via radio that cleaning is done</li>
                <li>Cashier clicks cleaning room on dashboard</li>
                <li>Marks room as available</li>
                <li>Room turns GREEN - Ready for next customer!</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="status-legend">
      <h2 class="section-title"><i class="fas fa-traffic-light"></i> Room Status Guide</h2>
      <div class="legend-items">
        <div class="legend-item">
          <div class="legend-color available"></div>
          <div>
            <div class="legend-label">Available</div>
            <div class="legend-desc">Ready for customers</div>
          </div>
        </div>
        <div class="legend-item">
          <div class="legend-color occupied"></div>
          <div>
            <div class="legend-label">Occupied</div>
            <div class="legend-desc">Currently in use</div>
          </div>
        </div>
        <div class="legend-item">
          <div class="legend-color cleaning"></div>
          <div>
            <div class="legend-label">Cleaning</div>
            <div class="legend-desc">Being prepared</div>
          </div>
        </div>
      </div>
    </div>

    <div class="tips-section">
      <h2 class="section-title"><i class="fas fa-lightbulb"></i> Pro Tips</h2>
      <div class="tips-grid">
        <div class="tip-card">
          <div class="tip-icon"><i class="fas fa-clock"></i></div>
          <h4 class="tip-title">Time Management</h4>
          <p class="tip-text">Monitor room timers regularly. Offer extensions 15 minutes before time expires to maximize satisfaction.</p>
        </div>
        
        <div class="tip-card">
          <div class="tip-icon"><i class="fas fa-chart-line"></i></div>
          <h4 class="tip-title">Daily Sales Review</h4>
          <p class="tip-text">Check sales report daily to track performance. Identify peak hours and adjust staffing accordingly.</p>
        </div>
        
        <div class="tip-card">
          <div class="tip-icon"><i class="fas fa-box"></i></div>
          <h4 class="tip-title">Inventory Alerts</h4>
          <p class="tip-text">Keep stock above 10 units to avoid running out. Red indicators show low stock items that need restocking.</p>
        </div>
        
        <div class="tip-card">
          <div class="tip-icon"><i class="fas fa-users"></i></div>
          <h4 class="tip-title">Staff Training</h4>
          <p class="tip-text">Train all staff on cleaning procedures and system usage. Consistent service improves customer experience.</p>
        </div>
        
        <div class="tip-card">
          <div class="tip-icon"><i class="fas fa-mobile-alt"></i></div>
          <h4 class="tip-title">Tablet Setup</h4>
          <p class="tip-text">Pre-assign tablets to rooms using ?room=X in URL. Keep tablets charged and accessible to customers.</p>
        </div>
        
        <div class="tip-card">
          <div class="tip-icon"><i class="fas fa-shield-alt"></i></div>
          <h4 class="tip-title">Security</h4>
          <p class="tip-text">Change default passwords immediately. Limit owner access to trusted personnel only.</p>
        </div>
      </div>
    </div>
  </main>

  <script>
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }
      });
    }, observerOptions);

    document.querySelectorAll('.role-card, .workflow-step, .tip-card').forEach(el => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(20px)';
      el.style.transition = 'all 0.5s ease-out';
      observer.observe(el);
    });
    
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
</body>
</html>