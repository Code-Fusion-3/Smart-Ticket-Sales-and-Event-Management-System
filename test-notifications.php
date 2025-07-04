<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/notifications.php';

// Set content type for better output
header('Content-Type: text/html; charset=UTF-8');

// Check and create database tables if they don't exist
try {
    // Check if email_logs table exists
    $emailTableExists = $db->fetchOne("SHOW TABLES LIKE 'email_logs'");
    if (!$emailTableExists) {
        $db->query("CREATE TABLE email_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recipient_email VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT,
            status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo '<div class="test-section success">✅ Created email_logs table</div>';
    }

    // Check if sms_logs table exists
    $smsTableExists = $db->fetchOne("SHOW TABLES LIKE 'sms_logs'");
    if (!$smsTableExists) {
        $db->query("CREATE TABLE sms_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            recipient_phone VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo '<div class="test-section success">✅ Created sms_logs table</div>';
    }
} catch (Exception $e) {
    echo '<div class="test-section error">❌ Database setup error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Convert existing tables to UTF-8 if needed
try {
    // Check and convert email_logs table character set
    $emailTableInfo = $db->fetchOne("SHOW TABLE STATUS LIKE 'email_logs'");
    if ($emailTableInfo && $emailTableInfo['Collation'] !== 'utf8mb4_unicode_ci') {
        $db->query("ALTER TABLE email_logs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo '<div class="test-section success">✅ Converted email_logs table to UTF-8</div>';
    }

    // Check and convert sms_logs table character set
    $smsTableInfo = $db->fetchOne("SHOW TABLE STATUS LIKE 'sms_logs'");
    if ($smsTableInfo && $smsTableInfo['Collation'] !== 'utf8mb4_unicode_ci') {
        $db->query("ALTER TABLE sms_logs CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo '<div class="test-section success">✅ Converted sms_logs table to UTF-8</div>';
    }
} catch (Exception $e) {
    echo '<div class="test-section error">❌ Character set conversion error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Notification Test - <?php echo SITE_NAME; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }

        .container {
            max-width: 800px;
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
        }

        .form-group {
            margin: 15px 0;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input[type="email"],
        input[type="tel"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>📧 Notification System Test</h1>
        <p>Test your email and SMS configuration</p>

        <?php
        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Debug: Show what was received
            echo '<div class="test-section info">';
            echo '<h3>🔍 POST Request Debug</h3>';
            echo '<p><strong>POST Data Received:</strong></p>';
            echo '<pre>' . print_r($_POST, true) . '</pre>';
            echo '</div>';

            // Simple test form
            if (isset($_POST['simple_test'])) {
                echo '<div class="test-section success">';
                echo '<h3>✅ Form Submission Test</h3>';
                echo '<p>POST request is working correctly! Form data was received.</p>';
                echo '<p><strong>Timestamp:</strong> ' . date('Y-m-d H:i:s') . '</p>';
                echo '</div>';
            }

            if (isset($_POST['test_email'])) {
                $testEmail = $_POST['email'] ?? SMTP_FROM_EMAIL;
                echo '<div class="test-section">';
                echo '<h3>🧪 Testing Email Configuration</h3>';
                echo '<p><strong>Testing email to:</strong> ' . htmlspecialchars($testEmail) . '</p>';

                // Enable error reporting for this test
                error_reporting(E_ALL);
                ini_set('display_errors', 1);

                try {
                    $emailResult = testEmail($testEmail);

                    if ($emailResult) {
                        echo '<div class="success">✅ Email sent successfully! Check your inbox.</div>';
                    } else {
                        echo '<div class="error">❌ Email failed to send.</div>';
                        echo '<div class="info">';
                        echo '<h4>Debug Information:</h4>';
                        echo '<p><strong>SMTP Host:</strong> ' . SMTP_HOST . '</p>';
                        echo '<p><strong>SMTP Port:</strong> ' . SMTP_PORT . '</p>';
                        echo '<p><strong>SMTP Username:</strong> ' . SMTP_USERNAME . '</p>';
                        echo '<p><strong>From Email:</strong> ' . SMTP_FROM_EMAIL . '</p>';
                        echo '<p><strong>Encryption:</strong> ' . SMTP_ENCRYPTION . '</p>';
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="error">❌ Email Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }

                // Show recent email error logs
                try {
                    $recentErrors = $db->fetchAll("SELECT * FROM email_logs WHERE status = 'failed' ORDER BY created_at DESC LIMIT 3");
                    if (!empty($recentErrors)) {
                        echo '<div class="info">';
                        echo '<h4>Recent Email Error Logs:</h4>';
                        foreach ($recentErrors as $error) {
                            echo '<div style="background: #fff3cd; padding: 10px; margin: 5px 0; border-radius: 5px;">';
                            echo '<strong>Time:</strong> ' . date('M j, H:i:s', strtotime($error['created_at'])) . '<br>';
                            echo '<strong>Recipient:</strong> ' . htmlspecialchars($error['recipient_email']) . '<br>';
                            echo '<strong>Subject:</strong> ' . htmlspecialchars($error['subject']) . '<br>';
                            echo '<strong>Error:</strong> ' . htmlspecialchars($error['error_message'] ?? 'No error message') . '<br>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="error">Could not fetch error logs: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }

                echo '</div>';
            }

            if (isset($_POST['test_sms'])) {
                $testPhone = $_POST['phone'] ?? '+250123456789';
                echo '<div class="test-section">';
                echo '<h3>📱 Testing SMS Configuration</h3>';
                echo '<p><strong>Testing SMS to:</strong> ' . htmlspecialchars($testPhone) . '</p>';

                // Enable error reporting for this test
                error_reporting(E_ALL);
                ini_set('display_errors', 1);

                try {
                    $smsResult = testSMS($testPhone);

                    if ($smsResult) {
                        echo '<div class="success">✅ SMS sent successfully! Check your phone.</div>';
                    } else {
                        echo '<div class="error">❌ SMS failed to send.</div>';
                        echo '<div class="info">';
                        echo '<h4>Debug Information:</h4>';
                        echo '<p><strong>SMS Username:</strong> ' . SMS_USERNAME . '</p>';
                        echo '<p><strong>API Key:</strong> ' . substr(SMS_API_KEY, 0, 10) . '...</p>';
                        echo '<p><strong>Sender ID:</strong> Not Set (Sandbox Mode)</p>';
                        echo '<p><strong>Phone Number:</strong> ' . htmlspecialchars($testPhone) . '</p>';
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="error">❌ SMS Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }

                // Show recent SMS error logs
                try {
                    $recentErrors = $db->fetchAll("SELECT * FROM sms_logs WHERE status = 'failed' ORDER BY created_at DESC LIMIT 3");
                    if (!empty($recentErrors)) {
                        echo '<div class="info">';
                        echo '<h4>Recent SMS Error Logs:</h4>';
                        foreach ($recentErrors as $error) {
                            echo '<div style="background: #fff3cd; padding: 10px; margin: 5px 0; border-radius: 5px;">';
                            echo '<strong>Time:</strong> ' . date('M j, H:i:s', strtotime($error['created_at'])) . '<br>';
                            echo '<strong>Recipient:</strong> ' . htmlspecialchars($error['recipient_phone']) . '<br>';
                            echo '<strong>Error:</strong> ' . htmlspecialchars($error['error_message'] ?? 'No error message') . '<br>';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="error">Could not fetch error logs: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }

                echo '</div>';
            }

            if (isset($_POST['test_sms_formatted'])) {
                $testPhone = $_POST['phone_formatted'] ?? '070 000 0000';
                echo '<div class="test-section">';
                echo '<h3>📱 Testing SMS with Formatted Phone Number</h3>';
                echo '<p><strong>Testing SMS to:</strong> ' . htmlspecialchars($testPhone) . '</p>';
                echo '<p><strong>Note:</strong> This tests phone number cleaning (removing spaces, dashes, etc.)</p>';

                // Enable error reporting for this test
                error_reporting(E_ALL);
                ini_set('display_errors', 1);

                try {
                    $smsResult = testSMS($testPhone);

                    if ($smsResult) {
                        echo '<div class="success">✅ SMS sent successfully! Phone number cleaning worked.</div>';
                    } else {
                        echo '<div class="error">❌ SMS failed to send.</div>';
                        echo '<div class="info">';
                        echo '<h4>Debug Information:</h4>';
                        echo '<p><strong>Original Phone:</strong> ' . htmlspecialchars($testPhone) . '</p>';
                        echo '<p><strong>SMS Username:</strong> ' . SMS_USERNAME . '</p>';
                        echo '<p><strong>API Key:</strong> ' . substr(SMS_API_KEY, 0, 10) . '...</p>';
                        echo '</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="error">❌ SMS Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }

                echo '</div>';
            }

            if (isset($_POST['test_ticket_email'])) {
                $testEmail = $_POST['ticket_email'] ?? SMTP_FROM_EMAIL;
                echo '<div class="test-section">';
                echo '<h3>🎫 Testing Ticket Email Template</h3>';
                echo '<p><strong>Sending ticket email to:</strong> ' . htmlspecialchars($testEmail) . '</p>';

                // Create sample ticket data
                $sampleTicket = [
                    'id' => 12345,
                    'ticket_name' => 'VIP Ticket',
                    'recipient_name' => 'John Doe',
                    'recipient_email' => $testEmail,
                    'purchase_price' => 25000,
                    'qr_code' => 'TICKET-SAMPLE-' . time(),
                    'planner_name' => 'Event Organizer'
                ];

                $sampleEvent = [
                    'title' => 'Sample Music Concert',
                    'venue' => 'Kigali Convention Centre',
                    'city' => 'Kigali',
                    'start_date' => date('Y-m-d', strtotime('+7 days')),
                    'start_time' => '19:00:00'
                ];

                $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($sampleTicket['qr_code']);

                try {
                    $emailBody = getEnhancedTicketEmailTemplate($sampleTicket, $sampleEvent, $qrCodeUrl);
                    $subject = "Sample Ticket - " . $sampleEvent['title'];
                    $plainText = "This is a sample ticket email for testing purposes.";

                    $result = sendEmail($testEmail, $subject, $emailBody, $plainText);

                    if ($result) {
                        echo '<div class="success">✅ Ticket email sent successfully! Check your inbox for the formatted ticket.</div>';
                    } else {
                        echo '<div class="error">❌ Ticket email failed to send. Check error logs for details.</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="error">❌ Error creating ticket email: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                echo '</div>';
            }

            if (isset($_POST['test_checkout_process'])) {
                $testEmail = $_POST['checkout_email'] ?? SMTP_FROM_EMAIL;
                echo '<div class="test-section">';
                echo '<h3>🛒 Testing Checkout Process</h3>';
                echo '<p><strong>Sending checkout notification to:</strong> ' . htmlspecialchars($testEmail) . '</p>';

                // Create sample ticket data
                $sampleTicket = [
                    'id' => 12345,
                    'ticket_name' => 'VIP Ticket',
                    'recipient_name' => 'John Doe',
                    'recipient_email' => $testEmail,
                    'purchase_price' => 25000,
                    'qr_code' => 'TICKET-SAMPLE-' . time(),
                    'planner_name' => 'Event Organizer'
                ];

                $sampleEvent = [
                    'title' => 'Sample Music Concert',
                    'venue' => 'Kigali Convention Centre',
                    'city' => 'Kigali',
                    'start_date' => date('Y-m-d', strtotime('+7 days')),
                    'start_time' => '19:00:00'
                ];

                $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($sampleTicket['qr_code']);

                try {
                    $emailBody = getEnhancedTicketEmailTemplate($sampleTicket, $sampleEvent, $qrCodeUrl);
                    $subject = "Sample Ticket - " . $sampleEvent['title'];
                    $plainText = "This is a sample ticket email for testing purposes.";

                    $result = sendEmail($testEmail, $subject, $emailBody, $plainText);

                    if ($result) {
                        echo '<div class="success">✅ Checkout notification sent successfully! Check your inbox for the formatted ticket.</div>';
                    } else {
                        echo '<div class="error">❌ Checkout notification failed to send. Check error logs for details.</div>';
                    }
                } catch (Exception $e) {
                    echo '<div class="error">❌ Error creating checkout notification: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }
                echo '</div>';
            }
        } else {
            // Debug: Show request method
            echo '<div class="test-section info">';
            echo '<h3>🔍 Request Method Debug</h3>';
            echo '<p><strong>Request Method:</strong> ' . $_SERVER['REQUEST_METHOD'] . '</p>';
            echo '<p><strong>Request URI:</strong> ' . $_SERVER['REQUEST_URI'] . '</p>';
            echo '</div>';
        }
        ?>

        <!-- Configuration Display -->
        <div class="test-section info">
            <h3>⚙️ Current Configuration</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4>📧 Email Settings</h4>
                    <pre>SMTP Host: <?php echo SMTP_HOST; ?>
SMTP Port: <?php echo SMTP_PORT; ?>
SMTP User: <?php echo SMTP_USERNAME; ?>
From Email: <?php echo SMTP_FROM_EMAIL; ?>
From Name: <?php echo SMTP_FROM_NAME ?? 'Not Set'; ?>
Encryption: <?php echo SMTP_ENCRYPTION; ?>
Debug Mode: <?php echo EMAIL_DEBUG ? 'ON' : 'OFF'; ?></pre>
                </div>
                <div>
                    <h4>📱 SMS Settings</h4>
                    <pre>SMS Username: <?php echo SMS_USERNAME; ?>
API Key: <?php echo substr(SMS_API_KEY, 0, 10) . '...'; ?>
Debug Mode: <?php echo SMS_DEBUG ? 'ON' : 'OFF'; ?></pre>
                </div>
            </div>
        </div>

        <!-- Test Forms -->
        <div class="test-section">
            <h3>🧪 Run Tests</h3>

            <!-- Simple Test Form -->
            <form method="POST" style="margin-bottom: 20px; background: #f0f8ff; padding: 15px; border-radius: 5px;">
                <h4>🔍 Simple Form Test</h4>
                <p>This form just tests if POST requests are working:</p>
                <input type="hidden" name="simple_test" value="1">
                <button type="submit" class="btn">✅ Test Form Submission</button>
            </form>

            <form method="POST" style="margin-bottom: 20px;">
                <div class="form-group">
                    <label for="email">Test Email Address:</label>
                    <input type="email" id="email" name="email" value="<?php echo SMTP_FROM_EMAIL; ?>" required>
                </div>
                <button type="submit" name="test_email" class="btn">📧 Test Basic Email</button>
            </form>

            <form method="POST" style="margin-bottom: 20px;">
                <div class="form-group">
                    <label for="phone">Test Phone Number (Rwanda format):</label>
                    <input type="tel" id="phone" name="phone" value="+250723527270" placeholder="+250XXXXXXXXX"
                        required>
                    <small>Format: +250XXXXXXXXX or 0XXXXXXXX</small>
                </div>
                <button type="submit" name="test_sms" class="btn">📱 Test SMS</button>
            </form>

            <form method="POST" style="margin-bottom: 20px;">
                <div class="form-group">
                    <label for="phone_formatted">Test Phone Number (Formatted):</label>
                    <input type="tel" id="phone_formatted" name="phone_formatted" value="070 000 0000"
                        placeholder="07X XXX XXXX" required>
                    <small>Test with formatted phone number (spaces)</small>
                </div>
                <button type="submit" name="test_sms_formatted" class="btn">📱 Test SMS (Formatted)</button>
            </form>

            <form method="POST" style="margin-bottom: 20px;">
                <div class="form-group">
                    <label for="ticket_email">Test Ticket Email Template:</label>
                    <input type="email" id="ticket_email" name="ticket_email" value="<?php echo SMTP_FROM_EMAIL; ?>"
                        required>
                </div>
                <button type="submit" name="test_ticket_email" class="btn">🎫 Test Ticket Email</button>
            </form>

            <form method="POST" style="margin-bottom: 20px;">
                <div class="form-group">
                    <label for="checkout_email">Test Checkout Process (Multiple Tickets):</label>
                    <input type="email" id="checkout_email" name="checkout_email" value="<?php echo SMTP_FROM_EMAIL; ?>"
                        required>
                </div>
                <button type="submit" name="test_checkout_process" class="btn">🛒 Test Checkout Notifications</button>
            </form>
        </div>

        <!-- Statistics -->
        <div class="test-section">
            <h3>📊 Notification Statistics</h3>
            <?php
            try {
                $stats = getNotificationStats();
                ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <h4>📧 Email Statistics</h4>
                        <ul>
                            <li>Total Sent: <?php echo $stats['emails']['total']; ?></li>
                            <li>Successful: <?php echo $stats['emails']['sent']; ?></li>
                            <li>Failed: <?php echo $stats['emails']['failed']; ?></li>
                            <li>Pending: <?php echo $stats['emails']['pending']; ?></li>
                        </ul>
                    </div>
                    <div>
                        <h4>📱 SMS Statistics</h4>
                        <ul>
                            <li>Total Sent: <?php echo $stats['sms']['total']; ?></li>
                            <li>Successful: <?php echo $stats['sms']['sent']; ?></li>
                            <li>Failed: <?php echo $stats['sms']['failed']; ?></li>
                            <li>Pending: <?php echo $stats['sms']['pending']; ?></li>
                        </ul>
                    </div>
                </div>
                <?php
            } catch (Exception $e) {
                echo '<div class="error">Error loading statistics: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>

        <!-- Recent Logs -->
        <div class="test-section">
            <h3>📝 Recent Notification Logs</h3>
            <?php
            try {
                // Get recent email logs
                $recentEmails = $db->fetchAll("SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 5");
                $recentSMS = $db->fetchAll("SELECT * FROM sms_logs ORDER BY created_at DESC LIMIT 5");
                ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <h4>📧 Recent Emails</h4>
                        <?php if (empty($recentEmails)): ?>
                            <p>No email logs found.</p>
                        <?php else: ?>
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr style="background: #f8f9fa;">
                                    <th style="padding: 8px; border: 1px solid #ddd;">Recipient</th>
                                    <th style="padding: 8px; border: 1px solid #ddd;">Status</th>
                                    <th style="padding: 8px; border: 1px solid #ddd;">Date</th>
                                </tr>
                                <?php foreach ($recentEmails as $email): ?>
                                    <tr>
                                        <td style="padding: 8px; border: 1px solid #ddd; font-size: 12px;">
                                            <?php echo htmlspecialchars(substr($email['recipient_email'], 0, 20)) . '...'; ?>
                                        </td>
                                        <td style="padding: 8px; border: 1px solid #ddd;">
                                            <span
                                                style="color: <?php echo $email['status'] === 'sent' ? 'green' : ($email['status'] === 'failed' ? 'red' : 'orange'); ?>">
                                                <?php echo ucfirst($email['status']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 8px; border: 1px solid #ddd; font-size: 12px;">
                                            <?php echo date('M j, H:i', strtotime($email['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div>
                        <h4>📱 Recent SMS</h4>
                        <?php if (empty($recentSMS)): ?>
                            <p>No SMS logs found.</p>
                        <?php else: ?>
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr style="background: #f8f9fa;">
                                    <th style="padding: 8px; border: 1px solid #ddd;">Recipient</th>
                                    <th style="padding: 8px; border: 1px solid #ddd;">Status</th>
                                    <th style="padding: 8px; border: 1px solid #ddd;">Date</th>
                                </tr>
                                <?php foreach ($recentSMS as $sms): ?>
                                    <tr>
                                        <td style="padding: 8px; border: 1px solid #ddd; font-size: 12px;">
                                            <?php echo htmlspecialchars($sms['recipient_phone']); ?>
                                        </td>
                                        <td style="padding: 8px; border: 1px solid #ddd;">
                                            <span
                                                style="color: <?php echo $sms['status'] === 'sent' ? 'green' : ($sms['status'] === 'failed' ? 'red' : 'orange'); ?>">
                                                <?php echo ucfirst($sms['status']); ?>
                                            </span>
                                        </td>
                                        <td style="padding: 8px; border: 1px solid #ddd; font-size: 12px;">
                                            <?php echo date('M j, H:i', strtotime($sms['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            } catch (Exception $e) {
                echo '<div class="error">Error loading logs: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>

        <!-- Troubleshooting -->
        <div class="test-section">
            <h3>🔧 Troubleshooting Guide</h3>

            <div style="margin: 15px 0;">
                <h4>📧 Email Issues:</h4>
                <ul>
                    <li><strong>Authentication Failed:</strong> Check SMTP username and password</li>
                    <li><strong>Connection Timeout:</strong> Verify SMTP host and port settings</li>
                    <li><strong>SSL/TLS Errors:</strong> Try changing SMTP_ENCRYPTION from 'tls' to 'ssl' or vice versa
                    </li>
                    <li><strong>Gmail Issues:</strong> Enable "Less secure app access" or use App Password</li>
                    <li><strong>Firewall:</strong> Ensure port 587 (TLS) or 465 (SSL) is open</li>
                </ul>
            </div>

            <div style="margin: 15px 0;">
                <h4>📱 SMS Issues:</h4>
                <ul>
                    <li><strong>Invalid Phone Format:</strong> Use +250XXXXXXXXX format for Rwanda</li>
                    <li><strong>API Key Invalid:</strong> Check your AfricasTalking API key</li>
                    <li><strong>Insufficient Credits:</strong> Top up your AfricasTalking account</li>
                    <li><strong>Sender ID Issues:</strong> Use approved sender ID or leave blank</li>
                    <li><strong>Network Issues:</strong> Check internet connection</li>
                </ul>
            </div>

            <div style="margin: 15px 0;">
                <h4>🔍 Debug Steps:</h4>
                <ol>
                    <li>Check PHP error logs: <code>tail -f /var/log/apache2/error.log</code></li>
                    <li>Enable debug mode in config.php (EMAIL_DEBUG and SMS_DEBUG)</li>
                    <li>Test with simple email first, then complex templates</li>
                    <li>Verify database tables are created (email_logs, sms_logs)</li>
                    <li>Check network connectivity to SMTP and SMS services</li>
                </ol>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="test-section">
            <h3>⚡ Quick Actions</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button onclick="location.reload()" class="btn">🔄 Refresh Page</button>
                <button onclick="window.open('/phpmyadmin', '_blank')" class="btn">🗄️ Open phpMyAdmin</button>
                <button onclick="showLogs()" class="btn">📋 View Error Logs</button>
                <button onclick="clearLogs()" class="btn">🗑️ Clear Test Logs</button>
            </div>
        </div>

        <!-- System Information -->
        <div class="test-section">
            <h3>ℹ️ System Information</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4>PHP Configuration</h4>
                    <pre>PHP Version: <?php echo phpversion(); ?>
OpenSSL: <?php echo extension_loaded('openssl') ? 'Enabled' : 'Disabled'; ?>
cURL: <?php echo extension_loaded('curl') ? 'Enabled' : 'Disabled'; ?>
MySQLi: <?php echo extension_loaded('mysqli') ? 'Enabled' : 'Disabled'; ?>
JSON: <?php echo extension_loaded('json') ? 'Enabled' : 'Disabled'; ?></pre>
                </div>
                <div>
                    <h4>Server Information</h4>
                    <pre>Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
PHP Memory: <?php echo ini_get('memory_limit'); ?>
Max Execution: <?php echo ini_get('max_execution_time'); ?>s
Upload Max: <?php echo ini_get('upload_max_filesize'); ?>
Post Max: <?php echo ini_get('post_max_size'); ?></pre>
                </div>
            </div>
        </div>

        <!-- Enhanced Diagnostic Section -->
        <div class="test-section">
            <h3>🔍 Detailed Diagnostics</h3>

            <?php
            // Check if database tables exist
            $emailTableExists = $db->fetchOne("SHOW TABLES LIKE 'email_logs'");
            $smsTableExists = $db->fetchOne("SHOW TABLES LIKE 'sms_logs'");
            ?>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                <div>
                    <h4>🗄️ Database Tables</h4>
                    <ul>
                        <li>Email Logs Table: <?php echo $emailTableExists ? '✅ Exists' : '❌ Missing'; ?></li>
                        <li>SMS Logs Table: <?php echo $smsTableExists ? '✅ Exists' : '❌ Missing'; ?></li>
                    </ul>
                </div>
                <div>
                    <h4>🔧 PHP Extensions</h4>
                    <ul>
                        <li>OpenSSL: <?php echo extension_loaded('openssl') ? '✅ Enabled' : '❌ Disabled'; ?></li>
                        <li>cURL: <?php echo extension_loaded('curl') ? '✅ Enabled' : '❌ Disabled'; ?></li>
                        <li>PHPMailer:
                            <?php echo class_exists('PHPMailer\PHPMailer\PHPMailer') ? '✅ Available' : '❌ Missing'; ?>
                        </li>
                        <li>AfricasTalking:
                            <?php echo class_exists('AfricasTalking\SDK\AfricasTalking') ? '✅ Available' : '❌ Missing'; ?>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Test SMTP Connection -->
            <div style="margin: 20px 0;">
                <h4>📧 SMTP Connection Test</h4>
                <?php
                if (isset($_POST['test_smtp_connection'])) {
                    echo '<div class="test-section">';
                    echo '<h5>Testing SMTP Connection...</h5>';

                    try {
                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_CONNECTION;
                        $mail->Debugoutput = function ($str, $level) {
                            echo '<pre style="background: #f8f9fa; padding: 10px; margin: 5px 0; font-size: 12px;">' . htmlspecialchars($str) . '</pre>';
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

                        echo '<p>Attempting to connect to ' . SMTP_HOST . ':' . SMTP_PORT . '...</p>';
                        $mail->smtpConnect();
                        echo '<div class="success">✅ SMTP connection successful!</div>';

                    } catch (Exception $e) {
                        echo '<div class="error">❌ SMTP connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                    echo '</div>';
                }
                ?>
                <form method="POST">
                    <button type="submit" name="test_smtp_connection" class="btn">🔌 Test SMTP Connection</button>
                </form>
            </div>

            <!-- Show Recent Error Logs -->
            <div style="margin: 20px 0;">
                <h4>📋 Recent Error Logs</h4>
                <?php
                // Get recent failed emails
                $failedEmails = $db->fetchAll("SELECT * FROM email_logs WHERE status = 'failed' ORDER BY created_at DESC LIMIT 3");
                $failedSMS = $db->fetchAll("SELECT * FROM sms_logs WHERE status = 'failed' ORDER BY created_at DESC LIMIT 3");
                ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <h5>📧 Failed Emails</h5>
                        <?php if (empty($failedEmails)): ?>
                            <p>No failed emails found.</p>
                        <?php else: ?>
                            <?php foreach ($failedEmails as $email): ?>
                                <div style="background: #f8d7da; padding: 10px; margin: 5px 0; border-radius: 5px;">
                                    <strong>To:</strong> <?php echo htmlspecialchars($email['recipient_email']); ?><br>
                                    <strong>Subject:</strong> <?php echo htmlspecialchars($email['subject']); ?><br>
                                    <strong>Error:</strong>
                                    <?php echo htmlspecialchars($email['error_message'] ?? 'No error message'); ?><br>
                                    <strong>Date:</strong> <?php echo date('M j, H:i', strtotime($email['created_at'])); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div>
                        <h5>📱 Failed SMS</h5>
                        <?php if (empty($failedSMS)): ?>
                            <p>No failed SMS found.</p>
                        <?php else: ?>
                            <?php foreach ($failedSMS as $sms): ?>
                                <div style="background: #f8d7da; padding: 10px; margin: 5px 0; border-radius: 5px;">
                                    <strong>To:</strong> <?php echo htmlspecialchars($sms['recipient_phone']); ?><br>
                                    <strong>Error:</strong>
                                    <?php echo htmlspecialchars($sms['error_message'] ?? 'No error message'); ?><br>
                                    <strong>Date:</strong> <?php echo date('M j, H:i', strtotime($sms['created_at'])); ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Alternative Email Configuration Test -->
            <div style="margin: 20px 0;">
                <h4>🔄 Alternative Email Configuration</h4>
                <form method="POST" style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px;">
                        <div>
                            <label>SMTP Host:</label>
                            <input type="text" name="alt_smtp_host" value="smtp.gmail.com"
                                style="width: 100%; padding: 5px;">
                        </div>
                        <div>
                            <label>SMTP Port:</label>
                            <select name="alt_smtp_port" style="width: 100%; padding: 5px;">
                                <option value="587">587 (TLS)</option>
                                <option value="465">465 (SSL)</option>
                                <option value="25">25 (No encryption)</option>
                            </select>
                        </div>
                        <div>
                            <label>Encryption:</label>
                            <select name="alt_smtp_encryption" style="width: 100%; padding: 5px;">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="">None</option>
                            </select>
                        </div>
                        <div>
                            <label>Test Email:</label>
                            <input type="email" name="alt_test_email" value="<?php echo SMTP_FROM_EMAIL; ?>"
                                style="width: 100%; padding: 5px;">
                        </div>
                    </div>
                    <button type="submit" name="test_alternative_config" class="btn">🧪 Test Alternative Config</button>
                </form>

                <?php
                if (isset($_POST['test_alternative_config'])) {
                    echo '<div class="test-section">';
                    echo '<h5>Testing Alternative Configuration...</h5>';

                    $altHost = $_POST['alt_smtp_host'];
                    $altPort = $_POST['alt_smtp_port'];
                    $altEncryption = $_POST['alt_smtp_encryption'];
                    $altEmail = $_POST['alt_test_email'];

                    try {
                        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;
                        $mail->Debugoutput = function ($str, $level) {
                            echo '<pre style="background: #f8f9fa; padding: 5px; margin: 2px 0; font-size: 11px;">' . htmlspecialchars($str) . '</pre>';
                        };

                        $mail->isSMTP();
                        $mail->Host = $altHost;
                        $mail->SMTPAuth = true;
                        $mail->Username = SMTP_USERNAME;
                        $mail->Password = SMTP_PASSWORD;
                        $mail->Port = $altPort;

                        if ($altEncryption === 'ssl') {
                            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                        } elseif ($altEncryption === 'tls') {
                            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        }

                        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                        $mail->addAddress($altEmail);
                        $mail->Subject = "Alternative Config Test";
                        $mail->Body = "This is a test using alternative SMTP configuration.";

                        $result = $mail->send();

                        if ($result) {
                            echo '<div class="success">✅ Alternative configuration works! Email sent successfully.</div>';
                        } else {
                            echo '<div class="error">❌ Alternative configuration failed.</div>';
                        }

                    } catch (Exception $e) {
                        echo '<div class="error">❌ Alternative configuration error: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <p style="color: #666;">
                <strong><?php echo SITE_NAME; ?></strong> - Notification System Test<br>
                <small>Last updated: <?php echo date('Y-m-d H:i:s'); ?></small>
            </p>
        </div>
    </div>

    <script>
        function showLogs() {
            // Open error logs in new window (if accessible)
            alert('Check your server error logs:\n\n' +
                'Apache: /var/log/apache2/error.log\n' +
                'PHP: /var/log/php_errors.log\n' +
                'Application: Check browser console');
        }

        function clearLogs() {
            if (confirm('Are you sure you want to clear test notification logs?')) {
                fetch('clear-test-logs.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'clear_test_logs'
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Test logs cleared successfully!');
                            location.reload();
                        } else {
                            alert('Error clearing logs: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error clearing logs. Check console for details.');
                    });
            }
        }

        // Debug: Log form submissions
        document.addEventListener('DOMContentLoaded', function () {
            console.log('Page loaded, checking forms...');

            // Add debugging to all forms
            document.querySelectorAll('form').forEach((form, index) => {
                console.log(`Form ${index}:`, form);

                form.addEventListener('submit', function (e) {
                    console.log(`Form ${index} submitting...`);
                    console.log('Form action:', this.action);
                    console.log('Form method:', this.method);
                    console.log('Form data:', new FormData(this));

                    // Don't prevent default - let the form submit normally
                    // e.preventDefault(); // REMOVED THIS LINE

                    // Just add visual feedback without disabling
                    const button = this.querySelector('button[type="submit"]');
                    if (button) {
                        const originalText = button.innerHTML;
                        button.innerHTML = '⏳ Sending...';

                        // Re-enable after 5 seconds as fallback
                        setTimeout(() => {
                            button.innerHTML = originalText;
                        }, 5000);
                    }
                });
            });

            // Debug: Check if any JavaScript errors
            window.addEventListener('error', function (e) {
                console.error('JavaScript error:', e.error);
            });
        });

        // Auto-refresh statistics every 30 seconds (simplified)
        setInterval(function () {
            console.log('Auto-refresh check...');
        }, 30000);
    </script>
</body>

</html>