<?php
$pageTitle = "User Details";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($userId <= 0) {
    $_SESSION['error_message'] = "Invalid user ID";
    redirect('index.php');
}

// Get user details
$sql = "SELECT * FROM users WHERE id = $userId";
$user = $db->fetchOne($sql);

if (!$user) {
    $_SESSION['error_message'] = "User not found";
    redirect('index.php');
}

// Get user statistics based on role
$userStats = [];

if ($user['role'] === 'event_planner') {
    // Get planner statistics
    $sql = "SELECT 
                COUNT(*) as total_events,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_events,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_events,
                SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) as canceled_events
            FROM events WHERE planner_id = $userId";
    $eventStats = $db->fetchOne($sql);
    
    // Get ticket sales statistics
    $sql = "SELECT 
                COUNT(t.id) as total_tickets_sold,
                SUM(t.purchase_price) as total_revenue,
                COUNT(DISTINCT t.event_id) as events_with_sales
            FROM tickets t
            JOIN events e ON t.event_id = e.id
            WHERE e.planner_id = $userId AND t.status = 'sold'";
    $salesStats = $db->fetchOne($sql);
    
    // Get recent events
    $sql = "SELECT id, title, start_date, status, total_tickets, available_tickets
            FROM events 
            WHERE planner_id = $userId 
            ORDER BY created_at DESC 
            LIMIT 5";
    $recentEvents = $db->fetchAll($sql);
    
    $userStats = [
        'events' => $eventStats,
        'sales' => $salesStats,
        'recent_events' => $recentEvents
    ];
    
} elseif ($user['role'] === 'customer') {
    // Get customer statistics
    $sql = "SELECT 
                COUNT(*) as total_tickets,
                SUM(purchase_price) as total_spent,
                COUNT(DISTINCT event_id) as events_attended
            FROM tickets 
            WHERE user_id = $userId AND status = 'sold'";
    $ticketStats = $db->fetchOne($sql);
    
    // Get recent ticket purchases
    $sql = "SELECT 
                t.id,
                t.purchase_price,
                t.created_at,
                e.title as event_title,
                e.start_date,
                e.venue
            FROM tickets t
            JOIN events e ON t.event_id = e.id
            WHERE t.user_id = $userId AND t.status = 'sold'
            ORDER BY t.created_at DESC
            LIMIT 5";
    $recentTickets = $db->fetchAll($sql);
    
    $userStats = [
        'tickets' => $ticketStats,
        'recent_tickets' => $recentTickets
    ];
    
} elseif ($user['role'] === 'agent') {
    // Get agent statistics
    $sql = "SELECT 
                COUNT(*) as total_scans,
                COUNT(DISTINCT ticket_id) as unique_tickets_scanned,
                COUNT(DISTINCT DATE(scan_time)) as active_days
            FROM agent_scans 
            WHERE agent_id = $userId";
    $scanStats = $db->fetchOne($sql);
    
    // Get recent scans
    $sql = "SELECT 
                as_table.scan_time,
                as_table.status,
                e.title as event_title,
                t.id as ticket_id
            FROM agent_scans as_table
            JOIN tickets t ON as_table.ticket_id = t.id
            JOIN events e ON t.event_id = e.id
            WHERE as_table.agent_id = $userId
            ORDER BY as_table.scan_time DESC
            LIMIT 5";
    $recentScans = $db->fetchAll($sql);
    
    $userStats = [
        'scans' => $scanStats,
        'recent_scans' => $recentScans
    ];
}

// Get transaction history
$sql = "SELECT 
            id,
            amount,
            type,
            status,
            description,
            created_at
        FROM transactions 
        WHERE user_id = $userId 
        ORDER BY created_at DESC 
        LIMIT 10";
$transactions = $db->fetchAll($sql);

include '../../includes/admin_header.php';
?>

