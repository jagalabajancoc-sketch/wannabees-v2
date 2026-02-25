<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $role = intval($_SESSION['role_id']);
    if ($role === 1) header('Location: owner/dashboard.php');
    else if ($role === 2) header('Location: index.php?error=Staff accounts do not use the system.');
    else if ($role === 3) header('Location: cashier/dashboard.php');
    else if ($role === 4) header('Location: customer/room_tablet.php');
    else header('Location: customer/fallback.php');
    exit;
}
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

// Generate math captcha
$num1 = rand(1, 10);
$num2 = rand(1, 10);
$_SESSION['captcha_answer'] = $num1 + $num2;
$_SESSION['captcha_question'] = "$num1 + $num2";
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Login — Wannabees Family KTV</title>
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
    
    .login-container {
      background: #f5f5f5;
      border-radius: 20px;
      padding: 40px 35px;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      animation: slideIn 0.5s ease-out;
    }
    
    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .logo-wrapper {
      text-align: center;
      margin-bottom: 25px;
    }
    
    .logo {
      width: 120px;
      height: auto;
      margin-bottom: 15px;
      animation: fadeIn 0.8s ease-out;
    }
    
    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    
    h1 {
      font-size: 22px;
      color: #2c2c2c;
      text-align: center;
      margin-bottom: 30px;
      font-weight: 600;
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
    
    input::placeholder {
      color: #bbb;
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
      transition: color 0.3s;
    }
    
    .password-toggle:hover {
      color: #f2a20a;
    }
    
    .error-message {
      background: #fdecea;
      border: 1px solid #f5b7b1;
      color: #c0392b;
      padding: 12px 15px;
      border-radius: 8px;
      margin-top: 12px;
      margin-bottom: 0;
      font-size: 14px;
      animation: shake 0.5s ease;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-10px); }
      75% { transform: translateX(10px); }
    }
    
    .error-message i {
      font-size: 16px;
    }
    
    .btn-login {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      border: none;
      border-radius: 10px;
      color: white;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(242, 162, 10, 0.3);
      margin-top: 20px;
    }
    
    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(242, 162, 10, 0.4);
    }
    
    .btn-login:active {
      transform: translateY(0);
    }
    
    .forgot-password {
      text-align: right;
      margin-top: 10px;
    }
    
    .forgot-password a {
      color: #f2a20a;
      text-decoration: none;
      font-size: 13px;
      font-weight: 500;
      transition: color 0.3s;
    }
    
    .forgot-password a:hover {
      color: #d89209;
      text-decoration: underline;
    }
    
    .help-text {
      text-align: center;
      margin-top: 20px;
      font-size: 12px;
      color: #888;
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
      border-color: #f2a20a;
      box-shadow: 0 0 0 3px rgba(242, 162, 10, 0.1);
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
      color: #f2a20a;
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
      border-color: #f2a20a;
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
      background: #f2a20a;
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
      background: #d89209;
      transform: rotate(180deg);
    }
    
    @media (max-width: 480px) {
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
  <div class="login-container">
    <div class="logo-wrapper">
      <img src="assets/images/KTVL.png" alt="Wannabees Family KTV" class="logo">
    </div>
    <h1>Wannabees Family KTV</h1>

    <form action="auth/auth.php" method="post" autocomplete="off">
      <div class="form-group">
        <label for="username">Username</label>
        <input id="username" name="username" type="text" required maxlength="50" placeholder="Enter username" autofocus>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="password-wrapper">
          <input id="password" name="password" type="password" required maxlength="128" placeholder="Enter password" style="padding-right: 45px;">
          <button type="button" class="password-toggle" id="togglePassword">
            <i class="far fa-eye" id="eyeIcon"></i>
          </button>
        </div>
        
        <?php if ($error): ?>
          <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= $error ?></span>
          </div>
        <?php endif; ?>
        
        <div class="forgot-password">
          <a href="auth/forgot_password.php">Forgot Password?</a>
        </div>
      </div>

      <div class="form-group">
        <label for="captcha">Security Verification</label>
        <div class="captcha-wrapper">
          <div class="captcha-question">
            <i class="fas fa-shield-halved"></i>
            <span>What is <strong id="captchaQuestion"><?= $_SESSION['captcha_question'] ?></strong>?</span>
          </div>
          <input type="number" id="captcha" name="captcha" class="captcha-input" placeholder="?" required autocomplete="off">
          <button type="button" class="captcha-refresh" onclick="refreshCaptcha()" title="Refresh captcha">
            <i class="fas fa-arrows-rotate"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn-login">Login</button>
    </form>
    
    <div class="help-text">
      Need help? Contact your system administrator.
    </div>
  </div>

  <script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordField = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    const captchaInput = document.getElementById('captcha');
    
    togglePassword.addEventListener('click', function() {
      const type = passwordField.type === 'password' ? 'text' : 'password';
      passwordField.type = type;
      
      if (type === 'password') {
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
      } else {
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
      }
    });
    
    // Real-time captcha validation
    captchaInput.addEventListener('input', async function() {
      const userAnswer = this.value.trim();
      if (!userAnswer) {
        this.classList.remove('correct', 'incorrect');
        return;
      }
      
      try {
        const response = await fetch('auth/validate_captcha.php', {
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
    
    async function refreshCaptcha() {
      try {
        const response = await fetch('auth/refresh_captcha.php');
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
  </script>
</body>
</html>