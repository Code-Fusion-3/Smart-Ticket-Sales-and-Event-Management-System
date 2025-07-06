<?php
// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Please login to proceed with checkout.";
    redirect('login.php?redirect=checkout.php');
}

// Debug: Check database connection and methods
if (!$db) {
    error_log("Database connection is null");
    $errors[] = "Database connection failed";
}

// Check if required methods exist
if (!method_exists($db, 'insert')) {
    error_log("Database insert method not found");
    $errors[] = "Database configuration error";
}

if (!method_exists($db, 'escape')) {
    error_log("Database escape method not found");
    $errors[] = "Database configuration error";
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

// Initialize variables
$errors = [];
$success = false;
$recipientData = [];

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
            if (empty($recipientName))
                $recipientName = $user['username'];
            if (empty($recipientEmail))
                $recipientEmail = $user['email'];
            if (empty($recipientPhone))
                $recipientPhone = $user['phone_number'];

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

        // If credit card payment is selected and there's an amount to charge, redirect to Stripe
        if ($amountToCharge > 0 && $paymentMethod === 'credit_card') {
            // Store recipient data in session for Stripe payment success handler
            $_SESSION['stripe_recipient_data'] = $recipientData;
            $_SESSION['stripe_cart_items'] = $cartItems;
            $_SESSION['stripe_total'] = $total;
            $_SESSION['stripe_subtotal'] = $subtotal;
            $_SESSION['stripe_fees'] = $fees;
            $_SESSION['stripe_balance_used'] = $balanceUsed;

            // Redirect to Stripe checkout (this will be handled by checkout.php)
            // The actual Stripe session creation happens in checkout.php
            return; // Exit early, let checkout.php handle the Stripe redirect
        }

        // Process external payment if needed (for mobile money and other methods)
        $paymentSuccess = true;
        $paymentReference = generateRandomString(12);

        if ($amountToCharge > 0) {
            // Validate payment details based on method
            if ($paymentMethod === 'mobile_money') {
                $mobileNumber = $_POST['mobile_number'] ?? '';
                if (empty($mobileNumber)) {
                    $errors[] = "Please enter your mobile number.";
                    $paymentSuccess = false;
                }
            }
        }

        if ($paymentSuccess && empty($errors)) {
            // Begin transaction
            try {
                $db->query("START TRANSACTION");

                // Deduct from user balance if used
                if ($balanceUsed > 0) {
                    $newBalance = $user['balance'] - $balanceUsed;
                    $updateBalanceResult = $db->query("UPDATE users SET balance = $newBalance WHERE id = $userId");

                    if (!$updateBalanceResult) {
                        throw new Exception("Failed to update user balance");
                    }

                    // Record balance transaction
                    $balanceTransactionResult = $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, payment_method, description)
                        VALUES ($userId, $balanceUsed, 'purchase', 'completed', '$paymentReference', 'balance', 'Ticket purchase using account balance')");

                    if (!$balanceTransactionResult) {
                        throw new Exception("Failed to record balance transaction");
                    }
                }

                // Record external payment transaction if any
                if ($amountToCharge > 0) {
                    $paymentTransactionResult = $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, payment_method, description)
                        VALUES ($userId, $amountToCharge, 'purchase', 'completed', '$paymentReference', '$paymentMethod', 'Ticket purchase')");

                    if (!$paymentTransactionResult) {
                        throw new Exception("Failed to record payment transaction");
                    }
                }

                // Process each cart item and create tickets
                $generatedTickets = [];

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

                    // Create individual tickets
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

                    // Update ticket availability
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
                }

                // Record system fee
                if ($fees > 0) {
                    $feeTransactionResult = $db->query("INSERT INTO transactions (user_id, amount, type, status, reference_id, description)
                        VALUES ($userId, $fees, 'system_fee', 'completed', '$paymentReference', 'Service fee for ticket purchase')");

                    if (!$feeTransactionResult) {
                        throw new Exception("Failed to record system fee");
                    }
                }

                // Clear the cart
                $clearCartResult = $db->query("DELETE FROM cart_items WHERE cart_id = $cartId");
                if (!$clearCartResult) {
                    throw new Exception("Failed to clear cart");
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
                            // Debug: Log the phone number being sent
                            error_log("Attempting to send SMS to phone: " . $ticket['recipient_phone']);

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
                        } else {
                            error_log("SMS not sent - phone empty or sendSMS function not available. Phone: " . ($ticket['recipient_phone'] ?? 'EMPTY'));
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
                        error_log("Notification Error Stack Trace: " . $notificationError->getTraceAsString());

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

                // Store order reference for order confirmation page
                $_SESSION['order_reference'] = $paymentReference;

                // Set success message
                $_SESSION['success_message'] = "🎉 Payment successful! Your " . count($generatedTickets) . " ticket" . (count($generatedTickets) > 1 ? 's have' : ' has') . " been purchased and sent to your email.";

                // If single ticket, redirect to view that ticket
                if (count($generatedTickets) === 1) {
                    redirect('view-ticket.php?id=' . $generatedTickets[0]['id'] . '&new=1');
                } else {
                    // Multiple tickets, redirect to order confirmation
                    redirect('order-confirmation.php');
                }

            } catch (Exception $e) {
                // Rollback transaction on error
                $db->query("ROLLBACK");
                error_log("Checkout Error: " . $e->getMessage());
                error_log("Checkout Error Stack Trace: " . $e->getTraceAsString());
                $errors[] = "Checkout failed: " . $e->getMessage();
            }
        }
    }
} ?>