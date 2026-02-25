<?php
/**
 * Email Templates for Wannabees KTV
 * Provides HTML email designs for OTP and password reset communications
 */

class EmailTemplates {
    
    /**
     * Generate OTP email template
     * @param string $otp The one-time password
     * @param string $userName The user's name (optional)
     * @return string HTML email content
     */
    public static function otpEmailTemplate($otp, $userName = '') {
        $greeting = $userName ? "Hello " . htmlspecialchars($userName) . "," : "Hello,";
        
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset OTP - Wannabees KTV</title>
    <style>
        body {
            font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .email-header p {
            margin: 10px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .email-body {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .otp-section {
            background-color: #f9f9f9;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 30px 0;
            border-radius: 4px;
        }
        .otp-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .otp-code {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            letter-spacing: 6px;
            font-family: \'Courier New\', monospace;
            text-align: center;
            margin: 15px 0;
        }
        .otp-expiry {
            font-size: 13px;
            color: #e74c3c;
            text-align: center;
            margin-top: 15px;
            font-weight: 500;
        }
        .email-content {
            font-size: 14px;
            line-height: 1.8;
            color: #555;
            margin-bottom: 20px;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            font-size: 13px;
        }
        .warning strong {
            display: block;
            margin-bottom: 5px;
        }
        .security-note {
            background-color: #e8f4f8;
            border: 1px solid #b3d9e8;
            color: #0c5568;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            font-size: 12px;
            line-height: 1.6;
        }
        .security-note strong {
            display: block;
            margin-bottom: 8px;
        }
        .email-footer {
            background-color: #f9f9f9;
            border-top: 1px solid #eee;
            padding: 20px 30px;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
        .footer-links {
            margin-bottom: 15px;
        }
        .footer-links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
        }
        .footer-links a:hover {
            text-decoration: underline;
        }
        .company-name {
            font-weight: 600;
            color: #333;
        }
        @media (max-width: 600px) {
            .email-container {
                margin: 0;
                border-radius: 0;
            }
            .email-body {
                padding: 20px 15px;
            }
            .otp-code {
                font-size: 28px;
                letter-spacing: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <h1>🔐 Password Reset Code</h1>
            <p>Wannabees KTV</p>
        </div>
        
        <!-- Body -->
        <div class="email-body">
            <div class="greeting">' . $greeting . '</div>
            
            <div class="email-content">
                <p>You have requested to reset your password for your Wannabees KTV account. Use the code below to proceed with your password reset.</p>
            </div>
            
            <!-- OTP Section -->
            <div class="otp-section">
                <div class="otp-label">Your One-Time Password (OTP):</div>
                <div class="otp-code">' . $otp . '</div>
                <div class="otp-expiry">⏱️ This code expires in <strong>15 minutes</strong></div>
            </div>
            
            <!-- Security Note -->
            <div class="security-note">
                <strong>🛡️ Security Notice</strong>
                <p>Never share this code with anyone, including Wannabees KTV staff. We will never ask you for this code via email or phone.</p>
            </div>
            
            <!-- Warning -->
            <div class="warning">
                <strong>⚠️ Didn\'t request this?</strong>
                If you did not request a password reset, please ignore this email. Your account remains secure.
            </div>
            
            <div class="email-content">
                <p>If you have any questions or need assistance, please contact our support team.</p>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="email-footer">
            <div class="footer-links">
                <a href="https://wannabeesktv.com">Visit Website</a> | 
                <a href="https://wannabeesktv.com/support">Support</a>
            </div>
            <p>© 2024 <span class="company-name">Wannabees KTV</span>. All rights reserved.</p>
            <p style="margin: 10px 0 0 0; font-size: 11px;">This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Get plain text version of OTP email
     * @param string $otp The one-time password
     * @param string $userName The user's name (optional)
     * @return string Plain text email content
     */
    public static function otpEmailPlainText($otp, $userName = '') {
        $greeting = $userName ? "Hello " . $userName . "," : "Hello,";
        
        $text = $greeting . "\n\n" .
                "You have requested to reset your password for your Wannabees KTV account.\n\n" .
                "YOUR ONE-TIME PASSWORD (OTP):\n" .
                $otp . "\n\n" .
                "⏱️  This code expires in 15 minutes.\n\n" .
                "SECURITY NOTICE:\n" .
                "Never share this code with anyone, including Wannabees KTV staff. We will never ask you for this code via email or phone.\n\n" .
                "If you did not request a password reset, please ignore this email. Your account remains secure.\n\n" .
                "If you have any questions or need assistance, please contact our support team.\n\n" .
                "Best regards,\n" .
                "Wannabees KTV Management\n\n" .
                "---\n" .
                "This is an automated message, please do not reply to this email.\n" .
                "© 2024 Wannabees KTV. All rights reserved.";
        
        return $text;
    }
}
?>
