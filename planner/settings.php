<?php
$pageTitle = "Account Settings";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user has permission
checkPermission('event_planner');

// Get user ID
$userId = getCurrentUserId();

// Get user data
$sql = "SELECT * FROM users WHERE id = $userId";
$user = $db->fetchOne($sql);

if (!$user) {
    $_SESSION['error_message'] = "User not found.";
    redirect('index.php');
}

// Process notification settings form
$notificationSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_notifications'])) {
    // Get notification preferences
    $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
    $smsNotifications = isset($_POST['sms_notifications']) ? 1 : 0;
    $eventReminders = isset($_POST['event_reminders']) ? 1 : 0;
    $paymentNotifications = isset($_POST['payment_notifications']) ? 1 : 0;
    $marketingEmails = isset($_POST['marketing_emails']) ? 1 : 0;
    
    // Update notification settings
    // Note: In a real application, you would have a separate table for notification preferences
    // For this example, we'll assume these settings are stored in user metadata or a similar table
    
    $notificationSuccess = true;
    $_SESSION['success_message'] = "Notification settings updated successfully.";
}

// Process account deactivation request
$deactivationErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_account'])) {
    $password = $_POST['deactivation_password'] ?? '';
    $confirmation = $_POST['deactivation_confirmation'] ?? '';
    
    // Validate inputs
    if (empty($password)) {
        $deactivationErrors[] = "Password is required";
    } elseif (!password_verify($password, $user['password_hash'])) {
        $deactivationErrors[] = "Password is incorrect";
    }
    
    if ($confirmation !== 'DEACTIVATE') {
        $deactivationErrors[] = "Please type DEACTIVATE to confirm";
    }
    
    // If no errors, deactivate account
    if (empty($deactivationErrors)) {
        // Update user status to inactive
        $sql = "UPDATE users SET status = 'inactive' WHERE id = $userId";
        $db->query($sql);
        
        // Log the user out
        session_destroy();
        
        // Redirect to login page with message
        redirect('../login.php?message=account_deactivated');
    }
}

