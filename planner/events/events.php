<?php
// error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = "Manage Events";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has permission
checkPermission('event_planner');

// Get planner ID
$plannerId = getCurrentUserId();

// Handle actions
$action = $_GET['action'] ?? 'list';

// List all events
if ($action == 'list') {
    // Get filter parameters
    $status = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;
    
    // Build query
    $whereClause = "WHERE planner_id = $plannerId";
    
    if ($status != 'all') {
        $whereClause .= " AND status = '" . $db->escape($status) . "'";
    }
    
    if (!empty($search)) {
        $whereClause .= " AND (title LIKE '%" . $db->escape($search) . "%' OR 
                              venue LIKE '%" . $db->escape($search) . "%' OR 
                              city LIKE '%" . $db->escape($search) . "%')";
    }
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM events $whereClause";
    $countResult = $db->fetchOne($countSql);
    $totalEvents = $countResult['total'];
    $totalPages = ceil($totalEvents / $perPage);
    
    // Get events
    $sql = "SELECT * FROM events $whereClause ORDER BY start_date DESC LIMIT $offset, $perPage";
    $events = $db->fetchAll($sql);
    
    include '../../includes/planner_header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Manage Events</h1>
        <a href="?action=create" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-plus mr-2"></i> Create New Event
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="" class="flex flex-wrap items-end gap-4">
            <input type="hidden" name="action" value="list">

            <div class="w-full md:w-auto flex-grow">
                <label for="search" class="block text-gray-700 font-bold mb-2">Search</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                    placeholder="Search by title, venue, city...">
            </div>

            <div class="w-full md:w-auto">
                <label for="status" class="block text-gray-700 font-bold mb-2">Status</label>
                <select id="status" name="status"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                    <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="canceled" <?php echo $status == 'canceled' ? 'selected' : ''; ?>>Canceled</option>
                    <option value="suspended" <?php echo $status == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>

            <div>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
            </div>

            <?php if (!empty($search) || $status != 'all'): ?>
            <div>
                <a href="?action=list" class="text-indigo-600 hover:text-indigo-800">
                    <i class="fas fa-times mr-1"></i> Clear Filters
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Events List -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if (empty($events)): ?>
        <div class="p-6 text-center">
            <p class="text-gray-500">No events found. Create your first event!</p>
            <a href="?action=create" class="mt-2 inline-block text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-plus mr-1"></i> Create Event
            </a>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead>
                    <tr>
                        <th
                            class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Event
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
                            Tickets
                        </th>
                        <th
                            class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th>
                        <th
                            class="py-3 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                    <tr>
                        <td class="py-4 px-4 border-b border-gray-200">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <?php if (!empty($event['image'])): ?>
                                    <img class="h-10 w-10 rounded-full object-cover"
                                        src="<?php echo $event['image']; ?>" alt="">
                                    <?php else: ?>
                                    <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                        <i class="fas fa-calendar-alt text-indigo-600"></i>
                                    </div>
                                    <?php endif; ?>
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
                                <?php echo $event['city']; ?>, <?php echo $event['country']; ?>
                            </div>
                        </td>
                        <td class="py-4 px-4 border-b border-gray-200">
                            <?php
                                // Get ticket types for this event
                                $sql = "SELECT * FROM ticket_types WHERE event_id = " . $event['id'] . " ORDER BY price ASC";
                                $ticketTypes = $db->fetchAll($sql);
                                
                                // Get total tickets sold for this event
                                $sql = "SELECT COUNT(*) as count,SUM(t.purchase_price) as revenue FROM tickets t 
                                 JOIN ticket_types tt ON t.ticket_type_id = tt.id WHERE tt.event_id = " . $event['id'] . " 
                                 AND t.status = 'sold'";
                                $ticketStats = $db->fetchOne($sql);
                                
                                $sold = $ticketStats['count'] ?? 0;
                                $revenue = $ticketStats['revenue'] ?? 0;
                                $total = $event['total_tickets'];
                                $percentage = ($total > 0) ? round(($sold / $total) * 100) : 0;
                                
                                // Get price range
                                $minPrice = !empty($ticketTypes) ? min(array_column($ticketTypes, 'price')) : 0;
                                $maxPrice = !empty($ticketTypes) ? max(array_column($ticketTypes, 'price')) : 0;
                                ?>
                            <div class="text-sm text-gray-900">
                                <?php echo $sold; ?> / <?php echo $total; ?> (<?php echo $percentage; ?>%)
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5 mb-1">
                                <div class="bg-indigo-600 h-2.5 rounded-full"
                                    style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <div class="text-xs text-gray-500">
                                <?php if (count($ticketTypes) > 0): ?>
                                <?php echo count($ticketTypes); ?> ticket types
                                <?php if ($minPrice == $maxPrice): ?>
                                • <?php echo formatCurrency($minPrice); ?>
                                <?php else: ?>
                                • <?php echo formatCurrency($minPrice); ?> - <?php echo formatCurrency($maxPrice); ?>
                                <?php endif; ?>
                                <?php else: ?>
                                No ticket types defined
                                <?php endif; ?>
                            </div>
                            <?php if ($revenue > 0): ?>
                            <div class="text-xs text-green-600 font-medium">
                                Revenue: <?php echo formatCurrency($revenue); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="py-4 px-4 border-b border-gray-200">
                            <?php
                                $statusClasses = [
                                    'active' => 'bg-green-100 text-green-800',
                                    'completed' => 'bg-blue-100 text-blue-800',
                                    'canceled' => 'bg-red-100 text-red-800',
                                    'suspended' => 'bg-yellow-100 text-yellow-800'
                                ];
                                $statusClass = $statusClasses[$event['status']] ?? 'bg-gray-100 text-gray-800';
                                ?>
                            <span
                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                <?php echo ucfirst($event['status']); ?>
                            </span>
                        </td>
                        <td class="py-4 px-4 border-b border-gray-200 text-sm">
                            <a href="?action=edit&id=<?php echo $event['id']; ?>"
                                class="text-indigo-600 hover:text-indigo-900 mr-3">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="event-details.php?id=<?php echo $event['id']; ?>"
                                class="text-blue-600 hover:text-blue-900 mr-3">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="tickets.php?event_id=<?php echo $event['id']; ?>"
                                class="text-green-600 hover:text-green-900">
                                <i class="fas fa-ticket-alt"></i> Tickets
                            </a>
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
                    <a href="?action=list&page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>"
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                    <a href="?action=list&page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>"
                        class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Next
                    </a>
                    <?php endif; ?>
                </div>

                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo ($offset + 1); ?></span> to
                            <span class="font-medium"><?php echo min($offset + $perPage, $totalEvents); ?></span> of
                            <span class="font-medium"><?php echo $totalEvents; ?></span> events
                        </p>
                    </div>

                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                            <a href="?action=list&page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Previous
                            </a>
                            <?php endif; ?>

                            <?php
                                    // Display page numbers
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    // Always show first page
                                    if ($startPage > 1) {
                                        echo '<a href="?action=list&page=1&status=' . $status . '&search=' . urlencode($search) . '" 
                                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                                1
                                              </a>';
                                        
                                        if ($startPage > 2) {
                                            echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium text-gray-700 bg-white">
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
                                            
                                        echo '<a href="?action=list&page=' . $i . '&status=' . $status . '&search=' . urlencode($search) . '" 
                                               class="relative inline-flex items-center px-4 py-2 border text-sm font-medium ' . $pageClass . '">
                                                ' . $i . '
                                              </a>';
                                    }
                                    
                                    // Always show last page
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium text-gray-700 bg-white">
                                                    ...
                                                  </span>';
                                        }
                                        
                                        echo '<a href="?action=list&page=' . $totalPages . '&status=' . $status . '&search=' . urlencode($search) . '" 
                                               class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                                ' . $totalPages . '
                                              </a>';
                                    }
                                    ?>

                            <?php if ($page < $totalPages): ?>
                            <a href="?action=list&page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Next
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

