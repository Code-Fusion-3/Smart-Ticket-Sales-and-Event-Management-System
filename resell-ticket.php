<?php
$pageTitle = "Resell Ticket";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
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
    redirect('customer/tickets.php');
}

// Get ticket details with event information
$sql = "SELECT t.*, e.title, e.start_date, e.start_time, e.venue, e.city, e.status as event_status,
               COALESCE(tt.name, 'Standard Ticket') as ticket_name,
               COALESCE(tt.price, e.ticket_price) as original_price,
               tr.id as resale_id, tr.resale_price, tr.status as resale_status
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
        LEFT JOIN ticket_resales tr ON t.id = tr.ticket_id AND tr.status = 'active'
        WHERE t.id = $ticketId AND t.user_id = $userId";

$ticket = $db->fetchOne($sql);

if (!$ticket) {
    $_SESSION['error_message'] = "Ticket not found or you don't have permission to resell it.";
    redirect('customer/tickets.php');
}

// Check if ticket can be resold
$errors = [];
$canResell = true;

// Check if event has already started
if (strtotime($ticket['start_date']) <= time()) {
    $errors[] = "Cannot resell tickets for events that have already started.";
    $canResell = false;
}

// Check if event is active
if ($ticket['event_status'] !== 'active') {
    $errors[] = "Cannot resell tickets for inactive events.";
    $canResell = false;
}

// Check if ticket is already used
if ($ticket['status'] === 'used') {
    $errors[] = "Cannot resell used tickets.";
    $canResell = false;
}

// Check if ticket is already being resold
if ($ticket['resale_id']) {
    $errors[] = "This ticket is already listed for resale.";
    $canResell = false;
}

// Get resale settings
$resaleSettings = [];
$settingsSql = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('resale_enabled', 'resale_max_percentage', 'resale_fee_percentage')";
$settings = $db->fetchAll($settingsSql);
foreach ($settings as $setting) {
    $resaleSettings[$setting['setting_key']] = $setting['setting_value'];
}

// Default settings if not found
$resaleEnabled = ($resaleSettings['resale_enabled'] ?? '1') == '1';
$maxResalePercentage = (float)($resaleSettings['resale_max_percentage'] ?? '175'); // 175% = 75% markup
$resaleFeePercentage = (float)($resaleSettings['resale_fee_percentage'] ?? '10');

if (!$resaleEnabled) {
    $errors[] = "Ticket resale is currently disabled.";
    $canResell = false;
}

// Calculate price limits
$originalPrice = (float)$ticket['original_price'];
$maxResalePrice = ($originalPrice * $maxResalePercentage) / 100;
$minResalePrice = $originalPrice * 0.5; // Minimum 50% of original price

