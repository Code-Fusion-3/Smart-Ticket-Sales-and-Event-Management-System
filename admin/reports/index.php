<?php
$pageTitle = "Reports & Analytics";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

// Get date range for reports (default to last 30 days)
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));

// Revenue Analytics
$revenueSql = "SELECT 
                    DATE(t.created_at) as date,
                    SUM(CASE WHEN t.type = 'purchase' THEN t.amount ELSE 0 END) as ticket_sales,
                    SUM(CASE WHEN t.type = 'system_fee' THEN t.amount ELSE 0 END) as system_fees,
                    COUNT(CASE WHEN t.type = 'purchase' THEN 1 END) as ticket_count
                FROM transactions t
                WHERE t.status = 'completed'
                AND DATE(t.created_at) BETWEEN '$startDate' AND '$endDate'
                GROUP BY DATE(t.created_at)
                ORDER BY date ASC";
$revenueData = $db->fetchAll($revenueSql);

// User Registration Analytics
$userSql = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as new_users,
                SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as customers,
                SUM(CASE WHEN role = 'event_planner' THEN 1 ELSE 0 END) as planners,
                SUM(CASE WHEN role = 'agent' THEN 1 ELSE 0 END) as agents
            FROM users
            WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate'
            GROUP BY DATE(created_at)
            ORDER BY date ASC";
$userData = $db->fetchAll($userSql);

// Event Analytics
$eventSql = "SELECT 
                DATE(e.created_at) as date,
                COUNT(*) as events_created,
                SUM(CASE WHEN e.status = 'active' THEN 1 ELSE 0 END) as active_events,
                SUM(e.total_tickets) as total_tickets_available,
                SUM(e.total_tickets - e.available_tickets) as tickets_sold
            FROM events e
            WHERE DATE(e.created_at) BETWEEN '$startDate' AND '$endDate'
            GROUP BY DATE(e.created_at)
            ORDER BY date ASC";
$eventData = $db->fetchAll($eventSql);

// Top Performing Events
$topEventsSql = "SELECT 
                    e.id,
                    e.title,
                    e.start_date,
                    u.username as planner,
                    COUNT(t.id) as tickets_sold,
                    SUM(t.purchase_price) as total_revenue,
                    AVG(t.purchase_price) as avg_ticket_price
                FROM events e
                LEFT JOIN tickets t ON e.id = t.event_id AND t.status = 'sold'
                JOIN users u ON e.planner_id = u.id
                WHERE DATE(e.created_at) BETWEEN '$startDate' AND '$endDate'
                GROUP BY e.id
                HAVING tickets_sold > 0
                ORDER BY total_revenue DESC
                LIMIT 10";
$topEvents = $db->fetchAll($topEventsSql);

// Top Event Planners
$topPlannersSql = "SELECT 
                        u.id,
                        u.username,
                        u.email,
                        COUNT(DISTINCT e.id) as events_created,
                        COUNT(t.id) as tickets_sold,
                        SUM(t.purchase_price) as total_revenue
                    FROM users u
                    JOIN events e ON u.id = e.planner_id
                    LEFT JOIN tickets t ON e.id = t.event_id AND t.status = 'sold'
                    WHERE u.role = 'event_planner'
                    AND DATE(e.created_at) BETWEEN '$startDate' AND '$endDate'
                    GROUP BY u.id
                    HAVING tickets_sold > 0
                    ORDER BY total_revenue DESC
                    LIMIT 10";
$topPlanners = $db->fetchAll($topPlannersSql);

// Summary Statistics
$summarySql = "SELECT 
                    (SELECT COUNT(*) FROM users WHERE DATE(created_at) BETWEEN $startDate AND $endDate) as new_users,
                    (SELECT COUNT(*) FROM events WHERE DATE(created_at) BETWEEN $startDate AND $endDate) as new_events,
                    (SELECT COUNT(*) FROM tickets WHERE status = 'sold' AND DATE(created_at) BETWEEN $startDate AND $endDate) as tickets_sold,
                    (SELECT SUM(amount) FROM transactions WHERE type = 'purchase' AND status = 'completed' AND DATE(created_at) BETWEEN $startDate AND $endDate) as ticket_revenue,
                    (SELECT SUM(amount) FROM transactions WHERE type = 'system_fee' AND status = 'completed' AND DATE(created_at) BETWEEN $startDate AND $endDate) as system_fees,
                    (SELECT COUNT(*) FROM withdrawals WHERE DATE(created_at) BETWEEN $startDate AND $endDate) as withdrawal_requests,
                    (SELECT SUM(amount) FROM withdrawals WHERE status = 'completed' AND DATE(created_at) BETWEEN $startDate AND $endDate) as withdrawals_completed";
