<?php
session_start();
header('Content-Type: application/json');

$answer = isset($_POST['answer']) ? trim($_POST['answer']) : '';

if (empty($answer)) {
    echo json_encode(['correct' => false]);
    exit;
}

// Check if answer matches (without clearing session)
if (isset($_SESSION['forgot_captcha_answer']) && intval($answer) === intval($_SESSION['forgot_captcha_answer'])) {
    echo json_encode(['correct' => true]);
} else {
    echo json_encode(['correct' => false]);
}
?>
