<?php
$pageTitle = "Events";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Get filter parameters
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$city = isset($_GET['city']) ? trim($_GET['city']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date = isset($_GET['date']) ? trim($_GET['date']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'date_asc';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

$whereConditions = ["e.status = 'active'"];

if (!empty($category)) {
    $whereConditions[] = "e.category = '" . $db->escape($category) . "'";
}

if (!empty($city)) {
    $whereConditions[] = "e.city LIKE '%" . $db->escape($city) . "%'";
}

if (!empty($search)) {
    $searchEscaped = $db->escape($search);
    $whereConditions[] = "(e.title LIKE '%$searchEscaped%' OR e.description LIKE '%$searchEscaped%' OR e.venue LIKE '%$searchEscaped%')";
}

if (!empty($date)) {
    $whereConditions[] = "DATE(e.start_date) = '" . $db->escape($date) . "'";
} else {
    // Show only available events (upcoming + ongoing) by default
    $whereConditions[] = "e.end_date >= CURDATE()";
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Build ORDER BY clause
switch ($sort) {
    case 'date_desc':
        $orderBy = "e.start_date DESC";
        break;
    case 'price_asc':
        $orderBy = "COALESCE(min_price, e.ticket_price) ASC";
        break;
    case 'price_desc':
        $orderBy = "COALESCE(min_price, e.ticket_price) DESC";
        break;
    case 'name_asc':
        $orderBy = "e.title ASC";
        break;
    case 'name_desc':
        $orderBy = "e.title DESC";
        break;
    default:
        $orderBy = "e.start_date ASC";
}


// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM events e $whereClause";
$totalResult = $db->fetchOne($countSql);
$totalEvents = $totalResult['total'];
$totalPages = ceil($totalEvents / $perPage);

$sql = "SELECT e.*, u.username as planner_name,
        (SELECT MIN(tt.price) FROM ticket_types tt WHERE tt.event_id = e.id AND tt.available_tickets > 0) as min_price,
        (SELECT MAX(tt.price) FROM ticket_types tt WHERE tt.event_id = e.id AND tt.available_tickets > 0) as max_price,
        (SELECT COUNT(*) FROM ticket_types tt WHERE tt.event_id = e.id AND tt.available_tickets > 0) as ticket_types_count,
        (SELECT SUM(tt.available_tickets) FROM ticket_types tt WHERE tt.event_id = e.id) as total_available_tickets,
        CASE 
            WHEN CURDATE() < e.start_date THEN 'upcoming'
            WHEN CURDATE() BETWEEN e.start_date AND e.end_date THEN 'ongoing'
            ELSE 'past'
        END as event_status
        FROM events e 
        JOIN users u ON e.planner_id = u.id 
        $whereClause
        ORDER BY 
            CASE WHEN CURDATE() BETWEEN e.start_date AND e.end_date THEN 1 ELSE 2 END,
            $orderBy
        LIMIT $offset, $perPage";

$events = $db->fetchAll($sql);

// Get filter options
$categoriesSql = "SELECT DISTINCT category FROM events WHERE category IS NOT NULL AND category != '' AND status = 'active' ORDER BY category";
$categories = $db->fetchAll($categoriesSql);

$citiesSql = "SELECT DISTINCT city FROM events WHERE city IS NOT NULL AND city != '' AND status = 'active' ORDER BY city";
$cities = $db->fetchAll($citiesSql);

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Discover Events</h1>
        <p class="text-gray-600">Find and book tickets for amazing events happening near you</p>
    </div>

   <!-- Complete Filters Section (lines 108-143) -->
<div class="bg-white rounded-lg shadow-md p-6 mb-8">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
        <!-- Search -->
        <div class="lg:col-span-2">
            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search Events</label>
            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>"
                placeholder="Event name, venue, or description..."
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
        </div>

        <!-- Category -->
        <div>
            <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
            <select id="category" name="category"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                    <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['category']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- City -->
        <div>
            <label for="city" class="block text-sm font-medium text-gray-700 mb-1">City</label>
            <select id="city" name="city"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                <option value="">All Cities</option>
                <?php foreach ($cities as $cityOption): ?>
                <option value="<?php echo htmlspecialchars($cityOption['city']); ?>"
                    <?php echo $city === $cityOption['city'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cityOption['city']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Date -->
        <div>
            <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
            <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date); ?>"
                min="<?php echo date('Y-m-d'); ?>"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
        </div>

        <!-- Submit -->
        <div class="flex items-end">
            <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md transition duration-200">
                <i class="fas fa-search mr-2"></i>Search
            </button>
        </div>
    </form>
</div>


    <!-- Results Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <p class="text-gray-600">
                Showing <?php echo number_format($totalEvents); ?> event<?php echo $totalEvents !== 1 ? 's' : ''; ?>
                <?php if (!empty($search) || !empty($category) || !empty($city) || !empty($date)): ?>
                <span class="text-indigo-600">with applied filters</span>
                <?php endif; ?>
            </p>
        </div>

        <!-- Sort Options -->
        <div class="flex items-center space-x-2">
            <label for="sort" class="text-sm text-gray-600">Sort by:</label>
            <select id="sort" name="sort" onchange="updateSort(this.value)"
                class="px-3 py-1 border border-gray-300 rounded-md text-sm focus:outline-none focus:border-indigo-500">
                <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Date (Earliest)</option>
                <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Date (Latest)</option>
                <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price (Low to High)
                </option>
                <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price (High to Low)
                </option>
                <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
            </select>
        </div>
    </div>

    <!-- Events Grid -->
<?php if (empty($events)): ?>
<div class="text-center py-12">
    <div class="text-gray-400 mb-4">
        <i class="fas fa-calendar-times text-6xl"></i>
    </div>
    <h3 class="text-xl font-semibold text-gray-600 mb-2">No Events Found</h3>
    <p class="text-gray-500 mb-4">Try adjusting your search criteria or browse all events</p>
    <a href="events.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
        View All Events
    </a>
</div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
    <?php foreach ($events as $event): ?>
    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
        <div class="relative">
            <?php if (!empty($event['image'])): ?>
            <img src="<?php echo SITE_URL; ?>/uploads/events/<?php echo $event['image']; ?>"
                alt="<?php echo htmlspecialchars($event['title']); ?>" class="w-full h-48 object-cover">
            <?php else: ?>
            <div class="w-full h-48 bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center">
                <i class="fas fa-calendar-alt text-white text-4xl"></i>
            </div>
            <?php endif; ?>

            <!-- Pricing Badge -->
            <div class="absolute top-0 right-0 bg-indigo-600 text-white px-3 py-1 m-2 rounded-lg text-sm font-semibold">
                <?php 
                // Display pricing based on ticket types
                if ($event['ticket_types_count'] > 0 && $event['min_price'] !== null) {
                    if ($event['min_price'] == $event['max_price']) {
                        // Single price
                        echo formatCurrency($event['min_price']);
                    } else {
                        // Price range
                        echo formatCurrency($event['min_price']) . ' - ' . formatCurrency($event['max_price']);
                    }
                } else {
                    // Fallback to event base price
                    echo formatCurrency($event['ticket_price']);
                }
                ?>
            </div>

            <!-- Category Badge -->
            <?php if (!empty($event['category'])): ?>
            <div class="absolute top-0 left-0 bg-black bg-opacity-50 text-white px-3 py-1 m-2 rounded-lg text-xs">
                <?php echo htmlspecialchars($event['category']); ?>
            </div>
            <?php endif; ?>

            <!-- Event Status Badge -->
            <?php if ($event['event_status'] === 'ongoing'): ?>
            <div class="absolute bottom-0 left-0 bg-red-500 text-white px-3 py-1 m-2 rounded-lg text-xs animate-pulse">
                <i class="fas fa-circle mr-1"></i>LIVE NOW
            </div>
            <?php elseif ($event['event_status'] === 'upcoming'): ?>
            <div class="absolute bottom-0 left-0 bg-green-500 text-white px-3 py-1 m-2 rounded-lg text-xs">
                <i class="fas fa-clock mr-1"></i>UPCOMING
            </div>
            <?php endif; ?>

            <!-- Status Badge for low availability -->
            <?php 
            $availableTickets = $event['total_available_tickets'] ?? $event['available_tickets'];
            if ($availableTickets <= 10 && $availableTickets > 0): 
            ?>
            <div class="absolute bottom-0 right-0 bg-orange-500 text-white px-2 py-1 m-2 rounded text-xs">
                Only <?php echo $availableTickets; ?> left!
            </div>
            <?php elseif ($availableTickets <= 0): ?>
            <div class="absolute bottom-0 right-0 bg-gray-800 text-white px-2 py-1 m-2 rounded text-xs">
                Sold Out
            </div>
            <?php endif; ?>
        </div>

        <div class="p-4">
            <div class="flex justify-between items-start mb-2">
                <h3 class="text-lg font-semibold line-clamp-2 flex-1"><?php echo htmlspecialchars($event['title']); ?></h3>
                <?php if ($event['event_status'] === 'ongoing'): ?>
                    <span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full ml-2 flex-shrink-0">
                        LIVE
                    </span>
                <?php endif; ?>
            </div>

            <div class="flex items-center text-gray-600 mb-2 text-sm">
                <i class="fas fa-map-marker-alt mr-2 flex-shrink-0"></i>
                <span class="truncate"><?php echo htmlspecialchars($event['venue']) . ', ' . htmlspecialchars($event['city']); ?></span>
            </div>

            <div class="flex items-center text-gray-600 mb-2 text-sm">
                <i class="fas fa-calendar-day mr-2 flex-shrink-0"></i>
                <span>
                    <?php echo formatDate($event['start_date']); ?>
                    <?php if ($event['start_date'] !== $event['end_date']): ?>
                        - <?php echo formatDate($event['end_date']); ?>
                    <?php endif; ?>
                </span>
            </div>

            <div class="flex items-center text-gray-600 mb-3 text-sm">
                <i class="fas fa-clock mr-2 flex-shrink-0"></i>
                <span><?php echo formatTime($event['start_time']); ?></span>
            </div>

            <!-- Event Duration Info for Multi-day Events -->
            <?php if ($event['start_date'] !== $event['end_date']): ?>
            <div class="flex items-center text-gray-500 mb-3 text-xs">
                <i class="fas fa-calendar-week mr-2 flex-shrink-0"></i>
                <span>
                    <?php 
                    $startDate = new DateTime($event['start_date']);
                    $endDate = new DateTime($event['end_date']);
                    $duration = $startDate->diff($endDate)->days + 1;
                    echo $duration . ' day' . ($duration > 1 ? 's' : '') . ' event';
                    ?>
                </span>
            </div>
            <?php endif; ?>

            <div class="flex items-center text-gray-600 mb-4 text-xs">
                <i class="fas fa-user mr-2 flex-shrink-0"></i>
                <span class="truncate">By <?php echo htmlspecialchars($event['planner_name']); ?></span>
            </div>

            <!-- Ticket Types Preview -->
            <?php if ($event['ticket_types_count'] > 1): ?>
            <div class="mb-3 pb-3 border-b border-gray-200">
                <div class="text-xs text-gray-500 mb-2"><?php echo $event['ticket_types_count']; ?> ticket types available:</div>
                <?php
                // Get ticket types for this event
                $ticketTypesSql = "SELECT name, price, available_tickets 
                                  FROM ticket_types 
                                  WHERE event_id = " . $event['id'] . " 
                                  AND available_tickets > 0 
                                  ORDER BY price ASC 
                                  LIMIT 2";
                $ticketTypes = $db->fetchAll($ticketTypesSql);
                ?>
                <div class="space-y-1">
                    <?php foreach ($ticketTypes as $type): ?>
                    <div class="flex justify-between items-center text-xs">
                        <span class="text-gray-700 truncate mr-2"><?php echo htmlspecialchars($type['name']); ?></span>
                        <span class="text-indigo-600 font-semibold"><?php echo formatCurrency($type['price']); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($event['ticket_types_count'] > 2): ?>
                    <div class="text-xs text-gray-500">
                        +<?php echo ($event['ticket_types_count'] - 2); ?> more type<?php echo ($event['ticket_types_count'] - 2) > 1 ? 's' : ''; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-500">
                    <?php echo number_format($availableTickets); ?>
                    ticket<?php echo $availableTickets !== 1 ? 's' : ''; ?> left
                </span>

                <?php if ($availableTickets > 0): ?>
                <a href="event-details.php?id=<?php echo $event['id']; ?>"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded text-sm transition duration-300">
                    <?php echo $event['event_status'] === 'ongoing' ? 'Join Now' : 'View Details'; ?>
                </a>
                <?php else: ?>
                <span class="bg-gray-400 text-white font-bold py-2 px-4 rounded text-sm cursor-not-allowed">
                    Sold Out
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>


    <!-- Clear Filters -->
    <?php if (!empty($search) || !empty($category) || !empty($city) || !empty($date)): ?>
    <div class="mt-6 text-center">
        <a href="events.php" class="text-indigo-600 hover:text-indigo-800 text-sm">
            <i class="fas fa-times mr-1"></i>Clear all filters
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
function updateSort(sortValue) {
    const url = new URL(window.location);
    url.searchParams.set('sort', sortValue);
    url.searchParams.set('page', '1'); // Reset to first page when sorting
    window.location.href = url.toString();
}

// Add some CSS for line clamping
document.addEventListener('DOMContentLoaded', function() {
    const style = document.createElement('style');
    style.textContent = `
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    `;
    document.head.appendChild(style);
});
</script>

<?php include 'includes/footer.php'; ?>