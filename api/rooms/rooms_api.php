<?php
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

$sql = "
SELECT r.room_id,
  r.room_number,
  r.status,
  r.is_active,
  rt.type_name,
  rt.price_per_hour,
  rt.price_per_30min,
  rent.rental_id,
  rent.started_at,
  rent.total_minutes
FROM rooms r
JOIN room_types rt ON r.room_type_id = rt.room_type_id
LEFT JOIN rentals rent ON rent.room_id = r.room_id AND rent.ended_at IS NULL
GROUP BY r.room_id
ORDER BY rt.price_per_hour ASC, r.room_number ASC";
$res = $mysqli->query($sql);
$rooms = [];
if ($res) {
    while ($row = $res->fetch_assoc()) $rooms[] = [
        'room_id'        => intval($row['room_id']),
        'room_number'    => intval($row['room_number']),
        'status'         => $row['status'],
        'is_active'      => intval($row['is_active']),
        'type_name'      => $row['type_name'],
        'price_per_hour' => floatval($row['price_per_hour']),
        'price_per_30min'=> floatval($row['price_per_30min']),
        'rental_id'      => $row['rental_id'] !== null ? intval($row['rental_id']) : null,
        'started_at'     => $row['started_at'],
        'total_minutes'  => $row['total_minutes'] !== null ? intval($row['total_minutes']) : null,
    ];
    $res->free();
}
echo json_encode(['success'=>true,'rooms'=>$rooms]);