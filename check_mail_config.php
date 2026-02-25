<?php
// Check PHP mail configuration
echo "PHP Mail Configuration Status\n";
echo "==============================\n\n";

// Check if mail() is disabled
if (!function_exists('mail')) {
    echo "✗ mail() function is DISABLED\n";
} else {
    echo "✓ mail() function is ENABLED\n";
}

// Check configuration
echo "\nCurrent Mail Settings:\n";
echo "  SMTP: " . ini_get('SMTP') . "\n";
echo "  smtp_port: " . ini_get('smtp_port') . "\n";
echo "  sendmail_path: " . ini_get('sendmail_path') . "\n";
echo "  sendmail_from: " . ini_get('sendmail_from') . "\n";

// Check php.ini location
echo "\nPHP Information:\n";
echo "  php.ini location: " . php_ini_loaded_file() . "\n";
echo "  PHP version: " . phpversion() . "\n";

// Test mail sending
echo "\n\nTesting mail() function...\n";
$test_result = @mail('test@example.com', 'Test', 'Test message');
echo "  Result: " . ($test_result ? "Success" : "Failed");
?>
