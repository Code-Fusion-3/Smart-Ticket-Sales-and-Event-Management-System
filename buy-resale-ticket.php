<?php
$pageTitle = "Buy Resale Ticket";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';

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
    $recipientName = trim($_POST['recipient_name'] ?? '');
    $recipientEmail = trim($_POST['recipient_email'] ?? '');
    $recipientPhone = trim($_POST['recipient_phone'] ?? '');
    
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
                                   updated_at = NOW()
                                   WHERE id = " . $listing['ticket_id'];
                $db->query($updateTicketSql);
                
                // Update resale listing status
                $db->query("UPDATE ticket_resales SET status = 'sold', sold_at = NOW() WHERE id = $resaleId");
                
                // Send notifications
                
                // Notify buyer
                $buyerNotificationTitle = "Ticket Purchase Successful";
                $buyerNotificationMessage = "You have successfully purchased a resale ticket for '" . $listing['title'] . "'.";
                createNotification($userId, $buyerNotificationTitle, $buyerNotificationMessage, 'ticket');
                
                // Notify seller
                $sellerNotificationTitle = "Ticket Sold";
                $sellerNotificationMessage = "Your ticket for '" . $listing['title'] . "' has been sold for " . formatCurrency($listing['resale_price']) . ". Earnings: " . formatCurrency($sellerEarnings);
                createNotification($listing['seller_id'], $sellerNotificationTitle, $sellerNotificationMessage, 'ticket');
                
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
                
                $buyerEmailBody = getTicketEmailTemplate($ticketData, $eventDetails, $qrCodeUrl);
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
                redirect('customer/tickets.php');
                
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
    <!-- Page Header -->
    <div class="mb-8">
        <nav class="text-sm breadcrumbs mb-4">
            <a href="marketplace.php" class="text-indigo-600 hover:text-indigo-800">Marketplace</a>
            <span class="mx-2 text-gray-500">></span>
            <span class="text-gray-700">Purchase Ticket</span>
        </nav>
        <h1 class="text-3xl font-bold text-gray-900">Purchase Resale Ticket</h1>
        <p class="text-gray-600 mt-2">Complete your purchase to secure this ticket</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
            <h4 class="font-bold">Please fix the following errors:</h4>
            <ul class="list-disc pl-5 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Purchase Form -->
        <div class="lg:col-span-2">
            <form method="POST" action="">
                <!-- Ticket Information -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-indigo-600 text-white px-6 py-4">
                        <h2 class="text-xl font-bold">Ticket Details</h2>
                    </div>
                    
                    <div class="p-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0 w-20 h-20 bg-gray-200 rounded-lg overflow-hidden">
                                <?php if (!empty($listing['image'])): ?>
                                    <img src="<?php echo substr($listing['image'], strpos($listing['image'], 'uploads')); ?>" 
                                         alt="<?php echo htmlspecialchars($listing['title']); ?>" 
                                         class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center">
                                        <i class="fas fa-calendar-alt text-2xl text-gray-400"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($listing['title']); ?></h3>
                                <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($listing['ticket_type_name'] ?? 'Standard Ticket'); ?></p>
                                
                                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <div class="text-sm text-gray-500">Date & Time</div>
                                        <div class="font-medium"><?php echo formatDate($listing['start_date']); ?> at <?php echo formatTime($listing['start_time']); ?></div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-500">Venue</div>
                                        <div class="font-medium"><?php echo htmlspecialchars($listing['venue']); ?>, <?php echo htmlspecialchars($listing['city']); ?></div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-500">Seller</div>
                                        <div class="font-medium"><?php echo htmlspecialchars($listing['seller_name']); ?></div>
                                    </div>
                                    <div>
                                        <div class="text-sm text-gray-500">Listed</div>
                                        <div class="font-medium"><?php echo formatDateTime($listing['listed_at']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recipient Information -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="bg-green-600 text-white px-6 py-4">
                        <h2 class="text-xl font-bold">Ticket Recipient Information</h2>
                    </div>
                    
                    <div class="p-6">
                        <p class="text-gray-600 mb-4">Please provide the information for the person who will use this ticket.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="recipient_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="recipient_name" name="recipient_name" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                       required>
                            </div>
                            <div>
                                <label for="recipient_email" class="block text-sm font-medium text-gray-700 mb-2">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input type="email" id="recipient_email" name="recipient_email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                       required>
                            </div>
                            <div>
                                <label for="recipient_phone" class="block text-sm font-medium text-gray-700 mb-2">
                                    Phone Number <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="recipient_phone" name="recipient_phone" 
                                       value="<?php echo htmlspecialchars($user['phone_number']); ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                       required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Information -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="bg-blue-600 text-white px-6 py-4">
                        <h2 class="text-xl font-bold">Payment Information</h2>
                    </div>
                    
                    <div class="p-6">
                        <?php if ($user['balance'] > 0): ?>
                            <div class="mb-6">
                                <label class="flex items-center">
                                    <input type="checkbox" name="use_balance" id="use_balance" class="form-checkbox h-5 w-5 text-indigo-600" 
                                           <?php echo $user['balance'] >= $listing['resale_price'] ? 'checked' : ''; ?>>
                                    <span class="ml-2">
                                        Use my account balance (<?php echo formatCurrency($user['balance']); ?> available)
                                    </span>
                                </label>
                                <?php if ($user['balance'] < $listing['resale_price']): ?>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Your balance will cover <?php echo formatCurrency($user['balance']); ?> of the total. 
                                        The remaining <?php echo formatCurrency($listing['resale_price'] - $user['balance']); ?> will be charged to your selected payment method.
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div id="payment-method-selection" class="mb-4">
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
                                </select>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" name="purchase_ticket" id="purchase-button" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded transition duration-300">
                                <i class="fas fa-credit-card mr-2"></i> Complete Purchase
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
                
                <input type="hidden" name="purchase_ticket" value="1">
            </form>
        </div>
        
        <!-- Order Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md overflow-hidden sticky top-4">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Order Summary</h2>
                </div>
                
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="flex justify-between">
                            <span>Ticket Price</span>
                            <span><?php echo formatCurrency($listing['resale_price']); ?></span>
                        </div>
                        
                        <div class="border-t pt-4 flex justify-between font-bold text-lg">
                            <span>Total</span>
                            <span><?php echo formatCurrency($listing['resale_price']); ?></span>
                        </div>
                        
                        <!-- Savings Display -->
                        <?php 
                        $originalPrice = $listing['original_price'] ?? $listing['resale_price'];
                        $savings = $originalPrice - $listing['resale_price'];
                        if ($savings > 0):
                        ?>
                            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                <div class="flex items-center">
                                    <i class="fas fa-tag text-green-600 mr-2"></i>
                                    <div>
                                        <div class="text-sm font-medium text-green-800">You Save</div>
                                        <div class="text-lg font-bold text-green-600"><?php echo formatCurrency($savings); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Ticket Details -->
                        <div class="bg-gray-50 p-4 rounded-lg mt-4">
                            <h3 class="font-semibold mb-2">Ticket Details</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span>Event:</span>
                                    <span class="font-medium"><?php echo htmlspecialchars($listing['title']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Type:</span>
                                    <span class="font-medium"><?php echo htmlspecialchars($listing['ticket_type_name'] ?? 'Standard'); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Date:</span>
                                    <span class="font-medium"><?php echo formatDate($listing['start_date']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Time:</span>
                                    <span class="font-medium"><?php echo formatTime($listing['start_time']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span>Venue:</span>
                                    <span class="font-medium"><?php echo htmlspecialchars($listing['venue']); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Security Notice -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <div class="flex items-start">
                                <i class="fas fa-shield-alt text-blue-600 mr-2 mt-1"></i>
                                <div class="text-sm text-blue-800">
                                    <div class="font-medium">Secure Purchase</div>
                                    <div>This ticket is verified and protected by our marketplace guarantee.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethods = document.querySelectorAll('.payment-method-radio');
    const paymentDetails = document.querySelectorAll('.payment-details');
    const useBalanceCheckbox = document.getElementById('use_balance');
    const paymentMethodSelection = document.getElementById('payment-method-selection');
    const purchaseButton = document.getElementById('purchase-button');
    
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
        }
    }
    
    // Toggle payment method section based on balance checkbox
    function togglePaymentMethodSection() {
        if (useBalanceCheckbox && useBalanceCheckbox.checked && <?php echo $user['balance'] >= $listing['resale_price'] ? 'true' : 'false'; ?>) {
            paymentMethodSelection.classList.add('hidden');
            paymentDetails.forEach(detail => {
                detail.classList.add('hidden');
            });
            purchaseButton.innerHTML = '<i class="fas fa-wallet mr-2"></i> Complete Purchase Using Balance';
        } else {
            paymentMethodSelection.classList.remove('hidden');
            togglePaymentDetails();
            purchaseButton.innerHTML = '<i class="fas fa-credit-card mr-2"></i> Complete Purchase';
        }
    }
    
    // Add event listeners
    paymentMethods.forEach(method => {
        method.addEventListener('change', togglePaymentDetails);
    });
    
    if (useBalanceCheckbox) {
        useBalanceCheckbox.addEventListener('change', togglePaymentMethodSection);
    }
    
    // Initialize
    togglePaymentDetails();
    if (useBalanceCheckbox) {
        togglePaymentMethodSection();
    }
    
    // Form validation
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked')?.value;
        const useBalance = useBalanceCheckbox?.checked || false;
        
        // If using balance and balance covers total, proceed without validation
        if (useBalance && <?php echo $user['balance'] >= $listing['resale_price'] ? 'true' : 'false'; ?>) {
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
        } else if (selectedMethod === 'mobile_money') {
            const mobileNumber = document.getElementById('mobile_number').value.trim();
            
            if (!mobileNumber) {
                e.preventDefault();
                alert('Please enter your mobile number.');
                return false;
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
