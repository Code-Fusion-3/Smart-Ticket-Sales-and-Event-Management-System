<?php
$pageTitle = "Financial Overview";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

// Date range filter
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d'); // Today
$period = $_GET['period'] ?? 'month';

// Set date range based on period
switch ($period) {
    case 'today':
        $startDate = $endDate = date('Y-m-d');
        break;
    case 'week':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = date('Y-m-d');
        break;
    case 'month':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-d');
        break;
    case 'quarter':
        $startDate = date('Y-m-01', strtotime('-3 months'));
        $endDate = date('Y-m-d');
        break;
    case 'year':
        $startDate = date('Y-01-01');
        $endDate = date('Y-m-d');
        break;
}

// Revenue Statistics
$revenueSql = "SELECT 
                SUM(CASE WHEN t.type = 'purchase' THEN t.amount ELSE 0 END) as total_sales,
                SUM(CASE WHEN t.type = 'system_fee' THEN t.amount ELSE 0 END) as total_fees,
                COUNT(CASE WHEN t.type = 'purchase' THEN 1 END) as total_transactions,
                COUNT(DISTINCT CASE WHEN t.type = 'purchase' THEN t.user_id END) as unique_customers
               FROM transactions t 
               WHERE t.status = 'completed' 
               AND DATE(t.created_at) BETWEEN '$startDate' AND '$endDate'";
$revenueStats = $db->fetchOne($revenueSql);

// Withdrawal Statistics
$withdrawalSql = "SELECT 
                    SUM(amount) as total_withdrawals,
                    SUM(fee) as total_withdrawal_fees,
                    COUNT(*) as total_withdrawal_requests,
                    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
                  FROM withdrawals 
                  WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate'";
$withdrawalStats = $db->fetchOne($withdrawalSql);

// Daily Revenue Chart Data
$dailyRevenueSql = "SELECT 
                        DATE(t.created_at) as date,
                        SUM(CASE WHEN t.type = 'purchase' THEN t.amount ELSE 0 END) as sales,
                        SUM(CASE WHEN t.type = 'system_fee' THEN t.amount ELSE 0 END) as fees
                    FROM transactions t 
                    WHERE t.status = 'completed' 
                    AND DATE(t.created_at) BETWEEN '$startDate' AND '$endDate'
                    GROUP BY DATE(t.created_at)
                    ORDER BY DATE(t.created_at)";
$dailyRevenue = $db->fetchAll($dailyRevenueSql);

// Top Events by Revenue
$topEventsSql = "SELECT 
                    e.title,
                    e.id,
                    u.username as planner_name,
                    SUM(t.amount) as revenue,
                    COUNT(t.id) as ticket_count
                 FROM transactions t
                 JOIN tickets tk ON t.reference_id = tk.id
                 JOIN events e ON tk.event_id = e.id
                 JOIN users u ON e.planner_id = u.id
                 WHERE t.type = 'purchase' 
                 AND t.status = 'completed'
                 AND DATE(t.created_at) BETWEEN '$startDate' AND '$endDate'
                 GROUP BY e.id
                 ORDER BY revenue DESC
                 LIMIT 10";
$topEvents = $db->fetchAll($topEventsSql);

// Top Event Planners by Revenue
$topPlannersSql = "SELECT 
                      u.username,
                      u.id,
                      u.email,
                      SUM(t.amount) as revenue,
                      COUNT(DISTINCT e.id) as event_count,
                      COUNT(t.id) as ticket_count
                   FROM transactions t
                   JOIN tickets tk ON t.reference_id = tk.id
                   JOIN events e ON tk.event_id = e.id
                   JOIN users u ON e.planner_id = u.id
                   WHERE t.type = 'purchase' 
                   AND t.status = 'completed'
                   AND DATE(t.created_at) BETWEEN '$startDate' AND '$endDate'
                   GROUP BY u.id
                   ORDER BY revenue DESC
                   LIMIT 10";
$topPlanners = $db->fetchAll($topPlannersSql);

// Recent High-Value Transactions
$highValueSql = "SELECT 
                    t.*,
                    u.username as user_name,
                    u.email as user_email,
                    e.title as event_title
                 FROM transactions t
                 JOIN users u ON t.user_id = u.id
                 LEFT JOIN tickets tk ON t.reference_id = tk.id
                 LEFT JOIN events e ON tk.event_id = e.id
                 WHERE t.amount >= 50000 
                 AND DATE(t.created_at) BETWEEN '$startDate' AND '$endDate'
                 ORDER BY t.amount DESC, t.created_at DESC
                 LIMIT 20";
$highValueTransactions = $db->fetchAll($highValueSql);

// Pending Withdrawals
$pendingWithdrawalsSql = "SELECT 
                            w.*,
                            u.username,
                            u.email
                          FROM withdrawals w
                          JOIN users u ON w.user_id = u.id
                          WHERE w.status = 'pending'
                          ORDER BY w.created_at ASC
                          LIMIT 10";
