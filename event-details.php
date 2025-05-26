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

// Get ticket types if available
$ticketTypesSql = "SELECT * FROM ticket_types WHERE event_id = $eventId AND available_tickets > 0";
$ticketTypes = $db->fetchAll($ticketTypesSql);

// If no specific ticket types, create a default one based on event data
if (empty($ticketTypes)) {
    $ticketTypes = [[
        'id' => 0,
        'name' => 'Standard Ticket',
        'price' => $event['ticket_price'],
        'description' => 'Standard entry ticket',
        'available_tickets' => $event['available_tickets']
    ]];
}

// Process add to cart
$addedToCart = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    // Check if user is logged in
    if (!isLoggedIn()) {
        // Store intended action in session
        $_SESSION['redirect_after_login'] = "event-details.php?id=$eventId";
        redirect('login.php');
    }
    
    $userId = getCurrentUserId();
    $ticketTypeId = isset($_POST['ticket_type_id']) ? (int)$_POST['ticket_type_id'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    
    // Validate inputs
    if ($quantity <= 0) {
        $errors[] = "Please select at least one ticket.";
    }
    
    // Check if there are enough tickets available
    if (empty($errors)) {
        if ($ticketTypeId > 0) {
            // Check specific ticket type
            $availableSql = "SELECT available_tickets FROM ticket_types WHERE id = $ticketTypeId AND event_id = $eventId";
            $availableResult = $db->fetchOne($availableSql);
            
            if (!$availableResult || $availableResult['available_tickets'] < $quantity) {
                $errors[] = "Sorry, there are not enough tickets available.";
            }
        } else {
            // Check event's available tickets
            if ($event['available_tickets'] < $quantity) {
                $errors[] = "Sorry, there are not enough tickets available.";
            }
        }
    }
    
    // Add to cart if no errors
    if (empty($errors)) {
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
        
        // Add item to cart
        $addItemSql = "INSERT INTO cart_items (cart_id, event_id, ticket_type_id, quantity, created_at) 
        VALUES ($cartId, $eventId, $ticketTypeId, $quantity, NOW())";
$db->query($addItemSql);

$addedToCart = true;
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

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <?php if ($addedToCart): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <div class="flex">
                <div class="py-1"><i class="fas fa-check-circle mr-2"></i></div>
                <div>
                    <p class="font-bold">Tickets added to cart!</p>
                    <p class="text-sm">You can now proceed to checkout or continue browsing events.</p>
                    <div class="mt-2">
                        <a href="cart.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded inline-block mr-2">
                            View Cart
                        </a>
                        <a href="events.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded inline-block">
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
            <ul class="list-disc pl-5">
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
                        <img src="<?php echo  substr($event['image'], strpos($event['image'], 'uploads')); ?>" alt="<?php echo $event['title']; ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-gray-300">
                            <i class="fas fa-calendar-alt text-6xl text-gray-500"></i>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($event['category'])): ?>
                        <div class="absolute top-0 left-0 bg-indigo-600 text-white px-4 py-2 m-4 rounded-lg">
                            <?php echo htmlspecialchars($event['category']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Event Info -->
                <div class="p-6">
                    <h1 class="text-3xl font-bold mb-4"><?php echo htmlspecialchars($event['title']); ?></h1>
                    
                    <div class="flex flex-wrap gap-4 mb-6">
                        <div class="flex items-center text-gray-600">
                            <i class="far fa-calendar-alt mr-2 text-indigo-600"></i>
                            <span>
                                <?php echo formatDate($event['start_date']); ?>
                                <?php if ($event['start_date'] !== $event['end_date']): ?>
                                    - <?php echo formatDate($event['end_date']); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="flex items-center text-gray-600">
                            <i class="far fa-clock mr-2 text-indigo-600"></i>
                            <span><?php echo formatTime($event['start_time']); ?> - <?php echo formatTime($event['end_time']); ?></span>
                        </div>
                        
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-map-marker-alt mr-2 text-indigo-600"></i>
                            <span><?php echo htmlspecialchars($event['venue']); ?>, <?php echo htmlspecialchars($event['city']); ?></span>
                        </div>
                        
                        <div class="flex items-center text-gray-600">
                            <i class="fas fa-user mr-2 text-indigo-600"></i>
                            <span>Organized by <?php echo htmlspecialchars($event['planner_name']); ?></span>
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
                            <p class="text-gray-700"><?php echo htmlspecialchars($event['city']); ?>, <?php echo htmlspecialchars($event['country']); ?></p>
                            
                            <?php if (!empty($event['address'])): ?>
                                <div class="mt-4">
                                    <a href="https://maps.google.com/?q=<?php echo urlencode($event['address'] . ', ' . $event['city'] . ', ' . $event['country']); ?>" 
                                       target="_blank" 
                                       class="text-indigo-600 hover:text-indigo-800">
                                        <i class="fas fa-map-marked-alt mr-2"></i> View on Google Maps
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Social Sharing -->
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-semibold mb-3">Share This Event</h3>
                        <div class="flex space-x-4">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(SITE_URL . '/event-details.php?id=' . $eventId); ?>" 
                               target="_blank" 
                               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-full">
                                <i class="fab fa-facebook-f mr-2"></i> Share
                            </a>
                            <a href="https://twitter.com/intent/tweet?text=<?php echo urlencode('Check out this event: ' . $event['title']); ?>&url=<?php echo urlencode(SITE_URL . '/event-details.php?id=' . $eventId); ?>" 
                               target="_blank" 
                               class="bg-blue-400 hover:bg-blue-500 text-white px-4 py-2 rounded-full">
                                <i class="fab fa-twitter mr-2"></i> Tweet
                            </a>
                            <a href="mailto:?subject=<?php echo urlencode('Check out this event: ' . $event['title']); ?>&body=<?php echo urlencode('I thought you might be interested in this event: ' . $event['title'] . '\n\n' . SITE_URL . '/event-details.php?id=' . $eventId); ?>" 
                               class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-full">
                                <i class="fas fa-envelope mr-2"></i> Email
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Ticket Purchase -->
        <div class="lg:w-1/3">
            <div class="bg-white rounded-lg shadow-md overflow-hidden sticky top-4">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Get Tickets</h2>
                </div>
                
                <div class="p-6">
                    <?php if ($event['available_tickets'] <= 0): ?>
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
                        <form method="POST" action="">
                            <?php if (count($ticketTypes) > 1): ?>
                                <div class="mb-4">
                                    <label for="ticket_type_id" class="block text-gray-700 font-bold mb-2">Ticket Type</label>
                                    <select id="ticket_type_id" name="ticket_type_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500" required>
                                        <?php foreach ($ticketTypes as $type): ?>
                                            <option value="<?php echo $type['id']; ?>" data-price="<?php echo $type['price']; ?>">
                                                <?php echo htmlspecialchars($type['name']); ?> - <?php echo formatCurrency($type['price']); ?>
                                                <?php if (!empty($type['description'])): ?>
                                                    (<?php echo htmlspecialchars($type['description']); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="ticket_type_id" value="<?php echo $ticketTypes[0]['id']; ?>">
                                <div class="mb-4">
                                    <div class="bg-gray-100 p-4 rounded-lg">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <h3 class="font-bold"><?php echo htmlspecialchars($ticketTypes[0]['name']); ?></h3>
                                                <?php if (!empty($ticketTypes[0]['description'])): ?>
                                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($ticketTypes[0]['description']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-xl font-bold text-indigo-600">
                                                <?php echo formatCurrency($ticketTypes[0]['price']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-6">
                                <label for="quantity" class="block text-gray-700 font-bold mb-2">Number of Tickets</label>
                                <div class="flex items-center">
                                    <button type="button" id="decrease-quantity" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-l">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" id="quantity" name="quantity" value="1" min="1" max="10" 
                                           class="w-full text-center py-2 border-t border-b border-gray-300 focus:outline-none focus:border-indigo-500"
                                           required>
                                    <button type="button" id="increase-quantity" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded-r">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <p class="text-sm text-gray-500 mt-1">Maximum 10 tickets per order</p>
                            </div>
                            
                            <div class="mb-6 p-4 bg-gray-100 rounded-lg">
                                <div class="flex justify-between mb-2">
                                    <span>Price per ticket:</span>
                                    <span id="price-per-ticket"><?php echo formatCurrency($ticketTypes[0]['price']); ?></span>
                                </div>
                                <div class="flex justify-between font-bold text-lg">
                                    <span>Total:</span>
                                    <span id="total-price"><?php echo formatCurrency($ticketTypes[0]['price']); ?></span>
                                </div>
                            </div>
                            
                            <input type="hidden" name="add_to_cart" value="1">
                            <?php if (isLoggedIn()): ?>
                                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded transition duration-300">
                                    <i class="fas fa-ticket-alt mr-2"></i> Add to Cart
                                </button>
                            <?php else: ?>
                                <button type="button" class="w-full bg-gray-400 text-white font-bold py-3 px-4 rounded cursor-not-allowed">
                                    <i class="fas fa-lock mr-2"></i> Login Required
                                </button>
                                <p class="text-center mt-2 text-sm text-gray-600">
                                    <a href="login.php?redirect=event-details.php?id=<?php echo $eventId; ?>" class="text-indigo-600 hover:text-indigo-800">
                                        Login</a> or <a href="register.php" class="text-indigo-600 hover:text-indigo-800">Register</a> to purchase tickets
                                </p>
                            <?php endif; ?>

                        </form>
                        
                        <div class="mt-4 text-sm text-gray-600">
                            <p><i class="fas fa-info-circle mr-1"></i> Tickets are non-refundable</p>
                            <p><i class="fas fa-lock mr-1"></i> Secure checkout</p>
                        </div>
                        
                        <script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const quantityInput = document.getElementById('quantity');
                                const decreaseBtn = document.getElementById('decrease-quantity');
                                const increaseBtn = document.getElementById('increase-quantity');
                                const pricePerTicketEl = document.getElementById('price-per-ticket');
                                const totalPriceEl = document.getElementById('total-price');
                                const ticketTypeSelect = document.getElementById('ticket_type_id');
                                
                                // Get initial price
                                let pricePerTicket = <?php echo $ticketTypes[0]['price']; ?>;
                                
                                // Update price when ticket type changes
                                if (ticketTypeSelect) {
                                    ticketTypeSelect.addEventListener('change', function() {
                                        const selectedOption = this.options[this.selectedIndex];
                                        pricePerTicket = parseFloat(selectedOption.getAttribute('data-price'));
                                        pricePerTicketEl.textContent = 'Rwf' + pricePerTicket.toFixed(2);
                                        updateTotalPrice();
                                    });
                                }
                                
                                // Update total price
                                function updateTotalPrice() {
                                    const quantity = parseInt(quantityInput.value);
                                    const total = pricePerTicket * quantity;
                                    totalPriceEl.textContent = 'Rwf' + total.toFixed(2);
                                }
                                
                                // Decrease quantity
                                decreaseBtn.addEventListener('click', function() {
                                    const currentValue = parseInt(quantityInput.value);
                                    if (currentValue > 1) {
                                        quantityInput.value = currentValue - 1;
                                        updateTotalPrice();
                                    }
                                });
                                
                                // Increase quantity
                                increaseBtn.addEventListener('click', function() {
                                    const currentValue = parseInt(quantityInput.value);
                                    if (currentValue < 10) {
                                        quantityInput.value = currentValue + 1;
                                        updateTotalPrice();
                                    }
                                });
                                
                                // Update when quantity changes directly
                                quantityInput.addEventListener('change', function() {
                                    let value = parseInt(this.value);
                                    if (isNaN(value) || value < 1) {
                                        value = 1;
                                    } else if (value > 10) {
                                        value = 10;
                                    }
                                    this.value = value;
                                    updateTotalPrice();
                                });
                            });
                        </script>
                    <?php endif; ?>
                    
                    <div class="mt-6 border-t border-gray-200 pt-6">
                        <h3 class="font-bold text-gray-700 mb-2">Event Status</h3>
                        <?php
                        $statusClasses = [
                            'active' => 'bg-green-100 text-green-800',
                            'completed' => 'bg-blue-100 text-blue-800',
                            'canceled' => 'bg-red-100 text-red-800',
                            'suspended' => 'bg-yellow-100 text-yellow-800'
                        ];
                        $statusClass = $statusClasses[$event['status']] ?? 'bg-gray-100 text-gray-800';
                        ?>
                        <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                            <?php echo ucfirst($event['status']); ?>
                        </span>
                        
                        <div class="mt-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Available Tickets:</span>
                                <span class="font-medium"><?php echo $event['available_tickets']; ?> / <?php echo $event['total_tickets']; ?></span>
                            </div>
                            
                            <?php
                            $soldPercentage = ($event['total_tickets'] > 0) 
                                ? round((($event['total_tickets'] - $event['available_tickets']) / $event['total_tickets']) * 100) 
                                : 0;
                            ?>
                            <div class="w-full bg-gray-200 rounded-full h-2.5 mt-2">
                                <div class="bg-indigo-600 h-2.5 rounded-full" style="width: <?php echo $soldPercentage; ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                <?php echo $soldPercentage; ?>% sold
                            </p>
                        </div>
                    </div>
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
                                <img src="<?php echo substr($event['image'], strpos($event['image'], 'uploads')); ?>" alt="<?php echo $similarEvent['title']; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center bg-gray-300">
                                    <i class="fas fa-calendar-alt text-4xl text-gray-500"></i>
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
                                <a href="event-details.php?id=<?php echo $similarEvent['id']; ?>" class="text-gray-900 hover:text-indigo-600">
                                    <?php echo htmlspecialchars($similarEvent['title']); ?>
                                </a>
                            </h3>
                            <div class="flex items-center text-sm text-gray-600 mb-4">
                                <i class="fas fa-map-marker-alt mr-2"></i>
                                <?php echo htmlspecialchars($similarEvent['venue']); ?>, <?php echo htmlspecialchars($similarEvent['city']); ?>
                            </div>
                            <a href="event-details.php?id=<?php echo $similarEvent['id']; ?>" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 px-4 rounded transition duration-300">
                                View Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
