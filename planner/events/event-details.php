<?php
// error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
$pageTitle = "Event Details";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has permission
checkPermission('event_planner');

// Get planner ID
$plannerId = getCurrentUserId();

// Get event ID
$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($eventId <= 0) {
    $_SESSION['error_message'] = "Invalid event ID.";
    redirect('events.php');
}

// Get event details
$sql = "SELECT * FROM events WHERE id = $eventId AND planner_id = $plannerId";
$event = $db->fetchOne($sql);

if (!$event) {
    $_SESSION['error_message'] = "Event not found or you don't have permission to view it.";
    redirect('events.php');
}

// Get ticket types
$sql = "SELECT * FROM ticket_types WHERE event_id = $eventId ORDER BY price ASC";
$ticketTypes = $db->fetchAll($sql);

// Get sales statistics
$sql = "SELECT 
            COUNT(*) as total_sold,
            SUM(t.purchase_price) as total_revenue
        FROM tickets t
        WHERE t.event_id = $eventId
        AND t.status = 'sold'";
$salesStats = $db->fetchOne($sql);

// Get recent ticket sales
$sql = "SELECT 
            t.id, t.purchase_price, t.created_at, t.status,
            t.recipient_name, t.recipient_email,
            u.username, u.email,
            tt.name as ticket_type
        FROM tickets t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
        WHERE t.event_id = $eventId
        ORDER BY t.created_at DESC
        LIMIT 10";
$recentSales = $db->fetchAll($sql);

// Get planner ID
$plannerId = getCurrentUserId();

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
    
    if ($eventId <= 0) {
        $_SESSION['error_message'] = "Invalid event ID.";
        redirect('planner/events/events.php');
    }
    
    // Verify event belongs to this planner
    $sql = "SELECT id FROM events WHERE id = $eventId AND planner_id = $plannerId";
    $event = $db->fetchOne($sql);
    
    if (!$event) {
        $_SESSION['error_message'] = "Event not found or you don't have permission to modify it.";
        redirect('planner/events/events.php');
    }
    
    // Process action
    switch ($action) {
        case 'suspend':
            $sql = "UPDATE events SET status = 'suspended', updated_at = NOW() WHERE id = $eventId AND planner_id = $plannerId";
            $db->query($sql);
            $_SESSION['success_message'] = "Event has been suspended. Ticket sales are now paused.";
            break;
            
        case 'activate':
            $sql = "UPDATE events SET status = 'active', updated_at = NOW() WHERE id = $eventId AND planner_id = $plannerId";
            $db->query($sql);
            $_SESSION['success_message'] = "Event has been activated. Ticket sales are now resumed.";
            break;
            
        case 'cancel':
            $sql = "UPDATE events SET status = 'canceled', updated_at = NOW() WHERE id = $eventId AND planner_id = $plannerId";
            $db->query($sql);
            $_SESSION['success_message'] = "Event has been canceled.";
            break;
            
        default:
            $_SESSION['error_message'] = "Invalid action.";
            break;
    }
    
    // Redirect back to event details
    redirect("event-details.php?id=$eventId");
} 

include '../../includes/planner_header.php';

