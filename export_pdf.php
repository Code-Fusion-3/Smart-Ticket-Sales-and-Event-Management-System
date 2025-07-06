<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/pdf_generator.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$userId = getCurrentUserId();
$type = $_GET['type'] ?? '';
$filters = $_GET;

// Remove type from filters
unset($filters['type']);

switch ($type) {
    case 'transactions':
        $pdfGenerator->generateTransactionReport($userId, 'customer', $filters);
        break;
    case 'tickets':
        $pdfGenerator->generateTicketReport($userId, 'customer', $filters);
        break;
    case 'financial':
        $pdfGenerator->generateFinancialReport($userId, 'customer', $filters);
        break;
    case 'purchases':
        generateCustomerPurchaseReportPDF($userId, $filters);
        break;
    default:
        http_response_code(400);
        echo 'Invalid report type';
        exit;
}

function generateCustomerPurchaseReportPDF($userId, $filters)
{
    global $db, $pdfGenerator;

    // Build query with filters
    $whereConditions = ["t.user_id = $userId AND t.type = 'purchase'"];

    if (!empty($filters['status'])) {
        $whereConditions[] = "t.status = '" . $db->escape($filters['status']) . "'";
    }
    if (!empty($filters['start_date'])) {
        $whereConditions[] = "DATE(t.created_at) >= '" . $db->escape($filters['start_date']) . "'";
    }
    if (!empty($filters['end_date'])) {
        $whereConditions[] = "DATE(t.created_at) <= '" . $db->escape($filters['end_date']) . "'";
    }

    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

    $sql = "SELECT 
                t.id as transaction_id,
                t.amount,
                t.status,
                t.payment_method,
                t.created_at,
                e.title as event_title,
                e.start_date,
                e.start_time,
                e.venue,
                tk.recipient_name,
                tk.recipient_email,
                tt.name as ticket_type
            FROM transactions t
            LEFT JOIN tickets tk ON t.reference_id = tk.id
            LEFT JOIN events e ON tk.event_id = e.id
            LEFT JOIN ticket_types tt ON tk.ticket_type_id = tt.id
            $whereClause
            ORDER BY t.created_at DESC";

    $purchases = $db->fetchAll($sql);

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>My Purchases Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
            .title { font-size: 24px; font-weight: bold; color: #333; }
            .subtitle { font-size: 14px; color: #666; margin-top: 5px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .status-completed { color: green; }
            .status-pending { color: orange; }
            .status-failed { color: red; }
            .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="title">My Purchases Report</div>
            <div class="subtitle">Generated on ' . date('F j, Y \a\t g:i A') . '</div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Event</th>
                    <th>Ticket Type</th>
                    <th>Recipient</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Purchase Date</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($purchases as $purchase) {
        $html .= '
                <tr>
                    <td>' . $purchase['transaction_id'] . '</td>
                    <td>' . htmlspecialchars($purchase['event_title'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($purchase['ticket_type'] ?? 'General') . '</td>
                    <td>' . htmlspecialchars($purchase['recipient_name'] ?? 'N/A') . '</td>
                    <td>' . formatCurrency($purchase['amount']) . '</td>
                    <td class="status-' . $purchase['status'] . '">' . ucfirst($purchase['status']) . '</td>
                    <td>' . date('M j, Y', strtotime($purchase['created_at'])) . '</td>
                </tr>';
    }

    $html .= '
            </tbody>
        </table>
        
        <div class="footer">
            <p>Smart Ticket System - My Purchases Report</p>
        </div>
    </body>
    </html>';

    $pdfGenerator->convertHTMLToPDF($html, 'my_purchases_report_' . date('Y-m-d') . '.pdf');
}
?>