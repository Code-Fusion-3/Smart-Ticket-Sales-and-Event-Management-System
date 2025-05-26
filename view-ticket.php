<?php
$pageTitle = "View Ticket";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = "view-ticket.php?id=" . ($_GET['id'] ?? '');
    redirect('login.php');
}

$userId = getCurrentUserId();
$ticketId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get ticket details
$sql = "SELECT t.*, 
               e.title as event_title, e.description as event_description, 
               e.venue, e.address, e.city, e.country, 
               e.start_date, e.end_date, e.start_time, e.end_time, e.image,
               tt.name as ticket_type_name, tt.description as ticket_type_description,
               u.username as purchaser_name, u.email as purchaser_email
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
        JOIN users u ON t.user_id = u.id
        WHERE t.id = $ticketId";
$ticket = $db->fetchOne($sql);

// Check if ticket exists and belongs to the user
if (!$ticket || $ticket['user_id'] != $userId) {
    $_SESSION['error_message'] = "Ticket not found or you don't have permission to view it.";
    redirect('my-tickets.php');
}

// Determine event status
$now = time();
$startDate = strtotime($ticket['start_date'] . ' ' . $ticket['start_time']);
$endDate = strtotime($ticket['end_date'] . ' ' . $ticket['end_time']);

$eventStatus = 'upcoming';
if ($now > $endDate) {
    $eventStatus = 'past';
} elseif ($now >= $startDate && $now <= $endDate) {
    $eventStatus = 'ongoing';
}

// Set status classes
$statusClasses = [
    'sold' => 'bg-green-100 text-green-800',
    'used' => 'bg-blue-100 text-blue-800',
    'reselling' => 'bg-yellow-100 text-yellow-800',
    'resold' => 'bg-purple-100 text-purple-800'
];
$statusClass = $statusClasses[$ticket['status']] ?? 'bg-gray-100 text-gray-800';

// Set event status classes
$eventStatusClasses = [
    'upcoming' => 'bg-indigo-100 text-indigo-800',
    'ongoing' => 'bg-green-100 text-green-800',
    'past' => 'bg-gray-100 text-gray-600'
];
$eventStatusClass = $eventStatusClasses[$eventStatus];

