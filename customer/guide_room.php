<?php
session_start();
require_once __DIR__ . '/../db.php';

// Check if customer has valid session (QR login)
if (!isset($_SESSION['customer_rental_id'])) { 
    header('Location: ../index.php'); 
    exit; 
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Customer Guide – Wannabees KTV</title>
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
    
    /* Main Content */
    main {
      padding: 30px;
      max-width: 1000px;
      margin: 0 auto;
    }
    
    .guide-container {
      background: white;
      border-radius: 16px;
      padding: 40px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    
    .page-title {
      font-size: 32px;
      font-weight: 700;
      margin-bottom: 10px;
      color: #2c2c2c;
      text-align: center;
    }
    
    .page-subtitle {
      font-size: 16px;
      color: #666;
      text-align: center;
      margin-bottom: 40px;
    }
    
    .section {
      margin-bottom: 40px;
    }
    
    .section-title {
      font-size: 24px;
      font-weight: 700;
      color: #f39c12;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .section-icon {
      width: 50px;
      height: 50px;
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 24px;
    }
    
    .content-text {
      font-size: 16px;
      line-height: 1.8;
      color: #555;
      margin-bottom: 20px;
    }
    
    .steps-list {
      list-style: none;
      padding: 0;
    }
    
    .step-item {
      background: #fff9e6;
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 15px;
      border-left: 4px solid #f5c542;
      display: flex;
      gap: 15px;
      align-items: start;
    }
    
    .step-number {
      width: 35px;
      height: 35px;
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 700;
      font-size: 18px;
      flex-shrink: 0;
    }
    
    .step-content {
      flex: 1;
    }
    
    .step-title {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 8px;
      color: #2c2c2c;
    }
    
    .step-description {
      font-size: 15px;
      color: #666;
      line-height: 1.6;
    }
    
    .tips-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }
    
    .tip-card {
      background: #f9f9f9;
      padding: 25px;
      border-radius: 12px;
      border-left: 4px solid #27ae60;
      transition: all 0.3s;
    }
    
    .tip-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .tip-icon {
      width: 45px;
      height: 45px;
      background: #27ae60;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      margin-bottom: 15px;
      font-size: 22px;
    }
    
    .tip-title {
      font-size: 16px;
      font-weight: 600;
      margin-bottom: 10px;
      color: #2c2c2c;
    }
    
    .tip-text {
      font-size: 14px;
      color: #666;
      line-height: 1.6;
    }
    
    .warning-box {
      background: #fff3cd;
      border: 2px solid #ffc107;
      border-radius: 12px;
      padding: 20px;
      margin-top: 30px;
      display: flex;
      gap: 15px;
    }
    
    .warning-icon {
      font-size: 24px;
      color: #ff9800;
    }
    
    .warning-content {
      flex: 1;
    }
    
    .warning-title {
      font-size: 18px;
      font-weight: 700;
      color: #ff9800;
      margin-bottom: 10px;
    }
    
    .warning-text {
      font-size: 15px;
      color: #856404;
      line-height: 1.6;
    }
    
    .help-box {
      background: #e3f2fd;
      border: 2px solid #2196f3;
      border-radius: 12px;
      padding: 25px;
      margin-top: 30px;
      text-align: center;
    }
    
    .help-title {
      font-size: 20px;
      font-weight: 700;
      color: #1976d2;
      margin-bottom: 10px;
    }
    
    .help-text {
      font-size: 15px;
      color: #0d47a1;
      margin-bottom: 15px;
    }
    
    .help-btn {
      padding: 12px 30px;
      background: #2196f3;
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .help-btn:hover {
      background: #1976d2;
      transform: scale(1.05);
    }
  </style>
</head>
<body>
  <header>
    <div class="header-left">
      <img src="../assets/images/KTVL.png" alt="logo">
      <div>
        <div class="header-title">Wannabees Family KTV</div>
        <div class="header-subtitle">Customer Guide</div>
      </div>
    </div>
    <div class="header-nav">
      <button class="nav-btn" onclick="location.href='room_tablet.php'" title="Back to Room">
        <i class="fas fa-home"></i>
      </button>
    </div>
  </header>

  <main>
    <div class="guide-container">
      <h1 class="page-title">Welcome to Your Room!</h1>
      <p class="page-subtitle">Here's everything you need to know to enjoy your time with us</p>

      <!-- How to Order -->
      <div class="section">
        <div class="section-title">
          <div class="section-icon"><i class="fas fa-utensils"></i></div>
          How to Order Food & Drinks
        </div>
        
        <ul class="steps-list">
          <li class="step-item">
            <div class="step-number">1</div>
            <div class="step-content">
              <div class="step-title">Browse the Menu</div>
              <div class="step-description">Scroll through our drinks and snacks sections to see what's available</div>
            </div>
          </li>
          
          <li class="step-item">
            <div class="step-number">2</div>
            <div class="step-content">
              <div class="step-title">Select Quantity</div>
              <div class="step-description">Use the + and - buttons to choose how many items you want</div>
            </div>
          </li>
          
          <li class="step-item">
            <div class="step-number">3</div>
            <div class="step-content">
              <div class="step-title">Add to Cart</div>
              <div class="step-description">Click "Add to Cart" for each item you want to order</div>
            </div>
          </li>
          
          <li class="step-item">
            <div class="step-number">4</div>
            <div class="step-content">
              <div class="step-title">Place Your Order</div>
              <div class="step-description">When ready, click the green "Place Order" button at the bottom</div>
            </div>
          </li>
          
          <li class="step-item">
            <div class="step-number">5</div>
            <div class="step-content">
              <div class="step-title">Wait for Delivery</div>
              <div class="step-description">Your order will be prepared and delivered to your room shortly!</div>
            </div>
          </li>
        </ul>
      </div>

      <!-- How to Extend Time -->
      <div class="section">
        <div class="section-title">
          <div class="section-icon"><i class="fas fa-clock"></i></div>
          How to Extend Your Time
        </div>
        
        <p class="content-text">
          Running out of time? No problem! You can easily extend your karaoke session right from your tablet.
        </p>
        
        <ul class="steps-list">
          <li class="step-item">
            <div class="step-number">1</div>
            <div class="step-content">
              <div class="step-title">Check Time Remaining</div>
              <div class="step-description">Look at the orange box showing your remaining time</div>
            </div>
          </li>
          
          <li class="step-item">
            <div class="step-number">2</div>
            <div class="step-content">
              <div class="step-title">Click "Extend Time"</div>
              <div class="step-description">Press the "Extend Time" button in the time remaining box</div>
            </div>
          </li>
          
          <li class="step-item">
            <div class="step-number">3</div>
            <div class="step-content">
              <div class="step-title">Choose Duration</div>
              <div class="step-description">Select 30 minutes, 1 hour, 2 hours, or 3 hours</div>
            </div>
          </li>
          
          <li class="step-item">
            <div class="step-number">4</div>
            <div class="step-content">
              <div class="step-title">Confirm Extension</div>
              <div class="step-description">Click "Extend" to add the time. The cost will be added to your bill</div>
            </div>
          </li>
        </ul>
      </div>

      <!-- Tips for Best Experience -->
      <div class="section">
        <div class="section-title">
          <div class="section-icon"><i class="fas fa-lightbulb"></i></div>
          Tips for the Best Experience
        </div>
        
        <div class="tips-grid">
          <div class="tip-card">
            <div class="tip-icon"><i class="fas fa-volume-up"></i></div>
            <div class="tip-title">Adjust Volume</div>
            <div class="tip-text">Use the karaoke machine controls to adjust microphone and music volume to your preference</div>
          </div>
          
          <div class="tip-card">
            <div class="tip-icon"><i class="fas fa-bell"></i></div>
            <div class="tip-title">Order Early</div>
            <div class="tip-text">Place your food orders at the start so they arrive while you're singing!</div>
          </div>
          
          <div class="tip-card">
            <div class="tip-icon"><i class="fas fa-eye"></i></div>
            <div class="tip-title">Watch the Timer</div>
            <div class="tip-text">Keep an eye on your remaining time and extend early if needed</div>
          </div>
          
          <div class="tip-card">
            <div class="tip-icon"><i class="fas fa-hand-sparkles"></i></div>
            <div class="tip-title">Keep It Clean</div>
            <div class="tip-text">Please help us maintain a clean room for the next guests</div>
          </div>
          
          <div class="tip-card">
            <div class="tip-icon"><i class="fas fa-star"></i></div>
            <div class="tip-title">Have Fun!</div>
            <div class="tip-text">Don't be shy! Sing your heart out and enjoy your time with friends and family</div>
          </div>
          
          <div class="tip-card">
            <div class="tip-icon"><i class="fas fa-receipt"></i></div>
            <div class="tip-title">Check Your Bill</div>
            <div class="tip-text">Tap "View Bill" anytime to see your current charges</div>
          </div>
        </div>
      </div>

      <!-- Important Notes -->
      <div class="warning-box">
        <div class="warning-icon">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="warning-content">
          <div class="warning-title">Important Notes</div>
          <div class="warning-text">
            • Payment is processed at the front desk when you're ready to leave<br>
            • All food and drink orders are final - please order carefully<br>
            • Please inform staff immediately if you encounter any technical issues<br>
            • Outside food and drinks are not allowed
          </div>
        </div>
      </div>

      <!-- Need Help -->
      <div class="help-box">
        <div class="help-title">Need Assistance?</div>
        <div class="help-text">Our friendly staff is here to help! Just step outside and call for assistance.</div>
        <button class="help-btn" onclick="location.href='room_tablet.php'">
          <i class="fas fa-arrow-left"></i> Back to Room
        </button>
      </div>
    </div>
  </main>
</body>
</html>