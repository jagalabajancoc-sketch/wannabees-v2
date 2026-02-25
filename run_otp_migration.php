<?php
// Auto-apply OTP schema migration
require_once __DIR__ . '/db.php';

echo "OTP Schema Migration - Auto Apply\n";
echo "==================================\n\n";

// 1. Check current schema
$result = $mysqli->query("DESCRIBE password_reset_otps");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[$row['Field']] = true;
}

echo "Current columns: " . implode(", ", array_keys($columns)) . "\n\n";

// 2. Add missing columns
$errors = [];
$success = [];

if (!isset($columns['action'])) {
    echo "Adding 'action' column...\n";
    if ($mysqli->query("ALTER TABLE password_reset_otps ADD COLUMN action VARCHAR(50) DEFAULT 'password_reset'")) {
        $success[] = "Added 'action' column";
        echo "  ✓ Success\n";
    } else {
        $errors[] = "Failed to add 'action': " . $mysqli->error;
        echo "  ✗ Error: " . $mysqli->error . "\n";
    }
} else {
    echo "✓ 'action' column already exists\n";
}

if (!isset($columns['metadata'])) {
    echo "Adding 'metadata' column...\n";
    if ($mysqli->query("ALTER TABLE password_reset_otps ADD COLUMN metadata JSON DEFAULT NULL")) {
        $success[] = "Added 'metadata' column";
        echo "  ✓ Success\n";
    } else {
        $errors[] = "Failed to add 'metadata': " . $mysqli->error;
        echo "  ✗ Error: " . $mysqli->error . "\n";
    }
} else {
    echo "✓ 'metadata' column already exists\n";
}

// 3. Update existing records
echo "\nUpdating existing records...\n";
$result = $mysqli->query("UPDATE password_reset_otps SET action = 'password_reset' WHERE action IS NULL OR action = ''");
echo "  ✓ Updated " . $mysqli->affected_rows . " records\n";

// 4. Verify
echo "\nVerifying new schema...\n";
$result = $mysqli->query("DESCRIBE password_reset_otps");
$new_columns = [];
while ($row = $result->fetch_assoc()) {
    $new_columns[$row['Field']] = $row['Type'];
}

if (isset($new_columns['action']) && isset($new_columns['metadata'])) {
    echo "✓ Migration successful! All columns present.\n";
    echo "\nFinal schema:\n";
    foreach ($new_columns as $col => $type) {
        echo "  - $col ($type)\n";
    }
} else {
    echo "✗ Migration incomplete!\n";
}

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  ✗ $error\n";
    }
}

echo "\nDone. Try sending OTP again.\n";
?>
