<?php
// inventory.php - Enhanced Inventory Management
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 1) { 
    header('Location: ../index.php'); 
    exit; 
}
$ownerName = htmlspecialchars($_SESSION['display_name'] ?: $_SESSION['username']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $name = trim($_POST['product_name']);
    $price = floatval($_POST['price']);
    $qty = intval($_POST['stock_quantity']);
    $active = isset($_POST['is_active']) ? 1 : 0;
    $category = trim($_POST['category']);
    
    if ($id > 0) {
        $stmt = $mysqli->prepare("UPDATE products SET product_name = ?, price = ?, stock_quantity = ?, is_active = ?, Category = ? WHERE product_id = ?");
        $stmt->bind_param('sdiisi', $name, $price, $qty, $active, $category, $id);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $mysqli->prepare("INSERT INTO products (product_name, price, stock_quantity, is_active, Category) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sdiis', $name, $price, $qty, $active, $category);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: inventory.php');
    exit;
}

$products = [];
$res = $mysqli->query("SELECT product_id, product_name, price, stock_quantity, is_active, Category FROM products ORDER BY product_name ASC");
if ($res) while ($r = $res->fetch_assoc()) $products[] = $r;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Inventory Management — Wannabees KTV</title>
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
    
    /* Header - Responsive */
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
      display: none;
    }
    
    .header-nav {
      display: flex;
      gap: 4px;
      flex-wrap: wrap;
      align-items: center;
    }
    /* keep logout compact on desktop, full width on mobile */
    .header-nav form.logout-form {
      display: inline-block;
    }
    .header-nav form.logout-form .btn {
      padding: 6px 10px;
      font-size: 12px;
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
      display: flex;
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
    
    .btn-success {
      background: #f2a20a;
      color: white;
      border-color: #f2a20a;
    }
    
    .btn-success:hover {
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
    
    /* Mobile Navigation */
    .mobile-nav-toggle {
      display: none;
      background: none;
      border: none;
      font-size: 18px;
      cursor: pointer;
      color: #333;
    }
    
    /* Main Content - Optimized for minimal scrolling */
    main {
      padding: 12px;
      height: calc(100vh - 60px);
      overflow-y: auto;
      display: flex;
      flex-direction: column;
    }
    
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
      flex-shrink: 0;
    }
    
    .page-title {
      font-size: 18px;
      font-weight: 700;
      color: #2c2c2c;
    }
    
    /* Search and Filter Bar */
    .search-filter-bar {
      display: flex;
      gap: 8px;
      margin-bottom: 10px;
      flex-wrap: wrap;
      align-items: center;
    }
    
    .search-box {
      flex: 1;
      min-width: 200px;
      position: relative;
      display: flex;
      align-items: center;
    }
    
    .search-box i {
      position: absolute;
      left: 12px;
      color: #999;
      font-size: 12px;
      pointer-events: none;
    }
    
    .search-box input {
      width: 100%;
      padding: 8px 12px 8px 35px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 13px;
      transition: all 0.2s;
    }
    
    .search-box input:focus {
      outline: none;
      border-color: #f2a20a;
      box-shadow: 0 0 0 3px rgba(242,162,10,0.1);
    }
    
    #categoryFilter,
    #statusFilter {
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 13px;
      background: white;
      cursor: pointer;
      transition: all 0.2s;
      min-width: 140px;
    }
    
    #categoryFilter:focus,
    #statusFilter:focus {
      outline: none;
      border-color: #f2a20a;
      box-shadow: 0 0 0 3px rgba(242,162,10,0.1);
    }
    
    .btn-clear {
      padding: 8px 12px;
      white-space: nowrap;
    }
    
    .btn-clear i {
      font-size: 11px;
    }
    
    /* Compact Stats Cards */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
      gap: 8px;
      margin-bottom: 6px;
      flex-shrink: 0;
    }
    
    .stat-card {
      background: white;
      padding: 10px 8px;
      border-radius: 6px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      text-align: center;
    }
    
    .stat-label {
      font-size: 10px;
      color: #666;
      margin-bottom: 3px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .stat-value {
      font-size: 16px;
      font-weight: 700;
      color: #f2a20a;
      line-height: 1;
    }
    
    /* Compact Products Grid */
    .products-container {
      flex: 1;
      min-height: 0;
    }
    
    .products-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 10px;
      padding-bottom: 20px;
    }
    
    .product-card {
      background: white;
      border-radius: 8px;
      padding: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }
    
    .product-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }
    
    .product-card.inactive {
      opacity: 0.6;
      background: #f9f9f9;
    }
    
    .product-status {
      position: absolute;
      top: 10px;
      right: 10px;
      padding: 3px 8px;
      border-radius: 12px;
      font-size: 10px;
      font-weight: 600;
      text-transform: uppercase;
    }
    
    .product-status.active {
      background: #d4edda;
      color: #155724;
    }
    
    .product-status.inactive {
      background: #f8d7da;
      color: #721c24;
    }
    
    .product-name {
      font-size: 15px;
      font-weight: 600;
      margin-bottom: 8px;
      color: #2c2c2c;
      padding-right: 50px;
      line-height: 1.3;
    }
    
    .product-info {
      display: flex;
      justify-content: space-between;
      margin-bottom: 6px;
      padding: 5px 0;
      border-top: 1px solid #f0f0f0;
    }
    
    .product-label {
      color: #666;
      font-size: 12px;
    }
    
    .product-value {
      font-weight: 600;
      font-size: 13px;
    }
    
    .product-price {
      color: #27ae60;
    }
    
    .product-stock {
      color: #f2a20a;
      display: flex;
      align-items: center;
      gap: 4px;
    }
    
    .product-stock.low {
      color: #e74c3c;
    }
    
    .product-actions {
      margin-top: 12px;
    }
    
    .btn-edit {
      width: 100%;
      padding: 7px 10px;
      background: white;
      color: #f2a20a;
      border: 1px solid #f2a20a;
      border-radius: 4px;
      cursor: pointer;
      font-weight: 500;
      transition: all 0.2s;
      font-size: 12px;
    }
    
    .btn-edit:hover {
      background: #f2a20a;
      color: white;
      border-color: #f2a20a;
    }
    
    /* Modal - Mobile Optimized */
    .modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.6);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    
    .modal.active {
      display: flex;
    }
    
    .modal-content {
      background: white;
      border-radius: 12px;
      padding: 20px;
      width: 100%;
      max-width: 400px;
      max-height: 90vh;
      overflow-y: auto;
      position: relative;
    }
    
    .modal-close {
      position: absolute;
      top: 15px;
      right: 15px;
      background: none;
      border: none;
      font-size: 20px;
      cursor: pointer;
      color: #999;
      transition: color 0.3s;
    }
    
    .modal-close:hover {
      color: #333;
    }
    
    .modal-title {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 20px;
      padding-right: 30px;
    }
    
    .form-group {
      margin-bottom: 12px;
    }
    
    .form-group label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: #666;
      margin-bottom: 6px;
    }
    
    .form-group input {
      width: 100%;
      padding: 9px 11px;
      border: 2px solid #e0e0e0;
      border-radius: 6px;
      font-size: 14px;
      transition: all 0.3s;
    }
    
    .form-group select {
      width: 100%;
      padding: 9px 11px;
      border: 2px solid #e0e0e0;
      border-radius: 6px;
      font-size: 14px;
      transition: all 0.3s;
      background: white;
      cursor: pointer;
    }
    
    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: #f2a20a;
      box-shadow: 0 0 0 3px rgba(242,162,10,0.1);
    }
    
    .checkbox-group {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .checkbox-group input[type="checkbox"] {
      width: 18px;
      height: 18px;
      cursor: pointer;
    }
    
    .modal-actions {
      display: flex;
      gap: 8px;
      margin-top: 20px;
    }
    
    .btn-cancel {
      flex: 1;
      padding: 10px 16px;
      background: white;
      border: 1px solid #ddd;
      border-radius: 4px;
      cursor: pointer;
      font-weight: 500;
      transition: all 0.2s;
      font-size: 13px;
      color: #666;
    }
    
    .btn-cancel:hover {
      background: #f8f8f8;
      border-color: #bbb;
    }
    
    .btn-save {
      flex: 1;
      padding: 10px 16px;
      background: #f2a20a;
      border: 1px solid #f2a20a;
      border-radius: 4px;
      color: white;
      cursor: pointer;
      font-weight: 500;
      transition: all 0.2s;
      font-size: 13px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
    }
    
    .btn-save:hover {
      background: #d89209;
      border-color: #d89209;
    }
    
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
      margin-top: 20px;
    }
    
    .empty-state i {
      font-size: 48px;
      color: #ddd;
      margin-bottom: 15px;
    }
    
    .empty-state h3 {
      font-size: 18px;
      margin-bottom: 8px;
      color: #666;
    }
    
    .empty-state p {
      color: #999;
      font-size: 14px;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
      .header-left img {
        height: 30px;
      }
      
      .header-title {
        font-size: 14px;
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
      
      .page-title {
        font-size: 18px;
      }
      
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
      }
      
      .stat-card {
        padding: 12px 8px;
      }
      
      .stat-value {
        font-size: 16px;
      }
      
      .products-grid {
        grid-template-columns: 1fr;
        gap: 8px;
      }
      
      .product-card {
        padding: 12px;
      }
      
      .product-name {
        font-size: 15px;
        margin-bottom: 6px;
      }
      
      .modal-content {
        margin: 10px;
        max-height: 85vh;
      }
      
      .header-nav .logout-form {
        width: 100%;
      }
      
      .header-nav .logout-form .btn {
        width: 100%;
        padding: 10px 12px;
      }
    }
    
    @media (max-width: 480px) {
      header {
        padding: 8px 12px;
      }
      
      main {
        padding: 12px;
      }
      
      .page-header {
        flex-direction: column;
        gap: 8px;
        align-items: stretch;
      
      .search-filter-bar {
        flex-direction: column;
        gap: 8px;
      }
      
      .search-box {
        min-width: 100%;
      }
      
      #categoryFilter,
      #statusFilter {
        width: 100%;
      }
      }
      
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .stat-card {
        padding: 10px 6px;
      }
      
      .stat-label {
        font-size: 10px;
      }
      
      .stat-value {
        font-size: 14px;
      }
      
      .product-card {
        padding: 10px;
      }
      
      .modal-content {
        padding: 12px;
      }
    }
    
    /* Desktop - ensure stats in single row */
    @media (min-width: 769px) {
      .stats-grid {
        grid-template-columns: repeat(4, 1fr);
      }
      
      .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      }
    }
    
    /* Landscape phone optimization */
    @media (max-width: 768px) and (orientation: landscape) {
      .stats-grid {
        grid-template-columns: repeat(4, 1fr);
      }
      
      .products-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }
    
    /* Scrollbar styling */
    ::-webkit-scrollbar {
      width: 6px;
    }
    
    ::-webkit-scrollbar-track {
      background: #f1f1f1;
    }
    
    ::-webkit-scrollbar-thumb {
      background: #c1c1c1;
      border-radius: 3px;
    }
    
    ::-webkit-scrollbar-thumb:hover {
      background: #a8a8a8;
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
      <button class="btn btn-primary"><i class="fas fa-box"></i> <span>Inventory</span></button>
      <button class="btn" onclick="location.href='sales_report.php'"><i class="fas fa-dollar-sign"></i> <span>Sales</span></button>
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
      <h1 class="page-title">Inventory Management</h1>
      <button class="btn btn-success" onclick="openCreateModal()">
        <i class="fas fa-plus"></i> Add Product
      </button>
    </div>

    <!-- Search and Filter -->
    <div class="search-filter-bar">
      <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" placeholder="Search products..." onkeyup="filterProducts()">
      </div>
      <select id="categoryFilter" onchange="filterProducts()">
        <option value="">All Categories</option>
        <option value="Beverages">Beverages</option>
        <option value="Snacks">Snacks</option>
        <option value="Noodles">Noodles</option>
        <option value="Food">Food</option>
        <option value="Condiments">Condiments</option>
        <option value="Other">Other</option>
      </select>
      <select id="statusFilter" onchange="filterProducts()">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
      <button class="btn btn-clear" onclick="clearFilters()" title="Clear all filters">
        <i class="fas fa-times"></i> Clear
      </button>
    </div>
    
    <!-- Results Counter -->
    <div class="results-counter" id="resultsCounter" style="font-size: 12px; color: #666; margin-bottom: 8px; display: none;">
      <i class="fas fa-info-circle"></i> <span id="resultsCount">0</span> products found
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Products</div>
        <div class="stat-value"><?= count($products) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Active Products</div>
        <div class="stat-value"><?= count(array_filter($products, fn($p) => $p['is_active'] == 1)) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Low Stock</div>
        <div class="stat-value"><?= count(array_filter($products, fn($p) => $p['stock_quantity'] < 10)) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Value</div>
        <div class="stat-value">₱<?= number_format(array_sum(array_map(fn($p) => $p['price'] * $p['stock_quantity'], $products)), 0) ?></div>
      </div>
    </div>

    <div class="products-container">
      <?php if (count($products) > 0): ?>
        <div class="products-grid">
          <?php foreach ($products as $p): ?>
            <div class="product-card <?= $p['is_active'] ? '' : 'inactive' ?>">
              <span class="product-status <?= $p['is_active'] ? 'active' : 'inactive' ?>">
                <?= $p['is_active'] ? 'Active' : 'Inactive' ?>
              </span>
              
              <div class="product-name"><?= htmlspecialchars($p['product_name']) ?></div>
              
              
              <?php if (!empty($p['Category'])): ?>
              <div class="product-category" style="font-size: 11px; color: #666; margin-bottom: 6px; font-style: italic;">
                <i class="fas fa-tag"></i> <?= htmlspecialchars($p['Category']) ?>
              </div>
              <?php endif; ?>
              <div class="product-info">
                <span class="product-label">Price</span>
                <span class="product-value product-price">₱<?= number_format($p['price'], 2) ?></span>
              </div>
              
              <div class="product-info">
                <span class="product-label">Stock</span>
                <span class="product-value product-stock <?= $p['stock_quantity'] < 10 ? 'low' : '' ?>">
                  <?= $p['stock_quantity'] ?> units
                  <?= $p['stock_quantity'] < 10 ? '<i class="fas fa-exclamation-triangle"></i>' : '' ?>
                </span>
              </div>
              
              <div class="product-actions">
                <button class="btn-edit" onclick='openEditModal(<?= json_encode($p) ?>)'>
                  <i class="fas fa-edit"></i> Edit
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-box-open"></i>
          <h3>No Products Yet</h3>
          <p>Click "Add Product" to create your first product</p>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- Product Modal -->
  <div id="productModal" class="modal">
    <div class="modal-content">
      <button class="modal-close" onclick="closeModal()">×</button>
      <h3 class="modal-title" id="modalTitle">Add Product</h3>
      
      <form method="post">
        <input type="hidden" name="product_id" id="product_id">
        
        <div class="form-group">
          <label for="product_name">Product Name</label>
          <input type="text" id="product_name" name="product_name" required placeholder="e.g., Coke 330ml">
        </div>
        
        <div class="form-group">
          <label for="price">Price (₱)</label>
          <input type="number" id="price" name="price" step="0.01" min="0" required placeholder="0.00">
        </div>
        
        <div class="form-group">
          <label for="stock_quantity">Stock Quantity</label>
          <input type="number" id="stock_quantity" name="stock_quantity" min="0" required placeholder="0">
        </div>
        
        <div class="form-group">
          <label for="category">Category</label>
          <select id="category" name="category">
            <option value="">-- Select Category --</option>
            <option value="Beverages">Beverages</option>
            <option value="Snacks">Snacks</option>
            <option value="Noodles">Noodles</option>
            <option value="Food">Food</option>
            <option value="Condiments">Condiments</option>
            <option value="Other">Other</option>
          </select>
        </div>
        
        <div class="form-group">
          <div class="checkbox-group">
            <input type="checkbox" id="is_active" name="is_active" checked>
            <label for="is_active" style="margin: 0">Active (available for ordering)</label>
          </div>
        </div>
        
        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
          <button type="submit" class="btn-save">
            <i class="fas fa-save"></i> Save Product
          </button>
        </div>
      </form>
    </div>
  </div>

<script>
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
    
    function openCreateModal() {
      document.getElementById('modalTitle').textContent = 'Add Product';
      document.getElementById('product_id').value = '';
      document.getElementById('product_name').value = '';
      document.getElementById('price').value = '';
      document.getElementById('stock_quantity').value = '';
      document.getElementById('is_active').checked = true;
      document.getElementById('category').value = '';
      document.getElementById('productModal').classList.add('active');
      document.body.style.overflow = 'hidden';
    }
    
    function openEditModal(product) {
      document.getElementById('modalTitle').textContent = 'Edit Product';
      document.getElementById('product_id').value = product.product_id;
      document.getElementById('product_name').value = product.product_name;
      document.getElementById('price').value = product.price;
      document.getElementById('stock_quantity').value = product.stock_quantity;
      document.getElementById('is_active').checked = product.is_active == 1;
      document.getElementById('category').value = product.Category || '';
      document.getElementById('productModal').classList.add('active');
      document.body.style.overflow = 'hidden';
    }
    
    function closeModal() {
      document.getElementById('productModal').classList.remove('active');
      document.body.style.overflow = 'auto';
    }
    
    // Close mobile nav when clicking outside
    document.addEventListener('click', function(e) {
      const nav = document.getElementById('headerNav');
      const toggle = document.querySelector('.mobile-nav-toggle');
      
      if (!nav.contains(e.target) && !toggle.contains(e.target)) {
        nav.classList.remove('active');
      }
    });
    
    // Close modal on outside click
    document.getElementById('productModal').addEventListener('click', function(e) {
      if (e.target === this) closeModal();
    });
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeModal();
    });
    
    // Handle window resize
    window.addEventListener('resize', function() {
      if (window.innerWidth > 768) {
        document.getElementById('headerNav').classList.remove('active');
      }
    });
    
    // Filter products based on search and filters
    function clearFilters() {
      document.getElementById('searchInput').value = '';
      document.getElementById('categoryFilter').value = '';
      document.getElementById('statusFilter').value = '';
      filterProducts();
    }
    
    function filterProducts() {
      const searchInput = document.getElementById('searchInput').value.toLowerCase();
      const categoryFilter = document.getElementById('categoryFilter').value.toLowerCase();
      const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
      
      const productCards = document.querySelectorAll('.product-card');
      let visibleCount = 0;
      
      productCards.forEach(card => {
        const productName = card.querySelector('.product-name').textContent.toLowerCase();
        const categoryEl = card.querySelector('.product-category');
        const productCategory = categoryEl ? categoryEl.textContent.toLowerCase() : '';
        const isActive = !card.classList.contains('inactive');
        
        // Check search match
        const matchesSearch = productName.includes(searchInput);
        
        // Check category match
        const matchesCategory = !categoryFilter || productCategory.includes(categoryFilter);
        
        // Check status match
        let matchesStatus = true;
        if (statusFilter === 'active') {
          matchesStatus = isActive;
        } else if (statusFilter === 'inactive') {
          matchesStatus = !isActive;
        }
        
        // Show or hide card
        if (matchesSearch && matchesCategory && matchesStatus) {
          card.style.display = '';
          visibleCount++;
        } else {
          card.style.display = 'none';
        }
      });
      
      // Show/hide empty state
      const productsGrid = document.querySelector('.products-grid');
      const emptyState = document.querySelector('.empty-state');
      
      if (visibleCount === 0 && productCards.length > 0) {
        if (!emptyState) {
          const emptyDiv = document.createElement('div');
          emptyDiv.className = 'empty-state filter-empty';
          emptyDiv.innerHTML = '<i class="fas fa-search"></i><h3>No Products Found</h3><p>Try adjusting your search or filters</p>';
          productsGrid.parentElement.appendChild(emptyDiv);
        }
        if (productsGrid) productsGrid.style.display = 'none';
      } else {
        const filterEmpty = document.querySelector('.filter-empty');
        if (filterEmpty) filterEmpty.remove();
        if (productsGrid) productsGrid.style.display = 'grid';
      }
      
      // Update results counter
      const resultsCounter = document.getElementById('resultsCounter');
      const resultsCount = document.getElementById('resultsCount');
      const isFiltering = searchInput || categoryFilter || statusFilter;
      
      if (isFiltering) {
        resultsCounter.style.display = 'block';
        resultsCount.textContent = visibleCount;
      } else {
        resultsCounter.style.display = 'none';
      }
    }
  </script>
</body>
</html>