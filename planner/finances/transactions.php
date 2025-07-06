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
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Filters
$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$minAmount = $_GET['min_amount'] ?? '';
$maxAmount = $_GET['max_amount'] ?? '';

// Build WHERE clause
$whereConditions = ["t.user_id = $plannerId"];

if (!empty($typeFilter)) {
    $whereConditions[] = "t.type = '" . $db->escape($typeFilter) . "'";
}

if (!empty($statusFilter)) {
    $whereConditions[] = "t.status = '" . $db->escape($statusFilter) . "'";
}

if (!empty($startDate)) {
    $whereConditions[] = "DATE(t.created_at) >= '" . $db->escape($startDate) . "'";
}

if (!empty($endDate)) {
    $whereConditions[] = "DATE(t.created_at) <= '" . $db->escape($endDate) . "'";
}

if (!empty($minAmount)) {
    $whereConditions[] = "t.amount >= " . (float) $minAmount;
}

if (!empty($maxAmount)) {
    $whereConditions[] = "t.amount <= " . (float) $maxAmount;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM transactions t $whereClause";
$totalResult = $db->fetchOne($countSql);
$totalRecords = $totalResult['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get transactions
$sql = "SELECT 
            t.*,
            e.title as event_title
        FROM transactions t
        LEFT JOIN tickets tk ON t.reference_id = tk.id AND t.type = 'purchase'
        LEFT JOIN events e ON tk.event_id = e.id OR (t.type = 'sale' AND t.description LIKE CONCAT('%', e.title, '%'))
        $whereClause
        ORDER BY t.created_at DESC
        LIMIT $offset, $perPage";
$transactions = $db->fetchAll($sql);

// Get transaction statistics
$statsSql = "SELECT 
                COUNT(*) as total_count,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_amount,
                SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                SUM(CASE WHEN status = 'failed' THEN amount ELSE 0 END) as failed_amount,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_count
             FROM transactions t
             $whereClause";
$stats = $db->fetchOne($statsSql);

include '../../includes/planner_header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Transaction History</h1>
        <div class="flex gap-2">
            <a href="index.php" class="text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
            </a>
            <?php
            // Build export parameters, excluding the 'type' parameter to avoid conflict
            $exportParams = $_GET;
            unset($exportParams['type']); // Remove the filter type parameter
            $exportParams['type_filter'] = $typeFilter; // Add as type_filter for export
            $exportParams['status_filter'] = $statusFilter; // Add as status_filter for export
            ?>
            <a href="export.php?type=transactions&<?php echo http_build_query($exportParams); ?>"
                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-download mr-2"></i> Export
            </a>
        </div>
    </div>

    <!-- Transaction Statistics -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-green-600"><?php echo formatCurrency($stats['completed_amount']); ?>
                </div>
                <div class="text-sm text-gray-500"><?php echo number_format($stats['completed_count']); ?> Completed
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-yellow-600"><?php echo formatCurrency($stats['pending_amount']); ?>
                </div>
                <div class="text-sm text-gray-500"><?php echo number_format($stats['pending_count']); ?> Pending</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-red-600"><?php echo formatCurrency($stats['failed_amount']); ?>
                </div>
                <div class="text-sm text-gray-500"><?php echo number_format($stats['failed_count']); ?> Failed</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Transaction Type</label>
                    <select id="type" name="type"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                        <option value="">All Types</option>
                        <option value="purchase" <?php echo $typeFilter === 'purchase' ? 'selected' : ''; ?>>Purchase
                        </option>
                        <option value="sale" <?php echo $typeFilter === 'sale' ? 'selected' : ''; ?>>Sale</option>
                        <option value="withdrawal" <?php echo $typeFilter === 'withdrawal' ? 'selected' : ''; ?>>
                            Withdrawal</option>
                        <option value="system_fee" <?php echo $typeFilter === 'system_fee' ? 'selected' : ''; ?>>System
                            Fee</option>
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                        <option value="">All Statuses</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>
                            Completed
                        </option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending
                        </option>
                        <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed
                        </option>
                    </select>
                </div>

                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date"
                        value="<?php echo htmlspecialchars($startDate); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                </div>

                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="min_amount" class="block text-sm font-medium text-gray-700 mb-1">Min Amount</label>
                    <input type="number" id="min_amount" name="min_amount"
                        value="<?php echo htmlspecialchars($minAmount); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm"
                        placeholder="0">
                </div>

                <div>
                    <label for="max_amount" class="block text-sm font-medium text-gray-700 mb-1">Max Amount</label>
                    <input type="number" id="max_amount" name="max_amount"
                        value="<?php echo htmlspecialchars($maxAmount); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm"
                        placeholder="1000000">
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
                <a href="transactions.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-times mr-2"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Transactions Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Reference</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                <i class="fas fa-receipt text-4xl text-gray-300 mb-4 block"></i>
                                No transactions found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo formatDateTime($transaction['created_at']); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($transaction['description'] ?? 'Transaction'); ?>
                                    </div>
                                    <?php if (!empty($transaction['event_title'])): ?>
                                        <div class="text-xs text-gray-500">
                                            Event: <?php echo htmlspecialchars($transaction['event_title']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                <?php
                                switch ($transaction['type']) {
                                    case 'sale':
                                        echo 'bg-green-100 text-green-800';
                                        break;
                                    case 'purchase':
                                        echo 'bg-blue-100 text-blue-800';
                                        break;
                                    case 'withdrawal':
                                        echo 'bg-yellow-100 text-yellow-800';
                                        break;
                                    case 'system_fee':
                                        echo 'bg-red-100 text-red-800';
                                        break;
                                    default:
                                        echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo formatCurrency($transaction['amount']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                <?php
                                switch ($transaction['status']) {
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
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo htmlspecialchars($transaction['reference_id'] ?? 'N/A'); ?>
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