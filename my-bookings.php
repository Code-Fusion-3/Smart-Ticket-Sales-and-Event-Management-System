<?php
$pageTitle = "My Bookings";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Only allow logged-in users
if (!isLoggedIn()) {
    redirect('login.php?redirect=my-bookings.php');
    exit;
}

$userId = getCurrentUserId();
$user = $db->fetchOne("SELECT balance, username, email, phone_number FROM users WHERE id = $userId");
$userBalance = $user['balance'] ?? 0;

// Handle booking completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_booking'])) {
    $bookingId = (int) $_POST['booking_id'];

    // Get booking details
    $bookingSql = "SELECT b.*, e.title, e.start_date, e.start_time, e.venue, tt.name as ticket_name 
                   FROM bookings b 
                   JOIN events e ON b.event_id = e.id 
                   LEFT JOIN ticket_types tt ON b.ticket_type_id = tt.id 
                   WHERE b.id = $bookingId AND b.user_id = $userId AND b.status = 'pending'";
    $booking = $db->fetchOne($bookingSql);

    if ($booking && $booking['status'] === 'pending') {
        // Calculate remaining amount (total - amount already paid)
        $remainingAmount = $booking['total_amount'] - $booking['amount_paid'];
        $systemFees = $remainingAmount * 0.04; // 4% fees deducted from planner's payment
        $plannerReceives = $remainingAmount - $systemFees; // Planner gets 96%

        // Check if user has sufficient balance (customer pays full remaining amount)
        // $userSql = "SELECT balance, username, email, phone_number FROM users WHERE id = $userId";
        // $user = $db->fetchOne($userSql);

        if ($user && $userBalance >= $remainingAmount) {
            try {
                $db->query("START TRANSACTION");

                // Deduct from user balance (customer pays full remaining amount)
                $updateBalanceSql = "UPDATE users SET balance = balance - $remainingAmount WHERE id = $userId";
                $db->query($updateBalanceSql);

                // Update booking status and amount paid
                $updateBookingSql = "UPDATE bookings SET status = 'confirmed', payment_status = 'full', amount_paid = total_amount, updated_at = NOW() WHERE id = $bookingId";
                $db->query($updateBookingSql);

                // Create tickets
                $quantity = $booking['quantity'];
                $eventId = $booking['event_id'];
                $ticketTypeId = $booking['ticket_type_id'];
                $price = $booking['total_amount'] / $quantity; // Price per ticket

                for ($i = 0; $i < $quantity; $i++) {
                    $qrCode = 'TICKET-' . generateRandomString(16);
                    $ticketSql = "INSERT INTO tickets (event_id, ticket_type_id, user_id, recipient_name, recipient_email, recipient_phone, qr_code, purchase_price, status, created_at)
                                 VALUES ($eventId, " . ($ticketTypeId ? $ticketTypeId : "NULL") . ", $userId, 
                                         '" . $db->escape($user['username']) . "', 
                                         '" . $db->escape($user['email']) . "', 
                                         '" . $db->escape($user['phone_number']) . "', 
                                         '$qrCode', $price, 'sold', NOW())";
                    $db->insert($ticketSql);
                }

                // Get planner ID for payment
                $plannerSql = "SELECT planner_id FROM events WHERE id = {$booking['event_id']}";
                $event = $db->fetchOne($plannerSql);
                $plannerId = $event['planner_id'];

                // Pay planner (96% of remaining amount)
                $updatePlannerBalanceSql = "UPDATE users SET balance = balance + $plannerReceives WHERE id = $plannerId";
                $db->query($updatePlannerBalanceSql);

                // Create transaction record for customer payment
                $transactionSql = "INSERT INTO transactions (user_id, type, amount, description, status, reference_id, created_at)
                                 VALUES ($userId, 'purchase', $remainingAmount, 'Booking completion for {$booking['title']}', 'completed', '{$booking['transaction_id']}', NOW())";
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
                $_SESSION['success_message'] = "Booking completed successfully! Your tickets are now available.";

            } catch (Exception $e) {
                $db->query("ROLLBACK");
                $_SESSION['error_message'] = "Failed to complete booking: " . $e->getMessage();
            }
        } else {
            if (!$user) {
                $_SESSION['error_message'] = "User not found.";
            } else {
                $_SESSION['error_message'] = "Insufficient balance. You need " . formatCurrency($remainingAmount) . " to complete your booking. Your current balance: " . formatCurrency($userBalance);
            }
        }
    } else {
        $_SESSION['error_message'] = "Booking not found or already completed.";
    }

    redirect('my-bookings.php');
    exit;
}

