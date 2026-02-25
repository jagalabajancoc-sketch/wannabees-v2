if (password_verify($password, $user['password'])) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['role_id'] = $user['role_id'];

    if ($user['must_change_password'] == 1) {
        header("Location: verify_otp.php");
        exit;
    }

    header("Location: customer/fallback.php");
    exit;
}
