<?php
$pageTitle = "Payment Processing";
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

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Please login to view your payment status.";
    redirect('login.php');
}

$userId = getCurrentUserId();

// Check if session_id parameter exists
if (!isset($_GET['session_id'])) {
    $_SESSION['error_message'] = "Invalid payment session.";
    redirect('my-tickets.php');
}

$sessionId = $_GET['session_id'];

// Check if this is a resold ticket purchase
if (isset($_GET['resale']) && $_GET['resale'] == '1' && isset($_SESSION['resale_payment'])) {
    $resaleData = $_SESSION['resale_payment'];
    unset($_SESSION['resale_payment']);
    $resaleId = (int) $resaleData['resale_id'];
    $recipientName = $resaleData['recipient_name'];
    $recipientEmail = $resaleData['recipient_email'];
    $recipientPhone = $resaleData['recipient_phone'];
    $balanceUsed = $resaleData['balance_used'];
    $amountToCharge = $resaleData['amount_to_charge'];
    $paymentMethod = $resaleData['payment_method'];
    $stripeSessionId = $sessionId;
    // Generate a user-friendly order reference for resale
    $orderReference = 'RS-' . strtoupper(generateRandomString(10));
    // Fetch the resale listing again to ensure it's still valid
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
    try {
        $db->query("START TRANSACTION");
        // Record buyer's balance transaction if any
        if ($balanceUsed > 0) {
            $newBuyerBalance = $user['balance'] - $balanceUsed;
            $db->query("UPDATE users SET balance = $newBuyerBalance WHERE id = $userId");
            $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, external_reference, payment_method, description)
                        VALUES ($userId, $balanceUsed, 'purchase', 'completed', '$orderReference', '$stripeSessionId', 'balance', 'Resale ticket purchase using account balance')");
        }
        // Record Stripe payment transaction
        if ($amountToCharge > 0) {
            $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, external_reference, payment_method, description)
                        VALUES ($userId, $amountToCharge, 'purchase', 'completed', '$orderReference', '$stripeSessionId', 'credit_card', 'Resale ticket purchase via Stripe')");
        }
        // Add earnings to seller's balance
        $sellerEarnings = $listing['seller_earnings'];
        $db->query("UPDATE users SET balance = balance + $sellerEarnings WHERE id = " . $listing['seller_id']);
        // Record seller's earnings transaction
        $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, external_reference, description)
                    VALUES (" . $listing['seller_id'] . ", $sellerEarnings, 'sale', 'completed', '$orderReference', '$stripeSessionId', 'Resale ticket sale earnings')");
        // Record platform fee
        $platformFee = $listing['platform_fee'];
        if ($platformFee > 0) {
            $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, external_reference, description)
                        VALUES (" . $listing['seller_id'] . ", $platformFee, 'system_fee', 'completed', '$orderReference', '$stripeSessionId', 'Platform fee for resale transaction')");
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
        $db->query("UPDATE ticket_resales SET status = 'sold', sold_at = NOW(), buyer_id = $userId WHERE id = $resaleId");
        // Send notifications
        // Notify buyer
        $buyerNotificationTitle = "Ticket Purchase Successful";
        $buyerNotificationMessage = "You have successfully purchased a resale ticket for '" . $listing['title'] . "'.";
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
        $sellerEmailBody = "\n                <h2>Congratulations! Your ticket has been sold.</h2>\n                <p>Your ticket for <strong>" . htmlspecialchars($listing['title']) . "</strong> has been successfully sold.</p>\n                <p><strong>Sale Details:</strong></p>\n                <ul>\n                    <li>Sale Price: " . formatCurrency($listing['resale_price']) . "</li>\n                    <li>Platform Fee: " . formatCurrency($platformFee) . "</li>\n                    <li>Your Earnings: " . formatCurrency($sellerEarnings) . "</li>\n                </ul>\n                <p>The earnings have been added to your account balance.</p>\n                ";
        sendEmail($listing['seller_email'], $sellerEmailSubject, $sellerEmailBody);
        $db->query("COMMIT");
        $_SESSION['success_message'] = "Ticket purchased successfully! Check your email for ticket details.";
        $_SESSION['order_reference'] = $orderReference;
        redirect('order-confirmation.php');
    } catch (Exception $e) {
        $db->query("ROLLBACK");
        error_log("Resale purchase error: " . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred during resale purchase. Please try again.";
        redirect('marketplace.php');
    }
}