$summary = $db->fetchOne($summarySql);

include '../../includes/admin_header.php';
?>

<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Reports & Analytics</h1>
            <p class="text-gray-600 mt-2 text-sm sm:text-base">Platform performance and insights</p>
        </div>
        <div class="flex gap-2">
            <button onclick="window.print()"
                class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm">
                <i class="fas fa-print mr-2"></i>Print Report
            </button>
            <a href="export.php?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>"
                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm">
                <i class="fas fa-download mr-2"></i>Export Data
            </a>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <form method="GET" action="" class="flex flex-col sm:flex-row gap-4 items-end">
            <div class="flex-1">
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
            </div>

            <div class="flex-1">
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
            </div>

            <div>
                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm">
                    <i class="fas fa-filter mr-1"></i>Update Report
                </button>
            </div>
        </form>
    </div>

    <!-- Summary Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6 sm:mb-8">
        <!-- New Users -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <i class="fas fa-users text-lg sm:text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm sm:text-lg font-semibold text-gray-900">New Users</h3>
                    <p class="text-xl sm:text-2xl font-bold text-blue-600">
                        <?php echo number_format($summary['new_users'] ?? 0); ?></p>
                    <p class="text-xs sm:text-sm text-gray-500">Registered in period</p>
                </div>
            </div>
        </div>

        <!-- New Events -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600">
                    <i class="fas fa-calendar-alt text-lg sm:text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm sm:text-lg font-semibold text-gray-900">New Events</h3>
                    <p class="text-xl sm:text-2xl font-bold text-green-600">
                        <?php echo number_format($summary['new_events'] ?? 0); ?></p>
                    <p class="text-xs sm:text-sm text-gray-500">Created in period</p>
                </div>
            </div>
        </div>

        <!-- Tickets Sold -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                    <i class="fas fa-ticket-alt text-lg sm:text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm sm:text-lg font-semibold text-gray-900">Tickets Sold</h3>
                    <p class="text-xl sm:text-2xl font-bold text-purple-600">
                        <?php echo number_format($summary['tickets_sold'] ?? 0); ?></p>
                    <p class="text-xs sm:text-sm text-gray-500">Total sales</p>
                </div>
            </div>
        </div>

        <!-- Total Revenue -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                    <i class="fas fa-dollar-sign text-lg sm:text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm sm:text-lg font-semibold text-gray-900">Revenue</h3>
                    <p class="text-xl sm:text-2xl font-bold text-yellow-600">
                        <?php echo formatCurrency($summary['ticket_revenue'] ?? 0); ?></p>
                    <p class="text-xs sm:text-sm text-gray-500">Ticket sales</p>
                </div>
            </div>
        </div>

        <!-- System Fees -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                    <i class="fas fa-percentage text-lg sm:text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm sm:text-lg font-semibold text-gray-900">System Fees</h3>
                    <p class="text-xl sm:text-2xl font-bold text-indigo-600">
                        <?php echo formatCurrency($summary['system_fees'] ?? 0); ?></p>
                    <p class="text-xs sm:text-sm text-gray-500">Platform earnings</p>
                </div>
            </div>
        </div>

        <!-- Withdrawal Requests -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-red-100 text-red-600">
                    <i class="fas fa-money-bill-wave text-lg sm:text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm sm:text-lg font-semibold text-gray-900">Withdrawals</h3>
                    <p class="text-xl sm:text-2xl font-bold text-red-600">
                        <?php echo number_format($summary['withdrawal_requests'] ?? 0); ?></p>
                    <p class="text-xs sm:text-sm text-gray-500">Total requests</p>
                </div>
            </div>
        </div>

        <!-- Completed Withdrawals -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-teal-100 text-teal-600">
                    <i class="fas fa-check-circle text-lg sm:text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm sm:text-lg font-semibold text-gray-900">Paid Out</h3>
                    <p class="text-xl sm:text-2xl font-bold text-teal-600">
                        <?php echo formatCurrency($summary['withdrawals_completed'] ?? 0); ?></p>
                    <p class="text-xs sm:text-sm text-gray-500">Completed withdrawals</p>
                </div>
            </div>
        </div>

        <!-- Profit Margin -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-emerald-100 text-emerald-600">
                    <i class="fas fa-chart-line text-lg sm:text-xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-sm sm:text-lg font-semibold text-gray-900">Profit Margin</h3>
                    <?php 
                    $revenue = $summary['ticket_revenue'] ?? 0;
                    $fees = $summary['system_fees'] ?? 0;
                    $margin = $revenue > 0 ? round(($fees / $revenue) * 100, 1) : 0;
                    ?>
                    <p class="text-xl sm:text-2xl font-bold text-emerald-600"><?php echo $margin; ?>%</p>
                    <p class="text-xs sm:text-sm text-gray-500">Fee percentage</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Revenue Chart -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <h3 class="text-lg font-semibold mb-4">Revenue Trends</h3>
            <div class="h-64 flex items-center justify-center">
                <canvas id="revenueChart" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- User Registration Chart -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <h3 class="text-lg font-semibold mb-4">User Registration Trends</h3>
            <div class="h-64 flex items-center justify-center">
                <canvas id="userChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Performers Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Top Events -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <h3 class="text-lg font-semibold mb-4">Top Performing Events</h3>
            <?php if (empty($topEvents)): ?>
            <p class="text-gray-500 text-center py-8">No events found for the selected period</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 text-sm font-medium text-gray-500">Event</th>
                            <th class="text-left py-2 text-sm font-medium text-gray-500">Planner</th>
                            <th class="text-left py-2 text-sm font-medium text-gray-500">Sold</th>
                            <th class="text-left py-2 text-sm font-medium text-gray-500">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topEvents as $index => $event): ?>
                        <tr class="border-b">
                            <td class="py-2">
                                <div class="flex items-center">
                                    <span
                                        class="bg-indigo-100 text-indigo-800 text-xs font-medium px-2 py-1 rounded-full mr-2">
                                        #<?php echo $index + 1; ?>
                                    </span>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 truncate max-w-32">
                                            <?php echo htmlspecialchars($event['title']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo formatDate($event['start_date']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-2 text-sm text-gray-900">
                                <?php echo htmlspecialchars($event['planner']); ?>
                            </td>
                            <td class="py-2 text-sm font-medium">
                                <?php echo number_format($event['tickets_sold']); ?>
                            </td>
                            <td class="py-2 text-sm font-bold text-green-600">
                                <?php echo formatCurrency($event['total_revenue']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Top Event Planners -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <h3 class="text-lg font-semibold mb-4">Top Event Planners</h3>
            <?php if (empty($topPlanners)): ?>
            <p class="text-gray-500 text-center py-8">No planners found for the selected period</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-2 text-sm font-medium text-gray-500">Planner</th>
                            <th class="text-left py-2 text-sm font-medium text-gray-500">Events</th>
                            <th class="text-left py-2 text-sm font-medium text-gray-500">Tickets</th>
                            <th class="text-left py-2 text-sm font-medium text-gray-500">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topPlanners as $index => $planner): ?>
                        <tr class="border-b">
                            <td class="py-2">
                                <div class="flex items-center">
                                    <span
                                        class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded-full mr-2">
                                        #<?php echo $index + 1; ?>
                                    </span>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($planner['username']); ?>
                                        </div>
                                        <div class="text-xs text-gray-500 truncate max-w-32">
                                            <?php echo htmlspecialchars($planner['email']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-2 text-sm">
                                <?php echo number_format($planner['events_created']); ?>
                            </td>
                            <td class="py-2 text-sm">
                                <?php echo number_format($planner['tickets_sold']); ?>
                            </td>
                            <td class="py-2 text-sm font-bold text-green-600">
                                <?php echo formatCurrency($planner['total_revenue']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detailed Analytics Tables -->
    <div class="grid grid-cols-1 gap-6">
        <!-- Daily Revenue Breakdown -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <h3 class="text-lg font-semibold mb-4">Daily Revenue Breakdown</h3>
            <?php if (empty($revenueData)): ?>
            <p class="text-gray-500 text-center py-8">No revenue data found for the selected period</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b bg-gray-50">
                            <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Date</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Tickets Sold</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Ticket Revenue</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">System Fees</th>
                            <th class="text-left py-3 px-4 text-sm font-medium text-gray-500">Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                            $totalTickets = 0;
                            $totalTicketRevenue = 0;
                            $totalSystemFees = 0;
                            
                            foreach ($revenueData as $data): 
                                $totalTickets += $data['ticket_count'];
                                $totalTicketRevenue += $data['ticket_sales'];
                                $totalSystemFees += $data['system_fees'];
                                $dailyTotal = $data['ticket_sales'] + $data['system_fees'];
                            ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4 text-sm text-gray-900">
                                <?php echo formatDate($data['date']); ?>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-900">
                                <?php echo number_format($data['ticket_count']); ?>
                            </td>
                            <td class="py-3 px-4 text-sm font-medium text-green-600">
                                <?php echo formatCurrency($data['ticket_sales']); ?>
                            </td>
                            <td class="py-3 px-4 text-sm font-medium text-blue-600">
                                <?php echo formatCurrency($data['system_fees']); ?>
                            </td>
                            <td class="py-3 px-4 text-sm font-bold text-indigo-600">
                                <?php echo formatCurrency($dailyTotal); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 bg-gray-50">
                            <td class="py-3 px-4 text-sm font-bold text-gray-900">Total</td>
                            <td class="py-3 px-4 text-sm font-bold text-gray-900">
                                <?php echo number_format($totalTickets); ?>
                            </td>
                            <td class="py-3 px-4 text-sm font-bold text-green-600">
                                <?php echo formatCurrency($totalTicketRevenue); ?>
                            </td>
                            <td class="py-3 px-4 text-sm font-bold text-blue-600">
                                <?php echo formatCurrency($totalSystemFees); ?>
                            </td>
                            <td class="py-3 px-4 text-sm font-bold text-indigo-600">
                                <?php echo formatCurrency($totalTicketRevenue + $totalSystemFees); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js for analytics charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueData = <?php echo json_encode($revenueData); ?>;

    const revenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: revenueData.map(item => {
                const date = new Date(item.date);
                return date.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric'
                });
            }),
            datasets: [{
                label: 'Ticket Sales',
                data: revenueData.map(item => parseFloat(item.ticket_sales)),
                borderColor: 'rgb(34, 197, 94)',
                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                tension: 0.1
            }, {
                label: 'System Fees',
                data: revenueData.map(item => parseFloat(item.system_fees)),
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
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
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': Rwf' + context.parsed.y
                                .toLocaleString();
                        }
                    }
                }
            }
        }
    });

    // User Registration Chart
    const userCtx = document.getElementById('userChart').getContext('2d');
    const userData = <?php echo json_encode($userData); ?>;

    const userChart = new Chart(userCtx, {
        type: 'bar',
        data: {
            labels: userData.map(item => {
                const date = new Date(item.date);
                return date.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric'
                });
            }),
            datasets: [{
                label: 'Customers',
                data: userData.map(item => parseInt(item.customers)),
                backgroundColor: 'rgba(168, 85, 247, 0.8)',
                borderColor: 'rgb(168, 85, 247)',
                borderWidth: 1
            }, {
                label: 'Event Planners',
                data: userData.map(item => parseInt(item.planners)),
                backgroundColor: 'rgba(34, 197, 94, 0.8)',
                borderColor: 'rgb(34, 197, 94)',
                borderWidth: 1
            }, {
                label: 'Agents',
                data: userData.map(item => parseInt(item.agents)),
                backgroundColor: 'rgba(249, 115, 22, 0.8)',
                borderColor: 'rgb(249, 115, 22)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y + ' users';
                        }
                    }
                }
            }
        }
    });
});

// Print functionality
window.addEventListener('beforeprint', function() {
    // Hide non-essential elements when printing
    const elements = document.querySelectorAll('.no-print');
    elements.forEach(el => el.style.display = 'none');
});

window.addEventListener('afterprint', function() {
    // Restore elements after printing
    const elements = document.querySelectorAll('.no-print');
    elements.forEach(el => el.style.display = '');
});
</script>

<style>
@media print {
    .no-print {
        display: none !important;
    }

    .container {
        max-width: none !important;
        padding: 0 !important;
    }

    .bg-white {
        box-shadow: none !important;
    }

    .text-indigo-600,
    .text-blue-600,
    .text-green-600,
    .text-purple-600,
    .text-yellow-600,
    .text-red-600,
    .text-teal-600,
    .text-emerald-600 {
        color: #000 !important;
    }
}
</style>

<?php include '../../includes/admin_footer.php'; ?>