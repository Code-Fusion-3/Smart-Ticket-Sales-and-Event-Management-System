<?php
$pageTitle = "Transaction Details";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

$transactionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($transactionId <= 0) {
    $_SESSION['error_message'] = "Invalid transaction ID";
    redirect('transactions.php');
}

// Get transaction details
$sql = "SELECT 
            t.*,
            u.username,
            u.email,
            u.phone_number,
            e.title as event_title,
            e.id as event_id,
            ep.username as planner_name,
            tk.id as ticket_id,
            tk.qr_code
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN tickets tk ON t.reference_id = tk.id AND t.type = 'purchase'
        LEFT JOIN events e ON tk.event_id = e.id
        LEFT JOIN users ep ON e.planner_id = ep.id
        WHERE t.id = $transactionId";

$transaction = $db->fetchOne($sql);

if (!$transaction) {
    $_SESSION['error_message'] = "Transaction not found";
    redirect('transactions.php');
}

include '../../includes/admin_header.php';
?>

<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Transaction Details</h1>
            <p class="text-gray-600 mt-2 text-sm sm:text-base">Transaction ID: #<?php echo $transaction['id']; ?></p>
        </div>
        <div class="flex gap-2">
            <a href="transactions.php"
                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm">
                <i class="fas fa-arrow-left mr-2"></i>Back to Transactions
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Transaction Information -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Transaction Information</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Transaction ID</label>
                        <div class="text-lg font-mono">#<?php echo $transaction['id']; ?></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
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
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
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
                                default:
                                    echo 'bg-gray-100 text-gray-800';
                            }
                            ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?>
                        </span>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                        <div class="text-2xl font-bold text-green-600">
                            <?php echo formatCurrency($transaction['amount']); ?></div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <div class="text-gray-900">
                            <?php echo !empty($transaction['payment_method']) ? ucfirst(str_replace('_', ' ', $transaction['payment_method'])) : 'N/A'; ?>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reference ID</label>
                        <div class="text-gray-900 font-mono">
                            <?php echo !empty($transaction['reference_id']) ? htmlspecialchars($transaction['reference_id']) : 'N/A'; ?>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Created Date</label>
                        <div class="text-gray-900"><?php echo formatDateTime($transaction['created_at']); ?></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Updated Date</label>
                        <div class="text-gray-900"><?php echo formatDateTime($transaction['updated_at']); ?></div>
                    </div>
                </div>

                <?php if (!empty($transaction['description'])): ?>
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <div class="text-gray-900 bg-gray-50 p-3 rounded-lg">
                        <?php echo nl2br(htmlspecialchars($transaction['description'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- User Information -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">User Information</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <div class="text-gray-900"><?php echo htmlspecialchars($transaction['username']); ?></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <div class="text-gray-900"><?php echo htmlspecialchars($transaction['email']); ?></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <div class="text-gray-900"><?php echo htmlspecialchars($transaction['phone_number']); ?></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">User ID</label>
                        <div class="text-gray-900">#<?php echo $transaction['user_id']; ?></div>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="../users/view.php?id=<?php echo $transaction['user_id']; ?>"
                        class="text-indigo-600 hover:text-indigo-800 text-sm">
                        <i class="fas fa-user mr-1"></i>View User Profile →
                    </a>
                </div>
            </div>

            <!-- Event Information (if applicable) -->
            <?php if (!empty($transaction['event_title'])): ?>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Event Information</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Event Title</label>
                        <div class="text-gray-900"><?php echo htmlspecialchars($transaction['event_title']); ?></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Event Planner</label>
                        <div class="text-gray-900"><?php echo htmlspecialchars($transaction['planner_name']); ?></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Event ID</label>
                        <div class="text-gray-900">#<?php echo $transaction['event_id']; ?></div>
                    </div>

                    <?php if (!empty($transaction['ticket_id'])): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ticket ID</label>
                        <div class="text-gray-900">#<?php echo $transaction['ticket_id']; ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mt-4 flex gap-2">
                    <a href="../events/view.php?id=<?php echo $transaction['event_id']; ?>"
                        class="text-indigo-600 hover:text-indigo-800 text-sm">
                        <i class="fas fa-calendar mr-1"></i>View Event →
                    </a>

                    <?php if (!empty($transaction['ticket_id'])): ?>
                    <a href="../tickets/view.php?id=<?php echo $transaction['ticket_id']; ?>"
                        class="text-indigo-600 hover:text-indigo-800 text-sm">
                        <i class="fas fa-ticket-alt mr-1"></i>View Ticket →
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Actions Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Actions</h3>

                <div class="space-y-3">
                    <?php if ($transaction['status'] === 'pending'): ?>
                    <a href="update_transaction.php?id=<?php echo $transaction['id']; ?>&action=complete&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 block text-center"
                        onclick="return confirm('Mark this transaction as completed?')">
                        <i class="fas fa-check mr-2"></i>Mark as Completed
                    </a>

                    <a href="update_transaction.php?id=<?php echo $transaction['id']; ?>&action=fail&return=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                        class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 block text-center"
                        onclick="return confirm('Mark this transaction as failed?')">
                        <i class="fas fa-times mr-2"></i>Mark as Failed
                    </a>
                    <?php endif; ?>

                    <a href="export.php?type=transaction&id=<?php echo $transaction['id']; ?>"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 block text-center">
                        <i class="fas fa-download mr-2"></i>Export Details
                    </a>

                    <a href="mailto:<?php echo htmlspecialchars($transaction['email']); ?>?subject=Transaction%20%23<?php echo $transaction['id']; ?>"
                        class="w-full bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 block text-center">
                        <i class="fas fa-envelope mr-2"></i>Contact User
                    </a>
                </div>
            </div>

            <!-- Transaction Timeline -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Transaction Timeline</h3>

                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-plus text-blue-600 text-sm"></i>
                        </div>
                        <div class="ml-3">
                            <div class="text-sm font-medium text-gray-900">Transaction Created</div>
                            <div class="text-xs text-gray-500"><?php echo formatDateTime($transaction['created_at']); ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($transaction['created_at'] !== $transaction['updated_at']): ?>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-edit text-yellow-600 text-sm"></i>
                        </div>
                        <div class="ml-3">
                            <div class="text-sm font-medium text-gray-900">Last Updated</div>
                            <div class="text-xs text-gray-500"><?php echo formatDateTime($transaction['updated_at']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($transaction['status'] === 'completed'): ?>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check text-green-600 text-sm"></i>
                        </div>
                        <div class="ml-3">
                            <div class="text-sm font-medium text-gray-900">Transaction Completed</div>
                            <div class="text-xs text-gray-500">Successfully processed</div>
                        </div>
                    </div>
                    <?php elseif ($transaction['status'] === 'failed'): ?>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-times text-red-600 text-sm"></i>
                        </div>
                        <div class="ml-3">
                            <div class="text-sm font-medium text-gray-900">Transaction Failed</div>
                            <div class="text-xs text-gray-500">Processing failed</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-clock text-yellow-600 text-sm"></i>
                        </div>
                        <div class="ml-3">
                            <div class="text-sm font-medium text-gray-900">Pending Processing</div>
                            <div class="text-xs text-gray-500">Awaiting completion</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/admin_footer.php'; ?>