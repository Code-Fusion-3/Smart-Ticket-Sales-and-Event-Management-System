<?php
$pageTitle = "Shopping Cart";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/cart_functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Please login to view your cart.";
    redirect('login.php?redirect=cart.php');
}

$userId = getCurrentUserId();

// Get or create cart ID
$cartSql = "SELECT id FROM cart WHERE user_id = $userId";
$cartResult = $db->fetchOne($cartSql);

if (!$cartResult) {
    // Create cart if it doesn't exist
    $createCartSql = "INSERT INTO cart (user_id) VALUES ($userId)";
    $cartId = $db->insert($createCartSql);
} else {
    $cartId = $cartResult['id'];
}
$cartItems = [];
$totalAmount = 0;
$totalItems = 0;

// Process cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update quantity
    if (isset($_POST['update_quantity'])) {
        $itemId = (int) $_POST['item_id'];
        $quantity = (int) $_POST['quantity'];

        if ($quantity <= 0) {
            // Remove item if quantity is 0 or negative
            $db->query("DELETE FROM cart_items WHERE id = $itemId AND cart_id = $cartId");
            $_SESSION['success_message'] = "Item removed from cart.";
        } else {
            // Check availability before updating
            $checkSql = "SELECT ci.*, 
                               COALESCE(tt.available_tickets, e.available_tickets) as available_tickets,
                               COALESCE(tt.name, 'Standard Ticket') as ticket_name,
                               e.title as event_title
                        FROM cart_items ci
                        JOIN events e ON ci.event_id = e.id
                        LEFT JOIN ticket_types tt ON ci.ticket_type_id = tt.id
                        WHERE ci.id = $itemId AND ci.cart_id = $cartId";
            $cartItem = $db->fetchOne($checkSql);

            if ($cartItem) {
                $availableTickets = $cartItem['available_tickets'] ?? 0;

                if ($quantity > $availableTickets) {
                    $_SESSION['error_message'] = "Sorry, only $availableTickets tickets available for " . htmlspecialchars($cartItem['ticket_name']) . " in " . htmlspecialchars($cartItem['event_title']);
                } else {
                    // Update quantity using cart function
                    if (updateCartItemQuantity($userId, $itemId, $quantity)) {
                        $_SESSION['success_message'] = "Cart updated successfully.";
                    } else {
                        $_SESSION['error_message'] = "Failed to update cart.";
                    }
                }
            } else {
                $_SESSION['error_message'] = "Cart item not found.";
            }
        }
        redirect('cart.php');
    }

    // Remove item
    if (isset($_POST['remove_item'])) {
        $itemId = (int) $_POST['item_id'];

        if (removeFromCart($userId, $itemId)) {
            $_SESSION['success_message'] = "Item removed from cart.";
        } else {
            $_SESSION['error_message'] = "Failed to remove item from cart.";
        }
        redirect('cart.php');
    }

    // Clear cart
    if (isset($_POST['clear_cart'])) {
        if (clearCart($userId)) {
            $_SESSION['success_message'] = "Cart cleared successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to clear cart.";
        }
        redirect('cart.php');
    }

    // Proceed to checkout
    if (isset($_POST['checkout'])) {
        redirect('checkout.php');
    }
}

