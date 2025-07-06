<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

$type = $_GET['type'] ?? 'transactions'; // Default to transactions if no type specified
$format = $_GET['format'] ?? 'csv';

// Set headers for download
if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $type . '_export_' . date('Y-m-d') . '.csv"');
} else {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $type . '_export_' . date('Y-m-d') . '.json"');
}

switch ($type) {
    case 'transactions':
        exportTransactions($format);
        break;
    case 'withdrawals':
        exportWithdrawals($format);
        break;
    case 'transaction':
        $id = (int) ($_GET['id'] ?? 0);
        exportSingleTransaction($id, $format);
        break;
    case 'withdrawal':
        $id = (int) ($_GET['id'] ?? 0);
        exportSingleWithdrawal($id, $format);
        break;
    default:
        // If invalid type, redirect to admin finances page with error
        $_SESSION['error_message'] = "Invalid export type: $type";
        header('Location: index.php');
        exit;
}

function exportTransactions($format)
{
    global $db;

    // Build query with filters from GET parameters
    $whereConditions = [];
    if (!empty($_GET['type_filter'])) {
        $whereConditions[] = "t.type = '" . $db->escape($_GET['type_filter']) . "'";
    }
    if (!empty($_GET['status_filter'])) {
        $whereConditions[] = "t.status = '" . $db->escape($_GET['status_filter']) . "'";
    }
    if (!empty($_GET['user_filter'])) {
        $whereConditions[] = "(u.username LIKE '%" . $db->escape($_GET['user_filter']) . "%' OR u.email LIKE '%" . $db->escape($_GET['user_filter']) . "%')";
    }
    if (!empty($_GET['start_date'])) {
        $whereConditions[] = "DATE(t.created_at) >= '" . $db->escape($_GET['start_date']) . "'";
    }
    if (!empty($_GET['end_date'])) {
        $whereConditions[] = "DATE(t.created_at) <= '" . $db->escape($_GET['end_date']) . "'";
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    $sql = "SELECT 
                t.id,
                t.amount,
                t.type,
                t.status,
                t.payment_method,
                t.reference_id,
                t.description,
                t.created_at,
                u.username,
                u.email
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            $whereClause
            ORDER BY t.created_at DESC";

    $transactions = $db->fetchAll($sql);

    if ($format === 'csv') {
        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'Transaction ID',
            'Username',
            'Email',
            'Amount',
            'Type',
            'Status',
            'Payment Method',
            'Reference ID',
            'Description',
            'Created Date'
        ], ',', '"', '\\');

        // CSV data
        foreach ($transactions as $transaction) {
            fputcsv($output, [
                $transaction['id'],
                $transaction['username'],
                $transaction['email'],
                $transaction['amount'],
                $transaction['type'],
                $transaction['status'],
                $transaction['payment_method'] ?? 'N/A',
                $transaction['reference_id'] ?? 'N/A',
                $transaction['description'] ?? 'N/A',
                $transaction['created_at']
            ], ',', '"', '\\');
        }

        fclose($output);
    } else {
        echo json_encode($transactions, JSON_PRETTY_PRINT);
    }
}

