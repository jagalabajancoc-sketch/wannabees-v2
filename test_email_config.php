<?php
/**
 * Email Configuration Tester
 * 
 * Verify your mail_config.php is set up correctly
 */

require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

?>
<!DOCTYPE html>
<html>
<head>
    <title>Email Config Tester - Wannabees KTV</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .container { max-width: 800px; margin: 0 auto; }
        .section { margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 3px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Email Configuration Tester</h1>
        <p>Test if your email settings are working correctly</p>

        <div class="section">
            <h2>Current Configuration</h2>
            <?php
                $config = require __DIR__ . '/mail_config.php';
                echo '<pre>';
                echo "Service: " . $config['service'] . "\n";
                
                if ($config['service'] === 'mailhog') {
                    echo "MailHog Host: " . $config['mailhog']['host'] . "\n";
                    echo "MailHog Port: " . $config['mailhog']['port'] . "\n";
                } elseif ($config['service'] === 'smtp') {
                    echo "SMTP Host: " . $config['smtp']['host'] . "\n";
                    echo "SMTP Port: " . $config['smtp']['port'] . "\n";
                    echo "SMTP Auth: " . ($config['smtp']['username'] ? 'Yes' : 'No') . "\n";
                    echo "Encryption: " . $config['smtp']['encryption'] . "\n";
                } else {
                    echo "Service: Sendmail\n";
                }
                echo "From: " . $config['from_email'] . "\n";
                echo '</pre>';
            ?>
        </div>

        <div class="section">
            <h2>1. Connection Test</h2>
            <?php
                $config = require __DIR__ . '/mail_config.php';
                
                if ($config['service'] === 'mailhog') {
                    $host = $config['mailhog']['host'];
                    $port = $config['mailhog']['port'];
                } elseif ($config['service'] === 'smtp') {
                    $host = $config['smtp']['host'];
                    $port = $config['smtp']['port'];
                } else {
                    echo '<div class="status warning">Sendmail mode - cannot test connection</div>';
                    $host = null;
                }
                
                if ($host && $port) {
                    $connection = @fsockopen($host, $port, $errno, $errstr, 5);
                    if ($connection) {
                        echo '<div class="status success">✓ Connection successful to ' . $host . ':' . $port . '</div>';
                        fclose($connection);
                    } else {
                        echo '<div class="status error">✗ Cannot connect to ' . $host . ':' . $port . '</div>';
                        echo '<p><strong>Error:</strong> ' . $errstr . ' (Code: ' . $errno . ')</p>';
                        echo '<p><strong>Solution:</strong> ';
                        if ($config['service'] === 'mailhog') {
                            echo 'Start MailHog: Run MailHog_windows_amd64.exe';
                        } elseif ($config['service'] === 'smtp') {
                            echo 'Check your SMTP host, port, and firewall settings';
                        }
                        echo '</p>';
                    }
                }
            ?>
        </div>

        <div class="section">
            <h2>2. Send Test Email</h2>
            <?php
                if ($_POST['action'] ?? false === 'send_test') {
                    $test_email = $_POST['test_email'] ?? '';
                    
                    if (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
                        echo '<div class="status error">✗ Invalid email address</div>';
                    } else {
                        $config = require __DIR__ . '/mail_config.php';
                        $mail = new PHPMailer(true);
                        
                        try {
                            // Configure PHPMailer
                            if ($config['service'] === 'mailhog') {
                                $mail->isSMTP();
                                $mail->Host = $config['mailhog']['host'];
                                $mail->Port = $config['mailhog']['port'];
                                $mail->SMTPAuth = false;
                            } elseif ($config['service'] === 'smtp') {
                                $mail->isSMTP();
                                $mail->Host = $config['smtp']['host'];
                                $mail->Port = $config['smtp']['port'];
                                $mail->SMTPAuth = !empty($config['smtp']['username']);
                                if ($mail->SMTPAuth) {
                                    $mail->Username = $config['smtp']['username'];
                                    $mail->Password = $config['smtp']['password'];
                                }
                                $mail->SMTPSecure = $config['smtp']['encryption'];
                            } else {
                                $mail->isSendmail();
                            }
                            
                            $mail->setFrom($config['from_email'], $config['from_name']);
                            $mail->addAddress($test_email);
                            $mail->isHTML(true);
                            $mail->Subject = '✓ Test Email - Wannabees KTV';
                            $mail->Body = '<h2>Email Configuration is Working!</h2><p>If you received this email, your mail service is configured correctly.</p><hr><p>Sent at: ' . date('Y-m-d H:i:s') . '</p>';
                            $mail->AltBody = 'Email Configuration is Working! Sent at: ' . date('Y-m-d H:i:s');
                            
                            if ($mail->send()) {
                                echo '<div class="status success">✓ Test email sent to ' . $test_email . '</div>';
                                if ($config['service'] === 'mailhog') {
                                    echo '<p>Check it at: <a href="http://localhost:8025" target="_blank">http://localhost:8025</a></p>';
                                }
                            }
                        } catch (Exception $e) {
                            echo '<div class="status error">✗ Email send failed</div>';
                            echo '<p><strong>Error:</strong> ' . $e->getMessage() . '</p>';
                        }
                    }
                }
            ?>
            
            <form method="POST">
                <label for="test_email">Test Email Address:</label><br>
                <input type="email" id="test_email" name="test_email" placeholder="your-email@example.com" required>
                <button type="submit" name="action" value="send_test">Send Test Email</button>
            </form>
        </div>

        <div class="section">
            <h2>3. Settings File</h2>
            <p>Edit <code>mail_config.php</code> to change settings</p>
            <button onclick="window.location.href = '/edit.php?file=mail_config.php'">Edit Configuration</button>
        </div>

        <div class="section">
            <h2>Quick Setup</h2>
            <p><strong>To get started with MailHog:</strong></p>
            <ol>
                <li>Download: <a href="https://github.com/mailhog/MailHog/releases" target="_blank">MailHog_windows_amd64.exe</a></li>
                <li>Run the executable</li>
                <li>Visit: <a href="http://localhost:8025" target="_blank">http://localhost:8025</a></li>
                <li>Send test email above</li>
            </ol>
        </div>
    </div>
</body>
</html>
