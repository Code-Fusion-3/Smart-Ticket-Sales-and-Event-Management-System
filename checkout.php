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

// Get cart items
$itemsSql = "SELECT ci.id, ci.event_id, ci.ticket_type_id, ci.quantity, 
                   ci.recipient_name, ci.recipient_email, ci.recipient_phone,
                   e.title, e.start_date, e.start_time, e.venue, e.city,
                   COALESCE(tt.name, 'Standard Ticket') as ticket_name, 
                   COALESCE(tt.price, e.ticket_price) as ticket_price
            FROM cart_items ci
            JOIN events e ON ci.event_id = e.id
            LEFT JOIN ticket_types tt ON ci.ticket_type_id = tt.id
            WHERE ci.cart_id = $cartId
            ORDER BY ci.created_at DESC";
$cartItems = $db->fetchAll($itemsSql);

if (empty($cartItems)) {
    $_SESSION['error_message'] = "Your cart is empty. Please add tickets before checkout.";
    redirect('cart.php');
}

// Calculate totals
$subtotal = 0;
$fees = 0;
$total = 0;

foreach ($cartItems as $item) {
    $subtotal += $item['ticket_price'] * $item['quantity'];
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
    
    // Validate payment method
    if (empty($paymentMethod)) {
        $errors[] = "Please select a payment method.";
    }
    
    // Validate recipient information for each item
    foreach ($cartItems as $index => $item) {
        $itemId = $item['id'];
        
        $recipientName = $_POST["recipient_name_$itemId"] ?? '';
        $recipientEmail = $_POST["recipient_email_$itemId"] ?? '';
        $recipientPhone = $_POST["recipient_phone_$itemId"] ?? '';
        
        // Update cart item with recipient info
        $updateSql = "UPDATE cart_items SET 
                        recipient_name = '" . $db->escape($recipientName) . "',
                        recipient_email = '" . $db->escape($recipientEmail) . "',
                        recipient_phone = '" . $db->escape($recipientPhone) . "'
                      WHERE id = $itemId AND cart_id = $cartId";
        $db->query($updateSql);
    }
    
    // Process payment
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
            // In a real application, you would integrate with a payment gateway here
            // For now, we'll simulate a successful payment
            $paymentSuccess = true;
        }
    
        if ($paymentSuccess) {
            // Begin transaction
            $db->query("START TRANSACTION");
            

            try {
                // Deduct from user balance if used
                if ($balanceUsed > 0) {
                    $newBalance = $user['balance'] - $balanceUsed;
                    $db->query("UPDATE users SET balance = $newBalance WHERE id = $userId");
                    
                    // Record balance transaction
                    $db->query("INSERT INTO transactions (user_id, amount, type, status ,reference_id, payment_method, description)
                                VALUES ($userId, $balanceUsed, 'purchase', 'completed' ,'$paymentReference', 'balance', 'Ticket purchase using account balance')");
                }
                
                // Record external payment transaction if any
                if ($amountToCharge > 0) {
                    $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, payment_method, description)
                                VALUES ($userId, $amountToCharge, 'purchase','completed' ,'$paymentReference', '$paymentMethod', 'Ticket purchase')");
                }
                
                // Process each cart item
                foreach ($cartItems as $item) {
                    $eventId = $item['event_id'];
                    $ticketTypeId = isset($item['ticket_type_id']) ? $item['ticket_type_id'] : null;
                    $quantity = $item['quantity'];
                    $price = $item['ticket_price'];
                    $cartItemId = $item['id'];
                    
                    // Create tickets
                    for ($i = 0; $i < $quantity; $i++) {
                        // Get recipient details from form
                        $recipientName = $_POST["recipient_name_{$cartItemId}_{$i}"] ?? '';
                        $recipientEmail = $_POST["recipient_email_{$cartItemId}_{$i}"] ?? '';
                        $recipientPhone = $_POST["recipient_phone_{$cartItemId}_{$i}"] ?? '';
                        
                        // If recipient details are empty, use the current user's information
                        if (empty($recipientName)) {
                            $recipientName = $user['username'];
                        }
                        
                        if (empty($recipientEmail)) {
                            $recipientEmail = $user['email'];
                        }
                        
                        if (empty($recipientPhone)) {
                            $recipientPhone = $user['phone_number'];
                        }
                        
                        // Generate QR code (in a real app, you'd use a proper QR library)
                        $qrCode = 'TICKET-' . generateRandomString(16);
                        
                    $sql = "INSERT INTO tickets (event_id, ticket_type_id, user_id, recipient_name, recipient_email, recipient_phone, qr_code, purchase_price, status)
                    VALUES ($eventId, " . ($ticketTypeId ? $ticketTypeId : "NULL") . ", $userId, '" . $db->escape($recipientName) . "', '" . $db->escape($recipientEmail) . "', 
                    '" . $db->escape($recipientPhone) . "', '$qrCode', $price, 'sold')";
                    $ticketId = $db->insert($sql);
                        
                        // Get event details for the notification
                        $eventSql = "SELECT title, venue, city, start_date, start_time FROM events WHERE id = $eventId";
                        $eventDetails = $db->fetchOne($eventSql);
                        
                        // Generate QR code data for the ticket
                        $qrCodeData = json_encode([
                            'ticket_id' => $ticketId,
                            'event_id' => $eventId,
                            'user_id' => $userId,
                            'verification_token' => $qrCode,
                            'timestamp' => time()
                        ]);
                        
                        // Generate QR code URL
                        $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrCodeData);
                        
                        // Create ticket data array for notifications
                        $ticketData = [
                            'id' => $ticketId,
                            'recipient_name' => $recipientName,
                            'recipient_email' => $recipientEmail,
                            'recipient_phone' => $recipientPhone,
                            'purchase_price' => $price,
                            'qr_code' => $qrCode
                        ];
                        
                        // Send email notification to the ticket recipient
                        $emailSubject = "Your Ticket for " . $eventDetails['title'];
                        $emailBody = getTicketEmailTemplate($ticketData, $eventDetails, $qrCodeUrl);
                        $plainTextEmail = "Your ticket for " . $eventDetails['title'] . " has been confirmed. Please check your account to view and download your ticket.";
                        
                        sendEmail($recipientEmail, $emailSubject, $emailBody, $plainTextEmail);
                        
                        // Send SMS notification if we have a phone number
                        if (!empty($recipientPhone)) {
                            $smsMessage = getTicketSMSTemplate($ticketData, $eventDetails);
                            sendSMS($recipientPhone, $smsMessage);
                        }
                        
                        // Create notification in the database
                        $notificationTitle = "Ticket Purchased";
                        $notificationMessage = "You have successfully purchased a ticket for " . $eventDetails['title'] . ".";
                        
                        $db->query("INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                                    VALUES ($userId, '" . $db->escape($notificationTitle) . "', '" . $db->escape($notificationMessage) . "', 'ticket', 0, NOW())");
                    }
                    
                    // Update available tickets
                    if ($ticketTypeId) {
                        $db->query("UPDATE ticket_types SET available_tickets = available_tickets - $quantity WHERE id = $ticketTypeId");
                    }
                    
                    $db->query("UPDATE events SET available_tickets = available_tickets - $quantity WHERE id = $eventId");
                }
                
                // Calculate system fee
                if ($fees > 0) {
                    $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, description)
                                VALUES ($userId, $fees, 'system_fee', 'completed','$paymentReference', 'Service fee for ticket purchase')");
                }
                
                // Clear the cart
                $db->query("DELETE FROM cart_items WHERE cart_id = $cartId");
                
                // Commit transaction
                $db->query("COMMIT");
                
                // Set success flag
                $success = true;
                
                // Store order reference in session for confirmation page
                $_SESSION['order_reference'] = $paymentReference;
                
                // Redirect to confirmation page
                redirect('order-confirmation.php');
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $db->query("ROLLBACK");
                echo "Error: " . $e->getMessage();
                $errors[] = "An error occurred during checkout: " . $e->getMessage();
            }
        } else {
            $errors[] = "Payment processing failed. Please try again.";
        }
        
    }
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Checkout</h1>
    
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
        <!-- Checkout Form -->
        <div class="lg:w-2/3">
            <form method="POST" action="">
                <!-- Ticket Information -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-indigo-600 text-white px-6 py-4">
                        <h2 class="text-xl font-bold">Ticket Information</h2>
                    </div>
                    
                    <div class="p-6">
                        <p class="text-gray-600 mb-4">Please provide recipient information for each ticket. Leave blank to use your account information.</p>
                        
                        <?php foreach ($cartItems as $index => $item): ?>
                            <div class="mb-6 pb-6 <?php echo $index < count($cartItems) - 1 ? 'border-b border-gray-200' : ''; ?>">
                                <div class="flex items-center mb-4">
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-lg"><?php echo htmlspecialchars($item['title']); ?></h3>
                                        <div class="text-sm text-indigo-600"><?php echo htmlspecialchars($item['ticket_name']); ?></div>
                                        <div class="text-sm text-gray-600">
                                            <?php echo formatDate($item['start_date']); ?> at <?php echo formatTime($item['start_time']); ?>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-gray-600"><?php echo $item['quantity']; ?> × <?php echo formatCurrency($item['ticket_price']); ?></div>
                                        <div class="font-semibold"><?php echo formatCurrency($item['ticket_price'] * $item['quantity']); ?></div>
                                    </div>
                                </div>
                                
                                <?php for ($i = 0; $i < $item['quantity']; $i++): ?>
    <div class="bg-gray-50 p-4 rounded-lg mb-4">
        <h4 class="font-semibold mb-3">Ticket #<?php echo $i + 1; ?> Recipient</h4>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                <input type="text" name="recipient_name_<?php echo $item['id']; ?>_<?php echo $i; ?>" 
                       value="<?php echo htmlspecialchars($item['recipient_name'] ?? $user['username']); ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                       placeholder="Recipient Name">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                <input type="email" name="recipient_email_<?php echo $item['id']; ?>_<?php echo $i; ?>" 
                       value="<?php echo htmlspecialchars($item['recipient_email'] ?? $user['email']); ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                       placeholder="Recipient Email">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-bold mb-2">Phone</label>
                <input type="text" name="recipient_phone_<?php echo $item['id']; ?>_<?php echo $i; ?>" 
                       value="<?php echo htmlspecialchars($item['recipient_phone'] ?? $user['phone_number']); ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                       placeholder="Recipient Phone">
            </div>
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
        <h2 class="text-xl font-bold">Payment Information</h2>
    </div>
    
    <div class="p-6">
        <?php if ($user['balance'] > 0): ?>
            <div class="mb-6">
                <label class="flex items-center">
                    <input type="checkbox" name="use_balance" id="use_balance" class="form-checkbox h-5 w-5 text-indigo-600" 
                           <?php echo $user['balance'] >= $total ? 'checked' : ''; ?>>
                    <span class="ml-2">
                        Use my account balance (<?php echo formatCurrency($user['balance']); ?> available)
                    </span>
                </label>
                <?php if ($user['balance'] < $total): ?>
                    <p class="text-sm text-gray-600 mt-1">
                        Your balance will cover <?php echo formatCurrency($user['balance']); ?> of your total. 
                        The remaining <?php echo formatCurrency($total - $user['balance']); ?> will be charged to your selected payment method.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div id="payment-method-selection" class="mb-4 <?php echo ($user['balance'] >= $total && isset($_POST['use_balance'])) ? 'hidden' : ''; ?>">
            <label class="block text-gray-700 font-bold mb-2">Payment Method</label>
            
            <div class="space-y-2">
                <label class="flex items-center p-3 border rounded-md hover:bg-gray-50 cursor-pointer">
                    <input type="radio" name="payment_method" value="credit_card" class="h-4 w-4 text-indigo-600 payment-method-radio" checked>
                    <span class="ml-2 flex items-center">
                        <i class="far fa-credit-card text-gray-500 mr-2"></i> Credit Card
                    </span>
                </label>
                
                <label class="flex items-center p-3 border rounded-md hover:bg-gray-50 cursor-pointer">
                    <input type="radio" name="payment_method" value="mobile_money" class="h-4 w-4 text-indigo-600 payment-method-radio">
                    <span class="ml-2 flex items-center">
                        <i class="fas fa-mobile-alt text-gray-500 mr-2"></i> Mobile Money
                    </span>
                </label>
                
               
            </div>
        </div>
        
        <!-- Credit Card Details -->
        <div id="credit-card-details" class="payment-details mb-6 p-4 bg-gray-50 rounded-lg">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Card Number</label>
                    <input type="text" id="card_number" name="card_number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500" 
                           placeholder="4242 4242 4242 4242">
                    <p class="text-xs text-gray-500 mt-1">Use 4242 4242 4242 4242 for testing</p>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Cardholder Name</label>
                    <input type="text" id="card_name" name="card_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500" 
                           placeholder="John Doe">
                </div>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="col-span-1">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Expiry Month</label>
                    <select id="card_month" name="card_month" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo sprintf('%02d', $i); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-span-1">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Expiry Year</label>
                    <select id="card_year" name="card_year" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                        <?php 
                        $currentYear = date('Y');
                        for ($i = $currentYear; $i <= $currentYear + 10; $i++): 
                        ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-gray-700 text-sm font-bold mb-2">CVV</label>
                    <input type="text" id="card_cvv" name="card_cvv" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500" 
                           placeholder="123">
                    <p class="text-xs text-gray-500 mt-1">Use any 3 digits for testing</p>
                </div>
            </div>
        </div>
        
        <!-- Mobile Money Details -->
        <div id="mobile-money-details" class="payment-details mb-6 p-4 bg-gray-50 rounded-lg hidden">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Mobile Number</label>
                <input type="text" id="mobile_number" name="mobile_number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500" 
                       placeholder="07XX XXX XXX">
                <p class="text-xs text-gray-500 mt-1">Use 0700000000 for testing</p>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Network Provider</label>
                <select id="mobile_provider" name="mobile_provider" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                    <option value="mtn">MTN Mobile Money</option>
                    <option value="airtel">Airtel Money</option>
                    <option value="vodafone">Vodafone Cash</option>
                </select>
            </div>
            <p class="hidden text-sm text-gray-600">
                <i class="fas fa-info-circle mr-1"></i> In a real environment, you would receive a prompt on your phone to complete the payment. For testing, we'll simulate this process.
            </p>
        </div>
        
        <!-- Airtel Money Details -->
        <div id="airtel-money-details" class="payment-details mb-6 p-4 bg-gray-50 rounded-lg hidden">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Airtel Money Number</label>
                <input type="text" id="airtel_number" name="airtel_number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500" 
                       placeholder="07XX XXX XXX">
                <p class="text-xs text-gray-500 mt-1">Use 0700000000 for testing</p>
            </div>
            <div class="mb-4">
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                For testing purposes, the system will simulate an Airtel Money payment. No actual charges will be made.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-6">
            <button type="submit" id="payment-button" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded transition duration-300">
                Complete Purchase
            </button>
        </div>
        
        <div class="mt-4 text-center text-sm text-gray-600">
            <p>By completing this purchase, you agree to our <a href="#" class="text-indigo-600 hover:text-indigo-800">Terms of Service</a></p>
            <p class="mt-2 text-xs bg-gray-100 p-2 rounded">
                <i class="fas fa-shield-alt mr-1"></i> This is a secure, encrypted payment. Your payment details are protected.
            </p>
        </div>
    </div>
