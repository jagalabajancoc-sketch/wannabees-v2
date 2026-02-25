<?php
// Test send_settings_otp.php API directly
session_start();

// Simulate owner session
$_SESSION['user_id'] = 2;  // owner user_id
$_SESSION['username'] = 'owner';
$_SESSION['role_id'] = 1;

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['action'] = 'password';
$_POST['new_email'] = '';

echo "Testing send_settings_otp.php\n";
echo "============================\n";
echo "Session: user_id = " . $_SESSION['user_id'] . "\n";
echo "POST: action = password\n\n";

// Capture output
ob_start();

// Include the API file
require_once __DIR__ . '/api/auth/send_settings_otp.php';

$output = ob_get_clean();

echo "API Response:\n";
echo $output;
?>
