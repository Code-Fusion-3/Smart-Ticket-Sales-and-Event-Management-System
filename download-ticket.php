<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = "download-ticket.php?id=" . ($_GET['id'] ?? '');
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
    $_SESSION['error_message'] = "Ticket not found or you don't have permission to download it.";
    redirect('my-tickets.php');
}

// Generate QR code data
$qrCodeData = json_encode([
    'ticket_id' => $ticketId,
    'event_id' => $ticket['event_id'],
    'user_id' => $userId,
    'verification_token' => $ticket['qr_code'],
    'timestamp' => time()
]);

// Get QR code image
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrCodeData);
$qrCodeImage = file_get_contents($qrCodeUrl);

// Create PDF using TCPDF (or any other PDF library)
// For this example, we'll create a simple HTML file and force download
// In a real application, you'd use a proper PDF library like TCPDF or FPDF

// Set the content type to force download
header('Content-Type: text/html');
header('Content-Disposition: attachment; filename="ticket_' . $ticketId . '.html"');

// Generate HTML content for the ticket
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #' . $ticketId . ' - ' . htmlspecialchars($ticket['event_title']) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9fafb;
        }
        .ticket-container {
            max-width: 800px;
            margin: 20px auto;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .ticket-header {
            background-color: #4f46e5;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ticket-body {
            padding: 20px;
        }
        .ticket-footer {
            background-color: #f9fafb;
            padding: 15px 20px;
            border-top: 1px dashed #e5e7eb;
            display: flex;
            justify-content: space-between;
        }
        .event-title {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
        .ticket-type {
            font-size: 24px;
            font-weight: bold;
            color:rgb(149, 209, 37);
            background-color:rgba(99, 241, 118, 0.64);
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 5px;
        }
        .ticket-id {
            font-size: 14px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .info-section {
            background-color: #f9fafb;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        .info-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: #4f46e5;
        }
        .qr-code {
            text-align: center;
            margin: 20px 0;
        }
        .qr-code img {
            max-width: 200px;
            height: auto;
        }
        .qr-code-text {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
        }
        .tear-line {
            height: 20px;
            border-top: 2px dashed #e5e7eb;
            position: relative;
            margin: 0 20px;
        }
        .tear-line::before, .tear-line::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            background-color: white;
            border-radius: 50%;
            top: -10px;
        }
        .tear-line::before {
            left: -30px;
        }
        .tear-line::after {
            right: -30px;
        }
    </style>
</head>
<body>
    <div class="ticket-container">
        <div class="ticket-header">
            <div>
                <h1 class="event-title">' . htmlspecialchars($ticket['event_title']) . '</h1>
                ' . (!empty($ticket['ticket_type_name']) ? '<div class="ticket-type">' . htmlspecialchars($ticket['ticket_type_name']) . '</div>' : '') . '
            </div>
            <div class="ticket-id">Ticket #' . $ticketId . '</div>
        </div>
        
        <div class="ticket-body">
            <div class="qr-code">
                <img src="' . $qrCodeUrl . '" alt="Ticket QR Code">
                <div class="qr-code-text">Scan to verify ticket</div>
                <div class="qr-code-text">' . substr($ticket['qr_code'], 0, 16) . '...</div>
            </div>
            
            <div class="info-grid">
                <div class="info-section">
                    <div class="info-title">Event Details</div>
                    <p><strong>Date:</strong> ' . formatDate($ticket['start_date']) . '</p>
                    <p><strong>Time:</strong> ' . formatTime($ticket['start_time']) . ' - ' . formatTime($ticket['end_time']) . '</p>
                    <p><strong>Venue:</strong> ' . htmlspecialchars($ticket['venue']) . '</p>
                    <p><strong>Address:</strong> ' . htmlspecialchars($ticket['address'] . ', ' . $ticket['city'] . ', ' . $ticket['country']) . '</p>
                </div>
                
                <div class="info-section">
                    <div class="info-title">Ticket Holder</div>
                    <p><strong>Name:</strong> ' . htmlspecialchars($ticket['recipient_name']) . '</p>
                    <p><strong>Email:</strong> ' . htmlspecialchars($ticket['recipient_email']) . '</p>
                    ' . (!empty($ticket['recipient_phone']) ? '<p><strong>Phone:</strong> ' . htmlspecialchars($ticket['recipient_phone']) . '</p>' : '') . '
                    <p><strong>Purchase Price:</strong> ' . formatCurrency($ticket['purchase_price']) . '</p>
                </div>
            </div>
            
            ' . (!empty($ticket['event_description']) ? '
            <div class="info-section" style="margin-top: 20px;">
                <div class="info-title">Event Description</div>
                <p>' . nl2br(htmlspecialchars($ticket['event_description'])) . '</p>
            </div>' : '') . '
        </div>
        
        <div class="tear-line"></div>
        
        <div class="ticket-footer">
            <div>Purchased on: ' . formatDate($ticket['created_at']) . '</div>
            <div>Powered by ' . SITE_NAME . '</div>
        </div>
    </div>
</body>
</html>';

exit;