</div>

            </form>
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
                            <span><?php echo formatCurrency($subtotal); ?></span>
                        </div>
                        
                        <?php if ($fees > 0): ?>
                            <div class="flex justify-between">
                                <span>Service Fee (<?php echo $feePercentage; ?>%)</span>
                                <span><?php echo formatCurrency($fees); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="border-t pt-4 flex justify-between font-bold text-lg">
                            <span>Total</span>
                            <span><?php echo formatCurrency($total); ?></span>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg mt-4">
                            <h3 class="font-semibold mb-2">Order Details</h3>
                            <ul class="space-y-2 text-sm">
                                <?php foreach ($cartItems as $item): ?>
                                    <li class="flex justify-between">
                                        <span><?php echo $item['quantity']; ?> × <?php echo htmlspecialchars($item['ticket_name']); ?></span>
                                        <span><?php echo formatCurrency($item['ticket_price'] * $item['quantity']); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
        const creditCardDetails = document.getElementById('credit-card-details');
        const mobileMoneyDetails = document.getElementById('mobile-money-details');
        
        // Show/hide payment details based on selected method
        function togglePaymentDetails() {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;
            
            creditCardDetails.classList.add('hidden');
            mobileMoneyDetails.classList.add('hidden');
            
            if (selectedMethod === 'credit_card') {
                creditCardDetails.classList.remove('hidden');
            } else if (selectedMethod === 'mobile_money' || selectedMethod === 'airtel_money') {
                mobileMoneyDetails.classList.remove('hidden');
            }
        }
        
        // Add event listeners
        paymentMethods.forEach(method => {
            method.addEventListener('change', togglePaymentDetails);
        });
        
        // Initialize display
        togglePaymentDetails();
    });

    document.addEventListener('DOMContentLoaded', function() {
        const paymentMethods = document.querySelectorAll('.payment-method-radio');
        const paymentDetails = document.querySelectorAll('.payment-details');
        const useBalanceCheckbox = document.getElementById('use_balance');
        const paymentMethodSelection = document.getElementById('payment-method-selection');
        const paymentButton = document.getElementById('payment-button');
        
        // Show/hide payment details based on selected method
        function togglePaymentDetails() {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;
            
            // Hide all payment details first
            paymentDetails.forEach(detail => {
                detail.classList.add('hidden');
            });
            
            // Show the selected payment method details
            if (selectedMethod === 'credit_card') {
                document.getElementById('credit-card-details').classList.remove('hidden');
            } else if (selectedMethod === 'mobile_money') {
                document.getElementById('mobile-money-details').classList.remove('hidden');
            } else if (selectedMethod === 'airtel_money') {
                document.getElementById('airtel-money-details').classList.remove('hidden');
            }
        }
        
        // Toggle payment method section based on balance checkbox
        function togglePaymentMethodSection() {
            if (useBalanceCheckbox && useBalanceCheckbox.checked && <?php echo $user['balance'] >= $total ? 'true' : 'false'; ?>) {
                paymentMethodSelection.classList.add('hidden');
                paymentDetails.forEach(detail => {
                    detail.classList.add('hidden');
                });
                paymentButton.textContent = 'Complete Purchase Using Balance';
            } else {
                paymentMethodSelection.classList.remove('hidden');
                togglePaymentDetails();
                paymentButton.textContent = 'Complete Purchase';
            }
        }
        
        // Add event listeners
        paymentMethods.forEach(method => {
            method.addEventListener('change', togglePaymentDetails);
        });
        
        if (useBalanceCheckbox) {
            useBalanceCheckbox.addEventListener('change', togglePaymentMethodSection);
        }
        
        // Simulate payment processing
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked')?.value;
            const useBalance = useBalanceCheckbox?.checked || false;
            
            // If using balance and balance covers total, proceed without validation
            if (useBalance && <?php echo $user['balance'] >= $total ? 'true' : 'false'; ?>) {
                return true;
            }
            
            // Validate payment details based on selected method
            if (selectedMethod === 'credit_card') {
                const cardNumber = document.getElementById('card_number').value.trim();
                const cardName = document.getElementById('card_name').value.trim();
                const cardCvv = document.getElementById('card_cvv').value.trim();
                
                if (!cardNumber || !cardName || !cardCvv) {
                    e.preventDefault();
                    alert('Please fill in all credit card details.');
                    return false;
                }
                
                               // Basic validation for testing card number
                               if (cardNumber !== '4242424242424242' && !cardNumber.match(/^4242\s?4242\s?4242\s?4242$/)) {
                    // For academic purposes, show a warning but still allow submission
                    if (!confirm('For testing, please use card number 4242 4242 4242 4242. Do you want to proceed anyway?')) {
                        e.preventDefault();
                        return false;
                    }
                }
                
                // Simulate payment processing
                showProcessingOverlay('Processing credit card payment...');
                
            } else if (selectedMethod === 'mobile_money') {
                const mobileNumber = document.getElementById('mobile_number').value.trim();
                const provider = document.getElementById('mobile_provider').value;
                
                if (!mobileNumber) {
                    e.preventDefault();
                    alert('Please enter your mobile number.');
                    return false;
                }
                
                // Basic validation for testing mobile number
                if (mobileNumber !== '0700000000' && !mobileNumber.match(/^07\d{8}$/)) {
                    if (!confirm('For testing, please use mobile number 0700000000. Do you want to proceed anyway?')) {
                        e.preventDefault();
                        return false;
                    }
                }
                
                // Simulate payment processing
                showProcessingOverlay('Sending payment request to your mobile phone...');
                
            } else if (selectedMethod === 'airtel_money') {
                const airtelNumber = document.getElementById('airtel_number').value.trim();
                
                if (!airtelNumber) {
                    e.preventDefault();
                    alert('Please enter your Airtel Money number.');
                    return false;
                }
                
                // Basic validation for testing Airtel number
                if (airtelNumber !== '0700000000' && !airtelNumber.match(/^07\d{8}$/)) {
                    if (!confirm('For testing, please use Airtel number 0700000000. Do you want to proceed anyway?')) {
                        e.preventDefault();
                        return false;
                    }
                }
                
                // Simulate payment processing
                showProcessingOverlay('Sending payment request to Airtel Money...');
            }
            
            // For academic purposes, simulate a brief delay to show processing
            setTimeout(function() {
                return true;
            }, 2000);
        });
        
        // Function to show processing overlay
        function showProcessingOverlay(message) {
            // Create overlay if it doesn't exist
            let overlay = document.getElementById('payment-processing-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'payment-processing-overlay';
                overlay.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
                overlay.innerHTML = `
                    <div class="bg-white p-6 rounded-lg shadow-lg text-center max-w-md mx-4">
                        <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-indigo-500 mx-auto mb-4"></div>
                        <h3 class="text-lg font-bold mb-2">Processing Payment</h3>
                        <p id="processing-message" class="text-gray-600">${message}</p>
                        <p class="text-sm text-gray-500 mt-4">This is a simulation for academic purposes. No actual payment is being processed.</p>
                    </div>
                `;
                document.body.appendChild(overlay);
            } else {
                document.getElementById('processing-message').textContent = message;
                overlay.classList.remove('hidden');
            }
        }
        
        // Initialize
        togglePaymentDetails();
        if (useBalanceCheckbox) {
            togglePaymentMethodSection();
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
