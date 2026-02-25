<?php
require_once __DIR__ . '/db.php';

// Check current schema
$result = $mysqli->query("DESCRIBE password_reset_otps");

echo "<h2>Current password_reset_otps Schema:</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

$has_action = false;
$has_metadata = false;

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "<td>" . $row['Extra'] . "</td>";
    echo "</tr>";
    
    if ($row['Field'] === 'action') $has_action = true;
    if ($row['Field'] === 'metadata') $has_metadata = true;
}
echo "</table>";

echo "<h2>Migration Status:</h2>";
if (!$has_action || !$has_metadata) {
    echo "<p style='color: red;'><strong>❌ Missing columns!</strong></p>";
    echo "<p>Run this SQL to fix:</p>";
    echo "<pre style='background: #f0f0f0; padding: 10px;'>";
    echo "ALTER TABLE password_reset_otps\n";
    echo "ADD COLUMN IF NOT EXISTS action VARCHAR(50) DEFAULT 'password_reset',\n";
    echo "ADD COLUMN IF NOT EXISTS metadata JSON DEFAULT NULL;";
    echo "</pre>";
    
    // Attempt auto-fix
    echo "<h3>Attempting auto-fix...</h3>";
    if ($mysqli->query("ALTER TABLE password_reset_otps ADD COLUMN IF NOT EXISTS action VARCHAR(50) DEFAULT 'password_reset'")) {
        echo "✓ Added action column<br>";
    } else {
        echo "✗ Failed to add action: " . $mysqli->error . "<br>";
    }
    
    if ($mysqli->query("ALTER TABLE password_reset_otps ADD COLUMN IF NOT EXISTS metadata JSON DEFAULT NULL")) {
        echo "✓ Added metadata column<br>";
    } else {
        echo "✗ Failed to add metadata: " . $mysqli->error . "<br>";
    }
    
    // Update existing records
    $mysqli->query("UPDATE password_reset_otps SET action = 'password_reset' WHERE action IS NULL OR action = ''");
    
    echo "<p>✓ Auto-fix complete! Try sending OTP again.</p>";
} else {
    echo "<p style='color: green;'><strong>✓ All required columns present!</strong></p>";
    echo "<p>If OTP still fails, check server error logs.</p>";
}
?>
