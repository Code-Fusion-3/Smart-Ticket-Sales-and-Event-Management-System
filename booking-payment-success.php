<?php
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
    $_SESSION['error_message'] = "Please login to view your payment status.";
    redirect('login.php');
}

$userId = getCurrentUserId();

// Determine payment source
$isStripe = isset($_GET['session_id']);
$isMobileMoney = isset($_GET['mobile_money']);

if (!$isStripe && !$isMobileMoney) {
    $_SESSION['error_message'] = "Invalid payment confirmation.";
    redirect('my-bookings.php');
}

// Get booking info from session
$bookingPayment = $_SESSION['booking_payment'] ?? $_SESSION['mobile_money_booking_payment'] ?? null;
if (!$bookingPayment) {
    $_SESSION['error_message'] = "Session expired or invalid. Please try again.";
    redirect('my-bookings.php');
}
$bookingId = (int) $bookingPayment['booking_id'];
$amountPaid = floatval($bookingPayment['amount']);

// Fetch booking
$bookingSql = "SELECT b.*, e.title, e.planner_id as planner_id, e.start_date, e.start_time, e.venue, e.city, e.image, tt.name as ticket_name, u.username as planner_name, u.email as planner_email
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

// Debug log for critical booking fields
$plannerId = $booking['planner_id'] ?? null;
$transactionId = $booking['transaction_id'] ?? null;
error_log("DEBUG: booking_id=$bookingId, planner_id=$plannerId, transaction_id=$transactionId, amountPaid=$amountPaid");

// Safety check for required fields
if (empty($transactionId) || empty($plannerId)) {
    throw new Exception("Missing transaction_id or planner_id for booking completion. booking_id=$bookingId, planner_id=$plannerId, transaction_id=$transactionId");
}

// Payment validation (for Stripe, check session)
if ($isStripe) {
    \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
    $sessionId = $_GET['session_id'];
    $session = \Stripe\Checkout\Session::retrieve($sessionId);
    if ($session->payment_status !== 'paid') {
        $_SESSION['error_message'] = "Payment was not completed successfully.";
        redirect('my-bookings.php');
    }
}

// Mark booking as completed, generate tickets, and send notifications
try {
    $db->query("START TRANSACTION");

    // Update booking status and amount paid
    $updateBookingSql = "UPDATE bookings SET status = 'confirmed', payment_status = 'full', amount_paid = total_amount, updated_at = NOW() WHERE id = $bookingId";
    $db->query($updateBookingSql);

    // Get user info
    $user = $db->fetchOne("SELECT username, email, phone_number FROM users WHERE id = $userId");

    // Create tickets
    $quantity = $booking['quantity'];
    $eventId = $booking['event_id'];
    $ticketTypeId = $booking['ticket_type_id'];
    $price = $booking['total_amount'] / $quantity;
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
        $generatedTickets[] = [
            'id' => $ticketId,
            'event_id' => $eventId,
            'ticket_type_id' => $ticketTypeId,
            'event_title' => $booking['title'],
            'ticket_name' => $booking['ticket_name'],
            'venue' => $booking['venue'],
            'city' => $booking['city'],
            'start_date' => $booking['start_date'],
            'start_time' => $booking['start_time'],
            'recipient_name' => $user['username'],
            'recipient_email' => $user['email'],
            'recipient_phone' => $user['phone_number'],
            'purchase_price' => $price,
            'qr_code' => $qrCode,
            'planner_name' => $booking['planner_name']
        ];
    }

    // Pay planner (96% of remaining amount)
    $systemFees = $amountPaid * 0.04;
    $plannerReceives = $amountPaid - $systemFees;
    $plannerId = $booking['planner_id'];
    $db->query("UPDATE users SET balance = balance + $plannerReceives WHERE id = $plannerId");

    // Create transaction record for customer payment
    $transactionSql = "INSERT INTO transactions (user_id, type, amount, description, status, reference_id, created_at)
                     VALUES ($userId, 'purchase', $amountPaid, 'Booking completion for {$booking['title']}', 'completed', '{$booking['transaction_id']}', NOW())";
    $db->insert($transactionSql);

    // Create transaction record for planner payment
    $plannerTransactionSql = "INSERT INTO transactions (user_id, type, amount, description, status, reference_id, created_at)
                            VALUES ($plannerId, 'sale', $plannerReceives, 'Payment for booking completion - {$booking['title']}', 'completed', '{$booking['transaction_id']}_planner', NOW())";
    $db->insert($plannerTransactionSql);

    // Create transaction record for system fees
    $feesTransactionSql = "INSERT INTO transactions (user_id, type, amount, description, status, reference_id, created_at)
                         VALUES (1, 'system_fee', $systemFees, 'System fees from booking completion - {$booking['title']}', 'completed', '{$booking['transaction_id']}_fees', NOW())";
    $db->insert($feesTransactionSql);

    $db->query("COMMIT");

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

    unset($_SESSION['booking_payment']);
    unset($_SESSION['mobile_money_booking_payment']);
    $_SESSION['success_message'] = "Booking completed successfully! Your tickets are now available.";
    redirect('my-tickets.php');
} catch (Exception $e) {
    $db->query("ROLLBACK");
    $_SESSION['error_message'] = "Failed to complete booking: " . $e->getMessage();
    redirect('my-bookings.php');
}