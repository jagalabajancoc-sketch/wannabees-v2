<?php
session_start();
header('Content-Type: application/json');

// Generate new math captcha for forgot password
$num1 = rand(1, 10);
$num2 = rand(1, 10);
$_SESSION['forgot_captcha_answer'] = $num1 + $num2;
$_SESSION['forgot_captcha_question'] = "$num1 + $num2";

echo json_encode([
    'success' => true,
    'question' => $_SESSION['forgot_captcha_question']
]);
?>
