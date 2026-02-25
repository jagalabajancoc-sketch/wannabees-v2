<?php
require_once __DIR__ . '/db.php';

echo "Checking table structure for password_reset_otps...\n\n";

// Get full table definition
$result = $mysqli->query("SHOW CREATE TABLE password_reset_otps");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Table Definition:\n";
    echo $row['Create Table'];
    echo "\n\n";
}

// Check constraints
$result = $mysqli->query("SELECT CONSTRAINT_NAME, CONSTRAINT_TYPE, TABLE_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_NAME='password_reset_otps'");
echo "Constraints:\n";
while ($row = $result->fetch_assoc()) {
    echo "  - " . $row['CONSTRAINT_NAME'] . " (" . $row['CONSTRAINT_TYPE'] . ")\n";
}
?>
