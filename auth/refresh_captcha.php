<?php
session_start();
header('Content-Type: application/json');

// Generate new math captcha
$num1 = rand(1, 100);
$num2 = rand(1, 100);
$_SESSION['captcha_answer'] = $num1 + $num2;
$_SESSION['captcha_question'] = "$num1 + $num2";

echo json_encode([
    'success' => true,
    'question' => $_SESSION['captcha_question']
]);
?>
