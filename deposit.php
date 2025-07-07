<?php
// Set session cookie params for local dev (localhost:3000)
session_set_cookie_params([
    'samesite' => 'Lax',
    'secure' => false,
    'httponly' => true,
    'path' => '/',
    'domain' => '', // Remove domain restriction for localhost with port
]);
// Debugging: log session id and session content
session_start();
error_log('DEPOSIT.PHP SESSION ID: ' . session_id());
error_log('DEPOSIT.PHP SESSION: ' . print_r($_SESSION, true));

$pageTitle = "Deposit Funds";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize Stripe
\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Please login to deposit funds.";
    redirect('login.php');
}

$userId = getCurrentUserId();
$user = $db->fetchOne("SELECT * FROM users WHERE id = $userId");

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);
    if ($amount <= 0) {
        $errors[] = "Please enter a valid deposit amount.";
    }
    if (empty($errors)) {
        // Prepare Stripe Checkout Session
        $line_items = [
            [
                'price_data' => [
                    'currency' => 'rwf',
                    'product_data' => [
                        'name' => 'Account Deposit',
                    ],
                    'unit_amount' => intval($amount * 1), // Stripe expects cents
                ],
                'quantity' => 1,
            ]
        ];
        // Store deposit info in session for post-payment processing
        $_SESSION['deposit_amount'] = $amount;
        $_SESSION['deposit_user_id'] = $userId;
        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => $line_items,
            'mode' => 'payment',
            'customer_email' => $user['email'],
            'success_url' => SITE_URL . '/stripe-deposit-success.php?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => SITE_URL . '/deposit.php?canceled=1',
        ]);
        header('Location: ' . $session->url);
        exit;
    }
}

include 'includes/header.php';
?>
<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6 max-w-lg">
    <h1 class="text-2xl font-bold mb-4 text-gray-900">Deposit Funds</h1>
    <p class="mb-6 text-gray-600">Top up your account balance to use for ticket purchases and other payments.</p>
    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?php foreach ($errors as $error): ?>
        <div><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
        Deposit successful!
    </div>
    <?php endif; ?>
    <form method="POST" class="space-y-4 bg-white p-6 rounded-lg shadow-md">
        <div>
            <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount (Rwf)</label>
            <input type="number" min="100" step="100" name="amount" id="amount" required
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm"
                placeholder="Enter amount to deposit">
        </div>
        <button type="submit"
            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200">
            <i class="fab fa-cc-stripe mr-2"></i>Deposit with Stripe
        </button>
    </form>
    <div class="mt-6 text-gray-500 text-sm">
        <strong>Current Balance:</strong> <?php echo formatCurrency($user['balance']); ?>
    </div>
</div>
<?php include 'includes/footer.php'; ?>