<?php
$pageTitle = "Email Ticket";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has permission
checkPermission('event_planner');

// Get planner ID
$plannerId = getCurrentUserId();

// Get ticket ID
$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($ticketId <= 0) {
    $_SESSION['error_message'] = "Invalid ticket ID.";
    redirect('tickets.php');
}

// Get ticket details
$sql = "SELECT 
            t.*, 
            e.title as event_title, 
            e.venue,
            e.start_date,
            e.start_time,
            u.username, 
            u.email as user_email
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        JOIN users u ON t.user_id = u.id
        WHERE t.id = $ticketId
        AND e.planner_id = $plannerId";
$ticket = $db->fetchOne($sql);

if (!$ticket) {
    $_SESSION['error_message'] = "Ticket not found or you don't have permission to view it.";
    redirect('tickets.php');
}

$success = false;
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipientEmail = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    
    // Validate inputs
    if (empty($recipientEmail)) {
        $error = "Email address is required";
    } elseif (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif (empty($subject)) {
        $error = "Subject is required";
    } elseif (empty($message)) {
        $error = "Message is required";
    }
    
    if (empty($error)) {
        // Generate ticket PDF or use existing one
        $ticketUrl = SITE_URL . "/planner/print-ticket.php?id=" . $ticketId;
        
        // Send email with ticket
        $emailSent = sendEmail($recipientEmail, $subject, $message, [
            'ticket_url' => $ticketUrl,
            'event_title' => $ticket['event_title'],
            'event_date' => formatDate($ticket['start_date']),
            'event_time' => formatTime($ticket['start_time']),
            'venue' => $ticket['venue'],
            'ticket_id' => $ticketId
        ]);
        
        if ($emailSent) {
            // Log the email
            $sql = "INSERT INTO email_logs (recipient_email, subject, message, status, created_at) 
                    VALUES ('" . $db->escape($recipientEmail) . "', 
                            '" . $db->escape($subject) . "', 
                            '" . $db->escape($message) . "', 
                            'sent', 
                            NOW())";
            $db->query($sql);
            
            $success = true;
        } else {
            $error = "Failed to send email. Please try again.";
        }
    }
}

// Default email content
$defaultSubject = "Your Ticket for " . $ticket['event_title'];
$defaultMessage = "Hello,\n\n";
$defaultMessage .= "Attached is your ticket for " . $ticket['event_title'] . " on " . formatDate($ticket['start_date']) . " at " . formatTime($ticket['start_time']) . ".\n\n";
$defaultMessage .= "Event: " . $ticket['event_title'] . "\n";
$defaultMessage .= "Date: " . formatDate($ticket['start_date']) . "\n";
$defaultMessage .= "Time: " . formatTime($ticket['start_time']) . "\n";
$defaultMessage .= "Venue: " . $ticket['venue'] . "\n";
$defaultMessage .= "Ticket ID: #" . $ticketId . "\n\n";
$defaultMessage .= "Please bring this ticket with you to the event. You can either print it or show it on your mobile device.\n\n";
$defaultMessage .= "Thank you for your purchase!\n\n";
$defaultMessage .= "Regards,\n";
$defaultMessage .= SITE_NAME;

include '../../includes/planner_header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <a href="tickets.php?action=view&id=<?php echo $ticketId; ?>" class="text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-arrow-left mr-2"></i> Back to Ticket
            </a>
            <h1 class="text-3xl font-bold mt-2">Email Ticket #<?php echo $ticketId; ?></h1>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="bg-indigo-600 text-white px-6 py-4">
            <h2 class="text-xl font-bold">Send Ticket by Email</h2>
        </div>
        
        <div class="p-6">
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <p>Ticket has been sent successfully to <?php echo htmlspecialchars($recipientEmail); ?>.</p>
                </div>
                
                <div class="flex justify-center mt-4">
                    <a href="tickets.php?action=view&id=<?php echo $ticketId; ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                        Return to Ticket
                    </a>
                </div>
            <?php else: ?>
                <?php if (!empty($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <p><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="email" class="block text-gray-700 font-bold mb-2">Recipient Email *</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ($ticket['recipient_email'] ?: $ticket['user_email'])); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                               required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="subject" class="block text-gray-700 font-bold mb-2">Subject *</label>
                        <input type="text" id="subject" name="subject" 
                               value="<?php echo htmlspecialchars($_POST['subject'] ?? $defaultSubject); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                               required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="message" class="block text-gray-700 font-bold mb-2">Message *</label>
                        <textarea id="message" name="message" rows="10" 
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                  required><?php echo htmlspecialchars($_POST['message'] ?? $defaultMessage); ?></textarea>
                    </div>
                    
                    <a href="tickets.php?action=view&id=<?php echo $ticketId; ?>" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded mr-2">
                            Cancel
                        </a>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                            Send Email
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden mt-6">
        <div class="bg-indigo-600 text-white px-6 py-4">
            <h2 class="text-xl font-bold">Ticket Preview</h2>
        </div>
        
        <div class="p-6">
            <div class="border border-gray-300 rounded-lg p-4">
                <div class="bg-gray-100 p-4 rounded-lg mb-4">
                    <h3 class="text-xl font-bold"><?php echo htmlspecialchars($ticket['event_title']); ?></h3>
                    <p class="text-gray-600">Ticket #<?php echo $ticketId; ?></p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-sm text-gray-500">Date</p>
                        <p class="text-base font-medium"><?php echo formatDate($ticket['start_date']); ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Time</p>
                        <p class="text-base font-medium"><?php echo formatTime($ticket['start_time']); ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Venue</p>
                        <p class="text-base font-medium"><?php echo htmlspecialchars($ticket['venue']); ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Attendee</p>
                        <p class="text-base font-medium"><?php echo htmlspecialchars($ticket['recipient_name'] ?: $ticket['username']); ?></p>
                    </div>
                </div>
                
                <div class="flex justify-center">
                    <?php if (!empty($ticket['qr_code'])): ?>
                        <img src="<?php echo SITE_URL . '/' . $ticket['qr_code']; ?>" alt="Ticket QR Code" class="h-32 w-32">
                    <?php else: ?>
                        <div class="h-32 w-32 bg-gray-200 flex items-center justify-center rounded">
                            <i class="fas fa-qrcode text-gray-400 text-3xl"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <p class="text-center text-sm text-gray-500 mt-2">
                    This QR code will be included in the email for ticket verification.
                </p>
            </div>
            
            <p class="text-sm text-gray-500 mt-4">
                Note: The recipient will receive a link to view and print the full ticket.
            </p>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
