<?php
$pageTitle = "Shopping Cart";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Please login to view your cart.";
    redirect('login.php?redirect=cart.php');
}

$userId = getCurrentUserId();

// Get cart ID
$cartSql = "SELECT id FROM cart WHERE user_id = $userId";
$cartResult = $db->fetchOne($cartSql);

$cartId = $cartResult ? $cartResult['id'] : 0;
$cartItems = [];
$totalAmount = 0;

// Process cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update quantity
    if (isset($_POST['update_quantity'])) {
        $itemId = (int)$_POST['item_id'];
        $quantity = (int)$_POST['quantity'];
        
        if ($quantity <= 0) {
            // Remove item if quantity is 0 or negative
            $db->query("DELETE FROM cart_items WHERE id = $itemId AND cart_id = $cartId");
        } else {
            // Update quantity
            $db->query("UPDATE cart_items SET quantity = $quantity WHERE id = $itemId AND cart_id = $cartId");
        }
        
        $_SESSION['success_message'] = "Cart updated successfully.";
        redirect('cart.php');
    }
    
    // Remove item
    if (isset($_POST['remove_item'])) {
        $itemId = (int)$_POST['item_id'];
        $db->query("DELETE FROM cart_items WHERE id = $itemId AND cart_id = $cartId");
        
        $_SESSION['success_message'] = "Item removed from cart.";
        redirect('cart.php');
    }
    
    // Clear cart
    if (isset($_POST['clear_cart'])) {
        $db->query("DELETE FROM cart_items WHERE cart_id = $cartId");
        
        $_SESSION['success_message'] = "Cart cleared successfully.";
        redirect('cart.php');
    }
    
    // Proceed to checkout
    if (isset($_POST['checkout'])) {
        redirect('checkout.php');
    }
}

// Get cart items if cart exists
if ($cartId) {
    $itemsSql = "SELECT ci.id, ci.event_id, ci.ticket_type_id, ci.quantity, 
                       e.title, e.start_date, e.start_time, e.venue, e.city, e.image,
                       COALESCE(tt.name, 'Standard Ticket') as ticket_name, 
                       COALESCE(tt.price, e.ticket_price) as ticket_price,
                       COALESCE(tt.available_tickets, e.available_tickets) as available_tickets
                FROM cart_items ci
                JOIN events e ON ci.event_id = e.id
                LEFT JOIN ticket_types tt ON ci.ticket_type_id = tt.id
                WHERE ci.cart_id = $cartId
                ORDER BY ci.created_at DESC";
    $cartItems = $db->fetchAll($itemsSql);
    
    // Calculate total amount
    foreach ($cartItems as $item) {
        $totalAmount += $item['ticket_price'] * $item['quantity'];
    }
}


include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Your Shopping Cart</h1>
    <?php if (empty($cartItems)): ?>
        <div class="bg-white rounded-lg shadow-md p-8 text-center">
            <div class="text-gray-500 mb-4">
                <i class="fas fa-shopping-cart text-6xl"></i>
            </div>
            <h2 class="text-2xl font-bold mb-4">Your cart is empty</h2>
            <p class="text-gray-600 mb-6">Looks like you haven't added any tickets to your cart yet.</p>
            <a href="events.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg transition duration-300">
                Browse Events
            </a>
        </div>
    <?php else: ?>
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Cart Items -->
            <div class="lg:w-2/3">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-6">
                        <h2 class="text-xl font-bold mb-4">Cart Items (<?php echo count($cartItems); ?>)</h2>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left py-3">Event</th>
                                        <th class="text-center py-3">Price</th>
                                        <th class="text-center py-3">Quantity</th>
                                        <th class="text-center py-3">Subtotal</th>
                                        <th class="text-right py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cartItems as $item): ?>
                                        <tr class="border-b">                                           
                                        <td class="py-4">
    <div class="flex items-center space-x-4">
        <div class="flex-shrink-0 w-20 h-20 bg-gray-100 rounded-md overflow-hidden">
            <?php if (!empty($item['image'])): ?>
                <img src="<?php echo  substr($item['image'], strpos($item['image'], 'uploads')); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="w-full h-full object-cover">
            <?php else: ?>
                <div class="w-full h-full flex items-center justify-center">
                    <i class="fas fa-calendar-alt text-2xl text-gray-400"></i>
                </div>
            <?php endif; ?>
        </div>
        <div class="flex-1 min-w-0">
            <h3 class="text-base font-semibold text-gray-900 truncate">
                <a href="event-details.php?id=<?php echo $item['event_id']; ?>" class="text-indigo-600 hover:text-indigo-800 hover:underline">
                    <?php echo htmlspecialchars($item['title']); ?>
                </a>
            </h3>
            <div class="mt-1 text-sm font-medium text-indigo-600 flex items-center space-x-2">
                <span><?php echo htmlspecialchars($item['ticket_name']); ?></span>
                <span class="bg-gray-100 rounded-full px-2 py-1 text-xs font-medium text-gray-600">
                    <?php echo htmlspecialchars($item['available_tickets']); ?> available
                </span>
            </div>
            <div class="mt-1 grid grid-cols-1 gap-1 text-xs text-gray-500">
                <div class="flex items-center">
                    <i class="far fa-calendar-alt w-4 text-center mr-1 text-gray-400"></i> 
                    <span class="font-medium"><?php echo formatDate($item['start_date']); ?></span>
                </div>
                <div class="flex items-center">
                    <i class="far fa-clock w-4 text-center mr-1 text-gray-400"></i> 
                    <span class="font-medium"><?php echo formatTime($item['start_time']); ?></span>
                </div>
                <div class="flex items-center">
                    <i class="fas fa-map-marker-alt w-4 text-center mr-1 text-gray-400"></i> 
                    <span class="font-medium truncate"><?php echo htmlspecialchars($item['venue']); ?>, <?php echo htmlspecialchars($item['city']); ?></span>
                </div>
            </div>
        </div>
    </div>
