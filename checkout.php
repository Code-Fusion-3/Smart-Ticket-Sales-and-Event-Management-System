<?php
// error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
$pageTitle = "Checkout";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Please login to proceed with checkout.";
    redirect('login.php?redirect=checkout.php');
}

$userId = getCurrentUserId();

// Get cart ID
$cartSql = "SELECT id FROM cart WHERE user_id = $userId";
$cartResult = $db->fetchOne($cartSql);

if (!$cartResult) {
    $_SESSION['error_message'] = "Your cart is empty. Please add tickets before checkout.";
    redirect('cart.php');
}

$cartId = $cartResult['id'];

// Get cart items with enhanced ticket type information
$itemsSql = "SELECT ci.id, ci.event_id, ci.ticket_type_id, ci.quantity, 
                   ci.recipient_name, ci.recipient_email, ci.recipient_phone,
                   e.title, e.start_date, e.start_time, e.venue, e.city, e.status,
                   e.planner_id, e.image,
                   COALESCE(tt.name, 'Standard Ticket') as ticket_name, 
                   COALESCE(tt.description, 'Standard entry ticket') as ticket_description,
                   COALESCE(tt.price, e.ticket_price) as ticket_price,
                   COALESCE(tt.available_tickets, e.available_tickets) as available_tickets,
                   u.username as planner_name
            FROM cart_items ci
            JOIN events e ON ci.event_id = e.id
            JOIN users u ON e.planner_id = u.id
            LEFT JOIN ticket_types tt ON ci.ticket_type_id = tt.id
            WHERE ci.cart_id = $cartId
            ORDER BY ci.created_at DESC";
$cartItems = $db->fetchAll($itemsSql);

if (empty($cartItems)) {
    $_SESSION['error_message'] = "Your cart is empty. Please add tickets before checkout.";
    redirect('cart.php');
}

// Validate cart items before checkout
$validationErrors = [];
$validItems = [];

foreach ($cartItems as $item) {
    // Check if event is still active
    if ($item['status'] !== 'active') {
        $validationErrors[] = "Event '{$item['title']}' is no longer available.";
        continue;
    }
    
    // Check ticket availability
    if ($item['available_tickets'] < $item['quantity']) {
        $validationErrors[] = "Only {$item['available_tickets']} tickets available for '{$item['ticket_name']}' in '{$item['title']}'.";
        continue;
    }
    
    $validItems[] = $item;
}

// If there are validation errors, redirect back to cart
if (!empty($validationErrors)) {
    $_SESSION['error_message'] = implode(' ', $validationErrors);
    redirect('cart.php');
}

$cartItems = $validItems;

// Calculate totals
$subtotal = 0;
$fees = 0;
$total = 0;
$totalTickets = 0;

foreach ($cartItems as $item) {
    $subtotal += $item['ticket_price'] * $item['quantity'];
    $totalTickets += $item['quantity'];
}

// Get service fee percentage
$feesSql = "SELECT percentage FROM system_fees WHERE fee_type = 'ticket_sale'";
$feesResult = $db->fetchOne($feesSql);
if ($feesResult) {
    $feePercentage = $feesResult['percentage'];
    $fees = ($subtotal * $feePercentage) / 100;
}

$total = $subtotal + $fees;

// Get user information
$userSql = "SELECT username, email, phone_number, balance FROM users WHERE id = $userId";
$user = $db->fetchOne($userSql);

// Process checkout
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = $_POST['payment_method'] ?? '';
    $useBalance = isset($_POST['use_balance']) ? true : false;
    