<?php
include '../../includes/footer.php';

} elseif ($action == 'create' || $action == 'edit') {
    // Get event data if editing
    $event = [];
    $eventId = 0;
    
    if ($action == 'edit' && isset($_GET['id'])) {
        $eventId = (int)$_GET['id'];
        $sql = "SELECT * FROM events WHERE id = $eventId AND planner_id = $plannerId";
        $event = $db->fetchOne($sql);
        
        if (!$event) {
            $_SESSION['error_message'] = "Event not found or you don't have permission to edit it.";
            redirect('planner/events/events.php');
        }
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // Get form data
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $category = $_POST['category'] ?? '';
        $venue = $_POST['venue'] ?? '';
        $address = $_POST['address'] ?? '';
        $city = $_POST['city'] ?? '';
        $country = $_POST['country'] ?? '';
        $startDate = $_POST['start_date'] ?? '';
        $endDate = $_POST['end_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $totalTickets = (int)($_POST['total_tickets'] ?? 0);
        $ticketPrice = (float)($_POST['ticket_price'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        
        // Validate form data
        $errors = [];
        
        if (empty($title)) {
            $errors[] = "Title is required";
        }
        
        if (empty($venue)) {
            $errors[] = "Venue is required";
        }
        
        if (empty($startDate)) {
            $errors[] = "Start date is required";
        }
        
        if (empty($endDate)) {
            $errors[] = "End date is required";
        }
        
        if (empty($startTime)) {
            $errors[] = "Start time is required";
        }
        
        if (empty($endTime)) {
            $errors[] = "End time is required";
        }
   // Validate ticket types
   $ticketTypes = $_POST['ticket_types'] ?? [];
    
   if (empty($ticketTypes)) {
       $errors[] = "At least one ticket type is required";
   } else {
       $totalTickets = 0;
       
       foreach ($ticketTypes as $index => $type) {
           if (empty($type['name'])) {
               $errors[] = "Ticket name is required for all ticket types";
               break;
           }
           
           if (!isset($type['price']) || $type['price'] < 0) {
               $errors[] = "Valid price is required for all ticket types";
               break;
           }
           
           if (!isset($type['quantity']) || $type['quantity'] < 1) {
               $errors[] = "Quantity must be at least 1 for all ticket types";
               break;
           }
           
           $totalTickets += (int)$type['quantity'];
       }
       
       if ($totalTickets < 0) {
           $errors[] = "Total tickets must be at least 1";
       }
   }
        
        // Handle image upload
        $imagePath = $event['image'] ?? '';
        
        if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
            $uploadDir = '../../uploads/events/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = time() . '_' . basename($_FILES['image']['name']);
            $targetFile = $uploadDir . $fileName;
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            
            // Check if image file is a actual image
            $check = getimagesize($_FILES['image']['tmp_name']);
            if ($check === false) {
                $errors[] = "File is not an image";
            }
            
            // Check file size (limit to 5MB)
            if ($_FILES['image']['size'] > 5000000) {
                $errors[] = "File is too large (max 5MB)";
            }
            
            // Allow certain file formats
            if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
                $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed";
            }
            
            // If no errors, upload file
            if (empty($errors)) {
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                    $imagePath = '../../uploads/events/' . $fileName;
                } else {
                    $errors[] = "Failed to upload image";
                }
            }
        }
        
        // If no errors, save event
        if (empty($errors)) {
            // Calculate available tickets
            $availableTickets = $totalTickets;
            
            if ($action == 'edit') {
                // If editing, get current tickets sold
                $sql = "SELECT COUNT(*) as count FROM tickets WHERE event_id = $eventId AND status = 'sold'";
                $ticketCount = $db->fetchOne($sql);
                $soldTickets = $ticketCount['count'] ?? 0;
                
                // Available tickets = total tickets - sold tickets
                $availableTickets = $totalTickets - $soldTickets;
                
                if ($availableTickets < 0) {
                    $errors[] = "Total tickets cannot be less than tickets already sold";
                }
            }
            
         // Inside the form submission handling code
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Validate ticket types
    $ticketTypes = $_POST['ticket_types'] ?? [];
    
    if (empty($ticketTypes)) {
        $errors[] = "At least one ticket type is required";
    } else {
        $totalTickets = 0;
        
        foreach ($ticketTypes as $index => $type) {
            if (empty($type['name'])) {
                $errors[] = "Ticket name is required for all ticket types";
                break;
            }
            
            if (!isset($type['price']) || $type['price'] < 0) {
                $errors[] = "Valid price is required for all ticket types";
                break;
            }
            
            if (!isset($type['quantity']) || $type['quantity'] < 1) {
                $errors[] = "Quantity must be at least 1 for all ticket types";
                break;
            }
            
            $totalTickets += (int)$type['quantity'];
        }
        
        // Store total tickets for the event
        $totalTickets = $totalTickets;
    }
    
    // If no errors, save event and ticket types
    if (empty($errors)) {
        // Begin transaction
        $db->query("START TRANSACTION");
        
        try {
            if ($action == 'create') {
                // Insert new event
                $sql = "INSERT INTO events (
                            planner_id, title, description, category, venue, address, city, country,
                            start_date, end_date, start_time, end_time, total_tickets, available_tickets,
                            ticket_price, image, status, created_at, updated_at
                        ) VALUES (
                            $plannerId, 
                            '" . $db->escape($title) . "', 
                            '" . $db->escape($description) . "', 
                            '" . $db->escape($category) . "', 
                            '" . $db->escape($venue) . "', 
                            '" . $db->escape($address) . "', 
                            '" . $db->escape($city) . "', 
                            '" . $db->escape($country) . "', 
                            '" . $db->escape($startDate) . "', 
                            '" . $db->escape($endDate) . "', 
                            '" . $db->escape($startTime) . "', 
                            '" . $db->escape($endTime) . "', 
                            $totalTickets, 
                            $totalTickets, 
                            0, 
                            '" . $db->escape($imagePath) . "', 
                            '" . $db->escape($status) . "',
                            NOW(),
                            NOW()
                        )";
                
                // $db->query($sql);
                $eventId = $db->insert($sql);
                
                // Insert ticket types
                foreach ($ticketTypes as $type) {
                    $typeName = $db->escape($type['name']);
                    $typeDesc = $db->escape($type['description'] ?? '');
                    $typePrice = (float)$type['price'];
                    $typeQuantity = (int)$type['quantity'];
                    
                    $sql = "INSERT INTO ticket_types (
                                event_id, name, description, price, total_tickets, available_tickets, created_at, updated_at
                            ) VALUES (
                                $eventId,
                                '$typeName',
                                '$typeDesc',
                                $typePrice,
                                $typeQuantity,
                                $typeQuantity,
                                NOW(),
                                NOW()
                            )";
                    
                    $db->query($sql);
                }
                
                $_SESSION['success_message'] = "Event created successfully with " . count($ticketTypes) . " ticket types";
            } else {
                // Update existing event
                $sql = "UPDATE events SET 
                            title = '" . $db->escape($title) . "', 
                            description = '" . $db->escape($description) . "', 
                            category = '" . $db->escape($category) . "', 
                            venue = '" . $db->escape($venue) . "', 
                            address = '" . $db->escape($address) . "', 
                            city = '" . $db->escape($city) . "', 
                            country = '" . $db->escape($country) . "', 
                            start_date = '" . $db->escape($startDate) . "', 
                            end_date = '" . $db->escape($endDate) . "', 
                            start_time = '" . $db->escape($startTime) . "', 
                            end_time = '" . $db->escape($endTime) . "', 
                            total_tickets = $totalTickets, 
                            status = '" . $db->escape($status) . "',
                            updated_at = NOW()";
                
                if (!empty($imagePath)) {
                    $sql .= ", image = '" . $db->escape($imagePath) . "'";
                }
                
                $sql .= " WHERE id = $eventId AND planner_id = $plannerId";
                
                $db->query($sql);
                
                // Handle ticket types for existing event
                // First, get existing ticket types to check if any tickets have been sold
                $sql = "SELECT tt.id, tt.name, tt.total_tickets, 
                               (SELECT COUNT(*) FROM tickets t WHERE t.ticket_type_id = tt.id AND t.status = 'sold') as sold_tickets
                        FROM ticket_types tt
                        WHERE tt.event_id = $eventId";
                $existingTypes = $db->fetchAll($sql);
                
                // Create a map of existing types by ID
                $existingTypesMap = [];
                foreach ($existingTypes as $type) {
                    $existingTypesMap[$type['id']] = $type;
                }
                
                // Track which types we're keeping
                $keepTypeIds = [];
                
                // Insert/update ticket types
                foreach ($ticketTypes as $type) {
                    $typeId = isset($type['id']) ? (int)$type['id'] : 0;
                    $typeName = $db->escape($type['name']);
                    $typeDesc = $db->escape($type['description'] ?? '');
                    $typePrice = (float)$type['price'];
                    $typeQuantity = (int)$type['quantity'];
                    
                    if ($typeId > 0 && isset($existingTypesMap[$typeId])) {
                        // This is an existing type - update it
                        $soldTickets = $existingTypesMap[$typeId]['sold_tickets'];
                        
                        // Ensure we're not reducing below sold tickets
                        if ($typeQuantity < $soldTickets) {
                            throw new Exception("Cannot reduce ticket quantity below sold amount for {$type['name']}");
                        }
                        
                        $availableTickets = $typeQuantity - $soldTickets;
                        
                        $sql = "UPDATE ticket_types SET 
                        name = '$typeName',
                        description = '$typeDesc',
                        price = $typePrice,
                        total_tickets = $typeQuantity,
                        available_tickets = $availableTickets,
                        updated_at = NOW()
                    WHERE id = $typeId AND event_id = $eventId";
            
            $db->query($sql);
            $keepTypeIds[] = $typeId;
        } else {
            // This is a new type - insert it
            $sql = "INSERT INTO ticket_types (
                        event_id, name, description, price, total_tickets, available_tickets, created_at, updated_at
                    ) VALUES (
                        $eventId,
                        '$typeName',
                        '$typeDesc',
                        $typePrice,
                        $typeQuantity,
                        $typeQuantity,
                        NOW(),
                        NOW()
                    )";
            
            // $db->query($sql);
            $keepTypeIds[] = $db->insert($sql);
        }
    }
    
    // Delete any ticket types that weren't in the form (if they have no sold tickets)
    if (!empty($existingTypesMap)) {
        $deleteTypeIds = [];
        
        foreach ($existingTypesMap as $id => $type) {
            if (!in_array($id, $keepTypeIds)) {
                // Check if this type has sold tickets
                if ($type['sold_tickets'] > 0) {
                    throw new Exception("Cannot delete ticket type '{$type['name']}' because it has sold tickets");
                }
                
                $deleteTypeIds[] = $id;
            }
        }
        
        if (!empty($deleteTypeIds)) {
            $deleteIdsStr = implode(',', $deleteTypeIds);
            $sql = "DELETE FROM ticket_types WHERE id IN ($deleteIdsStr) AND event_id = $eventId";
            $db->query($sql);
        }
    }
    
    // Update available tickets in the events table
    $sql = "UPDATE events SET 
                available_tickets = (SELECT SUM(available_tickets) FROM ticket_types WHERE event_id = $eventId),
                updated_at = NOW()
            WHERE id = $eventId";
    $db->query($sql);
    
    $_SESSION['success_message'] = "Event updated successfully with " . count($ticketTypes) . " ticket types";
}

// Commit transaction
$db->query("COMMIT");
redirect('planner/events/events.php');
} catch (Exception $e) {
// Rollback transaction on error
$db->query("ROLLBACK");
$errors[] = $e->getMessage();
}
}
}