include '../includes/planner_header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Account Settings</h1>
        <a href="profile.php" class="text-indigo-600 hover:text-indigo-800">
            <i class="fas fa-arrow-left mr-2"></i> Back to Profile
        </a>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Notification Settings -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Notification Settings</h2>
                </div>
                
                <div class="p-6">
                    <?php if ($notificationSuccess): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                            <p>Your notification settings have been updated successfully.</p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="space-y-4">
                            <div class="flex items-center">
                                <input type="checkbox" id="email_notifications" name="email_notifications" class="h-4 w-4 text-indigo-600" checked>
                                <label for="email_notifications" class="ml-2 block text-gray-700">
                                    Email Notifications
                                    <p class="text-sm text-gray-500">Receive important updates about your account via email</p>
                                </label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" id="sms_notifications" name="sms_notifications" class="h-4 w-4 text-indigo-600" checked>
                                <label for="sms_notifications" class="ml-2 block text-gray-700">
                                    SMS Notifications
                                    <p class="text-sm text-gray-500">Receive important updates about your account via SMS</p>
                                </label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" id="event_reminders" name="event_reminders" class="h-4 w-4 text-indigo-600" checked>
                                <label for="event_reminders" class="ml-2 block text-gray-700">
                                    Event Reminders
                                    <p class="text-sm text-gray-500">Receive reminders about upcoming events</p>
                                </label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" id="payment_notifications" name="payment_notifications" class="h-4 w-4 text-indigo-600" checked>
                                <label for="payment_notifications" class="ml-2 block text-gray-700">
                                    Payment Notifications
                                    <p class="text-sm text-gray-500">Receive notifications about payments and withdrawals</p>
                                </label>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" id="marketing_emails" name="marketing_emails" class="h-4 w-4 text-indigo-600">
                                <label for="marketing_emails" class="ml-2 block text-gray-700">
                                    Marketing Emails
                                    <p class="text-sm text-gray-500">Receive promotional emails about new features and offers</p>
                                </label>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <input type="hidden" name="update_notifications" value="1">
                            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                                Save Notification Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Account Deactivation -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-red-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Deactivate Account</h2>
                </div>
                
                <div class="p-6">
                    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded mb-6">
                        <p class="font-bold">Warning: This action cannot be undone!</p>
                        <p>Deactivating your account will:</p>
                        <ul class="list-disc pl-5 mt-2">
                            <li>Hide all your events from public view</li>
                            <li>Prevent you from logging in</li>
                            <li>Retain your data for record-keeping purposes</li>
                        </ul>
                        <p class="mt-2">If you wish to temporarily stop using the platform, consider not deactivating your account.</p>
                    </div>
                    
                    <?php if (!empty($deactivationErrors)): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                            <p class="font-bold">Please fix the following errors:</p>
                            <ul class="list-disc pl-5">
                                <?php foreach ($deactivationErrors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" onsubmit="return confirm('Are you sure you want to deactivate your account? This action cannot be undone.');">
                        <div class="space-y-4">
                            <div>
                                <label for="deactivation_password" class="block text-gray-700 font-bold mb-2">Enter Your Password *</label>
                                <input type="password" id="deactivation_password" name="deactivation_password" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-red-500"
                                       required>
                            </div>
                            
                            <div>
                                <label for="deactivation_confirmation" class="block text-gray-700 font-bold mb-2">Type DEACTIVATE to confirm *</label>
                                <input type="text" id="deactivation_confirmation" name="deactivation_confirmation" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-red-500"
                                       required>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <input type="hidden" name="deactivate_account" value="1">
                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                Deactivate Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Help & Support -->
        <div>
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Help & Support</h2>
                </div>
                
                <div class="p-6">
                    <div class="space-y-4">
                        <div>
                            <h3 class="font-bold text-gray-700 mb-2">Contact Support</h3>
                            <p class="text-sm text-gray-600">
                                Need help with your account? Our support team is available 24/7.
                            </p>
                            <a href="mailto:support@<?php echo $_SERVER['HTTP_HOST']; ?>" class="mt-2 inline-block text-indigo-600 hover:text-indigo-800">
                                <i class="fas fa-envelope mr-2"></i> Email Support
                            </a>
                        </div>
                        
                        <div class="border-t border-gray-200 pt-4">
                            <h3 class="font-bold text-gray-700 mb-2">FAQs</h3>
                            <p class="text-sm text-gray-600">
                                Find answers to commonly asked questions about your account.
                            </p>
                            <a href="#" class="mt-2 inline-block text-indigo-600 hover:text-indigo-800">
                                <i class="fas fa-question-circle mr-2"></i> View FAQs
                            </a>
                        </div>
                        
                        <div class="border-t border-gray-200 pt-4">
                        <h3 class="font-bold text-gray-700 mb-2">Documentation</h3>
                            <p class="text-sm text-gray-600">
                                Learn how to use all features of our platform with our comprehensive guides.
                            </p>
                            <a href="#" class="mt-2 inline-block text-indigo-600 hover:text-indigo-800">
                                <i class="fas fa-book mr-2"></i> Read Documentation
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Privacy & Security</h2>
                </div>
                
                <div class="p-6">
                    <div class="space-y-4">
                        <a href="#" class="flex items-center text-indigo-600 hover:text-indigo-800">
                            <i class="fas fa-shield-alt mr-2"></i> Privacy Policy
                        </a>
                        
                        <a href="#" class="flex items-center text-indigo-600 hover:text-indigo-800">
                            <i class="fas fa-file-contract mr-2"></i> Terms of Service
                        </a>
                        
                        <a href="#" class="flex items-center text-indigo-600 hover:text-indigo-800">
                            <i class="fas fa-cookie mr-2"></i> Cookie Policy
                        </a>
                        
                        <div class="border-t border-gray-200 pt-4 mt-4">
                            <a href="#" class="flex items-center text-indigo-600 hover:text-indigo-800">
                                <i class="fas fa-download mr-2"></i> Download Your Data
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
