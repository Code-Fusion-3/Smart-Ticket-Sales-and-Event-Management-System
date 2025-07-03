<?php
$pageTitle = "Planner Dashboard";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user has permission
checkPermission('event_planner');

// Get planner ID
$plannerId = getCurrentUserId();

// Get planner's events count
$sql = "SELECT 
            COUNT(*) as total_events,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_events,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_events,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_events,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_events
        FROM events 
        WHERE planner_id = $plannerId";
$eventStats = $db->fetchOne($sql);

// Get total tickets sold
$sql = "SELECT 
            COUNT(*) as tickets_sold,
            SUM(t.purchase_price) as total_revenue
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        WHERE e.planner_id = $plannerId
        AND t.status = 'sold'";
$ticketStats = $db->fetchOne($sql);


// Get upcoming events
$sql = "SELECT * FROM events 
        WHERE planner_id = $plannerId 
        AND start_date >= CURDATE()
        AND status = 'active'
        ORDER BY start_date ASC
        LIMIT 5";
$upcomingEvents = $db->fetchAll($sql);

// Include header
include '../includes/planner_header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Event Planner Dashboard</h1>
        <a href="events.php?action=create"
            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-plus mr-2"></i> Create New Event
        </a>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="rounded-full bg-indigo-100 p-3 mr-4">
                    <i class="fas fa-calendar text-indigo-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-gray-500 text-sm">Total Events</h3>
                    <p class="text-2xl font-bold"><?php echo $eventStats['total_events'] ?? 0; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="rounded-full bg-green-100 p-3 mr-4">
                    <i class="fas fa-ticket-alt text-green-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-gray-500 text-sm">Tickets Sold</h3>
                    <p class="text-2xl font-bold"><?php echo $ticketStats['tickets_sold'] ?? 0; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="rounded-full bg-blue-100 p-3 mr-4">
                    <i class="fas fa-calendar-check text-blue-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-gray-500 text-sm">Active Events</h3>
                    <p class="text-2xl font-bold"><?php echo $eventStats['active_events'] ?? 0; ?></p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="rounded-full bg-yellow-100 p-3 mr-4">
                    <i class="fas fa-dollar-sign text-yellow-600 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-gray-500 text-sm">Total Revenue</h3>
                    <p class="text-2xl font-bold"><?php echo formatCurrency($ticketStats['total_revenue'] ?? 0); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Events -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-bold mb-4">Upcoming Events</h2>

        <?php if (empty($upcomingEvents)): ?>
        <div class="text-center py-4">
            <p class="text-gray-500">You don't have any upcoming events.</p>
            <a href="events.php?action=create" class="mt-2 inline-block text-indigo-600 hover:text-indigo-800">
                Create your first event
            </a>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead>
                    <tr>
                        <th
                            class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Event Name
                        </th>
                        <th
                            class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date & Time
                        </th>
                        <th
                            class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Venue
                        </th>
                        <th
                            class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Tickets Sold
                        </th>
                        <th
                            class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcomingEvents as $event): ?>
                    <tr>
                        <td class="py-4 px-4 border-b border-gray-200">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">

                                    <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                        <i class="fas fa-calendar-alt text-indigo-600"></i>
                                    </div>

                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo $event['title']; ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo $event['category']; ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4 border-b border-gray-200">
                            <div class="text-sm text-gray-900">
                                <?php echo formatDate($event['start_date']); ?>
                            </div>
                            <div class="text-sm text-gray-500">
                                <?php echo formatTime($event['start_time']); ?> -
                                <?php echo formatTime($event['end_time']); ?>
                            </div>
                        </td>
                        <td class="py-4 px-4 border-b border-gray-200">
                            <div class="text-sm text-gray-900">
                                <?php echo $event['venue']; ?>
                            </div>
                            <div class="text-sm text-gray-500">
                                <?php echo $event['city']; ?>
                            </div>
                        </td>
                        <td class="py-4 px-4 border-b border-gray-200">
                            <?php
                                    // Get tickets sold for this event
                                    $sql = "SELECT COUNT(*) as count FROM tickets WHERE event_id = " . $event['id'] . " AND status = 'sold'";
                                    $ticketCount = $db->fetchOne($sql);
                                    $sold = $ticketCount['count'] ?? 0;
                                    $total = $event['total_tickets'];
                                    $percentage = ($total > 0) ? round(($sold / $total) * 100) : 0;
                                    ?>
                            <div class="text-sm text-gray-900">
                                <?php echo $sold; ?> / <?php echo $total; ?> (<?php echo $percentage; ?>%)
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-indigo-600 h-2.5 rounded-full"
                                    style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </td>
                        <td class="py-4 px-4 border-b border-gray-200 text-sm">
                            <a href="events/events.php?action=edit&id=<?php echo $event['id']; ?>"
                                class="text-indigo-600 hover:text-indigo-900 mr-3">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="tickets/tickets.php?event_id=<?php echo $event['id']; ?>"
                                class="text-green-600 hover:text-green-900 mr-3">
                                <i class="fas fa-ticket-alt"></i> Tickets
                            </a>
                            <a href="events/event-details.php?id=<?php echo $event['id']; ?>"
                                class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-4 text-right">
            <a href="events/events.php" class="text-indigo-600 hover:text-indigo-800">
                View all events <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Links -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-4">Quick Links</h2>
            <ul class="space-y-2">
                <li>
                    <a href="events/events.php" class="flex items-center text-indigo-600 hover:text-indigo-800">
                        <i class="fas fa-calendar-alt mr-2"></i> Manage Events
                    </a>
                </li>
                <li>
                    <a href="tickets/tickets.php" class="flex items-center text-indigo-600 hover:text-indigo-800">
                        <i class="fas fa-ticket-alt mr-2"></i> Manage Tickets
                    </a>
                </li>
                <li>
                    <a href="reports/reports.php" class="flex items-center text-indigo-600 hover:text-indigo-800">
                        <i class="fas fa-chart-bar mr-2"></i> View Reports
                    </a>
                </li>
                <li>
                    <a href="profile.php" class="flex items-center text-indigo-600 hover:text-indigo-800">
                        <i class="fas fa-user-edit mr-2"></i> Edit Profile
                    </a>
                </li>
            </ul>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-4">Recent Activity</h2>
            <?php
            // Get recent activity (ticket sales, etc.)
