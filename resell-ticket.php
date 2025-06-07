<?php
$pageTitle = "Resell Ticket";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/notifications.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Please login to resell tickets.";
    redirect('login.php?redirect=resell-ticket.php');
}

$userId = getCurrentUserId();
$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ticketId <= 0) {
    $_SESSION['error_message'] = "Invalid ticket ID.";
    redirect('my-tickets.php');
}

// Get ticket details with event information
$sql = "SELECT t.*, e.title as event_title, e.start_date, e.start_time, e.venue, e.city, e.status as event_status,
               tt.name as ticket_type_name, tt.price as original_price,
               tr.id as resale_id, tr.resale_price, tr.status as resale_status
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
        LEFT JOIN ticket_resales tr ON t.id = tr.ticket_id AND tr.status = 'active'
        WHERE t.id = $ticketId AND t.user_id = $userId";

$ticket = $db->fetchOne($sql);

if (!$ticket) {
    $_SESSION['error_message'] = "Ticket not found or you don't have permission to resell it.";
    redirect('my-tickets.php');
}

// Check if ticket can be resold
$errors = [];
$canResell = true;

// Check if event has already started
if (strtotime($ticket['start_date'] . ' ' . $ticket['start_time']) <= time()) {
    $errors[] = "Cannot resell tickets for events that have already started.";
    $canResell = false;
}

// Check if event is active
if ($ticket['event_status'] !== 'active') {
    $errors[] = "Cannot resell tickets for inactive events.";
    $canResell = false;
}

// Check if ticket is already being resold
if ($ticket['resale_id']) {
    $errors[] = "This ticket is already listed for resale.";
    $canResell = false;
}

// Check if ticket has been used
if ($ticket['status'] === 'used') {
    $errors[] = "Cannot resell used tickets.";
    $canResell = false;
}

// Get resale fee percentage
$resaleFeeQuery = "SELECT percentage FROM system_fees WHERE fee_type = 'resale'";
$resaleFeeResult = $db->fetchOne($resaleFeeQuery);
$resaleFeePercentage = $resaleFeeResult ? $resaleFeeResult['percentage'] : 3.0;

// Calculate maximum resale price (75% cap)
$originalPrice = $ticket['original_price'] ?? $ticket['purchase_price'];
$maxResalePrice = $originalPrice * 0.75;

