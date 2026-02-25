<?php
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'Invalid id']); exit; }
$stmt = $mysqli->prepare("SELECT product_id, product_name, price, stock_quantity, is_active FROM products WHERE product_id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$product) { echo json_encode(['success'=>false,'error'=>'Not found']); exit; }
echo json_encode(['success'=>true,'product'=>$product]);