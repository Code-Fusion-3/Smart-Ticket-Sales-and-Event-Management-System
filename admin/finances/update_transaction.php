<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

$transactionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$returnUrl = isset($_GET['return']) ? $_GET['return'] : 'transactions.php';

// Validate parameters
if ($transactionId <= 0) {
    $_SESSION['error_message'] = "Invalid transaction ID";
    redirect($returnUrl);
}

if (!in_array($action, ['complete', 'fail'])) {
    $_SESSION['error_message'] = "Invalid action";
    redirect($returnUrl);
}

// Get transaction details
$sql = "SELECT * FROM transactions WHERE id = $transactionId";
$transaction = $db->fetchOne($sql);

if (!$transaction) {
    $_SESSION['error_message'] = "Transaction not found";
    redirect($returnUrl);
}

// Check if transaction can be updated
if ($transaction['status'] !== 'pending') {
    $_SESSION['error_message'] = "Only pending transactions can be updated";
    redirect($returnUrl);
}

// Update transaction status
$newStatus = ($action === 'complete') ? 'completed' : 'failed';
$sql = "UPDATE transactions SET status = '$newStatus', updated_at = NOW() WHERE id = $transactionId";

if ($db->query($sql)) {
    $actionText = ($action === 'complete') ? 'completed' : 'failed';
    $_SESSION['success_message'] = "Transaction #$transactionId has been marked as $actionText";
    
    // If completing a transaction, update user balance if needed
    if ($action === 'complete' && in_array($transaction['type'], ['deposit', 'sale', 'resale'])) {
        $balanceUpdateSql = "UPDATE users SET balance = balance + {$transaction['amount']} WHERE id = {$transaction['user_id']}";
        $db->query($balanceUpdateSql);
    }
    
    // If failing a withdrawal transaction, refund the amount
    if ($action === 'fail' && $transaction['type'] === 'withdrawal') {
        $refundSql = "UPDATE users SET balance = balance + {$transaction['amount']} WHERE id = {$transaction['user_id']}";
        $db->query($refundSql);
    }
    
} else {
    $_SESSION['error_message'] = "Failed to update transaction status";
}

// Clean return URL
$cleanReturnUrl = str_replace(SITE_URL . '/', '', urldecode($returnUrl));
$cleanReturnUrl = str_replace(SITE_URL, '', $cleanReturnUrl);

// Ensure it's a relative URL
if (strpos($cleanReturnUrl, 'http') === 0 || strpos($cleanReturnUrl, '//') === 0) {
    $cleanReturnUrl = 'transactions.php';
}

// Remove any leading slashes and ensure it starts from admin directory
$cleanReturnUrl = ltrim($cleanReturnUrl, '/');
if (strpos($cleanReturnUrl, 'admin/') !== 0) {
    $cleanReturnUrl = 'transactions.php';
}

// Redirect back to the return URL
redirect($cleanReturnUrl);
?>