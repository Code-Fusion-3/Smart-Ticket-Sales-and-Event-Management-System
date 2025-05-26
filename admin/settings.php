<?php
$pageTitle = "System Settings";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

// Handle form submissions
$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_general'])) {
        // Update general settings
        $siteName = trim($_POST['site_name']);
        $siteEmail = trim($_POST['site_email']);
        $ticketExpiryHours = (int)$_POST['ticket_expiry_hours'];
        
        if (empty($siteName)) {
            $errors[] = "Site name is required";
        }
        if (empty($siteEmail) || !filter_var($siteEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid site email is required";
        }
        if ($ticketExpiryHours < 1 || $ticketExpiryHours > 72) {
            $errors[] = "Ticket expiry hours must be between 1 and 72";
        }
        
        if (empty($errors)) {
            // Update settings
            $settings = [
                'site_name' => $siteName,
                'site_email' => $siteEmail,
                'ticket_expiry_hours' => $ticketExpiryHours
            ];
            
            foreach ($settings as $key => $value) {
                $sql = "INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                        VALUES ('" . $db->escape($key) . "', '" . $db->escape($value) . "', NOW())
                        ON DUPLICATE KEY UPDATE 
                        setting_value = '" . $db->escape($value) . "', 
                        updated_at = NOW()";
                $db->query($sql);
            }
            
            $success = true;
            $_SESSION['success_message'] = "General settings updated successfully";
        }
    }
    
    if (isset($_POST['update_fees'])) {
        // Update fee settings
        $ticketSaleFee = (float)$_POST['ticket_sale_fee'];
        $withdrawalFee = (float)$_POST['withdrawal_fee'];
        $resaleFee = (float)$_POST['resale_fee'];
        
        if ($ticketSaleFee < 0 || $ticketSaleFee > 50) {
            $errors[] = "Ticket sale fee must be between 0% and 50%";
        }
        if ($withdrawalFee < 0 || $withdrawalFee > 20) {
            $errors[] = "Withdrawal fee must be between 0% and 20%";
        }
        if ($resaleFee < 0 || $resaleFee > 30) {
            $errors[] = "Resale fee must be between 0% and 30%";
        }
        
        if (empty($errors)) {
            // Update fee settings
            $fees = [
                'ticket_sale' => $ticketSaleFee,
                'withdrawal' => $withdrawalFee,
                'resale' => $resaleFee
            ];
            
            foreach ($fees as $type => $percentage) {
                $sql = "INSERT INTO system_fees (fee_type, percentage, updated_at) 
                        VALUES ('" . $db->escape($type) . "', $percentage, NOW())
                        ON DUPLICATE KEY UPDATE 
                        percentage = $percentage, 
                        updated_at = NOW()";
                $db->query($sql);
            }
            
            $success = true;
            $_SESSION['success_message'] = "Fee settings updated successfully";
        }
    }
    
    if (isset($_POST['update_notifications'])) {
        // Update notification settings
        $emailEnabled = isset($_POST['email_enabled']) ? '1' : '0';
        $smsEnabled = isset($_POST['sms_enabled']) ? '1' : '0';
        $smtpHost = trim($_POST['smtp_host']);
        $smtpPort = (int)$_POST['smtp_port'];
        $smtpUsername = trim($_POST['smtp_username']);
        $smtpPassword = trim($_POST['smtp_password']);
        $smsApiKey = trim($_POST['sms_api_key']);
        $smsApiSecret = trim($_POST['sms_api_secret']);
        
        $notificationSettings = [
            'email_enabled' => $emailEnabled,
            'sms_enabled' => $smsEnabled,
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort,
            'smtp_username' => $smtpUsername,
            'smtp_password' => $smtpPassword,
            'sms_api_key' => $smsApiKey,
            'sms_api_secret' => $smsApiSecret
        ];
        
        foreach ($notificationSettings as $key => $value) {
            $sql = "INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                    VALUES ('" . $db->escape($key) . "', '" . $db->escape($value) . "', NOW())
                    ON DUPLICATE KEY UPDATE 
                    setting_value = '" . $db->escape($value) . "', 
                    updated_at = NOW()";
            $db->query($sql);
        }
        
        $success = true;
        $_SESSION['success_message'] = "Notification settings updated successfully";
    }
    
    if (isset($_POST['update_security'])) {
        // Update security settings
        $maxLoginAttempts = (int)$_POST['max_login_attempts'];
        $sessionTimeout = (int)$_POST['session_timeout'];
        $passwordMinLength = (int)$_POST['password_min_length'];
        $requireStrongPassword = isset($_POST['require_strong_password']) ? '1' : '0';
        $enableTwoFactor = isset($_POST['enable_two_factor']) ? '1' : '0';
        
        if ($maxLoginAttempts < 3 || $maxLoginAttempts > 10) {
            $errors[] = "Max login attempts must be between 3 and 10";
        }
        if ($sessionTimeout < 15 || $sessionTimeout > 1440) {
            $errors[] = "Session timeout must be between 15 and 1440 minutes";
        }
        if ($passwordMinLength < 6 || $passwordMinLength > 20) {
            $errors[] = "Password minimum length must be between 6 and 20 characters";
        }
        
        if (empty($errors)) {
            $securitySettings = [
                'max_login_attempts' => $maxLoginAttempts,
                'session_timeout' => $sessionTimeout,
                'password_min_length' => $passwordMinLength,
                'require_strong_password' => $requireStrongPassword,
                'enable_two_factor' => $enableTwoFactor
            ];
            
            foreach ($securitySettings as $key => $value) {
                $sql = "INSERT INTO system_settings (setting_key, setting_value, updated_at) 
                        VALUES ('" . $db->escape($key) . "', '" . $db->escape($value) . "', NOW())
                        ON DUPLICATE KEY UPDATE 
                        setting_value = '" . $db->escape($value) . "', 
                        updated_at = NOW()";
                $db->query($sql);
            }
            
            $success = true;
            $_SESSION['success_message'] = "Security settings updated successfully";
        }
    }
    
    if (isset($_POST['add_setting'])) {
        // Add new custom setting
        $settingKey = trim($_POST['new_setting_key']);
        $settingValue = trim($_POST['new_setting_value']);
        $settingDescription = trim($_POST['new_setting_description']);
        
        if (empty($settingKey)) {
            $errors[] = "Setting key is required";
        }
        if (empty($settingValue)) {
            $errors[] = "Setting value is required";
        }
        
        // Check if key already exists
        if (empty($errors)) {
            $checkSql = "SELECT id FROM system_settings WHERE setting_key = '" . $db->escape($settingKey) . "'";
            $existing = $db->fetchOne($checkSql);
            
            if ($existing) {
                $errors[] = "Setting key already exists";
            }
        }
        
        if (empty($errors)) {
            $sql = "INSERT INTO system_settings (setting_key, setting_value, description, updated_at) 
                    VALUES ('" . $db->escape($settingKey) . "', '" . $db->escape($settingValue) . "', 
                            '" . $db->escape($settingDescription) . "', NOW())";
            
            if ($db->query($sql)) {
                $success = true;
                $_SESSION['success_message'] = "New setting added successfully";
            } else {
                $errors[] = "Failed to add new setting";
            }
        }
    }
    
    if (isset($_POST['delete_setting'])) {
        $settingId = (int)$_POST['setting_id'];
        
        if ($settingId > 0) {
            $sql = "DELETE FROM system_settings WHERE id = $settingId";
            if ($db->query($sql)) {
                $success = true;
                $_SESSION['success_message'] = "Setting deleted successfully";
            } else {
                $errors[] = "Failed to delete setting";
            }
        }
    }
}

