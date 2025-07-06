<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/pdf_generator.php';

// Check if user has planner permission
checkPermission('event_planner');

$plannerId = getCurrentUserId();
$type = $_GET['type'] ?? '';
$filters = $_GET;

// Remove type from filters
unset($filters['type']);

switch ($type) {
    case 'transactions':
        $pdfGenerator->generateTransactionReport($plannerId, 'planner', $filters);
        break;
    case 'tickets':
        $pdfGenerator->generateTicketReport($plannerId, 'planner', $filters);
        break;
    case 'financial':
        $pdfGenerator->generateFinancialReport($plannerId, 'planner', $filters);
        break;
    case 'events':
        generatePlannerEventReportPDF($plannerId, $filters);
        break;
    default:
        http_response_code(400);
        echo 'Invalid report type';
        exit;
}

function generatePlannerEventReportPDF($plannerId, $filters)
{
    global $db, $pdfGenerator;

    // Build query with filters
    $whereConditions = ["e.planner_id = $plannerId"];

    if (!empty($filters['status'])) {
        $whereConditions[] = "e.status = '" . $db->escape($filters['status']) . "'";
    }
    if (!empty($filters['start_date'])) {
        $whereConditions[] = "DATE(e.start_date) >= '" . $db->escape($filters['start_date']) . "'";
    }
    if (!empty($filters['end_date'])) {
        $whereConditions[] = "DATE(e.start_date) <= '" . $db->escape($filters['end_date']) . "'";
    }

    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

    $sql = "SELECT 
                e.*,
                COUNT(t.id) as total_tickets,
                COUNT(CASE WHEN t.status = 'sold' THEN 1 END) as sold_tickets,
                COUNT(CASE WHEN t.status = 'available' THEN 1 END) as available_tickets,
                SUM(CASE WHEN t.status = 'sold' THEN t.purchase_price ELSE 0 END) as total_revenue,
                AVG(CASE WHEN t.status = 'sold' THEN t.purchase_price END) as avg_ticket_price
            FROM events e
            LEFT JOIN tickets t ON e.id = t.event_id
            $whereClause
            GROUP BY e.id
            ORDER BY e.start_date DESC";

    $events = $db->fetchAll($sql);

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>My Events Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
            .title { font-size: 24px; font-weight: bold; color: #333; }
            .subtitle { font-size: 14px; color: #666; margin-top: 5px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .status-active { color: green; }
            .status-cancelled { color: red; }
            .status-completed { color: blue; }
            .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="title">My Events Report</div>
            <div class="subtitle">Generated on ' . date('F j, Y \a\t g:i A') . '</div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Date & Time</th>
                    <th>Venue</th>
                    <th>Status</th>
                    <th>Tickets Sold</th>
                    <th>Available</th>
                    <th>Revenue</th>
                    <th>Avg Price</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($events as $event) {
        $html .= '
                <tr>
                    <td>' . htmlspecialchars($event['title']) . '</td>
                    <td>' . date('M j, Y g:i A', strtotime($event['start_date'] . ' ' . $event['start_time'])) . '</td>
                    <td>' . htmlspecialchars($event['venue']) . '</td>
                    <td class="status-' . $event['status'] . '">' . ucfirst($event['status']) . '</td>
                    <td>' . $event['sold_tickets'] . '</td>
                    <td>' . $event['available_tickets'] . '</td>
                    <td>' . formatCurrency($event['total_revenue']) . '</td>
                    <td>' . formatCurrency($event['avg_ticket_price']) . '</td>
                </tr>';
    }

    $html .= '
            </tbody>
        </table>
        
        <div class="footer">
            <p>Smart Ticket System - My Events Report</p>
        </div>
    </body>
    </html>';

    $pdfGenerator->convertHTMLToPDF($html, 'my_events_report_' . date('Y-m-d') . '.pdf');
}
?>