<?php
$pageTitle = "Event Management";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

// Filters
$statusFilter = $_GET['status'] ?? '';
$plannerFilter = $_GET['planner'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build WHERE clause
$whereConditions = [];
if (!empty($statusFilter)) {
    $whereConditions[] = "e.status = '" . $db->escape($statusFilter) . "'";
}
if (!empty($plannerFilter)) {
    $whereConditions[] = "e.planner_id = " . (int)$plannerFilter;
}
if (!empty($categoryFilter)) {
    $whereConditions[] = "e.category = '" . $db->escape($categoryFilter) . "'";
}
if (!empty($searchQuery)) {
    $whereConditions[] = "(e.title LIKE '%" . $db->escape($searchQuery) . "%' OR e.description LIKE '%" . $db->escape($searchQuery) . "%')";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Get total count
$countSql = "SELECT COUNT(*) as total 
             FROM events e 
             JOIN users u ON e.planner_id = u.id 
             $whereClause";
$totalResult = $db->fetchOne($countSql);
$totalEvents = $totalResult['total'];
$totalPages = ceil($totalEvents / $perPage);

// Get events
$sql = "SELECT 
            e.*,
            u.username as planner_name,
            u.email as planner_email,
            (SELECT COUNT(*) FROM tickets t WHERE t.event_id = e.id AND t.status = 'sold') as tickets_sold,
            (SELECT SUM(t.purchase_price) FROM tickets t WHERE t.event_id = e.id AND t.status = 'sold') as revenue
        FROM events e
        JOIN users u ON e.planner_id = u.id
        $whereClause
        ORDER BY e.created_at DESC
        LIMIT $offset, $perPage";
$events = $db->fetchAll($sql);

// Get planners for filter
$plannersSql = "SELECT DISTINCT u.id, u.username 
                FROM users u 
                JOIN events e ON u.id = e.planner_id 
                WHERE u.role = 'event_planner' 
                ORDER BY u.username";
$planners = $db->fetchAll($plannersSql);

// Get categories for filter
$categoriesSql = "SELECT DISTINCT category FROM events WHERE category IS NOT NULL ORDER BY category";
$categories = $db->fetchAll($categoriesSql);

include '../../includes/admin_header.php';
?>

<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Event Management</h1>
            <p class="text-gray-600 mt-2 text-sm sm:text-base">Monitor and moderate all events on the platform</p>
        </div>
        <div class="flex gap-2">
            <a href="../index.php"
                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm sm:text-base">
                <i class="fas fa-arrow-left mr-2"></i>Dashboard
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

    <!-- Statistics Cards -->
    <?php
    $statsSql = "SELECT 
                    COUNT(*) as total_events,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_events,
                    SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_events,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_events,
                    SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) as canceled_events
                 FROM events";
    $stats = $db->fetchOne($statsSql);
    ?>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-lg sm:text-2xl font-bold text-blue-600"><?php echo $stats['total_events']; ?></div>
                <div class="text-xs sm:text-sm text-gray-500">Total Events</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-lg sm:text-2xl font-bold text-green-600"><?php echo $stats['active_events']; ?></div>
                <div class="text-xs sm:text-sm text-gray-500">Active</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-lg sm:text-2xl font-bold text-red-600"><?php echo $stats['suspended_events']; ?></div>
                <div class="text-xs sm:text-sm text-gray-500">Suspended</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-lg sm:text-2xl font-bold text-purple-600"><?php echo $stats['completed_events']; ?>
                </div>
                <div class="text-xs sm:text-sm text-gray-500">Completed</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-lg sm:text-2xl font-bold text-yellow-600"><?php echo $stats['canceled_events']; ?>
                </div>
                <div class="text-xs sm:text-sm text-gray-500">Canceled</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <form method="GET" action="" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Search -->
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>"
                        placeholder="Event title or description..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                </div>

                <!-- Status Filter -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active
                        </option>
                        <option value="suspended" <?php echo $statusFilter === 'suspended' ? 'selected' : ''; ?>>
                            Suspended</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>
                            Completed</option>
                        <option value="canceled" <?php echo $statusFilter === 'canceled' ? 'selected' : ''; ?>>Canceled
                        </option>
                    </select>
                </div>

                <!-- Planner Filter -->
                <div>
                    <label for="planner" class="block text-sm font-medium text-gray-700 mb-1">Event Planner</label>
                    <select id="planner" name="planner"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                        <option value="">All Planners</option>
                        <?php foreach ($planners as $planner): ?>
                        <option value="<?php echo $planner['id']; ?>"
                            <?php echo $plannerFilter == $planner['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($planner['username']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Category Filter -->
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select id="category" name="category"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['category']); ?>"
                            <?php echo $categoryFilter === $category['category'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['category']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter Buttons -->
                <div class="flex items-end gap-2">
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm">
                        <i class="fas fa-search mr-1"></i>Filter
                    </button>
                    <a href="index.php"
                        class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm">
                        <i class="fas fa-times mr-1"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Events Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">
                            Planner</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">
                            Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">
                            Sales</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($events)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                            <i class="fas fa-calendar-alt text-4xl text-gray-300 mb-4 block"></i>
                            No events found
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($events as $event): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-start">
                                <?php if ($event['image']): ?>
                                <img class="h-12 w-12 rounded-lg object-cover mr-3 flex-shrink-0"
                                    src="<?php echo SITE_URL; ?>/uploads/events/<?php echo $event['image']; ?>"
                                    alt="Event">
                                <?php else: ?>
                                <div
                                    class="h-12 w-12 rounded-lg bg-gray-200 flex items-center justify-center mr-3 flex-shrink-0">
                                    <i class="fas fa-calendar-alt text-gray-400"></i>
                                </div>
                                <?php endif; ?>

                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-medium text-gray-900 truncate max-w-48">
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($event['category']); ?> •
                                        <?php echo htmlspecialchars($event['venue']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 hidden sm:table-cell">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($event['planner_name']); ?>
                            </div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($event['planner_email']); ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <div class="text-sm text-gray-900"><?php echo formatDate($event['start_date']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo formatTime($event['start_time']); ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
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
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <div class="text-sm text-gray-900"><?php echo $event['tickets_sold']; ?> tickets</div>
                            <div class="text-xs text-gray-500"><?php echo formatCurrency($event['revenue'] ?? 0); ?>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center space-x-2">
                                <a href="view.php?id=<?php echo $event['id']; ?>"
                                    class="text-indigo-600 hover:text-indigo-900 text-sm" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>

                                <!-- Status Update Dropdown -->
                                <div class="relative inline-block text-left">
                                    <button type="button"
                                        class="text-gray-600 hover:text-gray-900 text-sm dropdown-toggle"
                                        onclick="toggleDropdown('status-dropdown-<?php echo $event['id']; ?>')"
                                        title="Update Status">
                                        <i class="fas fa-cog"></i>
                                    </button>

                                    <div id="status-dropdown-<?php echo $event['id']; ?>"
                                        class="dropdown-menu hidden absolute right-0 z-10 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                                        <div class="py-1">
                                            <?php 
                                                    // Create a clean return URL
                                                    $currentPage = basename($_SERVER['PHP_SELF']);
                                                    $queryString = $_SERVER['QUERY_STRING'];
                                                    $returnUrl = $currentPage . (!empty($queryString) ? '?' . $queryString : '');
                                                    ?>

                                            <?php if ($event['status'] !== 'active'): ?>
                                            <a href="update_status.php?event_id=<?php echo $event['id']; ?>&status=active&return=<?php echo urlencode($returnUrl); ?>"
                                                class="block px-4 py-2 text-sm text-green-700 hover:bg-green-50"
                                                onclick="return confirm('Activate this event?')">
                                                <i class="fas fa-check mr-2"></i>Activate
                                            </a>
                                            <?php endif; ?>

                                            <?php if ($event['status'] !== 'suspended'): ?>
                                            <a href="update_status.php?event_id=<?php echo $event['id']; ?>&status=suspended&return=<?php echo urlencode($returnUrl); ?>"
                                                class="block px-4 py-2 text-sm text-red-700 hover:bg-red-50"
                                                onclick="return confirm('Suspend this event?')">
                                                <i class="fas fa-ban mr-2"></i>Suspend
                                            </a>
                                            <?php endif; ?>

                                            <?php if ($event['status'] !== 'completed'): ?>
                                            <a href="update_status.php?event_id=<?php echo $event['id']; ?>&status=completed&return=<?php echo urlencode($returnUrl); ?>"
                                                class="block px-4 py-2 text-sm text-blue-700 hover:bg-blue-50"
                                                onclick="return confirm('Mark this event as completed?')">
                                                <i class="fas fa-check-circle mr-2"></i>Complete
                                            </a>
                                            <?php endif; ?>

                                            <?php if ($event['status'] !== 'canceled'): ?>
                                            <a href="update_status.php?event_id=<?php echo $event['id']; ?>&status=canceled&return=<?php echo urlencode($returnUrl); ?>"
                                                class="block px-4 py-2 text-sm text-yellow-700 hover:bg-yellow-50"
                                                onclick="return confirm('Cancel this event?')">
                                                <i class="fas fa-times-circle mr-2"></i>Cancel
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>

                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="mt-6 flex justify-center">
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                <span class="sr-only">Previous</span>
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                    $isCurrentPage = $i === $page;
                    $pageClass = $isCurrentPage 
                        ? 'relative inline-flex items-center px-4 py-2 border border-indigo-500 bg-indigo-50 text-sm font-medium text-indigo-600'
                        : 'relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50';
                ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                class="<?php echo $pageClass; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                <span class="sr-only">Next</span>
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/admin_footer.php'; ?>