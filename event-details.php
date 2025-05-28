<?php
$pageTitle = "Event Details";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Get event ID from URL
$eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($eventId <= 0) {
    $_SESSION['error_message'] = "Invalid event ID.";
    redirect('events.php');
}

// Get event details
$sql = "SELECT e.*, u.username as planner_name, u.id as planner_id 
        FROM events e 
        JOIN users u ON e.planner_id = u.id 
        WHERE e.id = $eventId";
$event = $db->fetchOne($sql);

if (!$event) {
    $_SESSION['error_message'] = "Event not found.";
    redirect('events.php');
}

// Check if event is active
if ($event['status'] !== 'active') {
    $_SESSION['error_message'] = "This event is not currently available.";
    redirect('events.php');
}

// Get ticket types for this event
$ticketTypesSql = "SELECT * FROM ticket_types WHERE event_id = $eventId ORDER BY price ASC";
$ticketTypes = $db->fetchAll($ticketTypesSql);

// If no specific ticket types, create a default one based on event data
if (empty($ticketTypes)) {
    $ticketTypes = [[
        'id' => 0,
        'name' => 'Standard Ticket',
        'price' => $event['ticket_price'],
        'description' => 'Standard entry ticket',
        'available_tickets' => $event['available_tickets'],
        'total_tickets' => $event['total_tickets']
    ]];
}

// Process add to cart
$addedToCart = false;
$errors = [];
$cartItems = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    // Check if user is logged in
    if (!isLoggedIn()) {
        // Store intended action in session
        $_SESSION['redirect_after_login'] = "event-details.php?id=$eventId";
        redirect('login.php');
    }
    
    $userId = getCurrentUserId();
    $totalItemsAdded = 0;
    
    // Process each ticket type
    foreach ($ticketTypes as $ticketType) {
        $ticketTypeId = $ticketType['id'];
        $quantityKey = 'quantity_' . $ticketTypeId;
        $quantity = isset($_POST[$quantityKey]) ? (int)$_POST[$quantityKey] : 0;
        
        if ($quantity > 0) {
            // Validate quantity
            if ($quantity > $ticketType['available_tickets']) {
                $errors[] = "Sorry, only " . $ticketType['available_tickets'] . " tickets available for " . htmlspecialchars($ticketType['name']);
                continue;
            }
            
            if ($quantity > 10) {
                $errors[] = "Maximum 10 tickets per type allowed for " . htmlspecialchars($ticketType['name']);
                continue;
            }
            
            // Check if user already has a cart
            $cartSql = "SELECT id FROM cart WHERE user_id = $userId";
            $cartResult = $db->fetchOne($cartSql);
            
            if ($cartResult) {
                $cartId = $cartResult['id'];
            } else {
                // Create new cart
                $createCartSql = "INSERT INTO cart (user_id, created_at) VALUES ($userId, NOW())";
                $cartId = $db->insert($createCartSql);
            }
            
            // Check if this ticket type is already in cart
            $existingItemSql = "SELECT id, quantity FROM cart_items 
                               WHERE cart_id = $cartId AND event_id = $eventId AND ticket_type_id = $ticketTypeId";
            $existingItem = $db->fetchOne($existingItemSql);
            
            if ($existingItem) {
                // Update existing cart item
                $newQuantity = $existingItem['quantity'] + $quantity;
                if ($newQuantity > $ticketType['available_tickets']) {
                    $errors[] = "Cannot add more " . htmlspecialchars($ticketType['name']) . " tickets. Only " . $ticketType['available_tickets'] . " available.";
                    continue;
                }
                
                $updateItemSql = "UPDATE cart_items SET quantity = $newQuantity, updated_at = NOW() 
                                 WHERE id = " . $existingItem['id'];
                $db->query($updateItemSql);
            } else {
                // Add new item to cart
                $addItemSql = "INSERT INTO cart_items (cart_id, event_id, ticket_type_id, quantity, created_at) 
                              VALUES ($cartId, $eventId, $ticketTypeId, $quantity, NOW())";
                $db->query($addItemSql);
            }
            
            $cartItems[] = [
                'name' => $ticketType['name'],
                'quantity' => $quantity,
                'price' => $ticketType['price']
            ];
            $totalItemsAdded += $quantity;
        }
    }
    
    if (empty($errors) && $totalItemsAdded > 0) {
        $addedToCart = true;
    } elseif ($totalItemsAdded === 0 && empty($errors)) {
        $errors[] = "Please select at least one ticket.";
    }
}

