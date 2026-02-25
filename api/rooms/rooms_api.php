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
    while ($row = $res->fetch_assoc()) $rooms[] = $row;
    $res->free();
}
echo json_encode(['success'=>true,'rooms'=>$rooms]);