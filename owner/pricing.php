<?php
// pricing.php - Enhanced Room Pricing Management
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 1) { 
    header('Location: ../index.php'); 
    exit; 
}
$ownerName = htmlspecialchars($_SESSION['display_name'] ?: $_SESSION['username']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_type_id = isset($_POST['room_type_id']) ? intval($_POST['room_type_id']) : 0;
    $per_hour = isset($_POST['price_per_hour']) ? floatval($_POST['price_per_hour']) : 0;
    $per_30 = isset($_POST['price_per_30min']) ? floatval($_POST['price_per_30min']) : 0;
    
    if ($room_type_id > 0) {
        $stmt = $mysqli->prepare("UPDATE room_types SET price_per_hour = ?, price_per_30min = ? WHERE room_type_id = ?");
        $stmt->bind_param('ddi', $per_hour, $per_30, $room_type_id);
        $stmt->execute();
        $stmt->close();
        header('Location: pricing.php?success=1');
        exit;
    }
}

// Disable caching of database queries
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragm: no-cache");
header("Expires: 0");

$res = $mysqli->query("SELECT room_type_id, type_name, price_per_hour, price_per_30min FROM room_types ORDER BY room_type_id ASC");
$types = [];
if ($res) while ($r = $res->fetch_assoc()) $types[] = $r;

// Get room count for each type
foreach ($types as $key => $type) {
    $result = $mysqli->query("SELECT COUNT(*) as count FROM rooms WHERE room_type_id = {$type['room_type_id']}");
    $types[$key]['room_count'] = $result->fetch_assoc()['count'];
}

$success = isset($_GET['success']);

// Debug: Show what's in $types
error_log("DEBUG Pricing - Types count: " . count($types));
error_log("DEBUG Pricing - Types data: " . json_encode($types));

unset($key, $type);  // Clear the loop variables

