<?php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 3) { 
    header('Location: ../index.php'); 
    exit; 
}
$cashierName = htmlspecialchars($_SESSION['display_name'] ?: $_SESSION['username']);
$userId = intval($_SESSION['user_id']);

// Fetch user details
$stmt = $mysqli->prepare("SELECT username, display_name, email FROM users WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Settings – Wannabees KTV</title>
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
    }
    
    main {
      padding: 1.5rem;
      max-width: 1000px;
      margin: 0 auto;
    }
    
    .page-header {
      margin-bottom: 2rem;
    }
    
    .page-title {
      font-size: 1.5rem;
      font-weight: 600;
      color: #212529;
    }
    
    .settings-section {
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
      font-size: 1rem;
    }
    
    .form-group {
      margin-bottom: 1.5rem;
    }
    
    .form-group:last-child {
      margin-bottom: 0;
    }
    
    .form-label {
      display: block;
      font-size: 0.875rem;
      font-weight: 600;
      color: #212529;
      margin-bottom: 0.5rem;
    }
    
    .form-input {
      width: 100%;
      padding: 0.75rem;
      border: 1px solid #dee2e6;
      border-radius: 6px;
      font-size: 0.875rem;
      transition: all 0.2s;
    }
    
    .form-input:focus {
      outline: none;
      border-color: #f2a20a;
      box-shadow: 0 0 0 3px rgba(242, 162, 10, 0.1);
    }
    
    .form-input:disabled {
      background: #e9ecef;
      cursor: not-allowed;
    }
    
    .btn {
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.875rem;
      font-weight: 600;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .btn-primary {
      background: #f2a20a;
      color: white;
    }
    
    .btn-primary:hover {
      background: #d89209;
    }
    
    .btn-secondary {
      background: #6c757d;
      color: white;
    }
    
    .btn-secondary:hover {
      background: #5a6268;
    }
    
    .alert {
      padding: 1rem;
      border-radius: 6px;
      margin-bottom: 1.5rem;
      display: none;
    }
    
    .alert.show {
      display: block;
    }
    
    .alert-success {
      background: #d1e7dd;
      color: #0f5132;
      border: 1px solid #badbcc;
    }
    
    .alert-error {
      background: #f8d7da;
      color: #842029;
      border: 1px solid #f5c2c7;
    }
    
    .switch-group {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem;
      background: #f8f9fa;
      border-radius: 6px;
      margin-bottom: 1rem;
    }
    
    .switch-label {
      font-size: 0.875rem;
      color: #212529;
      font-weight: 500;
    }
    
    .switch {
      position: relative;
      display: inline-block;
      width: 50px;
      height: 28px;
    }
    
    .switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    
    .slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #ccc;
      transition: .4s;
      border-radius: 28px;
    }
    
    .slider:before {
      position: absolute;
      content: "";
      height: 20px;
      width: 20px;
      left: 4px;
      bottom: 4px;
      background-color: white;
      transition: .4s;
      border-radius: 50%;
    }
    
    input:checked + .slider {
      background-color: #f2a20a;
    }
    
    input:checked + .slider:before {
      transform: translateX(22px);
    }
  </style>
