<?php
// Preview the HTML email design
require_once __DIR__ . '/api/auth/email_templates.php';

$sampleOTP = '123456';
$sampleUserName = 'John Doe';

// Generate the HTML
$htmlEmail = EmailTemplates::otpEmailTemplate($sampleOTP, $sampleUserName);

// Replace template variables for preview
$htmlEmail = str_replace('{greeting}', 'Hello ' . htmlspecialchars($sampleUserName) . ',', $htmlEmail);
$htmlEmail = str_replace('{otp}', $sampleOTP, $htmlEmail);

echo $htmlEmail;
?>
