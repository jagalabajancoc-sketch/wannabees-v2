<?php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 3) { 
    header('Location: ../index.php'); 
    exit; 
}
$cashierName = htmlspecialchars($_SESSION['display_name'] ?: $_SESSION['username']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Cashier Guide – Wannabees KTV</title>
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
      line-height: 1.6;
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
      max-width: 1200px;
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
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .hero-section {
      background: white;
      border-radius: 8px;
      border: 1px solid #e9ecef;
      padding: 2rem;
      text-align: center;
      margin-bottom: 1.5rem;
    }
    
    .hero-title {
      font-size: 1.75rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
      color: #212529;
    }
    
    .hero-subtitle {
      font-size: 1rem;
      color: #6c757d;
    }
    
    .guide-section {
      background: white;
      border-radius: 8px;
      border: 1px solid #e9ecef;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
    }
    
    .section-title {
      font-size: 1.125rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
      color: #212529;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .section-icon {
      width: 36px;
      height: 36px;
      background: #f2a20a;
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.125rem;
      flex-shrink: 0;
    }
    
    .step {
      margin-bottom: 1.5rem;
      padding: 1.25rem;
      background: #f8f9fa;
      border-radius: 6px;
      border-left: 3px solid #f2a20a;
    }
    
    .step:last-child {
      margin-bottom: 0;
    }
    
    .step-number {
      display: inline-block;
      width: 28px;
      height: 28px;
      background: #f2a20a;
      color: white;
      border-radius: 50%;
      text-align: center;
      line-height: 28px;
      font-weight: 700;
      font-size: 0.875rem;
      margin-right: 0.75rem;
    }
    
    .step-title {
      font-size: 1rem;
      font-weight: 600;
      margin-bottom: 0.75rem;
      color: #212529;
    }
    
    .step-description {
      color: #495057;
      font-size: 0.875rem;
      margin-left: 36px;
    }
    
    .step-description ul {
      margin-top: 0.75rem;
      padding-left: 1.25rem;
    }
    
    .step-description li {
      margin-bottom: 0.5rem;
    }
    
    .step-description li:last-child {
      margin-bottom: 0;
    }
    
    .tip-box {
      background: #e7f3ff;
      border-left: 3px solid #0d6efd;
      padding: 1rem 1.25rem;
      border-radius: 6px;
      margin-top: 1.5rem;
    }
    
    .tip-title {
      color: #0d6efd;
      font-weight: 600;
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;
    }
    
    .tip-text {
      color: #495057;
      font-size: 0.875rem;
    }
    
    .warning-box {
      background: #fff3cd;
      border-left: 3px solid #ffc107;
      padding: 1rem 1.25rem;
      border-radius: 6px;
      margin-top: 1.5rem;
    }
    
    .warning-title {
      color: #856404;
      font-weight: 600;
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;
    }
    
    .warning-text {
      color: #495057;
      font-size: 0.875rem;
    }
    
    .status-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1rem;
      margin-top: 1rem;
    }
    
    .status-card {
      padding: 1.5rem;
      border-radius: 6px;
      text-align: center;
      border: 2px solid;
    }
    
    .status-card.available {
      background: #f8fff9;
      border-color: #198754;
      color: #0d5c2f;
    }
    
    .status-card.occupied {
      background: #fff8f8;
      border-color: #dc3545;
      color: #841f2b;
    }
    
    .status-card.cleaning {
      background: #f8fbff;
      border-color: #0d6efd;
      color: #084298;
    }
    
    .status-card.green {
      background: #f8fff9;
      border-color: #198754;
      color: #0d5c2f;
    }
    
    .status-card.blue {
      background: #f8fbff;
      border-color: #0d6efd;
      color: #084298;
    }
    
    .status-card.yellow {
      background: #fffcf5;
      border-color: #ffc107;
      color: #856404;
    }
    
    .status-card.purple {
      background: #faf8ff;
      border-color: #6f42c1;
      color: #4a2885;
    }
    
    .status-icon {
      font-size: 2rem;
      margin-bottom: 0.75rem;
    }
    
    .status-label {
      font-size: 1rem;
      margin-bottom: 0.5rem;
      font-weight: 600;
    }
    
    .status-description {
      font-size: 0.875rem;
      opacity: 0.9;
    }
    
    .checklist {
      list-style: none;
      padding-left: 0;
    }
    
    .checklist li {
      padding-left: 1.5rem;
      position: relative;
    }
    
    .checklist li:before {
      content: "✓";
      position: absolute;
      left: 0;
      color: #198754;
      font-weight: bold;
    }
    
    .back-button-section {
      text-align: center;
      margin-top: 2rem;
    }
    
    .back-button {
      padding: 0.75rem 2rem;
      background: #f2a20a;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 1rem;
      font-weight: 600;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .back-button:hover {
      background: #f2a20a;
    }
    
    @media (max-width: 768px) {
      .hero-title {
        font-size: 1.5rem;
      }
      
      .hero-subtitle {
        font-size: 0.875rem;
      }
      
      .section-title {
        font-size: 1rem;
      }
      
      .status-grid {
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
        <a href="sales_report.php" class="nav-btn">
          <i class="fas fa-chart-line"></i>
          <span>Sales</span>
        </a>
        <a href="guide.php" class="nav-btn active">
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
    <div class="hero-section">
      <h1 class="hero-title">Cashier Operations Guide</h1>
      <p class="hero-subtitle">Complete reference for room management and customer service</p>
    </div>

    <!-- Starting a Rental -->
    <div class="guide-section">
      <h2 class="section-title">
        <span class="section-icon">
          <i class="fas fa-play"></i>
        </span>
        Starting a Rental
      </h2>
      
      <div class="step">
        <div class="step-title">
          <span class="step-number">1</span>
          Select Available Room
        </div>
        <div class="step-description">
          Click on any <strong>GREEN</strong> (Available) room card on the dashboard. Green rooms are ready for immediate use.
        </div>
      </div>
      
      <div class="step">
        <div class="step-title">
          <span class="step-number">2</span>
          Choose Duration
        </div>
        <div class="step-description">
          Select the initial rental time:
          <ul class="checklist">
            <li>30 Minutes</li>
            <li>1 Hour (most common)</li>
            <li>2 Hours</li>
            <li>3 Hours</li>
          </ul>
          The system will calculate the total cost based on the room rate.
        </div>
      </div>
      
      <div class="step">
        <div class="step-title">
          <span class="step-number">3</span>
          Confirm and Start
        </div>
        <div class="step-description">
          Review the total amount and click <strong>"Start Rental"</strong>. The room will immediately turn <strong>RED</strong> (Occupied) and the timer will begin.
        </div>
      </div>
      
      <div class="tip-box">
        <div class="tip-title">
          <i class="fas fa-lightbulb"></i> Tip
        </div>
        <div class="tip-text">
          Customers can extend their time directly from the tablet inside the room. Extensions will automatically be added to their bill.
        </div>
      </div>
    </div>

    <!-- Processing Checkout -->
    <div class="guide-section">
      <h2 class="section-title">
        <span class="section-icon">
          <i class="fas fa-receipt"></i>
        </span>
        Processing Checkout
      </h2>
      
      <div class="step">
        <div class="step-title">
          <span class="step-number">1</span>
          Open Occupied Room
        </div>
        <div class="step-description">
          Click on any <strong>RED</strong> (Occupied) room card. The billing modal will open automatically.
        </div>
      </div>
      
      <div class="step">
        <div class="step-title">
          <span class="step-number">2</span>
          Review Bill Details
        </div>
        <div class="step-description">
          Check the itemized bill which includes:
          <ul class="checklist">
            <li><strong>Room Rental:</strong> Base time and rate</li>
            <li><strong>Time Extensions:</strong> Any additional time added</li>
            <li><strong>Orders:</strong> Food and drinks ordered</li>
            <li><strong>Grand Total:</strong> Complete amount due</li>
          </ul>
        </div>
      </div>
      
      <div class="step">
        <div class="step-title">
          <span class="step-number">3</span>
          Select Payment Method
        </div>
        <div class="step-description">
          Choose the customer's payment method:
          <ul class="checklist">
            <li><strong>CASH:</strong> Physical money payment</li>
            <li><strong>GCASH:</strong> Mobile wallet payment</li>
          </ul>
        </div>
      </div>
      
      <div class="step">
        <div class="step-title">
          <span class="step-number">4</span>
          Process Payment
        </div>
        <div class="step-description">
          Click <strong>"Process Payment"</strong>. This will:
          <ul class="checklist">
            <li>Record the transaction</li>
            <li>End the rental</li>
            <li>Mark room for cleaning</li>
            <li>Add to daily sales report</li>
          </ul>
        </div>
      </div>
      
      <div class="warning-box">
        <div class="warning-title">
          <i class="fas fa-exclamation-triangle"></i> Important
        </div>
        <div class="warning-text">
          <strong>Always verify payment before processing!</strong> For GCASH payments, confirm that you've received the money in the business account before clicking "Process Payment".
        </div>
      </div>
    </div>

    <!-- Cash Handling -->
    <div class="guide-section">
      <h2 class="section-title">
        <span class="section-icon">
          <i class="fas fa-money-bill-wave"></i>
        </span>
        Cash Handling Procedures
      </h2>
      
      <div class="step">
        <div class="step-title">
          <i class="fas fa-calculator"></i> Calculating Change
        </div>
        <div class="step-description">
          <ul class="checklist">
            <li>Announce the total amount clearly</li>
            <li>Count the money received from customer</li>
            <li>Calculate change accurately</li>
            <li>Count change back to customer clearly</li>
            <li>Thank the customer</li>
          </ul>
        </div>
      </div>
      
      <div class="step">
        <div class="step-title">
          <i class="fas fa-shield-alt"></i> Security Best Practices
        </div>
        <div class="step-description">
          <ul class="checklist">
            <li>Keep large bills secure and out of sight</li>
            <li>Never leave the cash drawer open unattended</li>
            <li>Verify large bills for authenticity</li>
            <li>Keep small change available</li>
            <li>Count your drawer at start and end of shift</li>
          </ul>
        </div>
      </div>
      
      <div class="tip-box">
        <div class="tip-title">
          <i class="fas fa-lightbulb"></i> Pro Tip
        </div>
        <div class="tip-text">
          Always count change twice - once when calculating, and once when handing it to the customer. This prevents errors and builds customer trust.
        </div>
      </div>
    </div>

    <!-- Room Status Colors -->
    <div class="guide-section">
      <h2 class="section-title">
        <span class="section-icon">
          <i class="fas fa-palette"></i>
        </span>
        Room Status Colors
      </h2>
      
      <div class="status-grid">
        <div class="status-card available">
          <div class="status-icon"><i class="fas fa-check-circle"></i></div>
          <div class="status-label">Available</div>
          <div class="status-description">Ready to rent. Click to start a new rental.</div>
        </div>
        
        <div class="status-card occupied">
          <div class="status-icon"><i class="fas fa-users"></i></div>
          <div class="status-label">Occupied</div>
          <div class="status-description">Customer using room. Click to view bill or checkout.</div>
        </div>
        
        <div class="status-card cleaning">
          <div class="status-icon"><i class="fas fa-broom"></i></div>
          <div class="status-label">Cleaning</div>
          <div class="status-description">Being cleaned by staff. Cannot start new rental yet.</div>
        </div>
      </div>
    </div>

    <!-- Sales Reports -->
    <div class="guide-section">
      <h2 class="section-title">
        <span class="section-icon">
          <i class="fas fa-chart-line"></i>
        </span>
        Sales Reports
      </h2>
      
      <div class="step">
        <div class="step-title">
          <i class="fas fa-file-alt"></i> Accessing Reports
        </div>
        <div class="step-description">
          Click the <strong>"Sales"</strong> button in the header to view daily sales reports. You can:
          <ul class="checklist">
            <li>View total sales for any date</li>
            <li>See transaction count</li>
            <li>Check average bill amount</li>
            <li>Review payment method breakdown</li>
            <li>Track 7-day sales trends</li>
          </ul>
        </div>
      </div>
      
      <div class="tip-box">
        <div class="tip-title">
          <i class="fas fa-lightbulb"></i> End of Day
        </div>
        <div class="tip-text">
          At the end of your shift, review the daily sales report and verify it matches your cash drawer count. Report any discrepancies to the manager immediately.
        </div>
      </div>
    </div>

    <!-- Customer Service -->
    <div class="guide-section">
      <h2 class="section-title">
        <span class="section-icon">
          <i class="fas fa-heart"></i>
        </span>
        Excellent Customer Service
      </h2>
      
      <div class="status-grid">
        <div class="status-card green">
          <div class="status-icon"><i class="fas fa-smile"></i></div>
          <div class="status-label">Greet Warmly</div>
          <div class="status-description">Welcome every customer with a genuine smile</div>
        </div>
        
        <div class="status-card blue">
          <div class="status-icon"><i class="fas fa-comments"></i></div>
          <div class="status-label">Communicate Clearly</div>
          <div class="status-description">Explain charges and answer questions patiently</div>
        </div>
        
        <div class="status-card yellow">
          <div class="status-icon"><i class="fas fa-bolt"></i></div>
          <div class="status-label">Be Efficient</div>
          <div class="status-description">Process transactions quickly and accurately</div>
        </div>
        
        <div class="status-card purple">
          <div class="status-icon"><i class="fas fa-hands-helping"></i></div>
          <div class="status-label">Go Extra Mile</div>
          <div class="status-description">Offer suggestions and help when needed</div>
        </div>
      </div>
      
      <div class="warning-box">
        <div class="warning-title">
          <i class="fas fa-user-friends"></i> Remember
        </div>
        <div class="warning-text">
          You are often the first and last person customers interact with. Your attitude and service directly impact whether they'll return. Always be professional, friendly, and helpful!
        </div>
      </div>
    </div>

    <!-- Common Issues -->
    <div class="guide-section">
      <h2 class="section-title">
        <span class="section-icon">
          <i class="fas fa-tools"></i>
        </span>
        Handling Common Issues
      </h2>
      
      <div class="step">
        <div class="step-title">
          <i class="fas fa-question-circle"></i> Customer Disputes Bill
        </div>
        <div class="step-description">
          <ul class="checklist">
            <li>Show them the itemized bill on screen</li>
            <li>Explain each charge clearly</li>
            <li>Check if time extensions were added from tablet</li>
            <li>Call manager if customer is still unsatisfied</li>
            <li>Remain calm and professional</li>
          </ul>
        </div>
      </div>
      
      <div class="step">
        <div class="step-title">
          <i class="fas fa-credit-card"></i> Payment System Issues
        </div>
        <div class="step-description">
          <ul class="checklist">
            <li>For GCASH issues, verify payment on phone</li>
            <li>If system error, note transaction manually</li>
            <li>Inform manager immediately</li>
            <li>Apologize for inconvenience</li>
            <li>Offer alternative payment method</li>
          </ul>
        </div>
      </div>
      
      <div class="warning-box">
        <div class="warning-title">
          <i class="fas fa-phone-alt"></i> When in Doubt
        </div>
        <div class="warning-text">
          Never hesitate to call the manager or owner for help with difficult situations. It's better to ask than to make a costly mistake!
        </div>
      </div>
    </div>

    <!-- Order Management -->
    <div class="guide-section">
      <h2 class="section-title">
        <span class="section-icon">
          <i class="fas fa-utensils"></i>
        </span>
        Managing Food &amp; Drink Orders
      </h2>

      <div class="step">
        <div class="step-title">
          <span class="step-number">1</span>
          Receiving Orders
        </div>
        <div class="step-description">
          When a customer places an order from the room tablet or walks to the counter, it appears in the <strong>Active Orders</strong> panel on your dashboard. You will see:
          <ul class="checklist">
            <li>Room number</li>
            <li>Items ordered</li>
            <li>Total cost</li>
            <li>Amount the customer says they will pay</li>
            <li>Calculated change</li>
          </ul>
        </div>
      </div>

      <div class="step">
        <div class="step-title">
          <span class="step-number">2</span>
          Order Status Flow
        </div>
        <div class="step-description">
          Advance orders through the following statuses using the buttons on the dashboard:
          <ul class="checklist">
            <li><strong>NEW</strong> → Click "Start Preparing" when you begin preparing the order</li>
            <li><strong>PREPARING</strong> → Click "Ready to Deliver" when food is ready for pickup</li>
            <li><strong>READY TO DELIVER</strong> → Announce on radio for staff to pick up, then click "Delivering"</li>
            <li><strong>DELIVERING</strong> → Click "Mark Delivered" when staff confirms delivery and payment collected</li>
          </ul>
        </div>
      </div>

      <div class="tip-box">
        <div class="tip-title">
          <i class="fas fa-radio"></i> Radio Communication
        </div>
        <div class="tip-text">
          When an order is <strong>READY TO DELIVER</strong>, use the radio to notify staff. Staff will pick up the food, deliver it to the room, collect payment from the customer, and bring the money back to you. Then mark the order as <strong>DELIVERED</strong>.
        </div>
      </div>
    </div>

    <!-- Room Cleaning Management -->
    <div class="guide-section">
      <h2 class="section-title">
        <span class="section-icon">
          <i class="fas fa-broom"></i>
        </span>
        Room Cleaning Management
      </h2>

      <div class="step">
        <div class="step-title">
          <span class="step-number">1</span>
          Mark Room as Cleaning
        </div>
        <div class="step-description">
          After a customer checks out and payment is processed:
          <ul class="checklist">
            <li>Find the room in the <strong>Rooms</strong> panel (it will show OCCUPIED)</li>
            <li>Click the <strong>"Cleaning"</strong> button</li>
            <li>Notify staff via radio to clean the room</li>
            <li>Room turns BLUE (Cleaning status)</li>
          </ul>
        </div>
      </div>

      <div class="step">
        <div class="step-title">
          <span class="step-number">2</span>
          Mark Room as Available
        </div>
        <div class="step-description">
          Once staff signals via radio that cleaning is complete:
          <ul class="checklist">
            <li>Find the room in the <strong>Rooms</strong> panel (it will show CLEANING)</li>
            <li>Click the <strong>"Available"</strong> button</li>
            <li>Room turns GREEN — ready for the next customer!</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Back Button -->
    <div class="guide-section back-button-section">
      <button class="back-button" onclick="location.href='dashboard.php'">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
      </button>
    </div>
  </main>
</body>
</html>