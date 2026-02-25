<?php
// Find actual user and test sending
require_once __DIR__ . '/db.php';

echo "Finding test user...\n";
$result = $mysqli->query("SELECT user_id, username, email FROM users LIMIT 1");
if ($result && $result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "Found user: user_id=" . $user['user_id'] . ", username=" . $user['username'] . ", email=" . $user['email'] . "\n\n";
    
    // TEST 1: Password change OTP (no metadata)
    echo "TEST 1: Creating password change OTP (no metadata)...\n";
    $otp1 = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiry = date('Y-m-d H:i:s', time() + 600);
    $action = 'password';
    
    $stmt = $mysqli->prepare("INSERT INTO password_reset_otps (user_id, email, otp_code, action, expires_at) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('issss', $user['user_id'], $user['email'], $otp1, $action, $expiry);
    
    if ($stmt->execute()) {
        echo "✓ Password OTP created: $otp1\n";
        $mysqli->query("DELETE FROM password_reset_otps WHERE otp_code = '$otp1'");
    } else {
        echo "✗ Password OTP failed: " . $stmt->error . "\n";
    }
    $stmt->close();
    
    // TEST 2: Email change OTP (with metadata JSON)
    echo "\nTEST 2: Creating email change OTP (with metadata JSON)...\n";
    $otp2 = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $action_email = 'email';
    $metadata = json_encode(['new_email' => 'newemail@example.com']);
    
    $stmt = $mysqli->prepare("INSERT INTO password_reset_otps (user_id, email, otp_code, action, metadata, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isssss', $user['user_id'], $user['email'], $otp2, $action_email, $metadata, $expiry);
    
    if ($stmt->execute()) {
        echo "✓ Email OTP created: $otp2\n";
        echo "  Metadata: $metadata\n";
        $mysqli->query("DELETE FROM password_reset_otps WHERE otp_code = '$otp2'");
    } else {
        echo "✗ Email OTP failed: " . $stmt->error . "\n";
    }
    $stmt->close();
    
    echo "\n✓ All tests passed!\n";
} else {
    echo "No users found in database!\n";
}
?>
