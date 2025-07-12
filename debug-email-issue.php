<?php
/**
 * Debug Email Issue
 * This script helps diagnose why emails are not being sent
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';

// Check if user is logged in
// session_start();
if (!isset($_SESSION['user_id'])) {
    die('Access denied. Please log in first.');
}

$pageTitle = "Debug Email Issue";
include 'includes/header.php';

// Diagnostic checks
$checks = [];

// Check 1: Email function exists
$checks['sendEmail_function'] = function_exists('sendEmail') ? '✅ Available' : '❌ Not Found';

// Check 2: Database connection
try {
    $testQuery = $db->query("SELECT 1");
    $checks['database_connection'] = '✅ Connected';
} catch (Exception $e) {
    $checks['database_connection'] = '❌ Failed: ' . $e->getMessage();
}

// Check 3: Email configuration
$checks['smtp_host'] = defined('SMTP_HOST') ? '✅ ' . SMTP_HOST : '❌ Not defined';
$checks['smtp_username'] = defined('SMTP_USERNAME') ? '✅ ' . SMTP_USERNAME : '❌ Not defined';
$checks['smtp_password'] = defined('SMTP_PASSWORD') ? '✅ Set' : '❌ Not set';
$checks['smtp_port'] = defined('SMTP_PORT') ? '✅ ' . SMTP_PORT : '❌ Not defined';

// Check 4: Active customers count
try {
    $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND status = 'active'";
    $result = $db->fetchOne($sql);
    $customerCount = $result['count'] ?? 0;
    $checks['active_customers'] = $customerCount > 0 ? "✅ $customerCount customers" : '❌ No active customers';
} catch (Exception $e) {
    $checks['active_customers'] = '❌ Error: ' . $e->getMessage();
}

// Check 5: Email logs table
try {
    $sql = "SELECT COUNT(*) as count FROM email_logs";
    $result = $db->fetchOne($sql);
    $checks['email_logs_table'] = '✅ Available (' . ($result['count'] ?? 0) . ' records)';
} catch (Exception $e) {
    $checks['email_logs_table'] = '❌ Error: ' . $e->getMessage();
}

// Check 6: Recent email logs
try {
    $sql = "SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 5";
    $recentLogs = $db->fetchAll($sql);
    $checks['recent_email_logs'] = !empty($recentLogs) ? '✅ ' . count($recentLogs) . ' recent logs' : '❌ No recent logs';
} catch (Exception $e) {
    $checks['recent_email_logs'] = '❌ Error: ' . $e->getMessage();
}

// Check 7: Test email function
$testResult = '';
if (function_exists('sendEmail')) {
    $testEmail = 'test@example.com'; // Dummy email for testing
    $testSubject = 'Test Email';
    $testBody = '<p>Test email body</p>';

    try {
        // This will fail but we can see the error
        $result = sendEmail($testEmail, $testSubject, $testBody);
        $testResult = $result ? '✅ Test successful' : '❌ Test failed';
    } catch (Exception $e) {
        $testResult = '❌ Exception: ' . $e->getMessage();
    }
} else {
    $testResult = '❌ Function not available';
}
$checks['test_email_function'] = $testResult;
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Debug Email Issue</h1>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">System Diagnostics</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($checks as $check => $status): ?>
                <div class="p-4 border rounded-lg">
                    <h3 class="font-semibold text-gray-800 mb-2"><?php echo ucwords(str_replace('_', ' ', $check)); ?>
                    </h3>
                    <p class="text-sm"><?php echo $status; ?></p>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($recentLogs)): ?>
            <div class="mt-6">
                <h3 class="font-semibold mb-4">Recent Email Logs</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white border border-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 border-b">Email</th>
                                <th class="px-4 py-2 border-b">Subject</th>
                                <th class="px-4 py-2 border-b">Status</th>
                                <th class="px-4 py-2 border-b">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($log['recipient_email']); ?>
                                </td>
                                <td class="px-4 py-2 border-b">
                                    <?php echo htmlspecialchars(substr($log['subject'], 0, 50)); ?></td>
                                <td class="px-4 py-2 border-b">
                                    <span
                                        class="px-2 py-1 rounded text-xs <?php echo $log['status'] === 'sent' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo ucfirst($log['status']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2 border-b"><?php echo $log['created_at']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <h3 class="font-semibold text-yellow-800 mb-2">Common Issues & Solutions:</h3>
                <ul class="text-sm text-yellow-700 space-y-1">
                    <li>• <strong>No customers:</strong> Create some customer accounts first</li>
                    <li>• <strong>SMTP errors:</strong> Check Gmail app password and 2FA settings</li>
                    <li>• <strong>Function not found:</strong> Ensure notifications.php is properly included</li>
                    <li>• <strong>Database errors:</strong> Check database connection and table structure</li>
                </ul>
            </div>

            <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <h3 class="font-semibold text-blue-800 mb-2">Next Steps:</h3>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li>• Use the <a href="test-email-simple.php" class="text-blue-600 underline">Simple Email Test</a>
                        to test basic email functionality</li>
                    <li>• Check error logs for detailed error messages</li>
                    <li>• Verify Gmail settings and app password</li>
                    <li>• Create test customer accounts if none exist</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>