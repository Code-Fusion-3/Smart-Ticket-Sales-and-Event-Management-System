<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$userId = getCurrentUserId();
$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';

// Set headers for download
if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="customer_' . $type . '_export_' . date('Y-m-d') . '.csv"');
} else {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="customer_' . $type . '_export_' . date('Y-m-d') . '.json"');
}

switch ($type) {
    case 'transactions':
        exportTransactions($userId, $format);
        break;
    case 'tickets':
        exportTickets($userId, $format);
        break;
    case 'purchases':
        exportPurchases($userId, $format);
        break;
    default:
        http_response_code(400);
        echo 'Invalid export type';
        exit;
}

function exportTransactions($userId, $format)
{
    global $db;

    // Build query with filters from GET parameters
    $whereConditions = ["t.user_id = $userId"];
    if (!empty($_GET['type_filter'])) {
        $whereConditions[] = "t.type = '" . $db->escape($_GET['type_filter']) . "'";
    }
    if (!empty($_GET['status_filter'])) {
        $whereConditions[] = "t.status = '" . $db->escape($_GET['status_filter']) . "'";
    }
    if (!empty($_GET['start_date'])) {
        $whereConditions[] = "DATE(t.created_at) >= '" . $db->escape($_GET['start_date']) . "'";
    }
    if (!empty($_GET['end_date'])) {
        $whereConditions[] = "DATE(t.created_at) <= '" . $db->escape($_GET['end_date']) . "'";
    }

    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

    $sql = "SELECT 
                t.id,
                t.amount,
                t.type,
                t.status,
                t.payment_method,
                t.reference_id,
                t.description,
                t.created_at,
                e.title as event_title
            FROM transactions t
            LEFT JOIN tickets tk ON t.reference_id = tk.id AND t.type = 'purchase'
            LEFT JOIN events e ON tk.event_id = e.id
            $whereClause
            ORDER BY t.created_at DESC";

    $transactions = $db->fetchAll($sql);

    if ($format === 'csv') {
        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'Transaction ID',
            'Amount',
            'Type',
            'Status',
            'Payment Method',
            'Reference ID',
            'Description',
            'Event Title',
            'Created Date'
        ], ',', '"', '\\');

        // CSV data
        foreach ($transactions as $transaction) {
            fputcsv($output, [
                $transaction['id'],
                $transaction['amount'],
                $transaction['type'],
                $transaction['status'],
                $transaction['payment_method'] ?? 'N/A',
                $transaction['reference_id'] ?? 'N/A',
                $transaction['description'] ?? 'N/A',
                $transaction['event_title'] ?? 'N/A',
                $transaction['created_at']
            ], ',', '"', '\\');
        }

        fclose($output);
    } else {
        echo json_encode($transactions, JSON_PRETTY_PRINT);
    }
}

function exportTickets($userId, $format)
{
    global $db;

    // Build query with filters from GET parameters
    $whereConditions = ["t.user_id = $userId"];
    if (!empty($_GET['status_filter'])) {
        $whereConditions[] = "t.status = '" . $db->escape($_GET['status_filter']) . "'";
    }
    if (!empty($_GET['start_date'])) {
        $whereConditions[] = "DATE(t.created_at) >= '" . $db->escape($_GET['start_date']) . "'";
    }
    if (!empty($_GET['end_date'])) {
        $whereConditions[] = "DATE(t.created_at) <= '" . $db->escape($_GET['end_date']) . "'";
    }

    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

    $sql = "SELECT 
                t.id,
                t.purchase_price,
                t.status,
                t.recipient_name,
                t.recipient_email,
                t.recipient_phone,
                t.created_at,
                e.title as event_title,
                e.start_date,
                e.start_time,
                e.venue,
                e.address,
                e.city,
                tt.name as ticket_type,
                u.username as planner_name
            FROM tickets t
            JOIN events e ON t.event_id = e.id
            LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
            LEFT JOIN users u ON e.planner_id = u.id
            $whereClause
            ORDER BY t.created_at DESC";

    $tickets = $db->fetchAll($sql);

    if ($format === 'csv') {
        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'Ticket ID',
            'Event Title',
            'Ticket Type',
            'Purchase Price',
            'Status',
            'Recipient Name',
            'Recipient Email',
            'Recipient Phone',
            'Event Date',
            'Event Time',
            'Venue',
            'Address',
            'City',
            'Event Planner',
            'Purchase Date'
        ], ',', '"', '\\');

        // CSV data
        foreach ($tickets as $ticket) {
            fputcsv($output, [
                $ticket['id'],
                $ticket['event_title'],
                $ticket['ticket_type'] ?? 'General',
                $ticket['purchase_price'],
                $ticket['status'],
                $ticket['recipient_name'] ?? 'N/A',
                $ticket['recipient_email'] ?? 'N/A',
                $ticket['recipient_phone'] ?? 'N/A',
                $ticket['start_date'],
                $ticket['start_time'],
                $ticket['venue'],
                $ticket['address'] ?? 'N/A',
                $ticket['city'] ?? 'N/A',
                $ticket['planner_name'],
                $ticket['created_at']
            ], ',', '"', '\\');
        }

        fclose($output);
    } else {
        echo json_encode($tickets, JSON_PRETTY_PRINT);
    }
}

function exportPurchases($userId, $format)
{
    global $db;

    // Build query with filters from GET parameters
    $whereConditions = ["t.user_id = $userId AND t.type = 'purchase'"];
    if (!empty($_GET['status_filter'])) {
        $whereConditions[] = "t.status = '" . $db->escape($_GET['status_filter']) . "'";
    }
    if (!empty($_GET['start_date'])) {
        $whereConditions[] = "DATE(t.created_at) >= '" . $db->escape($_GET['start_date']) . "'";
    }
    if (!empty($_GET['end_date'])) {
        $whereConditions[] = "DATE(t.created_at) <= '" . $db->escape($_GET['end_date']) . "'";
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

    if ($format === 'csv') {
        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'Transaction ID',
            'Amount',
            'Status',
            'Payment Method',
            'Event Title',
            'Event Date',
            'Venue',
            'Ticket Type',
            'Recipient Name',
            'Recipient Email',
            'Purchase Date'
        ], ',', '"', '\\');

        // CSV data
        foreach ($purchases as $purchase) {
            fputcsv($output, [
                $purchase['transaction_id'],
                $purchase['amount'],
                $purchase['status'],
                $purchase['payment_method'] ?? 'N/A',
                $purchase['event_title'] ?? 'N/A',
                $purchase['start_date'] ?? 'N/A',
                $purchase['venue'] ?? 'N/A',
                $purchase['ticket_type'] ?? 'General',
                $purchase['recipient_name'] ?? 'N/A',
                $purchase['recipient_email'] ?? 'N/A',
                $purchase['created_at']
            ], ',', '"', '\\');
        }

        fclose($output);
    } else {
        echo json_encode($purchases, JSON_PRETTY_PRINT);
    }
}
?>