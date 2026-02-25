<?php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 1) { 
    header('Location: ../index.php'); 
    exit; 
}
$ownerName = htmlspecialchars($_SESSION['display_name'] ?: $_SESSION['username']);
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
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
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
    
    .header-info {
      display: flex;
      flex-direction: column;
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
    
    .mobile-toggle {
      display: none;
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      color: #212529;
    }
    
    .header-nav {
      display: flex;
      gap: 0.5rem;
      align-items: center;
    }
    
    .btn {
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
    }
    
    .btn:hover {
      background: #f8f9fa;
      border-color: #adb5bd;
    }
    
    .btn-primary {
      background: #f2a20a;
      color: white;
      border-color: #f2a20a;
    }
    
    .btn-primary:hover {
      background: #d89209;
    }
    
    .btn-danger {
      border-color: #dc3545;
      color: #dc3545;
    }
    
    .btn-danger:hover {
      background: #dc3545;
      color: #ffffff;
    }
    
    @media (max-width: 768px) {
      .mobile-toggle {
        display: block;
      }
      .header-nav {
        width: 100%;
        flex-direction: column;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
      }
      .header-nav.active {
        max-height: 500px;
      }
      .btn {
        width: 100%;
        justify-content: flex-start;
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
    
    .btn-save {
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
      background: #f2a20a;
      color: white;
    }
    
    .btn-save:hover {
      background: #d89209;
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
    
    .logout-form {
      margin: 0;
    }
  </style>
</head>
<body>
  <header>
    <div class="header-left">
      <img src="../assets/images/KTVL.png" alt="logo" onerror="this.style.display='none'">
      <div class="header-info">
        <div class="header-title">Settings</div>
        <div class="header-subtitle">Owner: <?= $ownerName ?></div>
      </div>
    </div>
    
    <button class="mobile-toggle" onclick="toggleNav()">
      <i class="fas fa-bars"></i>
    </button>
    
    <div class="header-nav" id="headerNav">
      <button class="btn" onclick="location.href='dashboard.php'"><i class="fas fa-door-open"></i> <span>Rooms</span></button>
      <button class="btn" onclick="location.href='inventory.php'"><i class="fas fa-box"></i> <span>Inventory</span></button>
      <button class="btn" onclick="location.href='sales_report.php'"><i class="fas fa-dollar-sign"></i> <span>Sales</span></button>
      <button class="btn" onclick="location.href='pricing.php'"><i class="fas fa-tag"></i> <span>Pricing</span></button>
      <button class="btn" onclick="location.href='users.php'"><i class="fas fa-users"></i> <span>Users</span></button>
      <button class="btn" onclick="location.href='guide.php'"><i class="fas fa-book"></i> <span>Guide</span></button>
      <button class="btn btn-primary" onclick="location.href='settings.php'"><i class="fas fa-cog"></i> <span>Settings</span></button>
      <form action="../auth/logout.php" method="post" class="logout-form">
        <button type="button" class="btn btn-danger" onclick="logoutNow(this)"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></button>
      </form>
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
        <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
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
        <button type="submit" class="btn-save"><i class="fas fa-key"></i> Change Password</button>
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
        <span class="switch-label">Show Real-time Updates</span>
        <label class="switch">
          <input type="checkbox" id="realtimeUpdates" checked>
          <span class="slider"></span>
        </label>
      </div>
    </div>
  </main>

  <script>
    function toggleNav() {
      const nav = document.getElementById('headerNav');
      if (nav) nav.classList.toggle('active');
    }

    function logoutNow(btn) {
      if (confirm('Are you sure you want to logout?')) {
        btn.closest('form').submit();
      }
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
    ['soundNotifications', 'autoRefresh', 'realtimeUpdates'].forEach(id => {
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
