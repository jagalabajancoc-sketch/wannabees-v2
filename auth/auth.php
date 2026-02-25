<?php
// auth.php - Login authentication
session_start();
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$captcha = isset($_POST['captcha']) ? trim($_POST['captcha']) : '';

if (empty($username) || empty($password)) {
    header('Location: ../index.php?error=Please enter username and password');
    exit;
}

// Validate captcha
if (empty($captcha)) {
    header('Location: ../index.php?error=Please complete the security verification');
    exit;
}

if (!isset($_SESSION['captcha_answer']) || intval($captcha) !== intval($_SESSION['captcha_answer'])) {
    header('Location: ../index.php?error=Incorrect security verification. Please try again.');
    exit;
}

// Clear captcha after validation
unset($_SESSION['captcha_answer']);
unset($_SESSION['captcha_question']);

// Query user
$stmt = $mysqli->prepare("SELECT user_id, username, password, role_id, is_active, must_change_password, display_name FROM users WHERE username = ?");
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header('Location: ../index.php?error=Invalid username or password');
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Check if account is active
if ($user['is_active'] != 1) {
    header('Location: ../index.php?error=Account is disabled. Contact administrator.');
    exit;
}

// Verify password - support both hashed and plain text for migration
$passwordValid = false;

// Try hashed password first (secure)
if (password_verify($password, $user['password'])) {
    $passwordValid = true;
} 
// Fallback to plain text comparison (for legacy accounts)
elseif ($user['password'] === $password) {
    $passwordValid = true;
    
    // Auto-upgrade to hashed password for security
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $updateStmt = $mysqli->prepare("UPDATE users SET password = ? WHERE user_id = ?");
    $updateStmt->bind_param('si', $hashedPassword, $user['user_id']);
    $updateStmt->execute();
    $updateStmt->close();
}

if (!$passwordValid) {
    header('Location: ../index.php?error=Invalid username or password');
    exit;
}

// Login successful - set session
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role_id'] = $user['role_id'];
$_SESSION['display_name'] = $user['display_name'];

// Check if user must change password first (for new accounts)
if ($user['must_change_password'] == 1) {
    header('Location: first_time_password_change.php');
    exit;
}

// Redirect based on role
$role = intval($user['role_id']);
if ($role === 1) {
    header('Location: ../owner/dashboard.php');
} elseif ($role === 2) {
    header('Location: ../index.php?error=Staff accounts do not use the system. Please use radio communication.');
} elseif ($role === 3) {
    header('Location: ../cashier/dashboard.php');
} elseif ($role === 4) {
    header('Location: ../customer/room_tablet.php');
} else {
    header('Location: ../customer/fallback.php');
}
exit;
?>