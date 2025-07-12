<?php
/**
 * Test Event Notification Email Functionality
 * This file tests the email notification feature for new events
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';

// Check if user is logged in as planner
// session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'event_planner') {
    die('Access denied. Event planner privileges required.');
}

$pageTitle = "Test Event Notification Email Functionality";
include 'includes/planner_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Test Event Notification Email Functionality</h1>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Event Notification Email Feature Overview</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-blue-800 mb-2">What it does:</h3>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>• Automatically sends emails when new events are created</li>
                        <li>• Notifies all active customers about new events</li>
                        <li>• Includes event details and direct link to event</li>
                        <li>• Professional HTML email template</li>
                        <li>• Tracks success/failure of email sending</li>
                    </ul>
                </div>

                <div class="bg-green-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-green-800 mb-2">Email Features:</h3>
                    <ul class="text-sm text-green-700 space-y-1">
                        <li>• Beautiful HTML email design</li>
                        <li>• Event details with venue, date, time</li>
                        <li>• Direct link to event page</li>
                        <li>• Planner information included</li>
                        <li>• Mobile-responsive design</li>
                    </ul>
                </div>
            </div>

            <div class="mt-6">
                <h3 class="font-semibold mb-2">Test Links:</h3>
                <div class="space-y-2">
                    <a href="planner/events/events.php?action=create" class="block text-blue-600 hover:text-blue-800">
                        → Create New Event (will trigger email notifications)
                    </a>
                    <a href="planner/events/events.php" class="block text-blue-600 hover:text-blue-800">
                        → View All Events
                    </a>
                </div>
            </div>

            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <h3 class="font-semibold text-yellow-800 mb-2">Testing Instructions:</h3>
                <ul class="text-sm text-yellow-700 space-y-1">
                    <li>• Create a new event using the link above</li>
                    <li>• Fill in all required event details</li>
                    <li>• Submit the form to create the event</li>
                    <li>• Check that success message mentions email notifications</li>
                    <li>• Verify emails are sent to all active customers</li>
                </ul>
            </div>

            <div class="mt-6 p-4 bg-purple-50 border border-purple-200 rounded-lg">
                <h3 class="font-semibold text-purple-800 mb-2">Email Template Features:</h3>
                <ul class="text-sm text-purple-700 space-y-1">
                    <li>• Gradient header with event announcement</li>
                    <li>• Event title, category, and organizer info</li>
                    <li>• Venue, location, date, and time details</li>
                    <li>• Event description (if provided)</li>
                    <li>• Call-to-action button to view event</li>
                    <li>• Professional footer with branding</li>
                </ul>
            </div>

            <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <h3 class="font-semibold text-red-800 mb-2">Important Notes:</h3>
                <ul class="text-sm text-red-700 space-y-1">
                    <li>• Only active customers receive notifications</li>
                    <li>• Emails are sent automatically after event creation</li>
                    <li>• Failed email sends are logged for debugging</li>
                    <li>• Success message shows number of emails sent</li>
                    <li>• No customer preferences needed - all customers notified</li>
                </ul>
            </div>
        </div>

        <!-- Email Preview Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <h2 class="text-xl font-semibold mb-4">Email Template Preview</h2>

            <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                    <div
                        style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px 10px 0 0; text-align: center;">
                        <h1 style="margin: 0; font-size: 24px;">🎉 New Event Available!</h1>
                        <p style="margin: 10px 0 0 0; opacity: 0.9;">Don't miss out on this exciting opportunity</p>
                    </div>

                    <div
                        style="background: white; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <h2 style="color: #333; margin-top: 0;">Sample Event Title</h2>

                        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                <span
                                    style="background: #667eea; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; margin-right: 10px;">Concert</span>
                                <span style="color: #666; font-size: 14px;">Organized by Sample Planner</span>
                            </div>

                            <div style="margin: 15px 0;">
                                <p style="margin: 5px 0; color: #333;"><strong>📍 Venue:</strong> Sample Venue</p>
                                <p style="margin: 5px 0; color: #333;"><strong>🏙️ Location:</strong> Sample City</p>
                                <p style="margin: 5px 0; color: #333;"><strong>📅 Date:</strong> December 25, 2024</p>
                                <p style="margin: 5px 0; color: #333;"><strong>⏰ Time:</strong> 7:00 PM</p>
                            </div>
                        </div>

                        <div style="margin: 30px 0; text-align: center;">
                            <a href="#"
                                style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; display: inline-block;">
                                🎫 View Event & Get Tickets
                            </a>
                        </div>

                        <div style="border-top: 1px solid #eee; padding-top: 20px; margin-top: 30px;">
                            <p style="color: #666; font-size: 14px; margin: 0;">
                                You're receiving this email because you're a registered customer of
                                <?php echo SITE_NAME; ?>.
                                We'll keep you updated about new events and special offers!
                            </p>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 20px; color: #666; font-size: 12px;">
                        <p>© <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/planner_footer.php'; ?>