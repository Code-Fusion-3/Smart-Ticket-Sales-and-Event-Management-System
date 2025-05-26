<?php
$pageTitle = "My Tickets";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = "my-tickets.php";
    redirect('login.php');
}

$userId = getCurrentUserId();

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query conditions
$conditions = ["t.user_id = $userId"];

if ($status !== 'all') {
    $conditions[] = "t.status = '" . $db->escape($status) . "'";
}

if (!empty($search)) {
    $conditions[] = "(e.title LIKE '%" . $db->escape($search) . "%' OR 
                      e.venue LIKE '%" . $db->escape($search) . "%' OR 
                      t.recipient_name LIKE '%" . $db->escape($search) . "%')";
}

$whereClause = "WHERE " . implode(" AND ", $conditions);

// Get total count
$countSql = "SELECT COUNT(*) as total 
             FROM tickets t 
             JOIN events e ON t.event_id = e.id 
             $whereClause";
$countResult = $db->fetchOne($countSql);
$totalTickets = $countResult['total'];
$totalPages = ceil($totalTickets / $perPage);

// Get tickets
$sql = "SELECT t.id, t.event_id, t.ticket_type_id, t.recipient_name, t.recipient_email, 
               t.recipient_phone, t.qr_code, t.purchase_price, t.status, t.created_at,
               e.title as event_title, e.venue, e.city, e.start_date, e.start_time, e.end_date,
               e.image, tt.name as ticket_type_name
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
        $whereClause
        ORDER BY e.start_date ASC, t.id DESC
        LIMIT $offset, $perPage";
