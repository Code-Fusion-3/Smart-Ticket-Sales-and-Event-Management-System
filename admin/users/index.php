<?php
// Add at the top of your file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/debug.log'); // Create logs directory
error_reporting(E_ALL);
$pageTitle = "User Management";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/notifications.php';

// Check if user has admin permission
checkPermission('admin');

// Handle user status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    error_log("POST data: " . print_r($_POST, true));
    error_log("Target User ID: " . $targetUserId);
    error_log("New Status: " . $newStatus);

    $targetUserId = (int) $_POST['user_id'];
    $newStatus = $_POST['update_status']; // This gets the value from the button

    if (in_array($newStatus, ['active', 'suspended', 'inactive']) && $targetUserId > 0) {
        // Get user details before updating
        $userSql = "SELECT * FROM users WHERE id = $targetUserId";
        $user = $db->fetchOne($userSql);

        if (!$user) {
            $_SESSION['error_message'] = "User not found.";
            redirect('admin/users/index.php');
        }

        $escapedStatus = $db->escape($newStatus);
        $sql = "UPDATE users SET status = '$escapedStatus', updated_at = NOW() WHERE id = $targetUserId";

        if ($db->query($sql)) {
            // Send notification email to user about status change
            if (function_exists('sendEmail') && !empty($user['email'])) {
                $statusMessages = [
                    'active' => [
                        'subject' => 'Account Activated - ' . SITE_NAME,
                        'body' => "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                            <h2 style='color: #10B981;'>Account Activated</h2>
                            <p>Hello {$user['username']},</p>
                            <p>Your account has been activated by the administrator. You can now log in and access all features of the platform.</p>
                            <p>If you have any questions, please contact our support team.</p>
                            <p>Thank you for using " . SITE_NAME . "!</p>
                        </div>"
                    ],
                    'suspended' => [
                        'subject' => 'Account Temporarily Suspended - ' . SITE_NAME,
                        'body' => "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                            <h2 style='color: #F59E0B;'>Account Temporarily Suspended</h2>
                            <p>Hello {$user['username']},</p>
                            <p>Your account has been temporarily suspended by the administrator. This is usually a temporary measure and your account can be reactivated.</p>
                            <p><strong>What this means:</strong></p>
                            <ul>
                                <li>You cannot log in to your account</li>
                                <li>Your data and account information are preserved</li>
                                <li>This suspension can be lifted by contacting support</li>
                            </ul>
                            <p>If you believe this is an error or have questions, please contact our support team immediately.</p>
                            <p>Thank you for your understanding.</p>
                        </div>"
                    ],
                    'inactive' => [
                        'subject' => 'Account Permanently Deactivated - ' . SITE_NAME,
                        'body' => "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                            <h2 style='color: #EF4444;'>Account Permanently Deactivated</h2>
                            <p>Hello {$user['username']},</p>
                            <p>Your account has been permanently deactivated by the administrator. This is a serious action that requires special consideration to reverse.</p>
                            <p><strong>What this means:</strong></p>
                            <ul>
                                <li>You cannot log in to your account</li>
                                <li>Your account is considered inactive/deleted</li>
                                <li>Reactivating requires direct admin intervention</li>
                                <li>This action is typically permanent</li>
                            </ul>
                            <p>If you believe this is an error or wish to appeal this decision, please contact our support team immediately with a detailed explanation.</p>
                            <p>Thank you for using " . SITE_NAME . ".</p>
                        </div>"
                    ]
                ];

                if (isset($statusMessages[$newStatus])) {
                    $emailData = $statusMessages[$newStatus];
                    sendEmail($user['email'], $emailData['subject'], $emailData['body']);
                }
            }

            $_SESSION['success_message'] = "User status updated to " . ucfirst($newStatus) . " successfully. Notification sent to user.";
        } else {
            $_SESSION['error_message'] = "Failed to update user status.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid status or user ID.";
    }
    redirect('admin/users/index.php');
}

