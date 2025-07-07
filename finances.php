<?php
$pageTitle = "My Finances";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/notifications.php';

if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Please login to view your finances.";
    redirect('login.php');
}

$userId = getCurrentUserId();
$user = $db->fetchOne("SELECT * FROM users WHERE id = $userId");

// Calculate gained, deposited, used
$gained = $db->fetchOne("SELECT SUM(amount) as total FROM transactions WHERE user_id = $userId AND type IN ('sale','resale') AND status = 'completed'")['total'] ?? 0;
$deposited = $db->fetchOne("SELECT SUM(amount) as total FROM transactions WHERE user_id = $userId AND type = 'deposit' AND status = 'completed'")['total'] ?? 0;
$used = $db->fetchOne("SELECT SUM(amount) as total FROM transactions WHERE user_id = $userId AND type = 'purchase' AND status = 'completed'")['total'] ?? 0;

// Transaction history
$transactions = $db->fetchAll("SELECT * FROM transactions WHERE user_id = $userId ORDER BY created_at DESC LIMIT 100");

// Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'finance_history_' . date('Ymd_His');
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Type', 'Amount', 'Status', 'Payment Method', 'Reference', 'Description', 'Date']);
    foreach ($transactions as $t) {
        fputcsv($out, [
            ucfirst($t['type']),
            $t['amount'],
            ucfirst($t['status']),
            $t['payment_method'] ?? '',
            $t['reference_id'] ?? '',
            $t['description'] ?? '',
            $t['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

include 'includes/header.php';
?>
<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6 max-w-3xl">
    <h1 class="text-2xl font-bold mb-4 text-gray-900">My Finances</h1>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
            <div class="text-xs text-gray-500 mb-1">Gained (Sales/Resale)</div>
            <div class="text-2xl font-bold text-green-700"><?php echo formatCurrency($gained); ?></div>
        </div>
        <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 text-center">
            <div class="text-xs text-gray-500 mb-1">Deposited</div>
            <div class="text-2xl font-bold text-indigo-700"><?php echo formatCurrency($deposited); ?></div>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
            <div class="text-xs text-gray-500 mb-1">Used (Purchases)</div>
            <div class="text-2xl font-bold text-red-700"><?php echo formatCurrency($used); ?></div>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-md overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td class="px-4 py-3 text-sm"><?php echo ucfirst($t['type']); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo formatCurrency($t['amount']); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo ucfirst($t['status']); ?></td>
                        <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($t['payment_method'] ?? ''); ?></td>
                        <td class="px-4 py-3 text-xs"><?php echo htmlspecialchars($t['reference_id'] ?? ''); ?></td>
                        <td class="px-4 py-3 text-xs"><?php echo htmlspecialchars($t['description'] ?? ''); ?></td>
                        <td class="px-4 py-3 text-xs"><?php echo formatDateTime($t['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>