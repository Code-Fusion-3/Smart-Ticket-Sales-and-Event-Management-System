<?php
/**
 * Test Event Notification Debug
 * This script tests the exact email notification logic from event creation
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';

// Check if user is logged in as planner
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'event_planner') {
    die('Access denied. Event planner privileges required.');
}

$pageTitle = "Test Event Notification Debug";
include 'includes/header.php';

$result = '';
$error = '';

// Handle test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_notification'])) {
    try {
        // Simulate the exact conditions from event creation
        $action = 'create';
        $eventId = 999; // Dummy event ID for testing
        $plannerId = $_SESSION['user_id'];

        // Test the exact condition from the event creation code
        echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; font-family: monospace;'>";
        echo "Testing condition: if (\$action == 'create' && \$eventId)<br>";
        echo "Action: $action<br>";
        echo "Event ID: $eventId<br>";
        echo "Condition result: " . ($action == 'create' && $eventId ? 'TRUE' : 'FALSE') . "<br>";
        echo "</div>";

        if ($action == 'create' && $eventId) {
            error_log("Starting email notification process for event ID: $eventId");

            if (function_exists('sendEmail')) {
                error_log("sendEmail function exists, proceeding with notifications");

                // Get all active customers
                $sql = "SELECT id, username, email FROM users WHERE role = 'customer' AND status = 'active'";
                $customers = $db->fetchAll($sql);

                error_log("Found " . count($customers) . " active customers to notify");

                // Get planner information
                $sql = "SELECT username FROM users WHERE id = $plannerId";
                $planner = $db->fetchOne($sql);
                $plannerName = $planner['username'] ?? 'Event Planner';

                // Simulate event details
                $eventDetails = [
                    'title' => 'Test Event',
                    'category' => 'Test',
                    'venue' => 'Test Venue',
                    'city' => 'Test City',
                    'start_date' => date('Y-m-d'),
                    'start_time' => '18:00:00',
                    'description' => 'Test description'
                ];

                // Prepare email content
                $emailSubject = "Test Event Notification - " . SITE_NAME;
                $emailBody = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h1>Test Event Notification</h1>
                    <p>This is a test to verify the email notification logic works.</p>
                    <p><strong>Event:</strong> " . htmlspecialchars($eventDetails['title']) . "</p>
                    <p><strong>Planner:</strong> " . htmlspecialchars($plannerName) . "</p>
                </div>";

                // Send email to each customer
                $sentCount = 0;
                $failedCount = 0;

                foreach ($customers as $customer) {
                    error_log("Attempting to send email to: " . $customer['email']);
                    try {
                        if (sendEmail($customer['email'], $emailSubject, $emailBody)) {
                            $sentCount++;
                            error_log("Email sent successfully to: " . $customer['email']);
                        } else {
                            $failedCount++;
                            error_log("Email failed to send to: " . $customer['email']);
                        }
                    } catch (Exception $e) {
                        $failedCount++;
                        error_log("Exception sending email to {$customer['email']}: " . $e->getMessage());
                    }
                }

                // Log the notification results
                $logMessage = "Event notification sent to $sentCount customers. Failed: $failedCount";
                error_log($logMessage);

                $result = "✅ Test completed!<br>";
                $result .= "📧 Emails sent: $sentCount<br>";
                $result .= "❌ Emails failed: $failedCount<br>";
                $result .= "📝 Check error logs for detailed information.";

            } else {
                $error = "❌ sendEmail function does not exist";
            }
        } else {
            $error = "❌ Condition failed: action=$action, eventId=$eventId";
        }

    } catch (Exception $e) {
        $error = "❌ Error: " . $e->getMessage();
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Test Event Notification Debug</h1>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Debug Email Notification Logic</h2>

            <p class="text-gray-600 mb-6">
                This test simulates the exact email notification logic that runs after event creation
                to help identify why emails might not be sent.
            </p>

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
                <button type="submit" name="test_notification"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                    🧪 Test Notification Logic
                </button>
            </form>

            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <h3 class="font-semibold text-yellow-800 mb-2">Debug Information:</h3>
                <ul class="text-sm text-yellow-700 space-y-1">
                    <li>• Tests the exact condition: <code>if ($action == 'create' && $eventId)</code></li>
                    <li>• Verifies sendEmail function availability</li>
                    <li>• Tests email sending to all active customers</li>
                    <li>• Logs all steps for debugging</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>