// Debug: Log what we're fetching
error_log("DEBUG: Fetched room types: " . json_encode($types));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta http-equiv="Expires" content="0" />
  <title>Pricing Management — Wannabees KTV</title>
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
      padding: 15px;
      max-width: 1200px;
      margin: 0 auto;
      height: calc(100vh - 60px);
      overflow-y: auto;
    }
    
    .page-header {
      margin-bottom: 15px;
    }
    
    .page-title {
      font-size: 20px;
      font-weight: 700;
      color: #2c2c2c;
      margin-bottom: 5px;
    }
    
    .page-description {
      color: #666;
      font-size: 13px;
    }
    
    .success-message {
      background: #d4edda;
      border: 1px solid #c3e6cb;
      color: #155724;
      padding: 10px 15px;
      border-radius: 6px;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
    }
    
    .pricing-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 15px;
    }
    
    .pricing-card {
      background: white;
      border-radius: 10px;
      padding: 15px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      transition: all 0.2s ease;
      position: relative;
      overflow: hidden;
    }
    
    .pricing-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .pricing-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(90deg, #f5c542, #f2a20a);
    }
    
    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
      gap: 8px;
      flex-wrap: wrap;
    }
    
    .room-type-name {
      font-size: 16px;
      font-weight: 700;
      color: #2c2c2c;
    }
    
    .room-count-badge {
      background: #e8f5e9;
      color: #2e7d32;
      padding: 3px 8px;
      border-radius: 12px;
      font-size: 11px;
      font-weight: 600;
    }
    
    .current-pricing {
      background: #f9f9f9;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 12px;
    }
    
    .pricing-columns {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }
    
    .pricing-left {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    
    .pricing-right {
      display: flex;
      flex-direction: column;
      gap: 6px;
      align-items: flex-end;
    }
    
    .pricing-label {
      font-size: 11px;
      color: #666;
      font-weight: 600;
    }
    
    .pricing-value {
      font-size: 16px;
      font-weight: 700;
      color: #27ae60;
    }
    
    form {
      margin-top: 10px;
    }
    
    .form-row {
      display: grid;
      grid-template-columns: 1fr;
      gap: 8px;
      margin-bottom: 10px;
    }
    
    .form-group {
      display: flex;
      flex-direction: column;
    }
    
    .form-group label {
      font-size: 11px;
      font-weight: 600;
      color: #666;
      margin-bottom: 4px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .form-group input {
      padding: 8px 10px;
      border: 2px solid #e0e0e0;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      transition: all 0.2s;
    }
    
    .form-group input:focus {
      outline: none;
      border-color: #f2a20a;
      box-shadow: 0 0 0 3px rgba(242,162,10,0.06);
    }
    
    .btn-update {
      width: 100%;
      padding: 10px;
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      border: none;
      border-radius: 6px;
      color: white;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    
    .btn-update:hover {
      transform: translateY(-1px);
      box-shadow: 0 3px 10px rgba(242,162,10,0.3);
    }
    
    .info-box {
      background: #e3f2fd;
      border-left: 3px solid #2196f3;
      padding: 12px 15px;
      border-radius: 6px;
      margin-top: 20px;
    }
    
    .info-box h4 {
      color: #1976d2;
      margin-bottom: 6px;
      font-size: 13px;
    }
    
    .info-box ul {
      margin-left: 18px;
      color: #555;
      font-size: 12px;
      line-height: 1.6;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      .header-subtitle {
        display: block;
      }
      
      .pricing-grid {
        grid-template-columns: 1fr;
      }
      
      .page-title {
        font-size: 18px;
      }
      
      .page-description {
        font-size: 12px;
      }
      
      .pricing-columns {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .pricing-right {
        align-items: flex-start;
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
      <button class="btn btn-primary">
        <i class="fas fa-tag"></i> <span>Pricing</span>
      </button>
      <button class="btn" onclick="location.href='users.php'">
        <i class="fas fa-users"></i> <span>Users</span>
      </button>
      <button class="btn" onclick="location.href='guide.php'">
        <i class="fas fa-book"></i> <span>Guide</span>
      </button>
      <button class="btn" onclick="location.href='settings.php'">
        <i class="fas fa-cog"></i> <span>Settings</span>
      </button>
      <form method="post" action="api/auth/logout.php" class="logout-form">
        <button type="button" class="btn btn-danger" onclick="logoutNow(this)">
          <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </button>
      </form>
    </div>
  </header>

  <main>
    <div class="page-header">
      <h1 class="page-title">Room Pricing Management</h1>
      <p class="page-description">Configure hourly and 30-minute rates for each room type</p>
    </div>

    <?php if ($success): ?>
      <div class="success-message">
        <i class="fas fa-check-circle"></i>
        <span>Pricing updated successfully!</span>
      </div>
    <?php endif; ?>

    <div class="pricing-grid">
      <?php foreach ($types as $type): ?>
        <div class="pricing-card">
          <div class="card-header">
            <h2 class="room-type-name"><?= htmlspecialchars($type['type_name']) ?></h2>
            <span class="room-count-badge">
              <i class="fas fa-door-open"></i> <?= $type['room_count'] ?> rooms
            </span>
          </div>

          <div class="current-pricing">
            <div class="pricing-columns">
              <div class="pricing-left">
                <div class="pricing-label">Per Hour</div>
                <div class="pricing-label">Per 30 Minutes</div>
              </div>
              <div class="pricing-right">
                <div class="pricing-value">₱<?= number_format($type['price_per_hour'], 2) ?></div>
                <div class="pricing-value">₱<?= number_format($type['price_per_30min'], 2) ?></div>
              </div>
            </div>
          </div>

          <form method="post">
            <input type="hidden" name="room_type_id" value="<?= $type['room_type_id'] ?>">
            
            <div class="form-row">
              <div class="form-group">
                <label for="hour_<?= $type['room_type_id'] ?>">
                  <i class="fas fa-clock"></i> Per Hour (₱)
                </label>
                <input 
                  type="number" 
                  id="hour_<?= $type['room_type_id'] ?>" 
                  name="price_per_hour" 
                  step="0.01" 
                  min="0" 
                  value="<?= number_format($type['price_per_hour'], 2, '.', '') ?>"
                  required
                >
              </div>
              
              <div class="form-group">
                <label for="half_<?= $type['room_type_id'] ?>">
                  <i class="fas fa-clock"></i> Per 30 Min (₱)
                </label>
                <input 
                  type="number" 
                  id="half_<?= $type['room_type_id'] ?>" 
                  name="price_per_30min" 
                  step="0.01" 
                  min="0" 
                  value="<?= number_format($type['price_per_30min'], 2, '.', '') ?>"
                  required
                >
              </div>
            </div>

            <button type="submit" class="btn-update">
              <i class="fas fa-save"></i> Update Pricing
            </button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="info-box">
      <h4><i class="fas fa-info-circle"></i> Pricing Guidelines</h4>
      <ul>
        <li>Price changes take effect immediately for new rentals</li>
        <li>Active rentals will continue at their original rates</li>
        <li>30-minute rate should typically be 50-55% of hourly rate</li>
        <li>Consider competitive pricing for your area</li>
        <li>Premium rooms should be priced higher to reflect better amenities</li>
      </ul>
    </div>
  </main>

  <script>
    // Auto-calculate 30min rate based on hourly (optional helper)
    document.querySelectorAll('input[name="price_per_hour"]').forEach(input => {
      input.addEventListener('input', function() {
        const halfInput = this.closest('form').querySelector('input[name="price_per_30min"]');
        if (halfInput && !halfInput.value) {
          halfInput.value = (parseFloat(this.value) / 2).toFixed(2);
        }
      });
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