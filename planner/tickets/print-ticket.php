<?php
$pageTitle = "Print Ticket";
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
            e.address,
            e.city,
            e.country,
            e.start_date,
            e.start_time,
            e.end_time,
            u.username, 
            u.email as user_email,
            tt.name as ticket_type
        FROM tickets t
        JOIN events e ON t.event_id = e.id
        JOIN users u ON t.user_id = u.id
        LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
        WHERE t.id = $ticketId
        AND e.planner_id = $plannerId";
$ticket = $db->fetchOne($sql);

if (!$ticket) {
    $_SESSION['error_message'] = "Ticket not found or you don't have permission to view it.";
    redirect('tickets.php');
}

// Set content type to PDF
header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo $ticketId; ?> - <?php echo htmlspecialchars($ticket['event_title']); ?></title>
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
            background-color: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .ticket-header {
            background-color: #4f46e5;
            color: #fff;
            padding: 20px;
            text-align: center;
        }
        .ticket-header h1 {
            margin: 0;
            font-size: 24px;
        }
        .ticket-body {
            display: flex;
            border-bottom: 1px dashed #e5e7eb;
        }
        .ticket-info {
            flex: 2;
            padding: 20px;
            border-right: 1px dashed #e5e7eb;
        }
        .ticket-qr {
            flex: 1;
            padding: 20px;
            text-align: center;
        }
        .ticket-footer {
            padding: 15px 20px;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
        .info-group {
            margin-bottom: 15px;
        }
        .info-label {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        .info-value {
            font-size: 16px;
            font-weight: 500;
        }
        .ticket-id {
            font-size: 14px;
            color: #9ca3af;
            margin-top: 5px;
        }
        .qr-code {
            max-width: 100%;
            height: auto;
        }
        .print-button {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #4f46e5;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .print-button:hover {
            background-color: #4338ca;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-sold {
            background-color: #dcfce7;
            color: #166534;
        }
        .status-used {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .status-reselling {
            background-color: #fef9c3;
            color: #854d0e;
        }
        .status-resold {
            background-color: #f3e8ff;
            color: #6b21a8;
        }
        .event-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .event-details > div {
            flex: 1;
        }
        .ticket-price {
            font-size: 20px;
            font-weight: bold;
            color: #4f46e5;
            margin-top: 10px;
        }
        @media print {
            .print-button {
                display: none;
            }
            body {
                background-color: white;
            }
            .ticket-container {
                box-shadow: none;
                border: 1px solid #e5e7eb;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">Print Ticket</button>
    
    <div class="ticket-container">
        <div class="ticket-header">
            <h1><?php echo htmlspecialchars($ticket['event_title']); ?></h1>
            <div class="ticket-id">Ticket #<?php echo $ticketId; ?></div>
        </div>
        
        <div class="ticket-body">
            <div class="ticket-info">
                <div class="event-details">
                    <div>
                        <div class="info-group">
                            <div class="info-label">Date</div>
                            <div class="info-value"><?php echo formatDate($ticket['start_date']); ?></div>
                        </div>
                        
                        <div class="info-group">
                            <div class="info-label">Time</div>
                            <div class="info-value"><?php echo formatTime($ticket['start_time']); ?> - <?php echo formatTime($ticket['end_time']); ?></div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="info-group">
                            <div class="info-label">Venue</div>
                            <div class="info-value"><?php echo htmlspecialchars($ticket['venue']); ?></div>
                        </div>
                        
                        <div class="info-group">
                            <div class="info-label">Location</div>
                            <div class="info-value">
                                <?php 
                                $location = [];
                                if (!empty($ticket['address'])) $location[] = $ticket['address'];
                                if (!empty($ticket['city'])) $location[] = $ticket['city'];
                                if (!empty($ticket['country'])) $location[] = $ticket['country'];
                                echo htmlspecialchars(implode(', ', $location)); 
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="info-group">
                    <div class="info-label">Ticket Type</div>
                    <div class="info-value"><?php echo htmlspecialchars($ticket['ticket_type'] ?? 'Standard'); ?></div>
                </div>
                
                <div class="info-group">
                    <div class="info-label">Attendee</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($ticket['recipient_name'] ?: $ticket['username']); ?>
                    </div>
                </div>
                
                <?php if (!empty($ticket['recipient_email']) || !empty($ticket['user_email'])): ?>
                    <div class="info-group">
                        <div class="info-label">Email</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($ticket['recipient_email'] ?: $ticket['user_email']); ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="info-group">
                    <div class="info-label">Status</div>
                    <?php
                    $statusClasses = [
                        'sold' => 'status-sold',
                        'used' => 'status-used',
                        'reselling' => 'status-reselling',
                        'resold' => 'status-resold'
                    ];
                    $statusClass = $statusClasses[$ticket['status']] ?? '';
                    ?>
                    <div class="status-badge <?php echo $statusClass; ?>">
                        <?php echo ucfirst($ticket['status']); ?>
                    </div>
                </div>
                
                <div class="ticket-price">
                    <?php echo formatCurrency($ticket['purchase_price']); ?>
                </div>
            </div>
            
            <div class="ticket-qr">
                <?php if (!empty($ticket['qr_code'])): ?>
                    <img src="<?php echo SITE_URL . '/' . $ticket['qr_code']; ?>" alt="Ticket QR Code" class="qr-code">
                <?php else: ?>
                    <div style="padding: 40px; background-color: #f3f4f6; border-radius: 8px;">
                        <p>QR code not available</p>
                    </div>
                <?php endif; ?>
                <p style="margin-top: 10px; font-size: 12px; color: #6b7280;">
                    Scan this QR code at the event entrance
                </p>
            </div>
        </div>
        
        <div class="ticket-footer">
            <p>This ticket was issued by <?php echo SITE_NAME; ?>. Ticket ID: <?php echo $ticketId; ?></p>
            <p>Purchase date: <?php echo formatDate($ticket['created_at']); ?> at <?php echo formatTime($ticket['created_at']); ?></p>
        </div>
    </div>
</body>
</html>