// Get cart items if cart exists
if ($cartId) {
    $itemsSql = "SELECT ci.id, ci.event_id, ci.ticket_type_id, ci.quantity, ci.created_at,
                       e.title, e.start_date, e.start_time, e.venue, e.city, e.image, e.status,
                       COALESCE(tt.name, 'Standard Ticket') as ticket_name, 
                       COALESCE(tt.description, 'Standard entry ticket') as ticket_description,
                       COALESCE(tt.price, e.ticket_price) as ticket_price,
                       COALESCE(tt.available_tickets, e.available_tickets) as available_tickets,
                       COALESCE(tt.total_tickets, e.total_tickets) as total_tickets,
                       u.username as planner_name
                FROM cart_items ci
                JOIN events e ON ci.event_id = e.id
                JOIN users u ON e.planner_id = u.id
                LEFT JOIN ticket_types tt ON ci.ticket_type_id = tt.id
                WHERE ci.cart_id = $cartId
                ORDER BY ci.created_at DESC";
    $cartItems = $db->fetchAll($itemsSql);

    // Calculate totals and validate availability
    $validItems = [];
    $invalidItems = [];

    foreach ($cartItems as $item) {
        // Check if event is still active
        if ($item['status'] !== 'active') {
            $invalidItems[] = $item;
            continue;
        }

        // Check if tickets are still available
        if ($item['available_tickets'] < $item['quantity']) {
            $item['max_available'] = $item['available_tickets'];
            $invalidItems[] = $item;
            continue;
        }

        $validItems[] = $item;
        $totalAmount += $item['ticket_price'] * $item['quantity'];
        $totalItems += $item['quantity'];
    }

    $cartItems = $validItems;

    // Show warnings for invalid items
    if (!empty($invalidItems)) {
        $warningMessages = [];
        foreach ($invalidItems as $invalid) {
            if ($invalid['status'] !== 'active') {
                $warningMessages[] = "Event '{$invalid['title']}' is no longer available.";
            } elseif (isset($invalid['max_available'])) {
                $warningMessages[] = "Only {$invalid['max_available']} tickets available for '{$invalid['ticket_name']}' in '{$invalid['title']}'.";
            }
        }
        $_SESSION['warning_message'] = implode(' ', $warningMessages);
    }
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold">Your Shopping Cart</h1>
        <div class="text-sm text-gray-600">
            <?php if ($totalItems > 0): ?>
                <?php echo $totalItems; ?> item<?php echo $totalItems > 1 ? 's' : ''; ?> in cart
            <?php endif; ?>
        </div>
    </div>

    <!-- Display Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-check-circle mr-2"></i><?php echo $_SESSION['success_message'];
            unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $_SESSION['error_message'];
            unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['warning_message'])): ?>
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-exclamation-triangle mr-2"></i><?php echo $_SESSION['warning_message'];
            unset($_SESSION['warning_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['info_message'])): ?>
        <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-info-circle mr-2"></i><?php echo $_SESSION['info_message'];
            unset($_SESSION['info_message']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($cartItems)): ?>
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <div class="text-gray-500 mb-4">
                <i class="fas fa-shopping-cart text-6xl"></i>
            </div>
            <h2 class="text-2xl font-bold mb-4">Your cart is empty</h2>
            <p class="text-gray-600 mb-6">Looks like you haven't added any tickets to your cart yet.</p>
            <a href="events.php"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300">
                <i class="fas fa-search mr-2"></i>Browse Events
            </a>
        </div>
    <?php else: ?>
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Cart Items -->
            <div class="lg:w-2/3">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-6">
                        <h2 class="text-xl font-bold mb-6 flex items-center">
                            <i class="fas fa-shopping-cart mr-2 text-indigo-600"></i>
                            Cart Items (<?php echo count($cartItems); ?>)
                        </h2>

                        <div class="space-y-6">
                            <?php foreach ($cartItems as $item): ?>
                                <div
                                    class="border border-gray-200 rounded-lg p-4 hover:border-indigo-300 transition duration-300">
                                    <div class="flex flex-col md:flex-row gap-4">
                                        <!-- Event Image -->
                                        <div class="flex-shrink-0">
                                            <div class="w-24 h-24 md:w-32 md:h-32 bg-gray-100 rounded-lg overflow-hidden">
                                                <?php if (!empty($item['image'])): ?>
                                                    <img src="<?php echo SITE_URL; ?>/uploads/events/<?php echo $item['image']; ?>"
                                                        alt="<?php echo htmlspecialchars($item['title']); ?>"
                                                        class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <div
                                                        class="w-full h-full flex items-center justify-center bg-gradient-to-br from-indigo-400 to-purple-500">
                                                        <i class="fas fa-calendar-alt text-white text-2xl"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Event Details -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-col md:flex-row md:items-start md:justify-between">
                                                <div class="flex-1 mb-4 md:mb-0">
                                                    <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                                        <a href="event-details.php?id=<?php echo $item['event_id']; ?>"
                                                            class="text-indigo-600 hover:text-indigo-800 hover:underline">
                                                            <?php echo htmlspecialchars($item['title']); ?>
                                                        </a>
                                                    </h3>

                                                    <!-- Ticket Type Information -->
                                                    <div class="mb-3">
                                                        <div
                                                            class="inline-flex items-center bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full text-sm font-medium">
                                                            <i class="fas fa-ticket-alt mr-2"></i>
                                                            <?php echo htmlspecialchars($item['ticket_name']); ?>
                                                        </div>
                                                        <?php if (!empty($item['ticket_description']) && $item['ticket_description'] !== 'Standard entry ticket'): ?>
                                                            <p class="text-sm text-gray-600 mt-1">
                                                                <?php echo htmlspecialchars($item['ticket_description']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>

                                                    <!-- Event Details -->
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm text-gray-600">
                                                        <div class="flex items-center">
                                                            <i
                                                                class="far fa-calendar-alt w-4 text-center mr-2 text-gray-400"></i>
                                                            <span><?php echo formatDate($item['start_date']); ?></span>
                                                        </div>
                                                        <div class="flex items-center">
                                                            <i class="far fa-clock w-4 text-center mr-2 text-gray-400"></i>
                                                            <span><?php echo formatTime($item['start_time']); ?></span>
                                                        </div>
                                                        <div class="flex items-center">
                                                            <i
                                                                class="fas fa-map-marker-alt w-4 text-center mr-2 text-gray-400"></i>
                                                            <span
                                                                class="truncate"><?php echo htmlspecialchars($item['venue']); ?>,
                                                                <?php echo htmlspecialchars($item['city']); ?></span>
                                                        </div>
                                                        <div class="flex items-center">
                                                            <i class="fas fa-user w-4 text-center mr-2 text-gray-400"></i>
                                                            <span
                                                                class="truncate"><?php echo htmlspecialchars($item['planner_name']); ?></span>
                                                        </div>
                                                    </div>

                                                    <!-- Availability Status -->
                                                    <div class="mt-3">
                                                        <?php if ($item['available_tickets'] <= 10): ?>
                                                            <span
                                                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                                Only <?php echo $item['available_tickets']; ?> left!
                                                            </span>
                                                        <?php else: ?>
                                                            <span
                                                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                <i class="fas fa-check-circle mr-1"></i>
                                                                <?php echo $item['available_tickets']; ?> available
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <!-- Price and Quantity Controls -->
                                                <div class="flex flex-col items-end space-y-4">
                                                    <!-- Price -->
                                                    <div class="text-right">
                                                        <div class="text-2xl font-bold text-indigo-600">
                                                            <?php echo formatCurrency($item['ticket_price']); ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">per ticket</div>
                                                    </div>

                                                    <!-- Quantity Controls -->
                                                    <div class="flex items-center space-x-2">
                                                        <span class="text-sm text-gray-600">Qty:</span>
                                                        <form method="POST" action="" class="inline-flex items-center">
                                                            <input type="hidden" name="item_id"
                                                                value="<?php echo $item['id']; ?>">
                                                            <input type="hidden" name="update_quantity" value="1">
                                                            <div class="flex items-center border border-gray-300 rounded-lg">
                                                                <button type="button" onclick="decreaseQuantity(this)"
                                                                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-3 rounded-l-lg transition duration-200">
                                                                    <i class="fas fa-minus text-sm"></i>
                                                                </button>
                                                                <input type="number" name="quantity"
                                                                    value="<?php echo $item['quantity']; ?>" min="1"
                                                                    max="<?php echo min(10, $item['available_tickets']); ?>"
                                                                    class="w-16 text-center py-2 border-0 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                                    onchange="this.form.submit()"
                                                                    data-item-id="<?php echo $item['id']; ?>">
                                                                <button type="button" onclick="increaseQuantity(this)"
                                                                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 px-3 rounded-r-lg transition duration-200">
                                                                    <i class="fas fa-plus text-sm"></i>
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>

                                                    <!-- Subtotal -->
                                                    <div class="text-right">
                                                        <div class="text-lg font-bold text-gray-900">
                                                            <?php echo formatCurrency($item['ticket_price'] * $item['quantity']); ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">subtotal</div>
                                                    </div>

                                                    <!-- Remove Button -->
                                                    <form method="POST" action="" class="inline">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                        <input type="hidden" name="remove_item" value="1">
                                                        <button type="submit"
                                                            class="text-red-600 hover:text-red-800 text-sm font-medium transition duration-200"
                                                            onclick="return confirm('Are you sure you want to remove this item from your cart?')">
                                                            <i class="fas fa-trash-alt mr-1"></i> Remove
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Cart Actions -->
                        <div class="mt-8 pt-6 border-t border-gray-200">
                            <div class="flex flex-col sm:flex-row justify-between items-center space-y-4 sm:space-y-0">
                                <form method="POST" action="">
                                    <input type="hidden" name="clear_cart" value="1">
                                    <button type="submit"
                                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-6 rounded-lg transition duration-300"
                                        onclick="return confirm('Are you sure you want to clear your entire cart?')">
                                        <i class="fas fa-trash mr-2"></i> Clear Cart
                                    </button>
                                </form>

                                <a href="events.php"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg transition duration-300">
                                    <i class="fas fa-search mr-2"></i> Continue Shopping
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="lg:w-1/3">
                <div class="bg-white rounded-lg shadow-md overflow-hidden sticky top-4">
                    <div class="bg-indigo-600 text-white px-6 py-4">
                        <h2 class="text-xl font-bold flex items-center">
                            <i class="fas fa-receipt mr-2"></i>
                            Order Summary
                        </h2>
                    </div>

                    <div class="p-6">
                        <!-- Items Breakdown -->
                        <div class="space-y-3 mb-6">
                            <h3 class="font-semibold text-gray-900 mb-3">Items in Cart</h3>
                            <?php foreach ($cartItems as $item): ?>
                                <div class="flex justify-between items-center text-sm">
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900 truncate">
                                            <?php echo htmlspecialchars($item['title']); ?>
                                        </div>
                                        <div class="text-gray-600">
                                            <?php echo $item['quantity']; ?>x
                                            <?php echo htmlspecialchars($item['ticket_name']); ?>
                                        </div>
                                    </div>
                                    <div class="font-semibold text-gray-900 ml-4">
                                        <?php echo formatCurrency($item['ticket_price'] * $item['quantity']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pricing Breakdown -->
                        <div class="space-y-3 border-t border-gray-200 pt-4">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Subtotal (<?php echo $totalItems; ?> items)</span>
                                <span class="font-medium"><?php echo formatCurrency($totalAmount); ?></span>
                            </div>

                            <div class="border-t border-gray-200 pt-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-lg font-bold text-gray-900">Total</span>
                                    <span
                                        class="text-2xl font-bold text-indigo-600"><?php echo formatCurrency($totalAmount); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Checkout Button -->
                        <form method="POST" action="checkout.php" class="mt-6">
                            <button type="submit" name="checkout"
                                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-lg transition duration-300 transform hover:scale-105">
                                <i class="fas fa-credit-card mr-2"></i> Proceed to Checkout
                            </button>
                        </form>

                        <!-- Security and Policy Info -->
                        <div class="mt-6 text-center text-sm text-gray-600 space-y-2">
                            <div class="flex items-center justify-center">
                                <i class="fas fa-lock mr-2 text-green-600"></i>
                                <span>Secure checkout</span>
                            </div>
                            <div class="flex items-center justify-center">
                                <i class="fas fa-shield-alt mr-2 text-blue-600"></i>
                                <span>SSL encrypted</span>
                            </div>
                            <div class="flex items-center justify-center">
                                <i class="fas fa-mobile-alt mr-2 text-purple-600"></i>
                                <span>Digital tickets via email</span>
                            </div>
                        </div>

                        <!-- Terms and Conditions -->
                        <div class="mt-4 text-xs text-gray-500 text-center">
                            <p>By proceeding, you agree to our
                                <a href="#" class="text-indigo-600 hover:text-indigo-800 underline">Terms of Service</a>
                                and
                                <a href="#" class="text-indigo-600 hover:text-indigo-800 underline">Privacy Policy</a>
                            </p>
                        </div>
                    </div>

                    <!-- Recommended Events -->
                    <?php
                    // Get recommended events based on cart items
                    $eventIds = array_column($cartItems, 'event_id');
                    if (!empty($eventIds)) {
                        $eventIdsStr = implode(',', $eventIds);
                        $recommendedSql = "SELECT DISTINCT e.*, u.username as planner_name
                                      FROM events e
                                      JOIN users u ON e.planner_id = u.id
                                      WHERE e.id NOT IN ($eventIdsStr)
                                      AND e.status = 'active'
                                      AND e.start_date >= CURDATE()
                                      AND (e.category IN (SELECT DISTINCT e2.category FROM events e2 WHERE e2.id IN ($eventIdsStr))
                                           OR e.city IN (SELECT DISTINCT e2.city FROM events e2 WHERE e2.id IN ($eventIdsStr)))
                                      ORDER BY e.start_date ASC
                                      LIMIT 3";
                        $recommendedEvents = $db->fetchAll($recommendedSql);

                        if (!empty($recommendedEvents)): ?>
                            <div class="mt-6 bg-white rounded-lg shadow-md overflow-hidden ">
                                <div class="bg-gray-200 px-6 py-4">
                                    <h3 class="text-lg font-bold text-gray-900">You Might Also Like</h3>
                                </div>
                                <div class="p-4 space-y-4">
                                    <?php foreach ($recommendedEvents as $event): ?>
                                        <div
                                            class="flex items-center space-x-3 p-3 border border-gray-200 rounded-lg hover:border-indigo-300 transition duration-300">
                                            <div class="flex-shrink-0 w-16 h-16 bg-gray-100 rounded-lg overflow-hidden">
                                                <?php if (!empty($event['image'])): ?>
                                                    <img src="<?php echo SITE_URL; ?>/uploads/events/<?php echo $event['image']; ?>"
                                                        alt="<?php echo htmlspecialchars($event['title']); ?>"
                                                        class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <div
                                                        class="w-full h-full flex items-center justify-center bg-gradient-to-br from-indigo-400 to-purple-500">
                                                        <i class="fas fa-calendar-alt text-white text-lg"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <h4 class="text-sm font-semibold text-gray-900 truncate">
                                                    <a href="event-details.php?id=<?php echo $event['id']; ?>"
                                                        class="hover:text-indigo-600">
                                                        <?php echo htmlspecialchars($event['title']); ?>
                                                    </a>
                                                </h4>
                                                <div class="text-xs text-gray-600">
                                                    <?php echo formatDate($event['start_date']); ?> •
                                                    <?php echo htmlspecialchars($event['city']); ?>
                                                </div>
                                                <div class="text-sm font-medium text-indigo-600">
                                                    <?php echo formatCurrency($event['ticket_price']); ?>
                                                </div>
                                            </div>
                                            <a href="event-details.php?id=<?php echo $event['id']; ?>"
                                                class="flex-shrink-0 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium py-1 px-3 rounded transition duration-200">
                                                View
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif;
                    }
                    ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript for Cart Functionality -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Quantity control functions
        window.decreaseQuantity = function (button) {
            const form = button.closest('form');
            const input = form.querySelector('input[name="quantity"]');
            const currentValue = parseInt(input.value);

            if (currentValue > 1) {
                input.value = currentValue - 1;
                form.submit();
            }
        };

        window.increaseQuantity = function (button) {
            const form = button.closest('form');
            const input = form.querySelector('input[name="quantity"]');
            const currentValue = parseInt(input.value);
            const maxValue = parseInt(input.getAttribute('max'));

            if (currentValue < maxValue) {
                input.value = currentValue + 1;
                form.submit();
            }
        };

        // Add loading state to forms
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function () {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML =
                        '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';

                    // Re-enable after 3 seconds as fallback
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }, 3000);
                }
            });
        });

        // Validate quantity inputs
        const quantityInputs = document.querySelectorAll('input[name="quantity"]');
        quantityInputs.forEach(input => {
            // Prevent invalid input
            input.addEventListener('keypress', function (e) {
                if (!/[0-9]/.test(e.key) && !['Backspace', 'Delete', 'Tab', 'Enter',
                    'ArrowLeft',
                    'ArrowRight'
                ].includes(e.key)) {
                    e.preventDefault();
                }
            });

            // Validate on blur
            input.addEventListener('blur', function () {
                let value = parseInt(this.value) || 1;
                const max = parseInt(this.getAttribute('max')) || 10;
                const min = parseInt(this.getAttribute('min')) || 1;

                if (value < min) value = min;
                if (value > max) value = max;

                if (this.value != value) {
                    this.value = value;
                }
            });
        });

        // Add smooth animations
        const cartItems = document.querySelectorAll('.border.border-gray-200');
        cartItems.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(20px)';

            setTimeout(() => {
                item.style.transition = 'all 0.5s ease';
                item.style.opacity = '1';
                item.style.transform = 'translateY(0)';
            }, index * 100);
        });

        // Add hover effects
        const hoverElements = document.querySelectorAll('.hover\\:border-indigo-300');
        hoverElements.forEach(element => {
            element.addEventListener('mouseenter', function () {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
            });

            element.addEventListener('mouseleave', function () {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '';
            });
        });
    });

    // Utility function to update cart count in header (if exists)
    function updateCartCount() {
        const cartCount = <?php echo $totalItems; ?>;
        const cartCountElements = document.querySelectorAll('.cart-count');

        cartCountElements.forEach(element => {
            element.textContent = cartCount;
            if (cartCount > 0) {
                element.style.display = 'inline';
            } else {
                element.style.display = 'none';
            }
        });
    }

    // Call on page load
    updateCartCount();
</script>

<!-- Additional CSS for better styling -->
<style>
    .transition-all {
        transition: all 0.3s ease;
    }

    .hover\:scale-105:hover {
        transform: scale(1.05);
    }

    /* Custom scrollbar for better UX */
    .overflow-y-auto::-webkit-scrollbar {
        width: 6px;
    }

    .overflow-y-auto::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }

    .overflow-y-auto::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }

    .overflow-y-auto::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    /* Loading animation */
    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: 0.5;
        }
    }

    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    /* Quantity input styling */
    input[type="number"]::-webkit-outer-spin-button,
    input[type="number"]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    input[type="number"] {
        -moz-appearance: textfield;
    }

    /* Mobile responsive adjustments */
    @media (max-width: 768px) {
        .sticky {
            position: relative !important;
            top: auto !important;
        }

        .lg\:w-1\/3 {
            width: 100%;
            margin-top: 2rem;
        }

        .text-2xl {
            font-size: 1.5rem;
        }

        .text-lg {
            font-size: 1.125rem;
        }
    }

    /* Print styles */
    @media print {
        .no-print {
            display: none !important;
        }

        .print-only {
            display: block !important;
        }
    }
</style>

<?php include 'includes/footer.php'; ?>