// If editing, load existing ticket types
$ticketTypes = [];
if ($action == 'edit' && isset($event['id'])) {
$sql = "SELECT * FROM ticket_types WHERE event_id = " . $event['id'] . " ORDER BY price ASC";
$ticketTypes = $db->fetchAll($sql);
}

        }
    }
    
    include '../../includes/planner_header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold"><?php echo $action == 'create' ? 'Create New Event' : 'Edit Event'; ?></h1>
        <a href="events.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-arrow-left mr-2"></i> Back to Events
        </a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <ul class="list-disc pl-4">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Basic Information -->
                <div>
                    <h2 class="text-xl font-bold mb-4">Basic Information</h2>

                    <div class="mb-4">
                        <label for="title" class="block text-gray-700 font-bold mb-2">Event Title *</label>
                        <input type="text" id="title" name="title"
                            value="<?php echo htmlspecialchars($event['title'] ?? ''); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            required>
                    </div>

                    <div class="mb-4">
                        <label for="category" class="block text-gray-700 font-bold mb-2">Category</label>
                        <select id="category" name="category"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                            <option value="">Select Category</option>
                            <option value="Conference"
                                <?php echo ($event['category'] ?? '') == 'Conference' ? 'selected' : ''; ?>>Conference
                            </option>
                            <option value="Concert"
                                <?php echo ($event['category'] ?? '') == 'Concert' ? 'selected' : ''; ?>>Concert
                            </option>
                            <option value="Exhibition"
                                <?php echo ($event['category'] ?? '') == 'Exhibition' ? 'selected' : ''; ?>>Exhibition
                            </option>
                            <option value="Workshop"
                                <?php echo ($event['category'] ?? '') == 'Workshop' ? 'selected' : ''; ?>>Workshop
                            </option>
                            <option value="Seminar"
                                <?php echo ($event['category'] ?? '') == 'Seminar' ? 'selected' : ''; ?>>Seminar
                            </option>
                            <option value="Festival"
                                <?php echo ($event['category'] ?? '') == 'Festival' ? 'selected' : ''; ?>>Festival
                            </option>
                            <option value="Sports"
                                <?php echo ($event['category'] ?? '') == 'Sports' ? 'selected' : ''; ?>>Sports</option>
                            <option value="Other"
                                <?php echo ($event['category'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="block text-gray-700 font-bold mb-2">Description</label>
                        <textarea id="description" name="description" rows="5"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"><?php echo htmlspecialchars($event['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="image" class="block text-gray-700 font-bold mb-2">Event Image</label>
                        <?php if (!empty($event['image'])): ?>
                        <div class="mb-2">
                            <img src="<?php echo $event['image']; ?>" alt="Event Image"
                                class="w-32 h-32 object-cover rounded">
                            <p class="text-sm text-gray-500 mt-1">Current image</p>
                        </div>
                        <?php endif; ?>
                        <input type="file" id="image" name="image"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                        <p class="text-sm text-gray-500 mt-1">Recommended size: 1200x630 pixels. Max size: 5MB.</p>
                    </div>
                </div>

                <!-- Venue & Time -->
                <div>
                    <h2 class="text-xl font-bold mb-4">Venue & Time</h2>

                    <div class="mb-4">
                        <label for="venue" class="block text-gray-700 font-bold mb-2">Venue Name *</label>
                        <input type="text" id="venue" name="venue"
                            value="<?php echo htmlspecialchars($event['venue'] ?? ''); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            required>
                    </div>

                    <div class="mb-4">
                        <label for="address" class="block text-gray-700 font-bold mb-2">Address</label>
                        <input type="text" id="address" name="address"
                            value="<?php echo htmlspecialchars($event['address'] ?? ''); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="city" class="block text-gray-700 font-bold mb-2">City</label>
                            <input type="text" id="city" name="city"
                                value="<?php echo htmlspecialchars($event['city'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="country" class="block text-gray-700 font-bold mb-2">Country</label>
                            <input type="text" id="country" name="country"
                                value="<?php echo htmlspecialchars($event['country'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="start_date" class="block text-gray-700 font-bold mb-2">Start Date *</label>
                            <input type="date" id="start_date" name="start_date"
                                value="<?php echo htmlspecialchars($event['start_date'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                required>
                        </div>
                        <div>
                            <label for="end_date" class="block text-gray-700 font-bold mb-2">End Date *</label>
                            <input type="date" id="end_date" name="end_date"
                                value="<?php echo htmlspecialchars($event['end_date'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                required>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="start_time" class="block text-gray-700 font-bold mb-2">Start Time *</label>
                            <input type="time" id="start_time" name="start_time"
                                value="<?php echo htmlspecialchars($event['start_time'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                required>
                        </div>
                        <div>
                            <label for="end_time" class="block text-gray-700 font-bold mb-2">End Time *</label>
                            <input type="time" id="end_time" name="end_time"
                                value="<?php echo htmlspecialchars($event['end_time'] ?? ''); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tickets & Status -->
            <div class="mt-6 border-t pt-6">
                <h2 class="text-xl font-bold mb-4">Ticket Types & Pricing</h2>

                <div id="ticket-types-container">
                    <?php 
        // Check if we're editing and have ticket types to display
        if ($action == 'edit' && isset($event['id'])): 
            // Fetch ticket types for this event
            $sql = "SELECT * FROM ticket_types WHERE event_id = " . $event['id'] . " ORDER BY price ASC";
            $ticketTypes = $db->fetchAll($sql);
            
            if (!empty($ticketTypes)):
                foreach ($ticketTypes as $index => $type): 
        ?>
                    <div class="ticket-type-row bg-gray-50 p-4 rounded-md mb-4 relative">
                        <?php if (count($ticketTypes) > 1): ?>
                        <button type="button" class="absolute top-2 right-2 text-red-500 hover:text-red-700"
                            onclick="this.parentElement.remove()">
                            <i class="fas fa-times-circle"></i>
                        </button>
                        <?php endif; ?>
                        <input type="hidden" name="ticket_types[<?php echo $index; ?>][id]"
                            value="<?php echo $type['id']; ?>">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Ticket Name *</label>
                                <input type="text" name="ticket_types[<?php echo $index; ?>][name]"
                                    value="<?php echo htmlspecialchars($type['name']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    required>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Price (<?php echo CURRENCY_SYMBOL; ?>)
                                    *</label>
                                <input type="number" name="ticket_types[<?php echo $index; ?>][price]"
                                    value="<?php echo htmlspecialchars($type['price']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    min="0" step="0.01" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Quantity *</label>
                                <input type="number" name="ticket_types[<?php echo $index; ?>][quantity]"
                                    value="<?php echo htmlspecialchars($type['total_tickets']); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    min="1" required>
                                <?php
                            // Get tickets sold for this type
                            $sql = "SELECT COUNT(*) as count FROM tickets WHERE ticket_type_id = " . $type['id'] . " AND status = 'sold'";
                            $ticketCount = $db->fetchOne($sql);
                            $soldTickets = $ticketCount['count'] ?? 0;
                            
                            if ($soldTickets > 0):
                            ?>
                                <p class="text-sm text-gray-500 mt-1">
                                    <?php echo $soldTickets; ?> tickets already sold. You cannot set quantity less than
                                    this.
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-bold mb-2">Description</label>
                            <textarea name="ticket_types[<?php echo $index; ?>][description]"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                rows="2"><?php echo htmlspecialchars($type['description']); ?></textarea>
                        </div>
                    </div>
                    <?php 
                endforeach;
            else:
                // No ticket types found, show default empty form
        ?>
                    <div class="ticket-type-row bg-gray-50 p-4 rounded-md mb-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Ticket Name *</label>
                                <input type="text" name="ticket_types[0][name]" placeholder="e.g. Regular, VIP, etc."
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    required>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Price (<?php echo CURRENCY_SYMBOL; ?>)
                                    *</label>
                                <input type="number" name="ticket_types[0][price]" placeholder="0.00"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    min="0" step="0.01" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Quantity *</label>
                                <input type="number" name="ticket_types[0][quantity]" placeholder="100"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    min="1" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-bold mb-2">Description</label>
                            <textarea name="ticket_types[0][description]"
                                placeholder="Describe what's included with this ticket type..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                rows="2"></textarea>
                        </div>
                    </div>
                    <?php 
            endif;
        else:
            // Creating a new event, show default empty form
        ?>
                    <div class="ticket-type-row bg-gray-50 p-4 rounded-md mb-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Ticket Name *</label>
                                <input type="text" name="ticket_types[0][name]" placeholder="e.g. Regular, VIP, etc."
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    required>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Price (<?php echo CURRENCY_SYMBOL; ?>)
                                    *</label>
                                <input type="number" name="ticket_types[0][price]" placeholder="0.00"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    min="0" step="0.01" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-bold mb-2">Quantity *</label>
                                <input type="number" name="ticket_types[0][quantity]" placeholder="100"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    min="1" required>
                            </div>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-bold mb-2">Description</label>
                            <textarea name="ticket_types[0][description]"
                                placeholder="Describe what's included with this ticket type..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                rows="2"></textarea>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mb-6">
                    <button type="button" id="add-ticket-type"
                        class="text-indigo-600 hover:text-indigo-800 font-medium">
                        <i class="fas fa-plus-circle mr-1"></i> Add Another Ticket Type
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="status" class="block text-gray-700 font-bold mb-2">Event Status</label>
                        <select id="status" name="status"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                            <option value="active"
                                <?php echo ($event['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed"
                                <?php echo ($event['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>Completed
                            </option>
                            <option value="canceled"
                                <?php echo ($event['status'] ?? '') == 'canceled' ? 'selected' : ''; ?>>Canceled
                            </option>
                            <option value="suspended"
                                <?php echo ($event['status'] ?? '') == 'suspended' ? 'selected' : ''; ?>>Suspended
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- JavaScript for dynamic ticket types -->
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const container = document.getElementById('ticket-types-container');
                const addButton = document.getElementById('add-ticket-type');
                let ticketTypeCount =
                    <?php echo ($action == 'edit' && !empty($ticketTypes)) ? count($ticketTypes) : 1; ?>;

                addButton.addEventListener('click', function() {
                    const newRow = document.createElement('div');
                    newRow.className = 'ticket-type-row bg-gray-50 p-4 rounded-md mb-4 relative';

                    newRow.innerHTML = `
            <button type="button" class="absolute top-2 right-2 text-red-500 hover:text-red-700" onclick="this.parentElement.remove()">
                <i class="fas fa-times-circle"></i>
            </button>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-2">
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Ticket Name *</label>
                    <input type="text" name="ticket_types[${ticketTypeCount}][name]" placeholder="e.g. Regular, VIP, etc." 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                           required>
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Price (<?php echo CURRENCY_SYMBOL; ?>) *</label>
                    <input type="number" name="ticket_types[${ticketTypeCount}][price]" placeholder="0.00" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                           min="0" step="0.01" required>
                </div>
                <div>
                    <label class="block text-gray-700 font-bold mb-2">Quantity *</label>
                    <input type="number" name="ticket_types[${ticketTypeCount}][quantity]" placeholder="100" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                           min="1" required>
                </div>
            </div>
            <div>
                <label class="block text-gray-700 font-bold mb-2">Description</label>
                <textarea name="ticket_types[${ticketTypeCount}][description]" placeholder="Describe what's included with this ticket type..." 
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                          rows="2"></textarea>
            </div>
        `;

                    container.appendChild(newRow);
                    ticketTypeCount++;
                });
            });
            </script>



            <div class="mt-6 border-t pt-6 flex justify-end">
                <a href="events.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded mr-2">
                    Cancel
                </a>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                    <?php echo $action == 'create' ? 'Create Event' : 'Update Event'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php
include '../../includes/footer.php';
}
?>