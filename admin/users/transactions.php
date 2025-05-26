<?php
$pageTitle = "User Transactions";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($userId <= 0) {
    $_SESSION['error_message'] = "Invalid user ID";
    redirect('index.php');
}

// Get user details
$sql = "SELECT username, email FROM users WHERE id = $userId";
$user = $db->fetchOne($sql);

if (!$user) {
    $_SESSION['error_message'] = "User not found";
    redirect('index.php');
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$countSql = "SELECT COUNT(*) as total FROM transactions WHERE user_id = $userId";
$totalResult = $db->fetchOne($countSql);
$totalTransactions = $totalResult['total'];
$totalPages = ceil($totalTransactions / $perPage);

// Get transactions
$sql = "SELECT 
            id,
            amount,
            type,
            status,
            reference_id,
            payment_method,
            description,
            created_at
        FROM transactions 
        WHERE user_id = $userId 
        ORDER BY created_at DESC 
        LIMIT $offset, $perPage";
$transactions = $db->fetchAll($sql);

include '../../includes/admin_header.php';
?>

<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Transaction History</h1>
            <p class="text-gray-600 mt-2 text-sm sm:text-base">
                All transactions for <?php echo htmlspecialchars($user['username']); ?>
                (<?php echo htmlspecialchars($user['email']); ?>)
            </p>
        </div>
        <div class="flex gap-2">
            <a href="view.php?id=<?php echo $userId; ?>"
                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm sm:text-base">
                <i class="fas fa-arrow-left mr-2"></i>Back to User
            </a>
            <a href="index.php"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm sm:text-base">
                <i class="fas fa-users mr-2"></i>All Users
            </a>
        </div>
    </div>

    <!-- Transactions Summary -->
    <?php
    $summarySql = "SELECT 
                        SUM(CASE WHEN type IN ('deposit', 'sale') AND status = 'completed' THEN amount ELSE 0 END) as total_credits,
                        SUM(CASE WHEN type IN ('withdrawal', 'purchase', 'system_fee') AND status = 'completed' THEN amount ELSE 0 END) as total_debits,
                        COUNT(*) as total_count
                   FROM transactions 
                   WHERE user_id = $userId";
    $summary = $db->fetchOne($summarySql);
    ?>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-lg sm:text-2xl font-bold text-green-600">
                    +<?php echo formatCurrency($summary['total_credits'] ?? 0); ?></div>
                <div class="text-xs sm:text-sm text-gray-500">Total Credits</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-lg sm:text-2xl font-bold text-red-600">
                    -<?php echo formatCurrency($summary['total_debits'] ?? 0); ?></div>
                <div class="text-xs sm:text-sm text-gray-500">Total Debits</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-lg sm:text-2xl font-bold text-blue-600"><?php echo $summary['total_count']; ?></div>
                <div class="text-xs sm:text-sm text-gray-500">Total Transactions</div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden sm:table-cell">
                            Status</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">
                            Payment Method</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">
                            Reference</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            <i class="fas fa-receipt text-4xl text-gray-300 mb-4 block"></i>
                            No transactions found
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">#<?php echo $transaction['id']; ?></div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php 
                                        switch($transaction['type']) {
                                            case 'deposit':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'withdrawal':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            case 'purchase':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'sale':
                                                echo 'bg-purple-100 text-purple-800';
                                                break;
                                            case 'system_fee':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div
                                class="text-sm font-medium 
                                        <?php echo in_array($transaction['type'], ['deposit', 'sale']) ? 'text-green-600' : 'text-red-600'; ?>">
                                <?php echo in_array($transaction['type'], ['deposit', 'sale']) ? '+' : '-'; ?>
                                <?php echo formatCurrency($transaction['amount']); ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 hidden sm:table-cell">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php 
                                        switch($transaction['status']) {
                                            case 'completed':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'pending':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'failed':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                <?php echo ucfirst($transaction['status']); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <div class="text-sm text-gray-900">
                                <?php echo $transaction['payment_method'] ? ucfirst(str_replace('_', ' ', $transaction['payment_method'])) : 'N/A'; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <div class="text-sm text-gray-900 truncate max-w-32">
                                <?php echo $transaction['reference_id'] ?? 'N/A'; ?>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-sm text-gray-900">
                                <?php echo formatDateTime($transaction['created_at']); ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="mt-6 flex justify-center">
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                <span class="sr-only">Previous</span>
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                for ($i = $startPage; $i <= $endPage; $i++):
                    $isCurrentPage = $i === $page;
                    $pageClass = $isCurrentPage 
                        ? 'relative inline-flex items-center px-4 py-2 border border-indigo-500 bg-indigo-50 text-sm font-medium text-indigo-600'
                        : 'relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50';
                ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                class="<?php echo $pageClass; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                <span class="sr-only">Next</span>
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/admin_footer.php'; ?>