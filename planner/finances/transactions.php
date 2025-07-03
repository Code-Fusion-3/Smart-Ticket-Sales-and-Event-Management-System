<?php
$pageTitle = "Transaction History";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has permission
checkPermission('event_planner');

// Get planner ID
$plannerId = getCurrentUserId();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filters
$type = isset($_GET['type']) ? $_GET['type'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$whereClause = "WHERE t.user_id = $plannerId";

if (!empty($type)) {
    $whereClause .= " AND t.type = '" . $db->escape($type) . "'";
}

if (!empty($status)) {
    $whereClause .= " AND t.status = '" . $db->escape($status) . "'";
}

if (!empty($dateFrom)) {
    $whereClause .= " AND t.created_at >= '" . $db->escape($dateFrom) . " 00:00:00'";
}

if (!empty($dateTo)) {
    $whereClause .= " AND t.created_at <= '" . $db->escape($dateTo) . " 23:59:59'";
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM transactions t $whereClause";
$countResult = $db->fetchOne($countSql);
$totalTransactions = $countResult['total'];
$totalPages = ceil($totalTransactions / $perPage);

// Get transactions
$sql = "SELECT t.*, e.title as event_title 
        FROM transactions t
        LEFT JOIN events e ON t.reference_id = e.id AND t.type IN ('purchase', 'sale')
        $whereClause
        ORDER BY t.created_at DESC
        LIMIT $offset, $perPage";
$transactions = $db->fetchAll($sql);

include '../../includes/planner_header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Transaction History</h1>
        <a href="index.php" class="text-indigo-600 hover:text-indigo-800">
            <i class="fas fa-arrow-left mr-2"></i> Back to Financial Dashboard
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-auto flex-grow">
                <label for="type" class="block text-gray-700 font-bold mb-2">Transaction Type</label>
                <select id="type" name="type"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                    <option value="">All Types</option>
                    <option value="sale" <?php echo $type == 'sale' ? 'selected' : ''; ?>>Sales</option>
                    <option value="withdrawal" <?php echo $type == 'withdrawal' ? 'selected' : ''; ?>>Withdrawals
                    </option>
                    <option value="system_fee" <?php echo $type == 'system_fee' ? 'selected' : ''; ?>>System Fees
                    </option>
                    <option value="deposit" <?php echo $type == 'deposit' ? 'selected' : ''; ?>>Deposits</option>
                </select>
            </div>

            <div class="w-full md:w-auto flex-grow">
                <label for="status" class="block text-gray-700 font-bold mb-2">Status</label>
                <select id="status" name="status"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="failed" <?php echo $status == 'failed' ? 'selected' : ''; ?>>Failed</option>
                </select>
            </div>

            <div class="w-full md:w-auto">
                <label for="date_from" class="block text-gray-700 font-bold mb-2">Date From</label>
                <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
            </div>

            <div class="w-full md:w-auto">
                <label for="date_to" class="block text-gray-700 font-bold mb-2">Date To</label>
                <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500">
            </div>

            <div>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
            </div>

            <?php if (!empty($type) || !empty($status) || !empty($dateFrom) || !empty($dateTo)): ?>
            <div>
                <a href="transactions.php" class="text-indigo-600 hover:text-indigo-800">
                    <i class="fas fa-times mr-1"></i> Clear Filters
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Transactions List -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <?php if (empty($transactions)): ?>
        <div class="p-6 text-center">
            <p class="text-gray-500">No transactions found.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th
                            class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date</th>
                        <th
                            class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Description</th>
                        <th
                            class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Type</th>
                        <th
                            class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Reference</th>
                        <th
                            class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Amount</th>
                        <th
                            class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo formatDate($transaction['created_at']); ?><br>
                            <span class="text-xs"><?php echo formatTime($transaction['created_at']); ?></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?php 
                                    if (!empty($transaction['description'])) {
                                        echo htmlspecialchars($transaction['description']);
                                    } elseif ($transaction['type'] == 'sale' && !empty($transaction['event_title'])) {
                                        echo 'Ticket sale for ' . htmlspecialchars($transaction['event_title']);
                                    } elseif ($transaction['type'] == 'withdrawal') {
                                        echo 'Withdrawal to ' . htmlspecialchars($transaction['payment_method'] ?? 'account');
                                    } elseif ($transaction['type'] == 'system_fee') {
                                        echo 'System fee';
                                    } else {
                                        echo ucfirst($transaction['type']);
                                    }
                                    ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php 
                                    $typeClasses = [
                                        'sale' => 'bg-green-100 text-green-800',
                                        'withdrawal' => 'bg-blue-100 text-blue-800',
                                        'system_fee' => 'bg-red-100 text-red-800',
                                        'deposit' => 'bg-purple-100 text-purple-800',
                                        'purchase' => 'bg-yellow-100 text-yellow-800',
                                        'resale' => 'bg-indigo-100 text-indigo-800'
                                    ];
                                    $typeClass = $typeClasses[$transaction['type']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                            <span
                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $typeClass; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo !empty($transaction['reference_id']) ? '#' . $transaction['reference_id'] : '-'; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <?php if (in_array($transaction['type'], ['sale', 'deposit'])): ?>
                            <span class="text-green-600">+<?php echo formatCurrency($transaction['amount']); ?></span>
                            <?php else: ?>
                            <span class="text-red-600">-<?php echo formatCurrency($transaction['amount']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php 
                                    $statusClasses = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'completed' => 'bg-green-100 text-green-800',
                                        'failed' => 'bg-red-100 text-red-800'
                                    ];
                                    $statusClass = $statusClasses[$transaction['status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                            <span
                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                <?php echo ucfirst($transaction['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 sm:px-6">
            <div class="flex items-center justify-between">
                <div class="flex-1 flex justify-between sm:hidden">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>"
                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                    </a>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>"
                        class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Next
                    </a>
                    <?php endif; ?>
                </div>

                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing <span class="font-medium"><?php echo ($offset + 1); ?></span> to
                            <span class="font-medium"><?php echo min($offset + $perPage, $totalTransactions); ?></span>
                            of
                            <span class="font-medium"><?php echo $totalTransactions; ?></span> transactions
                        </p>
                    </div>

                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>"
                                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Previous</span>
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>

                            <?php
                                    // Display page numbers
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $isCurrentPage = $i === $page;
                                        $pageClass = $isCurrentPage 
                                            ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600' 
                                            : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50';
                                            
                                        echo '<a href="?page=' . $i . '&type=' . urlencode($type) . '&status=' . urlencode($status) . '&date_from=' . urlencode($dateFrom) . '&date_to=' . urlencode($dateTo) . '" 
                                               class="relative inline-flex items-center px-4 py-2 border text-sm font-medium ' . $pageClass . '">
                                                ' . $i . '
                                              </a>';
                                    }
                                    ?>

                            <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&type=<?php echo urlencode($type); ?>&status=<?php echo urlencode($status); ?>&date_from=<?php echo urlencode($dateFrom); ?>&date_to=<?php echo urlencode($dateTo); ?>"
                                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                <span class="sr-only">Next</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php  ?>