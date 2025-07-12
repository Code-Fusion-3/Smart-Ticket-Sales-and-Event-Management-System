<?php
/**
 * Simple Email Test
 * This file tests the basic email functionality
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('Access denied. Please log in first.');
}

$pageTitle = "Simple Email Test";
include 'includes/header.php';

$result = '';
$error = '';

// Handle test email submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $testEmail = $_POST['test_email'] ?? '';

    if (empty($testEmail)) {
        $error = "Please enter an email address";
    } elseif (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // Test the email function
        $subject = "Test Email from " . SITE_NAME;
        $body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h1 style='color: #333;'>Test Email</h1>
            <p>This is a test email to verify that the email system is working correctly.</p>
            <p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
            <p><strong>Site:</strong> " . SITE_NAME . "</p>
        </div>";

        error_log("Testing email to: $testEmail");

        if (function_exists('sendEmail')) {
            if (sendEmail($testEmail, $subject, $body)) {
                $result = "✅ Test email sent successfully to $testEmail";
            } else {
                $error = "❌ Failed to send test email to $testEmail";
            }
        } else {
            $error = "❌ sendEmail function not found";
        }
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Simple Email Test</h1>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Test Email Functionality</h2>

            <?php if ($result): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $result; ?>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-4">
                    <label for="test_email" class="block text-gray-700 font-bold mb-2">Test Email Address</label>
                    <input type="email" id="test_email" name="test_email" placeholder="Enter email address to test"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                        required>
                </div>

                <button type="submit" name="test_email"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                    Send Test Email
                </button>
            </form>

            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <h3 class="font-semibold text-blue-800 mb-2">Email Configuration:</h3>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li><strong>SMTP Host:</strong> <?php echo SMTP_HOST; ?></li>
                    <li><strong>SMTP Port:</strong> <?php echo SMTP_PORT; ?></li>
                    <li><strong>From Email:</strong> <?php echo SMTP_FROM_EMAIL; ?></li>
                    <li><strong>Debug Mode:</strong> <?php echo EMAIL_DEBUG ? 'Enabled' : 'Disabled'; ?></li>
                </ul>
            </div>

            <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <h3 class="font-semibold text-yellow-800 mb-2">Troubleshooting:</h3>
                <ul class="text-sm text-yellow-700 space-y-1">
                    <li>• Check error logs for detailed error messages</li>
                    <li>• Verify SMTP credentials are correct</li>
                    <li>• Ensure Gmail app password is used (not regular password)</li>
                    <li>• Check if 2FA is enabled on Gmail account</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>