// Process resale listing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['list_for_resale']) && $canResell) {
    $resalePrice = (float)($_POST['resale_price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    
    // Validate resale price
    if ($resalePrice < $minResalePrice) {
        $errors[] = "Resale price cannot be less than " . formatCurrency($minResalePrice) . " (50% of original price).";
    } elseif ($resalePrice > $maxResalePrice) {
        $errors[] = "Resale price cannot exceed " . formatCurrency($maxResalePrice) . " (" . $maxResalePercentage . "% of original price).";
    }
    
    if (empty($errors)) {
        try {
            $db->query("BEGIN");
            
            // Create resale listing
            $insertSql = "INSERT INTO ticket_resales (ticket_id, seller_id, resale_price, description, status, created_at)
                         VALUES ($ticketId, $userId, $resalePrice, '" . $db->escape($description) . "', 'active', NOW())";
            $resaleId = $db->insert($insertSql);
            
            // Update ticket status
            $updateSql = "UPDATE tickets SET status = 'reselling' WHERE id = $ticketId";
            $db->query($updateSql);
            
            // Create notification
            $notificationTitle = "Ticket Listed for Resale";
            $notificationMessage = "Your ticket for '" . $ticket['title'] . "' has been listed for resale at " . formatCurrency($resalePrice) . ".";
            createNotification($userId, $notificationTitle, $notificationMessage, 'ticket');
            
            $db->query("COMMIT");
            
            $_SESSION['success_message'] = "Your ticket has been successfully listed for resale!";
            redirect('customer/tickets.php');
            
        } catch (Exception $e) {
            $db->query("ROLLBACK");
            $errors[] = "Failed to list ticket for resale. Please try again.";
            error_log("Resale listing error: " . $e->getMessage());
        }
    }
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Page Header -->
        <div class="mb-8">
            <nav class="text-sm breadcrumbs mb-4">
                <a href="customer/tickets.php" class="text-indigo-600 hover:text-indigo-800">My Tickets</a>
                <span class="mx-2 text-gray-500">›</span>
                <span class="text-gray-500">Resell Ticket</span>
            </nav>
            <h1 class="text-3xl font-bold text-gray-900">Resell Your Ticket</h1>
            <p class="text-gray-600 mt-2">List your ticket for resale in our marketplace</p>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <h4 class="font-bold mb-2">Cannot Resell Ticket:</h4>
                <ul class="list-disc pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
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
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-4"><?php echo htmlspecialchars($ticket['title']); ?></h3>
                                
                                <div class="space-y-3">
                                    <div class="flex items-center text-gray-600">
                                        <i class="fas fa-ticket-alt w-5 mr-3 text-indigo-600"></i>
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($ticket['ticket_name']); ?></div>
                                            <div class="text-sm">Ticket #<?php echo $ticket['id']; ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center text-gray-600">
                                        <i class="far fa-calendar-alt w-5 mr-3 text-indigo-600"></i>
                                        <div>
                                            <div class="font-medium"><?php echo formatDate($ticket['start_date']); ?></div>
                                            <div class="text-sm"><?php echo formatTime($ticket['start_time']); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center text-gray-600">
                                        <i class="fas fa-map-marker-alt w-5 mr-3 text-indigo-600"></i>
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($ticket['venue']); ?></div>
                                            <div class="text-sm"><?php echo htmlspecialchars($ticket['city']); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center text-gray-600">
                                        <i class="fas fa-user w-5 mr-3 text-indigo-600"></i>
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($ticket['recipient_name']); ?></div>
                                            <div class="text-sm">Ticket Holder</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h4 class="font-semibold text-gray-900 mb-3">Purchase Information</h4>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Original Price:</span>
                                            <span class="font-medium"><?php echo formatCurrency($ticket['original_price']); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Purchase Date:</span>
                                            <span class="font-medium"><?php echo formatDate($ticket['created_at']); ?></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600">Current Status:</span>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                <?php echo $ticket['status'] === 'sold' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                                <?php echo ucfirst($ticket['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resale Form -->
                <?php if ($canResell): ?>
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
                                <input type="number" 
                                       id="resale_price" 
                                       name="resale_price" 
                                       min="<?php echo $minResalePrice; ?>" 
                                       max="<?php echo $maxResalePrice; ?>" 
                                       step="0.01"
                                       value="<?php echo $originalPrice; ?>"
                                       class="w-full pl-12 pr-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                       required>
                            </div>
                            <div class="mt-2 text-sm text-gray-600">
                                <div class="flex justify-between">
                                    <span>Minimum: <?php echo formatCurrency($minResalePrice); ?></span>
                                    <span>Maximum: <?php echo formatCurrency($maxResalePrice); ?></span>
                                </div>
                                <div class="mt-1 text-xs text-gray-500">
                                    Platform fee (<?php echo $resaleFeePercentage; ?>%) will be deducted from your earnings
                                </div>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                                Description (Optional)
                            </label>
                            <textarea id="description" 
                                      name="description" 
                                      rows="3" 
                                      maxlength="500"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                      placeholder="Add any additional information about your ticket..."></textarea>
                            <div class="mt-1 text-xs text-gray-500">Maximum 500 characters</div>
                        </div>

                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                            <h4 class="font-semibold text-yellow-800 mb-2">⚠️ Important Terms</h4>
                            <ul class="text-sm text-yellow-700 space-y-1">
                                <li>• Once listed, your ticket will be available for purchase by other users</li>
                                                               <li>• A <?php echo $resaleFeePercentage; ?>% platform fee will be deducted from your sale</li>
                                <li>• You cannot cancel the listing once a buyer has purchased it</li>
                                <li>• The ticket will be transferred to the buyer upon successful payment</li>
                                <li>• You will receive payment minus fees within 24-48 hours</li>
                                <li>• Fraudulent listings may result in account suspension</li>
                            </ul>
                        </div>

                        <div class="flex items-center justify-between">
                            <a href="my-tickets.php" class="bg-gray-300 hover:bg-gray-400 text-gray-700 font-bold py-2 px-6 rounded transition duration-300">
                                Cancel
                            </a>
                            <button type="submit" name="list_for_resale" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded transition duration-300">
                                List for Resale
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pricing Calculator Sidebar -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md overflow-hidden sticky top-4">
                    <div class="bg-gray-800 text-white px-6 py-4">
                        <h3 class="text-lg font-bold">Pricing Calculator</h3>
                    </div>
                    
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Original Price:</span>
                                <span class="font-medium"><?php echo formatCurrency($originalPrice); ?></span>
                            </div>
                            
                            <div class="flex justify-between">
                                <span class="text-gray-600">Your Listing Price:</span>
                                <span class="font-medium" id="listing-price"><?php echo formatCurrency($originalPrice); ?></span>
                            </div>
                            
                            <div class="flex justify-between text-red-600">
                                <span>Platform Fee (<?php echo $resaleFeePercentage; ?>%):</span>
                                <span id="platform-fee">-<?php echo formatCurrency($originalPrice * $resaleFeePercentage / 100); ?></span>
                            </div>
                            
                            <div class="border-t pt-4 flex justify-between font-bold text-lg">
                                <span>You'll Receive:</span>
                                <span class="text-green-600" id="net-earnings"><?php echo formatCurrency($originalPrice * (100 - $resaleFeePercentage) / 100); ?></span>
                            </div>
                            
                            <div class="mt-4 p-3 bg-blue-50 rounded-lg">
                                <div class="text-sm text-blue-800">
                                    <div class="flex justify-between">
                                        <span>Profit/Loss:</span>
                                        <span id="profit-loss" class="font-medium">Rwf0.00</span>
                                    </div>
                                    <div class="flex justify-between mt-1">
                                        <span>Percentage:</span>
                                        <span id="profit-percentage" class="font-medium">0%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 text-xs text-gray-500">
                            <h4 class="font-semibold mb-2">Quick Price Suggestions:</h4>
                            <div class="space-y-1">
                                <button type="button" onclick="setPrice(<?php echo $originalPrice; ?>)" class="block w-full text-left hover:bg-gray-100 p-1 rounded">
                                    Same as original: <?php echo formatCurrency($originalPrice); ?>
                                </button>
                                <button type="button" onclick="setPrice(<?php echo $originalPrice * 1.1; ?>)" class="block w-full text-left hover:bg-gray-100 p-1 rounded">
                                    10% markup: <?php echo formatCurrency($originalPrice * 1.1); ?>
                                </button>
                                <button type="button" onclick="setPrice(<?php echo $originalPrice * 1.25; ?>)" class="block w-full text-left hover:bg-gray-100 p-1 rounded">
                                    25% markup: <?php echo formatCurrency($originalPrice * 1.25); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Market Information -->
                <?php
                // Get similar tickets for sale
                $marketSql = "SELECT tr.resale_price, tr.created_at
                             FROM ticket_resales tr
                             JOIN tickets t ON tr.ticket_id = t.id
                             WHERE t.event_id = " . $ticket['event_id'] . "
                             AND tr.status = 'active'
                             AND tr.ticket_id != $ticketId
                             ORDER BY tr.created_at DESC
                             LIMIT 5";
                $marketData = $db->fetchAll($marketSql);
                ?>
                
                <?php if (!empty($marketData)): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden mt-6">
                    <div class="bg-indigo-100 px-6 py-4">
                        <h3 class="text-lg font-bold text-indigo-800">Market Prices</h3>
                        <p class="text-sm text-indigo-600">Similar tickets for this event</p>
                    </div>
                    
                    <div class="p-6">
                        <div class="space-y-3">
                            <?php foreach ($marketData as $market): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Listed <?php echo timeAgo($market['created_at']); ?></span>
                                <span class="font-medium"><?php echo formatCurrency($market['resale_price']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php
                        $avgPrice = array_sum(array_column($marketData, 'resale_price')) / count($marketData);
                        ?>
                        <div class="mt-4 pt-4 border-t">
                            <div class="flex justify-between font-semibold">
                                <span>Average Price:</span>
                                <span class="text-indigo-600"><?php echo formatCurrency($avgPrice); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const priceInput = document.getElementById('resale_price');
    const listingPriceEl = document.getElementById('listing-price');
    const platformFeeEl = document.getElementById('platform-fee');
    const netEarningsEl = document.getElementById('net-earnings');
    const profitLossEl = document.getElementById('profit-loss');
    const profitPercentageEl = document.getElementById('profit-percentage');
    
    const originalPrice = <?php echo $originalPrice; ?>;
    const feePercentage = <?php echo $resaleFeePercentage; ?>;
    
    function updateCalculations() {
        const listingPrice = parseFloat(priceInput.value) || 0;
        const platformFee = (listingPrice * feePercentage) / 100;
        const netEarnings = listingPrice - platformFee;
        const profitLoss = netEarnings - originalPrice;
        const profitPercentage = originalPrice > 0 ? ((profitLoss / originalPrice) * 100) : 0;
        
        listingPriceEl.textContent = 'Rwf' + listingPrice.toFixed(2);
        platformFeeEl.textContent = '-Rwf' + platformFee.toFixed(2);
        netEarningsEl.textContent = 'Rwf' + netEarnings.toFixed(2);
        
        profitLossEl.textContent = (profitLoss >= 0 ? '+' : '') + 'Rwf' + profitLoss.toFixed(2);
        profitLossEl.className = 'font-medium ' + (profitLoss >= 0 ? 'text-green-600' : 'text-red-600');
        
        profitPercentageEl.textContent = (profitPercentage >= 0 ? '+' : '') + profitPercentage.toFixed(1) + '%';
        profitPercentageEl.className = 'font-medium ' + (profitPercentage >= 0 ? 'text-green-600' : 'text-red-600');
    }
    
    priceInput.addEventListener('input', updateCalculations);
    
    // Initialize calculations
    updateCalculations();
});

function setPrice(price) {
    const priceInput = document.getElementById('resale_price');
    const maxPrice = <?php echo $maxResalePrice; ?>;
    const minPrice = <?php echo $minResalePrice; ?>;
    
    // Ensure price is within limits
    if (price > maxPrice) price = maxPrice;
    if (price < minPrice) price = minPrice;
    
    priceInput.value = price.toFixed(2);
    priceInput.dispatchEvent(new Event('input'));
}
</script>

<?php include 'includes/footer.php'; ?>
