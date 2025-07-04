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
require_once 'checkout-logics.php';
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize Stripe
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

include 'includes/header.php';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['payment_method']) &&
    $_POST['payment_method'] === 'credit_card' &&
    ($user['balance'] < $total)
) {
    // Prepare line items for Stripe
    $line_items = [];
    foreach ($cartItems as $item) {
        $line_items[] = [
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => $item['title'] . ' - ' . $item['ticket_name'],
                ],
                'unit_amount' => intval($item['ticket_price'] * 100), // Stripe expects cents
            ],
            'quantity' => $item['quantity'],
        ];
    }
    
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => $line_items,
        'mode' => 'payment',
        'customer_email' => $user['email'],
        'success_url' => SITE_URL . '/order-confirmation.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => SITE_URL . '/checkout.php?canceled=1',
    ]);
    header('Location: ' . $session->url);
    exit;
}
?>
<!-- Enhanced CSS for better styling -->
<link rel="stylesheet" href="assets/css/checkout.css">
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
<?php include 'checkout-js.php' ?>
<?php include 'includes/footer.php'; ?>