// Process resale listing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['list_for_resale']) && $canResell) {
    $resalePrice = (float)$_POST['resale_price'];
    $description = trim($_POST['description'] ?? '');
    
    // Validate resale price
    if ($resalePrice <= 0) {
        $errors[] = "Please enter a valid resale price.";
    } elseif ($resalePrice > $maxResalePrice) {
        $errors[] = "Resale price cannot exceed 75% of the original price (" . formatCurrency($maxResalePrice) . ").";
    }
    
    // Calculate platform fee and seller earnings
    $platformFee = ($resalePrice * $resaleFeePercentage) / 100;
    $sellerEarnings = $resalePrice - $platformFee;
    
    if (empty($errors)) {
        try {
            $db->query("START TRANSACTION");
            
            // Update ticket status to reselling
            $updateTicketSql = "UPDATE tickets SET status = 'reselling' WHERE id = $ticketId";
            $db->query($updateTicketSql);
            
            // Create resale listing
            $insertResaleSql = "INSERT INTO ticket_resales (ticket_id, seller_id, resale_price, platform_fee, seller_earnings, description, status, listed_at)
                               VALUES ($ticketId, $userId, $resalePrice, $platformFee, $sellerEarnings, '" . $db->escape($description) . "', 'active', NOW())";
            $resaleId = $db->insert($insertResaleSql);
            
            // Create notification
            createNotification($userId, "Ticket Listed for Resale", 
                "Your ticket for '" . $ticket['event_title'] . "' has been listed for resale at " . formatCurrency($resalePrice) . ".", 'ticket');
            
            $db->query("COMMIT");
            
            $_SESSION['success_message'] = "Your ticket has been successfully listed for resale!";
            redirect('marketplace.php');
            
        } catch (Exception $e) {
            $db->query("ROLLBACK");
            error_log("Resale listing error: " . $e->getMessage());
            $errors[] = "An error occurred while listing your ticket. Please try again.";
        }
    }
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Page Header -->
    <div class="mb-8">
        <nav class="text-sm breadcrumbs mb-4">
            <a href="customer/tickets.php" class="text-indigo-600 hover:text-indigo-800">My Tickets</a>
            <span class="mx-2 text-gray-500">></span>
            <span class="text-gray-700">Resell Ticket</span>
        </nav>
        <h1 class="text-3xl font-bold text-gray-900">Resell Your Ticket</h1>
        <p class="text-gray-600 mt-2">List your ticket for resale on our marketplace</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <h4 class="font-bold">Cannot Resell Ticket:</h4>
            <ul class="list-disc pl-5 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Ticket Information -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Ticket Details</h2>
                </div>
                
                <div class="p-6">
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0 w-16 h-16 bg-indigo-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-ticket-alt text-2xl text-indigo-600"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($ticket['event_title']); ?></h3>
                            <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($ticket['ticket_type_name'] ?? 'Standard Ticket'); ?></p>
                            
                            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <div class="text-sm text-gray-500">Date & Time</div>
                                    <div class="font-medium"><?php echo formatDate($ticket['start_date']); ?> at <?php echo formatTime($ticket['start_time']); ?></div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">Venue</div>
                                    <div class="font-medium"><?php echo htmlspecialchars($ticket['venue']); ?>, <?php echo htmlspecialchars($ticket['city']); ?></div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">Original Price</div>
                                    <div class="font-medium"><?php echo formatCurrency($originalPrice); ?></div>
                                </div>
                                <div>
                                    <div class="text-sm text-gray-500">Ticket ID</div>
                                    <div class="font-medium">#<?php echo $ticket['id']; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($canResell): ?>
                <!-- Resale Form -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-green-600 text-white px-6 py-4">
                        <h2 class="text-xl font-bold">List for Resale</h2>
                    </div>
                    
                    <form method="POST" action="" class="p-6">
                        <div class="mb-6">
                            <label for="resale_price" class="block text-sm font-medium text-gray-700 mb-2">
                                Resale Price <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">Rwf</span>
                                <input type="number" id="resale_price" name="resale_price" 
                                       min="1" max="<?php echo $maxResalePrice; ?>" step="0.01"
                                       class="w-full pl-12 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                       placeholder="Enter resale price" required>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">
                                Maximum allowed: <?php echo formatCurrency($maxResalePrice); ?> (75% of original price)
                            </p>
                        </div>

                        <div class="mb-6">
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                                Description (Optional)
                            </label>
                            <textarea id="description" name="description" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                      placeholder="Add any additional information about your ticket..."></textarea>
                        </div>

                        <!-- Pricing Breakdown -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <h4 class="font-semibold text-gray-900 mb-3">Pricing Breakdown</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span>Resale Price:</span>
                                    <span id="display-resale-price">Rwf 0.00</span>
                                </div>
                                <div class="flex justify-between text-red-600">
                                    <span>Platform Fee (<?php echo $resaleFeePercentage; ?>%):</span>
                                    <span id="display-platform-fee">-Rwf 0.00</span>
                                </div>
                                <div class="border-t border-gray-300 pt-2 flex justify-between font-semibold">
                                    <span>You'll Receive:</span>
                                    <span id="display-earnings" class="text-green-600">Rwf 0.00</span>
                                </div>
                            </div>
                        </div>

                        <!-- Terms and Conditions -->
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                            <h4 class="font-semibold text-yellow-800 mb-2">Resale Terms</h4>
                            <ul class="text-sm text-yellow-700 space-y-1">
                                <li>• Maximum resale price is 75% of the original ticket price</li>
                                <li>• Platform fee of <?php echo $resaleFeePercentage; ?>% will be deducted from your earnings</li>
                                <li>• Once sold, the ticket will be transferred to the buyer</li>
                                <li>• You can cancel the listing anytime before it's sold</li>
                                <li>• Earnings will be added to your account balance after sale</li>
                            </ul>
                        </div>

                        <div class="flex space-x-4">
                            <button type="submit" name="list_for_resale" 
                                    class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300">
                                <i class="fas fa-tags mr-2"></i> List for Resale
                            </button>
                            <a href="customer/tickets.php" 
                               class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-bold py-3 px-6 rounded-lg text-center transition duration-300">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <!-- Resale Guidelines -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-blue-600 text-white px-6 py-4">
                    <h3 class="text-lg font-bold">Resale Guidelines</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4 text-sm">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-percentage text-blue-600 mt-1"></i>
                            <div>
                                <h4 class="font-semibold">75% Price Cap</h4>
                                <p class="text-gray-600">Resale prices are capped at 75% of the original price to ensure fair pricing.</p>
                            </div>
                        </div>
                                              <div class="flex items-start space-x-3">
                            <i class="fas fa-shield-alt text-blue-600 mt-1"></i>
                            <div>
                                <h4 class="font-semibold">Secure Transfer</h4>
                                <p class="text-gray-600">All ticket transfers are secure and verified through our platform.</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-clock text-blue-600 mt-1"></i>
                            <div>
                                <h4 class="font-semibold">Quick Sales</h4>
                                <p class="text-gray-600">Most tickets sell within 24-48 hours of listing.</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-money-bill-wave text-blue-600 mt-1"></i>
                            <div>
                                <h4 class="font-semibold">Instant Payments</h4>
                                <p class="text-gray-600">Receive payment immediately after your ticket is sold.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Market Statistics -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-purple-600 text-white px-6 py-4">
                    <h3 class="text-lg font-bold">Market Stats</h3>
                </div>
                <div class="p-6">
                    <?php
                    // Get marketplace statistics
                    $statsQuery = "SELECT 
                                    COUNT(*) as total_listings,
                                    COUNT(CASE WHEN status = 'sold' THEN 1 END) as sold_listings,
                                    AVG(resale_price) as avg_price
                                   FROM ticket_resales 
                                   WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    $stats = $db->fetchOne($statsQuery);
                    
                    $successRate = $stats['total_listings'] > 0 ? 
                        round(($stats['sold_listings'] / $stats['total_listings']) * 100) : 0;
                    ?>
                    
                    <div class="space-y-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600"><?php echo $successRate; ?>%</div>
                            <div class="text-sm text-gray-600">Success Rate</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600"><?php echo $stats['total_listings']; ?></div>
                            <div class="text-sm text-gray-600">Active Listings</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600"><?php echo formatCurrency($stats['avg_price'] ?? 0); ?></div>
                            <div class="text-sm text-gray-600">Average Price</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Help & Support -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-gray-600 text-white px-6 py-4">
                    <h3 class="text-lg font-bold">Need Help?</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-3 text-sm">
                        <a href="#" class="flex items-center text-gray-600 hover:text-indigo-600">
                            <i class="fas fa-question-circle mr-2"></i>
                            Resale FAQ
                        </a>
                        <a href="#" class="flex items-center text-gray-600 hover:text-indigo-600">
                            <i class="fas fa-headset mr-2"></i>
                            Contact Support
                        </a>
                        <a href="#" class="flex items-center text-gray-600 hover:text-indigo-600">
                            <i class="fas fa-file-alt mr-2"></i>
                            Terms & Conditions
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const resalePriceInput = document.getElementById('resale_price');
    const displayResalePrice = document.getElementById('display-resale-price');
    const displayPlatformFee = document.getElementById('display-platform-fee');
    const displayEarnings = document.getElementById('display-earnings');
    
    const feePercentage = <?php echo $resaleFeePercentage; ?>;
    
    function updatePricingBreakdown() {
        const resalePrice = parseFloat(resalePriceInput.value) || 0;
        const platformFee = (resalePrice * feePercentage) / 100;
        const earnings = resalePrice - platformFee;
        
        displayResalePrice.textContent = 'Rwf ' + resalePrice.toFixed(2);
        displayPlatformFee.textContent = '-Rwf ' + platformFee.toFixed(2);
        displayEarnings.textContent = 'Rwf ' + earnings.toFixed(2);
    }
    
    resalePriceInput.addEventListener('input', updatePricingBreakdown);
    
    // Initialize display
    updatePricingBreakdown();
});
</script>

<?php include 'includes/footer.php'; ?>
