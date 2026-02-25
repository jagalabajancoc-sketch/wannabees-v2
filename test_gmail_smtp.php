<?php
// Test script to verify Gmail SMTP configuration
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    echo "Testing Gmail SMTP Connection...\n\n";
    
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'jazdylabajan@gmail.com';
    $mail->Password   = 'tuxv gcbd hcsu xhzg';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    
    echo "✓ SMTP Configuration set\n";
    
    // Verify SMTP connection
    if ($mail->smtpConnect()) {
        echo "✓ Connected to Gmail SMTP successfully!\n\n";
        $mail->smtpClose();
    } else {
        echo "✗ Failed to connect to Gmail SMTP\n";
        echo "Error: " . $mail->ErrorInfo . "\n";
        exit(1);
    }
    
    // Now try sending a test email
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'jazdylabajan@gmail.com';
    $mail->Password   = 'tuxv gcbd hcsu xhzg';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    
    // Set From address to Gmail account (required by Gmail)
    $mail->setFrom('jazdylabajan@gmail.com', 'Wannabees KTV Test');
    $mail->addAddress('jazdylabajan@gmail.com', 'Test Recipient');
    
    $mail->Subject = 'Test Email from Wannabees KTV';
    $mail->Body = 'This is a test email to verify Gmail SMTP is working correctly.\n\nTest sent: ' . date('Y-m-d H:i:s');
    $mail->isHTML(false);
    
    echo "Attempting to send test email to jazdylabajan@gmail.com...\n";
    
    if ($mail->send()) {
        echo "✓ Test email sent successfully!\n\n";
        echo "Gmail SMTP is working correctly. OTP emails should send without issues.\n";
    } else {
        echo "✗ Failed to send test email\n";
        echo "Error: " . $mail->ErrorInfo . "\n";
    }
    
} catch (Exception $e) {
    echo "Exception occurred:\n";
    echo $e->getMessage() . "\n";
}
?>