function exportWithdrawals($format)
{
    global $db;

    // Build query with filters from GET parameters
    $whereConditions = [];
    if (!empty($_GET['status'])) {
        $whereConditions[] = "w.status = '" . $db->escape($_GET['status']) . "'";
    }
    if (!empty($_GET['start_date'])) {
        $whereConditions[] = "DATE(w.created_at) >= '" . $db->escape($_GET['start_date']) . "'";
    }
    if (!empty($_GET['end_date'])) {
        $whereConditions[] = "DATE(w.created_at) <= '" . $db->escape($_GET['end_date']) . "'";
    }
    if (!empty($_GET['user'])) {
        $whereConditions[] = "(u.username LIKE '%" . $db->escape($_GET['user']) . "%' OR u.email LIKE '%" . $db->escape($_GET['user']) . "%')";
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

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
                w.updated_at,
                u.username,
                u.email,
                u.phone_number
            FROM withdrawals w
            JOIN users u ON w.user_id = u.id
            $whereClause
            ORDER BY w.created_at DESC";

    $withdrawals = $db->fetchAll($sql);

    if ($format === 'csv') {
        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'Withdrawal ID',
            'Username',
            'Email',
            'Phone',
            'Amount',
            'Fee',
            'Net Amount',
            'Payment Method',
            'Status',
            'Admin Notes',
            'Created Date',
            'Updated Date'
        ], ',', '"', '\\');

        // CSV data
        foreach ($withdrawals as $withdrawal) {
            fputcsv($output, [
                $withdrawal['id'],
                $withdrawal['username'],
                $withdrawal['email'],
                $withdrawal['phone_number'],
                $withdrawal['amount'],
                $withdrawal['fee'],
                $withdrawal['net_amount'],
                $withdrawal['payment_method'],
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

function exportSingleTransaction($id, $format)
{
    global $db;

    if ($id <= 0) {
        http_response_code(400);
        echo 'Invalid transaction ID';
        return;
    }

    $sql = "SELECT 
                t.*,
                u.username,
                u.email,
                u.phone_number
            FROM transactions t
            JOIN users u ON t.user_id = u.id
            WHERE t.id = $id";

    $transaction = $db->fetchOne($sql);

    if (!$transaction) {
        http_response_code(404);
        echo 'Transaction not found';
        return;
    }

    if ($format === 'csv') {
        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'Field',
            'Value'
        ], ',', '"', '\\');

        // CSV data
        fputcsv($output, ['Transaction ID', $transaction['id']], ',', '"', '\\');
        fputcsv($output, ['Username', $transaction['username']], ',', '"', '\\');
        fputcsv($output, ['Email', $transaction['email']], ',', '"', '\\');
        fputcsv($output, ['Phone', $transaction['phone_number']], ',', '"', '\\');
        fputcsv($output, ['Amount', $transaction['amount']], ',', '"', '\\');
        fputcsv($output, ['Type', $transaction['type']], ',', '"', '\\');
        fputcsv($output, ['Status', $transaction['status']], ',', '"', '\\');
        fputcsv($output, ['Payment Method', $transaction['payment_method'] ?? 'N/A'], ',', '"', '\\');
        fputcsv($output, ['Reference ID', $transaction['reference_id'] ?? 'N/A'], ',', '"', '\\');
        fputcsv($output, ['Description', $transaction['description'] ?? 'N/A'], ',', '"', '\\');
        fputcsv($output, ['Created Date', $transaction['created_at']], ',', '"', '\\');
        fputcsv($output, ['Updated Date', $transaction['updated_at']], ',', '"', '\\');

        fclose($output);
    } else {
        echo json_encode($transaction, JSON_PRETTY_PRINT);
    }
}

function exportSingleWithdrawal($id, $format)
{
    global $db;

    if ($id <= 0) {
        http_response_code(400);
        echo 'Invalid withdrawal ID';
        return;
    }

    $sql = "SELECT 
                w.*,
                u.username,
                u.email,
                u.phone_number,
                u.balance
            FROM withdrawals w
            JOIN users u ON w.user_id = u.id
            WHERE w.id = $id";

    $withdrawal = $db->fetchOne($sql);

    if (!$withdrawal) {
        http_response_code(404);
        echo 'Withdrawal not found';
        return;
    }

    if ($format === 'csv') {
        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'Field',
            'Value'
        ], ',', '"', '\\');

        // CSV data
        fputcsv($output, ['Withdrawal ID', $withdrawal['id']], ',', '"', '\\');
        fputcsv($output, ['Username', $withdrawal['username']], ',', '"', '\\');
        fputcsv($output, ['Email', $withdrawal['email']], ',', '"', '\\');
        fputcsv($output, ['Phone', $withdrawal['phone_number']], ',', '"', '\\');
        fputcsv($output, ['Current Balance', $withdrawal['balance']], ',', '"', '\\');
        fputcsv($output, ['Withdrawal Amount', $withdrawal['amount']], ',', '"', '\\');
        fputcsv($output, ['Processing Fee', $withdrawal['fee']], ',', '"', '\\');
        fputcsv($output, ['Net Amount', $withdrawal['net_amount']], ',', '"', '\\');
        fputcsv($output, ['Payment Method', $withdrawal['payment_method']], ',', '"', '\\');
        fputcsv($output, ['Payment Details', $withdrawal['payment_details'] ?? 'N/A'], ',', '"', '\\');
        fputcsv($output, ['Status', $withdrawal['status']], ',', '"', '\\');
        fputcsv($output, ['Admin Notes', $withdrawal['admin_notes'] ?? 'N/A'], ',', '"', '\\');
        fputcsv($output, ['Created Date', $withdrawal['created_at']], ',', '"', '\\');
        fputcsv($output, ['Updated Date', $withdrawal['updated_at']], ',', '"', '\\');

        fclose($output);
    } else {
        echo json_encode($withdrawal, JSON_PRETTY_PRINT);
    }
}
?>