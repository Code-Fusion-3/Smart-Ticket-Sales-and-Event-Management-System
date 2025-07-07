<?php
$pageTitle = "Ticket Marketplace";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Filters
$categoryFilter = $_GET['category'] ?? '';
$cityFilter = $_GET['city'] ?? '';
$priceMin = isset($_GET['price_min']) ? (float)$_GET['price_min'] : 0;
$priceMax = isset($_GET['price_max']) ? (float)$_GET['price_max'] : 0;
$sortBy = $_GET['sort'] ?? 'newest';

// Build WHERE clause
$whereConditions = ["tr.status = 'active'", "e.status = 'active'", "e.start_date > CURDATE()"];
$params = [];

if (!empty($categoryFilter)) {
    $whereConditions[] = "e.category = ?";
    $params[] = $categoryFilter;
}

if (!empty($cityFilter)) {
    $whereConditions[] = "e.city = ?";
    $params[] = $cityFilter;
}

if ($priceMin > 0) {
    $whereConditions[] = "tr.resale_price >= ?";
    $params[] = $priceMin;
}

if ($priceMax > 0) {
    $whereConditions[] = "tr.resale_price <= ?";
    $params[] = $priceMax;
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Sorting
$orderClause = "ORDER BY ";
switch ($sortBy) {
    case 'price_low':
        $orderClause .= "tr.resale_price ASC";
        break;
    case 'price_high':
        $orderClause .= "tr.resale_price DESC";
        break;
    case 'date':
        $orderClause .= "e.start_date ASC";
        break;
    case 'newest':
    default:
        $orderClause .= "tr.listed_at DESC";
        break;
}

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total 
             FROM ticket_resales tr
             JOIN tickets t ON tr.ticket_id = t.id
             JOIN events e ON t.event_id = e.id
             LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
             $whereClause";

// Add: Only show resale tickets for ticket types that are sold out (available_tickets = 0)
// If ticket_type_id is NULL, allow listing (for backward compatibility)
$countSql .= " AND (t.ticket_type_id IS NULL OR tt.available_tickets = 0)";

$totalResult = $db->fetchOne($countSql, $params);
$totalListings = $totalResult['total'];
$totalPages = ceil($totalListings / $perPage);

// Get resale listings
$sql = "SELECT tr.*, t.id as ticket_id, t.purchase_price as original_price,
               e.title, e.start_date, e.start_time, e.venue, e.city, e.category, e.image,
               tt.name as ticket_type_name,
               u.username as seller_name
        FROM ticket_resales tr
        JOIN tickets t ON tr.ticket_id = t.id
        JOIN events e ON t.event_id = e.id
        LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
        JOIN users u ON tr.seller_id = u.id
        $whereClause
        AND (t.ticket_type_id IS NULL OR tt.available_tickets = 0)
        $orderClause
        LIMIT $offset, $perPage";

$listings = $db->fetchAll($sql, $params);

// Get filter options
$categoriesQuery = "SELECT DISTINCT e.category 
                   FROM events e 
                   JOIN tickets t ON e.id = t.event_id 
                   JOIN ticket_resales tr ON t.id = tr.ticket_id 
                   WHERE tr.status = 'active' AND e.status = 'active'
                   ORDER BY e.category";
$categories = $db->fetchAll($categoriesQuery);

$citiesQuery = "SELECT DISTINCT e.city 
               FROM events e 
               JOIN tickets t ON e.id = t.event_id 
               JOIN ticket_resales tr ON t.id = tr.ticket_id 
               WHERE tr.status = 'active' AND e.status = 'active'
               ORDER BY e.city";
$cities = $db->fetchAll($citiesQuery);

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Ticket Marketplace</h1>
        <p class="text-gray-600 mt-2">Find great deals on resale tickets from verified sellers</p>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
            <!-- Category Filter -->
            <div>
                <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select id="category" name="category" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['category']); ?>" 
                                <?php echo $categoryFilter === $category['category'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['category']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- City Filter -->
            <div>
                <label for="city" class="block text-sm font-medium text-gray-700 mb-1">City</label>
                <select id="city" name="city" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                    <option value="">All Cities</option>
                    <?php foreach ($cities as $city): ?>
                        <option value="<?php echo htmlspecialchars($city['city']); ?>" 
                                <?php echo $cityFilter === $city['city'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($city['city']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Price Range -->
            <div>
                <label for="price_min" class="block text-sm font-medium text-gray-700 mb-1">Min Price</label>
                <input type="number" id="price_min" name="price_min" value="<?php echo $priceMin; ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                       placeholder="Min">
            </div>

            <div>
                <label for="price_max" class="block text-sm font-medium text-gray-700 mb-1">Max Price</label>
                <input type="number" id="price_max" name="price_max" value="<?php echo $priceMax; ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                       placeholder="Max">
            </div>

            <!-- Sort -->
            <div>
                <label for="sort" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                <select id="sort" name="sort" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                    <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="price_low" <?php echo $sortBy === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_high" <?php echo $sortBy === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="date" <?php echo $sortBy === 'date' ? 'selected' : ''; ?>>Event Date</option>
                </select>
            </div>

            <!-- Filter Button -->
            <div class="flex items-end">
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Results Summary -->
    <div class="flex justify-between items-center mb-6">
        <div class="text-gray-600">
            Showing <?php echo count($listings); ?> of <?php echo $totalListings; ?> listings
        </div>
               <a href="resell-ticket.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition duration-300">
            <i class="fas fa-plus mr-2"></i> Sell Your Ticket
        </a>
    </div>

    <!-- Listings Grid -->
    <?php if (empty($listings)): ?>
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <div class="text-gray-500 mb-4">
                <i class="fas fa-search text-6xl"></i>
            </div>
            <h2 class="text-2xl font-bold mb-4">No tickets found</h2>
            <p class="text-gray-600 mb-6">Try adjusting your filters or check back later for new listings.</p>
            <a href="events.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg transition duration-300">
                Browse Events
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($listings as $listing): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
                    <!-- Event Image -->
                    <div class="relative h-48 bg-gray-200">
                        <?php if (!empty($listing['image'])): ?>
                            <img src="<?php echo substr($listing['image'], strpos($listing['image'], 'uploads')); ?>" 
                                 alt="<?php echo htmlspecialchars($listing['title']); ?>" 
                                 class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <i class="fas fa-calendar-alt text-4xl text-gray-400"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Price Badge -->
                        <div class="absolute top-2 right-2 bg-green-600 text-white px-3 py-1 rounded-full text-sm font-bold">
                            <?php echo formatCurrency($listing['resale_price']); ?>
                        </div>
                        
                        <!-- Discount Badge -->
                        <?php 
                        $originalPrice = $listing['original_price'];
                        $discount = round((($originalPrice - $listing['resale_price']) / $originalPrice) * 100);
                        if ($discount > 0):
                        ?>
                            <div class="absolute top-2 left-2 bg-red-600 text-white px-2 py-1 rounded text-xs font-bold">
                                <?php echo $discount; ?>% OFF
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Listing Content -->
                    <div class="p-4">
                        <div class="mb-2">
                            <span class="inline-block bg-indigo-100 text-indigo-800 text-xs px-2 py-1 rounded-full">
                                <?php echo htmlspecialchars($listing['category']); ?>
                            </span>
                        </div>
                        
                        <h3 class="text-lg font-semibold text-gray-900 mb-2 line-clamp-2">
                            <?php echo htmlspecialchars($listing['title']); ?>
                        </h3>
                        
                        <div class="text-sm text-gray-600 mb-2">
                            <i class="fas fa-ticket-alt mr-1"></i>
                            <?php echo htmlspecialchars($listing['ticket_type_name'] ?? 'Standard Ticket'); ?>
                        </div>
                        
                        <div class="text-sm text-gray-600 mb-2">
                            <i class="far fa-calendar-alt mr-1"></i>
                            <?php echo formatDate($listing['start_date']); ?> at <?php echo formatTime($listing['start_time']); ?>
                        </div>
                        
                        <div class="text-sm text-gray-600 mb-3">
                            <i class="fas fa-map-marker-alt mr-1"></i>
                            <?php echo htmlspecialchars($listing['venue']); ?>, <?php echo htmlspecialchars($listing['city']); ?>
                        </div>
                        
                        <!-- Seller Info -->
                        <div class="text-xs text-gray-500 mb-3">
                            Sold by: <?php echo htmlspecialchars($listing['seller_name']); ?>
                        </div>
                        
                        <!-- Pricing Info -->
                        <div class="border-t pt-3 mb-3">
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-gray-500">Original Price:</span>
                                <span class="line-through text-gray-400"><?php echo formatCurrency($originalPrice); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="font-semibold">Resale Price:</span>
                                <span class="text-lg font-bold text-green-600"><?php echo formatCurrency($listing['resale_price']); ?></span>
                            </div>
                        </div>
                        
                        <!-- Action Button -->
                        <?php if (isLoggedIn()): ?>
                            <a href="buy-resale-ticket.php?id=<?php echo $listing['id']; ?>" 
                               class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white text-center font-bold py-2 px-4 rounded transition duration-300">
                                Buy Now
                            </a>
                        <?php else: ?>
                            <a href="login.php?redirect=buy-resale-ticket.php?id=<?php echo $listing['id']; ?>" 
                               class="block w-full bg-gray-400 text-white text-center font-bold py-2 px-4 rounded">
                                Login to Buy
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-8 flex justify-center">
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
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
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Marketplace Info -->
    <div class="mt-12 bg-gray-50 rounded-lg p-8">
        <div class="text-center mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">Why Choose Our Marketplace?</h2>
            <p class="text-gray-600 max-w-2xl mx-auto">
                Buy and sell tickets safely with our verified marketplace. All transactions are secure and protected.
            </p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-shield-alt text-2xl text-green-600"></i>
                </div>
                <h3 class="text-lg font-semibold mb-2">Secure Transactions</h3>
                <p class="text-gray-600">All payments are processed securely with buyer protection.</p>
            </div>
            
            <div class="text-center">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-percentage text-2xl text-blue-600"></i>
                </div>
                <h3 class="text-lg font-semibold mb-2">Fair Pricing</h3>
                <p class="text-gray-600">75% price cap ensures fair resale prices for everyone.</p>
            </div>
            
            <div class="text-center">
                <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-users text-2xl text-purple-600"></i>
                </div>
                <h3 class="text-lg font-semibold mb-2">Verified Sellers</h3>
                <p class="text-gray-600">All sellers are verified users with authentic tickets.</p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
