<?php
require_once __DIR__ . '/db.php';

// First, check what's in the database
echo "=== BEFORE UPDATE ===\n";
$res = $mysqli->query("SELECT * FROM room_types ORDER BY room_type_id");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

// Update the third room type
$update_sql = "UPDATE room_types SET type_name = 'Premium (300)', price_per_hour = 300, price_per_30min = 150 WHERE room_type_id = 3";
$result = $mysqli->query($update_sql);

if (!$result) {
    echo "ERROR: " . $mysqli->error . "\n";
    exit;
}

echo "\n=== AFTER UPDATE ===\n";
$res = $mysqli->query("SELECT * FROM room_types ORDER BY room_type_id");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

$mysqli->close();
?>