// Get similar events
$similarSql = "SELECT e.*, u.username as planner_name 
               FROM events e 
               JOIN users u ON e.planner_id = u.id 
               WHERE e.id != $eventId 
               AND e.status = 'active' 
               AND e.start_date >= CURDATE()";

// Add category filter if event has a category
if (!empty($event['category'])) {
    $similarSql .= " AND e.category = '" . $db->escape($event['category']) . "'";
}

$similarSql .= " ORDER BY e.start_date ASC LIMIT 3";
$similarEvents = $db->fetchAll($similarSql);

// Calculate total available tickets
$totalAvailableTickets = 0;
foreach ($ticketTypes as $type) {
    $totalAvailableTickets += $type['available_tickets'];
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <?php if ($addedToCart): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
        <div class="flex">
            <div class="py-1"><i class="fas fa-check-circle mr-2"></i></div>
            <div>
                <p class="font-bold">Tickets added to cart!</p>
                <div class="text-sm mt-1">
                    <?php foreach ($cartItems as $item): ?>
                    <div><?php echo $item['quantity']; ?>x <?php echo htmlspecialchars($item['name']); ?> -
                        <?php echo formatCurrency($item['price'] * $item['quantity']); ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <a href="cart.php"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-block mr-2">
                        View Cart
                    </a>
                    <a href="events.php"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded inline-block">
                        Browse More Events
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
        <p class="font-bold">Please fix the following errors:</p>
        <ul class="list-disc pl-5 mt-2">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Event Details -->
        <div class="lg:w-2/3">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <!-- Event Image -->
                <div class="h-64 md:h-96 bg-gray-300 relative">
                    <?php if (!empty($event['image'])): ?>
                    <img src="<?php echo SITE_URL; ?>/uploads/events/<?php echo $event['image']; ?>"
                        alt="<?php echo htmlspecialchars($event['title']); ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                    <div
                        class="w-full h-full flex items-center justify-center bg-gradient-to-br from-indigo-400 to-purple-500">
                        <i class="fas fa-calendar-alt text-white text-6xl"></i>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($event['category'])): ?>
                    <div class="absolute top-0 left-0 bg-indigo-600 text-white px-4 py-2 m-4 rounded-lg">
                        <?php echo htmlspecialchars($event['category']); ?>
                    </div>
                    <?php endif; ?>

                    <!-- Event Status Badge -->
                    <div class="absolute top-0 right-0 m-4">
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
                            class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php echo $statusClass; ?>">
                            <?php echo ucfirst($event['status']); ?>
                        </span>
                    </div>
                </div>

                <!-- Event Info -->
                <div class="p-6">
                    <h1 class="text-3xl font-bold mb-4"><?php echo htmlspecialchars($event['title']); ?></h1>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="flex items-center text-gray-600">
                            <i class="far fa-calendar-alt mr-3 text-indigo-600 text-lg"></i>
                            <div>
                                <div class="font-medium">Date</div>
                                <div class="text-sm">
                                    <?php echo formatDate($event['start_date']); ?>
                                    <?php if ($event['start_date'] !== $event['end_date']): ?>
                                    - <?php echo formatDate($event['end_date']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center text-gray-600">
                            <i class="far fa-clock mr-3 text-indigo-600 text-lg"></i>
                            <div>
                                <div class="font-medium">Time</div>
                                <div class="text-sm"><?php echo formatTime($event['start_time']); ?> -
                                    <?php echo formatTime($event['end_time']); ?></div>
                            </div>
                        </div>

                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-map-marker-alt mr-3 text-indigo-600 text-lg"></i>
                            <div>
                                <div class="font-medium">Venue</div>
                                <div class="text-sm"><?php echo htmlspecialchars($event['venue']); ?>,
                                    <?php echo htmlspecialchars($event['city']); ?></div>
                            </div>
                        </div>

                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-user mr-3 text-indigo-600 text-lg"></i>
                            <div>
                                <div class="font-medium">Organizer</div>
                                <div class="text-sm"><?php echo htmlspecialchars($event['planner_name']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-8">
                        <h2 class="text-xl font-semibold mb-3">About This Event</h2>
                        <div class="text-gray-700 leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($event['description'] ?? 'No description available.')); ?>
                        </div>
                    </div>

                    <div class="mb-8">
                        <h2 class="text-xl font-semibold mb-3">Venue Information</h2>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <h3 class="font-bold mb-2"><?php echo htmlspecialchars($event['venue']); ?></h3>
                            <p class="text-gray-700 mb-2"><?php echo htmlspecialchars($event['address'] ?? ''); ?></p>
                            <p class="text-gray-700"><?php echo htmlspecialchars($event['city']); ?>,
                                <?php echo htmlspecialchars($event['country']); ?></p>

                            <?php if (!empty($event['address'])): ?>
                            <div class="mt-4">
                                <a href="https://maps.google.com/?q=<?php echo urlencode($event['address'] . ', ' . $event['city'] . ', ' . $event['country']); ?>"
                                    target="_blank" class="text-indigo-600 hover:text-indigo-800">
                                    <i class="fas fa-map-marked-alt mr-2"></i> View on Google Maps
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Social Sharing -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-semibold mb-3">Share This Event</h3>
                        <div class="flex flex-wrap gap-3">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . '/event-details.php?id=' . $eventId); ?>"
                                target="_blank"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-full transition duration-300">
                                <i class="fab fa-facebook-f mr-2"></i> Share
                            </a>
                            <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('Check out this event: ' . $event['title']); ?>&url=<?php echo urlencode(SITE_URL . '/event-details.php?id=' . $eventId); ?>"
                                target="_blank"
                                class="bg-blue-400 hover:bg-blue-500 text-white px-4 py-2 rounded-full transition duration-300">
                                <i class="fab fa-twitter mr-2"></i> Tweet
                            </a>
                            <a href="mailto:?subject=<?php echo urlencode('Check out this event: ' . $event['title']); ?>&body=<?php echo urlencode('I thought you might be interested in this event: ' . $event['title'] . '\n\n' . SITE_URL . '/event-details.php?id=' . $eventId); ?>"
                                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-full transition duration-300">
                                <i class="fas fa-envelope mr-2"></i> Email
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ticket Purchase Section -->
        <div class="lg:w-1/3">
            <div class="bg-white rounded-lg shadow-md overflow-hidden sticky top-4">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Get Tickets</h2>
                    <p class="text-indigo-100 text-sm mt-1">
                        <?php echo number_format($totalAvailableTickets); ?> tickets available
                    </p>
                </div>

                <div class="p-6">
                    <?php if ($totalAvailableTickets <= 0): ?>
                    <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-4">
                        <p class="font-bold">Sold Out!</p>
                        <p>Sorry, there are no more tickets available for this event.</p>
                    </div>
                    <?php elseif (strtotime($event['start_date']) < time()): ?>
                    <div class="bg-yellow-100 text-yellow-700 p-4 rounded-lg mb-4">
                        <p class="font-bold">Event Has Started</p>
                        <p>This event has already begun. Ticket sales may be closed.</p>
                    </div>
                    <?php else: ?>
                    <form method="POST" action="" id="ticket-form">
                        <div class="space-y-6">
                            <?php foreach ($ticketTypes as $index => $ticketType): ?>
                            <div
                                class="border border-gray-200 rounded-lg p-4 hover:border-indigo-300 transition duration-300">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="flex-1">
                                        <h3 class="font-bold text-lg text-gray-900">
                                            <?php echo htmlspecialchars($ticketType['name']); ?></h3>
                                        <?php if (!empty($ticketType['description'])): ?>
                                        <p class="text-sm text-gray-600 mt-1">
                                            <?php echo htmlspecialchars($ticketType['description']); ?></p>
                                        <?php endif; ?>
                                        <div class="mt-2">
                                            <span class="text-sm text-gray-500">
                                                <?php echo number_format($ticketType['available_tickets']); ?> of
                                                <?php echo number_format($ticketType['total_tickets'] ?? $ticketType['available_tickets']); ?>
                                                available
                                            </span>
                                        </div>
                                    </div>
                                    <div class="text-right ml-4">
                                        <div class="text-2xl font-bold text-indigo-600">
                                            <?php echo formatCurrency($ticketType['price']); ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($ticketType['available_tickets'] > 0): ?>
                                <div class="flex items-center justify-between">
                                    <label for="quantity_<?php echo $ticketType['id']; ?>"
                                        class="text-sm font-medium text-gray-700">
                                        Quantity:
                                    </label>
                                    <div class="flex items-center">
                                        <button type="button"
                                            class="decrease-btn bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-1 px-3 rounded-l"
                                            data-ticket-id="<?php echo $ticketType['id']; ?>">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" id="quantity_<?php echo $ticketType['id']; ?>"
                                            name="quantity_<?php echo $ticketType['id']; ?>" value="0" min="0"
                                            max="<?php echo min(10, $ticketType['available_tickets']); ?>"
                                            class="quantity-input w-16 text-center py-1 border-t border-b border-gray-300 focus:outline-none focus:border-indigo-500"
                                            data-price="<?php echo $ticketType['price']; ?>"
                                            data-ticket-id="<?php echo $ticketType['id']; ?>">
                                        <button type="button"
                                            class="increase-btn bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-1 px-3 rounded-r"
                                            data-ticket-id="<?php echo $ticketType['id']; ?>">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Subtotal for this ticket type -->
                                <div class="mt-3 text-right">
                                    <span class="text-sm text-gray-600">Subtotal: </span>
                                    <span class="font-semibold text-gray-900 subtotal"
                                        data-ticket-id="<?php echo $ticketType['id']; ?>">
                                        <?php echo formatCurrency(0); ?>
                                    </span>
                                </div>
                                <?php else: ?>
                                <div class="bg-gray-100 text-gray-600 text-center py-2 rounded">
                                    Sold Out
                                </div>
                                <?php endif; ?>

                                <!-- Availability indicator -->
                                <?php if ($ticketType['available_tickets'] <= 10 && $ticketType['available_tickets'] > 0): ?>
                                <div class="mt-2 text-center">
                                    <span
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Only <?php echo $ticketType['available_tickets']; ?> left!
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Order Summary -->
                        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                            <h4 class="font-semibold text-gray-900 mb-3">Order Summary</h4>
                            <div id="order-summary" class="space-y-2 text-sm">
                                <div class="text-gray-500 text-center py-2">No tickets selected</div>
                            </div>
                            <div class="border-t border-gray-300 mt-3 pt-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-lg">Total:</span>
                                    <span id="total-price"
                                        class="font-bold text-xl text-indigo-600"><?php echo formatCurrency(0); ?></span>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="add_to_cart" value="1">

                        <div class="mt-6">
                            <?php if (isLoggedIn()): ?>
                            <button type="submit" id="add-to-cart-btn"
                                class="w-full bg-gray-400 text-white font-bold py-3 px-4 rounded transition duration-300 cursor-not-allowed"
                                disabled>
                                <i class="fas fa-shopping-cart mr-2"></i> Add to Cart
                            </button>
                            <?php else: ?>
                            <button type="button"
                                class="w-full bg-gray-400 text-white font-bold py-3 px-4 rounded cursor-not-allowed">
                                <i class="fas fa-lock mr-2"></i> Login Required
                            </button>
                            <p class="text-center mt-3 text-sm text-gray-600">
                                <a href="login.php?redirect=event-details.php?id=<?php echo $eventId; ?>"
                                    class="text-indigo-600 hover:text-indigo-800">
                                    Login</a> or <a href="register.php"
                                    class="text-indigo-600 hover:text-indigo-800">Register</a> to purchase tickets
                            </p>
                            <?php endif; ?>
                        </div>
                    </form>

                    <div class="mt-4 text-sm text-gray-600 space-y-1">
                        <p><i class="fas fa-info-circle mr-1"></i> Maximum 10 tickets per type</p>
                        <p><i class="fas fa-shield-alt mr-1"></i> Secure checkout</p>
                        <p><i class="fas fa-ticket-alt mr-1"></i> Digital tickets via email</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Similar Events -->
    <?php if (!empty($similarEvents)): ?>
    <div class="mt-12">
        <h2 class="text-2xl font-bold mb-6">Similar Events You Might Like</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php foreach ($similarEvents as $similarEvent): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
                <div class="h-48 bg-gray-300 relative">
                    <?php if (!empty($similarEvent['image'])): ?>
                    <img src="<?php echo SITE_URL; ?>/uploads/events/<?php echo $similarEvent['image']; ?>"
                        alt="<?php echo htmlspecialchars($similarEvent['title']); ?>"
                        class="w-full h-full object-cover">
                    <?php else: ?>
                    <div
                        class="w-full h-full flex items-center justify-center bg-gradient-to-br from-indigo-400 to-purple-500">
                        <i class="fas fa-calendar-alt text-white text-4xl"></i>
                    </div>
                    <?php endif; ?>
                    <div class="absolute top-0 right-0 bg-indigo-600 text-white px-3 py-1 m-2 rounded-lg text-sm">
                        <?php echo formatCurrency($similarEvent['ticket_price']); ?>
                    </div>
                </div>
                <div class="p-4">
                    <div class="flex items-center text-sm text-gray-600 mb-2">
                        <i class="far fa-calendar-alt mr-2"></i>
                        <?php echo formatDate($similarEvent['start_date']); ?>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">
                        <a href="event-details.php?id=<?php echo $similarEvent['id']; ?>"
                            class="text-gray-900 hover:text-indigo-600">
                            <?php echo htmlspecialchars($similarEvent['title']); ?>
                        </a>
                    </h3>
                    <div class="flex items-center text-sm text-gray-600 mb-4">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        <?php echo htmlspecialchars($similarEvent['venue']); ?>,
                        <?php echo htmlspecialchars($similarEvent['city']); ?>
                    </div>
                    <a href="event-details.php?id=<?php echo $similarEvent['id']; ?>"
                        class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded transition duration-300">
                        View Details
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const quantityInputs = document.querySelectorAll('.quantity-input');
    const decreaseBtns = document.querySelectorAll('.decrease-btn');
    const increaseBtns = document.querySelectorAll('.increase-btn');
    const addToCartBtn = document.getElementById('add-to-cart-btn');
    const orderSummary = document.getElementById('order-summary');
    const totalPriceEl = document.getElementById('total-price');

    // Create a map of ticket types for easier access
    const ticketTypes = {};
    quantityInputs.forEach(input => {
        const ticketId = input.dataset.ticketId;
        const price = parseFloat(input.dataset.price);
        const container = input.closest('.border');
        const nameElement = container.querySelector('h3');
        const ticketName = nameElement ? nameElement.textContent.trim() : `Ticket ${ticketId}`;

        ticketTypes[ticketId] = {
            name: ticketName,
            price: price
        };
    });

    // Update order summary and total
    function updateOrderSummary() {
        let totalPrice = 0;
        let totalQuantity = 0;
        let summaryHTML = '';

        quantityInputs.forEach(input => {
            const quantity = parseInt(input.value) || 0;
            const ticketId = input.dataset.ticketId;
            const ticketInfo = ticketTypes[ticketId];

            if (quantity > 0 && ticketInfo) {
                const subtotal = quantity * ticketInfo.price;
                totalPrice += subtotal;
                totalQuantity += quantity;

                summaryHTML += `
                    <div class="flex justify-between items-center">
                        <span>${quantity}x ${ticketInfo.name}</span>
                        <span>Rwf${subtotal.toFixed(2)}</span>
                    </div>
                `;

                // Update individual subtotal
                const subtotalEl = document.querySelector(`.subtotal[data-ticket-id="${ticketId}"]`);
                if (subtotalEl) {
                    subtotalEl.textContent = `Rwf${subtotal.toFixed(2)}`;
                }
            } else {
                // Reset individual subtotal
                const subtotalEl = document.querySelector(`.subtotal[data-ticket-id="${ticketId}"]`);
                if (subtotalEl) {
                    subtotalEl.textContent = 'Rwf0.00';
                }
            }
        });

        // Update order summary
        if (totalQuantity === 0) {
            orderSummary.innerHTML = '<div class="text-gray-500 text-center py-2">No tickets selected</div>';
            if (addToCartBtn) {
                addToCartBtn.disabled = true;
                addToCartBtn.className =
                    'w-full bg-gray-400 text-white font-bold py-3 px-4 rounded transition duration-300 cursor-not-allowed';
                addToCartBtn.innerHTML = '<i class="fas fa-shopping-cart mr-2"></i> Add to Cart';
            }
        } else {
            orderSummary.innerHTML = summaryHTML;
            if (addToCartBtn) {
                addToCartBtn.disabled = false;
                addToCartBtn.className =
                    'w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded transition duration-300';
                addToCartBtn.innerHTML =
                    `<i class="fas fa-shopping-cart mr-2"></i> Add ${totalQuantity} Ticket${totalQuantity > 1 ? 's' : ''} to Cart`;
            }
        }

        // Update total price
        if (totalPriceEl) {
            totalPriceEl.textContent = `Rwf${totalPrice.toFixed(2)}`;
        }

        // Debug logging (remove in production)
        console.log('Order Summary Update:', {
            totalPrice: totalPrice,
            totalQuantity: totalQuantity,
            ticketTypes: ticketTypes
        });
    }

    // Decrease quantity
    decreaseBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const ticketId = this.dataset.ticketId;
            const input = document.getElementById(`quantity_${ticketId}`);
            if (input) {
                const currentValue = parseInt(input.value) || 0;

                if (currentValue > 0) {
                    input.value = currentValue - 1;
                    updateOrderSummary();
                }
            }
        });
    });

    // Increase quantity
    increaseBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const ticketId = this.dataset.ticketId;
            const input = document.getElementById(`quantity_${ticketId}`);
            if (input) {
                const currentValue = parseInt(input.value) || 0;
                const maxValue = parseInt(input.max) || 10;

                if (currentValue < maxValue) {
                    input.value = currentValue + 1;
                    updateOrderSummary();
                }
            }
        });
    });

    // Handle direct input changes
    quantityInputs.forEach(input => {
        input.addEventListener('input', function() {
            let value = parseInt(this.value) || 0;
            const maxValue = parseInt(this.max) || 10;

            // Validate input
            if (value < 0) {
                value = 0;
            } else if (value > maxValue) {
                value = maxValue;
            }

            this.value = value;
            updateOrderSummary();
        });

        input.addEventListener('change', function() {
            updateOrderSummary();
        });

        // Prevent non-numeric input
        input.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key) && !['Backspace', 'Delete', 'Tab', 'Enter', 'ArrowLeft',
                    'ArrowRight'
                ].includes(e.key)) {
                e.preventDefault();
            }
        });
    });

    // Form validation before submit
    const ticketForm = document.getElementById('ticket-form');
    if (ticketForm) {
        ticketForm.addEventListener('submit', function(e) {
            let hasSelection = false;

            quantityInputs.forEach(input => {
                if (parseInt(input.value) > 0) {
                    hasSelection = true;
                }
            });

            if (!hasSelection) {
                e.preventDefault();
                alert('Please select at least one ticket before adding to cart.');
                return false;
            }
        });

        // Add loading state to form submission
        ticketForm.addEventListener('submit', function() {
            if (addToCartBtn) {
                addToCartBtn.disabled = true;
                addToCartBtn.innerHTML =
                '<i class="fas fa-spinner fa-spin mr-2"></i> Adding to Cart...';
                addToCartBtn.className =
                    'w-full bg-gray-400 text-white font-bold py-3 px-4 rounded cursor-not-allowed';
            }
        });
    }

    // Initialize order summary
    updateOrderSummary();

    // Test function to verify everything is working
    window.testTicketCalculation = function() {
        console.log('Ticket Types:', ticketTypes);
        console.log('Quantity Inputs:', quantityInputs.length);
        console.log('Total Price Element:', totalPriceEl);
        console.log('Order Summary Element:', orderSummary);

        // Set a test quantity
        if (quantityInputs.length > 0) {
            quantityInputs[0].value = 2;
            updateOrderSummary();
        }
    };
});
</script>


<?php include 'includes/footer.php'; ?>