<?php
// get_rooms.php - return JSON with rooms, types and counts
require_once __DIR__ . '/../../db.php';
header('Content-Type: application/json');

$data = ['rooms' => [], 'counts' => ['available'=>0,'occupied'=>0,'cleaning'=>0]];

$sql = "SELECT r.room_id, r.room_number, r.status, rt.type_name, rt.price_per_hour, rt.price_per_30min
        FROM rooms r
        JOIN room_types rt ON r.room_type_id = rt.room_type_id
        ORDER BY r.room_number ASC";
if ($res = $mysqli->query($sql)) {
    while ($row = $res->fetch_assoc()) {
        $room = [
            'room_id' => intval($row['room_id']),
            'room_number' => intval($row['room_number']),
            'status' => $row['status'],
            'type_name' => $row['type_name'],
            'price_per_hour' => floatval($row['price_per_hour']),
            'price_per_30min' => floatval($row['price_per_30min']),
            'status_display' => $row['status'] // default
        ];

        // if occupied, attempt to fetch the active rental
        if ($row['status'] === 'OCCUPIED') {
            $rid = intval($row['room_id']);
            $s2 = $mysqli->prepare("SELECT rental_id, started_at, ended_at, total_minutes FROM rentals WHERE room_id = ? AND ended_at IS NULL ORDER BY started_at DESC LIMIT 1");
            if ($s2) {
                $s2->bind_param('i',$rid);
                $s2->execute();
                $s2->bind_result($rental_id, $started_at, $ended_at, $total_minutes);
                if ($s2->fetch()) {
                    // compute elapsed minutes
                    $started = new DateTime($started_at);
                    $now = new DateTime();
                    $interval = $now->getTimestamp() - $started->getTimestamp();
                    $elapsed_hms = gmdate("H:i:s", $interval);
                    $room['rental'] = [
                        'rental_id' => intval($rental_id),
                        'started_at' => $started_at,
                        'elapsed' => $elapsed_hms,
                        'total_minutes' => intval($total_minutes)
                    ];
                }
                $s2->close();
            }
        }

        // set counts
        switch ($row['status']) {
            case 'AVAILABLE':
                $data['counts']['available']++;
                $room['status_display'] = 'Available';
                break;
            case 'OCCUPIED':
                $data['counts']['occupied']++;
                $room['status_display'] = 'Occupied';
                break;
            case 'CLEANING':
                $data['counts']['cleaning']++;
                $room['status_display'] = 'Cleaning';
                break;
        }

        $data['rooms'][] = $room;
    }
    $res->free();
}

echo json_encode($data);