<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has permission
checkPermission('event_planner');

$plannerId = getCurrentUserId();
$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';

// Set headers for download
if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="planner_' . $type . '_export_' . date('Y-m-d') . '.csv"');
} else {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="planner_' . $type . '_export_' . date('Y-m-d') . '.json"');
}

switch ($type) {
    case 'transactions':
        exportTransactions($plannerId, $format);
        break;
    case 'withdrawals':
        exportWithdrawals($plannerId, $format);
        break;
    case 'earnings':
        exportEarnings($plannerId, $format);
        break;
    default:
        http_response_code(400);
        echo 'Invalid export type';
        exit;
}

function exportTransactions($plannerId, $format)
{
    global $db;

    // Build query with filters from GET parameters
    $whereConditions = ["t.user_id = $plannerId"];
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
            LEFT JOIN events e ON tk.event_id = e.id OR (t.type = 'sale' AND t.description LIKE CONCAT('%', e.title, '%'))
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

function exportWithdrawals($plannerId, $format)
{
    global $db;

    // Build query with filters from GET parameters
    $whereConditions = ["w.user_id = $plannerId"];
    if (!empty($_GET['status'])) {
        $whereConditions[] = "w.status = '" . $db->escape($_GET['status']) . "'";
    }
    if (!empty($_GET['start_date'])) {
        $whereConditions[] = "DATE(w.created_at) >= '" . $db->escape($_GET['start_date']) . "'";
    }
    if (!empty($_GET['end_date'])) {
        $whereConditions[] = "DATE(w.created_at) <= '" . $db->escape($_GET['end_date']) . "'";
    }

    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

    $sql = "SELECT 
                w.id,
                w.amount,
                w.fee,
                w.net_amount,
                w.payment_method,
                w.payment_details,
                w.status,
                w.admin_notes,
                w.created_at,
                w.updated_at
            FROM withdrawals w
            $whereClause
            ORDER BY w.created_at DESC";

    $withdrawals = $db->fetchAll($sql);

    if ($format === 'csv') {
        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'Withdrawal ID',
            'Amount',
            'Fee',
            'Net Amount',
            'Payment Method',
            'Payment Details',
            'Status',
            'Admin Notes',
            'Created Date',
            'Updated Date'
        ], ',', '"', '\\');

        // CSV data
        foreach ($withdrawals as $withdrawal) {
            fputcsv($output, [
                $withdrawal['id'],
                $withdrawal['amount'],
                $withdrawal['fee'],
                $withdrawal['net_amount'],
                $withdrawal['payment_method'],
                $withdrawal['payment_details'] ?? 'N/A',
                $withdrawal['status'],
                $withdrawal['admin_notes'] ?? 'N/A',
                $withdrawal['created_at'],
                $withdrawal['updated_at']
            ], ',', '"', '\\');
        }

        fclose($output);
    } else {
        echo json_encode($withdrawals, JSON_PRETTY_PRINT);
    }
}

function exportEarnings($plannerId, $format)
{
    global $db;

    // Get earnings data
    $sql = "SELECT 
                DATE(t.created_at) as date,
                SUM(CASE WHEN t.type = 'sale' THEN t.amount ELSE 0 END) as sales,
                SUM(CASE WHEN t.type = 'system_fee' THEN t.amount ELSE 0 END) as fees,
                SUM(CASE WHEN t.type = 'withdrawal' THEN t.amount ELSE 0 END) as withdrawals,
                COUNT(CASE WHEN t.type = 'sale' THEN 1 END) as sales_count,
                COUNT(CASE WHEN t.type = 'system_fee' THEN 1 END) as fees_count,
                COUNT(CASE WHEN t.type = 'withdrawal' THEN 1 END) as withdrawals_count
            FROM transactions t
            WHERE t.user_id = $plannerId
            GROUP BY DATE(t.created_at)
            ORDER BY date DESC";

    $earnings = $db->fetchAll($sql);

    if ($format === 'csv') {
        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'Date',
            'Sales Amount',
            'Fees Amount',
            'Withdrawals Amount',
            'Net Earnings',
            'Sales Count',
            'Fees Count',
            'Withdrawals Count'
        ], ',', '"', '\\');

        // CSV data
        foreach ($earnings as $earning) {
            $netEarnings = $earning['sales'] - $earning['fees'] - $earning['withdrawals'];
            fputcsv($output, [
                $earning['date'],
                $earning['sales'],
                $earning['fees'],
                $earning['withdrawals'],
                $netEarnings,
                $earning['sales_count'],
                $earning['fees_count'],
                $earning['withdrawals_count']
            ], ',', '"', '\\');
        }

        fclose($output);
    } else {
        // Add calculated fields for JSON
        foreach ($earnings as &$earning) {
            $earning['net_earnings'] = $earning['sales'] - $earning['fees'] - $earning['withdrawals'];
        }
        echo json_encode($earnings, JSON_PRETTY_PRINT);
    }
}
?>