$tickets = $db->fetchAll($sql);

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">My Tickets</h1>
        <a href="events.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition duration-300">
            <i class="fas fa-search mr-2"></i> Browse Events
        </a>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-auto flex-grow">
                <label for="search" class="block text-gray-700 font-bold mb-2">Search</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                       placeholder="Search by event, venue, recipient...">
            </div>
            
            <div class="w-full md:w-auto">
                <label for="status" class="block text-gray-700 font-bold mb-2">Status</label>
                <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                    <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>All Tickets</option>
                    <option value="sold" <?php echo $status == 'sold' ? 'selected' : ''; ?>>Valid</option>
                    <option value="used" <?php echo $status == 'used' ? 'selected' : ''; ?>>Used</option>
                    <option value="reselling" <?php echo $status == 'reselling' ? 'selected' : ''; ?>>Reselling</option>
                    <option value="resold" <?php echo $status == 'resold' ? 'selected' : ''; ?>>Resold</option>
                </select>
            </div>
            
            <div>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
            </div>
            
            <?php if (!empty($search) || $status != 'all'): ?>
                <div>
                    <a href="?status=all" class="text-indigo-600 hover:text-indigo-800">
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
                <div class="inline-block p-4 rounded-full bg-gray-100 mb-4">
                    <i class="fas fa-ticket-alt text-4xl text-gray-400"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No tickets found</h3>
                <p class="text-gray-500 mb-4">
                    <?php if (!empty($search) || $status != 'all'): ?>
                        Try adjusting your search filters or browse all tickets.
                    <?php else: ?>
                        You haven't purchased any tickets yet. Browse events to find something you like!
                    <?php endif; ?>
                </p>
                <a href="events.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                    Browse Events
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
                <?php foreach ($tickets as $ticket): ?>
                    <?php
                    // Determine if event is upcoming, ongoing, or past
                    $now = time();
                    $startDate = strtotime($ticket['start_date'] . ' ' . $ticket['start_time']);
                    $endDate = strtotime($ticket['end_date'] . ' 23:59:59');
                    
                    $eventStatus = 'upcoming';
                    if ($now > $endDate) {
                        $eventStatus = 'past';
                    } elseif ($now >= $startDate && $now <= $endDate) {
                        $eventStatus = 'ongoing';
                    }
                    
                    // Set status classes
                    $statusClasses = [
                        'sold' => 'bg-green-100 text-green-800',
                        'used' => 'bg-blue-100 text-blue-800',
                        'reselling' => 'bg-yellow-100 text-yellow-800',
                        'resold' => 'bg-purple-100 text-purple-800'
                    ];
                    $statusClass = $statusClasses[$ticket['status']] ?? 'bg-gray-100 text-gray-800';
                    
                    // Set event status classes
                    $eventStatusClasses = [
                        'upcoming' => 'bg-indigo-100 text-indigo-800',
                        'ongoing' => 'bg-green-100 text-green-800',
                        'past' => 'bg-gray-100 text-gray-600'
                    ];
                    $eventStatusClass = $eventStatusClasses[$eventStatus];
                    ?>
                    
                    <div class="bg-white border rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                        <div class="h-40 bg-gray-200 relative">
                            <?php if (!empty($ticket['image'])): ?>
                                <img src="<?php echo substr($ticket['image'], strpos($ticket['image'], 'uploads')); ?>" alt="<?php echo htmlspecialchars($ticket['event_title']); ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-gray-300">
                                    <i class="fas fa-calendar-alt text-4xl text-gray-500"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="absolute top-0 right-0 m-2">
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $eventStatusClass; ?>">
                                    <?php echo ucfirst($eventStatus); ?>
                                </span>
                            </div>
                            
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-4">
                                <h3 class="text-white font-semibold truncate">
                                    <?php echo htmlspecialchars($ticket['event_title']); ?>
                                </h3>
                            </div>
                        </div>
                        
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <div class="text-sm text-indigo-600 font-medium">
                                        <?php echo htmlspecialchars($ticket['ticket_type_name'] ?? 'Standard Ticket'); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Ticket #<?php echo $ticket['id']; ?>
                                    </div>
                                </div>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($ticket['status']); ?>
                                </span>
                            </div>
                            
                            <div class="text-sm text-gray-600 space-y-1 mb-3">
                                <div class="flex items-start">
                                    <i class="far fa-calendar-alt w-4 text-gray-400 mt-0.5 mr-1"></i>
                                    <span><?php echo formatDate($ticket['start_date']); ?> at <?php echo formatTime($ticket['start_time']); ?></span>
                                </div>
                                <div class="flex items-start">
                                    <i class="fas fa-map-marker-alt w-4 text-gray-400 mt-0.5 mr-1"></i>
                                    <span><?php echo htmlspecialchars($ticket['venue']); ?>, <?php echo htmlspecialchars($ticket['city']); ?></span>
                                </div>
                                <div class="flex items-start">
                                    <i class="far fa-user w-4 text-gray-400 mt-0.5 mr-1"></i>
                                    <span><?php echo htmlspecialchars($ticket['recipient_name']); ?></span>
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap gap-2 mt-4">
                                <a href="view-ticket.php?id=<?php echo $ticket['id']; ?>" class="text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-1 px-3 rounded transition duration-300">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                                
                                <a href="download-ticket.php?id=<?php echo $ticket['id']; ?>" class="text-xs bg-gray-600 hover:bg-gray-700 text-white font-bold py-1 px-3 rounded transition duration-300">
                                    <i class="fas fa-download mr-1"></i> Download
                                </a>
                                
                                <?php if ($ticket['status'] === 'sold' && $eventStatus !== 'past'): ?>
                                    <a href="resell-ticket.php?id=<?php echo $ticket['id']; ?>" class="text-xs bg-green-600 hover:bg-green-700 text-white font-bold py-1 px-3 rounded transition duration-300">
                                        <i class="fas fa-exchange-alt mr-1"></i> Resell
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                    Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>" 
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
                                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>" 
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
                                        echo '<a href="?page=1&status=' . $status . '&search=' . urlencode($search) . '" 
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
                                            
                                        echo '<a href="?page=' . $i . '&status=' . $status . '&search=' . urlencode($search) . '" 
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
                                        
                                        echo '<a href="?page=' . $totalPages . '&status=' . $status . '&search=' . urlencode($search) . '" 
                                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                ' . $totalPages . '
                                              </a>';
                                    }
                                    ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>" 
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
</div>

<?php include 'includes/footer.php'; ?>
