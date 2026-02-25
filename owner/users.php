<?php
session_start();
require_once __DIR__ . '/../db.php';
if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 1) { 
    header('Location: ../index.php'); 
    exit; 
}
$ownerName = htmlspecialchars($_SESSION['display_name'] ?: $_SESSION['username']);

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_user') {
        $username = trim($_POST['username']);
        $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
        $display_name = trim($_POST['display_name']);
        $role_id = intval($_POST['role_id']);
        $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
        
        $stmt = $mysqli->prepare("INSERT INTO users (username, password, display_name, role_id, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssii', $username, $password, $display_name, $role_id, $is_active);
        
        try {
            $stmt->execute();
            $success = "User created successfully!";
        } catch (mysqli_sql_exception $e) {
            $error = "Error: Username already exists or invalid data.";
        }
        $stmt->close();
    }
    
    if ($_POST['action'] === 'update_user') {
        $user_id = intval($_POST['user_id']);
        $username = trim($_POST['username']);
        $display_name = trim($_POST['display_name']);
        $role_id = intval($_POST['role_id']);
        $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
        
        // Update user info (NOT password)
        $stmt = $mysqli->prepare("UPDATE users SET username = ?, display_name = ?, role_id = ?, is_active = ? WHERE user_id = ?");
        $stmt->bind_param('sssii', $username, $display_name, $role_id, $is_active, $user_id);
        
        try {
            $stmt->execute();
            $success = "User updated successfully!";
        } catch (mysqli_sql_exception $e) {
            $error = "Error: Username already exists or invalid data.";
        }
        $stmt->close();
    }
    
    if ($_POST['action'] === 'delete_user') {
        $user_id = intval($_POST['user_id']);
        if ($user_id != $_SESSION['user_id']) { // Can't delete yourself
            $stmt = $mysqli->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->close();
            $success = "User deleted successfully!";
        } else {
            $error = "You cannot delete your own account.";
        }
    }
}

// Get all users with their roles
$users_result = $mysqli->query("
    SELECT u.user_id, u.username, u.display_name, u.created_at, u.is_active, r.role_id, r.role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.role_id 
    ORDER BY u.created_at DESC
");
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}

