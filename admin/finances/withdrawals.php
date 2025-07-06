<?php
$pageTitle = "Withdrawal Management";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

// Handle withdrawal status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $withdrawalId = (int) $_POST['withdrawal_id'];
    $newStatus = $_POST['update_status'];
    $adminNotes = $_POST['admin_notes'] ?? '';

    if (in_array($newStatus, ['approved', 'rejected', 'completed'])) {
        // Get withdrawal details
        $sql = "SELECT * FROM withdrawals WHERE id = $withdrawalId";
        $withdrawal = $db->fetchOne($sql);

        if ($withdrawal) {
            // Update withdrawal status
            $sql = "UPDATE withdrawals SET 
                        status = '" . $db->escape($newStatus) . "',
                        admin_notes = '" . $db->escape($adminNotes) . "',
                        updated_at = NOW() 
                    WHERE id = $withdrawalId";

            if ($db->query($sql)) {
                // If rejected, refund the amount to user's balance
                if ($newStatus === 'rejected') {
                    $refundSql = "UPDATE users SET balance = balance + {$withdrawal['amount']} WHERE id = {$withdrawal['user_id']}";
                    $db->query($refundSql);

                    // Create a transaction record for the refund
                    $refundTransactionSql = "INSERT INTO transactions (user_id, amount, type, status, description) 
                                           VALUES ({$withdrawal['user_id']}, {$withdrawal['amount']}, 'deposit', 'completed', 'Withdrawal refund - Request #{$withdrawalId} rejected')";
                    $db->query($refundTransactionSql);
                }

                $_SESSION['success_message'] = "Withdrawal request updated successfully";
            } else {
                $_SESSION['error_message'] = "Failed to update withdrawal request";
            }
        }
    }
    redirect($_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
}

// Pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filters
$statusFilter = $_GET['status'] ?? '';
$userFilter = $_GET['user'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// Build WHERE clause
$whereConditions = [];
if (!empty($statusFilter)) {
    $whereConditions[] = "w.status = '" . $db->escape($statusFilter) . "'";
}
if (!empty($userFilter)) {
    $whereConditions[] = "(u.username LIKE '%" . $db->escape($userFilter) . "%' OR u.email LIKE '%" . $db->escape($userFilter) . "%')";
}
if (!empty($startDate)) {
    $whereConditions[] = "DATE(w.created_at) >= '" . $db->escape($startDate) . "'";
}
if (!empty($endDate)) {
    $whereConditions[] = "DATE(w.created_at) <= '" . $db->escape($endDate) . "'";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM withdrawals w JOIN users u ON w.user_id = u.id $whereClause";
$totalResult = $db->fetchOne($countSql);
$totalRecords = $totalResult['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get withdrawals
$sql = "SELECT 
            w.*,
            u.username,
            u.email,
            u.phone_number
        FROM withdrawals w
        JOIN users u ON w.user_id = u.id
        $whereClause
        ORDER BY w.created_at DESC
        LIMIT $offset, $perPage";
$withdrawals = $db->fetchAll($sql);

// Get withdrawal statistics
$statsSql = "SELECT 
                COUNT(*) as total_count,
                SUM(w.amount) as total_amount,
                COUNT(CASE WHEN w.status = 'pending' THEN 1 END) as pending_count,
                SUM(CASE WHEN w.status = 'pending' THEN w.amount ELSE 0 END) as pending_amount,
                COUNT(CASE WHEN w.status = 'approved' THEN 1 END) as approved_count,
                SUM(CASE WHEN w.status = 'approved' THEN w.amount ELSE 0 END) as approved_amount,
                COUNT(CASE WHEN w.status = 'completed' THEN 1 END) as completed_count,
                SUM(CASE WHEN w.status = 'completed' THEN w.amount ELSE 0 END) as completed_amount,
                COUNT(CASE WHEN w.status = 'rejected' THEN 1 END) as rejected_count,
                SUM(CASE WHEN w.status = 'rejected' THEN w.amount ELSE 0 END) as rejected_amount
             FROM withdrawals w
             JOIN users u ON w.user_id = u.id
             $whereClause";
$stats = $db->fetchOne($statsSql);

include '../../includes/admin_header.php';
?>

<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Withdrawal Management</h1>
            <p class="text-gray-600 mt-2 text-sm sm:text-base">Review and process withdrawal requests</p>
        </div>
        <div class="flex gap-2">
            <a href="index.php"
                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm">
                <i class="fas fa-arrow-left mr-2"></i>Financial Overview
            </a>
            <a href="export.php?type=withdrawals&<?php echo http_build_query($_GET); ?>"
                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm">
                <i class="fas fa-download mr-2"></i>Export
            </a>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 alert-auto-hide">
        <?php echo $_SESSION['success_message'];
            unset($_SESSION['success_message']); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 alert-auto-hide">
        <?php echo $_SESSION['error_message'];
            unset($_SESSION['error_message']); ?>
    </div>
    <?php endif; ?>

    <!-- Withdrawal Statistics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-yellow-600"><?php echo formatCurrency($stats['pending_amount']); ?>
                </div>
                <div class="text-sm text-gray-500"><?php echo number_format($stats['pending_count']); ?> Pending</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-600"><?php echo formatCurrency($stats['approved_amount']); ?>
                </div>
                <div class="text-sm text-gray-500"><?php echo number_format($stats['approved_count']); ?> Approved</div>
            </div>
        </div>

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
                <div class="text-2xl font-bold text-red-600"><?php echo formatCurrency($stats['rejected_amount']); ?>
                </div>
                <div class="text-sm text-gray-500"><?php echo number_format($stats['rejected_count']); ?> Rejected</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <form method="GET" action="" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Status Filter -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending
                        </option>
                        <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved
                        </option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>
                            Completed</option>
                        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected
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

                <!-- Start Date -->
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date"
                        value="<?php echo htmlspecialchars($startDate); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                </div>

                <!-- End Date -->
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm">
                    <i class="fas fa-filter mr-1"></i>Filter
                </button>
                <a href="withdrawals.php"
                    class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm">
                    <i class="fas fa-times mr-1"></i>Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Withdrawals Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden lg:table-cell">
                            Payment Method</th>
                        <th
                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">
                            Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($withdrawals)): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            <i class="fas fa-money-bill-wave text-4xl text-gray-300 mb-4"></i>
                            <div>No withdrawal requests found</div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($withdrawals as $withdrawal): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">#<?php echo $withdrawal['id']; ?></div>
                        </td>

                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">
                                <?php echo htmlspecialchars($withdrawal['username']); ?>
                            </div>
                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($withdrawal['email']); ?>
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <div class="text-sm font-medium text-gray-900">
                                <?php echo formatCurrency($withdrawal['amount']); ?>
                            </div>
                            <div class="text-xs text-gray-500">Fee: <?php echo formatCurrency($withdrawal['fee']); ?>
                            </div>
                            <div class="text-xs text-green-600">Net:
                                <?php echo formatCurrency($withdrawal['net_amount']); ?>
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php
                                        switch ($withdrawal['status']) {
                                            case 'pending':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'approved':
                                                echo 'bg-blue-100 text-blue-800';
                                                break;
                                            case 'completed':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'rejected':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                <?php echo ucfirst($withdrawal['status']); ?>
                            </span>
                        </td>

                        <td class="px-4 py-3 hidden lg:table-cell">
                            <div class="text-sm text-gray-900">
                                <?php echo ucfirst(str_replace('_', ' ', $withdrawal['payment_method'])); ?>
                            </div>
                        </td>

                        <td class="px-4 py-3 hidden sm:table-cell">
                            <div class="text-sm text-gray-900"><?php echo formatDateTime($withdrawal['created_at']); ?>
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex items-center space-x-2">
                                <!-- View Details -->
                                <button onclick="viewWithdrawal(<?php echo $withdrawal['id']; ?>)"
                                    class="text-indigo-600 hover:text-indigo-900 text-sm" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>

                                <!-- Status Update Dropdown -->
                                <?php if ($withdrawal['status'] === 'pending'): ?>
                                <div class="relative inline-block text-left">
                                    <button type="button"
                                        class="text-gray-600 hover:text-gray-900 text-sm dropdown-toggle"
                                        onclick="toggleDropdown('status-dropdown-<?php echo $withdrawal['id']; ?>')">
                                        <i class="fas fa-cog"></i>
                                    </button>

                                    <div id="status-dropdown-<?php echo $withdrawal['id']; ?>"
                                        class="dropdown-menu hidden absolute right-0 z-10 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5">
                                        <div class="py-1">
                                            <button
                                                onclick="updateWithdrawalStatus(<?php echo $withdrawal['id']; ?>, 'approved')"
                                                class="block w-full text-left px-4 py-2 text-sm text-blue-700 hover:bg-blue-50">
                                                <i class="fas fa-check mr-2"></i>Approve
                                            </button>

                                            <button
                                                onclick="updateWithdrawalStatus(<?php echo $withdrawal['id']; ?>, 'rejected')"
                                                class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-red-50">
                                                <i class="fas fa-times mr-2"></i>Reject
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php elseif ($withdrawal['status'] === 'approved'): ?>
                                <button onclick="updateWithdrawalStatus(<?php echo $withdrawal['id']; ?>, 'completed')"
                                    class="text-green-600 hover:text-green-900 text-sm" title="Mark as Completed">
                                    <i class="fas fa-check-circle"></i>
                                </button>
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
        of <?php echo number_format($totalRecords); ?> withdrawal requests
    </div>
    <?php endif; ?>
</div>

<!-- Withdrawal Details Modal -->
<div id="withdrawalModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900" id="modalTitle">Withdrawal Details</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="modalContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-1/2 shadow-lg rounded-md bg-white">
        <form method="POST" id="statusForm">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900" id="statusModalTitle">Update Withdrawal Status</h3>
                    <button type="button" onclick="closeStatusModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <input type="hidden" name="withdrawal_id" id="statusWithdrawalId">
                <input type="hidden" name="update_status" id="statusValue">

                <div class="mb-4">
                    <label for="admin_notes" class="block text-sm font-medium text-gray-700 mb-2">Admin Notes
                        (Optional)</label>
                    <textarea name="admin_notes" id="admin_notes" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                        placeholder="Add any notes about this status change..."></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeStatusModal()"
                        class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        Cancel
                    </button>
                    <button type="submit" id="statusSubmitBtn"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                        Update Status
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
// View withdrawal details
function viewWithdrawal(withdrawalId) {
    fetch(`withdrawal_details.php?id=${withdrawalId}&ajax=1`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('modalContent').innerHTML = data;
            document.getElementById('withdrawalModal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load withdrawal details');
        });
}

// Update withdrawal status
function updateWithdrawalStatus(withdrawalId, status) {
    document.getElementById('statusWithdrawalId').value = withdrawalId;
    document.getElementById('statusValue').value = status;

    const statusText = status.charAt(0).toUpperCase() + status.slice(1);
    document.getElementById('statusModalTitle').textContent = `${statusText} Withdrawal Request`;
    document.getElementById('statusSubmitBtn').textContent = `${statusText} Request`;

    // Change button color based on action
    const submitBtn = document.getElementById('statusSubmitBtn');
    submitBtn.className = 'font-bold py-2 px-4 rounded text-white ';

    if (status === 'approved') {
        submitBtn.className += 'bg-blue-600 hover:bg-blue-700';
    } else if (status === 'rejected') {
        submitBtn.className += 'bg-red-600 hover:bg-red-700';
    } else if (status === 'completed') {
        submitBtn.className += 'bg-green-600 hover:bg-green-700';
    } else {
        submitBtn.className += 'bg-indigo-600 hover:bg-indigo-700';
    }

    document.getElementById('statusModal').classList.remove('hidden');
}

// Close modals
function closeModal() {
    document.getElementById('withdrawalModal').classList.add('hidden');
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.add('hidden');
    document.getElementById('admin_notes').value = '';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const withdrawalModal = document.getElementById('withdrawalModal');
    const statusModal = document.getElementById('statusModal');

    if (event.target === withdrawalModal) {
        closeModal();
    }
    if (event.target === statusModal) {
        closeStatusModal();
    }
}
</script>

<?php include '../../includes/admin_footer.php'; ?>