// Handle user deletion (soft delete - set to inactive)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $targetUserId = (int) $_POST['user_id'];

    // Get user details
    $userSql = "SELECT * FROM users WHERE id = $targetUserId";
    $user = $db->fetchOne($userSql);

    if (!$user) {
        $_SESSION['error_message'] = "User not found.";
        redirect('admin/users/index.php');
    }

    // Check if user is admin
    if ($user['role'] === 'admin') {
        $_SESSION['error_message'] = "Cannot delete admin accounts. Use status management instead.";
        redirect('admin/users/index.php');
    }

    // Check if user has any events or tickets
    $checkSql = "SELECT 
                    (SELECT COUNT(*) FROM events WHERE planner_id = " . $targetUserId . ") as events_count,
                    (SELECT COUNT(*) FROM tickets WHERE user_id = " . $targetUserId . ") as tickets_count";
    $checkResult = $db->fetchOne($checkSql);

    if ($checkResult['events_count'] > 0 || $checkResult['tickets_count'] > 0) {
        $_SESSION['error_message'] = "Cannot delete user with existing events or tickets. Set the user to inactive instead.";
    } else {
        // Soft delete - set status to inactive instead of hard delete
        $sql = "UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = $targetUserId";
        if ($db->query($sql)) {
            // Send notification email to user about account deletion
            if (function_exists('sendEmail') && !empty($user['email'])) {
                $emailSubject = "Account Deleted - " . SITE_NAME;
                $emailBody = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #EF4444;'>Account Deleted</h2>
                    <p>Hello {$user['username']},</p>
                    <p>Your account has been deleted by the administrator. This action is permanent and cannot be undone.</p>
                    <p>If you believe this is an error or have questions, please contact our support team immediately.</p>
                    <p>Thank you for using " . SITE_NAME . ".</p>
                </div>";

                sendEmail($user['email'], $emailSubject, $emailBody);
            }

            $_SESSION['success_message'] = "User account deleted successfully. Notification sent to user.";
        } else {
            $_SESSION['error_message'] = "Failed to delete user account.";
        }
    }
    redirect('admin/users/index.php');
}



