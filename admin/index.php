<?php
$pageTitle = "Admin Dashboard";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

// Get dashboard statistics
$userId = getCurrentUserId();

// Get total users by role
$sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
$userStats = $db->fetchAll($sql);

// Get total events by status
$sql = "SELECT status, COUNT(*) as count FROM events GROUP BY status";
$eventStats = $db->fetchAll($sql);

// Get total revenue from ticket sales
$sql = "SELECT SUM(purchase_price) as total_revenue FROM tickets WHERE status = 'sold'";
$revenueResult = $db->fetchOne($sql);
$totalRevenue = $revenueResult['total_revenue'] ?? 0;

// Get system fees collected (from transactions table where type = 'system_fee')
$sql = "SELECT SUM(amount) as total_fees FROM transactions WHERE type = 'system_fee' AND status = 'completed'";
$feesResult = $db->fetchOne($sql);
$totalFees = $feesResult['total_fees'] ?? 0;

// Get recent activities
$sql = "SELECT 
            t.id,
            t.created_at,
            t.purchase_price,
            u.username as buyer,
            e.title as event_title,
            ep.username as planner
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        JOIN events e ON t.event_id = e.id
        JOIN users ep ON e.planner_id = ep.id
        WHERE t.status = 'sold'
        ORDER BY t.created_at DESC
        LIMIT 10";
$recentSales = $db->fetchAll($sql);

// Get pending withdrawal requests
$sql = "SELECT 
            w.id,
            w.amount,
            w.created_at,
            u.username,
            u.email
        FROM withdrawals w
        JOIN users u ON w.user_id = u.id
        WHERE w.status = 'pending'
        ORDER BY w.created_at ASC
        LIMIT 5";
$pendingWithdrawals = $db->fetchAll($sql);

// Get total tickets sold
$sql = "SELECT COUNT(*) as total_tickets FROM tickets WHERE status = 'sold'";
$ticketResult = $db->fetchOne($sql);
$totalTickets = $ticketResult['total_tickets'] ?? 0;

include '../includes/admin_header.php';
?>

