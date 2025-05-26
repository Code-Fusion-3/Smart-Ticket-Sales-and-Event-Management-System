<?php
$pageTitle = "Event Details";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($eventId <= 0) {
    $_SESSION['error_message'] = "Invalid event ID";
    redirect('index.php');
}

// Get event details
$sql = "SELECT 
            e.*,
            u.username as planner_name,
            u.email as planner_email,
            u.phone_number as planner_phone,
            u.id as planner_id
        FROM events e
        JOIN users u ON e.planner_id = u.id
        WHERE e.id = $eventId";
$event = $db->fetchOne($sql);

if (!$event) {
    $_SESSION['error_message'] = "Event not found";
    redirect('index.php');
}

// Get ticket types
$sql = "SELECT * FROM ticket_types WHERE event_id = $eventId ORDER BY price ASC";
$ticketTypes = $db->fetchAll($sql);

// Get ticket sales statistics
$sql = "SELECT 
            COUNT(*) as total_sold,
            SUM(purchase_price) as total_revenue,
            COUNT(DISTINCT user_id) as unique_buyers
        FROM tickets 
        WHERE event_id = $eventId AND status = 'sold'";
$salesStats = $db->fetchOne($sql);

// Get recent ticket sales
$sql = "SELECT 
            t.id,
            t.purchase_price,
            t.created_at,
            u.username as buyer_name,
            u.email as buyer_email,
            tt.name as ticket_type
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
        WHERE t.event_id = $eventId AND t.status = 'sold'
        ORDER BY t.created_at DESC
        LIMIT 10";
$recentSales = $db->fetchAll($sql);

include '../../includes/admin_header.php';
?>