// Pagination and filtering
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build WHERE clause
$whereConditions = [];
if (!empty($roleFilter)) {
    $whereConditions[] = "role = '" . $db->escape($roleFilter) . "'";
}
if (!empty($statusFilter)) {
    $whereConditions[] = "status = '" . $db->escape($statusFilter) . "'";
}
if (!empty($searchQuery)) {
    $searchQuery = $db->escape($searchQuery);
    $whereConditions[] = "(username LIKE '%$searchQuery%' OR email LIKE '%$searchQuery%' OR phone_number LIKE '%$searchQuery%')";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM users $whereClause";
$totalResult = $db->fetchOne($countSql);
$totalUsers = $totalResult['total'];
$totalPages = ceil($totalUsers / $perPage);

// Get users with additional statistics
$sql = "SELECT 
            u.*,
            (SELECT COUNT(*) FROM events WHERE planner_id = u.id) as events_count,
            (SELECT COUNT(*) FROM tickets WHERE user_id = u.id AND status = 'sold') as tickets_count,
            (SELECT SUM(purchase_price) FROM tickets WHERE user_id = u.id AND status = 'sold') as total_spent
        FROM users u
        $whereClause
        ORDER BY u.created_at DESC
        LIMIT $offset, $perPage";
$users = $db->fetchAll($sql);

include '../../includes/admin_header.php';
?>

<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">User Management</h1>
            <p class="text-gray-600 mt-2 text-sm sm:text-base">Monitor and manage system users</p>
        </div>
        <div class="flex gap-2">
            <a href="../index.php"
                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm sm:text-base">
                <i class="fas fa-arrow-left mr-2"></i>Dashboard
            </a>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>"
                    placeholder="Username, email, or phone"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm sm:text-base">
            </div>

            <div>
                <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                <select id="role" name="role"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm sm:text-base">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="event_planner" <?php echo $roleFilter === 'event_planner' ? 'selected' : ''; ?>>Event
                        Planner</option>
                    <option value="customer" <?php echo $roleFilter === 'customer' ? 'selected' : ''; ?>>Customer
                    </option>
                    <option value="agent" <?php echo $roleFilter === 'agent' ? 'selected' : ''; ?>>Agent</option>
                </select>
            </div>

            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select id="status" name="status"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm sm:text-base">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="suspended" <?php echo $statusFilter === 'suspended' ? 'selected' : ''; ?>>Suspended
                    </option>
                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive
                    </option>
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button type="submit"
                    class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-md transition duration-200 text-sm sm:text-base">
                    <i class="fas fa-search mr-2"></i>Filter
                </button>
                <a href="index.php"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-700 font-bold py-2 px-3 rounded-md transition duration-200 text-sm sm:text-base">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Users Statistics -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <?php
        $statsSql = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
                        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                        SUM(CASE WHEN role = 'event_planner' THEN 1 ELSE 0 END) as planners
                     FROM users";
        $stats = $db->fetchOne($statsSql);
        ?>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-lg sm:text-2xl font-bold text-blue-600"><?php echo $stats['total']; ?></div>
                <div class="text-xs sm:text-sm text-gray-500">Total Users</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-lg sm:text-2xl font-bold text-green-600"><?php echo $stats['active']; ?></div>
                <div class="text-xs sm:text-sm text-gray-500">Active</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-lg sm:text-2xl font-bold text-yellow-600"><?php echo $stats['suspended']; ?></div>
                <div class="text-xs sm:text-sm text-gray-500">Suspended</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-lg sm:text-2xl font-bold text-red-600"><?php echo $stats['inactive']; ?></div>
                <div class="text-xs sm:text-sm text-gray-500">Inactive</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-lg sm:text-2xl font-bold text-purple-600"><?php echo $stats['planners']; ?></div>
                <div class="text-xs sm:text-sm text-gray-500">Planners</div>
            </div>
        </div>
    </div>

    <!-- Help Section -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fas fa-info-circle text-blue-400 mt-1"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">Account Management Guide</h3>
                <div class="mt-2 text-sm text-blue-700">
                    <ul class="list-disc list-inside space-y-1">
                        <li><strong>Active:</strong> Users can log in and use all features</li>
                        <li><strong>Suspended:</strong> Temporary restriction - user cannot log in but can be easily
                            reactivated (for rule violations, temporary issues)</li>
                        <li><strong>Inactive:</strong> Permanent deactivation - user cannot log in and requires admin
                            intervention to reactivate (soft delete)</li>
                        <li><strong>Delete:</strong> Only available for customers and event planners without
                            events/tickets</li>
                    </ul>
                    <div class="mt-3 p-2 bg-yellow-50 border border-yellow-200 rounded">
                        <p class="text-xs text-yellow-800"><strong>Note:</strong> Suspended accounts are temporary and
                            can be easily restored. Inactive accounts are permanent and require special consideration to
                            reactivate.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            User</th>
                        <th
                            class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">
                            Contact</th>
                        <th
                            class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Role</th>
                        <th
                            class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">
                            Stats</th>
                        <th
                            class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status</th>
                        <th
                            class="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-gray-500">No users found</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($users as $user): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 sm:px-6 py-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-8 w-8 sm:h-10 sm:w-10">
                                    <?php if ($user['profile_image']): ?>
                                    <img class="h-8 w-8 sm:h-10 sm:w-10 rounded-full object-cover"
                                        src="<?php echo SITE_URL; ?>/uploads/profiles/<?php echo $user['profile_image']; ?>"
                                        alt="Profile">
                                    <?php else: ?>
                                    <div
                                        class="h-8 w-8 sm:h-10 sm:w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                        <i class="fas fa-user text-gray-600 text-sm"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="ml-3 sm:ml-4 min-w-0 flex-1">
                                    <div class="text-sm font-medium text-gray-900 truncate">
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </div>
                                    <div class="text-xs sm:text-sm text-gray-500 truncate sm:hidden">
                                        <?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Joined <?php echo formatDate($user['created_at']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <td class="px-3 sm:px-6 py-4 hidden sm:table-cell">
                            <div class="text-sm text-gray-900 truncate max-w-32 lg:max-w-none">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </div>
                            <div class="text-sm text-gray-500">
                                <?php echo htmlspecialchars($user['phone_number']); ?>
                            </div>
                        </td>

                        <td class="px-3 sm:px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php
                                        switch ($user['role']) {
                                            case 'admin':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            case 'event_planner':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'agent':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                            </span>
                        </td>

                        <td class="px-3 sm:px-6 py-4 hidden lg:table-cell">
                            <div class="text-sm text-gray-900">
                                <?php if ($user['role'] === 'event_planner'): ?>
                                <div><?php echo $user['events_count']; ?> events</div>
                                <div class="text-xs text-gray-500">Balance:
                                    <?php echo formatCurrency($user['balance']); ?>
                                </div>
                                <?php elseif ($user['role'] === 'customer'): ?>
                                <div><?php echo $user['tickets_count']; ?> tickets</div>
                                <div class="text-xs text-gray-500">Spent:
                                    <?php echo formatCurrency($user['total_spent'] ?? 0); ?>
                                </div>
                                <?php else: ?>
                                <div class="text-gray-500">-</div>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td class="px-3 sm:px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php
                                        switch ($user['status']) {
                                            case 'active':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'suspended':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'inactive':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>" title="<?php 
                                  switch ($user['status']) {
                                      case 'active':
                                          echo 'User can log in and use all features';
                                          break;
                                      case 'suspended':
                                          echo 'Temporary restriction - can be easily reactivated';
                                          break;
                                      case 'inactive':
                                          echo 'Permanent deactivation - requires admin intervention';
                                          break;
                                      default:
                                          echo 'Unknown status';
                                  }
                                  ?>">
                                <?php echo ucfirst($user['status']); ?>
                                <?php if ($user['status'] === 'suspended'): ?>
                                <i class="fas fa-clock ml-1 text-xs"></i>
                                <?php elseif ($user['status'] === 'inactive'): ?>
                                <i class="fas fa-ban ml-1 text-xs"></i>
                                <?php endif; ?>
                            </span>
                        </td>

                        <td class="px-3 sm:px-6 py-4">
                            <div class="flex items-center space-x-2">
                                <!-- View Button -->
                                <a href="view.php?id=<?php echo $user['id']; ?>"
                                    class="text-indigo-600 hover:text-indigo-900 text-sm" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>

                                <!-- Status Toggle -->
                                <div class="relative">
                                    <button onclick="toggleDropdown('status-<?php echo $user['id']; ?>')"
                                        class="text-yellow-600 hover:text-yellow-900 text-sm dropdown-toggle"
                                        title="Change Status">
                                        <i class="fas fa-toggle-on"></i>
                                    </button>
                                    <div id="status-<?php echo $user['id']; ?>"
                                        class="dropdown-menu hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-10">
                                        <form method="POST" action="">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <div class="hidden">
                                                <?php if ($user['status'] !== 'suspended'): ?>
                                                <button type="submit" name="update_status" value="suspended"
                                                    class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                                    onclick="return confirm('Temporarily suspend this user? They will not be able to log in but can be easily reactivated.')">
                                                    <i class="fas fa-ban text-yellow-500 mr-2"></i>Temporarily Suspend
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($user['status'] !== 'suspended'): ?>
                                            <button type="submit" name="update_status" value="suspended"
                                                class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                                onclick="return confirm('Temporarily suspend this user? They will not be able to log in but can be easily reactivated.')">
                                                <i class="fas fa-ban text-yellow-500 mr-2"></i>Temporarily Suspend
                                            </button>
                                            <?php endif; ?>

                                            <?php if ($user['status'] !== 'inactive'): ?>
                                            <button type="submit" name="update_status" value="inactive"
                                                class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                                onclick="return confirm('Permanently deactivate this user? This is a serious action that requires special consideration to reverse.')">
                                                <i class="fas fa-pause-circle text-red-500 mr-2"></i>Permanently
                                                Deactivate
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($user['status'] !== 'active'): ?>
                                            <button type="submit" name="update_status" value="active"
                                                class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                                onclick="return confirm('Activate this user? They will be able to log in and use all features.')">
                                                <i class="fas fa-check-circle text-green-500 mr-2"></i>Activate Account
                                            </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>

                                </div>

                                <!-- Delete Account Button (for customers and event planners only) -->
                                <?php if (in_array($user['role'], ['customer', 'event_planner']) && $user['role'] !== 'admin'): ?>
                                <form method="POST" action="" class="inline"
                                    onsubmit="return confirmDelete('Are you sure you want to delete this user account? This will set the account to inactive and cannot be undone.')">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="delete_user"
                                        class="text-red-600 hover:text-red-900 text-sm" title="Delete Account"
                                        <?php echo ($user['events_count'] > 0 || $user['tickets_count'] > 0) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="mt-6 flex justify-center">
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                <span class="sr-only">Previous</span>
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);

                        for ($i = $startPage; $i <= $endPage; $i++):
                            $isCurrentPage = $i === $page;
                            $pageClass = $isCurrentPage
                                ? 'relative inline-flex items-center px-4 py-2 border border-indigo-500 bg-indigo-50 text-sm font-medium text-indigo-600'
                                : 'relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50';
                            ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                class="<?php echo $pageClass; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                <span class="sr-only">Next</span>
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
</div>

<script>
// Toggle dropdown menus
function toggleDropdown(id) {
    const dropdown = document.getElementById(id);
    const allDropdowns = document.querySelectorAll('.dropdown-menu');

    // Close all other dropdowns
    allDropdowns.forEach(d => {
        if (d.id !== id) {
            d.classList.add('hidden');
        }
    });

    // Toggle current dropdown
    dropdown.classList.toggle('hidden');
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.dropdown-toggle')) {
        const dropdowns = document.querySelectorAll('.dropdown-menu');
        dropdowns.forEach(dropdown => {
            dropdown.classList.add('hidden');
        });
    }
});

// Confirm delete function
function confirmDelete(message) {
    return confirm(message);
}

// Handle disabled delete buttons
document.addEventListener('DOMContentLoaded', function() {
    const deleteButtons = document.querySelectorAll('button[name="delete_user"]');

    deleteButtons.forEach(button => {
        if (button.disabled) {
            button.style.opacity = '0.5';
            button.style.cursor = 'not-allowed';
            button.title = 'Cannot delete user with existing events or tickets';
        }
    });
});
</script>

<?php include '../../includes/admin_footer.php'; ?>