<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

$withdrawalId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if ($withdrawalId <= 0) {
    if ($isAjax) {
        echo '<div class="text-red-600">Invalid withdrawal ID</div>';
        exit;
    } else {
        $_SESSION['error_message'] = "Invalid withdrawal ID";
        redirect('withdrawals.php');
    }
}

// Get withdrawal details
$sql = "SELECT 
            w.*,
            u.username,
            u.email,
            u.phone_number,
            u.balance
        FROM withdrawals w
        JOIN users u ON w.user_id = u.id
        WHERE w.id = $withdrawalId";

$withdrawal = $db->fetchOne($sql);

if (!$withdrawal) {
    if ($isAjax) {
        echo '<div class="text-red-600">Withdrawal not found</div>';
        exit;
    } else {
        $_SESSION['error_message'] = "Withdrawal not found";
        redirect('withdrawals.php');
    }
}

if ($isAjax) {
    // Return HTML for modal content
    ?>
<div class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Withdrawal ID</label>
            <div class="text-lg font-mono">#<?php echo $withdrawal['id']; ?></div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                    <?php 
                    switch($withdrawal['status']) {
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
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">User</label>
            <div class="text-gray-900"><?php echo htmlspecialchars($withdrawal['username']); ?></div>
            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($withdrawal['email']); ?></div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Current Balance</label>
            <div class="text-lg font-bold text-green-600"><?php echo formatCurrency($withdrawal['balance']); ?></div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Withdrawal Amount</label>
            <div class="text-lg font-bold text-red-600"><?php echo formatCurrency($withdrawal['amount']); ?></div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Processing Fee</label>
            <div class="text-gray-900"><?php echo formatCurrency($withdrawal['fee']); ?></div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Net Amount</label>
            <div class="text-lg font-bold text-green-600"><?php echo formatCurrency($withdrawal['net_amount']); ?></div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Payment Method</label>
            <div class="text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $withdrawal['payment_method'])); ?>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Request Date</label>
            <div class="text-gray-900"><?php echo formatDateTime($withdrawal['created_at']); ?></div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700">Last Updated</label>
            <div class="text-gray-900"><?php echo formatDateTime($withdrawal['updated_at']); ?></div>
        </div>
    </div>

    <?php if (!empty($withdrawal['payment_details'])): ?>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Details</label>
        <div class="bg-gray-50 p-3 rounded-lg">
            <?php echo nl2br(htmlspecialchars($withdrawal['payment_details'])); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($withdrawal['admin_notes'])): ?>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Notes</label>
        <div class="bg-blue-50 p-3 rounded-lg">
            <?php echo nl2br(htmlspecialchars($withdrawal['admin_notes'])); ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex justify-end space-x-3 pt-4 border-t">
        <a href="../users/view.php?id=<?php echo $withdrawal['user_id']; ?>"
            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded" target="_blank">
            <i class="fas fa-user mr-2"></i>View User
        </a>

        <?php if ($withdrawal['status'] === 'pending'): ?>
        <button onclick="closeModal(); updateWithdrawalStatus(<?php echo $withdrawal['id']; ?>, 'approved')"
            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-check mr-2"></i>Approve
        </button>

        <button onclick="closeModal(); updateWithdrawalStatus(<?php echo $withdrawal['id']; ?>, 'rejected')"
            class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-times mr-2"></i>Reject
        </button>
        <?php elseif ($withdrawal['status'] === 'approved'): ?>
        <button onclick="closeModal(); updateWithdrawalStatus(<?php echo $withdrawal['id']; ?>, 'completed')"
            class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-check-circle mr-2"></i>Mark Completed
        </button>
        <?php endif; ?>
    </div>
</div>
<?php
    exit;
}

// If not AJAX, include full page layout
$pageTitle = "Withdrawal Details";
include '../../includes/admin_header.php';
?>

