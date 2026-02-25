<?php
session_start();
require_once __DIR__ . '/../../db.php';

// Only owner/admin (adjust role_id if needed)
if ($_SESSION['role_id'] != 1) {
    die("Unauthorized");
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role_id = intval($_POST['role_id']);

    $defaultPasswordPlain = "Temp@123";
    $defaultPassword = password_hash($defaultPasswordPlain, PASSWORD_DEFAULT);

    $otp = rand(100000, 999999);
    $otpExpiry = date("Y-m-d H:i:s", strtotime("+15 minutes"));

    $stmt = $pdo->prepare("
        INSERT INTO users 
        (username, email, password, role_id, otp, otp_expiry, must_change_password)
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $username,
        $email,
        $defaultPassword,
        $role_id,
        $otp,
        $otpExpiry
    ]);

    // Send OTP email
    mail(
        $email,
        "Your Account OTP",
        "Your account has been created.\n\nOTP: $otp\nExpires in 15 minutes.\n\nLogin using your default password and change it immediately."
    );

    $message = "User created successfully. OTP sent to email.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create User</title>
</head>
<body>
<h2>Create System User</h2>

<form method="post">
    <input type="text" name="username" placeholder="Username" required><br><br>
    <input type="email" name="email" placeholder="Email" required><br><br>

    <select name="role_id">
        <option value="2">Staff</option>
        <option value="3">Cashier</option>
    </select><br><br>

    <button type="submit">Create User</button>
</form>

<p><?= $message ?></p>
</body>
</html>
