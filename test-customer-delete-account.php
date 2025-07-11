<?php
/**
 * Test Customer Delete Account Functionality
 * This file tests the delete account feature for customers
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id'])) {
    die('Access denied. Please log in first.');
}

$pageTitle = "Test Customer Delete Account Functionality";
include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Test Customer Delete Account Functionality</h1>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Delete Account Feature Overview</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-blue-800 mb-2">What it does:</h3>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>• Sets user status to 'inactive' (permanent deactivation)</li>
                        <li>• Sends notification email to user</li>
                        <li>• Only works for customers and event planners</li>
                        <li>• Prevents deletion if user has active tickets</li>
                        <li>• Requires email and password confirmation</li>
                    </ul>
                </div>

                <div class="bg-green-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-green-800 mb-2">Safety Features:</h3>
                    <ul class="text-sm text-green-700 space-y-1">
                        <li>• Soft delete (data preserved)</li>
                        <li>• Validation checks before deletion</li>
                        <li>• Email confirmation required</li>
                        <li>• Password verification required</li>
                        <li>• Reason for deletion recorded</li>
                    </ul>
                </div>
            </div>

            <div class="mt-6">
                <h3 class="font-semibold mb-2">Test Links:</h3>
                <div class="space-y-2">
                    <a href="profile.php" class="block text-blue-600 hover:text-blue-800">
                        → Go to Customer Profile Page (with delete account feature)
                    </a>
                    <a href="planner/profile.php" class="block text-blue-600 hover:text-blue-800">
                        → Go to Planner Profile Page (with delete account feature)
                    </a>
                    <a href="admin/users/index.php" class="block text-blue-600 hover:text-blue-800">
                        → Go to Admin User Management (admin delete feature)
                    </a>
                </div>
            </div>

            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <h3 class="font-semibold text-yellow-800 mb-2">Important Notes:</h3>
                <ul class="text-sm text-yellow-700 space-y-1">
                    <li>• Delete account is a permanent action</li>
                    <li>• Users cannot delete accounts with active tickets/events</li>
                    <li>• Admin intervention required to reactivate</li>
                    <li>• All data is preserved for legal compliance</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>