<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Withdrawal Details</h1>
            <p class="text-gray-600 mt-2 text-sm sm:text-base">Withdrawal ID: #<?php echo $withdrawal['id']; ?></p>
        </div>
        <div class="flex gap-2">
            <a href="withdrawals.php"
                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm">
                <i class="fas fa-arrow-left mr-2"></i>Back to Withdrawals
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Withdrawal Information -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Withdrawal Information</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Withdrawal ID</label>
                        <div class="text-lg font-mono">#<?php echo $withdrawal['id']; ?></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                            <?php 
                            switch($withdrawal['status']) {
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
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Withdrawal Amount</label>
                        <div class="text-2xl font-bold text-red-600">
                            <?php echo formatCurrency($withdrawal['amount']); ?></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Processing Fee</label>
                        <div class="text-lg text-gray-900"><?php echo formatCurrency($withdrawal['fee']); ?></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Net Amount</label>
                        <div class="text-2xl font-bold text-green-600">
                            <?php echo formatCurrency($withdrawal['net_amount']); ?></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <div class="text-gray-900">
                            <?php echo ucfirst(str_replace('_', ' ', $withdrawal['payment_method'])); ?></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Request Date</label>
                        <div class="text-gray-900"><?php echo formatDateTime($withdrawal['created_at']); ?></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Updated</label>
                        <div class="text-gray-900"><?php echo formatDateTime($withdrawal['updated_at']); ?></div>
                    </div>
                </div>

                <?php if (!empty($withdrawal['payment_details'])): ?>
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Payment Details</label>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <?php echo nl2br(htmlspecialchars($withdrawal['payment_details'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($withdrawal['admin_notes'])): ?>
                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Admin Notes</label>
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <?php echo nl2br(htmlspecialchars($withdrawal['admin_notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- User Information -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">User Information</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <div class="text-gray-900"><?php echo htmlspecialchars($withdrawal['username']); ?></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <div class="text-gray-900"><?php echo htmlspecialchars($withdrawal['email']); ?></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <div class="text-gray-900"><?php echo htmlspecialchars($withdrawal['phone_number']); ?></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Balance</label>
                        <div class="text-lg font-bold text-green-600">
                            <?php echo formatCurrency($withdrawal['balance']); ?></div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">User ID</label>
                        <div class="text-gray-900">#<?php echo $withdrawal['user_id']; ?></div>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="../users/view.php?id=<?php echo $withdrawal['user_id']; ?>"
                        class="text-indigo-600 hover:text-indigo-800 text-sm">
                        <i class="fas fa-user mr-1"></i>View User Profile →
                    </a>
                </div>
            </div>
        </div>

        <!-- Actions Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h3 class="text-lg font-semibold mb-4">Actions</h3>

                <div class="space-y-3">
                    <?php if ($withdrawal['status'] === 'pending'): ?>
                    <form method="POST" action="withdrawals.php" class="space-y-3">
                        <input type="hidden" name="withdrawal_id" value="<?php echo $withdrawal['id']; ?>">

                        <div>
                            <label for="admin_notes" class="block text-sm font-medium text-gray-700 mb-2">Admin Notes
                                (Optional)</label>
                            <textarea name="admin_notes" id="admin_notes" rows="3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm"
                                placeholder="Add any notes..."></textarea>
                        </div>

                        <button type="submit" name="update_status" value="approved"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200"
                            onclick="return confirm('Approve this withdrawal request?')">
                            <i class="fas fa-check mr-2"></i>Approve Request
                        </button>

                        <button type="submit" name="update_status" value="rejected"
                            class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200"
                            onclick="return confirm('Reject this withdrawal request? The amount will be refunded to user balance.')">
                            <i class="fas fa-times mr-2"></i>Reject Request
                        </button>
                    </form>
                    <?php elseif ($withdrawal['status'] === 'approved'): ?>
                    <form method="POST" action="withdrawals.php">
                        <input type="hidden" name="withdrawal_id" value="<?php echo $withdrawal['id']; ?>">

                        <div class="mb-3">
                            <label for="admin_notes" class="block text-sm font-medium text-gray-700 mb-2">Completion
                                Notes (Optional)</label>
                            <textarea name="admin_notes" id="admin_notes" rows="3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm"
                                placeholder="Add completion notes..."></textarea>
                        </div>

                        <button type="submit" name="update_status" value="completed"
                            class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200"
                            onclick="return confirm('Mark this withdrawal as completed?')">
                            <i class="fas fa-check-circle mr-2"></i>Mark as Completed
                        </button>
                    </form>
                    <?php endif; ?>

                    <a href="export.php?type=withdrawal&id=<?php echo $withdrawal['id']; ?>"
                        class="w-full bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 block text-center">
                        <i class="fas fa-download mr-2"></i>Export Details
                    </a>

                    <a href="mailto:<?php echo htmlspecialchars($withdrawal['email']); ?>?subject=Withdrawal%20Request%20%23<?php echo $withdrawal['id']; ?>"
                        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 block text-center">
                        <i class="fas fa-envelope mr-2"></i>Contact User
                    </a>
                </div>
            </div>

            <!-- Withdrawal Timeline -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold mb-4">Withdrawal Timeline</h3>

                <div class="space-y-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-plus text-blue-600 text-sm"></i>
                        </div>
                        <div class="ml-3">
                            <div class="text-sm font-medium text-gray-900">Request Submitted</div>
                            <div class="text-xs text-gray-500"><?php echo formatDateTime($withdrawal['created_at']); ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($withdrawal['created_at'] !== $withdrawal['updated_at']): ?>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-edit text-yellow-600 text-sm"></i>
                        </div>
                        <div class="ml-3">
                            <div class="text-sm font-medium text-gray-900">Last Updated</div>
                            <div class="text-xs text-gray-500"><?php echo formatDateTime($withdrawal['updated_at']); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($withdrawal['status'] === 'approved'): ?>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check text-blue-600 text-sm"></i>
                        </div>
                        <div class="ml-3">
                            <div class="text-sm font-medium text-gray-900">Request Approved</div>
                            <div class="text-xs text-gray-500">Awaiting payment processing</div>
                        </div>
                    </div>
                    <?php elseif ($withdrawal['status'] === 'completed'): ?>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600 text-sm"></i>
                        </div>
                        <div class="ml-3">
                            <div class="text-sm font-medium text-gray-900">Payment Completed</div>
                            <div class="text-xs text-gray-500">Withdrawal processed successfully</div>
                        </div>
                    </div>
                    <?php elseif ($withdrawal['status'] === 'rejected'): ?>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-times text-red-600 text-sm"></i>
                        </div>
                        <div class="ml-3">
                            <div class="text-sm font-medium text-gray-900">Request Rejected</div>
                            <div class="text-xs text-gray-500">Amount refunded to balance</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-clock text-yellow-600 text-sm"></i>
                        </div>
                        <div class="ml-3">
                            <div class="text-sm font-medium text-gray-900">Pending Review</div>
                            <div class="text-xs text-gray-500">Awaiting admin approval</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/admin_footer.php'; ?>