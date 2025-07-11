<?php
/**
 * Test Delete Account Functionality
 * This file tests the delete account feature for customers and event planners
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';

// Check if user is logged in as admin
session_start();
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die('Access denied. Admin privileges required.');
}

$pageTitle = "Test Delete Account Functionality";
include 'includes/admin_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Test Delete Account Functionality</h1>

        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Test Overview</h2>
            <p class="text-gray-600 mb-4">
                This page tests the delete account functionality for customers and event planners.
                The delete feature sets user accounts to 'inactive' status and sends notification emails.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-blue-800 mb-2">What it does:</h3>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>• Sets user status to 'inactive' (permanent deactivation)</li>
                        <li>• Sends notification email to user</li>
                        <li>• Only works for customers and event planners</li>
                        <li>• Prevents deletion if user has events/tickets</li>
                    </ul>
                </div>

                <div class="bg-green-50 p-4 rounded-lg">
                    <h3 class="font-semibold text-green-800 mb-2">Status Differences:</h3>
                    <ul class="text-sm text-green-700 space-y-1">
                        <li>• <strong>Suspended:</strong> Temporary restriction (easy to reactivate)</li>
                        <li>• <strong>Inactive:</strong> Permanent deactivation (requires admin intervention)</li>
                        <li>• Cannot delete admin accounts</li>
                        <li>• Cannot delete users with events/tickets</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Test Users Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Test Users</h2>

            <?php
            // Get test users (customers and event planners)
            $sql = "SELECT 
                        u.*,
                        (SELECT COUNT(*) FROM events WHERE planner_id = u.id) as events_count,
                        (SELECT COUNT(*) FROM tickets WHERE user_id = u.id AND status = 'sold') as tickets_count
                    FROM users u 
                    WHERE u.role IN ('customer', 'event_planner') 
                    AND u.status != 'inactive'
                    ORDER BY u.created_at DESC 
                    LIMIT 10";
            $testUsers = $db->fetchAll($sql);
            ?>

            <?php if (empty($testUsers)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">No test users found. Create some customers or event planners first.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Activity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($testUsers as $user): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($user['username']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($user['email']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php echo $user['role'] === 'event_planner' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            switch ($user['status']) {
                                                case 'active':
                                                    echo 'bg-green-100 text-green-800';
                                                    break;
                                                case 'suspended':
                                                    echo 'bg-red-100 text-red-800';
                                                    break;
                                                default:
                                                    echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        <?php if ($user['role'] === 'event_planner'): ?>
                                            <div><?php echo $user['events_count']; ?> events</div>
                                        <?php else: ?>
                                            <div><?php echo $user['tickets_count']; ?> tickets</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php
                                        $canDelete = ($user['events_count'] == 0 && $user['tickets_count'] == 0);
                                        ?>

                                        <?php if ($canDelete): ?>
                                            <form method="POST" action="admin/users/index.php" class="inline"
                                                onsubmit="return confirm('Are you sure you want to delete this user account? This will set the account to inactive and cannot be undone.')">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user"
                                                    class="bg-red-600 hover:bg-red-700 text-white text-xs font-medium px-3 py-1 rounded transition duration-200">
                                                    <i class="fas fa-trash mr-1"></i>Delete Account
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                                Cannot delete (has activity)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Test Results Section -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Test Results</h2>

            <?php
            // Get recent inactive users
            $sql = "SELECT * FROM users WHERE status = 'inactive' ORDER BY updated_at DESC LIMIT 5";
            $inactiveUsers = $db->fetchAll($sql);
            ?>

            <?php if (empty($inactiveUsers)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-check-circle text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">No inactive users found. Test the delete functionality above.</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <h3 class="font-medium text-gray-900">Recently Inactive Users:</h3>
                    <?php foreach ($inactiveUsers as $user): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <div class="font-medium text-gray-900">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($user['email']); ?> •
                                    <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                </div>
                            </div>
                            <div class="text-sm text-gray-500">
                                <?php echo formatDateTime($user['updated_at']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Navigation -->
        <div class="mt-6 flex gap-4">
            <a href="admin/users/index.php"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                <i class="fas fa-users mr-2"></i>User Management
            </a>
            <a href="admin/index.php"
                class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                <i class="fas fa-arrow-left mr-2"></i>Admin Dashboard
            </a>
        </div>
    </div>
</div>

<?php include 'includes/admin_footer.php'; ?>