</head>
<body>
  <header>
    <div class="header-container">
      <div class="header-left">
        <img src="../assets/images/KTVL.png" alt="logo" onerror="this.style.display='none'">
        <div class="header-info">
          <div class="header-title">Settings</div>
          <div class="header-subtitle">Cashier: <?= $cashierName ?></div>
        </div>
      </div>
      <button class="mobile-nav-toggle" onclick="toggleMobileNav()"><i class="fas fa-bars"></i></button>
      <nav class="header-nav" id="headerNav">
        <a href="dashboard.php" class="nav-btn"><i class="fas fa-home"></i> <span>Dashboard</span></a>
        <a href="transactions.php" class="nav-btn"><i class="fas fa-history"></i> <span>Transactions</span></a>
        <a href="sales_report.php" class="nav-btn"><i class="fas fa-chart-line"></i> <span>Sales</span></a>
        <a href="guide.php" class="nav-btn"><i class="fas fa-book"></i> <span>Guide</span></a>
        <a href="settings.php" class="nav-btn active"><i class="fas fa-cog"></i> <span>Settings</span></a>
        <a href="../auth/logout.php" class="nav-btn logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
      </nav>
    </div>
  </header>

  <main>
    <div class="page-header">
      <h1 class="page-title">Account Settings</h1>
    </div>

    <div id="alertBox" class="alert"></div>

    <!-- Profile Section -->
    <div class="settings-section">
      <div class="section-title">
        <div class="section-icon"><i class="fas fa-user"></i></div>
        Profile Information
      </div>
      <form id="profileForm">
        <div class="form-group">
          <label class="form-label">Username</label>
          <input type="text" class="form-input" value="<?= htmlspecialchars($user['username']) ?>" disabled>
        </div>
        <div class="form-group">
          <label class="form-label">Display Name</label>
          <input type="text" class="form-input" name="display_name" id="displayName" value="<?= htmlspecialchars($user['display_name'] ?: '') ?>" placeholder="Enter display name">
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" class="form-input" name="email" id="email" value="<?= htmlspecialchars($user['email'] ?: '') ?>" placeholder="Enter email address">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
      </form>
    </div>

    <!-- Security Section -->
    <div class="settings-section">
      <div class="section-title">
        <div class="section-icon"><i class="fas fa-lock"></i></div>
        Security
      </div>
      <form id="passwordForm">
        <div class="form-group">
          <label class="form-label">Current Password</label>
          <input type="password" class="form-input" name="current_password" id="currentPassword" placeholder="Enter current password">
        </div>
        <div class="form-group">
          <label class="form-label">New Password</label>
          <input type="password" class="form-input" name="new_password" id="newPassword" placeholder="Enter new password">
        </div>
        <div class="form-group">
          <label class="form-label">Confirm New Password</label>
          <input type="password" class="form-input" name="confirm_password" id="confirmPassword" placeholder="Confirm new password">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Change Password</button>
      </form>
    </div>

    <!-- Preferences Section -->
    <div class="settings-section">
      <div class="section-title">
        <div class="section-icon"><i class="fas fa-sliders-h"></i></div>
        Preferences
      </div>
      <div class="switch-group">
        <span class="switch-label">Sound Notifications</span>
        <label class="switch">
          <input type="checkbox" id="soundNotifications" checked>
          <span class="slider"></span>
        </label>
      </div>
      <div class="switch-group">
        <span class="switch-label">Auto-refresh Dashboard</span>
        <label class="switch">
          <input type="checkbox" id="autoRefresh" checked>
          <span class="slider"></span>
        </label>
      </div>
      <div class="switch-group">
        <span class="switch-label">Show Order Alerts</span>
        <label class="switch">
          <input type="checkbox" id="orderAlerts" checked>
          <span class="slider"></span>
        </label>
      </div>
    </div>
  </main>

  <script>
    function toggleMobileNav() {
      const nav = document.getElementById('headerNav');
      if (nav) nav.classList.toggle('active');
    }

    function showAlert(message, type) {
      const alertBox = document.getElementById('alertBox');
      alertBox.className = `alert alert-${type} show`;
      alertBox.textContent = message;
      setTimeout(() => {
        alertBox.classList.remove('show');
      }, 5000);
    }

    // Profile Form
    document.getElementById('profileForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(e.target);
      
      try {
        const response = await fetch('../api/users/update_profile.php', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();
        
        if (result.success) {
          showAlert('Profile updated successfully!', 'success');
          // Update session display name
          setTimeout(() => location.reload(), 1500);
        } else {
          showAlert(result.error || 'Failed to update profile', 'error');
        }
      } catch (error) {
        showAlert('An error occurred. Please try again.', 'error');
      }
    });

    // Password Form
    document.getElementById('passwordForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const currentPassword = document.getElementById('currentPassword').value;
      const newPassword = document.getElementById('newPassword').value;
      const confirmPassword = document.getElementById('confirmPassword').value;

      if (newPassword !== confirmPassword) {
        showAlert('New passwords do not match', 'error');
        return;
      }

      if (newPassword.length < 6) {
        showAlert('Password must be at least 6 characters', 'error');
        return;
      }

      const formData = new FormData();
      formData.append('current_password', currentPassword);
      formData.append('new_password', newPassword);
      
      try {
        const response = await fetch('../api/users/change_password.php', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();
        
        if (result.success) {
          showAlert('Password changed successfully!', 'success');
          e.target.reset();
        } else {
          showAlert(result.error || 'Failed to change password', 'error');
        }
      } catch (error) {
        showAlert('An error occurred. Please try again.', 'error');
      }
    });

    // Save preferences to localStorage
    ['soundNotifications', 'autoRefresh', 'orderAlerts'].forEach(id => {
      const checkbox = document.getElementById(id);
      const saved = localStorage.getItem(id);
      if (saved !== null) {
        checkbox.checked = saved === 'true';
      }
      checkbox.addEventListener('change', () => {
        localStorage.setItem(id, checkbox.checked);
        showAlert('Preference saved', 'success');
      });
    });
  </script>
</body>
</html>
