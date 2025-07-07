<?php
$pageTitle = "Order Confirmation";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$userId = getCurrentUserId();

// Check if order details exist in session (new approach)
if (isset($_SESSION['order_details'])) {
    $orderDetails = $_SESSION['order_details'];
    $orderReference = $orderDetails['reference'];
    $generatedTickets = $orderDetails['tickets'];

    // Clear from session after use
    unset($_SESSION['order_details']);

    // Get transaction details for verification
    $transactionSql = "SELECT * FROM transactions 
                       WHERE user_id = $userId 
                       AND reference_id = '" . $db->escape($orderReference) . "'
                       AND type = 'purchase'
                       ORDER BY created_at DESC LIMIT 1";
    $transaction = $db->fetchOne($transactionSql);

} else {
    // Fallback: Check if order reference exists in session (old approach)
    if (!isset($_SESSION['order_reference'])) {
        $_SESSION['error_message'] = "No order found. Please check your tickets in My Tickets.";
        redirect('my-tickets.php');
    }

    $orderReference = $_SESSION['order_reference'];
    unset($_SESSION['order_reference']); // Clear from session after use

    // Get transaction details
    $transactionSql = "SELECT * FROM transactions 
                       WHERE user_id = $userId 
                       AND reference_id = '" . $db->escape($orderReference) . "'
                       AND type = 'purchase'
                       ORDER BY created_at DESC";
    $transaction = $db->fetchOne($transactionSql);

    if (!$transaction) {
        $_SESSION['error_message'] = "Order not found. Please check your tickets in My Tickets.";
        redirect('my-tickets.php');
    }

    // Get tickets from this purchase
    $ticketsSql = "SELECT t.id, t.event_id, t.ticket_type_id, t.recipient_name, t.recipient_email, 
                          t.recipient_phone, t.qr_code, t.purchase_price, t.created_at,
                          e.title as event_title, e.venue, e.city, e.start_date, e.start_time,
                          tt.name as ticket_type_name
                   FROM tickets t
                   JOIN events e ON t.event_id = e.id
                   LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
                   WHERE t.user_id = $userId 
                   AND t.created_at >= '" . $transaction['created_at'] . "'
                   AND t.created_at <= DATE_ADD('" . $transaction['created_at'] . "', INTERVAL 1 MINUTE)
                   ORDER BY t.id ASC";
    $generatedTickets = $db->fetchAll($ticketsSql);

    // Convert to the format expected by the template
    foreach ($generatedTickets as &$ticket) {
        $ticket['event_title'] = $ticket['event_title'];
        $ticket['ticket_name'] = $ticket['ticket_type_name'] ?? 'Standard Ticket';
        $ticket['venue'] = $ticket['venue'];
        $ticket['city'] = $ticket['city'];
        $ticket['start_date'] = $ticket['start_date'];
        $ticket['start_time'] = $ticket['start_time'];
    }
}

if (empty($generatedTickets)) {
    $_SESSION['error_message'] = "No tickets found for this order. Please check your tickets in My Tickets.";
    redirect('my-tickets.php');
}

// Calculate totals
$totalAmount = 0;
foreach ($generatedTickets as $ticket) {
    $totalAmount += $ticket['purchase_price'];
}

// Get service fee
$feeSql = "SELECT * FROM transactions 
           WHERE user_id = $userId 
           AND reference_id = '" . $db->escape($orderReference) . "'
           AND type = 'system_fee'";
$feeTransaction = $db->fetchOne($feeSql);
$serviceFee = $feeTransaction ? $feeTransaction['amount'] : 0;

