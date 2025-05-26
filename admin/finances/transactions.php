<?php
$pageTitle = "Transaction Management";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Filters
$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$userFilter = $_GET['user'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$minAmount = $_GET['min_amount'] ?? '';
$maxAmount = $_GET['max_amount'] ?? '';

// Build WHERE clause
$whereConditions = [];
$params = [];

if (!empty($typeFilter)) {
    $whereConditions[] = "t.type = ?";
    $params[] = $typeFilter;
}

if (!empty($statusFilter)) {
    $whereConditions[] = "t.status = ?";
    $params[] = $statusFilter;
}

if (!empty($userFilter)) {
    $whereConditions[] = "(u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$userFilter%";
    $params[] = "%$userFilter%";
}

if (!empty($startDate)) {
    $whereConditions[] = "DATE(t.created_at) >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $whereConditions[] = "DATE(t.created_at) <= ?";
    $params[] = $endDate;
}

if (!empty($minAmount)) {
    $whereConditions[] = "t.amount >= ?";
    $params[] = $minAmount;
}

if (!empty($maxAmount)) {
    $whereConditions[] = "t.amount <= ?";
    $params[] = $maxAmount;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total 
             FROM transactions t 
             JOIN users u ON t.user_id = u.id 
             $whereClause";
$totalResult = $db->fetchOne($countSql);
$totalRecords = $totalResult['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get transactions
$sql = "SELECT 
            t.*,
            u.username,
            u.email,
            e.title as event_title,
            ep.username as planner_name
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN tickets tk ON t.reference_id = tk.id AND t.type = 'purchase'
        LEFT JOIN events e ON tk.event_id = e.id
        LEFT JOIN users ep ON e.planner_id = ep.id
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
             JOIN users u ON t.user_id = u.id
             $whereClause";
$stats = $db->fetchOne($statsSql);

include '../../includes/admin_header.php';
?>

<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Transaction Management</h1>
            <p class="text-gray-600 mt-2 text-sm sm:text-base">Monitor and manage all platform transactions</p>
        </div>
        <div class="flex gap-2">
            <a href="index.php"
                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm">
                <i class="fas fa-arrow-left mr-2"></i>Financial Overview
            </a>
            <a href="export.php?type=transactions&<?php echo http_build_query($_GET); ?>"
                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm">
                <i class="fas fa-download mr-2"></i>Export
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
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <form method="GET" action="" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Transaction Type -->
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Transaction Type</label>
                    <select id="type" name="type"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                        <option value="">All Types</option>
                        <option value="purchase" <?php echo $typeFilter === 'purchase' ? 'selected' : ''; ?>>Purchase
                        </option>
                        <option value="deposit" <?php echo $typeFilter === 'deposit' ? 'selected' : ''; ?>>Deposit
                        </option>
                        <option value="withdrawal" <?php echo $typeFilter === 'withdrawal' ? 'selected' : ''; ?>>
                            Withdrawal</option>
                        <option value="system_fee" <?php echo $typeFilter === 'system_fee' ? 'selected' : ''; ?>>System
                            Fee</option>
                        <option value="sale" <?php echo $typeFilter === 'sale' ? 'selected' : ''; ?>>Sale</option>
                        <option value="resale" <?php echo $typeFilter === 'resale' ? 'selected' : ''; ?>>Resale</option>
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                        <option value="">All Statuses</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>
                            Completed</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending
                        </option>
                        <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed
                        </option>
                    </select>
                </div>

                <!-- User Search -->
                <div>
                    <label for="user" class="block text-sm font-medium text-gray-700 mb-1">User</label>
                    <input type="text" id="user" name="user" value="<?php echo htmlspecialchars($userFilter); ?>"
                        placeholder="Username or email"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                </div>

                <!-- Amount Range -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount Range</label>
                    <div class="flex gap-2">
                        <input type="number" name="min_amount" value="<?php echo htmlspecialchars($minAmount); ?>"
                            placeholder="Min"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                        <input type="number" name="max_amount" value="<?php echo htmlspecialchars($maxAmount); ?>"
                            placeholder="Max"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Date Range -->
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

                <!-- Filter Actions -->
                <div class="flex items-end gap-2">
                    <button type="submit"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm">
                        <i class="fas fa-filter mr-1"></i>Filter
                    </button>
                </div>

                <div class="flex items-end">
                    <a href="transactions.php"
                        class="w-full bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm text-center">
                        <i class="fas fa-times mr-1"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Transactions Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">
                            Event/Reference</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">
                            Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                            <i class="fas fa-receipt text-4xl text-gray-300 mb-4"></i>
                            <div>No transactions found</div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">#<?php echo $transaction['id']; ?></div>
                        </td>

                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($transaction['username']); ?></div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($transaction['email']); ?>
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php 
                                        switch($transaction['type']) {
                                            case 'purchase':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'deposit':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'withdrawal':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                                                                       case 'system_fee':
                                                echo 'bg-purple-100 text-purple-800';
                                                break;
                                            case 'sale':
                                                echo 'bg-indigo-100 text-indigo-800';
                                                break;
                                            case 'resale':
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
                            <div class="text-sm font-medium text-gray-900">
                                <?php echo formatCurrency($transaction['amount']); ?></div>
                            <?php if (!empty($transaction['payment_method'])): ?>
                            <div class="text-xs text-gray-500">
                                <?php echo ucfirst(str_replace('_', ' ', $transaction['payment_method'])); ?></div>
                            <?php endif; ?>
                        </td>

                        <td class="px-4 py-3">
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
                            <?php if (!empty($transaction['event_title'])): ?>
                            <div class="text-sm text-gray-900">
                                <?php echo htmlspecialchars($transaction['event_title']); ?></div>
                            <div class="text-xs text-gray-500">by
                                <?php echo htmlspecialchars($transaction['planner_name']); ?></div>
                            <?php elseif (!empty($transaction['reference_id'])): ?>
                            <div class="text-sm text-gray-500">Ref:
                                <?php echo htmlspecialchars($transaction['reference_id']); ?></div>
                            <?php else: ?>
                            <div class="text-sm text-gray-400">-</div>
                            <?php endif; ?>
                        </td>

                        <td class="px-4 py-3 hidden sm:table-cell">
                            <div class="text-sm text-gray-900"><?php echo formatDateTime($transaction['created_at']); ?>
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex items-center space-x-2">
                                <a href="transaction_details.php?id=<?php echo $transaction['id']; ?>"
                                    class="text-indigo-600 hover:text-indigo-900 text-sm" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>

                                <?php if ($transaction['status'] === 'pending'): ?>
                                <a href="update_transaction.php?id=<?php echo $transaction['id']; ?>&action=complete&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                                    class="text-green-600 hover:text-green-900 text-sm" title="Mark as Completed"
                                    onclick="return confirm('Mark this transaction as completed?')">
                                    <i class="fas fa-check"></i>
                                </a>

                                <a href="update_transaction.php?id=<?php echo $transaction['id']; ?>&action=fail&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                                    class="text-red-600 hover:text-red-900 text-sm" title="Mark as Failed"
                                    onclick="return confirm('Mark this transaction as failed?')">
                                    <i class="fas fa-times"></i>
                                </a>
                                <?php endif; ?>
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
                // Display page numbers
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                // Always show first page
                if ($startPage > 1) {
                    echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            1
                          </a>';
                    
                    if ($startPage > 2) {
                        echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                ...
                              </span>';
                    }
                }
                
                // Page numbers
                for ($i = $startPage; $i <= $endPage; $i++) {
                    $isCurrentPage = $i === $page;
                    $pageClass = $isCurrentPage 
                        ? 'relative inline-flex items-center px-4 py-2 border border-indigo-500 bg-indigo-50 text-sm font-medium text-indigo-600'
                        : 'relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50';
                    
                    if ($isCurrentPage) {
                        echo '<span class="' . $pageClass . '">' . $i . '</span>';
                    } else {
                        echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '" class="' . $pageClass . '">' . $i . '</a>';
                    }
                }
                
                // Always show last page
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                                ...
                              </span>';
                    }
                    
                    echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $totalPages])) . '" 
                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                            ' . $totalPages . '
                          </a>';
                }
                ?>

            <?php if ($page < $totalPages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                <span class="sr-only">Next</span>
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Pagination Info -->
    <div class="mt-4 text-center text-sm text-gray-600">
        Showing <?php echo number_format($offset + 1); ?> to
        <?php echo number_format(min($offset + $perPage, $totalRecords)); ?>
        of <?php echo number_format($totalRecords); ?> transactions
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/admin_footer.php'; ?>