// Validate payment method - only check this for POST requests
    if (empty($paymentMethod) && (!$useBalance || $user['balance'] < $total)) {
        $errors[] = "Please select a payment method.";
    }
    
    // Validate recipient information for each cart item
    $recipientData = [];
    foreach ($cartItems as $item) {
        $itemId = $item['id'];
        
        for ($i = 0; $i < $item['quantity']; $i++) {
            $recipientName = $_POST["recipient_name_{$itemId}_{$i}"] ?? '';
            $recipientEmail = $_POST["recipient_email_{$itemId}_{$i}"] ?? '';
            $recipientPhone = $_POST["recipient_phone_{$itemId}_{$i}"] ?? '';
            
            // Use user's info if recipient info is empty
            if (empty($recipientName)) $recipientName = $user['username'];
            if (empty($recipientEmail)) $recipientEmail = $user['email'];
            if (empty($recipientPhone)) $recipientPhone = $user['phone_number'];
            
            // Validate email format
            if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format for ticket " . ($i + 1) . " of {$item['ticket_name']}.";
            }
            
            $recipientData[$itemId][] = [
                'name' => $recipientName,
                'email' => $recipientEmail,
                'phone' => $recipientPhone
            ];
        }
    }
    
    // Process payment if no errors
    if (empty($errors)) {
        // Check if using account balance
        $amountToCharge = $total;
        $balanceUsed = 0;
        
        if ($useBalance && $user['balance'] > 0) {
            if ($user['balance'] >= $total) {
                $balanceUsed = $total;
                $amountToCharge = 0;
            } else {
                $balanceUsed = $user['balance'];
                $amountToCharge = $total - $balanceUsed;
            }
        }
        
        // Process external payment if needed
        $paymentSuccess = true;
        $paymentReference = generateRandomString(12);
        
        if ($amountToCharge > 0) {
            // Validate payment details based on method
            if ($paymentMethod === 'credit_card') {
                $cardNumber = $_POST['card_number'] ?? '';
                $cardName = $_POST['card_name'] ?? '';
                $cardCvv = $_POST['card_cvv'] ?? '';
                
                if (empty($cardNumber) || empty($cardName) || empty($cardCvv)) {
                    $errors[] = "Please fill in all credit card details.";
                    $paymentSuccess = false;
                }
            } elseif ($paymentMethod === 'mobile_money') {
                $mobileNumber = $_POST['mobile_number'] ?? '';
                if (empty($mobileNumber)) {
                    $errors[] = "Please enter your mobile number.";
                    $paymentSuccess = false;
                }
            }
        }
    
        if ($paymentSuccess && empty($errors)) {
            // Begin transaction
            $db->query("START TRANSACTION");
            
            try {
                // Deduct from user balance if used
                if ($balanceUsed > 0) {
                    $newBalance = $user['balance'] - $balanceUsed;
                    $db->query("UPDATE users SET balance = $newBalance WHERE id = $userId");
                    
                    // Record balance transaction
                    $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, payment_method, description)
                                VALUES ($userId, $balanceUsed, 'purchase', 'completed', '$paymentReference', 'balance', 'Ticket purchase using account balance')");
                }
                
                // Record external payment transaction if any
                if ($amountToCharge > 0) {
                    $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, payment_method, description)
                                VALUES ($userId, $amountToCharge, 'purchase', 'completed', '$paymentReference', '$paymentMethod', 'Ticket purchase')");
                }
                
                // Process each cart item and create tickets
                $generatedTickets = [];
                
                foreach ($cartItems as $item) {
                    $eventId = $item['event_id'];
                    $ticketTypeId = $item['ticket_type_id'] ?: null;
                    $quantity = $item['quantity'];
                    $price = $item['ticket_price'];
                    $cartItemId = $item['id'];
                    
                    // Create individual tickets
                    for ($i = 0; $i < $quantity; $i++) {
                        $recipient = $recipientData[$cartItemId][$i];
                        
                        // Generate unique QR code
                        $qrCode = 'TICKET-' . generateRandomString(16);
                        
                        // Insert ticket
                        $ticketSql = "INSERT INTO tickets (event_id, ticket_type_id, user_id, recipient_name, recipient_email, recipient_phone, qr_code, purchase_price, status, created_at)
                                     VALUES ($eventId, " . ($ticketTypeId ? $ticketTypeId : "NULL") . ", $userId, 
                                             '" . $db->escape($recipient['name']) . "', 
                                             '" . $db->escape($recipient['email']) . "', 
                                             '" . $db->escape($recipient['phone']) . "', 
                                             '$qrCode', $price, 'sold', NOW())";
                        $ticketId = $db->insert($ticketSql);
                        
                        // Store ticket info for notifications
                        $generatedTickets[] = [
                            'id' => $ticketId,
                            'event_id' => $eventId,
                            'ticket_type_id' => $ticketTypeId,
                            'event_title' => $item['title'],
                            'ticket_name' => $item['ticket_name'],
                            'ticket_description' => $item['ticket_description'],
                            'venue' => $item['venue'],
                            'city' => $item['city'],
                            'start_date' => $item['start_date'],
                            'start_time' => $item['start_time'],
                            'recipient_name' => $recipient['name'],
                            'recipient_email' => $recipient['email'],
                            'recipient_phone' => $recipient['phone'],
                            'purchase_price' => $price,
                            'qr_code' => $qrCode,
                            'planner_name' => $item['planner_name']
                        ];
                    }
                    
                    // Update ticket availability
                    if ($ticketTypeId) {
                        $db->query("UPDATE ticket_types SET available_tickets = available_tickets - $quantity WHERE id = $ticketTypeId");
                    }
                    $db->query("UPDATE events SET available_tickets = available_tickets - $quantity WHERE id = $eventId");
                }
                
                // Record system fee
                if ($fees > 0) {
                    $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, description)
                                VALUES ($userId, $fees, 'system_fee', 'completed', '$paymentReference', 'Service fee for ticket purchase')");
                }
                
                // Clear the cart
                $db->query("DELETE FROM cart_items WHERE cart_id = $cartId");
                
                // Commit transaction
                $db->query("COMMIT");
                
                // Send notifications for each ticket
                foreach ($generatedTickets as $ticket) {
                    // Generate QR code data
                    $qrCodeData = json_encode([
                        'ticket_id' => $ticket['id'],
                        'event_id' => $ticket['event_id'],
                        'user_id' => $userId,
                        'verification_token' => $ticket['qr_code'],
                        'timestamp' => time()
                    ]);
                    
                    // Generate QR code URL
                    $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrCodeData);
                    
                    // Prepare event details for email
                    $eventDetails = [
                        'title' => $ticket['event_title'],
                        'venue' => $ticket['venue'],
                        'city' => $ticket['city'],
                        'start_date' => $ticket['start_date'],
                        'start_time' => $ticket['start_time']
                    ];
                    
                    // Send email notification
                    $emailSubject = "Your {$ticket['ticket_name']} for {$ticket['event_title']}";
                    $emailBody = getEnhancedTicketEmailTemplate($ticket, $eventDetails, $qrCodeUrl);
                    $plainTextEmail = "Your {$ticket['ticket_name']} for {$ticket['event_title']} has been confirmed. Ticket ID: {$ticket['id']}";
                    
                    sendEmail($ticket['recipient_email'], $emailSubject, $emailBody, $plainTextEmail);
                    
                    // Send SMS notification if phone number is provided
                    if (!empty($ticket['recipient_phone'])) {
                        $smsMessage = "Your {$ticket['ticket_name']} for {$ticket['event_title']} is confirmed! Ticket ID: {$ticket['id']}. Event: " . formatDate($ticket['start_date']) . " at {$ticket['venue']}";
                        sendSMS($ticket['recipient_phone'], $smsMessage);
                    }
                    
                    // Create in-app notification
                    $notificationTitle = "Ticket Purchased: {$ticket['ticket_name']}";
                    $notificationMessage = "You have successfully purchased a {$ticket['ticket_name']} for {$ticket['event_title']}.";
                    
                    $db->query("INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                                VALUES ($userId, '" . $db->escape($notificationTitle) . "', '" . $db->escape($notificationMessage) . "', 'ticket', 0, NOW())");
                }
                
                // Set success flag
                $success = true;
                
                // Store order details in session for confirmation page
                $_SESSION['order_details'] = [
                    'reference' => $paymentReference,
                    'total_amount' => $total,
                    'total_tickets' => $totalTickets,
                    'payment_method' => $paymentMethod,
                    'balance_used' => $balanceUsed,
                    'tickets' => $generatedTickets
                ];
                
                // Redirect to confirmation page
                redirect('order-confirmation.php');
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $db->query("ROLLBACK");
                error_log("Checkout Error: " . $e->getMessage());
                               $errors[] = "An error occurred during checkout. Please try again.";
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold">Secure Checkout</h1>
        <div class="text-sm text-gray-600">
            <i class="fas fa-lock mr-1"></i>SSL Secured
        </div>
    </div>

    <!-- Progress Indicator -->
    <div class="mb-8">
        <div class="flex items-center justify-center">
            <div class="flex items-center">
                <div class="flex items-center text-indigo-600">
                    <div
                        class="rounded-full h-8 w-8 bg-indigo-600 text-white flex items-center justify-center text-sm font-medium">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <span class="ml-2 text-sm font-medium">Cart</span>
                </div>
                <div class="flex-1 h-1 bg-indigo-600 mx-4"></div>
                <div class="flex items-center text-indigo-600">
                    <div
                        class="rounded-full h-8 w-8 bg-indigo-600 text-white flex items-center justify-center text-sm font-medium">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <span class="ml-2 text-sm font-medium">Checkout</span>
                </div>
                <div class="flex-1 h-1 bg-gray-300 mx-4"></div>
                <div class="flex items-center text-gray-400">
                    <div
                        class="rounded-full h-8 w-8 bg-gray-300 text-gray-600 flex items-center justify-center text-sm font-medium">
                        <i class="fas fa-check"></i>
                    </div>
                    <span class="ml-2 text-sm font-medium">Confirmation</span>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
        <div class="flex items-center">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <div>
                <p class="font-bold">Please fix the following errors:</p>
                <ul class="list-disc pl-5 mt-2">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex flex-col lg:flex-row gap-8">
        <!-- Checkout Form -->
        <div class="lg:w-2/3">
            <form method="POST" action="" id="checkout-form">
                <!-- Order Review -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-indigo-600 text-white px-6 py-4">
                        <h2 class="text-xl font-bold flex items-center">
                            <i class="fas fa-list-ul mr-2"></i>
                            Order Review
                        </h2>
                    </div>

                    <div class="p-6">
                        <div class="space-y-6">
                            <?php foreach ($cartItems as $index => $item): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-start space-x-4">
                                    <!-- Event Image -->
                                    <div class="flex-shrink-0 w-20 h-20 bg-gray-100 rounded-lg overflow-hidden">
                                        <?php if (!empty($item['image'])): ?>
                                        <img src="<?php echo SITE_URL; ?>/uploads/events/<?php echo $item['image']; ?>"
                                            alt="<?php echo htmlspecialchars($item['title']); ?>"
                                            class="w-full h-full object-cover">
                                        <?php else: ?>
                                        <div
                                            class="w-full h-full flex items-center justify-center bg-gradient-to-br from-indigo-400 to-purple-500">
                                            <i class="fas fa-calendar-alt text-white text-lg"></i>
                                        </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Event Details -->
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-lg text-gray-900 mb-2">
                                            <?php echo htmlspecialchars($item['title']); ?>
                                        </h3>

                                        <!-- Ticket Type Badge -->
                                        <div class="mb-3">
                                            <span
                                                class="inline-flex items-center bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full text-sm font-medium">
                                                <i class="fas fa-ticket-alt mr-2"></i>
                                                <?php echo htmlspecialchars($item['ticket_name']); ?>
                                            </span>
                                            <?php if (!empty($item['ticket_description']) && $item['ticket_description'] !== 'Standard entry ticket'): ?>
                                            <p class="text-sm text-gray-600 mt-1">
                                                <?php echo htmlspecialchars($item['ticket_description']); ?></p>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Event Info -->
                                        <div class="grid grid-cols-2 gap-4 text-sm text-gray-600 mb-3">
                                            <div class="flex items-center">
                                                <i class="far fa-calendar-alt mr-2 text-gray-400"></i>
                                                <?php echo formatDate($item['start_date']); ?>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="far fa-clock mr-2 text-gray-400"></i>
                                                <?php echo formatTime($item['start_time']); ?>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-map-marker-alt mr-2 text-gray-400"></i>
                                                <?php echo htmlspecialchars($item['venue']); ?>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-user mr-2 text-gray-400"></i>
                                                <?php echo htmlspecialchars($item['planner_name']); ?>
                                            </div>
                                        </div>

                                        <!-- Quantity and Price -->
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-600">
                                                Quantity: <span
                                                    class="font-medium"><?php echo $item['quantity']; ?></span>
                                            </span>
                                            <div class="text-right">
                                                <div class="text-lg font-bold text-indigo-600">
                                                    <?php echo formatCurrency($item['ticket_price'] * $item['quantity']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo formatCurrency($item['ticket_price']); ?> each
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Ticket Recipients Information -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-indigo-600 text-white px-6 py-4">
                        <h2 class="text-xl font-bold flex items-center">
                            <i class="fas fa-users mr-2"></i>
                            Ticket Recipients
                        </h2>
                    </div>

                    <div class="p-6">
                        <p class="text-gray-600 mb-6">Please provide recipient information for each ticket. Leave blank
                            to use your account information.</p>

                        <?php foreach ($cartItems as $index => $item): ?>
                        <div
                            class="mb-8 pb-6 <?php echo $index < count($cartItems) - 1 ? 'border-b border-gray-200' : ''; ?>">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-semibold text-lg text-gray-900">
                                    <?php echo htmlspecialchars($item['title']); ?> -
                                    <?php echo htmlspecialchars($item['ticket_name']); ?>
                                </h3>
                                <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium">
                                    <?php echo $item['quantity']; ?>
                                    ticket<?php echo $item['quantity'] > 1 ? 's' : ''; ?>
                                </span>
                            </div>

                            <?php for ($i = 0; $i < $item['quantity']; $i++): ?>
                            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                                <h4 class="font-semibold mb-3 flex items-center">
                                    <i class="fas fa-ticket-alt mr-2 text-indigo-600"></i>
                                    Ticket #<?php echo $i + 1; ?> Recipient
                                </h4>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-2">
                                            Full Name <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text"
                                            name="recipient_name_<?php echo $item['id']; ?>_<?php echo $i; ?>"
                                            value="<?php echo htmlspecialchars($item['recipient_name'] ?? $user['username']); ?>"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                            placeholder="Enter recipient's full name" required>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-2">
                                            Email Address <span class="text-red-500">*</span>
                                        </label>
                                        <input type="email"
                                            name="recipient_email_<?php echo $item['id']; ?>_<?php echo $i; ?>"
                                            value="<?php echo htmlspecialchars($item['recipient_email'] ?? $user['email']); ?>"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                            placeholder="Enter email address" required>
                                        <p class="text-xs text-gray-500 mt-1">Ticket will be sent to this email</p>
                                    </div>
                                    <div>
                                        <label class="block text-gray-700 text-sm font-bold mb-2">
                                            Phone Number
                                        </label>
                                        <input type="tel"
                                            name="recipient_phone_<?php echo $item['id']; ?>_<?php echo $i; ?>"
                                            value="<?php echo htmlspecialchars($item['recipient_phone'] ?? $user['phone_number']); ?>"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                            placeholder="Enter phone number">
                                        <p class="text-xs text-gray-500 mt-1">For SMS notifications (optional)</p>
                                    </div>
                                </div>

                                <!-- Quick Fill Options -->
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button type="button"
                                        class="text-xs bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full hover:bg-indigo-200 transition duration-200"
                                        onclick="fillMyInfo(this, '<?php echo $item['id']; ?>', '<?php echo $i; ?>')">
                                        Use My Info
                                    </button>
                                    <?php if ($i > 0): ?>
                                    <button type="button"
                                        class="text-xs bg-gray-100 text-gray-700 px-3 py-1 rounded-full hover:bg-gray-200 transition duration-200"
                                        onclick="copyFromPrevious(this, '<?php echo $item['id']; ?>', '<?php echo $i; ?>')">
                                        Copy from Previous
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Payment Information -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-indigo-600 text-white px-6 py-4">
                        <h2 class="text-xl font-bold flex items-center">
                            <i class="fas fa-credit-card mr-2"></i>
                            Payment Information
                        </h2>
                    </div>

                    <div class="p-6">
                        <!-- Account Balance Option -->
                        <?php if ($user['balance'] > 0): ?>
                        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                            <label class="flex items-center cursor-pointer">
                                <input type="checkbox" name="use_balance" id="use_balance"
                                    class="form-checkbox h-5 w-5 text-indigo-600 rounded focus:ring-indigo-500"
                                    <?php echo $user['balance'] >= $total ? 'checked' : ''; ?>>
                                <div class="ml-3">
                                    <span class="font-medium text-green-800">
                                        Use my account balance (<?php echo formatCurrency($user['balance']); ?>
                                        available)
                                    </span>
                                    <?php if ($user['balance'] < $total): ?>
                                    <p class="text-sm text-green-700 mt-1">
                                        Your balance will cover <?php echo formatCurrency($user['balance']); ?> of your
                                        total.
                                        The remaining <?php echo formatCurrency($total - $user['balance']); ?> will be
                                        charged to your selected payment method.
                                    </p>
                                    <?php else: ?>
                                    <p class="text-sm text-green-700 mt-1">
                                        Your balance is sufficient to cover the entire purchase.
                                    </p>
                                    <?php endif; ?>
                                </div>
                            </label>
                        </div>
                        <?php endif; ?>

                        <!-- Payment Method Selection -->
                        <div id="payment-method-selection"
                            class="mb-6 <?php echo ($user['balance'] >= $total) ? 'hidden' : ''; ?>">
                            <label class="block text-gray-700 font-bold mb-4">Select Payment Method</label>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Credit Card -->
                                <label
                                    class="flex items-center p-4 border-2 border-gray-200 rounded-lg hover:border-indigo-300 cursor-pointer transition duration-200 payment-method-option">
                                    <input type="radio" name="payment_method" value="credit_card"
                                        class="h-4 w-4 text-indigo-600 payment-method-radio" checked>
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center">
                                            <i class="far fa-credit-card text-2xl text-gray-500 mr-3"></i>
                                            <div>
                                                <div class="font-medium text-gray-900">Credit Card</div>
                                                <div class="text-sm text-gray-500">Visa, Mastercard, American Express
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </label>

                                <!-- Mobile Money -->
                                <label
                                    class="flex items-center p-4 border-2 border-gray-200 rounded-lg hover:border-indigo-300 cursor-pointer transition duration-200 payment-method-option">
                                    <input type="radio" name="payment_method" value="mobile_money"
                                        class="h-4 w-4 text-indigo-600 payment-method-radio">
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center">
                                            <i class="fas fa-mobile-alt text-2xl text-gray-500 mr-3"></i>
                                            <div>
                                                <div class="font-medium text-gray-900">Mobile Money</div>
                                                <div class="text-sm text-gray-500">MTN, Airtel, Vodafone</div>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Credit Card Details -->
                        <div id="credit-card-details" class="payment-details mb-6 p-4 bg-gray-50 rounded-lg">
                            <h3 class="font-semibold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-credit-card mr-2 text-indigo-600"></i>
                                Credit Card Information
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div class="md:col-span-2">
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Card Number</label>
                                    <input type="text" id="card_number" name="card_number"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                        placeholder="4242 4242 4242 4242" maxlength="19">
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        For testing, use: 4242 4242 4242 4242
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Cardholder Name</label>
                                    <input type="text" id="card_name" name="card_name"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                        placeholder="John Doe">
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Email for Receipt</label>
                                    <input type="email" id="card_email" name="card_email"
                                        value="<?php echo htmlspecialchars($user['email']); ?>"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Expiry Month</label>
                                    <select id="card_month" name="card_month"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo sprintf('%02d', $i); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Expiry Year</label>
                                    <select id="card_year" name="card_year"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                        <?php 
                                        $currentYear = date('Y');
                                        for ($i = $currentYear; $i <= $currentYear + 10; $i++): 
                                        ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">CVV</label>
                                    <input type="text" id="card_cvv" name="card_cvv"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                        placeholder="123" maxlength="4">
                                    <p class="text-xs text-gray-500 mt-1">3-4 digits on back of card</p>
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Money Details -->
                        <div id="mobile-money-details" class="payment-details mb-6 p-4 bg-gray-50 rounded-lg hidden">
                            <h3 class="font-semibold text-gray-900 mb-4 flex items-center">
                                <i class="fas fa-mobile-alt mr-2 text-indigo-600"></i>
                                Mobile Money Information
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Mobile Number</label>
                                    <input type="text" id="mobile_number" name="mobile_number"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                        placeholder="07XX XXX XXX">
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        For testing, use: 0700000000
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Network Provider</label>
                                    <select id="mobile_provider" name="mobile_provider"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                        <option value="mtn">MTN Mobile Money</option>
                                        <option value="airtel">Airtel Money</option>
                                        <option value="vodafone">Vodafone Cash</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex items-start">
                                    <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-2"></i>
                                    <div class="text-sm text-blue-700">
                                        <p class="font-medium">How it works:</p>
                                        <ol class="list-decimal list-inside mt-1 space-y-1">
                                            <li>You'll receive a payment prompt on your phone</li>
                                            <li>Enter your Mobile Money PIN to confirm</li>
                                            <li>You'll receive a confirmation SMS</li>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="mt-8">
                            <button type="submit" id="payment-button"
                                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 px-6 rounded-lg transition duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-indigo-300">
                                <i class="fas fa-lock mr-2"></i>
                                Complete Secure Purchase - <?php echo formatCurrency($total); ?>
                            </button>
                        </div>

                        <!-- Security Information -->
                        <div class="mt-6 text-center">
                            <div class="flex items-center justify-center space-x-6 text-sm text-gray-600">
                                <div class="flex items-center">
                                    <i class="fas fa-shield-alt text-green-600 mr-2"></i>
                                    <span>SSL Encrypted</span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-lock text-green-600 mr-2"></i>
                                    <span>Secure Payment</span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-mobile-alt text-green-600 mr-2"></i>
                                    <span>Instant Delivery</span>
                                </div>
                            </div>
                        </div>

                        <!-- Terms and Conditions -->
                        <div class="mt-6 text-center text-xs text-gray-500">
                            <p>By completing this purchase, you agree to our
                                <a href="#" class="text-indigo-600 hover:text-indigo-800 underline">Terms of
                                    Service</a>,
                                <a href="#" class="text-indigo-600 hover:text-indigo-800 underline">Privacy Policy</a>,
                                and
                                <a href="#" class="text-indigo-600 hover:text-indigo-800 underline">Refund Policy</a>
                            </p>
                            <p class="mt-2 bg-gray-100 p-2 rounded">
                                <i class="fas fa-info-circle mr-1"></i>
                                This is a demo environment. No actual payments will be processed.
                            </p>
                        </div>
                    </div>
                </div>
            </form>
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
                    <!-- Items Summary -->
                    <div class="space-y-4 mb-6">
                        <h3 class="font-semibold text-gray-900">Items in Order</h3>
                        <?php foreach ($cartItems as $item): ?>
                        <div class="flex justify-between items-start text-sm">
                            <div class="flex-1 pr-4">
                                <div class="font-medium text-gray-900 truncate">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </div>
                                <div class="text-gray-600">
                                    <?php echo $item['quantity']; ?>x
                                    <?php echo htmlspecialchars($item['ticket_name']); ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo formatDate($item['start_date']); ?> •
                                    <?php echo htmlspecialchars($item['venue']); ?>
                                </div>
                            </div>
                            <div class="font-semibold text-gray-900">
                                <?php echo formatCurrency($item['ticket_price'] * $item['quantity']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pricing Breakdown -->
                    <div class="border-t border-gray-200 pt-4 space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Subtotal (<?php echo $totalTickets; ?> tickets)</span>
                            <span class="font-medium"><?php echo formatCurrency($subtotal); ?></span>
                        </div>

                        <?php if ($fees > 0): ?>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">
                                Service Fee (<?php echo $feePercentage; ?>%)
                                <i class="fas fa-info-circle text-gray-400 ml-1" title="Platform service fee"></i>
                            </span>
                            <span class="font-medium"><?php echo formatCurrency($fees); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($user['balance'] > 0): ?>
                        <div class="flex justify-between text-sm text-green-600" id="balance-usage"
                            style="display: none;">
                            <span>Account Balance Used</span>
                            <span class="font-medium"
                                id="balance-amount">-<?php echo formatCurrency(min($user['balance'], $total)); ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="border-t border-gray-200 pt-3">
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-bold text-gray-900">Total</span>
                                <span class="text-2xl font-bold text-indigo-600" id="final-total">
                                    <?php echo formatCurrency($total); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Summary -->
                    <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-3">Payment Summary</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Events:</span>
                                <span class="font-medium"><?php echo count($cartItems); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Tickets:</span>
                                <span class="font-medium"><?php echo $totalTickets; ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Payment Method:</span>
                                <span class="font-medium" id="selected-payment-method">Credit Card</span>
                            </div>
                        </div>
                    </div>

                    <!-- Security Badges -->
                    <div class="mt-6 text-center">
                        <div class="grid grid-cols-2 gap-4 text-xs text-gray-600">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-shield-alt text-2xl text-green-600 mb-1"></i>
                                <span>Secure Payment</span>
                            </div>
                            <div class="flex flex-col items-center">
                                <i class="fas fa-mobile-alt text-2xl text-blue-600 mb-1"></i>
                                <span>Instant Delivery</span>
                            </div>
                        </div>
                    </div>

                    <!-- Support Information -->
                    <div class="mt-6 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="text-center">
                            <h5 class="font-semibold text-blue-900 text-sm mb-1">Need Help?</h5>
                            <p class="text-xs text-blue-700 mb-2">Our support team is here to assist you</p>
                            <div class="flex justify-center space-x-4 text-xs">
                                <a href="mailto:support@example.com" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-envelope mr-1"></i>Email
                                </a>
                                <a href="tel:+1234567890" class="text-blue-600 hover:text-blue-800">
                                    <i class="fas fa-phone mr-1"></i>Call
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced JavaScript for Checkout -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // User information for quick fill
    const userInfo = {
        name: '<?php echo addslashes($user['username']); ?>',
        email: '<?php echo addslashes($user['email']); ?>',
        phone: '<?php echo addslashes($user['phone_number']); ?>'
    };

    // Payment method handling
    const paymentMethods = document.querySelectorAll('.payment-method-radio');
    const paymentDetails = document.querySelectorAll('.payment-details');
    const useBalanceCheckbox = document.getElementById('use_balance');
    const paymentMethodSelection = document.getElementById('payment-method-selection');
    const paymentButton = document.getElementById('payment-button');
    const selectedPaymentMethodSpan = document.getElementById('selected-payment-method');
    const balanceUsage = document.getElementById('balance-usage');
    const finalTotal = document.getElementById('final-total');

    // Quick fill functions
    window.fillMyInfo = function(button, itemId, ticketIndex) {
        const container = button.closest('.bg-gray-50');
        container.querySelector(`input[name="recipient_name_${itemId}_${ticketIndex}"]`).value = userInfo
            .name;
        container.querySelector(`input[name="recipient_email_${itemId}_${ticketIndex}"]`).value = userInfo
            .email;
        container.querySelector(`input[name="recipient_phone_${itemId}_${ticketIndex}"]`).value = userInfo
            .phone;

        // Add visual feedback
        button.style.backgroundColor = '#10B981';
        button.style.color = 'white';
        button.innerHTML = '<i class="fas fa-check mr-1"></i>Applied';
        setTimeout(() => {
            button.style.backgroundColor = '';
            button.style.color = '';
            button.innerHTML = 'Use My Info';
        }, 2000);
    };

    window.copyFromPrevious = function(button, itemId, ticketIndex) {
        if (ticketIndex > 0) {
            const currentContainer = button.closest('.bg-gray-50');
            const previousContainer = currentContainer.parentElement.children[ticketIndex - 1];

            const prevName = previousContainer.querySelector(
                `input[name="recipient_name_${itemId}_${ticketIndex - 1}"]`).value;
            const prevEmail = previousContainer.querySelector(
                `input[name="recipient_email_${itemId}_${ticketIndex - 1}"]`).value;
            const prevPhone = previousContainer.querySelector(
                `input[name="recipient_phone_${itemId}_${ticketIndex - 1}"]`).value;

            currentContainer.querySelector(`input[name="recipient_name_${itemId}_${ticketIndex}"]`).value =
                prevName;
            currentContainer.querySelector(`input[name="recipient_email_${itemId}_${ticketIndex}"]`).value =
                prevEmail;
            currentContainer.querySelector(`input[name="recipient_phone_${itemId}_${ticketIndex}"]`).value =
                prevPhone;

            // Add visual feedback
            button.style.backgroundColor = '#10B981';
            button.style.color = 'white';
            button.innerHTML = '<i class="fas fa-check mr-1"></i>Copied';
            setTimeout(() => {
                button.style.backgroundColor = '';
                button.style.color = '';
                button.innerHTML = 'Copy from Previous';
            }, 2000);
        }
    };

    // Payment method selection
    function togglePaymentDetails() {
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked')?.value ||
            'credit_card';

        // Hide all payment details
        paymentDetails.forEach(detail => {
            detail.classList.add('hidden');
        });

        // Show selected payment method details
        if (selectedMethod === 'credit_card') {
            document.getElementById('credit-card-details').classList.remove('hidden');
            selectedPaymentMethodSpan.textContent = 'Credit Card';
        } else if (selectedMethod === 'mobile_money') {
            document.getElementById('mobile-money-details').classList.remove('hidden');
            selectedPaymentMethodSpan.textContent = 'Mobile Money';
        }

        // Update payment method option styling
        document.querySelectorAll('.payment-method-option').forEach(option => {
            option.classList.remove('border-indigo-500', 'bg-indigo-50');
            option.classList.add('border-gray-200');
        });

        const selectedOption = document.querySelector(`input[value="${selectedMethod}"]`).closest(
            '.payment-method-option');
        selectedOption.classList.remove('border-gray-200');
        selectedOption.classList.add('border-indigo-500', 'bg-indigo-50');
    }

    // Balance usage handling
    function togglePaymentMethodSection() {
        const userBalance = <?php echo $user['balance']; ?>;
        const totalAmount = <?php echo $total; ?>;

        if (useBalanceCheckbox && useBalanceCheckbox.checked) {
            if (userBalance >= totalAmount) {
                // Balance covers full amount
                paymentMethodSelection.classList.add('hidden');
                paymentDetails.forEach(detail => detail.classList.add('hidden'));
                paymentButton.innerHTML = '<i class="fas fa-wallet mr-2"></i>Complete Purchase Using Balance';
                selectedPaymentMethodSpan.textContent = 'Account Balance';
                finalTotal.textContent = '<?php echo formatCurrency(0); ?>';
            } else {
                // Partial balance usage
                paymentMethodSelection.classList.remove('hidden');
                togglePaymentDetails();
                paymentButton.innerHTML =
                    '<i class="fas fa-credit-card mr-2"></i>Complete Purchase - <?php echo formatCurrency($total - $user['balance']); ?>';
                finalTotal.textContent = '<?php echo formatCurrency($total - $user['balance']); ?>';
            }
            balanceUsage.style.display = 'flex';
        } else {
            // No balance usage
            paymentMethodSelection.classList.remove('hidden');
            togglePaymentDetails();
            paymentButton.innerHTML =
                '<i class="fas fa-lock mr-2"></i>Complete Secure Purchase - <?php echo formatCurrency($total); ?>';
            selectedPaymentMethodSpan.textContent = document.querySelector(
                    'input[name="payment_method"]:checked')?.value === 'mobile_money' ? 'Mobile Money' :
                'Credit Card';
            finalTotal.textContent = '<?php echo formatCurrency($total); ?>';
            balanceUsage.style.display = 'none';
        }
    }

    // Event listeners
    paymentMethods.forEach(method => {
        method.addEventListener('change', togglePaymentDetails);
    });

    if (useBalanceCheckbox) {
        useBalanceCheckbox.addEventListener('change', togglePaymentMethodSection);
    }

    // Card number formatting
    const cardNumberInput = document.getElementById('card_number');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            if (formattedValue !== e.target.value) {
                e.target.value = formattedValue;
            }
        });
    }

    // Form validation and submission
    const form = document.getElementById('checkout-form');
    form.addEventListener('submit', function(e) {
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked')?.value;
        const useBalance = useBalanceCheckbox?.checked || false;
        const userBalance = <?php echo $user['balance']; ?>;
        const totalAmount = <?php echo $total; ?>;

        // If using balance and balance covers total, proceed without additional validation
        if (useBalance && userBalance >= totalAmount) {
            showProcessingOverlay('Processing payment using account balance...');
            return true;
        }

        // Validate payment details based on selected method
        if (selectedMethod === 'credit_card') {
            const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
            const cardName = document.getElementById('card_name').value.trim();
            const cardCvv = document.getElementById('card_cvv').value.trim();

            if (!cardNumber || !cardName || !cardCvv) {
                e.preventDefault();
                alert('Please fill in all credit card details.');
                return false;
            }

            if (cardNumber !== '4242424242424242' && !confirm(
                    'For testing, please use card number 4242 4242 4242 4242. Continue anyway?')) {
                e.preventDefault();
                return false;
            }

            showProcessingOverlay('Processing credit card payment...');

        } else if (selectedMethod === 'mobile_money') {
            const mobileNumber = document.getElementById('mobile_number').value.trim();

            if (!mobileNumber) {
                e.preventDefault();
                alert('Please enter your mobile number.');
                return false;
            }

            if (mobileNumber !== '0700000000' && !confirm(
                    'For testing, please use mobile number 0700000000. Continue anyway?')) {
                e.preventDefault();
                return false;
            }

            showProcessingOverlay('Sending payment request to your mobile phone...');
        }

        // Validate recipient information
        const requiredFields = form.querySelectorAll('input[required]');
        for (let field of requiredFields) {
            if (!field.value.trim()) {
                e.preventDefault();
                field.focus();
                alert('Please fill in all required fields.');
                return false;
            }
        }

        return true;
    });

    // Processing overlay function
    function showProcessingOverlay(message) {
        let overlay = document.getElementById('payment-processing-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'payment-processing-overlay';
            overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            overlay.innerHTML = `
                <div class="bg-white p-8 rounded-lg shadow-lg text-center max-w-md mx-4">
                    <div class="animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-indigo-500 mx-auto mb-6"></div>
                    <h3 class="text-xl font-bold mb-3 text-gray-900">Processing Payment</h3>
                    <p id="processing-message" class="text-gray-600 mb-4">${message}</p>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                        <p class="text-sm text-yellow-800">
                            <i class="fas fa-info-circle mr-1"></i>
                            This is a demo environment. No actual payment is being processed.
                        </p>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
        } else {
            document.getElementById('processing-message').textContent = message;
            overlay.classList.remove('hidden');
        }
    }

    // Auto-fill email validation
    const emailInputs = document.querySelectorAll('input[type="email"]');
    emailInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const email = this.value.trim();
            if (email && !isValidEmail(email)) {
                this.classList.add('border-red-500');
                this.classList.remove('border-gray-300');

                // Show error message
                let errorMsg = this.parentElement.querySelector('.email-error');
                if (!errorMsg) {
                    errorMsg = document.createElement('p');
                    errorMsg.className = 'email-error text-xs text-red-500 mt-1';
                    errorMsg.innerHTML =
                        '<i class="fas fa-exclamation-circle mr-1"></i>Please enter a valid email address';
                    this.parentElement.appendChild(errorMsg);
                }
            } else {
                this.classList.remove('border-red-500');
                this.classList.add('border-gray-300');

                // Remove error message
                const errorMsg = this.parentElement.querySelector('.email-error');
                if (errorMsg) {
                    errorMsg.remove();
                }
            }
        });
    });

    // Email validation function
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Phone number formatting
    const phoneInputs = document.querySelectorAll('input[type="tel"]');
    phoneInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = value;
                } else if (value.length <= 6) {
                    value = value.slice(0, 3) + ' ' + value.slice(3);
                } else {
                    value = value.slice(0, 3) + ' ' + value.slice(3, 6) + ' ' + value.slice(6,
                        9);
                }
            }
            e.target.value = value;
        });
    });

    // Initialize
    togglePaymentDetails();
    if (useBalanceCheckbox) {
        togglePaymentMethodSection();
    }

    // Add smooth animations
    const formSections = document.querySelectorAll('.bg-white.rounded-lg.shadow-md');
    formSections.forEach((section, index) => {
        section.style.opacity = '0';
        section.style.transform = 'translateY(20px)';

        setTimeout(() => {
            section.style.transition = 'all 0.6s ease';
            section.style.opacity = '1';
            section.style.transform = 'translateY(0)';
        }, index * 200);
    });

    // Add loading states to buttons
    const buttons = document.querySelectorAll('button[type="button"]');
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';

            setTimeout(() => {
                this.disabled = false;
                this.innerHTML = originalText;
            }, 1000);
        });
    });

    // Real-time form validation feedback
    const requiredInputs = document.querySelectorAll('input[required]');
    requiredInputs.forEach(input => {
        input.addEventListener('input', function() {
            if (this.value.trim()) {
                this.classList.remove('border-red-300');
                this.classList.add('border-green-300');
            } else {
                this.classList.remove('border-green-300');
                this.classList.add('border-red-300');
            }
        });
    });

    // Scroll to first error on form submission
    form.addEventListener('invalid', function(e) {
        e.preventDefault();
        const firstInvalid = form.querySelector(':invalid');
        if (firstInvalid) {
            firstInvalid.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            firstInvalid.focus();
        }
    }, true);
});

// Additional utility functions
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-RW', {
        style: 'currency',
        currency: 'RWF',
        minimumFractionDigits: 0
    }).format(amount);
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm ${
        type === 'success' ? 'bg-green-100 text-green-800 border border-green-200' :
        type === 'error' ? 'bg-red-100 text-red-800 border border-red-200' :
        'bg-blue-100 text-blue-800 border border-blue-200'
    }`;

    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${
                type === 'success' ? 'fa-check-circle' :
                type === 'error' ? 'fa-exclamation-circle' :
                'fa-info-circle'
            } mr-2"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;

    document.body.appendChild(notification);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}
</script>

<!-- Enhanced CSS for better styling -->
<style>
/* Custom form styling */
.form-checkbox:checked {
    background-color: #4F46E5;
    border-color: #4F46E5;
}

.payment-method-option:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.payment-method-option.selected {
    border-color: #4F46E5 !important;
    background-color: #EEF2FF !important;
}

/* Loading animation */
@keyframes spin {
    0% {
        transform: rotate(0deg);
    }

    100% {
        transform: rotate(360deg);
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}

/* Smooth transitions */
.transition-all {
    transition: all 0.3s ease;
}

/* Input focus states */
input:focus,
select:focus {
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

/* Button hover effects */
button:hover {
    transform: translateY(-1px);
}

/* Card styling */
.bg-white {
    backdrop-filter: blur(10px);
}

/* Mobile responsive adjustments */
@media (max-width: 768px) {
    .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .grid-cols-3 {
        grid-template-columns: repeat(2, 1fr);
    }

    .grid-cols-3>div:last-child {
        grid-column: span 2;
    }

    .text-3xl {
        font-size: 1.875rem;
    }

    .sticky {
        position: relative !important;
        top: auto !important;
    }
}

/* Print styles */
@media print {
    .no-print {
        display: none !important;
    }

    .bg-indigo-600 {
        background-color: #000 !important;
        color: #fff !important;
    }
}

/* Accessibility improvements */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* High contrast mode */
@media (prefers-contrast: high) {
    .border-gray-300 {
        border-color: #000;
    }

    .text-gray-600 {
        color: #000;
    }
}
</style>

<?php include 'includes/footer.php'; ?>