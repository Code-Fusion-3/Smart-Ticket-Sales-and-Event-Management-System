<?php
$pageTitle = "Withdraw Funds";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has permission
checkPermission('event_planner');

// Get planner ID
$plannerId = getCurrentUserId();

// Get planner's current balance
$sql = "SELECT balance FROM users WHERE id = $plannerId";
$result = $db->fetchOne($sql);
$balance = $result['balance'] ?? 0;

// Get system withdrawal fee
$sql = "SELECT percentage FROM system_fees WHERE fee_type = 'withdrawal'";
$feeResult = $db->fetchOne($sql);
$withdrawalFeePercentage = $feeResult['percentage'] ?? 2.5;

// Process withdrawal request
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
    $paymentMethod = $_POST['payment_method'] ?? '';
    $paymentDetails = $_POST['payment_details'] ?? '';
    
    // Validate inputs
    if ($amount <= 0) {
        $errors[] = "Amount must be greater than zero";
    } elseif ($amount > $balance) {
        $errors[] = "Withdrawal amount cannot exceed your available balance";
    }
    
    if (empty($paymentMethod)) {
        $errors[] = "Payment method is required";
    }
    
    if (empty($paymentDetails)) {
        $errors[] = "Payment details are required";
    }
    
    // If no errors, process withdrawal
    if (empty($errors)) {
        // Calculate fee
        $fee = ($amount * $withdrawalFeePercentage) / 100;
        $netAmount = $amount - $fee;
        
        // Start transaction
        $db->query("START TRANSACTION");
        
        try {
            // Insert withdrawal record
            $sql = "INSERT INTO withdrawals (
                        user_id, amount, fee, net_amount, payment_method, payment_details, status, created_at
                    ) VALUES (
                                               $plannerId,
                        $amount,
                        $fee,
                        $netAmount,
                        '" . $db->escape($paymentMethod) . "',
                        '" . $db->escape($paymentDetails) . "',
                        'pending',
                        NOW()
                    )";
                    $withdrawalId = $db->insert($sql);

            
            // Update user balance
            $sql = "UPDATE users SET balance = balance - $amount WHERE id = $plannerId";
            $db->query($sql);
            
            // Insert transaction record
            $sql = "INSERT INTO transactions (
                        user_id, amount, type, status, reference_id, payment_method, description, created_at
                    ) VALUES (
                        $plannerId,
                        $amount,
                        'withdrawal',
                        'pending',
                        $withdrawalId,
                        '" . $db->escape($paymentMethod) . "',
                        'Withdrawal request',
                        NOW()
                    )";
            $db->query($sql);
            
            // Insert fee transaction if there's a fee
            if ($fee > 0) {
                $sql = "INSERT INTO transactions (
                            user_id, amount, type, status, reference_id, description, created_at
                        ) VALUES (
                            $plannerId,
                            $fee,
                            'system_fee',
                            'completed',
                            $withdrawalId,
                            'Withdrawal fee',
                            NOW()
                        )";
                $db->query($sql);
            }
            
            // Commit transaction
            $db->query("COMMIT");
            
            $success = true;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->query("ROLLBACK");
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}

// Get recent withdrawals
$sql = "SELECT * FROM withdrawals 
        WHERE user_id = $plannerId 
        ORDER BY created_at DESC 
        LIMIT 5";
$recentWithdrawals = $db->fetchAll($sql);

