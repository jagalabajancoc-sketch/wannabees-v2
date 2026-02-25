<?php
session_start();
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    http_response_code(405); 
    echo json_encode(['success'=>false,'error'=>'Method not allowed']); 
    exit; 
}

// Allow Owner and Cashier
if (!isset($_SESSION['user_id']) || !in_array(intval($_SESSION['role_id']), [1, 3])) { 
    http_response_code(403); 
    echo json_encode(['success'=>false,'error'=>'Forbidden']); 
    exit; 
}

$rental_id = isset($_POST['rental_id']) ? intval($_POST['rental_id']) : 0;
if ($rental_id <= 0) { 
    echo json_encode(['success'=>false,'error'=>'Invalid rental ID']); 
    exit; 
}

// Get rental access record
$stmt = $mysqli->prepare("SELECT ra.qr_token, ra.otp_code, r.room_id FROM rental_access ra JOIN rentals r ON ra.rental_id = r.rental_id WHERE ra.rental_id = ? LIMIT 1");
$stmt->bind_param('i', $rental_id);
$stmt->execute();
$res = $stmt->get_result();
$rental_access = $res->fetch_assoc();
$stmt->close();

if (!$rental_access) { 
    echo json_encode(['success'=>false,'error'=>'Rental access record not found']); 
    exit; 
}

$qr_token = $rental_access['qr_token'];
$otp_code = $rental_access['otp_code'];

// Generate QR code URL - point to auth/qr_login.php
$base_path = str_replace('/api/rooms', '', dirname($_SERVER['PHP_SELF']));
$qr_url = 'https://' . $_SERVER['HTTP_HOST'] . $base_path . '/auth/qr_login.php?token=' . $qr_token;

echo json_encode([
    'success'=>true,
    'qr_url'=>$qr_url,
    'otp_code'=>$otp_code
]);
exit;
?>
