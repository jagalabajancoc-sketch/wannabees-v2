<?php
// dashboard.php - Comprehensive Room Dashboard (for Customer/Cashier roles)
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$username = htmlspecialchars($_SESSION['username']);
$displayName = htmlspecialchars($_SESSION['display_name'] ?: $_SESSION['username']);
$room_id = intval($_SESSION['room_id']);
$role_id = intval($_SESSION['role_id']);

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

// Fetch active rental for this room
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
while ($p = $productsRes->fetch_assoc()) $products[] = $p;

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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Room <?= $room['room_number'] ?> — Dashboard</title>
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
    }
    
    /* Header */
    header {
      background: #f5f5f5;
      padding: 15px 30px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
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
      font-size: 18px;
      font-weight: 600;
    }
    
    .header-subtitle {
      font-size: 13px;
      color: #666;
    }
    
    .btn {
      padding: 10px 18px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.3s ease;
      background: #e8e8e8;
      color: #333;
    }
    
    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .btn-danger {
      background: #e74c3c;
      color: white;
    }
    
    .btn-primary {
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      color: white;
    }
    
    .btn-success {
      background: #27ae60;
      color: white;
    }
    
    .btn-info {
      background: #3498db;
      color: white;
    }
    
    /* Main Content */
    main {
      padding: 25px 30px;
      max-width: 1200px;
      margin: 0 auto;
    }
    
    /* Room Status Banner */
    .status-banner {
      background: white;
      padding: 30px;
      border-radius: 12px;
      margin-bottom: 30px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .status-banner.no-rental {
      background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
      color: white;
    }
    
    .status-banner.active-rental {
      background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
      color: white;
    }
    
    .status-info h2 {
      font-size: 28px;
      margin-bottom: 10px;
    }
    
    .status-info p {
      font-size: 16px;
      opacity: 0.9;
    }
    
    .timer-display {
      text-align: right;
    }
    
    .timer-label {
      font-size: 14px;
      opacity: 0.9;
      margin-bottom: 8px;
    }
    
    .timer-value {
      font-size: 48px;
      font-weight: 700;
      font-family: 'Courier New', monospace;
      letter-spacing: 2px;
    }
    
    /* Action Cards */
    .action-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .action-card {
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      text-align: center;
      transition: all 0.3s;
      cursor: pointer;
    }
    
    .action-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .action-card.disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    
    .action-card.disabled:hover {
      transform: none;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }
    
    .action-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      color: white;
      font-size: 36px;
    }
    
    .action-card.extend .action-icon {
      background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    }
    
    .action-card.bill .action-icon {
      background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
    }
    
    .action-title {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 10px;
      color: #2c2c2c;
    }
    
    .action-description {
      font-size: 14px;
      color: #666;
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
      overflow-y: auto;
    }
    
    .modal.active {
      display: flex;
    }
    
    .modal-content {
      background: white;
      border-radius: 16px;
      padding: 30px;
      width: 90%;
      max-width: 600px;
      max-height: 90vh;
      overflow-y: auto;
      margin: 20px;
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
      padding-bottom: 15px;
      border-bottom: 2px solid #f0f0f0;
    }
    
    .modal-title {
      font-size: 24px;
      font-weight: 700;
    }
    
    .modal-close {
      background: none;
      border: none;
      font-size: 28px;
      cursor: pointer;
      color: #999;
    }
    
    /* Order Form */
    .products-grid {
      display: grid;
      gap: 15px;
      margin-bottom: 25px;
    }
    
    .product-item {
      background: #f9f9f9;
      padding: 20px;
      border-radius: 10px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border: 2px solid transparent;
      transition: all 0.3s;
    }
    
    .product-item:hover {
      border-color: #f2a20a;
    }
    
    .product-info {
      flex: 1;
    }
    
    .product-name {
      font-size: 16px;
      font-weight: 600;
      margin-bottom: 5px;
    }
    
    .product-price {
      font-size: 18px;
      color: #27ae60;
      font-weight: 700;
    }
    
    .product-stock {
      font-size: 12px;
      color: #999;
      margin-top: 5px;
    }
    
    .product-quantity {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .qty-btn {
      width: 40px;
      height: 40px;
      border: none;
      border-radius: 8px;
      background: #e8e8e8;
      cursor: pointer;
      font-size: 18px;
      font-weight: 700;
      transition: all 0.3s;
    }
    
    .qty-btn:hover {
      background: #f2a20a;
      color: white;
    }
    
    .qty-input {
      width: 60px;
      height: 40px;
      text-align: center;
      border: 2px solid #e0e0e0;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
    }
    
    .order-summary {
      background: #fff9e6;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      border: 2px solid #f5c542;
    }
    
    .summary-row {
      display: flex;
      justify-content: space-between;
      padding: 10px 0;
      font-size: 16px;
    }
    
    .summary-row.total {
      border-top: 2px solid #f2a20a;
      margin-top: 10px;
      padding-top: 15px;
      font-size: 20px;
      font-weight: 700;
      color: #f2a20a;
    }
    
    /* Extension Options */
    .extension-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 15px;
      margin-bottom: 25px;
    }
    
    .extension-option {
      background: #f9f9f9;
      padding: 25px;
      border-radius: 10px;
      text-align: center;
      cursor: pointer;
      border: 3px solid transparent;
      transition: all 0.3s;
    }
    
    .extension-option:hover {
      border-color: #f2a20a;
      transform: scale(1.05);
    }
    
    .extension-option.selected {
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      color: white;
      border-color: #f2a20a;
    }
    
    .extension-time {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 8px;
    }
    
    .extension-price {
      font-size: 18px;
      font-weight: 600;
    }
    
    /* Bill Display */
    .bill-section {
      margin-bottom: 20px;
    }
    
    .bill-section h4 {
      font-size: 16px;
      color: #666;
      margin-bottom: 15px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .bill-row {
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
      border-bottom: 1px solid #f0f0f0;
    }
    
    .bill-label {
      color: #666;
    }
    
    .bill-value {
      font-weight: 600;
    }
    
    .grand-total {
      background: #fff9e6;
      padding: 20px;
      border-radius: 10px;
      margin: 20px 0;
      border: 2px solid #f5c542;
    }
    
    .grand-total-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .grand-total-label {
      font-size: 18px;
      font-weight: 600;
    }
    
    .grand-total-amount {
      font-size: 32px;
      font-weight: 700;
      color: #f2a20a;
    }
    
    .modal-actions {
      display: flex;
      gap: 12px;
      margin-top: 25px;
    }
    
    .btn-modal {
      flex: 1;
      padding: 16px;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s;
    }
    
    .btn-cancel {
      background: #e8e8e8;
      color: #333;
    }
    
    .btn-confirm {
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      color: white;
    }
    
    .btn-confirm:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(242,162,10,0.4);
    }
    
    .empty-message {
      text-align: center;
      padding: 40px 20px;
      color: #999;
    }
    
    .empty-message i {
      font-size: 48px;
      margin-bottom: 15px;
      color: #ddd;
    }
  </style>