$pendingWithdrawals = $db->fetchAll($pendingWithdrawalsSql);

include '../../includes/admin_header.php';
?>

<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Financial Overview</h1>
            <p class="text-gray-600 mt-2 text-sm sm:text-base">Monitor platform revenue, transactions, and financial
                health</p>
        </div>
        <div class="flex gap-2">
            <a href="../index.php"
                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm sm:text-base">
                <i class="fas fa-arrow-left mr-2"></i>Dashboard
            </a>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <form method="GET" action="" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Quick Period Selection -->
                <div>
                    <label for="period" class="block text-sm font-medium text-gray-700 mb-1">Quick Select</label>
                    <select id="period" name="period"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm"
                        onchange="this.form.submit()">
                        <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>Last 3 Months
                        </option>
                        <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>This Year</option>
                        <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom Range
                        </option>
                    </select>
                </div>

                <!-- Start Date -->
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                </div>

                <!-- End Date -->
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                </div>

                <!-- Filter Button -->
                <div class="flex items-end">
                    <button type="submit"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm">
                        <i class="fas fa-filter mr-1"></i>Apply Filter
                    </button>
                </div>

                <!-- Export Button -->
                <div class="flex items-end">
                    <a href="export.php?<?php echo http_build_query($_GET); ?>"
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm text-center">
                        <i class="fas fa-download mr-1"></i>Export
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total Sales -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600">
                    <i class="fas fa-dollar-sign text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Total Sales</h3>
                    <p class="text-xl sm:text-2xl font-bold text-green-600">
                        <?php echo formatCurrency($revenueStats['total_sales'] ?? 0); ?></p>
                    <p class="text-xs text-gray-500">
                        <?php echo number_format($revenueStats['total_transactions'] ?? 0); ?> transactions</p>
                </div>
            </div>
        </div>

        <!-- Platform Fees -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-percentage text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Platform Fees</h3>
                    <p class="text-xl sm:text-2xl font-bold text-blue-600">
                        <?php echo formatCurrency($revenueStats['total_fees'] ?? 0); ?></p>
                    <p class="text-xs text-gray-500">
                        <?php 
                        $feePercentage = ($revenueStats['total_sales'] > 0) ? 
                            round(($revenueStats['total_fees'] / $revenueStats['total_sales']) * 100, 2) : 0;
                        echo $feePercentage . '% of sales';
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Total Withdrawals -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-600">
                    <i class="fas fa-arrow-down text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Withdrawals</h3>
                    <p class="text-xl sm:text-2xl font-bold text-red-600">
                        <?php echo formatCurrency($withdrawalStats['total_withdrawals'] ?? 0); ?></p>
                    <p class="text-xs text-gray-500">
                        <?php echo number_format($withdrawalStats['total_withdrawal_requests'] ?? 0); ?> requests</p>
                </div>
            </div>
        </div>

        <!-- Pending Withdrawals -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                    <i class="fas fa-clock text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm font-medium text-gray-500">Pending</h3>
                    <p class="text-xl sm:text-2xl font-bold text-yellow-600">
                        <?php echo formatCurrency($withdrawalStats['pending_amount'] ?? 0); ?></p>
                    <p class="text-xs text-gray-500">
                        <?php echo number_format($withdrawalStats['pending_count'] ?? 0); ?> pending</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Chart -->
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Daily Revenue Trend</h3>
        <div class="h-64 sm:h-80">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Financial Analytics Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Top Events by Revenue -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <h3 class="text-lg font-semibold mb-4">Top Events by Revenue</h3>
            <?php if (empty($topEvents)): ?>
            <div class="text-center py-8">
                <i class="fas fa-chart-bar text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">No event data for selected period</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($topEvents as $index => $event): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div
                            class="w-8 h-8 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-sm font-bold mr-3">
                            <?php echo $index + 1; ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-medium text-gray-900 truncate">
                                <?php echo htmlspecialchars($event['title']); ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                by <?php echo htmlspecialchars($event['planner_name']); ?> •
                                <?php echo number_format($event['ticket_count']); ?> tickets
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-bold text-green-600">
                            <?php echo formatCurrency($event['revenue']); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4">
                <a href="../events/index.php" class="text-indigo-600 hover:text-indigo-800 text-sm">
                    View all events →
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Top Event Planners -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <h3 class="text-lg font-semibold mb-4">Top Event Planners</h3>
            <?php if (empty($topPlanners)): ?>
            <div class="text-center py-8">
                <i class="fas fa-users text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">No planner data for selected period</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($topPlanners as $index => $planner): ?>
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div
                            class="w-8 h-8 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center text-sm font-bold mr-3">
                            <?php echo $index + 1; ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-medium text-gray-900 truncate">
                                <?php echo htmlspecialchars($planner['username']); ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php echo number_format($planner['event_count']); ?> events •
                                <?php echo number_format($planner['ticket_count']); ?> tickets
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm font-bold text-green-600">
                            <?php echo formatCurrency($planner['revenue']); ?>
                        </div>
                        <a href="../users/view.php?id=<?php echo $planner['id']; ?>"
                            class="text-xs text-indigo-600 hover:text-indigo-800">
                            View Profile
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4">
                <a href="../users/index.php?role=event_planner" class="text-indigo-600 hover:text-indigo-800 text-sm">
                    View all planners →
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- High-Value Transactions & Pending Withdrawals -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- High-Value Transactions -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <h3 class="text-lg font-semibold mb-4">High-Value Transactions</h3>
            <?php if (empty($highValueTransactions)): ?>
            <div class="text-center py-8">
                <i class="fas fa-money-bill-wave text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">No high-value transactions for selected period</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="text-left py-2 text-xs font-medium text-gray-500 uppercase">Event</th>
                            <th class="text-left py-2 text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="text-left py-2 text-xs font-medium text-gray-500 uppercase">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($highValueTransactions as $transaction): ?>
                        <tr class="border-b">
                            <td class="py-2">
                                <div class="text-sm font-medium">
                                    <?php echo htmlspecialchars($transaction['user_name']); ?></div>
                                <div class="text-xs text-gray-500">
                                    <?php echo htmlspecialchars($transaction['user_email']); ?></div>
                            </td>
                            <td class="py-2">
                                <div class="text-sm">
                                    <?php echo htmlspecialchars($transaction['event_title'] ?? 'N/A'); ?></div>
                            </td>
                            <td class="py-2">
                                <div class="text-sm font-bold text-green-600">
                                    <?php echo formatCurrency($transaction['amount']); ?>
                                </div>
                            </td>
                            <td class="py-2">
                                <div class="text-xs text-gray-500">
                                    <?php echo formatDateTime($transaction['created_at']); ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4">
                <a href="transactions.php" class="text-indigo-600 hover:text-indigo-800 text-sm">
                    View all transactions →
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pending Withdrawals -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Pending Withdrawals</h3>
                <?php if (!empty($pendingWithdrawals)): ?>
                <span class="bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
                    <?php echo count($pendingWithdrawals); ?> pending
                </span>
                <?php endif; ?>
            </div>

            <?php if (empty($pendingWithdrawals)): ?>
            <div class="text-center py-8">
                <i class="fas fa-check-circle text-4xl text-green-300 mb-4"></i>
                <p class="text-gray-500">No pending withdrawal requests</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($pendingWithdrawals as $withdrawal): ?>
                <div class="flex justify-between items-center p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($withdrawal['username']); ?>
                        </div>
                        <div class="text-xs text-gray-500">
                            <?php echo htmlspecialchars($withdrawal['email']); ?>
                        </div>
                        <div class="text-xs text-gray-500">
                            Requested: <?php echo formatDateTime($withdrawal['created_at']); ?>
                        </div>
                    </div>
                    <div class="text-right ml-4">
                        <div class="text-sm font-bold text-red-600">
                            <?php echo formatCurrency($withdrawal['amount']); ?>
                        </div>
                        <div class="text-xs text-gray-500">
                            Fee: <?php echo formatCurrency($withdrawal['fee']); ?>
                        </div>
                        <a href="withdrawals.php?id=<?php echo $withdrawal['id']; ?>"
                            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                            Review →
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4">
                <a href="withdrawals.php" class="text-indigo-600 hover:text-indigo-800 text-sm">
                    View all withdrawal requests →
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Revenue Chart
    const ctx = document.getElementById('revenueChart').getContext('2d');

    const chartData = {
        labels: [
            <?php 
            foreach ($dailyRevenue as $day) {
                echo "'" . date('M j', strtotime($day['date'])) . "',";
            }
            ?>
        ],
        datasets: [{
            label: 'Sales Revenue',
            data: [
                <?php 
                foreach ($dailyRevenue as $day) {
                    echo ($day['sales'] ?? 0) . ",";
                }
                ?>
            ],
            backgroundColor: 'rgba(34, 197, 94, 0.1)',
            borderColor: 'rgba(34, 197, 94, 1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }, {
            label: 'Platform Fees',
            data: [
                <?php 
                foreach ($dailyRevenue as $day) {
                    echo ($day['fees'] ?? 0) . ",";
                }
                ?>
            ],
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            borderColor: 'rgba(59, 130, 246, 1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    };

    new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rwf' + value.toLocaleString();
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            elements: {
                point: {
                    radius: 4,
                    hoverRadius: 6
                }
            }
        }
    });

    // Period selector functionality
    document.getElementById('period').addEventListener('change', function() {
        if (this.value !== 'custom') {
            // Auto-submit for quick selections
            this.form.submit();
        }
    });
});
</script>

<?php include '../../includes/admin_footer.php'; ?>