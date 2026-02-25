<?php
session_start();
require_once __DIR__ . '/../db.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$error = '';
$rental_info = null;

// If token is provided, validate it
if ($token) {
    $stmt = $mysqli->prepare("
        SELECT ra.*, r.room_number, rt.type_name, rent.started_at, rent.total_minutes, b.grand_total
        FROM rental_access ra
        JOIN rooms r ON ra.room_id = r.room_id
        JOIN room_types rt ON r.room_type_id = rt.room_type_id
        JOIN rentals rent ON ra.rental_id = rent.rental_id
        LEFT JOIN bills b ON rent.rental_id = b.rental_id
        WHERE ra.qr_token = ? AND ra.expires_at > NOW() AND rent.ended_at IS NULL
        LIMIT 1
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $rental_info = $result->fetch_assoc();
    $stmt->close();

    if (!$rental_info) {
        $error = 'Invalid or expired QR code. Please ask staff for assistance.';
    }
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $entered_otp = preg_replace('/[^0-9]/', '', $_POST['otp']);
    $token = $_POST['token'];

    $stmt = $mysqli->prepare("
        SELECT ra.*, r.room_number, rt.type_name, rent.started_at, rent.total_minutes, 
               rent.rental_id, b.bill_id, b.grand_total
        FROM rental_access ra
        JOIN rooms r ON ra.room_id = r.room_id
        JOIN room_types rt ON r.room_type_id = rt.room_type_id
        JOIN rentals rent ON ra.rental_id = rent.rental_id
        LEFT JOIN bills b ON rent.rental_id = b.rental_id
        WHERE ra.qr_token = ? AND ra.otp_code = ? AND ra.expires_at > NOW() AND rent.ended_at IS NULL
        LIMIT 1
    ");
    $stmt->bind_param('ss', $token, $entered_otp);
    $stmt->execute();
    $result = $stmt->get_result();
    $access = $result->fetch_assoc();
    $stmt->close();

    if ($access) {
        // Set session for customer
        $_SESSION['customer_rental_id'] = $access['rental_id'];
        $_SESSION['customer_room_id'] = $access['room_id'];
        $_SESSION['customer_room_number'] = $access['room_number'];

        // Mark OTP as used
        $stmt = $mysqli->prepare("UPDATE rental_access SET is_used = 1 WHERE access_id = ?");
        $stmt->bind_param('i', $access['access_id']);
        $stmt->execute();
        $stmt->close();

        header('Location: ../customer/dashboard.php');
        exit;
    } else {
        $error = 'Invalid OTP code. Please try again.';
        // Re-fetch rental info for display
        $stmt = $mysqli->prepare("
            SELECT ra.*, r.room_number, rt.type_name, rent.started_at, rent.total_minutes, b.grand_total
            FROM rental_access ra
            JOIN rooms r ON ra.room_id = r.room_id
            JOIN room_types rt ON r.room_type_id = rt.room_type_id
            JOIN rentals rent ON ra.rental_id = rent.rental_id
            LEFT JOIN bills b ON rent.rental_id = b.rental_id
            WHERE ra.qr_token = ? AND ra.expires_at > NOW() AND rent.ended_at IS NULL
            LIMIT 1
        ");
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $rental_info = $result->fetch_assoc();
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Access - Wannabees KTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            color: white;
        }
        h1 { font-size: 24px; color: #2c2c2c; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; font-size: 14px; }
        .room-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #f5c542;
        }
        .room-info h3 { color: #2c2c2c; margin-bottom: 5px; }
        .room-info p { color: #666; font-size: 14px; }
        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 25px;
        }
        .otp-input {
            width: 50px;
            height: 60px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            transition: all 0.3s;
        }
        .otp-input:focus {
            border-color: #f5c542;
            outline: none;
            box-shadow: 0 0 0 3px rgba(245,197,66,0.2);
        }
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(245,197,66,0.4);
        }
        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .help-text {
            margin-top: 20px;
            color: #999;
            font-size: 12px;
        }
        .expired {
            text-align: center;
            padding: 40px;
        }
        .expired i {
            font-size: 64px;
            color: #e74c3c;
            margin-bottom: 20px;
        }
        .expired h2 {
            color: #2c2c2c;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <?php if ($error && !$rental_info): ?>
            <div class="expired">
                <i class="fas fa-exclamation-circle"></i>
                <h2>Access Expired</h2>
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php else: ?>
            <div class="logo">
                <i class="fas fa-music"></i>
            </div>
            <h1>Welcome to Wannabees KTV</h1>
            <p class="subtitle">Enter the 6-digit code to access your room</p>

            <?php if ($rental_info): ?>
            <div class="room-info">
                <h3><i class="fas fa-door-open"></i> Room <?= htmlspecialchars($rental_info['room_number']) ?></h3>
                <p><?= htmlspecialchars($rental_info['type_name']) ?></p>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="otpForm">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="otp-inputs">
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric">
                </div>
                <input type="hidden" name="otp" id="otpValue">
                <button type="submit" class="btn-submit">
                    <i class="fas fa-unlock"></i> Access Room
                </button>
            </form>

            <p class="help-text">
                <i class="fas fa-info-circle"></i> Ask staff for your access code
            </p>
        <?php endif; ?>
    </div>

    <script>
        const inputs = document.querySelectorAll('.otp-input');
        const otpValue = document.getElementById('otpValue');

        inputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 1) {
                    if (index < inputs.length - 1) {
                        inputs[index + 1].focus();
                    }
                }
                updateOtpValue();
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasted = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
                pasted.split('').forEach((char, i) => {
                    if (inputs[i]) inputs[i].value = char;
                });
                updateOtpValue();
            });
        });

        function updateOtpValue() {
            otpValue.value = Array.from(inputs).map(i => i.value).join('');
        }

        document.getElementById('otpForm').addEventListener('submit', (e) => {
            updateOtpValue();
            if (otpValue.value.length !== 6) {
                e.preventDefault();
                alert('Please enter all 6 digits');
            }
        });
    </script>
</body>
</html>