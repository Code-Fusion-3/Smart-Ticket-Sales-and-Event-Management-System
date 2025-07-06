<?php
$pageTitle = "Reports & Analytics";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has permission
checkPermission('event_planner');

// Get planner ID
$plannerId = getCurrentUserId();

// Get date range filters
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month

// Get sales overview data
$sql = "SELECT 
            COUNT(*) as total_tickets_sold,
            SUM(t.purchase_price) as total_revenue,
            AVG(t.purchase_price) as average_ticket_price,
            COUNT(DISTINCT t.event_id) as events_with_sales
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        WHERE e.planner_id = $plannerId
        AND t.status = 'sold'
        AND DATE(t.created_at) BETWEEN '$startDate' AND '$endDate'";
$salesOverview = $db->fetchOne($sql);

// Get event performance data
$sql = "SELECT 
            e.id,
            e.title,
            e.start_date,
            e.venue,
            e.city,
            COUNT(t.id) as tickets_sold,
            SUM(t.purchase_price) as revenue,
            e.total_tickets,
            ROUND((COUNT(t.id) / e.total_tickets) * 100, 2) as sell_percentage
        FROM events e
        LEFT JOIN tickets t ON e.id = t.event_id AND t.status = 'sold'
        WHERE e.planner_id = $plannerId
        AND e.start_date BETWEEN '$startDate' AND '$endDate'
        GROUP BY e.id
        ORDER BY e.start_date DESC";
$eventPerformance = $db->fetchAll($sql);

// Get monthly sales data for chart
$monthlyData = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthLabel = date('M Y', strtotime("-$i months"));
    $startDate = $month . '-01';
    $endDate = date('Y-m-t', strtotime($startDate));

    $sql = "SELECT 
                COUNT(*) as tickets_sold,
                SUM(t.purchase_price) as revenue
            FROM tickets t
            JOIN events e ON t.event_id = e.id
            WHERE e.planner_id = $plannerId
            AND t.status = 'sold'
            AND t.created_at BETWEEN '$startDate' AND '$endDate 23:59:59'";
    $result = $db->fetchOne($sql);

    $monthlyData[] = [
        'month' => $monthLabel,
        'tickets' => $result['tickets_sold'] ?? 0,
        'revenue' => $result['revenue'] ?? 0
    ];
}

// Get top performing events
$sql = "SELECT 
            e.title,
            COUNT(t.id) as tickets_sold,
            SUM(t.purchase_price) as revenue
        FROM events e
        LEFT JOIN tickets t ON e.id = t.event_id AND t.status = 'sold'
        WHERE e.planner_id = $plannerId
        GROUP BY e.id
        HAVING tickets_sold > 0
        ORDER BY revenue DESC
        LIMIT 5";
$topEvents = $db->fetchAll($sql);

include '../../includes/planner_header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Reports & Analytics</h1>
        <div class="flex gap-2">
            <a href="../index.php" class="text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
            </a>
            <a href="export_pdf.php?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>"
                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-download mr-2"></i> Export PDF
            </a>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="" class="flex gap-4 items-end">
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>"
                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
            </div>
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>"
                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
            </div>
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-filter mr-2"></i> Filter
            </button>
            <a href="reports.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-times mr-2"></i> Clear
            </a>
        </form>
    </div>

    <!-- Sales Overview Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="rounded-full bg-green-100 p-3 mr-4">
                    <i class="fas fa-ticket-alt text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-gray-500 text-sm">Tickets Sold</h3>
                    <p class="text-2xl font-bold">
                        <?php echo number_format($salesOverview['total_tickets_sold'] ?? 0); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="rounded-full bg-blue-100 p-3 mr-4">
                    <i class="fas fa-dollar-sign text-blue-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-gray-500 text-sm">Total Revenue</h3>
                    <p class="text-2xl font-bold"><?php echo formatCurrency($salesOverview['total_revenue'] ?? 0); ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="rounded-full bg-yellow-100 p-3 mr-4">
                    <i class="fas fa-chart-line text-yellow-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-gray-500 text-sm">Avg. Ticket Price</h3>
                    <p class="text-2xl font-bold">
                        <?php echo formatCurrency($salesOverview['average_ticket_price'] ?? 0); ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="rounded-full bg-purple-100 p-3 mr-4">
                    <i class="fas fa-calendar-check text-purple-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-gray-500 text-sm">Events with Sales</h3>
                    <p class="text-2xl font-bold"><?php echo number_format($salesOverview['events_with_sales'] ?? 0); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Chart -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-bold mb-4">Monthly Sales Trend</h2>
        <div class="w-full" style="height: 400px;">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <!-- Top Performing Events -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-bold mb-4">Top Performing Events</h2>
        <?php if (empty($topEvents)): ?>
        <p class="text-gray-500 text-center py-4">No events with sales found.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Tickets Sold</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Revenue</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Performance</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($topEvents as $event): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($event['title']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo number_format($event['tickets_sold']); ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                <?php echo formatCurrency($event['revenue']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="bg-green-600 h-2 rounded-full"
                                        style="width: <?php echo min(100, ($event['tickets_sold'] / max(1, $event['tickets_sold'])) * 100); ?>%">
                                    </div>
                                </div>
                                <span class="text-sm text-gray-500"><?php echo number_format($event['tickets_sold']); ?>
                                    sold</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Event Performance Table -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-bold mb-4">Event Performance Details</h2>
        <?php if (empty($eventPerformance)): ?>
        <p class="text-gray-500 text-center py-4">No events found in the selected date range.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Venue
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Tickets Sold</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Revenue</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sell
                            Rate</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($eventPerformance as $event): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($event['title']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo formatDate($event['start_date']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($event['venue']); ?></div>
                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($event['city']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo $event['tickets_sold']; ?> /
                                <?php echo $event['total_tickets']; ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">
                                <?php echo formatCurrency($event['revenue']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-16 bg-gray-200 rounded-full h-2 mr-2">
                                    <div class="bg-indigo-600 h-2 rounded-full"
                                        style="width: <?php echo $event['sell_percentage']; ?>%"></div>
                                </div>
                                <span class="text-sm text-gray-500"><?php echo $event['sell_percentage']; ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($monthlyData, 'month')); ?>,
            datasets: [{
                label: 'Revenue',
                data: <?php echo json_encode(array_column($monthlyData, 'revenue')); ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true,
                yAxisID: 'y'
            }, {
                label: 'Tickets Sold',
                data: <?php echo json_encode(array_column($monthlyData, 'tickets')); ?>,
                backgroundColor: 'rgba(16, 185, 129, 0.2)',
                borderColor: 'rgba(16, 185, 129, 1)',
                borderWidth: 2,
                tension: 0.3,
                fill: false,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            scales: {
                x: {
                    display: true,
                    title: {
                        display: true,
                        text: 'Month'
                    }
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Revenue (RWF)'
                    },
                    ticks: {
                        callback: function(value) {
                            return 'Rwf ' + value.toLocaleString();
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Tickets Sold'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (context.dataset.label === 'Revenue') {
                                return 'Revenue: Rwf ' + context.parsed.y.toLocaleString();
                            } else {
                                return 'Tickets: ' + context.parsed.y;
                            }
                        }
                    }
                }
            }
        }
    });
});
</script>