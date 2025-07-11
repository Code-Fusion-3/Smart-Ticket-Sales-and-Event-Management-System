<?php
/**
 * Test Profile Update Functionality
 * This file tests the profile update feature for customers
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

$pageTitle = "Test Profile Update Functionality";
include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Test Profile Update Functionality</h1>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Profile Update Feature Overview</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-blue-800 mb-2">What it does:</h3>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>• Update username, email, and phone number</li>
                        <li>• Upload and change profile image</li>
                        <li>• Change password with validation</li>
                        <li>• Real-time form validation</li>
                        <li>• Image preview functionality</li>
                    </ul>
                </div>

                <div class="bg-green-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-green-800 mb-2">Security Features:</h3>
                    <ul class="text-sm text-green-700 space-y-1">
                        <li>• Email uniqueness validation</li>
                        <li>• Password strength requirements</li>
                        <li>• Current password verification</li>
                        <li>• File upload security checks</li>
                        <li>• SQL injection prevention</li>
                    </ul>
                </div>
            </div>

            <div class="mt-6">
                <h3 class="font-semibold mb-2">Test Links:</h3>
                <div class="space-y-2">
                    <a href="profile.php" class="block text-blue-600 hover:text-blue-800">
                        → Go to Profile Page (with update functionality)
                    </a>
                    <a href="planner/profile.php" class="block text-blue-600 hover:text-blue-800">
                        → Go to Planner Profile Page (with update functionality)
                    </a>
                </div>
            </div>

            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <h3 class="font-semibold text-yellow-800 mb-2">Testing Checklist:</h3>
                <ul class="text-sm text-yellow-700 space-y-1">
                    <li>• Try updating username, email, and phone</li>
                    <li>• Upload a profile image (JPG, PNG, GIF)</li>
                    <li>• Test password change with current password</li>
                    <li>• Verify form validation works</li>
                    <li>• Check image preview functionality</li>
                    <li>• Test error handling for invalid inputs</li>
                </ul>
            </div>

            <div class="mt-6 p-4 bg-purple-50 border border-purple-200 rounded-lg">
                <h3 class="font-semibold text-purple-800 mb-2">Form Validation Rules:</h3>
                <ul class="text-sm text-purple-700 space-y-1">
                    <li>• Username: Minimum 3 characters</li>
                    <li>• Email: Valid email format, unique</li>
                    <li>• Phone: Valid phone number format</li>
                    <li>• Password: Minimum 6 characters</li>
                    <li>• Image: Max 2MB, JPG/PNG/GIF only</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>