// Generate QR code data - include ticket ID, event ID, and a verification token
// This ensures the QR code contains all necessary information for verification
$qrCodeData = json_encode([
    'ticket_id' => $ticketId,
    'event_id' => $ticket['event_id'],
    'user_id' => $userId,
    'verification_token' => $ticket['qr_code'],
    'timestamp' => time()
]);

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Navigation -->
        <div class="mb-6">
            <a href="my-tickets.php" class="text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-arrow-left mr-2"></i> Back to My Tickets
            </a>
        </div>
        
        <!-- Professional Ticket Design -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-8 relative">
            <!-- Background Pattern -->
            <div class="absolute inset-0 opacity-5 z-0 overflow-hidden">
                <div class="absolute inset-0" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI1IiBoZWlnaHQ9IjUiPgo8cmVjdCB3aWR0aD0iNSIgaGVpZ2h0PSI1IiBmaWxsPSIjZmZmIj48L3JlY3Q+CjxyZWN0IHdpZHRoPSIxIiBoZWlnaHQ9IjEiIGZpbGw9IiNjY2MiPjwvcmVjdD4KPC9zdmc+'); background-repeat: repeat;"></div>
            </div>
            
            <!-- Ticket Header -->
            <div class="bg-indigo-600 text-white px-6 py-4 flex justify-between items-center relative z-10">
                <div class="flex items-center">
                    <i class="fas fa-ticket-alt text-2xl mr-3"></i>
                    <h1 class="text-xl font-bold">E-Ticket #<?php echo $ticketId; ?></h1>
                </div>
                <div class="flex space-x-2">
                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $eventStatusClass; ?>">
                        <?php echo ucfirst($eventStatus); ?>
                    </span>
                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>">
                        <?php echo ucfirst($ticket['status']); ?>
                    </span>
                </div>
            </div>
            
            <!-- Ticket Content -->
            <div class="p-6 relative z-10">
                <!-- Event Image Banner -->
                <?php if (!empty($ticket['image'])): ?>
                <div class="mb-6 rounded-lg overflow-hidden h-48 relative">
                    <img src="<?php echo substr($ticket['image'], strpos($ticket['image'], 'uploads')); ?>" alt="<?php echo htmlspecialchars($ticket['event_title']); ?>" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-black to-transparent opacity-60"></div>
                    <div class="absolute bottom-0 left-0 p-4 text-white">
                        <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($ticket['event_title']); ?></h2>
                        <?php if (!empty($ticket['ticket_type_name'])): ?>
                            <div class="text-indigo-200 font-medium">
                                <?php echo htmlspecialchars($ticket['ticket_type_name']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="mb-6">
                    <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($ticket['event_title']); ?></h2>
                    <?php if (!empty($ticket['ticket_type_name'])): ?>
                        <div class="text-indigo-600 font-medium">
                            <?php echo htmlspecialchars($ticket['ticket_type_name']); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- QR Code Column -->
                    <div class="md:col-span-1">
                        <div class="bg-gray-50 p-4 rounded-lg text-center border border-gray-200">
                            <div class="mb-4">
                                <div class="mx-auto w-48 h-48 bg-white p-2 border rounded-lg shadow-inner">
                                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo urlencode($qrCodeData); ?>" 
                                         alt="Ticket QR Code" class="w-full h-full">
                                </div>
                                <div class="text-xs text-gray-500 mt-2">
                                    <p>Scan to verify ticket</p>
                                    <p class="font-mono mt-1"><?php echo substr($ticket['qr_code'], 0, 16) . '...'; ?></p>
                                </div>
                            </div>
                            
                            <div class="space-y-2">
                                <a href="download-ticket.php?id=<?php echo $ticketId; ?>" class="block w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                                    <i class="fas fa-download mr-2"></i> Download
                                </a>
                                
                                <a href="email-ticket.php?id=<?php echo $ticketId; ?>" class="block w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                                    <i class="fas fa-envelope mr-2"></i> Email Ticket
                                </a>
                                
                                <?php if ($ticket['status'] === 'sold' && $eventStatus !== 'past'): ?>
                                    <a href="resell-ticket.php?id=<?php echo $ticketId; ?>" class="block w-full bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                                        <i class="fas fa-exchange-alt mr-2"></i> Resell Ticket
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Event Details Column -->
                    <div class="md:col-span-2">
                        <div class="mb-6">
                            <div class="flex items-center text-gray-600 mb-4 bg-gray-50 p-3 rounded-lg border-l-4 border-indigo-500">
                                <i class="fas fa-tag text-indigo-500 mr-2"></i>
                                <span class="font-medium mr-2">Purchase Price:</span>
                                <span class="font-bold"><?php echo formatCurrency($ticket['purchase_price']); ?></span>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <h3 class="font-semibold text-gray-700 mb-2 flex items-center">
                                        <i class="far fa-calendar-alt text-indigo-500 mr-2"></i> Date & Time
                                    </h3>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex items-start">
                                            <span class="font-medium w-20">Date:</span>
                                            <span><?php echo formatDate($ticket['start_date']); ?></span>
                                        </div>
                                        <div class="flex items-start">
                                            <span class="font-medium w-20">Time:</span>
                                            <span><?php echo formatTime($ticket['start_time']); ?> - <?php echo formatTime($ticket['end_time']); ?></span>
                                        </div>
                                        <div class="flex items-start">
                                            <span class="font-medium w-20">Duration:</span>
                                            <?php
                                            $startDateTime = strtotime($ticket['start_date'] . ' ' . $ticket['start_time']);
                                            $endDateTime = strtotime($ticket['end_date'] . ' ' . $ticket['end_time']);
                                            $durationHours = round(($endDateTime - $startDateTime) / 3600);
                                            ?>
                                            <span><?php echo $durationHours; ?> hours</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <h3 class="font-semibold text-gray-700 mb-2 flex items-center">
                                        <i class="fas fa-map-marker-alt text-indigo-500 mr-2"></i> Location
                                    </h3>
                                    <div class="space-y-2 text-sm">
                                        <div class="flex items-start">
                                            <span class="font-medium w-20">Venue:</span>
                                            <span><?php echo htmlspecialchars($ticket['venue']); ?></span>
                                        </div>
                                        <div class="flex items-start">
                                            <span class="font-medium w-20">Address:</span>
                                            <span>
                                                <?php 
                                                $addressParts = [];
                                                if (!empty($ticket['address'])) $addressParts[] = $ticket['address'];
                                                if (!empty($ticket['city'])) $addressParts[] = $ticket['city'];
                                                if (!empty($ticket['country'])) $addressParts[] = $ticket['country'];
                                                echo htmlspecialchars(implode(', ', $addressParts));
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-6">
                                <h3 class="font-semibold text-gray-700 mb-2 flex items-center">
                                    <i class="far fa-user text-indigo-500 mr-2"></i> Ticket Holder
                                </h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div class="flex items-start">
                                        <span class="font-medium w-20">Name:</span>
                                        <span><?php echo htmlspecialchars($ticket['recipient_name']); ?></span>
                                    </div>
                                    <div class="flex items-start">
                                        <span class="font-medium w-20">Email:</span>
                                        <span><?php echo htmlspecialchars($ticket['recipient_email']); ?></span>
                                    </div>
                                    <?php if (!empty($ticket['recipient_phone'])): ?>
                                        <div class="flex items-start">
                                            <span class="font-medium w-20">Phone:</span>
                                            <span><?php echo htmlspecialchars($ticket['recipient_phone']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($ticket['event_description'])): ?>
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <h3 class="font-semibold text-gray-700 mb-2 flex items-center">
                                        <i class="fas fa-info-circle text-indigo-500 mr-2"></i> Event Description
                                    </h3>
                                    <div class="text-sm text-gray-600">
                                        <?php echo nl2br(htmlspecialchars($ticket['event_description'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Ticket Footer with Tear Line -->
            <div class="relative py-2">
                <div class="absolute left-0 right-0 border-t-2 border-dashed border-gray-300"></div>
                <div class="absolute left-0 top-0 bottom-0 w-6 bg-white flex items-center justify-center">
                    <div class="h-6 w-6 rounded-full bg-gray-200"></div>
                </div>
                <div class="absolute right-0 top-0 bottom-0 w-6 bg-white flex items-center justify-center">
                    <div class="h-6 w-6 rounded-full bg-gray-200"></div>
                </div>
            </div>
            
            <!-- Ticket Footer -->
            <div class="bg-gray-50 px-6 py-4 border-t relative z-10">
                <div class="flex flex-wrap justify-between items-center">
                    <div class="text-sm text-gray-600">
                        <div>Purchased on: <?php echo formatDate($ticket['created_at']); ?></div>
                        <div>Ticket ID: <?php echo $ticketId; ?></div>
                    </div>
                    
                    <div class="flex space-x-2 mt-2 sm:mt-0">
                        <button onclick="window.print()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-1 px-3 rounded transition duration-300">
                            <i class="fas fa-print mr-1"></i> Print
                        </button>
                        
                        <button onclick="shareTicket()" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-1 px-3 rounded transition duration-300">
                            <i class="fas fa-share-alt mr-1"></i> Share
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Important Information -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="bg-indigo-600 text-white px-6 py-4">
                <h2 class="text-xl font-bold">Important Information</h2>
            </div>
            
            <div class="p-6">
                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                            <i class="fas fa-info-circle text-indigo-600"></i>
                        </div>
                        <div>
                            <h3 class="font-medium">Entry Requirements</h3>
                            <p class="text-gray-600">Please present this ticket (either printed or on your mobile device) at the venue entrance. The QR code must be clearly visible for scanning.</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                            <i class="fas fa-id-card text-indigo-600"></i>
                        </div>
                        <div>
                            <h3 class="font-medium">Identification</h3>
                            <p class="text-gray-600">You may be required to present a valid ID that matches the name on the ticket. Please ensure you have appropriate identification with you.</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                            <i class="fas fa-ban text-indigo-600"></i>
                        </div>
                        <div>
                            <h3 class="font-medium">Ticket Transfer</h3>
                            <p class="text-gray-600">This ticket is non-transferable unless resold through our platform. Unauthorized reproduction or resale of this ticket is prohibited and may result in cancellation without refund.</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start">
                        <div class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center mr-3">
                            <i class="fas fa-clock text-indigo-600"></i>
                        </div>
                        <div>
                            <h3 class="font-medium">Arrival Time</h3>
                            <p class="text-gray-600">We recommend arriving at least 30 minutes before the event starts to allow time for entry procedures. Late arrivals may be admitted during a suitable break in the event.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Event Location Map -->
        <?php if (!empty($ticket['address']) || !empty($ticket['city'])): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Event Location</h2>
                </div>
                
                <div class="p-6">
                    <div class="aspect-w-16 aspect-h-9 mb-4">
                        <!-- In a real app, integrate with Google Maps or similar service -->
                        <div class="w-full h-64 bg-gray-200 rounded-lg flex items-center justify-center">
                            <div class="text-center">
                                <i class="fas fa-map-marker-alt text-4xl text-gray-400 mb-2"></i>
                                <p class="text-gray-600">
                                    <?php 
                                    $mapAddress = [];
                                    if (!empty($ticket['venue'])) $mapAddress[] = $ticket['venue'];
                                    if (!empty($ticket['address'])) $mapAddress[] = $ticket['address'];
                                    if (!empty($ticket['city'])) $mapAddress[] = $ticket['city'];
                                    if (!empty($ticket['country'])) $mapAddress[] = $ticket['country'];
                                    echo htmlspecialchars(implode(', ', $mapAddress));
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-center">
                        <a href="https://maps.google.com/?q=<?php echo urlencode(implode(', ', $mapAddress)); ?>" 
                           target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                            <i class="fas fa-directions mr-2"></i> Get Directions
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function shareTicket() {
        // Check if Web Share API is supported
        if (navigator.share) {
            navigator.share({
                title: '<?php echo htmlspecialchars($ticket['event_title']); ?> Ticket',
                text: 'Check out my ticket for <?php echo htmlspecialchars($ticket['event_title']); ?>!',
                url: window.location.href
            })
            .catch(error => console.log('Error sharing:', error));
        } else {
            // Fallback for browsers that don't support Web Share API
            alert('Copy this link to share your ticket: ' + window.location.href);
        }
    }
</script>

<!-- Print Styles -->
<style media="print">
    nav, footer, .no-print {
        display: none !important;
    }
    
    body {
        background-color: white;
    }
    
    .container {
        width: 100%;
        max-width: none;
        padding: 0;
        margin: 0;
    }
    
    .shadow-lg, .shadow-md {
        box-shadow: none !important;
    }
    
    .rounded-lg {
        border-radius: 0 !important;
    }
    
    @page {
        margin: 0.5cm;
    }
    
    /* Ensure the ticket design prints well */
    .bg-indigo-600 {
        background-color: #4f46e5 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .bg-gray-50 {
        background-color: #f9fafb !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    
    .text-white {
        color: white !important;
    }
</style>

<?php include 'includes/footer.php'; ?>