</td>

<td class="py-4 text-center">
    <?php echo formatCurrency($item['ticket_price']); ?>
</td>

                                            </td>
                                            <td class="py-4 text-center">
    <?php 
    // Debug the ticket price value
    echo formatCurrency((int)$item['ticket_price']); 
    ?>
</td>

                                            <td class="py-4 text-center">
                                                <form method="POST" action="" class="inline-flex">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <input type="hidden" name="update_quantity" value="1">
                                                    <div class="flex items-center justify-center">
                                                        <button type="button" onclick="decreaseQuantity(this)" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-1 px-2 rounded-l">
                                                            <i class="fas fa-minus"></i>
                                                        </button>
                                                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo min(10, $item['available_tickets']); ?>" 
                                                               class="w-12 text-center py-1 border-t border-b border-gray-300 focus:outline-none focus:border-indigo-500"
                                                               onchange="this.form.submit()">
                                                        <button type="button" onclick="increaseQuantity(this)" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-1 px-2 rounded-r">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </form>
                                            </td>
                                            <td class="py-4 text-center font-semibold">
                                                <?php echo formatCurrency((int)$item['ticket_price'] * $item['quantity']); ?>
                                            </td>
                                            <td class="py-4 text-right">
                                                <form method="POST" action="" class="inline">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <button type="submit" name="remove_item" class="text-red-600 hover:text-red-800" 
                                                            onclick="return confirm('Are you sure you want to remove this item?')">
                                                        <i class="fas fa-trash-alt"></i> Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-6 flex justify-between">
                            <form method="POST" action="">
                                <button type="submit" name="clear_cart" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-4 rounded transition duration-300"
                                        onclick="return confirm('Are you sure you want to clear your cart?')">
                                    <i class="fas fa-trash mr-2"></i> Clear Cart
                                </button>
                            </form>
                            <a href="events.php" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                                <i class="fas fa-shopping-bag mr-2"></i> Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="lg:w-1/3">
                <div class="bg-white rounded-lg shadow-md overflow-hidden sticky top-4">
                    <div class="bg-indigo-600 text-white px-6 py-4">
                        <h2 class="text-xl font-bold">Order Summary</h2>
                    </div>
                    
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex justify-between">
                                <span>Subtotal</span>
                                <span><?php echo formatCurrency($totalAmount); ?></span>
                            </div>
                            
                            <?php
                            // Calculate fees if applicable
                            $fees = 0;
                            $feesSql = "SELECT percentage FROM system_fees WHERE fee_type = 'ticket_sale'";
                            $feesResult = $db->fetchOne($feesSql);
                            if ($feesResult) {
                                $feePercentage = $feesResult['percentage'];
                                $fees = ($totalAmount * $feePercentage) / 100;
                            }
                            ?>
                            
                            <?php if ($fees > 0): ?>
                                <div class="flex justify-between">
                                    <span>Service Fee (<?php echo $feePercentage; ?>%)</span>
                                    <span><?php echo formatCurrency($fees); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="border-t pt-4 flex justify-between font-bold text-lg">
                                <span>Total</span>
                                <span><?php echo formatCurrency($totalAmount + $fees); ?></span>
                            </div>
                            
                            <form method="POST" action="">
                                <button type="submit" name="checkout" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded transition duration-300">
                                    <i class="fas fa-credit-card mr-2"></i> Proceed to Checkout
                                </button>
                            </form>
                            
                            <div class="text-center text-sm text-gray-600">
                                <p><i class="fas fa-lock mr-1"></i> Secure checkout</p>
                                <p class="mt-2">By proceeding, you agree to our <a href="#" class="text-indigo-600 hover:text-indigo-800">Terms of Service</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    function decreaseQuantity(button) {
        const input = button.nextElementSibling;
        const currentValue = parseInt(input.value);
        if (currentValue > 1) {
            input.value = currentValue - 1;
            input.form.submit();
        }
    }
    
    function increaseQuantity(button) {
        const input = button.previousElementSibling;
        const currentValue = parseInt(input.value);
        const maxValue = parseInt(input.getAttribute('max'));
        if (currentValue < maxValue) {
            input.value = currentValue + 1;
            input.form.submit();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>