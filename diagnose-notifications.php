<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Set content type for better output
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>

<head>
    <title>Notification Diagnostics - <?php echo SITE_NAME; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .test-section {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .error {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .warning {
            background: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }

        .info {
            background: #d1ecf1;
            border-color: #bee5eb;
            color: #0c5460;
        }

        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
        }

        .btn:hover {
            background: #0056b3;
        }

        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }

        .check-item {
            margin: 10px 0;
            padding: 10px;
            border-left: 4px solid #ddd;
        }

        .check-item.pass {
            border-left-color: #28a745;
            background: #f8fff9;
        }

        .check-item.fail {
            border-left-color: #dc3545;
            background: #fff8f8;
        }

        .check-item.warning {
            border-left-color: #ffc107;
            background: #fffdf8;
        }

        .solution {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }

        .solution h4 {
            margin-top: 0;
            color: #0056b3;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔧 Notification System Diagnostics</h1>
        <p>Comprehensive check of your email and SMS configuration</p>

        <?php
        $issues = [];
        $warnings = [];
        $solutions = [];

        // 1. Check Database Connection
        echo '<div class="test-section">';
        echo '<h3>🗄️ Database Connection</h3>';

        try {
            $db->query("SELECT 1");
            echo '<div class="check-item pass">✅ Database connection successful</div>';
        } catch (Exception $e) {
            echo '<div class="check-item fail">❌ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
            $issues[] = 'Database connection failed';
        }
        echo '</div>';

        // 2. Check Required Tables
        echo '<div class="test-section">';
        echo '<h3>📋 Required Database Tables</h3>';

        $requiredTables = ['email_logs', 'sms_logs', 'users', 'events', 'tickets'];
        foreach ($requiredTables as $table) {
            $exists = $db->fetchOne("SHOW TABLES LIKE '$table'");
            if ($exists) {
                echo '<div class="check-item pass">✅ Table "$table" exists</div>';
            } else {
                echo '<div class="check-item fail">❌ Table "$table" missing</div>';
                $issues[] = "Missing table: $table";
            }
        }
        echo '</div>';

        // 3. Check PHP Extensions
        echo '<div class="test-section">';
        echo '<h3>🔧 PHP Extensions</h3>';

        $requiredExtensions = [
            'openssl' => 'Required for SMTP encryption',
            'curl' => 'Required for SMS API calls',
            'mysqli' => 'Required for database operations',
            'json' => 'Required for API responses'
        ];

        foreach ($requiredExtensions as $ext => $description) {
            if (extension_loaded($ext)) {
                echo '<div class="check-item pass">✅ Extension "$ext" loaded - ' . $description . '</div>';
            } else {
                echo '<div class="check-item fail">❌ Extension "$ext" missing - ' . $description . '</div>';
                $issues[] = "Missing PHP extension: $ext";
            }
        }
        echo '</div>';

        // 4. Check Vendor Libraries
        echo '<div class="test-section">';
        echo '<h3>📚 Vendor Libraries</h3>';

        $vendorPath = __DIR__ . '/vendor/autoload.php';
        if (file_exists($vendorPath)) {
            echo '<div class="check-item pass">✅ Composer autoloader exists</div>';

            require_once $vendorPath;

            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                echo '<div class="check-item pass">✅ PHPMailer library available</div>';
            } else {
                echo '<div class="check-item fail">❌ PHPMailer library missing</div>';
                $issues[] = 'PHPMailer library missing';
            }

            if (class_exists('AfricasTalking\SDK\AfricasTalking')) {
                echo '<div class="check-item pass">✅ AfricasTalking SDK available</div>';
            } else {
                echo '<div class="check-item fail">❌ AfricasTalking SDK missing</div>';
                $issues[] = 'AfricasTalking SDK missing';
            }
        } else {
            echo '<div class="check-item fail">❌ Composer autoloader missing</div>';
            $issues[] = 'Composer autoloader missing';
        }
        echo '</div>';

        // 5. Check Email Configuration
        echo '<div class="test-section">';
        echo '<h3>📧 Email Configuration</h3>';

        $emailConfig = [
            'SMTP_HOST' => SMTP_HOST,
            'SMTP_PORT' => SMTP_PORT,
            'SMTP_USERNAME' => SMTP_USERNAME,
            'SMTP_PASSWORD' => SMTP_PASSWORD,
            'SMTP_FROM_EMAIL' => SMTP_FROM_EMAIL,
            'SMTP_FROM_NAME' => SMTP_FROM_NAME ?? 'Not Set'
        ];

        foreach ($emailConfig as $key => $value) {
            if (!empty($value)) {
                $displayValue = $key === 'SMTP_PASSWORD' ? str_repeat('*', 8) : $value;
                echo '<div class="check-item pass">✅ $key: ' . htmlspecialchars($displayValue) . '</div>';
            } else {
                echo '<div class="check-item fail">❌ $key: Not set</div>';
                $issues[] = "Email configuration missing: $key";
            }
        }

        // Check Gmail-specific issues
        if (SMTP_HOST === 'smtp.gmail.com') {
            echo '<div class="check-item warning">⚠️ Gmail SMTP detected - may require App Password</div>';
            $warnings[] = 'Gmail SMTP may require App Password instead of regular password';
        }
        echo '</div>';

        // 6. Check SMS Configuration
        echo '<div class="test-section">';
        echo '<h3>📱 SMS Configuration</h3>';

        $smsConfig = [
            'SMS_USERNAME' => SMS_USERNAME,
            'SMS_API_KEY' => SMS_API_KEY,
            'SMS_SENDER_ID' => SMS_SENDER_ID ?? 'Not Set'
        ];

        foreach ($smsConfig as $key => $value) {
            if (!empty($value)) {
                $displayValue = $key === 'SMS_API_KEY' ? substr($value, 0, 10) . '...' : $value;
                echo '<div class="check-item pass">✅ $key: ' . htmlspecialchars($displayValue) . '</div>';
            } else {
                echo '<div class="check-item fail">❌ $key: Not set</div>';
                $issues[] = "SMS configuration missing: $key";
            }
        }
        echo '</div>';

        // 7. Test SMTP Connection
        echo '<div class="test-section">';
        echo '<h3>🔌 SMTP Connection Test</h3>';

        if (isset($_POST['test_smtp'])) {
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_CONNECTION;
                $mail->Debugoutput = function ($str, $level) {
                    echo '<pre>' . htmlspecialchars($str) . '</pre>';
                };

                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $mail->SMTPAuth = true;
                $mail->Username = SMTP_USERNAME;
                $mail->Password = SMTP_PASSWORD;
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = SMTP_PORT;

                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                echo '<p>Testing connection to ' . SMTP_HOST . ':' . SMTP_PORT . '...</p>';
                $mail->smtpConnect();
                echo '<div class="success">✅ SMTP connection successful!</div>';

            } catch (Exception $e) {
                echo '<div class="error">❌ SMTP connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                $issues[] = 'SMTP connection failed: ' . $e->getMessage();
            }
        } else {
            echo '<form method="POST">';
            echo '<button type="submit" name="test_smtp" class="btn">🔌 Test SMTP Connection</button>';
            echo '</form>';
        }
        echo '</div>';

        // 8. Check File Permissions
        echo '<div class="test-section">';
        echo '<h3>📁 File Permissions</h3>';

        $paths = [
            'includes/' => 'Configuration files',
            'vendor/' => 'Vendor libraries',
            'uploads/' => 'Upload directory'
        ];

        foreach ($paths as $path => $description) {
            $fullPath = __DIR__ . '/' . $path;
            if (is_readable($fullPath)) {
                echo '<div class="check-item pass">✅ $path readable - $description</div>';
            } else {
                echo '<div class="check-item fail">❌ $path not readable - $description</div>';
                $issues[] = "Cannot read: $path";
            }
        }
        echo '</div>';

        // 9. Summary and Solutions
        echo '<div class="test-section">';
        echo '<h3>📊 Diagnostic Summary</h3>';

        if (empty($issues) && empty($warnings)) {
            echo '<div class="success">🎉 All checks passed! Your notification system should work correctly.</div>';
        } else {
            if (!empty($issues)) {
                echo '<div class="error">';
                echo '<h4>❌ Critical Issues Found:</h4>';
                echo '<ul>';
                foreach ($issues as $issue) {
                    echo '<li>' . htmlspecialchars($issue) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }

            if (!empty($warnings)) {
                echo '<div class="warning">';
                echo '<h4>⚠️ Warnings:</h4>';
                echo '<ul>';
                foreach ($warnings as $warning) {
                    echo '<li>' . htmlspecialchars($warning) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
        }
        echo '</div>';

        // 10. Common Solutions
        if (!empty($issues)) {
            echo '<div class="test-section">';
            echo '<h3>🔧 Common Solutions</h3>';

            if (in_array('Database connection failed', $issues)) {
                echo '<div class="solution">';
                echo '<h4>Database Connection Issue</h4>';
                echo '<p><strong>Solution:</strong></p>';
                echo '<ol>';
                echo '<li>Check if MySQL/MariaDB is running</li>';
                echo '<li>Verify database credentials in includes/config.php</li>';
                echo '<li>Ensure database "ticket_management_system" exists</li>';
                echo '<li>Check if user has proper permissions</li>';
                echo '</ol>';
                echo '</div>';
            }

            if (in_array('Missing PHP extension: openssl', $issues)) {
                echo '<div class="solution">';
                echo '<h4>OpenSSL Extension Missing</h4>';
                echo '<p><strong>Solution:</strong></p>';
                echo '<ol>';
                echo '<li>Install OpenSSL extension: <code>sudo apt-get install php-openssl</code></li>';
                echo '<li>Restart web server: <code>sudo systemctl restart apache2</code></li>';
                echo '<li>Check if enabled: <code>php -m | grep openssl</code></li>';
                echo '</ol>';
                echo '</div>';
            }

            if (in_array('PHPMailer library missing', $issues)) {
                echo '<div class="solution">';
                echo '<h4>PHPMailer Missing</h4>';
                echo '<p><strong>Solution:</strong></p>';
                echo '<ol>';
                echo '<li>Install Composer: <code>curl -sS https://getcomposer.org/installer | php</code></li>';
                echo '<li>Install PHPMailer: <code>composer require phpmailer/phpmailer</code></li>';
                echo '<li>Ensure vendor/autoload.php is included</li>';
                echo '</ol>';
                echo '</div>';
            }

            if (strpos(implode(' ', $issues), 'SMTP connection failed') !== false) {
                echo '<div class="solution">';
                echo '<h4>SMTP Connection Issues</h4>';
                echo '<p><strong>For Gmail:</strong></p>';
                echo '<ol>';
                echo '<li>Enable 2-factor authentication on your Gmail account</li>';
                echo '<li>Generate an App Password: Google Account → Security → App Passwords</li>';
                echo '<li>Use the App Password instead of your regular password</li>';
                echo '<li>Ensure "Less secure app access" is disabled</li>';
                echo '</ol>';
                echo '<p><strong>Alternative Solutions:</strong></p>';
                echo '<ol>';
                echo '<li>Try port 465 with SSL instead of 587 with TLS</li>';
                echo '<li>Check firewall settings</li>';
                echo '<li>Verify SMTP credentials</li>';
                echo '<li>Use a different email provider (Outlook, Yahoo, etc.)</li>';
                echo '</ol>';
                echo '</div>';
            }

            if (in_array('AfricasTalking SDK missing', $issues)) {
                echo '<div class="solution">';
                echo '<h4>AfricasTalking SDK Missing</h4>';
                echo '<p><strong>Solution:</strong></p>';
                echo '<ol>';
                echo '<li>Install AfricasTalking SDK: <code>composer require africastalking/africastalking</code></li>';
                echo '<li>Verify API credentials in AfricasTalking dashboard</li>';
                echo '<li>Check if you have sufficient credits</li>';
                echo '</ol>';
                echo '</div>';
            }
        }
        echo '</div>';

        // 11. Quick Fixes
        echo '<div class="test-section">';
        echo '<h3>⚡ Quick Fixes</h3>';
        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';

        echo '<div>';
        echo '<h4>📧 Email Quick Fixes</h4>';
        echo '<ul>';
        echo '<li><a href="test-notifications.php" target="_blank">Run Email Test</a></li>';
        echo '<li><a href="https://myaccount.google.com/apppasswords" target="_blank">Generate Gmail App Password</a></li>';
        echo '<li><a href="https://support.google.com/mail/answer/7126229" target="_blank">Gmail SMTP Guide</a></li>';
        echo '</ul>';
        echo '</div>';

        echo '<div>';
        echo '<h4>📱 SMS Quick Fixes</h4>';
        echo '<ul>';
        echo '<li><a href="https://africastalking.com/" target="_blank">AfricasTalking Dashboard</a></li>';
        echo '<li><a href="https://africastalking.com/docs/sms" target="_blank">SMS API Documentation</a></li>';
        echo '<li>Check phone number format: +250XXXXXXXXX</li>';
        echo '</ul>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        ?>

        <!-- Footer -->
        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <p style="color: #666;">
                <strong><?php echo SITE_NAME; ?></strong> - Notification Diagnostics<br>
                <small>Last updated: <?php echo date('Y-m-d H:i:s'); ?></small>
            </p>
        </div>
    </div>
</body>

</html>