// Get current settings
$settingsSql = "SELECT setting_key, setting_value, description, updated_at FROM system_settings ORDER BY setting_key";
$allSettings = $db->fetchAll($settingsSql);

// Convert to associative array for easier access
$settings = [];
foreach ($allSettings as $setting) {
    $settings[$setting['setting_key']] = $setting['setting_value'];
}

// Get current fees
$feesSql = "SELECT fee_type, percentage FROM system_fees";
$allFees = $db->fetchAll($feesSql);

$fees = [];
foreach ($allFees as $fee) {
    $fees[$fee['fee_type']] = $fee['percentage'];
}

include '../includes/admin_header.php';
?>

<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6">
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">System Settings</h1>
        <p class="text-gray-600 mt-2 text-sm sm:text-base">Configure platform-wide settings and preferences</p>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 alert-auto-hide">
        <i class="fas fa-check-circle mr-2"></i>
        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
        <i class="fas fa-exclamation-circle mr-2"></i>
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Settings Tabs -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                <button onclick="showTab('general')" id="general-tab"
                    class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-cog mr-2"></i>General
                </button>
                <button onclick="showTab('fees')" id="fees-tab"
                    class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-percentage mr-2"></i>Fees
                </button>
                <button onclick="showTab('notifications')" id="notifications-tab"
                    class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-bell mr-2"></i>Notifications
                </button>
                <button onclick="showTab('security')" id="security-tab"
                    class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-shield-alt mr-2"></i>Security
                </button>
                <button onclick="showTab('custom')" id="custom-tab"
                    class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    <i class="fas fa-plus mr-2"></i>Custom
                </button>
            </nav>
        </div>

        <!-- General Settings Tab -->
        <div id="general-content" class="tab-content p-6">
            <h3 class="text-lg font-semibold mb-4">General Settings</h3>
            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="site_name" class="block text-sm font-medium text-gray-700 mb-2">Site Name</label>
                        <input type="text" id="site_name" name="site_name"
                            value="<?php echo htmlspecialchars($settings['site_name'] ?? 'Smart Ticket System'); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            required>
                        <p class="text-sm text-gray-500 mt-1">The name of your ticketing platform</p>
                    </div>

                    <div>
                        <label for="site_email" class="block text-sm font-medium text-gray-700 mb-2">Site Email</label>
                        <label for="site_email" class="block text-sm font-medium text-gray-700 mb-2">Site Email</label>
                        <input type="email" id="site_email" name="site_email"
                            value="<?php echo htmlspecialchars($settings['site_email'] ?? 'contact@smartticket.com'); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            required>
                        <p class="text-sm text-gray-500 mt-1">Contact email for the platform</p>
                    </div>

                    <div>
                        <label for="ticket_expiry_hours" class="block text-sm font-medium text-gray-700 mb-2">Ticket
                            Booking Expiry (Hours)</label>
                        <input type="number" id="ticket_expiry_hours" name="ticket_expiry_hours"
                            value="<?php echo htmlspecialchars($settings['ticket_expiry_hours'] ?? '2'); ?>" min="1"
                            max="72"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            required>
                        <p class="text-sm text-gray-500 mt-1">Hours before unpaid bookings expire</p>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" name="update_general"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                        <i class="fas fa-save mr-2"></i>Save General Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Fee Settings Tab -->
        <div id="fees-content" class="tab-content p-6 hidden">
            <h3 class="text-lg font-semibold mb-4">Fee Configuration</h3>
            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="ticket_sale_fee" class="block text-sm font-medium text-gray-700 mb-2">Ticket Sale
                            Fee (%)</label>
                        <input type="number" id="ticket_sale_fee" name="ticket_sale_fee"
                            value="<?php echo htmlspecialchars($fees['ticket_sale'] ?? '5.00'); ?>" min="0" max="50"
                            step="0.01"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            required>
                        <p class="text-sm text-gray-500 mt-1">Platform fee on ticket sales</p>
                    </div>

                    <div>
                        <label for="withdrawal_fee" class="block text-sm font-medium text-gray-700 mb-2">Withdrawal Fee
                            (%)</label>
                        <input type="number" id="withdrawal_fee" name="withdrawal_fee"
                            value="<?php echo htmlspecialchars($fees['withdrawal'] ?? '2.50'); ?>" min="0" max="20"
                            step="0.01"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            required>
                        <p class="text-sm text-gray-500 mt-1">Fee for withdrawal requests</p>
                    </div>

                    <div>
                        <label for="resale_fee" class="block text-sm font-medium text-gray-700 mb-2">Resale Fee
                            (%)</label>
                        <input type="number" id="resale_fee" name="resale_fee"
                            value="<?php echo htmlspecialchars($fees['resale'] ?? '3.00'); ?>" min="0" max="30"
                            step="0.01"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            required>
                        <p class="text-sm text-gray-500 mt-1">Platform fee on ticket resales</p>
                    </div>
                </div>

                <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div class="flex">
                        <i class="fas fa-exclamation-triangle text-yellow-600 mr-2 mt-1"></i>
                        <div>
                            <h4 class="text-sm font-medium text-yellow-800">Important Note</h4>
                            <p class="text-sm text-yellow-700 mt-1">
                                Changing fee percentages will only affect new transactions. Existing transactions will
                                retain their original fee structure.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" name="update_fees"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                        <i class="fas fa-save mr-2"></i>Save Fee Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Notification Settings Tab -->
        <div id="notifications-content" class="tab-content p-6 hidden">
            <h3 class="text-lg font-semibold mb-4">Notification Configuration</h3>
            <form method="POST" action="">
                <!-- Email Settings -->
                <div class="mb-8">
                    <h4 class="text-md font-semibold mb-4 text-gray-800">Email Notifications</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="col-span-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="email_enabled"
                                    <?php echo ($settings['email_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>
                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <span class="ml-2 text-sm text-gray-700">Enable Email Notifications</span>
                            </label>
                        </div>

                        <div>
                            <label for="smtp_host" class="block text-sm font-medium text-gray-700 mb-2">SMTP
                                Host</label>
                            <input type="text" id="smtp_host" name="smtp_host"
                                value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                placeholder="smtp.gmail.com">
                        </div>

                        <div>
                            <label for="smtp_port" class="block text-sm font-medium text-gray-700 mb-2">SMTP
                                Port</label>
                            <input type="number" id="smtp_port" name="smtp_port"
                                value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                placeholder="587">
                        </div>

                        <div>
                            <label for="smtp_username" class="block text-sm font-medium text-gray-700 mb-2">SMTP
                                Username</label>
                            <input type="text" id="smtp_username" name="smtp_username"
                                value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                placeholder="your-email@gmail.com">
                        </div>

                        <div>
                            <label for="smtp_password" class="block text-sm font-medium text-gray-700 mb-2">SMTP
                                Password</label>
                            <input type="password" id="smtp_password" name="smtp_password"
                                value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                placeholder="App password or SMTP password">
                        </div>
                    </div>
                </div>

                <!-- SMS Settings -->
                <div class="mb-8">
                    <h4 class="text-md font-semibold mb-4 text-gray-800">SMS Notifications</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="col-span-2">
                            <label class="flex items-center">
                                <input type="checkbox" name="sms_enabled"
                                    <?php echo ($settings['sms_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>
                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <span class="ml-2 text-sm text-gray-700">Enable SMS Notifications</span>
                            </label>
                        </div>

                        <div>
                            <label for="sms_api_key" class="block text-sm font-medium text-gray-700 mb-2">SMS API
                                Key</label>
                            <input type="text" id="sms_api_key" name="sms_api_key"
                                value="<?php echo htmlspecialchars($settings['sms_api_key'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                placeholder="Your SMS provider API key">
                        </div>

                        <div>
                            <label for="sms_api_secret" class="block text-sm font-medium text-gray-700 mb-2">SMS API
                                Secret</label>
                            <input type="password" id="sms_api_secret" name="sms_api_secret"
                                value="<?php echo htmlspecialchars($settings['sms_api_secret'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                placeholder="Your SMS provider API secret">
                        </div>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" name="update_notifications"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                        <i class="fas fa-save mr-2"></i>Save Notification Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Security Settings Tab -->
        <div id="security-content" class="tab-content p-6 hidden">
            <h3 class="text-lg font-semibold mb-4">Security Configuration</h3>
            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="max_login_attempts" class="block text-sm font-medium text-gray-700 mb-2">Max Login
                            Attempts</label>
                        <input type="number" id="max_login_attempts" name="max_login_attempts"
                            value="<?php echo htmlspecialchars($settings['max_login_attempts'] ?? '5'); ?>" min="3"
                            max="10"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            required>
                        <p class="text-sm text-gray-500 mt-1">Number of failed attempts before account lockout</p>
                    </div>

                    <div>
                        <label for="session_timeout" class="block text-sm font-medium text-gray-700 mb-2">Session
                            Timeout (Minutes)</label>
                        <input type="number" id="session_timeout" name="session_timeout"
                            value="<?php echo htmlspecialchars($settings['session_timeout'] ?? '60'); ?>" min="15"
                            max="1440"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            required>
                        <p class="text-sm text-gray-500 mt-1">Minutes of inactivity before automatic logout</p>
                    </div>

                    <div>
                        <label for="password_min_length" class="block text-sm font-medium text-gray-700 mb-2">Minimum
                            Password Length</label>
                        <input type="number" id="password_min_length" name="password_min_length"
                            value="<?php echo htmlspecialchars($settings['password_min_length'] ?? '8'); ?>" min="6"
                            max="20"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            required>
                        <p class="text-sm text-gray-500 mt-1">Minimum number of characters required for passwords</p>
                    </div>

                    <div class="space-y-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="require_strong_password"
                                <?php echo ($settings['require_strong_password'] ?? '1') == '1' ? 'checked' : ''; ?>
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Require Strong Passwords</span>
                        </label>
                        <p class="text-sm text-gray-500 ml-6">Passwords must contain uppercase, lowercase, numbers, and
                            special characters</p>

                        <label class="flex items-center">
                            <input type="checkbox" name="enable_two_factor"
                                <?php echo ($settings['enable_two_factor'] ?? '0') == '1' ? 'checked' : ''; ?>
                                class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <span class="ml-2 text-sm text-gray-700">Enable Two-Factor Authentication</span>
                        </label>
                        <p class="text-sm text-gray-500 ml-6">Require users to use 2FA for enhanced security</p>
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" name="update_security"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                        <i class="fas fa-save mr-2"></i>Save Security Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Custom Settings Tab -->
        <div id="custom-content" class="tab-content p-6 hidden">
            <h3 class="text-lg font-semibold mb-4">Custom Settings</h3>

            <!-- Add New Setting Form -->
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <h4 class="text-md font-semibold mb-4">Add New Setting</h4>
                <form method="POST" action="">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="new_setting_key" class="block text-sm font-medium text-gray-700 mb-2">Setting
                                Key</label>
                            <input type="text" id="new_setting_key" name="new_setting_key"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                placeholder="setting_key" required>
                        </div>

                        <div>
                            <label for="new_setting_value" class="block text-sm font-medium text-gray-700 mb-2">Setting
                                Value</label>
                            <input type="text" id="new_setting_value" name="new_setting_value"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                placeholder="setting_value" required>
                        </div>

                        <div>
                            <label for="new_setting_description"
                                class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                            <input type="text" id="new_setting_description" name="new_setting_description"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                placeholder="Setting description">
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" name="add_setting"
                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                            <i class="fas fa-plus mr-2"></i>Add Setting
                        </button>
                    </div>
                </form>
            </div>

            <!-- Existing Settings Table -->
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                    <h4 class="text-md font-semibold">All System Settings</h4>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Setting Key</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Value</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Description</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Last Updated</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($allSettings as $setting): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    <?php echo htmlspecialchars($setting['setting_key']); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <div class="max-w-xs truncate"
                                        title="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                        <?php echo htmlspecialchars($setting['setting_value']); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">
                                    <div class="max-w-xs truncate"
                                        title="<?php echo htmlspecialchars($setting['description']); ?>">
                                        <?php echo htmlspecialchars($setting['description'] ?? 'No description'); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">
                                    <?php echo formatDateTime($setting['updated_at']); ?>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <button
                                        onclick="editSetting('<?php echo htmlspecialchars($setting['setting_key']); ?>', '<?php echo htmlspecialchars($setting['setting_value']); ?>', '<?php echo htmlspecialchars($setting['description']); ?>')"
                                        class="text-indigo-600 hover:text-indigo-900 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <?php if (!in_array($setting['setting_key'], ['site_name', 'site_email', 'ticket_expiry_hours'])): ?>
                                    <form method="POST" class="inline"
                                        onsubmit="return confirm('Are you sure you want to delete this setting?')">
                                        <input type="hidden" name="setting_id"
                                            value="<?php echo $setting['id'] ?? 0; ?>">
                                        <button type="submit" name="delete_setting"
                                            class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Setting Modal -->
<div id="editSettingModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Setting</h3>
            <form id="editSettingForm" method="POST" action="">
                <input type="hidden" id="edit_setting_key" name="edit_setting_key">

                <div class="mb-4">
                    <label for="edit_setting_value" class="block text-sm font-medium text-gray-700 mb-2">Setting
                        Value</label>
                    <input type="text" id="edit_setting_value" name="edit_setting_value"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                        required>
                </div>

                <div class="mb-4">
                    <label for="edit_setting_description"
                        class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <input type="text" id="edit_setting_description" name="edit_setting_description"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()"
                        class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        Cancel
                    </button>
                    <button type="submit" name="update_custom_setting"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                        Update Setting
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Tab functionality
function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => content.classList.add('hidden'));

    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('border-indigo-500', 'text-indigo-600');
        button.classList.add('border-transparent', 'text-gray-500');
    });

    // Show selected tab content
    document.getElementById(tabName + '-content').classList.remove('hidden');

    // Add active class to selected tab button
    const activeButton = document.getElementById(tabName + '-tab');
    activeButton.classList.remove('border-transparent', 'text-gray-500');
    activeButton.classList.add('border-indigo-500', 'text-indigo-600');

    // Store active tab in localStorage
    localStorage.setItem('activeSettingsTab', tabName);
}

// Edit setting functionality
function editSetting(key, value, description) {
    document.getElementById('edit_setting_key').value = key;
    document.getElementById('edit_setting_value').value = value;
    document.getElementById('edit_setting_description').value = description || '';
    document.getElementById('editSettingModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editSettingModal').classList.add('hidden');
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Load saved tab or default to general
    const activeTab = localStorage.getItem('activeSettingsTab') || 'general';
    showTab(activeTab);

    // Close modal when clicking outside
    document.getElementById('editSettingModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditModal();
        }
    });
});
</script>

<?php include '../includes/admin_footer.php'; ?>