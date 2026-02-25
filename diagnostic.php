<?php
require_once __DIR__ . '/db.php';

echo "=== ROOM TYPES TABLE ===\n";
$res = $mysqli->query("SELECT * FROM room_types ORDER BY room_type_id");
$typeCount = 0;
while ($row = $res->fetch_assoc()) {
    $typeCount++;
    echo json_encode($row) . "\n";
}
echo "Total types: " . $typeCount . "\n\n";

echo "=== ROOMS TABLE ===\n";
$res2 = $mysqli->query("SELECT room_id, room_number, room_type_id, status FROM rooms ORDER BY room_type_id, room_number");
$roomsByType = [];
while ($row = $res2->fetch_assoc()) {
    $rtId = $row['room_type_id'];
    if (!isset($roomsByType[$rtId])) $roomsByType[$rtId] = [];
    $roomsByType[$rtId][] = $row['room_number'];
}
foreach ($roomsByType as $rtId => $rooms) {
    echo "Type $rtId: " . count($rooms) . " rooms - " . implode(", ", $rooms) . "\n";
}

echo "\n=== QUERY OUTPUT (What pricing page should show) ===\n";
$res3 = $mysqli->query("SELECT room_type_id, type_name, price_per_hour, price_per_30min FROM room_types ORDER BY room_type_id ASC");
$output = [];
if ($res3) while ($r = $res3->fetch_assoc()) {
    $output[] = $r;
}
echo "Array with " . count($output) . " items:\n";
foreach ($output as $i => $item) {
    echo "[$i] => " . json_encode($item) . "\n";
}
?>
