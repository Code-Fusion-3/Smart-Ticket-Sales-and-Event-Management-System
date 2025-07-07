<?php
// Set session cookie params for local dev (localhost:3000)
session_set_cookie_params([
    'samesite' => 'Lax',
    'secure' => false,
    'httponly' => true,
    'path' => '/',
    'domain' => '', // Remove domain restriction
]);

// Debugging: log session id and session content
session_start();
error_log('STRIPE-DEPOSIT-SUCCESS.PHP SESSION ID: ' . session_id());
error_log('STRIPE-DEPOSIT-SUCCESS.PHP SESSION: ' . print_r($_SESSION, true));
error_log('STRIPE-DEPOSIT-SUCCESS.PHP COOKIES: ' . print_r($_COOKIE, true));

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
    error_log('STRIPE-DEPOSIT-SUCCESS: User not logged in');
    $_SESSION['error_message'] = "Please login to view your deposit status.";
    redirect('login.php');
}

$userId = getCurrentUserId();
$user = $db->fetchOne("SELECT * FROM users WHERE id = $userId");

error_log("STRIPE-DEPOSIT-SUCCESS: Current User ID: $userId");
error_log("STRIPE-DEPOSIT-SUCCESS: Session deposit_user_id: " . ($_SESSION['deposit_user_id'] ?? 'NOT SET'));
error_log("STRIPE-DEPOSIT-SUCCESS: Session deposit_amount: " . ($_SESSION['deposit_amount'] ?? 'NOT SET'));

if (!isset($_GET['session_id']) || !isset($_SESSION['deposit_amount']) || !isset($_SESSION['deposit_user_id'])) {
    error_log('STRIPE-DEPOSIT-SUCCESS: Missing required session data');
    error_log('GET session_id: ' . ($_GET['session_id'] ?? 'NOT SET'));
    error_log('SESSION deposit_amount: ' . ($_SESSION['deposit_amount'] ?? 'NOT SET'));
    error_log('SESSION deposit_user_id: ' . ($_SESSION['deposit_user_id'] ?? 'NOT SET'));
    $_SESSION['error_message'] = "Invalid deposit session.";
    redirect('deposit.php');
}

$sessionId = $_GET['session_id'];
$amount = floatval($_SESSION['deposit_amount']);
$depositUserId = intval($_SESSION['deposit_user_id']);

// Only allow the user who initiated the deposit to process it
if (intval($userId) != $depositUserId) {
    // Enhanced debugging output for session and user info
    error_log("STRIPE-DEPOSIT-SUCCESS: Unauthorized deposit attempt");
    error_log("Current userId: $userId (type: " . gettype($userId) . ")");
    error_log("Deposit userId: $depositUserId (type: " . gettype($depositUserId) . ")");
    error_log('Full SESSION: ' . print_r($_SESSION, true));
    error_log('Full COOKIES: ' . print_r($_COOKIE, true));
    error_log('User Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'NOT SET'));
    error_log('IP Address: ' . ($_SERVER['REMOTE_ADDR'] ?? 'NOT SET'));
    
    $_SESSION['error_message'] = "Unauthorized deposit attempt. Current user: $userId, Expected user: $depositUserId. Please contact support if this persists.";
    redirect('deposit.php');
}

try {
    $session = \Stripe\Checkout\Session::retrieve($sessionId);
    if ($session->payment_status !== 'paid') {
        $_SESSION['error_message'] = "Deposit payment was not completed successfully.";
        redirect('deposit.php');
    }

    // Check if already processed
    $existing = $db->fetchOne("SELECT * FROM transactions WHERE reference_id = '" . $db->escape($sessionId) . "' AND type = 'deposit'");
    if ($existing) {
        $_SESSION['success_message'] = "Deposit already processed.";
        unset($_SESSION['deposit_amount'], $_SESSION['deposit_user_id']);
        redirect('deposit.php');
    }

    // Begin transaction
    $db->query("START TRANSACTION");
    // Update user balance
    $db->query("UPDATE users SET balance = balance + $amount WHERE id = $userId");
    // Record deposit transaction
    $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, payment_method, description) VALUES ($userId, $amount, 'deposit', 'completed', '$sessionId', 'credit_card', 'Account deposit via Stripe')");
    $db->query("COMMIT");

    // Send notification
    $subject = "Deposit Successful";
    $emailBody = "<h2>Deposit Successful</h2><p>Your deposit of <strong>" . formatCurrency($amount) . "</strong> has been added to your account balance.</p><p>Thank you for using " . SITE_NAME . ".</p>";
    $smsBody = "Your deposit of " . formatCurrency($amount) . " was successful. Your new balance: " . formatCurrency($user['balance'] + $amount) . ".";
    sendNotification($user['email'], $user['phone_number'], $subject, $emailBody, $smsBody);

    unset($_SESSION['deposit_amount'], $_SESSION['deposit_user_id']);
    $_SESSION['success_message'] = "Deposit successful! Your balance has been updated.";
    redirect('deposit.php');
} catch (Exception $e) {
    $db->query("ROLLBACK");
    error_log("Stripe Deposit Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Deposit processing failed: " . $e->getMessage();
    redirect('deposit.php');
}