<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6">
    <!-- Page Header -->
    <div class="mb-6 sm:mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Admin Dashboard</h1>
        <p class="text-gray-600 mt-2 text-sm sm:text-base">Overview of platform activities and management tools</p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
        <!-- Total Revenue -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex items-center">
                <div class="p-2 sm:p-3 rounded-full bg-green-100 text-green-600 flex-shrink-0">
                    <i class="fas fa-dollar-sign text-lg sm:text-xl"></i>
                </div>
                <div class="ml-3 sm:ml-4 min-w-0 flex-1">
                    <h3 class="text-sm sm:text-lg font-semibold text-gray-900 truncate">Total Revenue</h3>
                    <p class="text-lg sm:text-2xl font-bold text-green-600 truncate">
                        <?php echo formatCurrency($totalRevenue); ?></p>
                    <p class="text-xs sm:text-sm text-gray-500"><?php echo $totalTickets; ?> tickets sold</p>
                </div>
            </div>
        </div>

        <!-- System Fees -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex items-center">
                <div class="p-2 sm:p-3 rounded-full bg-blue-100 text-blue-600 flex-shrink-0">
                    <i class="fas fa-percentage text-lg sm:text-xl"></i>
                </div>
                <div class="ml-3 sm:ml-4 min-w-0 flex-1">
                    <h3 class="text-sm sm:text-lg font-semibold text-gray-900 truncate">System Fees</h3>
                    <p class="text-lg sm:text-2xl font-bold text-blue-600 truncate">
                        <?php echo formatCurrency($totalFees); ?></p>
                    <p class="text-xs sm:text-sm text-gray-500">Platform earnings</p>
                </div>
            </div>
        </div>

        <!-- Total Users -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex items-center">
                <div class="p-2 sm:p-3 rounded-full bg-purple-100 text-purple-600 flex-shrink-0">
                    <i class="fas fa-users text-lg sm:text-xl"></i>
                </div>
                <div class="ml-3 sm:ml-4 min-w-0 flex-1">
                    <h3 class="text-sm sm:text-lg font-semibold text-gray-900 truncate">Total Users</h3>
                    <p class="text-lg sm:text-2xl font-bold text-purple-600">
                        <?php 
                        $totalUsers = 0;
                        foreach ($userStats as $stat) {
                            $totalUsers += $stat['count'];
                        }
                        echo $totalUsers;
                        ?>
                    </p>
                    <p class="text-xs sm:text-sm text-gray-500">Registered accounts</p>
                </div>
            </div>
        </div>

        <!-- Active Events -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex items-center">
                <div class="p-2 sm:p-3 rounded-full bg-orange-100 text-orange-600 flex-shrink-0">
                    <i class="fas fa-calendar-alt text-lg sm:text-xl"></i>
                </div>
                <div class="ml-3 sm:ml-4 min-w-0 flex-1">
                    <h3 class="text-sm sm:text-lg font-semibold text-gray-900 truncate">Active Events</h3>
                    <p class="text-lg sm:text-2xl font-bold text-orange-600">
                        <?php 
                        $activeEvents = 0;
                        foreach ($eventStats as $stat) {
                            if ($stat['status'] == 'active') {
                                $activeEvents = $stat['count'];
                                break;
                            }
                        }
                        echo $activeEvents;
                        ?>
                    </p>
                    <p class="text-xs sm:text-sm text-gray-500">Live events</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions and Statistics -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
            <div class="space-y-3">
                <a href="users/index.php"
                    class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded text-center transition duration-200 text-sm sm:text-base">
                    <i class="fas fa-users mr-2"></i> Manage Users
                </a>
                <a href="events/index.php"
                    class="block w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded text-center transition duration-200 text-sm sm:text-base">
                    <i class="fas fa-calendar mr-2"></i> Manage Events
                </a>
                <a href="finances/index.php"
                    class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-center transition duration-200 text-sm sm:text-base">
                    <i class="fas fa-chart-line mr-2"></i> Financial Overview
                </a>
                <a href="reports/index.php"
                    class="block w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded text-center transition duration-200 text-sm sm:text-base">
                    <i class="fas fa-file-alt mr-2"></i> Generate Reports
                </a>
            </div>
        </div>

        <!-- User Statistics -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <h3 class="text-lg font-semibold mb-4">User Distribution</h3>
            <div class="space-y-3">
                <?php foreach ($userStats as $stat): ?>
                <div class="flex justify-between items-center">
                    <span
                        class="capitalize text-gray-600 text-sm sm:text-base truncate"><?php echo str_replace('_', ' ', $stat['role']); ?></span>
                    <span class="font-bold text-gray-900 text-sm sm:text-base ml-2"><?php echo $stat['count']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Event Statistics -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <h3 class="text-lg font-semibold mb-4">Event Status</h3>
            <div class="space-y-3">
                <?php foreach ($eventStats as $stat): ?>
                <div class="flex justify-between items-center">
                    <span
                        class="capitalize text-gray-600 text-sm sm:text-base truncate"><?php echo $stat['status']; ?></span>
                    <span class="font-bold text-gray-900 text-sm sm:text-base ml-2"><?php echo $stat['count']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activities and Pending Actions -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 sm:gap-6">
        <!-- Recent Sales -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <h3 class="text-lg font-semibold mb-4">Recent Ticket Sales</h3>
            <?php if (empty($recentSales)): ?>
            <p class="text-gray-500 text-center py-4 text-sm sm:text-base">No recent sales</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 text-xs sm:text-sm font-medium text-gray-500">Event</th>
                            <th
                                class="text-left py-2 text-xs sm:text-sm font-medium text-gray-500 hidden sm:table-cell">
                                Buyer</th>
                            <th class="text-left py-2 text-xs sm:text-sm font-medium text-gray-500">Amount</th>
                            <th
                                class="text-left py-2 text-xs sm:text-sm font-medium text-gray-500 hidden md:table-cell">
                                Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSales as $sale): ?>
                        <tr class="border-b">
                            <td class="py-2">
                                <div class="text-xs sm:text-sm font-medium truncate max-w-32 sm:max-w-none">
                                    <?php echo htmlspecialchars($sale['event_title']); ?></div>
                                <div class="text-xs text-gray-500 truncate max-w-32 sm:max-w-none">by
                                    <?php echo htmlspecialchars($sale['planner']); ?></div>
                                <div class="text-xs text-gray-500 sm:hidden">
                                    <?php echo htmlspecialchars($sale['buyer']); ?></div>
                            </td>
                            <td class="py-2 text-xs sm:text-sm hidden sm:table-cell">
                                <?php echo htmlspecialchars($sale['buyer']); ?></td>
                            <td class="py-2 text-xs sm:text-sm font-medium">
                                <?php echo formatCurrency($sale['purchase_price']); ?></td>
                            <td class="py-2 text-xs text-gray-500 hidden md:table-cell">
                                <?php echo formatDateTime($sale['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            <div class="mt-4">
                <a href="reports/sales.php" class="text-indigo-600 hover:text-indigo-800 text-xs sm:text-sm">View all
                    sales →</a>
            </div>
        </div>

        <!-- Pending Withdrawals -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <h3 class="text-lg font-semibold mb-4">Pending Withdrawals</h3>
            <?php if (empty($pendingWithdrawals)): ?>
            <p class="text-gray-500 text-center py-4 text-sm sm:text-base">No pending withdrawal requests</p>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($pendingWithdrawals as $withdrawal): ?>
                <div class="flex justify-between items-center p-3 bg-yellow-50 rounded-lg">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium text-sm sm:text-base truncate">
                            <?php echo htmlspecialchars($withdrawal['username']); ?></div>
                        <div class="text-xs sm:text-sm text-gray-500">
                            <?php echo formatDateTime($withdrawal['created_at']); ?></div>
                    </div>
                    <div class="text-right ml-4 flex-shrink-0">
                        <div class="font-bold text-sm sm:text-base"><?php echo formatCurrency($withdrawal['amount']); ?>
                        </div>
                        <a href="finances/withdrawals.php?id=<?php echo $withdrawal['id']; ?>"
                            class="text-xs text-indigo-600 hover:text-indigo-800">Review</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="mt-4">
                <a href="finances/withdrawals.php" class="text-indigo-600 hover:text-indigo-800 text-xs sm:text-sm">View
                    all requests →</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin_footer.php'; ?>