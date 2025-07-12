<?php
/**
 * Test Action Determination
 * This script tests the exact action determination logic from event creation
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

$pageTitle = "Test Action Determination";
include 'includes/header.php';

$result = '';
$error = '';

// Handle test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_action'])) {
    try {
        // Simulate the exact action determination logic
        $action = $_POST['form_action'] ?? (isset($_GET['id']) ? 'edit' : 'create');
        $eventId = 999; // Dummy event ID for testing

        $result = "✅ Action determination test completed!<br>";
        $result .= "📝 Action: $action<br>";
        $result .= "📝 POST form_action: " . ($_POST['form_action'] ?? 'NOT SET') . "<br>";
        $result .= "📝 GET id: " . ($_GET['id'] ?? 'NOT SET') . "<br>";
        $result .= "📝 Event ID: $eventId<br>";

        // Test the email notification condition
        $condition = $action == 'create' && $eventId;
        $result .= "📝 Email condition (action == 'create' && eventId): " . ($condition ? 'TRUE' : 'FALSE') . "<br>";

        if ($condition) {
            $result .= "🎉 Email notification condition is met!<br>";

            // Test if sendEmail function exists
            if (function_exists('sendEmail')) {
                $result .= "✅ sendEmail function exists<br>";

                // Get customer count
                $sql = "SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND status = 'active'";
                $customers = $db->fetchOne($sql);
                $customerCount = $customers['count'] ?? 0;

                $result .= "📧 Active customers: $customerCount<br>";
            } else {
                $result .= "❌ sendEmail function does not exist<br>";
            }
        } else {
            $result .= "❌ Email notification condition is NOT met<br>";
        }

    } catch (Exception $e) {
        $error = "❌ Error: " . $e->getMessage();
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Test Action Determination</h1>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Test Action Determination Logic</h2>

            <p class="text-gray-600 mb-6">
                This test simulates the exact action determination logic used in event creation
                to verify that the email notification condition works correctly.
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
                <input type="hidden" name="form_action" value="create">
                <button type="submit" name="test_action"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                    🧪 Test Action Determination
                </button>
            </form>

            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <h3 class="font-semibold text-blue-800 mb-2">What this test does:</h3>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li>• Tests the exact action determination logic:
                        <code>$action = $_POST['form_action'] ?? (isset($_GET['id']) ? 'edit' : 'create')</code></li>
                    <li>• Tests the email notification condition: <code>if ($action == 'create' && $eventId)</code></li>
                    <li>• Verifies sendEmail function availability</li>
                    <li>• Shows all variable values for debugging</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>