<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Event Details</h1>
            <p class="text-gray-600 mt-2 text-sm sm:text-base">Detailed information and moderation tools</p>
        </div>
        <div class="flex gap-2">
            <a href="index.php"
                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm sm:text-base">
                <i class="fas fa-arrow-left mr-2"></i>Back to Events
            </a>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 alert-auto-hide">
        <i
            class="fas fa-check-circle mr-2"></i><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 alert-auto-hide">
        <i
            class="fas fa-exclamation-circle mr-2"></i><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['info_message'])): ?>
    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4 alert-auto-hide">
        <i
            class="fas fa-info-circle mr-2"></i><?php echo $_SESSION['info_message']; unset($_SESSION['info_message']); ?>
    </div>
    <?php endif; ?>

    <!-- Event Information Card -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="relative">
            <?php if ($event['image']): ?>
            <img class="w-full h-64 object-cover"
                src="<?php echo SITE_URL; ?>/uploads/events/<?php echo $event['image']; ?>" alt="Event Image">
            <?php else: ?>
            <div class="w-full h-64 bg-gray-200 flex items-center justify-center">
                <i class="fas fa-calendar-alt text-6xl text-gray-400"></i>
            </div>
            <?php endif; ?>

            <div class="absolute top-0 right-0 m-4">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                    <?php 
                    switch($event['status']) {
                        case 'active':
                            echo 'bg-green-100 text-green-800';
                            break;
                        case 'suspended':
                            echo 'bg-red-100 text-red-800';
                            break;
                        case 'completed':
                            echo 'bg-blue-100 text-blue-800';
                            break;
                        case 'canceled':
                            echo 'bg-yellow-100 text-yellow-800';
                            break;
                        default:
                            echo 'bg-gray-100 text-gray-800';
                    }
                    ?>">
                    <?php echo ucfirst($event['status']); ?>
                </span>
            </div>
        </div>

        <div class="p-4 sm:p-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Event Details -->
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mb-4">
                        <?php echo htmlspecialchars($event['title']); ?></h2>

                    <div class="space-y-3">
                        <div class="flex items-start">
                            <i class="fas fa-tag text-gray-400 mt-1 mr-3"></i>
                            <div>
                                <span class="text-sm text-gray-500">Category</span>
                                <div class="font-medium"><?php echo htmlspecialchars($event['category']); ?></div>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <i class="fas fa-map-marker-alt text-gray-400 mt-1 mr-3"></i>
                            <div>
                                <span class="text-sm text-gray-500">Venue</span>
                                <div class="font-medium"><?php echo htmlspecialchars($event['venue']); ?></div>
                                <div class="text-sm text-gray-600">
                                    <?php echo htmlspecialchars($event['address']); ?>,
                                    <?php echo htmlspecialchars($event['city']); ?>,
                                    <?php echo htmlspecialchars($event['country']); ?>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <i class="fas fa-calendar text-gray-400 mt-1 mr-3"></i>
                            <div>
                                <span class="text-sm text-gray-500">Date & Time</span>
                                <div class="font-medium">
                                    <?php echo formatDate($event['start_date']); ?> -
                                    <?php echo formatDate($event['end_date']); ?>
                                </div>
                                <div class="text-sm text-gray-600">
                                    <?php echo formatTime($event['start_time']); ?> -
                                    <?php echo formatTime($event['end_time']); ?>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <i class="fas fa-ticket-alt text-gray-400 mt-1 mr-3"></i>
                            <div>
                                <span class="text-sm text-gray-500">Tickets</span>
                                <div class="font-medium">
                                    <?php echo ($event['total_tickets'] - $event['available_tickets']); ?> sold /
                                    <?php echo $event['total_tickets']; ?> total
                                </div>
                                <div class="text-sm text-gray-600">
                                    <?php echo $event['available_tickets']; ?> remaining
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($event['description']): ?>
                    <div class="mt-6">
                        <h3 class="text-lg font-semibold mb-2">Description</h3>
                        <p class="text-gray-700 leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Event Planner & Actions -->
                <div>
                    <!-- Event Planner Info -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <h3 class="text-lg font-semibold mb-3">Event Planner</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Name:</span>
                                <span class="font-medium"><?php echo htmlspecialchars($event['planner_name']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Email:</span>
                                <span
                                    class="font-medium"><?php echo htmlspecialchars($event['planner_email']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Phone:</span>
                                <span
                                    class="font-medium"><?php echo htmlspecialchars($event['planner_phone']); ?></span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="../users/view.php?id=<?php echo $event['planner_id']; ?>"
                                class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                                View Planner Profile →
                            </a>
                        </div>
                    </div>

                    <!-- Event Actions -->
                    <div class="space-y-2">
                        <?php 
                            // Create a clean return URL
                            $currentPage = basename($_SERVER['PHP_SELF']);
                            $queryString = $_SERVER['QUERY_STRING'];
                            $returnUrl = $currentPage . (!empty($queryString) ? '?' . $queryString : '');
                            ?>

                        <?php if ($event['status'] !== 'active'): ?>
                        <a href="update_status.php?event_id=<?php echo $eventId; ?>&status=active&return=<?php echo urlencode($returnUrl); ?>"
                            class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 block text-center"
                            onclick="return confirm('Activate this event?')">
                            <i class="fas fa-check mr-2"></i>Activate Event
                        </a>
                        <?php endif; ?>

                        <?php if ($event['status'] !== 'suspended'): ?>
                        <a href="update_status.php?event_id=<?php echo $eventId; ?>&status=suspended&return=<?php echo urlencode($returnUrl); ?>"
                            class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 block text-center"
                            onclick="return confirm('Suspend this event? This will prevent new ticket sales.')">
                            <i class="fas fa-ban mr-2"></i>Suspend Event
                        </a>
                        <?php endif; ?>

                        <?php if ($event['status'] !== 'completed'): ?>
                        <a href="update_status.php?event_id=<?php echo $eventId; ?>&status=completed&return=<?php echo urlencode($returnUrl); ?>"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 block text-center"
                            onclick="return confirm('Mark this event as completed?')">
                            <i class="fas fa-check-circle mr-2"></i>Mark Complete
                        </a>
                        <?php endif; ?>

                        <?php if ($event['status'] !== 'canceled'): ?>
                        <a href="update_status.php?event_id=<?php echo $eventId; ?>&status=canceled&return=<?php echo urlencode($returnUrl); ?>"
                            class="w-full bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 block text-center"
                            onclick="return confirm('Cancel this event? This action cannot be undone.')">
                            <i class="fas fa-times-circle mr-2"></i>Cancel Event
                        </a>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Ticket Types -->
    <?php if (!empty($ticketTypes)): ?>
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <h3 class="text-lg font-semibold mb-4">Ticket Types</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($ticketTypes as $ticketType): ?>
            <div class="border border-gray-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900"><?php echo htmlspecialchars($ticketType['name']); ?></h4>
                <?php if ($ticketType['description']): ?>
                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($ticketType['description']); ?></p>
                <?php endif; ?>
                <div class="mt-3">
                    <div class="text-lg font-bold text-indigo-600"><?php echo formatCurrency($ticketType['price']); ?>
                    </div>
                    <div class="text-sm text-gray-500">
                        <?php echo ($ticketType['total_tickets'] - $ticketType['available_tickets']); ?> sold /
                        <?php echo $ticketType['total_tickets']; ?> total
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Sales Statistics -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-600"><?php echo $salesStats['total_sold']; ?></div>
                <div class="text-sm text-gray-500">Tickets Sold</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-green-600">
                    <?php echo formatCurrency($salesStats['total_revenue'] ?? 0); ?></div>
                <div class="text-sm text-gray-500">Total Revenue</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-purple-600"><?php echo $salesStats['unique_buyers']; ?></div>
                <div class="text-sm text-gray-500">Unique Buyers</div>
            </div>
        </div>
    </div>

    <!-- Recent Sales -->
    <?php if (!empty($recentSales)): ?>
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
        <h3 class="text-lg font-semibold mb-4">Recent Ticket Sales</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ticket ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Buyer</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">
                            Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">
                            Purchase Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($recentSales as $sale): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">#<?php echo $sale['id']; ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($sale['buyer_name']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($sale['buyer_email']); ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 hidden sm:table-cell">
                            <div class="text-sm text-gray-900">
                                <?php echo htmlspecialchars($sale['ticket_type'] ?? 'General'); ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">
                                <?php echo formatCurrency($sale['purchase_price']); ?></div>
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <div class="text-sm text-gray-900"><?php echo formatDateTime($sale['created_at']); ?></div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6">
        <h3 class="text-lg font-semibold mb-4">Recent Ticket Sales</h3>
        <div class="text-center py-8">
            <i class="fas fa-ticket-alt text-4xl text-gray-300 mb-4"></i>
            <p class="text-gray-500">No ticket sales yet</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/admin_footer.php'; ?>