</head>
<body>
  <header>
    <div class="header-left">
      <img src="../assets/images/KTVL.png" alt="logo">
      <div>
        <div class="header-title">Room <?= $room['room_number'] ?> — <?= htmlspecialchars($room['type_name']) ?></div>
        <div class="header-subtitle"><?= $displayName ?> (<?= $role_id === 3 ? 'Cashier' : 'Customer' ?>)</div>
      </div>
    </div>
    <form action="../auth/logout.php" method="post">
      <button class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</button>
    </form>
  </header>

  <main>
    <!-- Status Banner -->
    <div class="status-banner <?= $rental ? 'active-rental' : 'no-rental' ?>">
      <div class="status-info">
        <h2><?= $rental ? 'Active Rental' : 'No Active Rental' ?></h2>
        <p>
          <?php if ($rental): ?>
            Started: <?= date('g:i A', strtotime($rental['started_at'])) ?>
            &nbsp;|&nbsp;
            Duration: <?= $rental['total_minutes'] ?> minutes
          <?php else: ?>
            Room is currently <?= strtolower($room['status']) ?>
          <?php endif; ?>
        </p>
      </div>
      
      <?php if ($rental): ?>
        <div class="timer-display">
          <div class="timer-label">Time Remaining</div>
          <div class="timer-value" id="timerDisplay">--:--:--</div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Action Cards -->
    <div class="action-grid">
      <div class="action-card <?= !$rental ? 'disabled' : '' ?>" onclick="<?= $rental ? 'openOrderModal()' : '' ?>">
        <div class="action-icon">
          <i class="fas fa-utensils"></i>
        </div>
        <div class="action-title">Order Food & Drinks</div>
        <div class="action-description">
          <?= $rental ? 'Browse menu and place orders' : 'No active rental' ?>
        </div>
      </div>

      <div class="action-card extend <?= !$rental ? 'disabled' : '' ?>" onclick="<?= $rental ? 'openExtendModal()' : '' ?>">
        <div class="action-icon">
          <i class="fas fa-clock"></i>
        </div>
        <div class="action-title">Extend Time</div>
        <div class="action-description">
          <?= $rental ? 'Add more time to your rental' : 'No active rental' ?>
        </div>
      </div>

      <div class="action-card bill <?= !$rental ? 'disabled' : '' ?>" onclick="<?= $rental ? 'openBillModal()' : '' ?>">
        <div class="action-icon">
          <i class="fas fa-receipt"></i>
        </div>
        <div class="action-title">View Bill</div>
        <div class="action-description">
          <?= $rental ? 'See current charges and total' : 'No active rental' ?>
        </div>
      </div>
    </div>
  </main>

  <!-- Order Modal -->
  <div id="orderModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title">Order Food & Drinks</h3>
        <button class="modal-close" onclick="closeOrderModal()">×</button>
      </div>

      <div class="products-grid" id="productsGrid">
        <?php foreach ($products as $p): ?>
          <div class="product-item">
            <div class="product-info">
              <div class="product-name"><?= htmlspecialchars($p['product_name']) ?></div>
              <div class="product-price">₱<?= number_format($p['price'], 2) ?></div>
              <div class="product-stock">
                <?= $p['stock_quantity'] > 0 ? $p['stock_quantity'] . ' in stock' : 'Out of stock' ?>
              </div>
            </div>
            <div class="product-quantity">
              <button class="qty-btn" onclick="decrementQty(<?= $p['product_id'] ?>)">−</button>
              <input type="number" 
                     class="qty-input" 
                     id="qty_<?= $p['product_id'] ?>" 
                     value="0" 
                     min="0" 
                     max="<?= $p['stock_quantity'] ?>"
                     data-price="<?= $p['price'] ?>"
                     onchange="updateOrderTotal()">
              <button class="qty-btn" onclick="incrementQty(<?= $p['product_id'] ?>, <?= $p['stock_quantity'] ?>)">+</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="order-summary">
        <div class="summary-row">
          <span>Subtotal:</span>
          <span id="orderSubtotal">₱0.00</span>
        </div>
        <div class="summary-row total">
          <span>Total:</span>
          <span id="orderTotal">₱0.00</span>
        </div>
      </div>

      <div class="modal-actions">
        <button class="btn-modal btn-cancel" onclick="closeOrderModal()">Cancel</button>
        <button class="btn-modal btn-confirm" onclick="confirmOrder()">
          <i class="fas fa-check"></i> Place Order
        </button>
      </div>
    </div>
  </div>

  <!-- Extend Time Modal -->
  <div id="extendModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title">Extend Time</h3>
        <button class="modal-close" onclick="closeExtendModal()">×</button>
      </div>

      <div class="extension-grid" id="extensionGrid">
        <?php
        $price30 = floatval($room['price_per_30min']);
        $extensions = [
          ['minutes' => 30, 'label' => '30 Minutes', 'price' => $price30],
          ['minutes' => 60, 'label' => '1 Hour', 'price' => $price30 * 2],
          ['minutes' => 120, 'label' => '2 Hours', 'price' => $price30 * 4],
          ['minutes' => 180, 'label' => '3 Hours', 'price' => $price30 * 6]
        ];
        foreach ($extensions as $ext):
        ?>
          <div class="extension-option" data-minutes="<?= $ext['minutes'] ?>" data-price="<?= $ext['price'] ?>" onclick="selectExtension(this)">
            <div class="extension-time"><?= $ext['label'] ?></div>
            <div class="extension-price">₱<?= number_format($ext['price'], 2) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="modal-actions">
        <button class="btn-modal btn-cancel" onclick="closeExtendModal()">Cancel</button>
        <button class="btn-modal btn-confirm" onclick="confirmExtension()" id="extendConfirmBtn" disabled>
          <i class="fas fa-check"></i> Confirm Extension
        </button>
      </div>
    </div>
  </div>

  <!-- Bill Modal -->
  <div id="billModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title">Current Bill</h3>
        <button class="modal-close" onclick="closeBillModal()">×</button>
      </div>

      <div id="billContent">
        <div class="empty-message">
          <i class="fas fa-spinner fa-spin"></i>
          <p>Loading bill...</p>
        </div>
      </div>

      <div class="modal-actions">
        <button class="btn-modal btn-cancel" onclick="closeBillModal()">Close</button>
      </div>
    </div>
  </div>

  <script>
    const roomId = <?= json_encode($room_id) ?>;
    const hasRental = <?= $rental ? 'true' : 'false' ?>;
    
    <?php if ($rental): ?>
    const rentalId = <?= $rental['rental_id'] ?>;
    const startedAt = "<?= $rental['started_at'] ?>";
    const totalMinutes = <?= $rental['total_minutes'] ?>;
    
    // Timer countdown
    function updateTimer() {
      const start = new Date(startedAt);
      const end = new Date(start.getTime() + totalMinutes * 60000);
      const now = new Date();
      const remaining = Math.max(0, Math.floor((end - now) / 1000));
      
      const hours = Math.floor(remaining / 3600);
      const minutes = Math.floor((remaining % 3600) / 60);
      const seconds = remaining % 60;
      
      const display = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
      document.getElementById('timerDisplay').textContent = display;
      
      // Change color if time is running out
      if (remaining < 300) { // Less than 5 minutes
        document.getElementById('timerDisplay').style.color = '#e74c3c';
      }
    }
    
    updateTimer();
    setInterval(updateTimer, 1000);
    <?php endif; ?>

    // Order Modal Functions
    function openOrderModal() {
      if (!hasRental) return;
      document.getElementById('orderModal').classList.add('active');
      updateOrderTotal();
    }

    function closeOrderModal() {
      document.getElementById('orderModal').classList.remove('active');
      // Reset quantities
      document.querySelectorAll('.qty-input').forEach(input => input.value = 0);
      updateOrderTotal();
    }

    function incrementQty(productId, max) {
      const input = document.getElementById('qty_' + productId);
      if (parseInt(input.value) < max) {
        input.value = parseInt(input.value) + 1;
        updateOrderTotal();
      }
    }

    function decrementQty(productId) {
      const input = document.getElementById('qty_' + productId);
      if (parseInt(input.value) > 0) {
        input.value = parseInt(input.value) - 1;
        updateOrderTotal();
      }
    }

    function updateOrderTotal() {
      let total = 0;
      document.querySelectorAll('.qty-input').forEach(input => {
        const qty = parseInt(input.value) || 0;
        const price = parseFloat(input.dataset.price) || 0;
        total += qty * price;
      });
      
      document.getElementById('orderSubtotal').textContent = '₱' + total.toFixed(2);
      document.getElementById('orderTotal').textContent = '₱' + total.toFixed(2);
    }

    async function confirmOrder() {
      const items = [];
      document.querySelectorAll('.qty-input').forEach(input => {
        const qty = parseInt(input.value) || 0;
        if (qty > 0) {
          items.push({
            product_id: parseInt(input.id.replace('qty_', '')),
            quantity: qty
          });
        }
      });

      if (items.length === 0) {
        alert('Please select at least one item');
        return;
      }

      const formData = new FormData();
      formData.append('room_id', roomId);
      formData.append('items', JSON.stringify(items));

      try {
        const res = await fetch('../api/orders/add_order.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();

        if (data.success) {
          alert('Order placed successfully!\nTotal: ₱' + parseFloat(data.order_total).toFixed(2));
          closeOrderModal();
        } else {
          alert('Error: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Network error: ' + err.message);
      }
    }

    // Extension Modal Functions
    let selectedExtensionMinutes = 0;
    let selectedExtensionPrice = 0;

    function openExtendModal() {
      if (!hasRental) return;
      document.getElementById('extendModal').classList.add('active');
    }

    function closeExtendModal() {
      document.getElementById('extendModal').classList.remove('active');
      document.querySelectorAll('.extension-option').forEach(opt => opt.classList.remove('selected'));
      selectedExtensionMinutes = 0;
      selectedExtensionPrice = 0;
      document.getElementById('extendConfirmBtn').disabled = true;
    }

    function selectExtension(element) {
      document.querySelectorAll('.extension-option').forEach(opt => opt.classList.remove('selected'));
      element.classList.add('selected');
      selectedExtensionMinutes = parseInt(element.dataset.minutes);
      selectedExtensionPrice = parseFloat(element.dataset.price);
      document.getElementById('extendConfirmBtn').disabled = false;
    }

    async function confirmExtension() {
      if (selectedExtensionMinutes === 0) return;

      const formData = new FormData();
      formData.append('rental_id', rentalId);
      formData.append('minutes', selectedExtensionMinutes);

      try {
        const res = await fetch('../api/rooms/extend_time.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();

        if (data.success) {
          alert('Time extended by ' + selectedExtensionMinutes + ' minutes!\nCost: ₱' + selectedExtensionPrice.toFixed(2));
          location.reload();
        } else {
          alert('Error: ' + (data.error || 'Unknown error'));
        }
      } catch (err) {
        alert('Network error: ' + err.message);
      }
    }

    // Bill Modal Functions
    async function openBillModal() {
      if (!hasRental) return;
      document.getElementById('billModal').classList.add('active');
      
      try {
        const res = await fetch('../api/billing/get_bill.php?rental_id=' + rentalId);
        const data = await res.json();

        if (data.success) {
          renderBill(data);
        } else {
          document.getElementById('billContent').innerHTML = '<div class="empty-message"><i class="fas fa-exclamation-triangle"></i><p>Error loading bill</p></div>';
        }
      } catch (err) {
        document.getElementById('billContent').innerHTML = '<div class="empty-message"><i class="fas fa-exclamation-triangle"></i><p>Network error</p></div>';
      }
    }

    function closeBillModal() {
      document.getElementById('billModal').classList.remove('active');
    }

    function renderBill(data) {
      const { bill, rental, orders, extensions } = data;
      
      let html = '<div class="bill-section">';
      html += '<h4>Room Rental</h4>';
      html += '<div class="bill-row"><span class="bill-label">Room Type:</span><span class="bill-value">' + (rental.type_name || 'N/A') + '</span></div>';
      html += '<div class="bill-row"><span class="bill-label">Duration:</span><span class="bill-value">' + rental.total_minutes + ' minutes</span></div>';
      html += '<div class="bill-row"><span class="bill-label">Cost:</span><span class="bill-value">₱' + parseFloat(bill.total_room_cost).toFixed(2) + '</span></div>';
      html += '</div>';

      if (extensions && extensions.length > 0) {
        html += '<div class="bill-section">';
        html += '<h4>Time Extensions</h4>';
        extensions.forEach(ext => {
          html += '<div class="bill-row"><span class="bill-label">' + ext.minutes_added + ' minutes</span><span class="bill-value">₱' + parseFloat(ext.cost).toFixed(2) + '</span></div>';
        });
        html += '</div>';
      }

      if (orders && orders.length > 0) {
        html += '<div class="bill-section">';
        html += '<h4>Orders</h4>';
        orders.forEach(item => {
          const lineTotal = parseFloat(item.price) * parseInt(item.quantity);
          html += '<div class="bill-row"><span class="bill-label">' + item.product_name + ' x' + item.quantity + '</span><span class="bill-value">₱' + lineTotal.toFixed(2) + '</span></div>';
        });
        html += '</div>';
      }

      html += '<div class="grand-total">';
      html += '<div class="grand-total-row">';
      html += '<span class="grand-total-label">Grand Total:</span>';
      html += '<span class="grand-total-amount">₱' + parseFloat(bill.grand_total).toFixed(2) + '</span>';
      html += '</div>';
      html += '</div>';

      document.getElementById('billContent').innerHTML = html;
    }

    // Auto-refresh every 30 seconds
    setInterval(() => {
      location.reload();
    }, 30000);
  </script>
</body>
</html>