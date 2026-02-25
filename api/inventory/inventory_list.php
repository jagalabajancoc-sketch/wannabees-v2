<?php
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');
$res = $mysqli->query("SELECT product_id, product_name, price, stock_quantity, is_active FROM products WHERE is_active = 1 ORDER BY product_name ASC");
$products = [];
if ($res) while ($r = $res->fetch_assoc()) $products[] = $r;
echo json_encode(['success'=>true,'products'=>$products]);