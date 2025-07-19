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

// Calculate gained, deposited, used, and withdrawn
$gained = $db->fetchOne("SELECT SUM(amount) as total FROM transactions WHERE user_id = $userId AND type IN ('sale','resale') AND status = 'completed'")['total'] ?? 0;
$deposited = $db->fetchOne("SELECT SUM(amount) as total FROM transactions WHERE user_id = $userId AND type = 'deposit' AND status = 'completed'")['total'] ?? 0;
$used = $db->fetchOne("SELECT SUM(amount) as total FROM transactions WHERE user_id = $userId AND type = 'purchase' AND status = 'completed'")['total'] ?? 0;
$withdrawn = $db->fetchOne("SELECT SUM(amount) as total FROM transactions WHERE user_id = $userId AND type = 'withdrawal' AND status = 'completed'")['total'] ?? 0;

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
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
        <h1 class="text-2xl font-bold text-gray-900">My Finances</h1>
        <a href="withdraw.php"
            class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded shadow transition duration-200">
            <i class="fas fa-money-bill-wave mr-2"></i> Withdraw
        </a>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
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
        <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 text-center">
            <div class="text-xs text-gray-500 mb-1">Withdrawn</div>
            <div class="text-2xl font-bold text-orange-700"><?php echo formatCurrency($withdrawn); ?></div>
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
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($transactions as $t): ?>
                <tr class="hover:bg-indigo-50 transition">
                    <td class="px-4 py-3 text-sm">
                        <?php
                            $type = strtolower($t['type']);
                            $typeColors = [
                                'deposit' => 'bg-blue-100 text-blue-800',
                                'withdrawal' => 'bg-green-100 text-green-800',
                                'sale' => 'bg-yellow-100 text-yellow-800',
                                'resale' => 'bg-purple-100 text-purple-800',
                                'purchase' => 'bg-red-100 text-red-800',
                                'system_fee' => 'bg-gray-100 text-gray-800',
                            ];
                            $typeClass = $typeColors[$type] ?? 'bg-gray-100 text-gray-800';
                            ?>
                        <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $typeClass; ?>"
                            title="<?php echo ucfirst($type); ?>">
                            <?php echo ucfirst($type); ?>
                        </span>
                    </td>
                    <td
                        class="px-4 py-3 text-sm font-bold <?php echo ($type === 'withdrawal') ? 'text-green-700' : (($type === 'deposit') ? 'text-blue-700' : 'text-gray-900'); ?>">
                        <?php echo formatCurrency($t['amount']); ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?php
                            $status = strtolower($t['status']);
                            $statusColors = [
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'completed' => 'bg-green-100 text-green-800',
                                'approved' => 'bg-blue-100 text-blue-800',
                                'rejected' => 'bg-red-100 text-red-800',
                                'failed' => 'bg-red-100 text-red-800',
                            ];
                            $statusClass = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
                            ?>
                        <span class="px-2 py-1 rounded-full text-xs font-semibold <?php echo $statusClass; ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm"><?php echo htmlspecialchars($t['payment_method'] ?? ''); ?></td>
                    <td class="px-4 py-3 text-xs">
                        <?php echo htmlspecialchars($t['description'] ?? ''); ?>
                        <?php if ($type === 'withdrawal'): ?>
                        <span class="ml-1 text-green-600" title="This is a withdrawal transaction."><i
                                class="fas fa-arrow-up"></i></span>
                        <?php elseif ($type === 'deposit'): ?>
                        <span class="ml-1 text-blue-600" title="This is a deposit transaction."><i
                                class="fas fa-arrow-down"></i></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-xs"><?php echo formatDateTime($t['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>