?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <a href="events.php" class="text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-arrow-left mr-2"></i> Back to Events
            </a>
            <h1 class="text-3xl font-bold mt-2"><?php echo htmlspecialchars($event['title']); ?></h1>
        </div>
        <div class="flex space-x-2">
            <a href="events.php?action=edit&id=<?php echo $eventId; ?>"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-edit mr-2"></i> Edit Event
            </a>
            <a href="../tickets/tickets.php?event_id=<?php echo $eventId; ?>"
                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-ticket-alt mr-2"></i> Manage Tickets
            </a>
        </div>
    </div>

    <!-- Event Status Banner -->
    <?php
    $statusClasses = [
        'active' => 'bg-green-100 text-green-800 border-green-200',
        'completed' => 'bg-blue-100 text-blue-800 border-blue-200',
        'canceled' => 'bg-red-100 text-red-800 border-red-200',
        'suspended' => 'bg-yellow-100 text-yellow-800 border-yellow-200'
    ];
    $statusClass = $statusClasses[$event['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
    ?>
    <div class="<?php echo $statusClass; ?> border-l-4 p-4 mb-6 rounded-r">
        <div class="flex">
            <div class="flex-shrink-0">
                <?php if ($event['status'] == 'active'): ?>
                <i class="fas fa-check-circle text-green-600"></i>
                <?php elseif ($event['status'] == 'completed'): ?>
                <i class="fas fa-flag-checkered text-blue-600"></i>
                <?php elseif ($event['status'] == 'canceled'): ?>
                <i class="fas fa-times-circle text-red-600"></i>
                <?php elseif ($event['status'] == 'suspended'): ?>
                <i class="fas fa-pause-circle text-yellow-600"></i>
                <?php endif; ?>
            </div>
            <div class="ml-3">
                <p class="text-sm">
                    This event is currently <span class="font-medium"><?php echo ucfirst($event['status']); ?></span>
                    <?php if ($event['status'] == 'active'): ?>
                    and tickets are available for purchase.
                    <?php elseif ($event['status'] == 'completed'): ?>
                    and has already taken place.
                    <?php elseif ($event['status'] == 'canceled'): ?>
                    and tickets are no longer available.
                    <?php elseif ($event['status'] == 'suspended'): ?>
                    and ticket sales are temporarily paused.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Event Details -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="relative">
                    <?php if (!empty($event['image'])): ?>
                    <img src="<?php echo $event['image']; ?>" alt="<?php echo htmlspecialchars($event['title']); ?>"
                        class="w-full h-64 object-cover">
                    <?php else: ?>
                    <div class="w-full h-64 bg-gray-200 flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-6xl text-gray-400"></i>
                    </div>
                    <?php endif; ?>

                    <div class="absolute top-0 right-0 bg-indigo-600 text-white px-4 py-2 m-4 rounded-lg">
                        <div class="text-sm"><?php echo $event['category']; ?></div>
                    </div>
                </div>

                <div class="p-6">
                    <div class="mb-6">
                        <h2 class="text-xl font-bold mb-2">Event Details</h2>
                        <p class="text-gray-700 mb-4"><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <h3 class="text-lg font-semibold mb-2">Date & Time</h3>
                                <p class="flex items-center text-gray-700">
                                    <i class="far fa-calendar-alt mr-2 text-indigo-600"></i>
                                    <?php echo formatDate($event['start_date']); ?>
                                    <?php if ($event['start_date'] != $event['end_date']): ?>
                                    - <?php echo formatDate($event['end_date']); ?>
                                    <?php endif; ?>
                                </p>
                                <p class="flex items-center text-gray-700 mt-1">
                                    <i class="far fa-clock mr-2 text-indigo-600"></i>
                                    <?php echo formatTime($event['start_time']); ?> -
                                    <?php echo formatTime($event['end_time']); ?>
                                </p>
                            </div>

                            <div>
                                <h3 class="text-lg font-semibold mb-2">Location</h3>
                                <p class="flex items-center text-gray-700">
                                    <i class="fas fa-map-marker-alt mr-2 text-indigo-600"></i>
                                    <?php echo htmlspecialchars($event['venue']); ?>
                                </p>
                                <p class="text-gray-700 mt-1 ml-6">
                                    <?php echo htmlspecialchars($event['address']); ?><br>
                                    <?php echo htmlspecialchars($event['city']); ?>,
                                    <?php echo htmlspecialchars($event['country']); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h2 class="text-xl font-bold mb-2">Ticket Types</h2>
                        <?php if (empty($ticketTypes)): ?>
                        <p class="text-gray-500">No ticket types defined for this event.</p>
                        <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white">
                                <thead>
                                    <tr>
                                        <th
                                            class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Ticket Type
                                        </th>
                                        <th
                                            class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Price
                                        </th>
                                        <th
                                            class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Available
                                        </th>
                                        <th
                                            class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Sold
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ticketTypes as $type): 
                                            // Get tickets sold for this type
                                            $sql = "SELECT COUNT(*) as count FROM tickets WHERE ticket_type_id = " . $type['id'] . " AND status = 'sold'";
                                            $ticketCount = $db->fetchOne($sql);
                                            $soldTickets = $ticketCount['count'] ?? 0;
                                            $availableTickets = $type['available_tickets'];
                                            $totalTickets = $type['total_tickets'];
                                            $soldPercentage = ($totalTickets > 0) ? round(($soldTickets / $totalTickets) * 100) : 0;
                                        ?>
                                    <tr>
                                        <td class="py-2 px-4 border-b border-gray-200">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($type['name']); ?>
                                            </div>
                                            <?php if (!empty($type['description'])): ?>
                                            <div class="text-xs text-gray-500">
                                                <?php echo htmlspecialchars($type['description']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-2 px-4 border-b border-gray-200">
                                            <div class="text-sm text-gray-900">
                                                <?php echo formatCurrency($type['price']); ?>
                                            </div>
                                        </td>
                                        <td class="py-2 px-4 border-b border-gray-200">
                                            <div class="text-sm text-gray-900">
                                                <?php echo $availableTickets; ?> / <?php echo $totalTickets; ?>
                                            </div>
                                        </td>
                                        <td class="py-2 px-4 border-b border-gray-200">
                                            <div class="text-sm text-gray-900">
                                                <?php echo $soldTickets; ?>
                                                <span
                                                    class="text-xs text-gray-500">(<?php echo $soldPercentage; ?>%)</span>
                                            </div>
                                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                                <div class="bg-indigo-600 h-1.5 rounded-full"
                                                    style="width: <?php echo $soldPercentage; ?>%"></div>
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
            </div>
        </div>

        <!-- Sales Statistics -->
        <div>
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Sales Statistics</h2>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="bg-gray-50 p-4 rounded-lg text-center">
                            <div class="text-3xl font-bold text-indigo-600">
                                <?php echo $salesStats['total_sold'] ?? 0; ?>
                            </div>
                            <div class="text-sm text-gray-500">Tickets Sold</div>
                        </div>

                        <div class="bg-gray-50 p-4 rounded-lg text-center">
                            <div class="text-3xl font-bold text-green-600">
                                <?php echo formatCurrency($salesStats['total_revenue'] ?? 0); ?>
                            </div>
                            <div class="text-sm text-gray-500">Total Revenue</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="flex justify-between items-center mb-2">
                            <div class="text-sm font-medium text-gray-700">
                                Tickets Sold
                            </div>
                            <div class="text-sm text-gray-500">
                                <?php 
                                $totalTickets = $event['total_tickets'];
                                $soldTickets = $salesStats['total_sold'] ?? 0;
                                $percentage = ($totalTickets > 0) ? round(($soldTickets / $totalTickets) * 100) : 0;
                                echo $soldTickets . ' / ' . $totalTickets . ' (' . $percentage . '%)'; 
                                ?>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-indigo-600 h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%">
                            </div>
                        </div>
                    </div>