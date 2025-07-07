<?php
$pageTitle = "Buy Resale Ticket";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';
// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Please login to purchase tickets.";
    redirect('login.php?redirect=buy-resale-ticket.php?id=' . ($_GET['id'] ?? ''));
}

$userId = getCurrentUserId();
$resaleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($resaleId <= 0) {
    $_SESSION['error_message'] = "Invalid resale listing ID.";
    redirect('marketplace.php');
}

// Get resale listing details
$sql = "SELECT tr.*, t.id as ticket_id, t.qr_code, t.recipient_name, t.recipient_email, t.recipient_phone,
               e.title, e.start_date, e.start_time, e.venue, e.city, e.address, e.description, e.image,
               tt.name as ticket_type_name, tt.description as ticket_description,
               seller.username as seller_name, seller.email as seller_email
        FROM ticket_resales tr
        JOIN tickets t ON tr.ticket_id = t.id
        JOIN events e ON t.event_id = e.id
        LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
        JOIN users seller ON tr.seller_id = seller.id
        WHERE tr.id = $resaleId AND tr.status = 'active'";

$listing = $db->fetchOne($sql);

if (!$listing) {
    $_SESSION['error_message'] = "Resale listing not found or no longer available.";
    redirect('marketplace.php');
}

// Check if user is trying to buy their own ticket
if ($listing['seller_id'] == $userId) {
    $_SESSION['error_message'] = "You cannot buy your own ticket.";
    redirect('marketplace.php');
}

// Get user information
$userSql = "SELECT username, email, phone_number, balance FROM users WHERE id = $userId";
$user = $db->fetchOne($userSql);

