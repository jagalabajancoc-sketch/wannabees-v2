<?php
session_start();

// Generate math captcha
$num1 = rand(1, 10);
$num2 = rand(1, 10);
$_SESSION['forgot_captcha_answer'] = $num1 + $num2;
$_SESSION['forgot_captcha_question'] = "$num1 + $num2";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - Wannabees KTV</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    
    .forgot-container {
      background: white;
      border-radius: 20px;
      padding: 40px 35px;
      width: 100%;
      max-width: 500px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      position: relative;
    }
    
    .timer-badge {
      position: absolute;
      top: 20px;
      right: 20px;
      background: #d1f2eb;
      color: #00695c;
      padding: 8px 16px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 600;
      border: 2px solid #4db6ac;
      display: none;
    }
    
    .timer-badge.show {
      display: block;
    }
    
    .logo-wrapper {
      text-align: center;
      margin-bottom: 20px;
    }
    
    .logo {
      width: 100px;
      height: auto;
      margin-bottom: 10px;
    }
    
    h1 {
      font-size: 22px;
      color: #2c2c2c;
      text-align: center;
      margin-bottom: 10px;
      font-weight: 600;
    }
    
    .subtitle {
      text-align: center;
      color: #666;
      font-size: 14px;
      margin-bottom: 30px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    label {
      display: block;
      font-size: 14px;
      color: #666;
      margin-bottom: 8px;
      font-weight: 500;
    }
    
    input {
      width: 100%;
      padding: 13px 15px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      font-size: 15px;
      transition: all 0.3s ease;
      background: white;
      outline: none;
    }
    
    input:focus {
      border-color: #f2a20a;
      box-shadow: 0 0 0 3px rgba(242, 162, 10, 0.1);
    }
    
    input:disabled {
      background: #f5f5f5;
      cursor: not-allowed;
    }
    
    .password-wrapper {
      position: relative;
    }
    
    .password-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: #666;
      font-size: 18px;
      padding: 5px;
    }
    
    .password-toggle:hover {
      color: #f2a20a;
    }
    
    .btn {
      width: 100%;
      padding: 14px;
      border: none;
      border-radius: 10px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    
    .btn-primary {
      background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
      color: white;
      box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
    }
    
    .btn-primary:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(13, 110, 253, 0.4);
    }
    
    .btn-success {
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      color: white;
      box-shadow: 0 4px 15px rgba(242, 162, 10, 0.3);
    }
    
    .btn-success:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(242, 162, 10, 0.4);
    }
    
    .btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
    
    .alert {
      padding: 12px 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
      display: none;
      animation: slideDown 0.3s ease;
    }
    
    .alert.show {
      display: block;
    }
    
    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .alert-success {
      background: #d1e7dd;
      border: 1px solid #badbcc;
      color: #0f5132;
    }
    
    .alert-danger {
      background: #f8d7da;
      border: 1px solid #f5c2c7;
      color: #842029;
    }
    
    .alert-info {
      background: #cff4fc;
      border: 1px solid #b6effb;
      color: #055160;
    }
    
    .back-link {
      text-align: center;
      margin-top: 20px;
    }
    
    .back-link a {
      color: #0d6efd;
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
    }
    
    .back-link a:hover {
      text-decoration: underline;
    }
    
    #stepTwo {
      display: none;
    }
    
    .resend-info {
      text-align: center;
      margin-top: 15px;
      font-size: 13px;
      color: #666;
      display: none;
    }
    
    .resend-info.show {
      display: block;
    }
    
    .resend-link {
      color: #0d6efd;
      cursor: pointer;
      text-decoration: underline;
      font-weight: 600;
    }
    
    .captcha-wrapper {
      background: #fff;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      padding: 15px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 15px;
      transition: all 0.3s ease;
    }
    
    .captcha-wrapper:focus-within {
      border-color: #0d6efd;
      box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
    }
    
    .captcha-question {
      flex: 1;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 16px;
      font-weight: 600;
      color: #333;
    }
    
    .captcha-question i {
      color: #0d6efd;
      font-size: 20px;
    }
    
    .captcha-input {
      width: 80px;
      padding: 10px;
      border: 2px solid #e0e0e0;
      border-radius: 6px;
      font-size: 16px;
      text-align: center;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .captcha-input:focus {
      outline: none;
      border-color: #0d6efd;
    }
    
    .captcha-input.correct {
      border-color: #4caf50;
      background: #e8f5e9;
      color: #2e7d32;
    }
    
    .captcha-input.incorrect {
      border-color: #f44336;
      background: #ffebee;
      color: #c62828;
    }
    
    .captcha-refresh {
      background: #0d6efd;
      border: none;
      color: white;
      width: 36px;
      height: 36px;
      border-radius: 8px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s ease;
      font-size: 16px;
    }
    
    .captcha-refresh:hover {
      background: #0a58ca;
      transform: rotate(180deg);
    }
    
    @media (max-width: 480px) {
      .forgot-container {
        padding: 30px 20px;
      }
      
      .captcha-wrapper {
        flex-direction: column;
        gap: 12px;
        padding: 12px;
      }
      
      .captcha-question {
        font-size: 14px;
        text-align: center;
        justify-content: center;
      }
      
      .captcha-question i {
        font-size: 18px;
      }
      
      .captcha-input {
        width: 100%;
        max-width: 120px;
        margin: 0 auto;
        font-size: 18px;
        padding: 12px;
      }
      
      .captcha-refresh {
        width: 100%;
        max-width: 120px;
        height: 40px;
        margin: 0 auto;
      }
    }
  </style>
</head>
<body>
  <div class="forgot-container">
    <div class="timer-badge" id="timerBadge">
      <i class="fas fa-clock"></i> <span id="timerText">90 seconds remaining</span>
    </div>
    
    <div class="logo-wrapper">
      <img src="../assets/images/KTVL.png" alt="Wannabees Family KTV" class="logo" onerror="this.style.display='none'">
    </div>
    
    <h1>Forgot Password</h1>
    
    <div id="alertBox" class="alert"></div>
    
    <!-- Step 1: Enter Email and Send OTP -->
    <div id="stepOne">
      <p class="subtitle">Enter your registered email to receive an OTP</p>
      
      <form id="sendOtpForm">
        <div class="form-group">
          <label for="email">Enter Registered Email Id</label>
          <input type="email" id="email" name="email" placeholder="Enter Registered Email Id" required>
        </div>
        
        <div class="form-group">
          <label for="captcha">Security Verification</label>
          <div class="captcha-wrapper">
            <div class="captcha-question">
              <i class="fas fa-shield-halved"></i>
              <span>What is <strong id="captchaQuestion"><?= $_SESSION['forgot_captcha_question'] ?></strong>?</span>
            </div>
            <input type="number" id="captcha" name="captcha" class="captcha-input" placeholder="?" required autocomplete="off">
            <button type="button" class="captcha-refresh" onclick="refreshCaptcha()" title="Refresh captcha">
              <i class="fas fa-arrows-rotate"></i>
            </button>
          </div>
        </div>
        
        <button type="submit" class="btn btn-primary" id="sendOtpBtn">
          <i class="fas fa-paper-plane"></i> SEND OTP
        </button>
      </form>
    </div>
    
    <!-- Step 2: Verify OTP and Reset Password -->
    <div id="stepTwo">
      <p class="subtitle">Check your email for the OTP and reset your password</p>
      
      <form id="resetPasswordForm">
        <div class="form-group">
          <label for="emailConfirm">Enter Registered Email Id</label>
          <input type="email" id="emailConfirm" name="email" readonly>
        </div>
        
        <div class="form-group">
          <label for="otp">Enter OTP</label>
          <input type="text" id="otp" name="otp" placeholder="Enter OTP" maxlength="6" required>
        </div>
        
        <div class="form-group">
          <label for="newPassword">Enter New Password</label>
          <div class="password-wrapper">
            <input type="password" id="newPassword" name="new_password" placeholder="Enter New Password" style="padding-right: 45px;" required minlength="6">
            <button type="button" class="password-toggle" onclick="togglePassword('newPassword', this)">
              <i class="far fa-eye"></i>
            </button>
          </div>
        </div>
        
        <div class="form-group">
          <label for="confirmPassword">Confirm New Password</label>
          <div class="password-wrapper">
            <input type="password" id="confirmPassword" name="confirm_password" placeholder="Confirm New Password" style="padding-right: 45px;" required minlength="6">
            <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', this)">
              <i class="far fa-eye"></i>
            </button>
          </div>
        </div>
        
        <button type="submit" class="btn btn-success" id="submitBtn">
          <i class="fas fa-check"></i> Submit
        </button>
      </form>
      
      <div class="resend-info" id="resendInfo">
        <span class="resend-link" onclick="resendOtp()">
          <i class="fas fa-redo"></i> Resend OTP
        </span>
      </div>
    </div>
    
    <div class="back-link">
      <a href="../index.php">
        <i class="fas fa-arrow-left"></i> Back to Sign In
      </a>
    </div>
  </div>

  <script>
    let timerInterval = null;
    let remainingSeconds = 90;
    
    function showAlert(message, type) {
      const alertBox = document.getElementById('alertBox');
      alertBox.className = `alert alert-${type} show`;
      alertBox.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'}"></i> ${message}`;
      setTimeout(() => {
        alertBox.classList.remove('show');
      }, 5000);
    }
    
    // Real-time captcha validation
    const captchaInput = document.getElementById('captcha');
    if (captchaInput) {
      captchaInput.addEventListener('input', async function() {
        const userAnswer = this.value.trim();
        if (!userAnswer) {
          this.classList.remove('correct', 'incorrect');
          return;
        }
        
        try {
          const response = await fetch('validate_forgot_captcha.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `answer=${encodeURIComponent(userAnswer)}`
          });
          const data = await response.json();
          
          if (data.correct) {
            this.classList.remove('incorrect');
            this.classList.add('correct');
          } else {
            this.classList.remove('correct');
            this.classList.add('incorrect');
          }
        } catch (error) {
          console.error('Error validating captcha:', error);
        }
      });
    }
    
    async function refreshCaptcha() {
      try {
        const response = await fetch('refresh_forgot_captcha.php');
        const data = await response.json();
        if (data.success) {
          document.getElementById('captchaQuestion').textContent = data.question;
          const captchaField = document.getElementById('captcha');
          captchaField.value = '';
          captchaField.classList.remove('correct', 'incorrect');
        }
      } catch (error) {
        console.error('Error refreshing captcha:', error);
      }
    }
    
    function togglePassword(inputId, button) {
      const input = document.getElementById(inputId);
      const icon = button.querySelector('i');
      
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }
    
    function startTimer() {
      remainingSeconds = 90;
      const timerBadge = document.getElementById('timerBadge');
      const timerText = document.getElementById('timerText');
      const resendInfo = document.getElementById('resendInfo');
      
      timerBadge.classList.add('show');
      resendInfo.classList.remove('show');
      
      if (timerInterval) {
        clearInterval(timerInterval);
      }
      
      timerInterval = setInterval(() => {
        remainingSeconds--;
        timerText.textContent = `${remainingSeconds} seconds remaining`;
        
        if (remainingSeconds <= 0) {
          clearInterval(timerInterval);
          timerBadge.classList.remove('show');
          resendInfo.classList.add('show');
        }
      }, 1000);
    }
    
    function resendOtp() {
      // Go back to step one
      document.getElementById('stepTwo').style.display = 'none';
      document.getElementById('stepOne').style.display = 'block';
      document.getElementById('timerBadge').classList.remove('show');
      document.getElementById('resendInfo').classList.remove('show');
      if (timerInterval) {
        clearInterval(timerInterval);
      }
      showAlert('You can now resend OTP', 'info');
    }
    
    // Send OTP Form
    document.getElementById('sendOtpForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const email = document.getElementById('email').value;
      const captcha = document.getElementById('captcha').value;
      const sendOtpBtn = document.getElementById('sendOtpBtn');
      
      if (!captcha) {
        showAlert('Please complete the security verification', 'danger');
        return;
      }
      
      sendOtpBtn.disabled = true;
      sendOtpBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
      
      try {
        const response = await fetch('../api/auth/send_otp.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `email=${encodeURIComponent(email)}&captcha=${encodeURIComponent(captcha)}`
        });
        
        const result = await response.json();
        
        if (result.success) {
          showAlert('Check your registered email for the One-Time Password (OTP) and use it to reset your account password', 'info');
          
          // Switch to step two
          setTimeout(() => {
            document.getElementById('stepOne').style.display = 'none';
            document.getElementById('stepTwo').style.display = 'block';
            document.getElementById('emailConfirm').value = email;
            startTimer();
          }, 2000);
        } else {
          showAlert(result.error || 'Failed to send OTP. Please try again.', 'danger');
          // Refresh captcha on error
          refreshCaptcha();
          sendOtpBtn.disabled = false;
          sendOtpBtn.innerHTML = '<i class="fas fa-paper-plane"></i> SEND OTP';
        }
      } catch (error) {
        showAlert('An error occurred. Please try again.', 'danger');
        refreshCaptcha();
        sendOtpBtn.disabled = false;
        sendOtpBtn.innerHTML = '<i class="fas fa-paper-plane"></i> SEND OTP';
      }
    });
    
    // Reset Password Form
    document.getElementById('resetPasswordForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const email = document.getElementById('emailConfirm').value;
      const otp = document.getElementById('otp').value;
      const newPassword = document.getElementById('newPassword').value;
      const confirmPassword = document.getElementById('confirmPassword').value;
      
      if (newPassword !== confirmPassword) {
        showAlert('Passwords do not match. Please try again.', 'danger');
        return;
      }
      
      if (newPassword.length < 6) {
        showAlert('Password must be at least 6 characters long.', 'danger');
        return;
      }
      
      const submitBtn = document.getElementById('submitBtn');
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting...';
      
      try {
        const response = await fetch('../api/auth/reset_password.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `email=${encodeURIComponent(email)}&otp=${encodeURIComponent(otp)}&new_password=${encodeURIComponent(newPassword)}`
        });
        
        const result = await response.json();
        
        if (result.success) {
          showAlert('Password reset successfully! Redirecting to login...', 'success');
          setTimeout(() => {
            window.location.href = '../index.php';
          }, 2000);
        } else {
          showAlert(result.error || 'Failed to reset password. Please try again.', 'danger');
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="fas fa-check"></i> Submit';
        }
      } catch (error) {
        showAlert('An error occurred. Please try again.', 'danger');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-check"></i> Submit';
      }
    });
  </script>
</body>
</html>