<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">User Details</h1>
            <p class="text-gray-600 mt-2 text-sm sm:text-base">Detailed information for
                <?php echo htmlspecialchars($user['username']); ?></p>
        </div>
        <a href="index.php"
            class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm sm:text-base">
            <i class="fas fa-arrow-left mr-2"></i>Back to Users
        </a>
    </div>

    <!-- User Profile Card -->
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
            <div class="flex-shrink-0">
                <?php if ($user['profile_image']): ?>
                <img class="h-16 w-16 sm:h-20 sm:w-20 rounded-full object-cover"
                    src="<?php echo SITE_URL; ?>/uploads/profiles/<?php echo $user['profile_image']; ?>" alt="Profile">
                <?php else: ?>
                <div class="h-16 w-16 sm:h-20 sm:w-20 rounded-full bg-gray-300 flex items-center justify-center">
                    <i class="fas fa-user text-gray-600 text-2xl"></i>
                </div>
                <?php endif; ?>
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 mb-2">
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900">
                        <?php echo htmlspecialchars($user['username']); ?></h2>
                    <div class="flex gap-2">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            <?php 
                            switch($user['role']) {
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

                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            <?php 
                            switch($user['status']) {
                                case 'active':
                                    echo 'bg-green-100 text-green-800';
                                    break;
                                case 'suspended':
                                    echo 'bg-red-100 text-red-800';
                                    break;
                                case 'inactive':
                                    echo 'bg-yellow-100 text-yellow-800';
                                    break;
                                default:
                                    echo 'bg-gray-100 text-gray-800';
                            }
                            ?>">
                            <?php echo ucfirst($user['status']); ?>
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Email:</span>
                        <div class="font-medium truncate"><?php echo htmlspecialchars($user['email']); ?></div>
                    </div>
                    <div>
                        <span class="text-gray-500">Phone:</span>
                        <div class="font-medium"><?php echo htmlspecialchars($user['phone_number']); ?></div>
                    </div>
                    <div>
                        <span class="text-gray-500">Balance:</span>
                        <div class="font-medium text-green-600"><?php echo formatCurrency($user['balance']); ?></div>
                    </div>
                    <div>
                        <span class="text-gray-500">Joined:</span>
                        <div class="font-medium"><?php echo formatDate($user['created_at']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Role-specific Statistics -->
    <?php if ($user['role'] === 'event_planner' && !empty($userStats['events'])): ?>
    <!-- Event Planner Statistics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Event Statistics -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <h3 class="text-lg font-semibold mb-4">Event Statistics</h3>
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center p-3 bg-blue-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600"><?php echo $userStats['events']['total_events']; ?>
                    </div>
                    <div class="text-sm text-gray-600">Total Events</div>
                </div>
                <div class="text-center p-3 bg-green-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-600"><?php echo $userStats['events']['active_events']; ?>
                    </div>
                    <div class="text-sm text-gray-600">Active Events</div>
                </div>
                <div class="text-center p-3 bg-purple-50 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">
                        <?php echo $userStats['events']['completed_events']; ?></div>
                    <div class="text-sm text-gray-600">Completed</div>
                </div>
                <div class="text-center p-3 bg-red-50 rounded-lg">
                    <div class="text-2xl font-bold text-red-600"><?php echo $userStats['events']['canceled_events']; ?>
                    </div>
                    <div class="text-sm text-gray-600">Canceled</div>
                </div>
            </div>
        </div>

        <!-- Sales Statistics -->
        <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
            <h3 class="text-lg font-semibold mb-4">Sales Statistics</h3>
            <div class="space-y-4">
                <div class="flex justify-between items-center p-3 bg-green-50 rounded-lg">
                    <span class="text-gray-600">Total Revenue</span>
                    <span
                        class="text-xl font-bold text-green-600"><?php echo formatCurrency($userStats['sales']['total_revenue'] ?? 0); ?></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-blue-50 rounded-lg">
                    <span class="text-gray-600">Tickets Sold</span>
                    <span
                        class="text-xl font-bold text-blue-600"><?php echo $userStats['sales']['total_tickets_sold']; ?></span>
                </div>
                <div class="flex justify-between items-center p-3 bg-purple-50 rounded-lg">
                    <span class="text-gray-600">Events with Sales</span>
                    <span
                        class="text-xl font-bold text-purple-600"><?php echo $userStats['sales']['events_with_sales']; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Events -->
    <?php if (!empty($userStats['recent_events'])): ?>
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Recent Events</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">
                            Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">
                            Tickets</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($userStats['recent_events'] as $event): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900 truncate max-w-48">
                                <?php echo htmlspecialchars($event['title']); ?>
                            </div>
                            <div class="text-xs text-gray-500 sm:hidden">
                                <?php echo formatDate($event['start_date']); ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 hidden sm:table-cell">
                            <div class="text-sm text-gray-900"><?php echo formatDate($event['start_date']); ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php 
                                            switch($event['status']) {
                                                case 'active':
                                                    echo 'bg-green-100 text-green-800';
                                                    break;
                                                case 'completed':
                                                    echo 'bg-blue-100 text-blue-800';
                                                    break;
                                                case 'canceled':
                                                    echo 'bg-red-100 text-red-800';
                                                    break;
                                                default:
                                                    echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                <?php echo ucfirst($event['status']); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <div class="text-sm text-gray-900">
                                <?php echo ($event['total_tickets'] - $event['available_tickets']); ?> /
                                <?php echo $event['total_tickets']; ?>
                            </div>
                            <div class="text-xs text-gray-500">Sold / Total</div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php elseif ($user['role'] === 'customer' && !empty($userStats['tickets'])): ?>
    <!-- Customer Statistics -->
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Purchase Statistics</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <div class="text-2xl font-bold text-blue-600"><?php echo $userStats['tickets']['total_tickets']; ?>
                </div>
                <div class="text-sm text-gray-600">Total Tickets</div>
            </div>
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <div class="text-2xl font-bold text-green-600">
                    <?php echo formatCurrency($userStats['tickets']['total_spent'] ?? 0); ?></div>
                <div class="text-sm text-gray-600">Total Spent</div>
            </div>
            <div class="text-center p-4 bg-purple-50 rounded-lg">
                <div class="text-2xl font-bold text-purple-600"><?php echo $userStats['tickets']['events_attended']; ?>
                </div>
                <div class="text-sm text-gray-600">Events Attended</div>
            </div>
        </div>
    </div>

    <!-- Recent Ticket Purchases -->
    <?php if (!empty($userStats['recent_tickets'])): ?>
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Recent Ticket Purchases</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">
                            Venue</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">
                            Purchase Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($userStats['recent_tickets'] as $ticket): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900 truncate max-w-48">
                                <?php echo htmlspecialchars($ticket['event_title']); ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php echo formatDate($ticket['start_date']); ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 hidden sm:table-cell">
                            <div class="text-sm text-gray-900 truncate max-w-32">
                                <?php echo htmlspecialchars($ticket['venue']); ?>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">
                                <?php echo formatCurrency($ticket['purchase_price']); ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <div class="text-sm text-gray-900">
                                <?php echo formatDateTime($ticket['created_at']); ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php elseif ($user['role'] === 'agent' && !empty($userStats['scans'])): ?>
    <!-- Agent Statistics -->
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Agent Statistics</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="text-center p-4 bg-blue-50 rounded-lg">
                <div class="text-2xl font-bold text-blue-600"><?php echo $userStats['scans']['total_scans']; ?></div>
                <div class="text-sm text-gray-600">Total Scans</div>
            </div>
            <div class="text-center p-4 bg-green-50 rounded-lg">
                <div class="text-2xl font-bold text-green-600">
                    <?php echo $userStats['scans']['unique_tickets_scanned']; ?></div>
                <div class="text-sm text-gray-600">Unique Tickets</div>
            </div>
            <div class="text-center p-4 bg-purple-50 rounded-lg">
                <div class="text-2xl font-bold text-purple-600"><?php echo $userStats['scans']['active_days']; ?></div>
                <div class="text-sm text-gray-600">Active Days</div>
            </div>
        </div>
    </div>

    <!-- Recent Scans -->
    <?php if (!empty($userStats['recent_scans'])): ?>
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Recent Ticket Scans</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">
                            Ticket ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">
                            Scan Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($userStats['recent_scans'] as $scan): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900 truncate max-w-48">
                                <?php echo htmlspecialchars($scan['event_title']); ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 hidden sm:table-cell">
                            <div class="text-sm text-gray-900">
                                #<?php echo $scan['ticket_id']; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php 
                                            switch($scan['status']) {
                                                case 'valid':
                                                    echo 'bg-green-100 text-green-800';
                                                    break;
                                                case 'invalid':
                                                    echo 'bg-red-100 text-red-800';
                                                    break;
                                                case 'duplicate':
                                                    echo 'bg-yellow-100 text-yellow-800';
                                                    break;
                                                default:
                                                    echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                <?php echo ucfirst($scan['status']); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <div class="text-sm text-gray-900">
                                <?php echo formatDateTime($scan['scan_time']); ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Transaction History -->
    <?php if (!empty($transactions)): ?>
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
        <h3 class="text-lg font-semibold mb-4">Recent Transactions</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">
                            Status</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">
                            Description</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php 
                                        switch($transaction['type']) {
                                            case 'deposit':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'withdrawal':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            case 'purchase':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'sale':
                                                echo 'bg-purple-100 text-purple-800';
                                                break;
                                            case 'system_fee':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div
                                class="text-sm font-medium 
                                        <?php echo in_array($transaction['type'], ['deposit', 'sale']) ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo in_array($transaction['type'], ['deposit', 'sale']) ? '+' : '-'; ?>
                                <?php echo formatCurrency($transaction['amount']); ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 hidden sm:table-cell">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php 
                                        switch($transaction['status']) {
                                            case 'completed':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'pending':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'failed':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                <?php echo ucfirst($transaction['status']); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <div class="text-sm text-gray-900 truncate max-w-48">
                                <?php echo htmlspecialchars($transaction['description'] ?? 'N/A'); ?>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm text-gray-900">
                                <?php echo formatDateTime($transaction['created_at']); ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- View All Transactions Link -->
        <div class="mt-4 text-center">
            <a href="transactions.php?user_id=<?php echo $user['id']; ?>"
                class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                View All Transactions →
            </a>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
        <h3 class="text-lg font-semibold mb-4">Transaction History</h3>
        <div class="text-center py-8">
            <i class="fas fa-receipt text-4xl text-gray-300 mb-4"></i>
            <p class="text-gray-500">No transactions found for this user</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/admin_footer.php'; ?>