// Process purchase
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_ticket'])) {
    $paymentMethod = $_POST['payment_method'] ?? '';
    $useBalance = isset($_POST['use_balance']) ? true : false;
    $recipientName = trim($_POST['recipient_name_1_0'] ?? '');
    $recipientEmail = trim($_POST['recipient_email_1_0'] ?? '');
    $recipientPhone = trim($_POST['recipient_phone_1_0'] ?? '');
    
    // Validate inputs
    if (empty($recipientName)) {
        $errors[] = "Recipient name is required.";
    }
    
    if (empty($recipientEmail) || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid recipient email is required.";
    }
    
    if (empty($recipientPhone)) {
        $errors[] = "Recipient phone number is required.";
    }
    
    // Validate payment method
    if (empty($paymentMethod) && (!$useBalance || $user['balance'] < $listing['resale_price'])) {
        $errors[] = "Please select a payment method.";
    }
    
    // Process payment
    if (empty($errors)) {
        $totalPrice = $listing['resale_price'];
        $amountToCharge = $totalPrice;
        $balanceUsed = 0;
        
        if ($useBalance && $user['balance'] > 0) {
            if ($user['balance'] >= $totalPrice) {
                $balanceUsed = $totalPrice;
                $amountToCharge = 0;
            } else {
                $balanceUsed = $user['balance'];
                $amountToCharge = $totalPrice - $balanceUsed;
            }
        }
        
        // If Stripe payment is needed
        if ($amountToCharge > 0 && $paymentMethod === 'credit_card') {
            require_once __DIR__ . '/vendor/autoload.php';
            \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
            
            // Prepare line item for Stripe
            $line_items = [[
                'price_data' => [
                    'currency' => 'rwf',
                    'product_data' => [
                        'name' => $listing['title'] . ' - ' . ($listing['ticket_type_name'] ?? 'Standard Ticket'),
                    ],
                    'unit_amount' => intval($amountToCharge * 1), // Stripe expects cents
                ],
                'quantity' => 1,
            ]];
            
            // Store necessary info in session for post-payment processing
            $_SESSION['resale_payment'] = [
                'resale_id' => $resaleId,
                'recipient_name' => $recipientName,
                'recipient_email' => $recipientEmail,
                'recipient_phone' => $recipientPhone,
                'balance_used' => $balanceUsed,
                'amount_to_charge' => $amountToCharge,
                'payment_method' => $paymentMethod,
            ];
            
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => $line_items,
                'mode' => 'payment',
                'customer_email' => $user['email'],
                'success_url' => SITE_URL . '/stripe-payment-success.php?session_id={CHECKOUT_SESSION_ID}&resale=1',
                'cancel_url' => SITE_URL . '/buy-resale-ticket.php?id=' . $resaleId . '&canceled=1',
            ]);
            header('Location: ' . $session->url);
            exit;
        }
        
        // If no Stripe payment needed, process purchase immediately
        // Simulate payment processing
        $paymentSuccess = true;
        $paymentReference = generateRandomString(12);
        
        if ($paymentSuccess) {
            try {
                $db->query("START TRANSACTION");
                
                // Deduct from buyer's balance if used
                if ($balanceUsed > 0) {
                    $newBuyerBalance = $user['balance'] - $balanceUsed;
                    $db->query("UPDATE users SET balance = $newBuyerBalance WHERE id = $userId");
                    
                    // Record buyer's balance transaction
                    $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, payment_method, description)
                                VALUES ($userId, $balanceUsed, 'purchase', 'completed', '$paymentReference', 'balance', 'Resale ticket purchase using account balance')");
                }
                
                // Record external payment transaction if any
                if ($amountToCharge > 0) {
                    $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, payment_method, description)
                                VALUES ($userId, $amountToCharge, 'purchase', 'completed', '$paymentReference', '$paymentMethod', 'Resale ticket purchase')");
                }
                
                // Add earnings to seller's balance
                $sellerEarnings = $listing['seller_earnings'];
                $db->query("UPDATE users SET balance = balance + $sellerEarnings WHERE id = " . $listing['seller_id']);
                
                // Record seller's earnings transaction
                $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, description)
                            VALUES (" . $listing['seller_id'] . ", $sellerEarnings, 'sale', 'completed', '$paymentReference', 'Resale ticket sale earnings')");
                
                // Record platform fee
                $platformFee = $listing['platform_fee'];
                if ($platformFee > 0) {
                    $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, description)
                                VALUES (" . $listing['seller_id'] . ", $platformFee, 'system_fee', 'completed', '$paymentReference', 'Platform fee for resale transaction')");
                }
                
                // Update ticket ownership and details
                $updateTicketSql = "UPDATE tickets SET 
                                   user_id = $userId,
                                   recipient_name = '" . $db->escape($recipientName) . "',
                                   recipient_email = '" . $db->escape($recipientEmail) . "',
                                   recipient_phone = '" . $db->escape($recipientPhone) . "',
                                   purchase_price = " . $listing['resale_price'] . ",
                                   status = 'sold',
                                   updated_at = NOW(),
                                   created_at = NOW()
                                   WHERE id = " . $listing['ticket_id'];
                $db->query($updateTicketSql);
                
                // Update resale listing status
                $db->query("UPDATE ticket_resales SET status = 'sold', sold_at = NOW() WHERE id = $resaleId");
                
                // Send notifications
                
                // Notify buyer
                $buyerNotificationTitle = "Ticket Purchase Successful";
                $buyerNotificationMessage = "You have successfully purchased a resale ticket for '" . $listing['title'] . "'.";
                // Insert notification for buyer
                global $db;
                $db->query("INSERT INTO notifications (user_id, title, message, type) VALUES ($userId, '" . $db->escape($buyerNotificationTitle) . "', '" . $db->escape($buyerNotificationMessage) . "', 'ticket')");
                
                // Notify seller
                $sellerNotificationTitle = "Ticket Sold";
                $sellerNotificationMessage = "Your ticket for '" . $listing['title'] . "' has been sold for " . formatCurrency($listing['resale_price']) . ". Earnings: " . formatCurrency($sellerEarnings);
                $db->query("INSERT INTO notifications (user_id, title, message, type) VALUES (" . $listing['seller_id'] . ", '" . $db->escape($sellerNotificationTitle) . "', '" . $db->escape($sellerNotificationMessage) . "', 'ticket')");
                
                // Send email to buyer
                $buyerEmailSubject = "Ticket Purchase Confirmation - " . $listing['title'];
                $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($listing['qr_code']);
                
                $ticketData = [
                    'id' => $listing['ticket_id'],
                    'recipient_name' => $recipientName,
                    'recipient_email' => $recipientEmail,
                    'recipient_phone' => $recipientPhone,
                    'purchase_price' => $listing['resale_price'],
                    'qr_code' => $listing['qr_code']
                ];
                
                $eventDetails = [
                    'title' => $listing['title'],
                    'start_date' => $listing['start_date'],
                    'start_time' => $listing['start_time'],
                    'venue' => $listing['venue'],
                    'city' => $listing['city'],
                    'address' => $listing['address']
                ];
                
                $buyerEmailBody = getEnhancedTicketEmailTemplate($ticketData, $eventDetails, $qrCodeUrl);
                sendEmail($recipientEmail, $buyerEmailSubject, $buyerEmailBody);
                
                // Send email to seller
                $sellerEmailSubject = "Your Ticket Has Been Sold - " . $listing['title'];
                $sellerEmailBody = "
                <h2>Congratulations! Your ticket has been sold.</h2>
                <p>Your ticket for <strong>" . htmlspecialchars($listing['title']) . "</strong> has been successfully sold.</p>
                <p><strong>Sale Details:</strong></p>
                <ul>
                    <li>Sale Price: " . formatCurrency($listing['resale_price']) . "</li>
                    <li>Platform Fee: " . formatCurrency($platformFee) . "</li>
                    <li>Your Earnings: " . formatCurrency($sellerEarnings) . "</li>
                </ul>
                <p>The earnings have been added to your account balance.</p>
                ";
                sendEmail($listing['seller_email'], $sellerEmailSubject, $sellerEmailBody);
                
                $db->query("COMMIT");
                
                $_SESSION['success_message'] = "Ticket purchased successfully! Check your email for ticket details.";
                $_SESSION['order_reference'] = $paymentReference;
                redirect('order-confirmation.php');
                
            } catch (Exception $e) {
                $db->query("ROLLBACK");
                error_log("Resale purchase error: " . $e->getMessage());
                $errors[] = "An error occurred during purchase. Please try again.";
            }
        } else {
            $errors[] = "Payment processing failed. Please try again.";
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
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-start space-x-4">
                                    <!-- Event Image -->
                                    <div class="flex-shrink-0 w-20 h-20 bg-gray-100 rounded-lg overflow-hidden">
                                        <?php if (!empty($listing['image'])): ?>
                                        <img src="<?php echo SITE_URL; ?>/uploads/events/<?php echo $listing['image']; ?>"
                                            alt="<?php echo htmlspecialchars($listing['title']); ?>"
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
                                            <?php echo htmlspecialchars($listing['title']); ?>
                                        </h3>
                                        <!-- Ticket Type Badge -->
                                        <div class="mb-3">
                                            <span
                                                class="inline-flex items-center bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full text-sm font-medium">
                                                <i class="fas fa-ticket-alt mr-2"></i>
                                                <?php echo htmlspecialchars($listing['ticket_type_name'] ?? 'Standard Ticket'); ?>
                                            </span>
                                            <?php if (!empty($listing['ticket_description']) && $listing['ticket_description'] !== 'Standard entry ticket'): ?>
                                            <p class="text-sm text-gray-600 mt-1">
                                                <?php echo htmlspecialchars($listing['ticket_description']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <!-- Event Info -->
                                        <div class="grid grid-cols-2 gap-4 text-sm text-gray-600 mb-3">
                                            <div class="flex items-center">
                                                <i class="far fa-calendar-alt mr-2 text-gray-400"></i>
                                                <?php echo formatDate($listing['start_date']); ?>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="far fa-clock mr-2 text-gray-400"></i>
                                                <?php echo formatTime($listing['start_time']); ?>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-map-marker-alt mr-2 text-gray-400"></i>
                                                <?php echo htmlspecialchars($listing['venue']); ?>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="fas fa-user mr-2 text-gray-400"></i>
                                                <?php echo htmlspecialchars($listing['seller_name']); ?>
                                            </div>
                                        </div>
                                        <!-- Quantity and Price -->
                                        <div class="flex justify-between items-center">
                                            <span class="text-gray-600">
                                                Quantity: <span class="font-medium">1</span>
                                            </span>
                                            <div class="text-right">
                                                <div class="text-lg font-bold text-indigo-600">
                                                    <?php echo formatCurrency($listing['resale_price']); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo formatCurrency($listing['resale_price']); ?> each
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Ticket Recipients Information -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-indigo-600 text-white px-6 py-4">
                        <h2 class="text-xl font-bold flex items-center">
                            <i class="fas fa-users mr-2"></i>
                            Ticket Recipient
                        </h2>
                    </div>
                    <div class="p-6">
                        <p class="text-gray-600 mb-6">Please provide recipient information for this ticket. Leave blank
                            to use your account information.</p>
                        <div class="bg-gray-50 p-4 rounded-lg mb-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">
                                        Full Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="recipient_name_1_0"
                                        value="<?php echo htmlspecialchars($user['username']); ?>"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                        placeholder="Enter recipient's full name" required>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">
                                        Email Address <span class="text-red-500">*</span>
                                    </label>
                                    <input type="email" name="recipient_email_1_0"
                                        value="<?php echo htmlspecialchars($user['email']); ?>"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                        placeholder="Enter email address" required>
                                    <p class="text-xs text-gray-500 mt-1">Ticket will be sent to this email</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">
                                        Phone Number
                                    </label>
                                    <input type="tel" name="recipient_phone_1_0"
                                        value="<?php echo htmlspecialchars($user['phone_number']); ?>"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                        placeholder="Enter phone number">
                                    <p class="text-xs text-gray-500 mt-1">For SMS notifications (optional)</p>
                                </div>
                            </div>
                        </div>
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
                                    <?php echo $user['balance'] >= $listing['resale_price'] ? 'checked' : ''; ?>>
                                <div class="ml-3">
                                    <span class="font-medium text-green-800">
                                        Use my account balance (<?php echo formatCurrency($user['balance']); ?>
                                        available)
                                    </span>
                                    <?php if ($user['balance'] < $listing['resale_price']): ?>
                                    <p class="text-sm text-green-700 mt-1">
                                        Your balance will cover <?php echo formatCurrency($user['balance']); ?> of your
                                        total. The remaining
                                        <?php echo formatCurrency($listing['resale_price'] - $user['balance']); ?> will
                                        be charged to your selected payment method.
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
                            class="mb-6 <?php echo ($user['balance'] >= $listing['resale_price']) ? 'hidden' : ''; ?>">
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
                                Pay with Card (Stripe)
                            </h3>
                            <button type="submit" id="stripe-checkout-button"
                                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 px-6 rounded-lg transition duration-300 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-indigo-300">
                                <i class="fab fa-cc-stripe mr-2"></i>
                                Pay Securely with Stripe
                            </button>
                            <p class="text-xs text-gray-500 mt-2">
                                You will be redirected to Stripe's secure payment page.
                            </p>
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
                                    <p class="text-xs text-gray-500 mt-1">Use 0700000000 for testing</p>
                                </div>
                                <div>
                                    <label class="block text-gray-700 text-sm font-bold mb-2">Network Provider</label>
                                    <select id="mobile_provider" name="mobile_provider"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                        <option value="mtn">MTN Mobile Money</option>
                                        <option value="airtel">Airtel Money</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mt-6">
                            <button type="submit" name="purchase_ticket" id="payment-button"
                                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded transition duration-300">
                                <i class="fas fa-credit-card mr-2"></i> Complete Purchase
                            </button>
                        </div>
                        <div class="mt-4 text-center text-sm text-gray-600">
                            <p>By completing this purchase, you agree to our <a href="#"
                                    class="text-indigo-600 hover:text-indigo-800">Terms of Service</a></p>
                            <p class="mt-2 text-xs bg-gray-100 p-2 rounded">
                                <i class="fas fa-shield-alt mr-1"></i> This is a secure, encrypted payment. Your payment
                                details are protected.
                            </p>
                        </div>
                    </div>
                </div>
                <input type="hidden" name="purchase_ticket" value="1">
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
                    <div class="space-y-4 mb-6">
                        <h3 class="font-semibold text-gray-900">Items in Order</h3>
                        <div class="flex justify-between items-start text-sm">
                            <div class="flex-1 pr-4">
                                <div class="font-medium text-gray-900 truncate">
                                    <?php echo htmlspecialchars($listing['title']); ?>
                                </div>
                                <div class="text-gray-600">
                                    1x
                                    <?php echo htmlspecialchars($listing['ticket_type_name'] ?? 'Standard Ticket'); ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo formatDate($listing['start_date']); ?> •
                                    <?php echo htmlspecialchars($listing['venue']); ?>
                                </div>
                            </div>
                            <div class="font-semibold text-gray-900">
                                <?php echo formatCurrency($listing['resale_price']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="border-t border-gray-200 pt-4 space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Subtotal (1 ticket)</span>
                            <span class="font-medium"><?php echo formatCurrency($listing['resale_price']); ?></span>
                        </div>
                        <div class="border-t border-gray-200 pt-3">
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-bold text-gray-900">Total</span>
                                <span class="text-2xl font-bold text-indigo-600" id="final-total">
                                    <?php echo formatCurrency($listing['resale_price']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-3">Payment Summary</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Events:</span>
                                <span class="font-medium">1</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Total Tickets:</span>
                                <span class="font-medium">1</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Payment Method:</span>
                                <span class="font-medium" id="selected-payment-method">Credit Card</span>
                            </div>
                        </div>
                    </div>
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
<?php include 'checkout-js.php'; ?>
<?php include 'includes/footer.php'; ?>