try {
    // Retrieve the session from Stripe
    $session = \Stripe\Checkout\Session::retrieve($sessionId);

    // Check if payment was successful
    if ($session->payment_status !== 'paid') {
        $_SESSION['error_message'] = "Payment was not completed successfully.";
        redirect('checkout.php');
    }

    // Check if we've already processed this session
    $existingTransaction = $db->fetchOne("SELECT * FROM transactions WHERE reference_id = '" . $db->escape($sessionId) . "' AND type = 'purchase'");
    if ($existingTransaction) {
        // Payment already processed, redirect to order confirmation
        $_SESSION['order_reference'] = $sessionId;
        redirect('order-confirmation.php');
    }

    // Get cart items from session (stored before Stripe redirect)
    if (!isset($_SESSION['stripe_cart_items']) || !isset($_SESSION['stripe_recipient_data'])) {
        $_SESSION['error_message'] = "Session data not found. Please try again.";
        redirect('cart.php');
    }

    $cartItems = $_SESSION['stripe_cart_items'];
    $recipientData = $_SESSION['stripe_recipient_data'];
    $total = $_SESSION['stripe_total'];
    $subtotal = $_SESSION['stripe_subtotal'];
    $fees = $_SESSION['stripe_fees'];
    $balanceUsed = $_SESSION['stripe_balance_used'];
    $bookingType = $_SESSION['stripe_booking_type'] ?? 'full_payment';
    $stripeAmount = $_SESSION['stripe_amount'] ?? $total;

    // Clear session data
    unset($_SESSION['stripe_cart_items']);
    unset($_SESSION['stripe_recipient_data']);
    unset($_SESSION['stripe_total']);
    unset($_SESSION['stripe_subtotal']);
    unset($_SESSION['stripe_fees']);
    unset($_SESSION['stripe_balance_used']);
    unset($_SESSION['stripe_booking_type']);
    unset($_SESSION['stripe_amount']);

    if (empty($cartItems)) {
        $_SESSION['error_message'] = "No items found in session.";
        redirect('cart.php');
    }

    // Get user information
    $userSql = "SELECT username, email, phone_number, balance FROM users WHERE id = $userId";
    $user = $db->fetchOne($userSql);

    // Calculate total tickets
    $totalTickets = 0;
    foreach ($cartItems as $item) {
        $totalTickets += $item['quantity'];
    }

    // Begin transaction
    try {
        $db->query("START TRANSACTION");

        // Record Stripe payment transaction
        $transactionDescription = $bookingType === 'partial_booking' ? 'Booking deposit via Stripe' : 'Ticket purchase via Stripe';
        $paymentTransactionResult = $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, payment_method, description)
            VALUES ($userId, $stripeAmount, 'purchase', 'completed', '$sessionId', 'credit_card', '$transactionDescription')");

        if (!$paymentTransactionResult) {
            throw new Exception("Failed to record payment transaction");
        }

        // Process each cart item and create tickets or bookings
        $generatedTickets = [];
        $generatedBookings = [];

        foreach ($cartItems as $item) {
            $eventId = $item['event_id'];
            $ticketTypeId = $item['ticket_type_id'] ?: null;
            $quantity = $item['quantity'];
            $price = $item['ticket_price'];
            $cartItemId = $item['id'];

            // Verify ticket availability before creating tickets
            if ($ticketTypeId) {
                $availabilityCheck = $db->fetchOne("SELECT available_tickets FROM ticket_types WHERE id = $ticketTypeId");
                if (!$availabilityCheck || $availabilityCheck['available_tickets'] < $quantity) {
                    throw new Exception("Insufficient tickets available for {$item['ticket_name']}");
                }
            } else {
                $availabilityCheck = $db->fetchOne("SELECT available_tickets FROM events WHERE id = $eventId");
                if (!$availabilityCheck || $availabilityCheck['available_tickets'] < $quantity) {
                    throw new Exception("Insufficient tickets available for {$item['title']}");
                }
            }

            if ($bookingType === 'full_payment') {
                // Create individual tickets for full payment
                for ($i = 0; $i < $quantity; $i++) {
                    $recipient = $recipientData[$cartItemId][$i];

                    // Generate unique QR code
                    $qrCode = 'TICKET-' . generateRandomString(16);

                    // Insert ticket with proper escaping
                    $ticketSql = "INSERT INTO tickets (event_id, ticket_type_id, user_id, recipient_name, recipient_email, recipient_phone, qr_code, purchase_price, status, created_at)
                         VALUES ($eventId, " . ($ticketTypeId ? $ticketTypeId : "NULL") . ", $userId, 
                                 '" . $db->escape($recipient['name']) . "', 
                                 '" . $db->escape($recipient['email']) . "', 
                                 '" . $db->escape($recipient['phone']) . "', 
                                 '$qrCode', $price, 'sold', NOW())";

                    $ticketId = $db->insert($ticketSql);

                    if (!$ticketId) {
                        throw new Exception("Failed to create ticket for {$item['title']}");
                    }

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

                // Update ticket availability for full payment
                if ($ticketTypeId) {
                    $updateTicketTypeResult = $db->query("UPDATE ticket_types SET available_tickets = available_tickets - $quantity WHERE id = $ticketTypeId");
                    if (!$updateTicketTypeResult) {
                        throw new Exception("Failed to update ticket type availability");
                    }
                }

                $updateEventResult = $db->query("UPDATE events SET available_tickets = available_tickets - $quantity WHERE id = $eventId");
                if (!$updateEventResult) {
                    throw new Exception("Failed to update event availability");
                }
            } else {
                // Create booking for partial payment
                $bookingReference = generateRandomString(12);
                $bookingPrice = $price * $quantity;
                $depositAmount = $bookingPrice * 0.5; // 50% deposit

                // Insert booking record
                $bookingSql = "INSERT INTO bookings (user_id, event_id, ticket_type_id, quantity, total_amount, amount_paid, status, payment_status, transaction_id, created_at)
                    VALUES ($userId, $eventId, " . ($ticketTypeId ? $ticketTypeId : "NULL") . ", $quantity, $bookingPrice, $depositAmount, 'pending', 'partial', '$bookingReference', NOW())";
                $bookingId = $db->insert($bookingSql);

                if (!$bookingId) {
                    throw new Exception("Failed to create booking for {$item['title']}");
                }

                // Get event planner ID for deposit payment
                $eventSql = "SELECT planner_id FROM events WHERE id = $eventId";
                $eventResult = $db->fetchOne($eventSql);
                $plannerId = $eventResult['planner_id'];

                // Calculate planner earnings for deposit (50% minus system fee)
                $systemFeePercentage = 5.0; // Get from system_fees table
                $systemFeeSql = "SELECT percentage FROM system_fees WHERE fee_type = 'ticket_sale'";
                $feeResult = $db->fetchOne($systemFeeSql);
                if ($feeResult) {
                    $systemFeePercentage = $feeResult['percentage'];
                }

                $systemFeeAmount = ($depositAmount * $systemFeePercentage) / 100;
                $plannerEarning = $depositAmount - $systemFeeAmount;

                // Update planner's balance for deposit
                $updatePlannerBalanceResult = $db->query("UPDATE users SET balance = balance + $plannerEarning WHERE id = $plannerId");
                if (!$updatePlannerBalanceResult) {
                    throw new Exception("Failed to update planner balance for deposit");
                }

                // Record deposit transaction for planner
                $depositTransactionResult = $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, description)
                    VALUES ($plannerId, $plannerEarning, 'sale', 'completed', '$bookingReference', 'Booking deposit for event: " . $db->escape($item['title']) . "')");

                if (!$depositTransactionResult) {
                    throw new Exception("Failed to record planner deposit transaction");
                }

                // Record system fee transaction for deposit
                $depositFeeTransactionResult = $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, description)
                    VALUES ($plannerId, $systemFeeAmount, 'system_fee', 'completed', '$bookingReference', 'Platform fee for booking deposit: " . $db->escape($item['title']) . "')");

                if (!$depositFeeTransactionResult) {
                    throw new Exception("Failed to record planner deposit system fee transaction");
                }

                // Store booking info for notifications
                $generatedBookings[] = [
                    'id' => $bookingId,
                    'event_id' => $eventId,
                    'ticket_type_id' => $ticketTypeId,
                    'event_title' => $item['title'],
                    'ticket_name' => $item['ticket_name'],
                    'ticket_description' => $item['ticket_description'],
                    'venue' => $item['venue'],
                    'city' => $item['city'],
                    'start_date' => $item['start_date'],
                    'start_time' => $item['start_time'],
                    'quantity' => $quantity,
                    'price' => $bookingPrice,
                    'reference' => $bookingReference,
                    'planner_name' => $item['planner_name']
                ];
            }
        }

        // Process planner earnings for each event (only for full payments)
        $plannerEarnings = [];
        if ($bookingType === 'full_payment') {
            foreach ($cartItems as $item) {
                $eventId = $item['event_id'];
                $quantity = $item['quantity'];
                $price = $item['ticket_price'];
                $totalAmount = $price * $quantity;

                // Get event planner ID
                $eventSql = "SELECT planner_id FROM events WHERE id = $eventId";
                $eventResult = $db->fetchOne($eventSql);

                if ($eventResult) {
                    $plannerId = $eventResult['planner_id'];

                    // Calculate planner earnings (total amount minus system fee)
                    $systemFeePercentage = 5.0; // Get from system_fees table
                    $systemFeeSql = "SELECT percentage FROM system_fees WHERE fee_type = 'ticket_sale'";
                    $feeResult = $db->fetchOne($systemFeeSql);
                    if ($feeResult) {
                        $systemFeePercentage = $feeResult['percentage'];
                    }

                    $systemFeeAmount = ($totalAmount * $systemFeePercentage) / 100;
                    $plannerEarning = $totalAmount - $systemFeeAmount;

                    // Update planner's balance
                    $updatePlannerBalanceResult = $db->query("UPDATE users SET balance = balance + $plannerEarning WHERE id = $plannerId");
                    if (!$updatePlannerBalanceResult) {
                        throw new Exception("Failed to update planner balance");
                    }

                    // Record sale transaction for planner
                    $saleTransactionResult = $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, description)
                        VALUES ($plannerId, $plannerEarning, 'sale', 'completed', '$sessionId', 'Ticket sale for event: " . $db->escape($item['title']) . "')");

                    if (!$saleTransactionResult) {
                        throw new Exception("Failed to record planner sale transaction");
                    }

                    // Record system fee transaction for planner
                    $plannerFeeTransactionResult = $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, description)
                        VALUES ($plannerId, $systemFeeAmount, 'system_fee', 'completed', '$sessionId', 'Platform fee for ticket sale: " . $db->escape($item['title']) . "')");

                    if (!$plannerFeeTransactionResult) {
                        throw new Exception("Failed to record planner system fee transaction");
                    }

                    // Store planner earnings info for notifications
                    if (!isset($plannerEarnings[$plannerId])) {
                        $plannerEarnings[$plannerId] = [
                            'total_earnings' => 0,
                            'total_fees' => 0,
                            'events' => []
                        ];
                    }

                    $plannerEarnings[$plannerId]['total_earnings'] += $plannerEarning;
                    $plannerEarnings[$plannerId]['total_fees'] += $systemFeeAmount;
                    $plannerEarnings[$plannerId]['events'][] = [
                        'title' => $item['title'],
                        'quantity' => $quantity,
                        'amount' => $plannerEarning,
                        'fee' => $systemFeeAmount
                    ];
                }
            }
        }

        // Clear the cart
        $cartSql = "SELECT id FROM cart WHERE user_id = $userId";
        $cartResult = $db->fetchOne($cartSql);
        if ($cartResult) {
            $cartId = $cartResult['id'];
            $clearCartResult = $db->query("DELETE FROM cart_items WHERE cart_id = $cartId");
            if (!$clearCartResult) {
                throw new Exception("Failed to clear cart");
            }
        }

        // Commit transaction
        $db->query("COMMIT");

        // Send notifications for each ticket (outside transaction for better performance)
        $notificationResults = [];

        foreach ($generatedTickets as $ticket) {
            try {
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

                // Try enhanced template first, fallback to simple
                try {
                    if (function_exists('getEnhancedTicketEmailTemplate')) {
                        $emailBody = getEnhancedTicketEmailTemplate($ticket, $eventDetails, $qrCodeUrl);
                    } else {
                        throw new Exception("Enhanced template function not available");
                    }
                } catch (Exception $e) {
                    error_log("Enhanced email template failed, using simple template: " . $e->getMessage());
                    if (function_exists('getSimpleTicketEmailTemplate')) {
                        $emailBody = getSimpleTicketEmailTemplate($ticket, $eventDetails);
                    } else {
                        // Fallback to basic HTML template
                        $emailBody = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #6366f1;'>Your Ticket Confirmation</h2>
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                        <h3>" . htmlspecialchars($eventDetails['title']) . "</h3>
                        <p><strong>Ticket Type:</strong> " . htmlspecialchars($ticket['ticket_name']) . "</p>
                        <p><strong>Ticket ID:</strong> #" . $ticket['id'] . "</p>
                        <p><strong>Date:</strong> " . $eventDetails['start_date'] . "</p>
                        <p><strong>Time:</strong> " . $eventDetails['start_time'] . "</p>
                        <p><strong>Venue:</strong> " . htmlspecialchars($eventDetails['venue']) . ", " . htmlspecialchars($eventDetails['city']) . "</p>
                        <p><strong>Recipient:</strong> " . htmlspecialchars($ticket['recipient_name']) . "</p>
                        <p><strong>Price:</strong> " . number_format($ticket['purchase_price']) . " RWF</p>
                        <p><strong>Verification Code:</strong> " . htmlspecialchars($ticket['qr_code']) . "</p>
                    </div>
                </div>";
                    }
                }

                // Create plain text version
                $plainTextEmail = "Your {$ticket['ticket_name']} for {$ticket['event_title']} has been confirmed. " .
                    "Ticket ID: {$ticket['id']}. " .
                    "Event: {$ticket['start_date']} at {$ticket['start_time']} " .
                    "Venue: {$ticket['venue']}, {$ticket['city']}. " .
                    "Verification Code: {$ticket['qr_code']}";

                // Send email
                $emailResult = false;
                if (function_exists('sendEmail')) {
                    $emailResult = sendEmail($ticket['recipient_email'], $emailSubject, $emailBody, $plainTextEmail);
                } else {
                    error_log("sendEmail function not available");
                }

                if ($emailResult) {
                    error_log("Ticket email sent successfully to: " . $ticket['recipient_email']);
                } else {
                    error_log("Failed to send ticket email to: " . $ticket['recipient_email']);
                }

                // Send SMS notification if phone number is provided
                $smsResult = false;
                if (!empty($ticket['recipient_phone']) && function_exists('sendSMS')) {
                    $smsMessage = "Your {$ticket['ticket_name']} for {$ticket['event_title']} is confirmed! " .
                        "Ticket ID: {$ticket['id']}. " .
                        "Event: {$ticket['start_date']} at {$ticket['venue']}. " .
                        "Code: {$ticket['qr_code']}";

                    $smsResult = sendSMS($ticket['recipient_phone'], $smsMessage);

                    if ($smsResult) {
                        error_log("Ticket SMS sent successfully to: " . $ticket['recipient_phone']);
                    } else {
                        error_log("Failed to send ticket SMS to: " . $ticket['recipient_phone']);
                    }
                }

                // Create in-app notification
                $notificationTitle = "Ticket Purchased: {$ticket['ticket_name']}";
                $notificationMessage = "You have successfully purchased a {$ticket['ticket_name']} for {$ticket['event_title']}. Check your email for details.";

                $notificationResult = $db->query("INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                    VALUES ($userId, '" . $db->escape($notificationTitle) . "', '" . $db->escape($notificationMessage) . "', 'ticket', 0, NOW())");

                if ($notificationResult) {
                    error_log("In-app notification created for user: $userId");
                } else {
                    error_log("Failed to create in-app notification for user: $userId");
                }

                // Store notification results
                $notificationResults[] = [
                    'ticket_id' => $ticket['id'],
                    'email_sent' => $emailResult,
                    'sms_sent' => $smsResult,
                    'recipient_email' => $ticket['recipient_email'],
                    'recipient_phone' => $ticket['recipient_phone']
                ];

            } catch (Exception $notificationError) {
                // Log notification errors but don't fail the transaction
                error_log("Notification Error for ticket {$ticket['id']}: " . $notificationError->getMessage());

                // Store failed notification result
                $notificationResults[] = [
                    'ticket_id' => $ticket['id'],
                    'email_sent' => false,
                    'sms_sent' => false,
                    'error' => $notificationError->getMessage(),
                    'recipient_email' => $ticket['recipient_email'],
                    'recipient_phone' => $ticket['recipient_phone']
                ];
            }
        }

        // Log overall notification summary
        $successfulEmails = count(array_filter($notificationResults, function ($r) {
            return $r['email_sent'];
        }));
        $successfulSMS = count(array_filter($notificationResults, function ($r) {
            return $r['sms_sent'];
        }));
        $totalTickets = count($notificationResults);

        error_log("Notification Summary: $successfulEmails/$totalTickets emails sent, $successfulSMS/$totalTickets SMS sent");

        // Send booking notifications for partial payments
        if ($bookingType === 'partial_booking' && !empty($generatedBookings)) {
            foreach ($generatedBookings as $booking) {
                try {
                    // Create in-app notification for booking
                    $bookingNotificationTitle = "Booking Created: {$booking['ticket_name']}";
                    $bookingNotificationMessage = "Your booking for {$booking['event_title']} has been created. Complete the remaining payment before the event.";

                    $bookingNotificationResult = $db->query("INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                        VALUES ($userId, '" . $db->escape($bookingNotificationTitle) . "', '" . $db->escape($bookingNotificationMessage) . "', 'payment', 0, NOW())");

                    if ($bookingNotificationResult) {
                        error_log("Booking notification created for user: $userId");
                    } else {
                        error_log("Failed to create booking notification for user: $userId");
                    }

                    // Send email notification for booking
                    if (function_exists('sendEmail')) {
                        $bookingEmailSubject = "Booking Confirmation - {$booking['event_title']}";
                        $bookingEmailBody = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                            <h2 style='color: #6366f1;'>Booking Confirmation</h2>
                            <p>Hello,</p>
                            <p>Your booking has been successfully created!</p>
                            
                            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                                <h3>Booking Details:</h3>
                                <p><strong>Event:</strong> {$booking['event_title']}</p>
                                <p><strong>Ticket Type:</strong> {$booking['ticket_name']}</p>
                                <p><strong>Quantity:</strong> {$booking['quantity']}</p>
                                <p><strong>Total Amount:</strong> " . formatCurrency($booking['price']) . "</p>
                                <p><strong>Deposit Paid:</strong> " . formatCurrency($booking['price'] * 0.5) . "</p>
                                <p><strong>Remaining Payment:</strong> " . formatCurrency($booking['price'] * 0.5) . "</p>
                                <p><strong>Booking Reference:</strong> {$booking['reference']}</p>
                                <p><strong>Event Date:</strong> {$booking['start_date']} at {$booking['start_time']}</p>
                                <p><strong>Venue:</strong> {$booking['venue']}, {$booking['city']}</p>
                            </div>
                            
                            <p><strong>Important:</strong> Please complete the remaining payment before the event. You can do this from your 'My Bookings' page.</p>
                            <p>Thank you for choosing our platform!</p>
                        </div>";

                        $bookingEmailResult = sendEmail($user['email'], $bookingEmailSubject, $bookingEmailBody);

                        if ($bookingEmailResult) {
                            error_log("Booking email sent successfully to: " . $user['email']);
                        } else {
                            error_log("Failed to send booking email to: " . $user['email']);
                        }
                    }
                } catch (Exception $bookingNotificationError) {
                    error_log("Booking notification error: " . $bookingNotificationError->getMessage());
                }
            }
        }

        // Send notifications to planners about their ticket sales
        foreach ($plannerEarnings as $plannerId => $earnings) {
            try {
                // Get planner details
                $plannerSql = "SELECT username, email FROM users WHERE id = $plannerId";
                $planner = $db->fetchOne($plannerSql);

                if ($planner) {
                    // Create in-app notification for planner
                    $plannerNotificationTitle = "Ticket Sales Update";
                    $plannerNotificationMessage = "You have earned " . formatCurrency($earnings['total_earnings']) . " from recent ticket sales. Check your financial dashboard for details.";

                    $plannerNotificationResult = $db->query("INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                        VALUES ($plannerId, '" . $db->escape($plannerNotificationTitle) . "', '" . $db->escape($plannerNotificationMessage) . "', 'payment', 0, NOW())");

                    if ($plannerNotificationResult) {
                        error_log("Planner notification created for user: $plannerId");
                    } else {
                        error_log("Failed to create planner notification for user: $plannerId");
                    }

                    // Send email to planner if email function exists
                    if (function_exists('sendEmail') && !empty($planner['email'])) {
                        $plannerEmailSubject = "Ticket Sales Update - Earnings Added";
                        $plannerEmailBody = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                            <h2 style='color: #6366f1;'>Ticket Sales Update</h2>
                            <p>Hello {$planner['username']},</p>
                            <p>Great news! You have earned <strong>" . formatCurrency($earnings['total_earnings']) . "</strong> from recent ticket sales.</p>
                            
                            <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                                <h3>Sales Summary:</h3>
                                <ul>";

                        foreach ($earnings['events'] as $event) {
                            $plannerEmailBody .= "<li>{$event['title']}: {$event['quantity']} ticket(s) - " . formatCurrency($event['amount']) . "</li>";
                        }

                        $plannerEmailBody .= "
                                </ul>
                                <p><strong>Total Earnings:</strong> " . formatCurrency($earnings['total_earnings']) . "</p>
                                <p><strong>Platform Fees:</strong> " . formatCurrency($earnings['total_fees']) . "</p>
                            </div>
                            
                            <p>The earnings have been added to your account balance. You can view your financial dashboard for more details.</p>
                            <p>Thank you for using our platform!</p>
                        </div>";

                        $plannerEmailResult = sendEmail($planner['email'], $plannerEmailSubject, $plannerEmailBody);

                        if ($plannerEmailResult) {
                            error_log("Planner email sent successfully to: " . $planner['email']);
                        } else {
                            error_log("Failed to send planner email to: " . $planner['email']);
                        }
                    }
                }
            } catch (Exception $plannerNotificationError) {
                error_log("Planner notification error for planner $plannerId: " . $plannerNotificationError->getMessage());
            }
        }

        // Store order details in session for confirmation page
        if ($bookingType === 'full_payment') {
            $_SESSION['order_details'] = [
                'reference' => $sessionId,
                'total_amount' => $total,
                'total_tickets' => $totalTickets,
                'payment_method' => 'credit_card',
                'balance_used' => 0,
                'tickets' => $generatedTickets
            ];

            // Store order reference for order confirmation page
            $_SESSION['order_reference'] = $sessionId;

            // Set success message for full payment
            $_SESSION['success_message'] = "🎉 Payment successful! Your " . count($generatedTickets) . " ticket" . (count($generatedTickets) > 1 ? 's have' : ' has') . " been purchased and sent to your email.";

            // If single ticket, redirect to view that ticket
            if (count($generatedTickets) === 1) {
                redirect('view-ticket.php?id=' . $generatedTickets[0]['id'] . '&new=1');
            } else {
                // Multiple tickets, redirect to order confirmation
                redirect('order-confirmation.php');
            }
        } else {
            // Store booking details in session
            $_SESSION['booking_details'] = [
                'reference' => $sessionId,
                'total_amount' => $total,
                'deposit_amount' => $stripeAmount,
                'payment_method' => 'credit_card',
                'balance_used' => 0,
                'bookings' => $generatedBookings
            ];

            // Store booking reference
            $_SESSION['booking_reference'] = $sessionId;

            // Set success message for booking
            $_SESSION['success_message'] = "🎉 Booking deposit successful! Your " . count($generatedBookings) . " booking" . (count($generatedBookings) > 1 ? 's have' : ' has') . " been created. Complete the remaining payment before the event.";

            // Redirect to my-bookings page
            redirect('my-bookings.php');
        }

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->query("ROLLBACK");
        error_log("Stripe Payment Processing Error: " . $e->getMessage());
        error_log("Stripe Payment Processing Error Stack Trace: " . $e->getTraceAsString());
        $_SESSION['error_message'] = "Payment processing failed: " . $e->getMessage();
        redirect('checkout.php');
    }

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log("Stripe API Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Payment verification failed. Please contact support.";
    redirect('checkout.php');
} catch (Exception $e) {
    error_log("General Error in Stripe Payment Success: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while processing your payment.";
    redirect('checkout.php');
}
?>