// Get user's bookings
$bookingsSql = "SELECT b.*, e.title, e.start_date, e.start_time, e.venue, e.city, e.image,
                       tt.name as ticket_name, tt.description as ticket_description,
                       u.username as planner_name
                FROM bookings b 
                JOIN events e ON b.event_id = e.id 
                LEFT JOIN ticket_types tt ON b.ticket_type_id = tt.id 
                LEFT JOIN users u ON e.planner_id = u.id
                WHERE b.user_id = $userId AND b.status != 'canceled'
                ORDER BY b.created_at DESC";
$bookings = $db->fetchAll($bookingsSql);

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-900">My Bookings</h1>
        <a href="my-tickets.php"
            class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded shadow transition duration-200">
            <i class="fas fa-ticket-alt mr-2"></i> My Tickets
        </a>
    </div>

    <?php if (empty($bookings)): ?>
    <div class="bg-white rounded-lg shadow-md p-8 text-center">
        <div class="text-gray-400 mb-4">
            <i class="fas fa-calendar-times text-6xl"></i>
        </div>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">No Bookings Found</h3>
        <p class="text-gray-600 mb-4">You haven't made any bookings yet.</p>
        <a href="events.php"
            class="inline-flex items-center bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded shadow transition duration-200">
            <i class="fas fa-search mr-2"></i> Browse Events
        </a>
    </div>
    <?php else: ?>

    <div class="grid gap-6">
        <?php foreach ($bookings as $booking): ?>
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="flex flex-col md:flex-row">
                <!-- Event Image -->
                <div class="md:w-1/4">
                    <?php if (!empty($booking['image'])): ?>
                    <img src="<?php echo SITE_URL; ?>/uploads/events/<?php echo $booking['image']; ?>"
                        alt="<?php echo htmlspecialchars($booking['title']); ?>"
                        class="w-full h-48 md:h-full object-cover">
                    <?php else: ?>
                    <div
                        class="w-full h-48 md:h-full bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-white text-4xl"></i>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Booking Details -->
                <div class="md:w-3/4 p-6">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between">
                        <div class="flex-1">
                            <h3 class="text-xl font-bold text-gray-900 mb-2">
                                <?php echo htmlspecialchars($booking['title']); ?>
                            </h3>

                            <!-- Ticket Type Badge -->
                            <div class="mb-3">
                                <span
                                    class="inline-flex items-center bg-indigo-100 text-indigo-800 px-3 py-1 rounded-full text-sm font-medium">
                                    <i class="fas fa-ticket-alt mr-2"></i>
                                    <?php echo htmlspecialchars($booking['ticket_name'] ?? 'Standard Ticket'); ?>
                                </span>
                            </div>

                            <!-- Event Info -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600 mb-4">
                                <div class="flex items-center">
                                    <i class="far fa-calendar-alt mr-2 text-gray-400"></i>
                                    <?php echo formatDate($booking['start_date']); ?>
                                </div>
                                <div class="flex items-center">
                                    <i class="far fa-clock mr-2 text-gray-400"></i>
                                    <?php echo formatTime($booking['start_time']); ?>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-map-marker-alt mr-2 text-gray-400"></i>
                                    <?php echo htmlspecialchars($booking['venue']); ?>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-user mr-2 text-gray-400"></i>
                                    <?php echo htmlspecialchars($booking['planner_name']); ?>
                                </div>
                            </div>

                            <!-- Booking Info -->
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Quantity:</span>
                                    <span class="font-medium"><?php echo $booking['quantity']; ?> tickets</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Total Amount:</span>
                                    <span
                                        class="font-medium"><?php echo formatCurrency($booking['total_amount']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Amount Paid:</span>
                                    <span
                                        class="font-medium text-green-600"><?php echo formatCurrency($booking['amount_paid']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Remaining Payment:</span>
                                    <span
                                        class="font-medium text-orange-600"><?php echo formatCurrency($booking['total_amount'] - $booking['amount_paid']); ?></span>
                                </div>

                                <div class="flex justify-between">
                                    <span class="text-gray-600">Booking Reference:</span>
                                    <span class="font-mono text-xs"><?php echo $booking['transaction_id']; ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Status and Actions -->
                        <div class="mt-4 md:mt-0 md:ml-6 flex flex-col items-end">
                            <!-- Status Badge -->
                            <div class="mb-4">
                                <?php if ($booking['status'] === 'pending'): ?>
                                <span
                                    class="inline-flex items-center bg-orange-100 text-orange-800 px-3 py-1 rounded-full text-sm font-medium">
                                    <i class="fas fa-clock mr-2"></i> Pending Payment
                                </span>
                                <?php elseif ($booking['status'] === 'confirmed'): ?>
                                <span
                                    class="inline-flex items-center bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                                    <i class="fas fa-check mr-2"></i> Completed
                                </span>
                                <?php else: ?>
                                <span
                                    class="inline-flex items-center bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium">
                                    <i class="fas fa-info-circle mr-2"></i> <?php echo ucfirst($booking['status']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <!-- Action Buttons -->
                            <?php if ($booking['status'] === 'pending'): ?>
                            <?php
                                        $remainingAmount = $booking['total_amount'] - $booking['amount_paid'];
                                        // $userBalance = $user['balance'] ?? 0; // This line is removed
                                        ?>
                            <?php if ($userBalance >= $remainingAmount): ?>
                            <form method="POST" class="w-full">
                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                <button type="submit" name="complete_booking"
                                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded shadow transition duration-200">
                                    <i class="fas fa-credit-card mr-2"></i> Complete Payment
                                </button>
                            </form>
                            <p class="text-xs text-gray-500 mt-2 text-center">
                                Pay remaining <?php echo formatCurrency($remainingAmount); ?> from your balance
                            </p>
                            <?php else: ?>
                            <div class="mb-2 text-center text-orange-600 font-semibold">
                                Insufficient balance. You need <?php echo formatCurrency($remainingAmount); ?> to
                                complete your booking.<br>
                                Your current balance: <?php echo formatCurrency($userBalance); ?>
                            </div>
                            <?php if ($userBalance < $remainingAmount && $remainingAmount > 0): ?>
                            <form method="POST" action="booking-complete-payment.php" class="flex flex-col gap-2">
                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                <input type="hidden" name="use_balance" id="use_balance_<?php echo $booking['id']; ?>"
                                    value="0">
                                <input type="hidden" name="balance_amount" value="<?php echo $userBalance; ?>">
                                <input type="hidden" name="remaining_amount"
                                    id="remaining_amount_<?php echo $booking['id']; ?>"
                                    value="<?php echo $remainingAmount; ?>">
                                <div class="mb-2">
                                    <label>
                                        <input type="checkbox" id="useBalanceCheckbox_<?php echo $booking['id']; ?>"
                                            onclick="updatePaymentAmount_<?php echo $booking['id']; ?>()">
                                        Use my balance (<?php echo formatCurrency($userBalance); ?>) to reduce payment
                                    </label>
                                </div>
                                <div class="mb-2">
                                    <span>Amount to pay: <span
                                            id="payAmount_<?php echo $booking['id']; ?>"><?php echo formatCurrency($remainingAmount); ?></span></span>
                                </div>
                                <button type="submit" name="payment_method" value="stripe"
                                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded shadow transition duration-200">
                                    <i class="fab fa-cc-stripe mr-2"></i> Pay with Stripe
                                </button>
                                <button type="submit" name="payment_method" value="mobile_money"
                                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded shadow transition duration-200">
                                    <i class="fas fa-mobile-alt mr-2"></i> Pay with Mobile Money
                                </button>
                            </form>
                            <script>
                            function updatePaymentAmount_<?php echo $booking['id']; ?>() {
                                var checkbox = document.getElementById(
                                    'useBalanceCheckbox_<?php echo $booking['id']; ?>');
                                var payAmountSpan = document.getElementById('payAmount_<?php echo $booking['id']; ?>');
                                var useBalanceInput = document.getElementById(
                                    'use_balance_<?php echo $booking['id']; ?>');
                                var remaining = <?php echo $remainingAmount; ?>;
                                var balance = <?php echo $userBalance; ?>;
                                var toPay = remaining;
                                if (checkbox.checked) {
                                    toPay = Math.max(remaining - balance, 0);
                                    useBalanceInput.value = 1;
                                } else {
                                    toPay = remaining;
                                    useBalanceInput.value = 0;
                                }
                                payAmountSpan.textContent = 'Rwf ' + toPay.toLocaleString(undefined, {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                                document.getElementById('remaining_amount_<?php echo $booking['id']; ?>').value = toPay;
                            }
                            </script>
                            <?php endif; ?>
                            <?php endif; ?>
                            <?php elseif ($booking['status'] === 'confirmed'): ?>
                            <a href="my-tickets.php"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded shadow transition duration-200">
                                <i class="fas fa-ticket-alt mr-2"></i> View Tickets
                            </a>
                            <?php endif; // end status actions ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; // end if (empty($bookings)) ?>
</div>
<?php include 'includes/footer.php'; ?>