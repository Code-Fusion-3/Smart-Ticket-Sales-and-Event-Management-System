<?php
$pageTitle = "Verify Ticket";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/notifications.php';

// Check if user has agent permission
checkPermission('agent');

$agentId = getCurrentUserId();

$result = null;
$error = '';

// Check for ticket data in query parameters (for direct scan)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ticket_id'], $_GET['event_id'], $_GET['user_id'], $_GET['verification_token'])) {
    $ticketId = trim($_GET['ticket_id']);
    $eventId = trim($_GET['event_id']);
    $userId = trim($_GET['user_id']);
    $verificationToken = trim($_GET['verification_token']);

    // Try to find ticket by ID, event, user, and QR code
    $sql = "SELECT 
                t.*,
                e.title as event_title,
                e.start_date,
                e.start_time,
                e.end_date,
                e.end_time,
                e.venue,
                e.address,
                e.city,
                tt.name as ticket_type,
                u.username as planner_name
            FROM tickets t
            JOIN events e ON t.event_id = e.id
            LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
            LEFT JOIN users u ON e.planner_id = u.id
            WHERE t.id = '" . $db->escape($ticketId) . "' 
                AND t.event_id = '" . $db->escape($eventId) . "' 
                AND t.user_id = '" . $db->escape($userId) . "' 
                AND t.qr_code = '" . $db->escape($verificationToken) . "' 
                AND t.status IN ('sold', 'used')";
    $ticket = $db->fetchOne($sql);

    if (!$ticket) {
        $result = [
            'status' => 'invalid',
            'message' => 'This ticket is not recognized. Please check the QR code or ID.'
        ];
    } else {
        // Check if ticket has already been used
        $sql = "SELECT verification_time, agent_id FROM ticket_verifications WHERE ticket_id = " . $ticket['id'] . " AND status = 'verified' ORDER BY verification_time ASC LIMIT 1";
        $scanInfo = $db->fetchOne($sql);

        $today = date('Y-m-d');
        $eventDate = $ticket['start_date'];
        $now = strtotime($today);
        $eventStart = strtotime($ticket['start_date']);
        $eventEnd = strtotime($ticket['end_date']);

        if ($scanInfo) {
            // Already used
            $agentName = '';
            if ($scanInfo['agent_id']) {
                $agentSql = "SELECT username FROM users WHERE id = " . intval($scanInfo['agent_id']);
                $agentRow = $db->fetchOne($agentSql);
                $agentName = $agentRow ? $agentRow['username'] : '';
            }
            $result = [
                'status' => 'used',
                'message' => 'This ticket has already been used for entry on ' . formatDateTime($scanInfo['verification_time']) . ($agentName ? ' by Agent ' . htmlspecialchars($agentName) : ''),
                'ticket' => $ticket
            ];
        } elseif ($now < $eventStart) {
            // Too early
            $result = [
                'status' => 'too_early',
                'message' => 'This ticket is valid, but the event has not started yet. Please scan again on the event day.',
                'ticket' => $ticket
            ];
        } elseif ($now > $eventEnd) {
            // Too late
            $result = [
                'status' => 'too_late',
                'message' => 'This ticket is for a past event and cannot be used.',
                'ticket' => $ticket
            ];
        } else {
            // Ticket is valid
            $result = [
                'status' => 'verified',
                'message' => 'Ticket is valid. Welcome!',
                'ticket' => $ticket
            ];
        }

        // Record the verification (except for input_error and too_early/too_late/invalid)
        $status = $result['status'];
        if (in_array($status, ['verified', 'used', 'too_early', 'too_late', 'invalid'])) {
            $sql = "INSERT INTO ticket_verifications (ticket_id, agent_id, verification_time, status, notes, created_at) 
                    VALUES (" . $ticket['id'] . ", $agentId, NOW(), '" . $db->escape($status) . "', '', NOW())";
            $db->query($sql);
        }

        // Update ticket status if verified
        if ($status === 'verified') {
            $sql = "UPDATE tickets SET status = 'used', updated_at = NOW() WHERE id = " . $ticket['id'];
            $db->query($sql);
            
            // Send scan notification to ticket owner
            $agentName = '';
            if ($agentId) {
                $agentSql = "SELECT username FROM users WHERE id = " . intval($agentId);
                $agentRow = $db->fetchOne($agentSql);
                $agentName = $agentRow ? $agentRow['username'] : '';
            }
            
            // Send email notification to ticket owner
            sendTicketScanNotification($ticket['id'], $agentName, date('Y-m-d H:i:s'));
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketId = trim($_POST['ticket_id'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($ticketId)) {
        $result = [
            'status' => 'input_error',
            'message' => 'Please scan a ticket or enter a valid ticket ID/QR code.'
        ];
    } else {
        // Try to find ticket by ID or QR code (manual entry)
        $sql = "SELECT 
                    t.*,
                    e.title as event_title,
                    e.start_date,
                    e.start_time,
                    e.end_date,
                    e.end_time,
                    e.venue,
                    e.address,
                    e.city,
                    tt.name as ticket_type,
                    u.username as planner_name
                FROM tickets t
                JOIN events e ON t.event_id = e.id
                LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
                LEFT JOIN users u ON e.planner_id = u.id
                WHERE (t.id = '" . $db->escape($ticketId) . "' OR t.qr_code = '" . $db->escape($ticketId) . "')
                AND t.status IN ('sold', 'used')";

        $ticket = $db->fetchOne($sql);

        if (!$ticket) {
            $result = [
                'status' => 'invalid',
                'message' => 'This ticket is not recognized. Please check the QR code or ID.'
            ];
        } else {
            // Check if ticket has already been used
            $sql = "SELECT verification_time, agent_id FROM ticket_verifications WHERE ticket_id = " . $ticket['id'] . " AND status = 'verified' ORDER BY verification_time ASC LIMIT 1";
            $scanInfo = $db->fetchOne($sql);

            $today = date('Y-m-d');
            $eventDate = $ticket['start_date'];
            $now = strtotime($today);
            $eventStart = strtotime($ticket['start_date']);
            $eventEnd = strtotime($ticket['end_date']);

            if ($scanInfo) {
                // Already used
                $agentName = '';
                if ($scanInfo['agent_id']) {
                    $agentSql = "SELECT username FROM users WHERE id = " . intval($scanInfo['agent_id']);
                    $agentRow = $db->fetchOne($agentSql);
                    $agentName = $agentRow ? $agentRow['username'] : '';
                }
                $result = [
                    'status' => 'used',
                    'message' => 'This ticket has already been used for entry on ' . formatDateTime($scanInfo['verification_time']) . ($agentName ? ' by Agent ' . htmlspecialchars($agentName) : ''),
                    'ticket' => $ticket
                ];
            } elseif ($now < $eventStart) {
                // Too early
                $result = [
                    'status' => 'too_early',
                    'message' => 'This ticket is valid, but the event has not started yet. Please scan again on the event day.',
                    'ticket' => $ticket
                ];
            } elseif ($now > $eventEnd) {
                // Too late
                $result = [
                    'status' => 'too_late',
                    'message' => 'This ticket is for a past event and cannot be used.',
                    'ticket' => $ticket
                ];
            } else {
                // Ticket is valid
                $result = [
                    'status' => 'verified',
                    'message' => 'Ticket is valid. Welcome!',
                    'ticket' => $ticket
                ];
            }

            // Record the verification (except for input_error)
            $status = $result['status'];
            if (in_array($status, ['verified', 'used', 'too_early', 'too_late', 'invalid'])) {
                $sql = "INSERT INTO ticket_verifications (ticket_id, agent_id, verification_time, status, notes, created_at) 
                        VALUES (" . $ticket['id'] . ", $agentId, NOW(), '" . $db->escape($status) . "', '" . $db->escape($notes) . "', NOW())";
                $db->query($sql);
            }

            // Update ticket status if verified
            if ($status === 'verified') {
                $sql = "UPDATE tickets SET status = 'used', updated_at = NOW() WHERE id = " . $ticket['id'];
                $db->query($sql);
                
                // Send scan notification to ticket owner
                $agentName = '';
                if ($agentId) {
                    $agentSql = "SELECT username FROM users WHERE id = " . intval($agentId);
                    $agentRow = $db->fetchOne($agentSql);
                    $agentName = $agentRow ? $agentRow['username'] : '';
                }
                
                // Send email notification to ticket owner
                sendTicketScanNotification($ticket['id'], $agentName, date('Y-m-d H:i:s'));
            }
        }
    }
}

include '../includes/agent_header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="max-w-2xl mx-auto">
        <!-- Page Header -->
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Verify Ticket</h1>
            <p class="text-gray-600 mt-2">Scan or enter ticket details to verify validity</p>
        </div>

        <!-- Verification Form -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
            <div class="bg-indigo-600 text-white px-6 py-4">
                <h2 class="text-xl font-bold">Scan Ticket</h2>
            </div>
            <div class="p-6">
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="ticket_id" class="block text-sm font-medium text-gray-700 mb-2">Ticket ID or QR
                            Code</label>
                        <input type="text" id="ticket_id" name="ticket_id"
                            value="<?php echo htmlspecialchars($_POST['ticket_id'] ?? ''); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            placeholder="Enter ticket ID or scan QR code" required>
                    </div>

                    <div class="mb-4">
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                        <textarea id="notes" name="notes" rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            placeholder="Add any notes about the verification"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition duration-200">
                            <i class="fas fa-search mr-2"></i> Verify Ticket
                        </button>
                        <a href="index.php"
                            class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Verification Result -->
        <?php if ($result): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="px-6 py-4 <?php
                switch ($result['status']) {
                    case 'verified':
                        echo 'bg-green-600 text-white';
                        break;
                    case 'too_early':
                        echo 'bg-blue-600 text-white';
                        break;
                    case 'too_late':
                        echo 'bg-gray-600 text-white';
                        break;
                    case 'used':
                        echo 'bg-yellow-600 text-white';
                        break;
                    case 'invalid':
                        echo 'bg-red-600 text-white';
                        break;
                    case 'input_error':
                        echo 'bg-red-600 text-white';
                        break;
                    default:
                        echo 'bg-gray-600 text-white';
                }
                ?>">
                    <h2 class="text-xl font-bold">Verification Result</h2>
                </div>
                <div class="p-6">
                    <!-- Status Message -->
                    <div class="mb-6 text-center">
                        <?php if ($result['status'] === 'verified'): ?>
                            <i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i>
                        <?php elseif ($result['status'] === 'too_early'): ?>
                            <i class="fas fa-clock text-6xl text-blue-500 mb-4"></i>
                        <?php elseif ($result['status'] === 'too_late'): ?>
                            <i class="fas fa-calendar-times text-6xl text-gray-500 mb-4"></i>
                        <?php elseif ($result['status'] === 'used'): ?>
                            <i class="fas fa-exclamation-triangle text-6xl text-yellow-500 mb-4"></i>
                        <?php elseif ($result['status'] === 'invalid' || $result['status'] === 'input_error'): ?>
                            <i class="fas fa-times-circle text-6xl text-red-500 mb-4"></i>
                        <?php else: ?>
                            <i class="fas fa-question-circle text-6xl text-gray-500 mb-4"></i>
                        <?php endif; ?>

                        <h3 class="text-2xl font-bold mb-2">
                            <?php echo ucfirst(str_replace('_', ' ', $result['status'])); ?>
                        </h3>
                        <p class="text-gray-600"><?php echo htmlspecialchars($result['message']); ?></p>
                    </div>

                    <!-- Ticket Details -->
                    <?php if (isset($result['ticket'])): ?>
                        <div class="border-t border-gray-200 pt-6">
                            <h4 class="text-lg font-semibold mb-4">Ticket Details</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Event</label>
                                    <p class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($result['ticket']['event_title']); ?>
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Ticket Type</label>
                                    <p class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($result['ticket']['ticket_type'] ?? 'General'); ?>
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Date & Time</label>
                                    <p class="text-sm text-gray-900">
                                        <?php echo formatDate($result['ticket']['start_date']); ?> at
                                        <?php echo formatTime($result['ticket']['start_time']); ?>
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Venue</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($result['ticket']['venue']); ?>
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Recipient Name</label>
                                    <p class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($result['ticket']['recipient_name'] ?? 'N/A'); ?>
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Recipient Email</label>
                                    <p class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($result['ticket']['recipient_email'] ?? 'N/A'); ?>
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Purchase Price</label>
                                    <p class="text-sm text-gray-900">
                                        <?php echo formatCurrency($result['ticket']['purchase_price']); ?>
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Event Planner</label>
                                    <p class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($result['ticket']['planner_name']); ?>
                                    </p>
                                </div>
                            </div>

                            <?php if (!empty($result['ticket']['address'])): ?>
                                <div class="mt-4">
                                    <label class="block text-sm font-medium text-gray-700">Address</label>
                                    <p class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($result['ticket']['address']); ?>,
                                        <?php echo htmlspecialchars($result['ticket']['city']); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="mt-6 flex gap-2">
                        <button onclick="window.location.reload()"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition duration-200">
                            <i class="fas fa-redo mr-2"></i> Scan Another Ticket
                        </button>
                        <a href="index.php"
                            class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded transition duration-200">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/agent_footer.php'; ?>