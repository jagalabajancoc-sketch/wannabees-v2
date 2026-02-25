<?php
session_start();
require_once __DIR__ . '/../db.php';

// Must be logged in but with must_change_password flag
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php?error=Please login first');
    exit;
}

// Check if user actually needs to change password
$stmt = $mysqli->prepare("SELECT username, display_name, email, must_change_password FROM users WHERE user_id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || $user['must_change_password'] != 1) {
    // User doesn't need to change password, redirect to appropriate dashboard
    $role = intval($_SESSION['role_id']);
    if ($role === 1) {
        header('Location: ../owner/dashboard.php');
    } elseif ($role === 3) {
        header('Location: ../cashier/dashboard.php');
    } elseif ($role === 4) {
        header('Location: ../customer/room_tablet.php');
    } else {
        header('Location: ../customer/fallback.php');
    }
    exit;
}

$displayName = htmlspecialchars($user['display_name'] ?: $user['username']);
$username = htmlspecialchars($user['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Wannabees KTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
            padding: 40px 30px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #f2a20a;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
        }
        
        .welcome-box {
            background: #f8f9fa;
            border-left: 4px solid #f2a20a;
            padding: 16px;
            margin-bottom: 24px;
            border-radius: 4px;
        }
        
        .welcome-box h3 {
            font-size: 16px;
            color: #333;
            margin-bottom: 4px;
        }
        
        .welcome-box p {
            font-size: 13px;
            color: #666;
            line-height: 1.5;
        }
        
        .welcome-box .username {
            font-weight: 600;
            color: #f2a20a;
        }
        
        .info-box {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 13px;
            color: #e65100;
            line-height: 1.5;
        }
        
        .info-box i {
            margin-right: 6px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-group label i {
            margin-right: 6px;
            color: #f2a20a;
        }
        
        .password-input-wrapper {
            position: relative;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 40px 12px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #f2a20a;
            box-shadow: 0 0 0 3px rgba(242,162,10,0.1);
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 16px;
            padding: 4px;
        }
        
        .password-toggle:hover {
            color: #333;
        }
        
        .password-requirements {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 4px;
            margin-top: 8px;
            font-size: 12px;
            color: #666;
        }
        
        .password-requirements ul {
            list-style: none;
            padding-left: 0;
        }
        
        .password-requirements li {
            padding: 4px 0;
        }
        
        .password-requirements li::before {
            content: "✓ ";
            color: #4caf50;
            font-weight: bold;
            margin-right: 6px;
        }
        
        .alert {
            padding: 12px 16px;
            margin-bottom: 16px;
            border-radius: 4px;
            font-size: 13px;
            display: none;
        }
        
        .alert.show {
            display: block;
        }
        
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }
        
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: #f2a20a;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-submit:hover:not(:disabled) {
            background: #d89209;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(242,162,10,0.3);
        }
        
        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .logout-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
        }
        
        .logout-link a {
            color: #666;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .logout-link a:hover {
            color: #333;
            text-decoration: underline;
        }
        
        .otp-section {
            background: #f0f7ff;
            border: 2px solid #2196f3;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .otp-section h4 {
            font-size: 14px;
            color: #1976d2;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .otp-section h4 i {
            font-size: 16px;
        }
        
        .email-display {
            background: white;
            padding: 10px 12px;
            border-radius: 4px;
            margin-bottom: 12px;
            font-size: 14px;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .email-display i {
            color: #2196f3;
            margin-right: 8px;
        }
        
        .otp-input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
        }
        
        .btn-send-otp {
            padding: 10px 20px;
            background: #2196f3;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        
        .btn-send-otp:hover:not(:disabled) {
            background: #1976d2;
        }
        
        .btn-send-otp:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .otp-input-wrapper {
            flex: 1;
            display: none;
        }
        
        .otp-input-wrapper.show {
            display: block;
        }
        
        .otp-input-wrapper input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #2196f3;
            border-radius: 4px;
            font-size: 14px;
            letter-spacing: 4px;
            text-align: center;
            font-weight: 600;
        }
        
        .otp-status {
            font-size: 12px;
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 8px;
            display: none;
        }
        
        .otp-status.show {
            display: block;
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
        
        .otp-status i {
            margin-right: 6px;
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }
            
            .logo h1 {
                font-size: 28px;
            }
            
            .otp-input-group {
                flex-direction: column;
            }
            
            .btn-send-otp {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>Wannabees KTV</h1>
            <p>First Time Login - Password Setup</p>
        </div>
        
        <div class="welcome-box">
            <h3>Welcome, <span class="username"><?= $displayName ?></span>!</h3>
            <p>Your account has been created. For security reasons, please change your temporary password before continuing.</p>
        </div>
        
        <div class="info-box">
            <i class="fas fa-shield-alt"></i>
            <strong>Security Notice:</strong> You must change your password to access your account.
        </div>
        
        <div id="alertBox" class="alert"></div>
        
        <form id="changePasswordForm">
            <!-- OTP Verification Section -->
            <div class="otp-section">
                <h4>
                    <i class="fas fa-envelope-circle-check"></i>
                    Email Verification Required
                </h4>
                
                <div class="email-display">
                    <i class="fas fa-envelope"></i>
                    <strong><?= htmlspecialchars($user['email']) ?></strong>
                </div>
                
                <div class="otp-input-group">
                    <button type="button" class="btn-send-otp" id="sendOtpBtn" onclick="sendPasswordChangeOTP()">
                        <i class="fas fa-paper-plane"></i> Send OTP
                    </button>
                    
                    <div class="otp-input-wrapper" id="otpInputWrapper">
                        <input type="text" id="otpCode" name="otp" 
                               placeholder="000000" 
                               maxlength="6" 
                               pattern="[0-9]{6}"
                               inputmode="numeric"
                               required>
                    </div>
                </div>
                
                <div class="otp-status" id="otpStatus"></div>
            </div>
            
            <div class="form-group">
                <label for="currentPassword">
                    <i class="fas fa-key"></i> Current Password
                </label>
                <div class="password-input-wrapper">
                    <input type="password" id="currentPassword" name="current_password" required 
                           placeholder="Enter your current password">
                    <button type="button" class="password-toggle" onclick="togglePassword('currentPassword')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <div class="form-group">
                <label for="newPassword">
                    <i class="fas fa-lock"></i> New Password
                </label>
                <div class="password-input-wrapper">
                    <input type="password" id="newPassword" name="new_password" required 
                           placeholder="Enter your new password">
                    <button type="button" class="password-toggle" onclick="togglePassword('newPassword')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                <div class="password-requirements">
                    <ul>
                        <li>At least 8 characters long</li>
                        <li>Contains letters and numbers</li>
                        <li>Different from current password</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirmPassword">
                    <i class="fas fa-lock"></i> Confirm New Password
                </label>
                <div class="password-input-wrapper">
                    <input type="password" id="confirmPassword" name="confirm_password" required 
                           placeholder="Re-enter your new password">
                    <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword')">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn-submit" id="submitBtn">
                <i class="fas fa-check-circle"></i>
                <span>Change Password & Continue</span>
            </button>
        </form>
        
        <div class="logout-link">
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <script>
        let otpSent = false;
        
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.parentElement.querySelector('.password-toggle i');
            
            if (input.type === 'password') {
                input.type = 'text';
                button.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                button.className = 'fas fa-eye';
            }
        }
        
        function showAlert(message, type) {
            const alertBox = document.getElementById('alertBox');
            alertBox.textContent = message;
            alertBox.className = 'alert alert-' + type + ' show';
            
            // Auto-hide error alerts after 5 seconds
            if (type === 'error') {
                setTimeout(() => {
                    alertBox.classList.remove('show');
                }, 5000);
            }
        }
        
        function showOtpStatus(message, type) {
            const otpStatus = document.getElementById('otpStatus');
            otpStatus.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            otpStatus.className = 'otp-status ' + type + ' show';
        }
        
        async function sendPasswordChangeOTP() {
            const sendOtpBtn = document.getElementById('sendOtpBtn');
            const otpInputWrapper = document.getElementById('otpInputWrapper');
            
            // Disable button and show loading
            sendOtpBtn.disabled = true;
            sendOtpBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            
            try {
                const response = await fetch('../api/auth/send_password_change_otp.php', {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showOtpStatus(`OTP sent to ${result.email_hint}. Valid for 15 minutes.`, 'success');
                    otpInputWrapper.classList.add('show');
                    otpSent = true;
                    
                    // Update button text
                    sendOtpBtn.innerHTML = '<i class="fas fa-rotate"></i> Resend OTP';
                    sendOtpBtn.disabled = false;
                } else {
                    showOtpStatus(result.error || 'Failed to send OTP', 'error');
                    sendOtpBtn.disabled = false;
                    sendOtpBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send OTP';
                }
            } catch (error) {
                console.error('Error:', error);
                showOtpStatus('Network error. Please try again.', 'error');
                sendOtpBtn.disabled = false;
                sendOtpBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send OTP';
            }
        }
        
        document.getElementById('changePasswordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const otpCode = document.getElementById('otpCode').value;
            const submitBtn = document.getElementById('submitBtn');
            
            // Check if OTP was sent
            if (!otpSent) {
                showAlert('Please send OTP to your email first', 'error');
                return;
            }
            
            // Check if OTP is entered
            if (!otpCode || otpCode.length !== 6) {
                showAlert('Please enter the 6-digit OTP sent to your email', 'error');
                return;
            }
            
            // Client-side validation
            if (newPassword.length < 8) {
                showAlert('New password must be at least 8 characters long', 'error');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showAlert('New password and confirm password do not match', 'error');
                return;
            }
            
            if (currentPassword === newPassword) {
                showAlert('New password must be different from current password', 'error');
                return;
            }
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing Password...';
            
            try {
                const formData = new FormData();
                formData.append('current_password', currentPassword);
                formData.append('new_password', newPassword);
                formData.append('otp', otpCode);
                
                const response = await fetch('../api/users/first_time_password_change.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Password changed successfully! Redirecting...', 'success');
                    
                    // Redirect to appropriate dashboard after 1.5 seconds
                    setTimeout(() => {
                        window.location.href = result.redirect || '../index.php';
                    }, 1500);
                } else {
                    showAlert(result.error || 'Failed to change password', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> <span>Change Password & Continue</span>';
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Network error. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> <span>Change Password & Continue</span>';
            }
        });
    </script>
</body>
</html>
