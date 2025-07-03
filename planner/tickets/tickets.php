<?php
$pageTitle = "Manage Tickets";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has permission
checkPermission('event_planner');

// Get planner ID
$plannerId = getCurrentUserId();

// Get event ID if specified
$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

// Verify event belongs to this planner if event ID is provided
if ($eventId > 0) {
    $sql = "SELECT * FROM events WHERE id = $eventId AND planner_id = $plannerId";
    $event = $db->fetchOne($sql);
    
    if (!$event) {
        $_SESSION['error_message'] = "Event not found or you don't have permission to view it.";
        redirect('events.php');
    }
}

// Handle actions
$action = $_GET['action'] ?? 'list';

// List tickets
if ($action == 'list') {
    // Get filter parameters
    $status = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 20;
    $offset = ($page - 1) * $perPage;
    
    // Build query
    $whereClause = "WHERE e.planner_id = $plannerId";
    
    if ($eventId > 0) {
        $whereClause .= " AND t.event_id = $eventId";
    }
    
    if ($status != 'all') {
        $whereClause .= " AND t.status = '" . $db->escape($status) . "'";
    }
    
    if (!empty($search)) {
        $whereClause .= " AND (t.recipient_name LIKE '%" . $db->escape($search) . "%' OR 
                              t.recipient_email LIKE '%" . $db->escape($search) . "%' OR 
                              t.recipient_phone LIKE '%" . $db->escape($search) . "%' OR
                              u.username LIKE '%" . $db->escape($search) . "%' OR
                              u.email LIKE '%" . $db->escape($search) . "%' OR
                              e.title LIKE '%" . $db->escape($search) . "%')";
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total 
                 FROM tickets t
                 JOIN events e ON t.event_id = e.id
                 JOIN users u ON t.user_id = u.id
                 LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
                 $whereClause";
    $countResult = $db->fetchOne($countSql);
    $totalTickets = $countResult['total'];
    $totalPages = ceil($totalTickets / $perPage);
    
    // Get tickets
    $sql = "SELECT 
                t.*, 
                e.title as event_title, 
                e.start_date,
                u.username, 
                u.email as user_email,
                tt.name as ticket_type,
                tt.price as ticket_type_price
            FROM tickets t
            JOIN events e ON t.event_id = e.id
            JOIN users u ON t.user_id = u.id
            LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
            $whereClause
            ORDER BY t.created_at DESC
            LIMIT $offset, $perPage";
    $tickets = $db->fetchAll($sql);
    
    // Get events for filter dropdown
    $sql = "SELECT id, title FROM events WHERE planner_id = $plannerId ORDER BY start_date DESC";
    $events = $db->fetchAll($sql);
    
    include '../../includes/planner_header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">
            <?php if ($eventId > 0): ?>
            Tickets for "<?php echo htmlspecialchars($event['title']); ?>"
            <?php else: ?>
            Manage All Tickets
            <?php endif; ?>
        </h1>

        <?php if ($eventId > 0): ?>
        <a href="event-details.php?id=<?php echo $eventId; ?>"
            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-arrow-left mr-2"></i> Back to Event
        </a>
        <?php else: ?>
        <a href="events.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-arrow-left mr-2"></i> Back to Events
        </a>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="" class="flex flex-wrap items-end gap-4">
            <input type="hidden" name="action" value="list">

            <?php if ($eventId > 0): ?>
            <input type="hidden" name="event_id" value="<?php echo $eventId; ?>">
            <?php else: ?>
            <div class="w-full md:w-auto flex-grow">
                <label for="event_id" class="block text-gray-700 font-bold mb-2">Event</label>
                <select id="event_id" name="event_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                    <option value="">All Events</option>
                    <?php foreach ($events as $evt): ?>
                    <option value="<?php echo $evt['id']; ?>" <?php echo $eventId == $evt['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($evt['title']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <div class="w-full md:w-auto flex-grow">
                <label for="search" class="block text-gray-700 font-bold mb-2">Search</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                    placeholder="Search by name, email, phone...">
            </div>

            <div class="w-full md:w-auto">
                <label for="status" class="block text-gray-700 font-bold mb-2">Status</label>
                <select id="status" name="status"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                    <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="sold" <?php echo $status == 'sold' ? 'selected' : ''; ?>>Sold</option>
                    <option value="used" <?php echo $status == 'used' ? 'selected' : ''; ?>>Used</option>
                    <option value="reselling" <?php echo $status == 'reselling' ? 'selected' : ''; ?>>Reselling</option>
                    <option value="resold" <?php echo $status == 'resold' ? 'selected' : ''; ?>>Resold</option>
                </select>
            </div>

            <div>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
            </div>

            <?php if (!empty($search) || $status != 'all' || $eventId > 0): ?>
            <div>
                <a href="?action=list" class="text-indigo-600 hover:text-indigo-800">
                    <i class="fas fa-times mr-1"></i> Clear Filters
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tickets List -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if (empty($tickets)): ?>
        <div class="p-6 text-center">
            <p class="text-gray-500">No tickets found matching your criteria.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead>
                    <tr>
                        <th
                            class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Ticket ID
                        </th>
                        <?php if ($eventId == 0): ?>
                        <th
                            class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Event
                        </th>
                        <?php endif; ?>
                        <th
                            class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Ticket Type
                        </th>
                        <th
                            class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Purchaser
                        </th>
                        <th
                            class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Recipient
                        </th>
                        <th
                            class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Price
                        </th>
                        <th
                            class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th
                            class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Purchase Date
                        </th>
                        <th
                            class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td class="py-4 px-4 border-b border-gray-200">
                            <div class="text-sm font-medium text-gray-900">
                                #<?php echo $ticket['id']; ?>
                            </div>
                        </td>

                        <?php if ($eventId == 0): ?>
                        <td class="py-4 px-4 border-b border-gray-200">
                            <div class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($ticket['event_title']); ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php echo formatDate($ticket['start_date']); ?>
                            </div>
                        </td>
                        <?php endif; ?>

                        <td class="py-4 px-4 border-b border-gray-200">
                            <div class="text-sm text-gray-900">
                                <?php echo htmlspecialchars($ticket['ticket_type'] ?? 'Standard'); ?>
                            </div>
                        </td>

                        <td class="py-4 px-4 border-b border-gray-200">
                            <div class="text-sm text-gray-900">
                                <?php echo htmlspecialchars($ticket['username']); ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php echo htmlspecialchars($ticket['user_email']); ?>
                            </div>
                        </td>

                        <td class="py-4 px-4 border-b border-gray-200">
                            <div class="text-sm text-gray-900">
                                <?php echo htmlspecialchars($ticket['recipient_name'] ?: 'Same as purchaser'); ?>
                            </div>
                            <?php if (!empty($ticket['recipient_email']) || !empty($ticket['recipient_phone'])): ?>
                            <div class="text-xs text-gray-500">
                                <?php 
                                            $contact = [];
                                            if (!empty($ticket['recipient_email'])) $contact[] = $ticket['recipient_email'];
                                            if (!empty($ticket['recipient_phone'])) $contact[] = $ticket['recipient_phone'];
                                            echo htmlspecialchars(implode(' • ', $contact)); 
                                            ?>
                            </div>
                            <?php endif; ?>
                        </td>

                        <td class="py-4 px-4 border-b border-gray-200">
                            <div class="text-sm text-gray-900">
                                <?php echo formatCurrency($ticket['purchase_price']); ?>
                            </div>
                        </td>

                        <td class="py-4 px-4 border-b border-gray-200">
                            <?php
                                    $statusClasses = [
                                        'sold' => 'bg-green-100 text-green-800',
                                        'used' => 'bg-blue-100 text-blue-800',
                                        'reselling' => 'bg-yellow-100 text-yellow-800',
                                        'resold' => 'bg-purple-100 text-purple-800'
                                    ];
                                    $statusClass = $statusClasses[$ticket['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                            <span
                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                <?php echo ucfirst($ticket['status']); ?>
                            </span>
                        </td>

                        <td class="py-4 px-4 border-b border-gray-200">
                            <div class="text-sm text-gray-500">
                                <?php echo formatDate($ticket['created_at']); ?>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php echo formatTime($ticket['created_at']); ?>
                            </div>
                        </td>

                        <td class="py-4 px-4 border-b border-gray-200 text-sm">
                            <a href="?action=view&id=<?php echo $ticket['id']; ?><?php echo $eventId ? '&event_id=' . $eventId : ''; ?>"
                                class="text-indigo-600 hover:text-indigo-900 mr-3">
                                <i class="fas fa-eye"></i> View
                            </a>

                            <?php if ($ticket['status'] == 'sold'): ?>
                            <a href="?action=mark_used&id=<?php echo $ticket['id']; ?><?php echo $eventId ? '&event_id=' . $eventId : ''; ?>"
                                class="text-blue-600 hover:text-blue-900"
                                onclick="return confirm('Mark this ticket as used?')">
                                <i class="fas fa-check-circle"></i> Mark Used
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 sm:px-6">
            <div class="flex items-center justify-between">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                    <a href="?action=list<?php echo $eventId ? '&event_id=' . $eventId : ''; ?>&page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>"
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                    <a href="?action=list<?php echo $eventId ? '&event_id=' . $eventId : ''; ?>&page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>"
                        class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Next
                    </a>
                    <?php endif; ?>
                </div>

                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo ($offset + 1); ?></span> to
                            <span class="font-medium"><?php echo min($offset + $perPage, $totalTickets); ?></span> of
                            <span class="font-medium"><?php echo $totalTickets; ?></span> tickets
                        </p>
                    </div>

                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                            <a href="?action=list<?php echo $eventId ? '&event_id=' . $eventId : ''; ?>&page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>"
                                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Previous</span>
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>

                            <?php
                                    // Display page numbers
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    // Always show first page
                                    if ($startPage > 1) {
                                        echo '<a href="?action=list' . ($eventId ? '&event_id=' . $eventId : '') . '&page=1&status=' . $status . '&search=' . urlencode($search) . '" 
                                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                1
                                              </a>';
                                        
                                        if ($startPage > 2) {
                                            echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                                    ...
                                                  </span>';
                                        }
                                    }
                                    
                                    // Page numbers
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $isCurrentPage = $i === $page;
                                        $pageClass = $isCurrentPage 
                                            ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600' 
                                            : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50';
                                            
                                        echo '<a href="?action=list' . ($eventId ? '&event_id=' . $eventId : '') . '&page=' . $i . '&status=' . $status . '&search=' . urlencode($search) . '" 
                                               class="relative inline-flex items-center px-4 py-2 border text-sm font-medium ' . $pageClass . '">
                                                ' . $i . '
                                              </a>';
                                    }
                                    
                                    // Always show last page
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                                    ...
                                                  </span>';
                                        }
                                        
                                        echo '<a href="?action=list' . ($eventId ? '&event_id=' . $eventId : '') . '&page=' . $totalPages . '&status=' . $status . '&search=' . urlencode($search) . '" 
                                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                ' . $totalPages . '
                                              </a>';
                                    }
                                    ?>

                            <?php if ($page < $totalPages): ?>
                            <a href="?action=list<?php echo $eventId ? '&event_id=' . $eventId : ''; ?>&page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>"
                                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Next</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Export Options -->
    <?php if (!empty($tickets)): ?>
    <div class="mt-6 bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-bold mb-4">Export Options</h2>
        <div class="flex flex-wrap gap-4">
            <a href="export.php?type=csv<?php echo $eventId ? '&event_id=' . $eventId : ''; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>"
                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-file-csv mr-2"></i> Export to CSV
            </a>

            <a href="export.php?type=excel<?php echo $eventId ? '&event_id=' . $eventId : ''; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-file-excel mr-2"></i> Export to Excel
            </a>

            <a href="export.php?type=pdf<?php echo $eventId ? '&event_id=' . $eventId : ''; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>"
                class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-file-pdf mr-2"></i> Export to PDF
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php


} elseif ($action == 'view') {
    // View ticket details
    $ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($ticketId <= 0) {
        $_SESSION['error_message'] = "Invalid ticket ID.";
        redirect('tickets.php' . ($eventId ? '?event_id=' . $eventId : ''));
    }
    
    // Get ticket details
    $sql = "SELECT 
                t.*, 
                e.title as event_title, 
                e.venue,
                e.start_date,
                e.start_time,
                e.end_time,
                u.username, 
                u.email as user_email,
                u.phone_number as user_phone,
                tt.name as ticket_type,
                tt.description as ticket_type_description
            FROM tickets t
            JOIN events e ON t.event_id = e.id
            JOIN users u ON t.user_id = u.id
            LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
            WHERE t.id = $ticketId
            AND e.planner_id = $plannerId";
    $ticket = $db->fetchOne($sql);
    
    if (!$ticket) {
        $_SESSION['error_message'] = "Ticket not found or you don't have permission to view it.";
        redirect('tickets.php' . ($eventId ? '?event_id=' . $eventId : ''));
    }
    
    include '../../includes/planner_header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <a href="tickets.php<?php echo $eventId ? '?event_id=' . $eventId : ''; ?>"
                class="text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-arrow-left mr-2"></i> Back to Tickets
            </a>
            <h1 class="text-3xl font-bold mt-2">Ticket #<?php echo $ticketId; ?></h1>
        </div>

        <div class="flex space-x-2">
            <?php if ($ticket['status'] == 'sold'): ?>
            <a href="?action=mark_used&id=<?php echo $ticketId; ?><?php echo $eventId ? '&event_id=' . $eventId : ''; ?>"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                onclick="return confirm('Mark this ticket as used?')">
                <i class="fas fa-check-circle mr-2"></i> Mark as Used
            </a>
            <?php endif; ?>

            <a href="print-ticket.php?id=<?php echo $ticketId; ?>" target="_blank"
                class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-print mr-2"></i> Print Ticket
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Ticket Details -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Ticket Details</h2>
                </div>

                <div class="p-6">
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-2">Event Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">Event</p>
                                <p class="text-base font-medium"><?php echo htmlspecialchars($ticket['event_title']); ?>
                                </p>
                            </div>

                            <div>
                                <p class="text-sm text-gray-500">Venue</p>
                                <p class="text-base font-medium"><?php echo htmlspecialchars($ticket['venue']); ?></p>
                            </div>

                            <div>
                                <p class="text-sm text-gray-500">Date</p>
                                <p class="text-base font-medium"><?php echo formatDate($ticket['start_date']); ?></p>
                            </div>

                            <div>
                                <p class="text-sm text-gray-500">Time</p>
                                <p class="text-base font-medium"><?php echo formatTime($ticket['start_time']); ?> -
                                    <?php echo formatTime($ticket['end_time']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-2">Ticket Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">Ticket Type</p>
                                <p class="text-base font-medium">
                                    <?php echo htmlspecialchars($ticket['ticket_type'] ?? 'Standard'); ?></p>
                                <?php if (!empty($ticket['ticket_type_description'])): ?>
                                <p class="text-sm text-gray-500 mt-1">
                                    <?php echo htmlspecialchars($ticket['ticket_type_description']); ?></p>
                                <?php endif; ?>
                            </div>

                            <div>
                                <p class="text-sm text-gray-500">Purchase Price</p>
                                <p class="text-base font-medium">
                                    <?php echo formatCurrency($ticket['purchase_price']); ?></p>
                            </div>

                            <div>
                                <p class="text-sm text-gray-500">Status</p>
                                <?php
                                $statusClasses = [
                                    'sold' => 'bg-green-100 text-green-800',
                                    'used' => 'bg-blue-100 text-blue-800',
                                    'reselling' => 'bg-yellow-100 text-yellow-800',
                                    'resold' => 'bg-purple-100 text-purple-800'
                                ];
                                $statusClass = $statusClasses[$ticket['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                                <span
                                    class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($ticket['status']); ?>
                                </span>
                            </div>

                            <div>
                                <p class="text-sm text-gray-500">Purchase Date</p>
                                <p class="text-base font-medium"><?php echo formatDate($ticket['created_at']); ?> at
                                    <?php echo formatTime($ticket['created_at']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-semibold mb-2">Purchaser Information</h3>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-500">Name</p>
                                <p class="text-base font-medium"><?php echo htmlspecialchars($ticket['username']); ?>
                                </p>

                                <p class="text-sm text-gray-500 mt-2">Email</p>
                                <p class="text-base font-medium"><?php echo htmlspecialchars($ticket['user_email']); ?>
                                </p>

                                <?php if (!empty($ticket['user_phone'])): ?>
                                <p class="text-sm text-gray-500 mt-2">Phone</p>
                                <p class="text-base font-medium"><?php echo htmlspecialchars($ticket['user_phone']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold mb-2">Recipient Information</h3>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <?php if (!empty($ticket['recipient_name']) || !empty($ticket['recipient_email']) || !empty($ticket['recipient_phone'])): ?>
                                <?php if (!empty($ticket['recipient_name'])): ?>
                                <p class="text-sm text-gray-500">Name</p>
                                <p class="text-base font-medium">
                                    <?php echo htmlspecialchars($ticket['recipient_name']); ?></p>
                                <?php endif; ?>

                                <?php if (!empty($ticket['recipient_email'])): ?>
                                <p class="text-sm text-gray-500 mt-2">Email</p>
                                <p class="text-base font-medium">
                                    <?php echo htmlspecialchars($ticket['recipient_email']); ?></p>
                                <?php endif; ?>

                                <?php if (!empty($ticket['recipient_phone'])): ?>
                                <p class="text-sm text-gray-500 mt-2">Phone</p>
                                <p class="text-base font-medium">
                                    <?php echo htmlspecialchars($ticket['recipient_phone']); ?></p>
                                <?php endif; ?>
                                <?php else: ?>
                                <p class="text-gray-500">Same as purchaser</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- QR Code and Actions -->
        <div>
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Ticket QR Code</h2>
                </div>

                <div class="p-6 text-center">
                    <?php if (!empty($ticket['qr_code'])): ?>
                    <img src="<?php echo SITE_URL . '/' . $ticket['qr_code']; ?>" alt="Ticket QR Code"
                        class="mx-auto max-w-full h-auto">
                    <?php else: ?>
                    <div class="bg-gray-100 p-8 rounded-lg">
                        <i class="fas fa-qrcode text-gray-400 text-6xl mb-2"></i>
                        <p class="text-gray-500">QR code not generated</p>
                    </div>
                    <?php endif; ?>

                    <p class="text-sm text-gray-500 mt-2">
                        Scan this QR code at the event entrance for verification.
                    </p>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Actions</h2>
                </div>

                <div class="p-6">
                    <div class="space-y-3">
                        <a href="print-ticket.php?id=<?php echo $ticketId; ?>" target="_blank"
                            class="block w-full bg-white hover:bg-gray-50 text-gray-700 font-medium py-2 px-4 border border-gray-300 rounded-md text-center transition duration-150">
                            <i class="fas fa-print mr-2"></i> Print Ticket
                        </a>

                        <a href="email-ticket.php?id=<?php echo $ticketId; ?>"
                            class="block w-full bg-white hover:bg-gray-50 text-indigo-600 font-medium py-2 px-4 border border-indigo-600 rounded-md text-center transition duration-150">
                            <i class="fas fa-envelope mr-2"></i> Email Ticket
                        </a>

                        <?php if ($ticket['status'] == 'sold'): ?>
                        <a href="?action=mark_used&id=<?php echo $ticketId; ?><?php echo $eventId ? '&event_id=' . $eventId : ''; ?>"
                            class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md text-center transition duration-150"
                            onclick="return confirm('Mark this ticket as used?')">
                            <i class="fas fa-check-circle mr-2"></i> Mark as Used
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php


} elseif ($action == 'mark_used') {
    // Mark ticket as used
    $ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($ticketId <= 0) {
        $_SESSION['error_message'] = "Invalid ticket ID.";
        redirect('tickets.php' . ($eventId ? '?event_id=' . $eventId : ''));
    }
    
    // Verify ticket belongs to an event managed by this planner
    $sql = "SELECT t.id 
            FROM tickets t
            JOIN events e ON t.event_id = e.id
            WHERE t.id = $ticketId
            AND e.planner_id = $plannerId
            AND t.status = 'sold'";
    $ticket = $db->fetchOne($sql);
    
    if (!$ticket) {
        $_SESSION['error_message'] = "Ticket not found, already used, or you don't have permission to modify it.";
        redirect('tickets.php' . ($eventId ? '?event_id=' . $eventId : ''));
    }
    
    // Update ticket status
    $sql = "UPDATE tickets SET status = 'used', updated_at = NOW() WHERE id = $ticketId";
    $db->query($sql);
    
    $_SESSION['success_message'] = "Ticket #$ticketId has been marked as used.";
    
    // Redirect back
    if (isset($_GET['redirect']) && $_GET['redirect'] == 'view') {
        redirect('tickets.php?action=view&id=' . $ticketId . ($eventId ? '&event_id=' . $eventId : ''));
    } else {
        redirect('tickets.php' . ($eventId ? '?event_id=' . $eventId : ''));
    }
}
?>