$sql = "SELECT t.id, t.created_at, t.purchase_price as price, u.username, e.title as event_title
FROM tickets t
JOIN events e ON t.event_id = e.id
JOIN users u ON t.user_id = u.id
WHERE e.planner_id = $plannerId
AND t.status = 'sold'
ORDER BY t.created_at DESC
LIMIT 5";
$recentActivity = $db->fetchAll($sql);

            
            if (empty($recentActivity)):
            ?>
            <p class="text-gray-500">No recent activity.</p>
            <?php else: ?>
            <ul class="space-y-3">
                <?php foreach ($recentActivity as $activity): ?>
                <li class="flex items-start">
                    <div class="flex-shrink-0 h-8 w-8 rounded-full bg-green-100 flex items-center justify-center mr-3">
                        <i class="fas fa-ticket-alt text-green-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-900">
                            <span class="font-medium"><?php echo $activity['username']; ?></span> purchased a ticket for
                            <span class="font-medium"><?php echo $activity['event_title']; ?></span>
                        </p>
                        <p class="text-xs text-gray-500">
                            <?php echo formatCurrency($activity['price']); ?> •
                            <?php echo timeAgo($activity['created_at']); ?>
                        </p>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart Section -->
<div class="container mx-auto px-4 py-6">
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-bold mb-4">Sales Overview</h2>
        <div class="w-full" style="height: 300px;">
            <canvas id="salesChart"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Get sales data for chart
    <?php
    // Get monthly sales data for the last 6 months
    $months = [];
    $salesData = [];
    
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthLabel = date('M Y', strtotime("-$i months"));
        $months[] = $monthLabel;
        
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $sql = "SELECT SUM(t.price) as total
                FROM tickets t
                JOIN events e ON t.event_id = e.id
                WHERE e.planner_id = $plannerId
                AND t.status = 'sold'
                AND t.created_at BETWEEN '$startDate' AND '$endDate 23:59:59'";
        $result = $db->fetchOne($sql);
        
        $salesData[] = $result['total'] ?? 0;
    }
    ?>

    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'Monthly Sales',
                data: <?php echo json_encode($salesData); ?>,
                backgroundColor: 'rgba(79, 70, 229, 0.2)',
                borderColor: 'rgba(79, 70, 229, 1)',
                borderWidth: 2,
                tension: 0.3,
                fill: true
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
                            return '<?php echo CURRENCY_SYMBOL; ?>' + value;
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Sales: <?php echo CURRENCY_SYMBOL; ?>' + context.parsed.y;
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php  ?>