// Group tickets by event
$ticketsByEvent = [];
foreach ($generatedTickets as $ticket) {
    $eventId = $ticket['event_id'];
    if (!isset($ticketsByEvent[$eventId])) {
        $ticketsByEvent[$eventId] = [
            'event_title' => $ticket['event_title'],
            'venue' => $ticket['venue'],
            'city' => $ticket['city'],
            'start_date' => $ticket['start_date'],
            'start_time' => $ticket['start_time'],
            'tickets' => []
        ];
    }
    $ticketsByEvent[$eventId]['tickets'][] = $ticket;
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Success Message -->
        <div class="bg-green-100 border border-green-400 text-green-700 px-6 py-4 rounded-lg mb-8 flex items-start">
            <div class="flex-shrink-0 mt-0.5">
                <i class="fas fa-check-circle text-green-500 text-2xl"></i>
            </div>
            <div class="ml-3">
                <h3 class="text-lg font-semibold">🎉 Thank you for your purchase!</h3>
                <p>Your order has been successfully processed. Your <?php echo count($generatedTickets); ?>
                    ticket<?php echo count($generatedTickets) > 1 ? 's are' : ' is'; ?> ready.</p>
            </div>
        </div>

        <!-- Order Details -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="bg-indigo-600 text-white px-6 py-4">
                <h2 class="text-xl font-bold">Order Details</h2>
            </div>

            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="text-gray-500 text-sm font-semibold uppercase mb-2">Order Reference</h3>
                        <p class="text-lg font-mono"><?php echo htmlspecialchars($orderReference); ?></p>
                    </div>
                    <div>
                        <h3 class="text-gray-500 text-sm font-semibold uppercase mb-2">Order Date</h3>
                        <p><?php echo formatDateTime($transaction['created_at'] ?? date('Y-m-d H:i:s')); ?></p>
                    </div>
                    <div>
                        <h3 class="text-gray-500 text-sm font-semibold uppercase mb-2">Payment Method</h3>
                        <p class="capitalize">
                            <?php echo str_replace('_', ' ', $transaction['payment_method'] ?? 'Multiple Methods'); ?>
                        </p>
                    </div>
                    <div>
                        <h3 class="text-gray-500 text-sm font-semibold uppercase mb-2">Order Status</h3>
                        <span
                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                            Completed
                        </span>
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-6">
                    <h3 class="font-semibold text-lg mb-4">Order Summary</h3>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th
                                        class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Event
                                    </th>
                                    <th
                                        class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Ticket Type
                                    </th>
                                    <th
                                        class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Recipient
                                    </th>
                                    <th
                                        class="px-4 py-3 bg-gray-50 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Price
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($generatedTickets as $ticket): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($ticket['event_title']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo formatDate($ticket['start_date']); ?> at
                                                <?php echo formatTime($ticket['start_time']); ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo htmlspecialchars($ticket['ticket_name'] ?? 'Standard Ticket'); ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo htmlspecialchars($ticket['recipient_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($ticket['recipient_email']); ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <?php echo formatCurrency($ticket['purchase_price']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-right font-medium">Subtotal</td>
                                    <td class="px-4 py-3 text-right font-medium">
                                        <?php echo formatCurrency($totalAmount); ?>
                                    </td>
                                </tr>
                                <tr class="bg-gray-50">
                                    <td colspan="3" class="px-4 py-3 text-right font-bold">Total</td>
                                    <td class="px-4 py-3 text-right font-bold">
                                        <?php echo formatCurrency($totalAmount); ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="bg-indigo-600 text-white px-6 py-4">
                <h2 class="text-xl font-bold">Your Tickets</h2>
            </div>

            <div class="p-6">
                <p class="mb-6 text-gray-600">
                    Your tickets are ready! You can view them online or download them for printing.
                    We've also sent a copy to your email address.
                </p>

                <?php foreach ($ticketsByEvent as $eventId => $eventData): ?>
                    <div
                        class="mb-6 pb-6 <?php echo $eventId !== array_key_last($ticketsByEvent) ? 'border-b border-gray-200' : ''; ?>">
                        <h3 class="font-semibold text-lg mb-2"><?php echo htmlspecialchars($eventData['event_title']); ?>
                        </h3>
                        <div class="text-sm text-gray-600 mb-4">
                            <div><i class="far fa-calendar-alt mr-1"></i>
                                <?php echo formatDate($eventData['start_date']); ?> at
                                <?php echo formatTime($eventData['start_time']); ?>
                            </div>
                            <div><i class="fas fa-map-marker-alt mr-1"></i>
                                <?php echo htmlspecialchars($eventData['venue']); ?>,
                                <?php echo htmlspecialchars($eventData['city']); ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($eventData['tickets'] as $ticket): ?>
                                <div
                                    class="border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition-shadow">
                                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                        <div class="flex justify-between items-center">
                                            <div class="font-medium">
                                                <?php echo htmlspecialchars($ticket['ticket_type_name'] ?? 'Standard Ticket'); ?>
                                            </div>
                                            <div class="text-gray-500 text-sm">#<?php echo $ticket['id']; ?></div>
                                        </div>
                                    </div>
                                    <div class="p-4">
                                        <div class="text-center mb-4">
                                            <div class="bg-gray-100 inline-block p-2 rounded-lg">
                                                <!-- Generate actual QR code -->
                                                <div class="w-32 h-32 bg-white flex items-center justify-center">
                                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=128x128&data=<?php echo urlencode($ticket['qr_code']); ?>"
                                                        alt="QR Code" class="w-full h-full">
                                                </div>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                <p>Scan to verify ticket</p>
                                                <p class="font-mono"><?php echo substr($ticket['qr_code'], 0, 16) . '...'; ?>
                                                </p>
                                            </div>
                                        </div>


                                        <div class="text-sm">
                                            <div class="mb-1"><span class="font-medium">Recipient:</span>
                                                <?php echo htmlspecialchars($ticket['recipient_name']); ?></div>
                                            <div><span class="font-medium">Email:</span>
                                                <?php echo htmlspecialchars($ticket['recipient_email']); ?></div>
                                        </div>

                                        <div class="mt-4 flex justify-between">
                                            <a href="view-ticket.php?id=<?php echo $ticket['id']; ?>" target="_blank"
                                                class="text-indigo-600 hover:text-indigo-800 text-sm">
                                                <i class="fas fa-eye mr-1"></i> View
                                            </a>
                                            <a href="download-ticket.php?id=<?php echo $ticket['id']; ?>"
                                                class="text-indigo-600 hover:text-indigo-800 text-sm">
                                                <i class="fas fa-download mr-1"></i> Download
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="mt-6 flex flex-wrap gap-4 justify-center">
                    <a href="my-tickets.php"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded transition duration-300">
                        <i class="fas fa-ticket-alt mr-2"></i> View All My Tickets
                    </a>
                    <a href="events.php"
                        class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-6 rounded transition duration-300">
                        <i class="fas fa-search mr-2"></i> Browse More Events
                    </a>
                </div>
            </div>
        </div>

        <!-- Additional Information -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6">
                <h3 class="font-semibold text-lg mb-4">What's Next?</h3>

                <div class="space-y-4">
                    <div class="flex items-start">
                        <div
                            class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                            <i class="fas fa-envelope text-indigo-600"></i>
                        </div>
                        <div>
                            <h4 class="font-medium">Check Your Email</h4>
                            <p class="text-gray-600">We've sent a confirmation email with your tickets to your
                                registered email address. If you don't see it, please check your spam folder.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div
                            class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                            <i class="fas fa-mobile-alt text-indigo-600"></i>
                        </div>
                        <div>
                            <h4 class="font-medium">Save Your Tickets</h4>
                            <p class="text-gray-600">Download your tickets or save them to your mobile device. You'll
                                need to present them (either printed or on your phone) at the event entrance.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div
                            class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                            <i class="fas fa-calendar-alt text-indigo-600"></i>
                        </div>
                        <div>
                            <h4 class="font-medium">Add to Calendar</h4>
                            <p class="text-gray-600">Don't forget to add the event to your calendar. We'll also send you
                                a reminder email before the event.</p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div
                            class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                            <i class="fas fa-share-alt text-indigo-600"></i>
                        </div>
                        <div>
                            <h4 class="font-medium">Share with Friends</h4>
                            <p class="text-gray-600">Let your friends know you're going! Share the event on social media
                                and invite them to join you.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>