include '../../includes/planner_header.php';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Withdraw Funds</h1>
        <a href="index.php" class="text-indigo-600 hover:text-indigo-800">
            <i class="fas fa-arrow-left mr-2"></i> Back to Financial Dashboard
        </a>
    </div>

    <?php if ($success): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
        <p class="font-bold">Withdrawal Request Submitted!</p>
        <p>Your withdrawal request has been submitted successfully and is pending approval. You will be notified once
            it's processed.</p>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
        <p class="font-bold">Please fix the following errors:</p>
        <ul class="list-disc pl-5">
            <?php foreach ($errors as $error): ?>
            <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Withdrawal Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Request Withdrawal</h2>
                </div>

                <div class="p-6">
                    <div class="mb-6 bg-blue-50 p-4 rounded-lg">
                        <p class="text-blue-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            Your available balance: <span
                                class="font-bold"><?php echo formatCurrency($balance); ?></span>
                        </p>
                    </div>

                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="amount" class="block text-gray-700 font-bold mb-2">Withdrawal Amount *</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500">Rwf</span>
                                </div>
                                <input type="number" id="amount" name="amount" min="1" max="<?php echo $balance; ?>"
                                    step="0.01"
                                    class="w-full pl-12 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                    value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>"
                                    required oninput="calculateFee(this.value)">

                            </div>
                        </div>

                        <div class="mb-4 bg-gray-50 p-4 rounded-lg">
                            <div class="flex justify-between mb-2">
                                <span class="text-gray-600">Withdrawal Fee
                                    (<?php echo $withdrawalFeePercentage; ?>%):</span>
                                <span id="fee-amount" class="font-medium"><?php echo formatCurrency(0); ?></span>
                            </div>
                            <div class="flex justify-between font-bold">
                                <span class="text-gray-800">You will receive:</span>
                                <span id="net-amount" class="text-indigo-600"><?php echo formatCurrency(0); ?></span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="payment_method" class="block text-gray-700 font-bold mb-2">Payment Method
                                *</label>
                            <select id="payment_method" name="payment_method"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                required>
                                <option value="">Select Payment Method</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="paypal">PayPal</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="payment_details" class="block text-gray-700 font-bold mb-2">Payment Details
                                *</label>
                            <textarea id="payment_details" name="payment_details" rows="4"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                placeholder="Enter your payment details (e.g., bank account number, mobile money number, PayPal email, etc.)"
                                required></textarea>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded <?php echo (int)$balance == 0 ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                <?php echo (int)$balance == 0 ? 'disabled' : ''; ?>>
                                Submit Withdrawal Request
                            </button>

                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Recent Withdrawals -->
        <div>
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Recent Withdrawals</h2>
                </div>

                <div class="p-6">
                    <?php if (empty($recentWithdrawals)): ?>
                    <p class="text-gray-500 text-center py-4">No withdrawal history found.</p>
                    <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($recentWithdrawals as $withdrawal): ?>
                        <div class="border-b pb-4 last:border-b-0 last:pb-0">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium"><?php echo formatCurrency($withdrawal['amount']); ?></p>
                                    <p class="text-sm text-gray-500">
                                        Fee: <?php echo formatCurrency($withdrawal['fee']); ?> |
                                        Net: <?php echo formatCurrency($withdrawal['net_amount']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <?php echo formatDate($withdrawal['created_at']); ?> at
                                        <?php echo formatTime($withdrawal['created_at']); ?>
                                    </p>
                                </div>
                                <?php
                                        $statusClasses = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'approved' => 'bg-blue-100 text-blue-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'rejected' => 'bg-red-100 text-red-800'
                                        ];
                                        $statusClass = $statusClasses[$withdrawal['status']] ?? 'bg-gray-100 text-gray-800';
                                        ?>
                                <span
                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($withdrawal['status']); ?>
                                </span>
                            </div>
                            <p class="text-sm mt-2">
                                <span class="text-gray-600">Method:</span>
                                <?php echo ucfirst(str_replace('_', ' ', $withdrawal['payment_method'])); ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-4 text-center">
                        <a href="transactions.php?type=withdrawal"
                            class="text-indigo-600 hover:text-indigo-800 text-sm">
                            View All Withdrawals <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md overflow-hidden mt-6">
                <div class="bg-indigo-600 text-white px-6 py-4">
                    <h2 class="text-xl font-bold">Withdrawal Information</h2>
                </div>

                <div class="p-6">
                    <div class="space-y-4">
                        <div>
                            <h3 class="font-bold text-gray-700 mb-2">Processing Time</h3>
                            <p class="text-sm text-gray-600">
                                Withdrawal requests are typically processed within 1-3 business days.
                            </p>
                        </div>

                        <div>
                            <h3 class="font-bold text-gray-700 mb-2">Withdrawal Fee</h3>
                            <p class="text-sm text-gray-600">
                                A <?php echo $withdrawalFeePercentage; ?>% fee is applied to all withdrawals.
                            </p>
                        </div>

                        <div>
                            <h3 class="font-bold text-gray-700 mb-2">Minimum Withdrawal</h3>
                            <p class="text-sm text-gray-600">
                                The minimum withdrawal amount is <?php echo formatCurrency(10); ?>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function calculateFee(amount) {
    amount = parseFloat(amount) || 0;
    var feePercentage = <?php echo $withdrawalFeePercentage; ?>;
    var fee = (amount * feePercentage) / 100;
    var netAmount = amount - fee;

    document.getElementById('fee-amount').textContent = 'Rwf ' + fee.toFixed(2);
    document.getElementById('net-amount').textContent = 'Rwf ' + netAmount.toFixed(2);
}

// Calculate fee on page load if amount is pre-filled
document.addEventListener('DOMContentLoaded', function() {
    var amountField = document.getElementById('amount');
    if (amountField.value) {
        calculateFee(amountField.value);
    }
});
</script>

<?php  ?>