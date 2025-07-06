<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/pdf_generator.php';

// Check if user has admin permission
checkPermission('admin');

$type = $_GET['type'] ?? '';
$filters = $_GET;

// Remove type from filters
unset($filters['type']);

switch ($type) {
    case 'transactions':
        $pdfGenerator->generateTransactionReport(0, 'admin', $filters);
        break;
    case 'tickets':
        $pdfGenerator->generateTicketReport(0, 'admin', $filters);
        break;
    case 'financial':
        $pdfGenerator->generateFinancialReport(0, 'admin', $filters);
        break;
    case 'events':
        generateEventReportPDF($filters);
        break;
    case 'users':
        generateUserReportPDF($filters);
        break;
    default:
        http_response_code(400);
        echo 'Invalid report type';
        exit;
}

function generateEventReportPDF($filters)
{
    global $db, $pdfGenerator;

    // Build query with filters
    $whereConditions = [];

    if (!empty($filters['status'])) {
        $whereConditions[] = "e.status = '" . $db->escape($filters['status']) . "'";
    }
    if (!empty($filters['start_date'])) {
        $whereConditions[] = "DATE(e.start_date) >= '" . $db->escape($filters['start_date']) . "'";
    }
    if (!empty($filters['end_date'])) {
        $whereConditions[] = "DATE(e.start_date) <= '" . $db->escape($filters['end_date']) . "'";
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    $sql = "SELECT 
                e.*,
                u.username as planner_name,
                COUNT(t.id) as total_tickets,
                COUNT(CASE WHEN t.status = 'sold' THEN 1 END) as sold_tickets,
                SUM(CASE WHEN t.status = 'sold' THEN t.purchase_price ELSE 0 END) as total_revenue
            FROM events e
            LEFT JOIN users u ON e.planner_id = u.id
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
        <title>Event Report</title>
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
            <div class="title">Event Report</div>
            <div class="subtitle">Generated on ' . date('F j, Y \a\t g:i A') . '</div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Planner</th>
                    <th>Date & Time</th>
                    <th>Venue</th>
                    <th>Status</th>
                    <th>Tickets Sold</th>
                    <th>Revenue</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($events as $event) {
        $html .= '
                <tr>
                    <td>' . htmlspecialchars($event['title']) . '</td>
                    <td>' . htmlspecialchars($event['planner_name']) . '</td>
                    <td>' . date('M j, Y g:i A', strtotime($event['start_date'] . ' ' . $event['start_time'])) . '</td>
                    <td>' . htmlspecialchars($event['venue']) . '</td>
                    <td class="status-' . $event['status'] . '">' . ucfirst($event['status']) . '</td>
                    <td>' . $event['sold_tickets'] . '/' . $event['total_tickets'] . '</td>
                    <td>' . formatCurrency($event['total_revenue']) . '</td>
                </tr>';
    }

    $html .= '
            </tbody>
        </table>
        
        <div class="footer">
            <p>Smart Ticket System - Event Report</p>
        </div>
    </body>
    </html>';

    $pdfGenerator->convertHTMLToPDF($html, 'event_report_' . date('Y-m-d') . '.pdf');
}

function generateUserReportPDF($filters)
{
    global $db, $pdfGenerator;

    // Build query with filters
    $whereConditions = [];

    if (!empty($filters['role'])) {
        $whereConditions[] = "u.role = '" . $db->escape($filters['role']) . "'";
    }
    if (!empty($filters['status'])) {
        $whereConditions[] = "u.status = '" . $db->escape($filters['status']) . "'";
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    $sql = "SELECT 
                u.*,
                COUNT(t.id) as total_tickets,
                SUM(CASE WHEN t.status = 'sold' THEN t.purchase_price ELSE 0 END) as total_spent
            FROM users u
            LEFT JOIN tickets t ON u.id = t.user_id
            $whereClause
            GROUP BY u.id
            ORDER BY u.created_at DESC";

    $users = $db->fetchAll($sql);

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>User Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
            .title { font-size: 24px; font-weight: bold; color: #333; }
            .subtitle { font-size: 14px; color: #666; margin-top: 5px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .status-active { color: green; }
            .status-inactive { color: red; }
            .role-admin { color: red; }
            .role-planner { color: blue; }
            .role-customer { color: green; }
            .role-agent { color: orange; }
            .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="title">User Report</div>
            <div class="subtitle">Generated on ' . date('F j, Y \a\t g:i A') . '</div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Tickets</th>
                    <th>Total Spent</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($users as $user) {
        $html .= '
                <tr>
                    <td>' . htmlspecialchars($user['username']) . '</td>
                    <td>' . htmlspecialchars($user['email']) . '</td>
                    <td class="role-' . $user['role'] . '">' . ucfirst($user['role']) . '</td>
                    <td class="status-' . $user['status'] . '">' . ucfirst($user['status']) . '</td>
                    <td>' . date('M j, Y', strtotime($user['created_at'])) . '</td>
                    <td>' . $user['total_tickets'] . '</td>
                    <td>' . formatCurrency($user['total_spent']) . '</td>
                </tr>';
    }

    $html .= '
            </tbody>
        </table>
        
        <div class="footer">
            <p>Smart Ticket System - User Report</p>
        </div>
    </body>
    </html>';

    $pdfGenerator->convertHTMLToPDF($html, 'user_report_' . date('Y-m-d') . '.pdf');
}
?>