<?php
file_put_contents(__DIR__ . '/php_debug.log', 'Test log entry at top of booking-complete-payment.php' . PHP_EOL, FILE_APPEND);
error_log('Test log entry at top of booking-complete-payment.php');
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';
require_once __DIR__ . '/vendor/autoload.php';
// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Please login to complete your booking payment.";
    redirect('login.php');
}

$userId = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['booking_id'], $_POST['payment_method'])) {
    $_SESSION['error_message'] = "Invalid payment request.";
    redirect('my-bookings.php');
}

$bookingId = (int) $_POST['booking_id'];
$paymentMethod = $_POST['payment_method'];
$remainingAmount = isset($_POST['remaining_amount']) ? floatval($_POST['remaining_amount']) : 0;
$useBalance = isset($_POST['use_balance']) && $_POST['use_balance'] == '1';
$balanceAmount = isset($_POST['balance_amount']) ? floatval($_POST['balance_amount']) : 0;

// Fetch booking
$bookingSql = "SELECT b.*, e.title, e.start_date, e.start_time, e.venue, e.city, e.image, tt.name as ticket_name, u.username as planner_name, u.email as planner_email
               FROM bookings b 
               JOIN events e ON b.event_id = e.id 
               LEFT JOIN ticket_types tt ON b.ticket_type_id = tt.id 
               LEFT JOIN users u ON e.planner_id = u.id
               WHERE b.id = $bookingId AND b.user_id = $userId AND b.status = 'pending'";
$booking = $db->fetchOne($bookingSql);

if (!$booking) {
    $_SESSION['error_message'] = "Booking not found or already completed.";
    redirect('my-bookings.php');
}

$amountToPay = $booking['total_amount'] - $booking['amount_paid'];
if ($amountToPay <= 0) {
    $_SESSION['error_message'] = "No payment required for this booking.";
    redirect('my-bookings.php');
}

// Deduct balance if requested (already present)
if ($useBalance && $balanceAmount > 0) {
    $user = $db->fetchOne("SELECT balance FROM users WHERE id = $userId");
    $userBalance = $user['balance'] ?? 0;
    $deduct = min($userBalance, $amountToPay);
    if ($deduct > 0) {
        // Deduct from user balance
        $db->query("UPDATE users SET balance = balance - $deduct WHERE id = $userId");
        // Log transaction
        $db->insert("INSERT INTO transactions (user_id, amount, type, description, reference_id, created_at) VALUES ($userId, $deduct, 'purchase', 'Booking payment using balance', $bookingId, '" . date('Y-m-d H:i:s') . "')");
        $amountToPay -= $deduct;
        // Update booking amount_paid
        $db->query("UPDATE bookings SET amount_paid = amount_paid + $deduct WHERE id = $bookingId");
    }
}

