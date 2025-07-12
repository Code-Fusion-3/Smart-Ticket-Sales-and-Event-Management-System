<?php
/**
 * Direct Email Test
 * This script tests email functionality directly without web interface
 */

// Include required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/notifications.php';

echo "=== Direct Email Test ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Test 1: Check if sendEmail function exists
echo "1. Checking sendEmail function...\n";
if (function_exists('sendEmail')) {
    echo "   ✅ sendEmail function exists\n";
} else {
    echo "   ❌ sendEmail function NOT found\n";
    exit(1);
}

// Test 2: Check email configuration
echo "\n2. Checking email configuration...\n";
echo "   SMTP Host: " . (defined('SMTP_HOST') ? SMTP_HOST : 'NOT DEFINED') . "\n";
echo "   SMTP Port: " . (defined('SMTP_PORT') ? SMTP_PORT : 'NOT DEFINED') . "\n";
echo "   SMTP Username: " . (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'NOT DEFINED') . "\n";
echo "   SMTP Password: " . (defined('SMTP_PASSWORD') ? 'SET' : 'NOT SET') . "\n";
echo "   From Email: " . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'NOT DEFINED') . "\n";

// Test 3: Check database connection
echo "\n3. Checking database connection...\n";
try {
    $testQuery = $db->query("SELECT 1");
    echo "   ✅ Database connected\n";
} catch (Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Check for active customers
echo "\n4. Checking for active customers...\n";
try {
    $sql = "SELECT id, username, email FROM users WHERE role = 'customer' AND status = 'active' LIMIT 3";
    $customers = $db->fetchAll($sql);
    echo "   Found " . count($customers) . " active customers\n";
    foreach ($customers as $customer) {
        echo "   - " . $customer['username'] . " (" . $customer['email'] . ")\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error fetching customers: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 5: Test email sending
echo "\n5. Testing email sending...\n";
if (empty($customers)) {
    echo "   ❌ No customers to test with\n";
    exit(1);
}

$testCustomer = $customers[1];
$testEmail = $testCustomer['email'];
$testSubject = "Test Email from " . SITE_NAME . " - " . date('Y-m-d H:i:s');
$testBody = "
<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
    <h1 style='color: #333;'>Test Email</h1>
    <p>This is a test email to verify that the email system is working correctly.</p>
    <p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
    <p><strong>Site:</strong> " . SITE_NAME . "</p>
    <p><strong>Customer:</strong> " . $testCustomer['username'] . "</p>
</div>";

echo "   Sending test email to: " . $testEmail . "\n";
echo "   Subject: " . $testSubject . "\n";

try {
    $result = sendEmail($testEmail, $testSubject, $testBody);
    if ($result) {
        echo "   ✅ Email sent successfully!\n";
    } else {
        echo "   ❌ Email failed to send\n";
    }
} catch (Exception $e) {
    echo "   ❌ Exception: " . $e->getMessage() . "\n";
}

// Test 6: Check email logs
echo "\n6. Checking email logs...\n";
try {
    $sql = "SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 3";
    $logs = $db->fetchAll($sql);
    echo "   Recent email logs:\n";
    foreach ($logs as $log) {
        $status = $log['status'] === 'sent' ? '✅' : '❌';
        echo "   $status " . $log['recipient_email'] . " - " . $log['status'] . " (" . $log['created_at'] . ")\n";
        if ($log['status'] === 'failed' && !empty($log['error_message'])) {
            echo "      Error: " . substr($log['error_message'], 0, 100) . "...\n";
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error fetching logs: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?>