// Get roles for dropdown (only Owner and Cashier)
$roles_result = $mysqli->query("SELECT role_id, role_name FROM roles WHERE role_id IN (1, 3) ORDER BY role_id");
$roles = [];
while ($row = $roles_result->fetch_assoc()) {
    $roles[] = $row;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Users Management — Wannabees KTV</title>
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
      background: #fff;
      color: #2c2c2c;
    }
    
    /* Header - Consistent with inventory */
    header {
      background: #fff;
      padding: 12px 20px;
      border-bottom: 1px solid #e0e0e0;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }
    
    .header-left {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-shrink: 0;
    }
    
    .header-left img {
      width: 32px;
      height: 32px;
    }

    .header-title {
      font-size: 16px;
      font-weight: 600;
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
      font-size: 20px;
      cursor: pointer;
    }
    
    .btn {
      padding: 7px 12px;
      border: 1px solid #ddd;
      background: #fff;
      color: #555;
      cursor: pointer;
      font-size: 12px;
      font-weight: 500;
      border-radius: 4px;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: all 0.2s ease;
      white-space: nowrap;
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
      background: #fff;
      color: #e74c3c;
      border-color: #e74c3c;
    }
    
    .btn-danger:hover {
      background: #fef5f5;
      border-color: #c0392b;
      color: #c0392b;
    }
    
    main {
      padding: 20px;
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      gap: 12px;
    }
    
    .page-title {
      font-size: 24px;
      font-weight: 600;
    }
    
    .alert {
      padding: 12px 16px;
      margin-bottom: 16px;
      border-left: 4px solid;
      border-radius: 2px;
      font-size: 14px;
    }
    
    .alert-success {
      background: #f1f8e9;
      border-color: #7cb342;
      color: #558b2f;
    }
    
    .alert-error {
      background: #ffebee;
      border-color: #d32f2f;
      color: #b71c1c;
    }
    
    .users-table-container {
      background: #fff;
      border: 1px solid #e0e0e0;
      border-radius: 4px;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
    }
    
    thead {
      background: #fafafa;
      border-bottom: 2px solid #e0e0e0;
    }
    
    th {
      padding: 12px 16px;
      text-align: left;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #666;
    }
    
    td {
      padding: 12px 16px;
      border-bottom: 1px solid #f0f0f0;
      font-size: 13px;
    }
    
    tbody tr:hover {
      background: #fafafa;
    }
    
    .role-badge {
      padding: 4px 8px;
      border-radius: 2px;
      font-size: 11px;
      font-weight: 600;
      display: inline-block;
    }
    
    .role-owner {
      background: #fff3e0;
      color: #e65100;
    }
    
    .role-cashier {
      background: #e8f5e9;
      color: #1b5e20;
    }
    
    .role-customer {
      background: #f3e5f5;
      color: #4a148c;
    }
    
    .status-badge {
      padding: 4px 8px;
      border-radius: 2px;
      font-size: 11px;
      font-weight: 600;
      display: inline-block;
    }
    
    .status-active {
      background: #e8f5e9;
      color: #1b5e20;
    }
    
    .status-inactive {
      background: #ffebee;
      color: #b71c1c;
    }
    
    .btn-success {
      background: #28a745;
      color: white;
      border-color: #28a745;
    }
    
    .btn-success:hover {
      background: #218838;
      border-color: #218838;
    }
    /* Modal */
    .modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.4);
      z-index: 1000;
    }
    
    .modal.active {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .modal-content {
      background: white;
      border-radius: 4px;
      width: 100%;
      max-width: 450px;
      padding: 24px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.15);
      position: relative;
    }
    
    .modal-close {
      position: absolute;
      top: 16px;
      right: 16px;
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
      color: #999;
    }
    
    .modal-close:hover {
      color: #333;
    }
    
    .modal-title {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 6px;
    }
    
    .modal-subtitle {
      font-size: 13px;
      color: #666;
      margin-bottom: 16px;
    }
    
    .form-group {
      margin-bottom: 16px;
    }
    
    .form-group label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 6px;
      color: #333;
    }
    
    .form-group input,
    .form-group select {
      width: 100%;
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 3px;
      font-size: 13px;
    }
    
    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: #f2a20a;
      box-shadow: 0 0 0 2px rgba(242,162,10,0.1);
    }
    
    .modal-actions {
      display: flex;
      gap: 8px;
      justify-content: flex-end;
      margin-top: 20px;
      border-top: 1px solid #f0f0f0;
      padding-top: 16px;
    }
    
    .btn-cancel {
      padding: 8px 16px;
      background: #f5f5f5;
      border: 1px solid #ddd;
      border-radius: 3px;
      cursor: pointer;
      font-size: 13px;
      transition: all 0.15s ease;
    }
    
    .btn-cancel:hover {
      background: #eee;
    }
    
    .btn-save {
      padding: 8px 16px;
      background: #f2a20a;
      color: white;
      border: 1px solid #f2a20a;
      border-radius: 3px;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
      transition: all 0.15s ease;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    
    .btn-save:hover {
      background: #f5c542;
      border-color: #f5c542;
      color: #2c2c2c;
    }
    
    .btn-save:disabled {
      background: #ccc;
      border-color: #ccc;
      cursor: not-allowed;
      opacity: 0.6;
    }
    
    .btn-sm {
      padding: 6px 10px;
      font-size: 12px;
    }
    
    .form-info {
      background: #e3f2fd;
      border-left: 3px solid #1976d2;
      padding: 10px 12px;
      margin-bottom: 16px;
      font-size: 12px;
      color: #1565c0;
      border-radius: 2px;
    }
    
    .email-otp-group {
      display: flex;
      gap: 8px;
      align-items: flex-end;
    }
    
    .email-otp-group .form-group {
      flex: 1;
      margin-bottom: 0;
    }
    
    .btn-send-otp {
      padding: 8px 16px;
      background: #2196f3;
      color: white;
      border: none;
      border-radius: 3px;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
      white-space: nowrap;
      transition: all 0.15s ease;
    }
    
    .btn-send-otp:hover:not(:disabled) {
      background: #1976d2;
    }
    
    .btn-send-otp:disabled {
      background: #ccc;
      cursor: not-allowed;
      opacity: 0.6;
    }
    
    .otp-status {
      padding: 8px 12px;
      border-radius: 3px;
      font-size: 12px;
      margin-bottom: 12px;
    }
    
    .otp-status.success {
      background: #e8f5e9;
      color: #2e7d32;
      border: 1px solid #a5d6a7;
    }
    
    .otp-status.error {
      background: #ffebee;
      color: #c62828;
      border: 1px solid #ef9a9a;
    }
    
    .hidden {
      display: none !important;
    }
    
    .actions-cell {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      header {
        padding: 10px 12px;
      }
      
      .header-title {
        font-size: 14px;
      }
      
      main {
        padding: 12px;
      }
      
      .page-header {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .page-header .btn-primary {
        width: 100%;
      }
      
      .page-title {
        font-size: 20px;
      }
      
      .users-table-container {
        border: none;
        padding: 0;
      }
      
      table {
        display: block;
      }
      
      thead {
        display: none;
      }
      
      tbody {
        display: block;
      }
      
      tbody tr {
        display: block;
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        margin-bottom: 12px;
        padding: 12px;
      }
      
      tbody tr:hover {
        background: white;
      }
      
      td {
        display: block;
        padding: 6px 0;
        border: none;
        border-bottom: 1px solid #f0f0f0;
      }
      
      td:last-child {
        border-bottom: none;
        padding-top: 8px;
      }
      
      td:before {
        content: attr(data-label);
        font-weight: 600;
        color: #666;
        display: block;
        font-size: 11px;
        text-transform: uppercase;
        margin-bottom: 4px;
      }
      
      .actions-cell {
        flex-direction: column;
      }
      
      .btn, .btn-sm {
        width: 100%;
      }
      
      .mobile-nav-toggle {
        display: block;
      }
      
      .header-nav {
        position: fixed;
        top: 50px;
        left: 0;
        right: 0;
        background: white;
        flex-direction: column;
        border-bottom: 1px solid #e0e0e0;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
        z-index: 999;
      }
      
      .header-nav.active {
        max-height: 400px;
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
      <button class="btn btn-primary">
        <i class="fas fa-users"></i> <span>Users</span>
      </button>
      <button class="btn" onclick="location.href='guide.php'">
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
    <div class="page-header">
      <h1 class="page-title">Users Management</h1>
      <button class="btn btn-primary" onclick="openUserModal()">
        <i class="fas fa-plus"></i> Add User
      </button>
    </div>
    
    <?php if (isset($success)): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?= htmlspecialchars($success) ?></span>
      </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
      <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>
    
    <div class="users-table-container">
      <table>
        <thead>
          <tr>
            <th>Username</th>
            <th>Display Name</th>
            <th>Role</th>
            <th>Status</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
            <tr>
              <td data-label="Username"><?= htmlspecialchars($user['username']) ?></td>
              <td data-label="Display Name"><?= htmlspecialchars($user['display_name']) ?></td>
              <td data-label="Role">
                <span class="role-badge role-<?= strtolower($user['role_name']) ?>">
                  <?= htmlspecialchars($user['role_name']) ?>
                </span>
              </td>
              <td data-label="Status">
                <span class="status-badge <?= $user['is_active'] ? 'status-active' : 'status-inactive' ?>">
                  <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td data-label="Created"><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
              <td data-label="Actions">
                <div class="actions-cell">
                  <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                    <button class="btn btn-sm" onclick='editUser(<?= json_encode($user) ?>)'>
                      <i class="fas fa-edit"></i> Edit
                    </button>
                  <?php else: ?>
                    <span style="color: #999; font-size: 11px;">(Current User)</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>

  <!-- User Modal -->
  <div id="userModal" class="modal">
    <div class="modal-content">
      <button class="modal-close" onclick="closeUserModal()">×</button>
      <h3 class="modal-title" id="modalTitle">Add New User</h3>
      <div class="modal-subtitle" id="modalSubtitle">Create a new system user</div>
      
      <form method="post" id="userForm">
        <input type="hidden" name="action" id="formAction" value="create_user">
        <input type="hidden" name="user_id" id="userId" value="">
        <input type="hidden" name="otp" id="otpValue" value="">
        
        <div id="otpStatusMessage" class="otp-status hidden"></div>
        
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" required placeholder="Login username">
        </div>
        
        <div class="email-otp-group" id="emailOtpGroup">
          <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required placeholder="user@example.com">
          </div>
          <button type="button" class="btn-send-otp" id="sendOtpBtn" onclick="sendOTP()">
            <i class="fas fa-paper-plane"></i> Send OTP
          </button>
        </div>
        
        <div class="form-group hidden" id="otpInputGroup">
          <label for="otp">Enter OTP</label>
          <input type="text" id="otpInput" name="otp_input" maxlength="6" placeholder="Enter 6-digit OTP">
          <small style="color: #666; font-size: 11px; margin-top: 4px; display: block;">OTP has been sent to your email. Valid for 15 minutes.</small>
        </div>
        
        <!-- Password field only shown in edit mode -->
        <div class="form-group hidden" id="passwordGroup">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="User password">
        </div>
        
        <div class="form-group">
          <label for="display_name">Display Name</label>
          <input type="text" id="display_name" name="display_name" required placeholder="Full name">
        </div>
        
        <div class="form-group">
          <label for="role_id">Role</label>
          <select id="role_id" name="role_id" required>
            <option value="">-- Select Role --</option>
            <?php foreach ($roles as $role): ?>
              <option value="<?= $role['role_id'] ?>"><?= htmlspecialchars($role['role_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-group">
          <label for="is_active">User Status</label>
          <select id="is_active" name="is_active" required>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
        </div>
        
        <div class="form-info" id="editInfo" style="display: none;">
          <i class="fas fa-info-circle"></i>
          Password cannot be changed here. Users must reset their own passwords.
        </div>
        
        <div class="form-info" id="createInfo" style="background: #fff3e0; border-left-color: #f57c00; color: #e65100;">
          <i class="fas fa-info-circle"></i>
          Email verification required. A secure password will be auto-generated and sent to the user's email.
        </div>
        
        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeUserModal()">Cancel</button>
          <button type="submit" class="btn-save" id="submitBtn">
            <i class="fas fa-save"></i> <span id="submitBtnText">Create User</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Password Success Modal -->
  <div id="passwordModal" class="modal">
    <div class="modal-content" style="max-width: 550px;">
      <button class="modal-close" onclick="closePasswordModal()">×</button>
      <h3 class="modal-title" style="color: #4caf50;">
        <i class="fas fa-check-circle"></i> User Created Successfully!
      </h3>
      <div class="modal-subtitle">Account credentials have been generated</div>
      
      <div style="background: #f8f9fa; border: 2px solid #4caf50; border-radius: 8px; padding: 20px; margin: 20px 0;">
        <div style="margin-bottom: 16px;">
          <label style="display: block; font-size: 12px; color: #666; font-weight: 600; margin-bottom: 4px;">USERNAME</label>
          <div style="display: flex; gap: 8px; align-items: center;">
            <input type="text" id="generatedUsername" readonly style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; font-weight: 600; background: white;">
            <button onclick="copyToClipboard('generatedUsername')" class="btn btn-sm" style="white-space: nowrap;">
              <i class="fas fa-copy"></i> Copy
            </button>
          </div>
        </div>
        
        <div>
          <label style="display: block; font-size: 12px; color: #666; font-weight: 600; margin-bottom: 4px;">TEMPORARY PASSWORD</label>
          <div style="display: flex; gap: 8px; align-items: center;">
            <input type="text" id="generatedPassword" readonly style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; font-weight: 600; font-family: monospace; background: white; color: #f2a20a;">
            <button onclick="copyToClipboard('generatedPassword')" class="btn btn-sm" style="white-space: nowrap;">
              <i class="fas fa-copy"></i> Copy
            </button>
          </div>
        </div>
      </div>
      
      <div style="background: #fff3e0; border-left: 4px solid #ff9800; padding: 12px 16px; margin: 16px 0; border-radius: 4px;">
        <p style="margin: 0; color: #e65100; font-size: 13px; line-height: 1.5;">
          <i class="fas fa-info-circle"></i> <strong>Important:</strong> The password has been sent to the user's email. 
          They must change it on first login for security.
        </p>
      </div>
      
      <div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 12px 16px; margin: 16px 0; border-radius: 4px;">
        <p style="margin: 0 0 8px 0; color: #1565c0; font-size: 13px; font-weight: 600;">
          <i class="fas fa-envelope"></i> Email Sent To:
        </p>
        <p style="margin: 0; color: #1976d2; font-size: 13px;" id="userEmail"></p>
      </div>
      
      <div class="modal-actions" style="border-top: none; padding-top: 0; margin-top: 20px;">
        <button type="button" class="btn-save" onclick="closePasswordModal()">
          <i class="fas fa-check"></i> Done
        </button>
      </div>
    </div>
  </div>

  <script>
    let otpSent = false;
    let otpVerified = false;
    
    function openUserModal() {
      // Reset to create mode
      document.getElementById('modalTitle').textContent = 'Add New User';
      document.getElementById('modalSubtitle').textContent = 'Create a new system user';
      document.getElementById('formAction').value = 'create_user';
      document.getElementById('submitBtnText').textContent = 'Create User';
      document.getElementById('userId').value = '';
      document.getElementById('username').value = '';
      document.getElementById('email').value = '';
      document.getElementById('password').value = '';
      document.getElementById('display_name').value = '';
      document.getElementById('role_id').value = '';
      document.getElementById('is_active').value = '1';
      document.getElementById('passwordGroup').style.display = 'none';  // Hide password in create mode
      document.getElementById('password').required = false;
      document.getElementById('editInfo').style.display = 'none';
      document.getElementById('createInfo').style.display = 'block';
      
      // Reset OTP fields
      document.getElementById('emailOtpGroup').style.display = 'flex';
      document.getElementById('email').required = true;
      document.getElementById('email').disabled = false;
      document.getElementById('otpInputGroup').classList.add('hidden');
      document.getElementById('otpInput').value = '';
      document.getElementById('otpValue').value = '';
      
      // Clear any previous error messages
      const statusMsg = document.getElementById('otpStatusMessage');
      statusMsg.textContent = '';
      statusMsg.className = 'otp-status hidden';
      
      document.getElementById('sendOtpBtn').disabled = false;
      document.getElementById('submitBtn').disabled = false;
      otpSent = false;
      otpVerified = false;
      
      document.getElementById('userModal').classList.add('active');
      document.body.style.overflow = 'hidden';
    }
    
    function editUser(user) {
      // Switch to edit mode
      document.getElementById('modalTitle').textContent = 'Edit User';
      document.getElementById('modalSubtitle').textContent = 'Update user information';
      document.getElementById('formAction').value = 'update_user';
      document.getElementById('submitBtnText').textContent = 'Update User';
      document.getElementById('userId').value = user.user_id;
      document.getElementById('username').value = user.username;
      document.getElementById('display_name').value = user.display_name;
      document.getElementById('role_id').value = user.role_id;
      document.getElementById('is_active').value = user.is_active ? 1 : 0;
      document.getElementById('passwordGroup').style.display = 'none';
      document.getElementById('password').required = false;
      document.getElementById('editInfo').style.display = 'block';
      document.getElementById('createInfo').style.display = 'none';
      
      // Hide OTP fields in edit mode
      document.getElementById('emailOtpGroup').style.display = 'none';
      document.getElementById('email').required = false;
      document.getElementById('otpInputGroup').classList.add('hidden');
      
      document.getElementById('userModal').classList.add('active');
      document.body.style.overflow = 'hidden';
    }
    
    async function sendOTP() {
      const email = document.getElementById('email').value.trim();
      const username = document.getElementById('username').value.trim();
      const sendOtpBtn = document.getElementById('sendOtpBtn');
      const statusMsg = document.getElementById('otpStatusMessage');
      
      // Validate email
      if (!email) {
        showOTPStatus('Please enter an email address', 'error');
        return;
      }
      
      // Basic email validation
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        showOTPStatus('Please enter a valid email address', 'error');
        return;
      }
      
      // Disable button and show loading
      sendOtpBtn.disabled = true;
      sendOtpBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
      
      try {
        const formData = new FormData();
        formData.append('email', email);
        formData.append('username', username);
        
        const response = await fetch('../api/users/send_user_creation_otp.php', {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
          showOTPStatus(result.message, 'success');
          otpSent = true;
          
          // Show OTP input field
          document.getElementById('otpInputGroup').classList.remove('hidden');
          document.getElementById('email').disabled = true;
          
          // Update button
          sendOtpBtn.innerHTML = '<i class="fas fa-check"></i> OTP Sent';
          
        } else {
          showOTPStatus(result.error || 'Failed to send OTP', 'error');
          sendOtpBtn.disabled = false;
          sendOtpBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send OTP';
        }
      } catch (error) {
        console.error('Error sending OTP:', error);
        showOTPStatus('Network error. Please try again.', 'error');
        sendOtpBtn.disabled = false;
        sendOtpBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send OTP';
      }
    }
    
    function showOTPStatus(message, type) {
      const statusMsg = document.getElementById('otpStatusMessage');
      statusMsg.textContent = message;
      statusMsg.className = 'otp-status ' + type;
      statusMsg.classList.remove('hidden');
      
      // Auto hide after 5 seconds
      setTimeout(() => {
        if (type === 'error') {
          statusMsg.classList.add('hidden');
        }
      }, 5000);
    }
    
    // Handle form submission
    document.getElementById('userForm').addEventListener('submit', async function(e) {
      const formAction = document.getElementById('formAction').value;
      
      // Only validate OTP for create_user action
      if (formAction === 'create_user') {
        e.preventDefault();
        
        // Validate OTP was sent
        if (!otpSent) {
          showOTPStatus('Please send OTP to verify your email first', 'error');
          return;
        }
        
        // Validate OTP input
        const otpInput = document.getElementById('otpInput').value.trim();
        if (!otpInput || otpInput.length !== 6) {
          showOTPStatus('Please enter the 6-digit OTP sent to your email', 'error');
          return;
        }
        
        // Clear any previous error messages
        document.getElementById('otpStatusMessage').classList.add('hidden');
        
        // Prepare form data
        // Note: email field is disabled, so we need to add it manually
        const emailField = document.getElementById('email');
        const emailValue = emailField.value;
        
        const formData = new FormData(this);
        formData.set('email', emailValue);  // Explicitly add email since it's disabled
        formData.append('otp', otpInput);
        
        // Debug: log what we're sending
        console.log('Submitting user creation with:', {
          username: formData.get('username'),
          email: formData.get('email'),
          display_name: formData.get('display_name'),
          role_id: formData.get('role_id'),
          is_active: formData.get('is_active'),
          otp: otpInput
        });
        
        // Disable submit button
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
        
        try {
          const response = await fetch('../api/users/verify_otp_and_create_user.php', {
            method: 'POST',
            body: formData
          });
          
          const result = await response.json();
          
          if (result.success) {
            // Close the user creation modal
            closeUserModal();
            
            // Show password modal with generated credentials
            document.getElementById('generatedUsername').value = result.username;
            document.getElementById('generatedPassword').value = result.generated_password;
            document.getElementById('userEmail').textContent = document.getElementById('email').value;
            document.getElementById('passwordModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Reset form for next use
            document.getElementById('userForm').reset();
            otpSent = false;
            otpVerified = false;
          } else {
            showOTPStatus(result.error || 'Failed to create user', 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> <span id="submitBtnText">Create User</span>';
          }
        } catch (error) {
          console.error('Error creating user:', error);
          showOTPStatus('Network error. Please try again.', 'error');
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="fas fa-save"></i> <span id="submitBtnText">Create User</span>';
        }
      }
      // For update_user, let the form submit normally
    });
    
    function closeUserModal() {
      document.getElementById('userModal').classList.remove('active');
      document.body.style.overflow = 'auto';
    }
    
    function closePasswordModal() {
      document.getElementById('passwordModal').classList.remove('active');
      document.body.style.overflow = 'auto';
      // Reload page to show the new user in the list
      window.location.reload();
    }
    
    function copyToClipboard(elementId) {
      const element = document.getElementById(elementId);
      element.select();
      element.setSelectionRange(0, 99999); // For mobile devices
      
      try {
        document.execCommand('copy');
        // Visual feedback
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.style.background = '#4caf50';
        btn.style.borderColor = '#4caf50';
        btn.style.color = 'white';
        
        setTimeout(() => {
          btn.innerHTML = originalHTML;
          btn.style.background = '';
          btn.style.borderColor = '';
          btn.style.color = '';
        }, 2000);
      } catch (err) {
        console.error('Failed to copy:', err);
      }
    }
    
    document.getElementById('userModal').addEventListener('click', function(e) {
      if (e.target === this) closeUserModal();
    });
    
    document.getElementById('passwordModal').addEventListener('click', function(e) {
      if (e.target === this) closePasswordModal();
    });
    
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeUserModal();
        closePasswordModal();
      }
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