if ($amountToPay <= 0) {
    // Booking fully paid with balance, complete booking
    $db->query("UPDATE bookings SET status = 'confirmed', payment_status = 'full', amount_paid = total_amount, updated_at = NOW() WHERE id = $bookingId");
    // Generate tickets
    $quantity = $booking['quantity'];
    $eventId = $booking['event_id'];
    $ticketTypeId = $booking['ticket_type_id'];
    $price = $booking['total_amount'] / $quantity;
    $user = $db->fetchOne("SELECT username, email, phone_number FROM users WHERE id = $userId");
    $generatedTickets = [];
    for ($i = 0; $i < $quantity; $i++) {
        $qrCode = 'TICKET-' . generateRandomString(16);
        $ticketSql = "INSERT INTO tickets (event_id, ticket_type_id, user_id, recipient_name, recipient_email, recipient_phone, qr_code, purchase_price, status, created_at)
                     VALUES ($eventId, " . ($ticketTypeId ? $ticketTypeId : "NULL") . ", $userId, 
                             '" . $db->escape($user['username']) . "', 
                             '" . $db->escape($user['email']) . "', 
                             '" . $db->escape($user['phone_number']) . "', 
                             '$qrCode', $price, 'sold', NOW())";
        $ticketId = $db->insert($ticketSql);
        $ticket = [
            'id' => $ticketId,
            'event_id' => $eventId,
            'ticket_type_id' => $ticketTypeId,
            'user_id' => $userId,
            'recipient_name' => $user['username'],
            'recipient_email' => $user['email'],
            'recipient_phone' => $user['phone_number'],
            'purchase_price' => $price,
            'ticket_name' => $booking['ticket_name'],
            'event_title' => $booking['title'],
            'venue' => $booking['venue'],
            'city' => $booking['city'],
            'start_date' => $booking['start_date'],
            'start_time' => $booking['start_time'],
            'qr_code' => $qrCode
        ];
        $generatedTickets[] = $ticket;
    }
    // Send notifications (email, in-app, SMS)
    foreach ($generatedTickets as $ticket) {
        // In-app notification
        $notificationTitle = "Ticket Purchased: {$ticket['ticket_name']}";
        $notificationMessage = "You have successfully purchased a {$ticket['ticket_name']} for {$ticket['event_title']}. Check your email for details.";
        $db->query("INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
            VALUES ($userId, '" . $db->escape($notificationTitle) . "', '" . $db->escape($notificationMessage) . "', 'ticket', 0, NOW())");
        // Email
        $eventDetails = [
            'title' => $ticket['event_title'],
            'venue' => $ticket['venue'],
            'city' => $ticket['city'],
            'start_date' => $ticket['start_date'],
            'start_time' => $ticket['start_time']
        ];
        $qrCodeData = json_encode([
            'ticket_id' => $ticket['id'],
            'event_id' => $ticket['event_id'],
            'user_id' => $userId,
            'verification_token' => $ticket['qr_code'],
            'timestamp' => time()
        ]);
        $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrCodeData);
        $emailSubject = "Your {$ticket['ticket_name']} for {$ticket['event_title']}";
        if (function_exists('getEnhancedTicketEmailTemplate')) {
            $emailBody = getEnhancedTicketEmailTemplate($ticket, $eventDetails, $qrCodeUrl);
        } else {
            $emailBody = null;
        }
        if ($emailBody && function_exists('sendEmail')) {
            sendEmail($ticket['recipient_email'], $emailSubject, $emailBody);
        }
        // SMS
        if (!empty($ticket['recipient_phone']) && function_exists('sendSMS')) {
            $smsMessage = "Your {$ticket['ticket_name']} for {$ticket['event_title']} is confirmed! Ticket ID: {$ticket['id']}. Event: {$ticket['start_date']} at {$ticket['venue']}. Code: {$ticket['qr_code']}";
            sendSMS($ticket['recipient_phone'], $smsMessage);
        }
    }
    $_SESSION['success_message'] = "Booking completed using your balance.";
    redirect('my-tickets.php');
}

// Store booking info in session for payment success handler
$_SESSION['booking_payment'] = [
    'booking_id' => $bookingId,
    'amount' => $amountToPay,
    'user_id' => $userId
];

if ($paymentMethod === 'stripe') {
    // Debug log for Stripe amount
    error_log('Amount to pay sent to Stripe: ' . $amountToPay);
    // Stripe payment flow
    \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
    $user = $db->fetchOne("SELECT email FROM users WHERE id = $userId");
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [
            [
                'price_data' => [
                    'currency' => 'rwf',
                    'product_data' => [
                        'name' => $booking['title'] . ' - ' . $booking['ticket_name'],
                        'description' => 'Booking Completion Payment',
                    ],
                    'unit_amount' => intval($amountToPay), // Stripe expects amount in cents
                ],
                'quantity' => 1,
            ]
        ],
        'mode' => 'payment',
        'customer_email' => $user['email'],
        'success_url' => SITE_URL . '/booking-payment-success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => SITE_URL . '/my-bookings.php?canceled=1',
        'metadata' => [
            'booking_id' => $bookingId,
            'user_id' => $userId
        ]
    ]);
    header('Location: ' . $session->url);
    exit;
} elseif ($paymentMethod === 'mobile_money') {
    // Mobile Money payment flow (pseudo-code, adapt to your integration)
    // You should integrate your mobile money API here
    // For now, just simulate success for demonstration
    // In production, redirect to mobile money payment page and handle callback
    $_SESSION['mobile_money_booking_payment'] = [
        'booking_id' => $bookingId,
        'amount' => $amountToPay,
        'user_id' => $userId
    ];
    // Simulate immediate success (replace with real mobile money flow)
    header('Location: booking-payment-success.php?mobile_money=1');
    exit;
} else {
    $_SESSION['error_message'] = "Invalid payment method.";
    redirect('my-bookings.php');
}