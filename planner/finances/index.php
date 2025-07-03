<?php
$pageTitle = "Financial Dashboard";
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

// Get recent transactions
$sql = "SELECT t.*, e.title as event_title 
        FROM transactions t
        LEFT JOIN events e ON t.reference_id = e.id AND t.type IN ('purchase', 'sale')
        WHERE t.user_id = $plannerId
        ORDER BY t.created_at DESC
        LIMIT 10";
$recentTransactions = $db->fetchAll($sql);

// Get earnings summary
$sql = "SELECT 
            SUM(CASE WHEN t.type = 'sale' THEN t.amount ELSE 0 END) as total_sales,
            SUM(CASE WHEN t.type = 'withdrawal' THEN t.amount ELSE 0 END) as total_withdrawals,
            SUM(CASE WHEN t.type = 'system_fee' THEN t.amount ELSE 0 END) as total_fees
        FROM transactions t
        WHERE t.user_id = $plannerId";
$earningsSummary = $db->fetchOne($sql);

// Get monthly earnings for the chart (last 6 months)
$monthlyEarnings = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthStart = $month . '-01';
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    
    $sql = "SELECT 
                SUM(CASE WHEN t.type = 'sale' THEN t.amount ELSE 0 END) as sales,
                SUM(CASE WHEN t.type = 'system_fee' THEN t.amount ELSE 0 END) as fees
            FROM transactions t
            WHERE t.user_id = $plannerId
            AND t.created_at BETWEEN '$monthStart' AND '$monthEnd 23:59:59'";
    $monthData = $db->fetchOne($sql);
    
    $monthlyEarnings[] = [
        'month' => date('M Y', strtotime($monthStart)),
        'sales' => $monthData['sales'] ?? 0,
        'fees' => $monthData['fees'] ?? 0,
        'net' => ($monthData['sales'] ?? 0) - ($monthData['fees'] ?? 0)
    ];
}

// Get pending withdrawals
$sql = "SELECT * FROM withdrawals 
        WHERE user_id = $plannerId 
        AND status IN ('pending', 'approved') 
        ORDER BY created_at DESC";
$pendingWithdrawals = $db->fetchAll($sql);

include '../../includes/planner_header.php';
?>

<div class="container mx-auto px-4 py-6">
    <h1 class="text-3xl font-bold mb-6">Financial Dashboard</h1>

    <!-- Balance and Quick Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-md overflow-hidden col-span-2">
            <div class="bg-indigo-600 text-white px-6 py-4">
                <h2 class="text-xl font-bold">Your Balance</h2>
            </div>
            <div class="p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Available Balance</p>
                        <p class="text-4xl font-bold text-indigo-600"><?php echo formatCurrency($balance); ?></p>
                    </div>
                    <div class="flex space-x-2">
                        <a href="withdraw.php"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                            <i class="fas fa-money-bill-wave mr-2"></i> Withdraw Funds
                        </a>
                    </div>
                </div>

                <?php if (!empty($pendingWithdrawals)): ?>
                <div class="mt-4 pt-4 border-t">
                    <h3 class="font-semibold text-gray-700 mb-2">Pending Withdrawals</h3>
                    <div class="space-y-2">
                        <?php foreach ($pendingWithdrawals as $withdrawal): ?>
                        <div class="flex justify-between items-center bg-gray-50 p-3 rounded">
                            <div>
                                <p class="font-medium"><?php echo formatCurrency($withdrawal['amount']); ?></p>
                                <p class="text-sm text-gray-500">
                                    Requested: <?php echo formatDate($withdrawal['created_at']); ?>
                                </p>
                            </div>
                            <span
                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                <?php echo ucfirst($withdrawal['status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-indigo-600 text-white px-6 py-4">
                <h2 class="text-xl font-bold">Earnings Summary</h2>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div>
                        <p class="text-gray-500 text-sm">Total Sales</p>
                        <p class="text-2xl font-bold text-green-600">
                            <?php echo formatCurrency($earningsSummary['total_sales'] ?? 0); ?></p>
                    </div>

                    <div>
                        <p class="text-gray-500 text-sm">Total Fees</p>
                        <p class="text-2xl font-bold text-red-600">
                            <?php echo formatCurrency($earningsSummary['total_fees'] ?? 0); ?></p>
                    </div>

                    <div>
                        <p class="text-gray-500 text-sm">Total Withdrawals</p>
                        <p class="text-2xl font-bold text-blue-600">
                            <?php echo formatCurrency($earningsSummary['total_withdrawals'] ?? 0); ?></p>
                    </div>

                    <div class="pt-4 border-t">
                        <p class="text-gray-500 text-sm">Net Earnings</p>
                        <p class="text-2xl font-bold text-indigo-600">
                            <?php echo formatCurrency(($earningsSummary['total_sales'] ?? 0) - ($earningsSummary['total_fees'] ?? 0) - ($earningsSummary['total_withdrawals'] ?? 0)); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Earnings Chart -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <div class="bg-indigo-600 text-white px-6 py-4">
            <h2 class="text-xl font-bold">Monthly Earnings</h2>
        </div>
        <div class="p-6">
            <canvas id="earningsChart" height="300"></canvas>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="bg-indigo-600 text-white px-6 py-4 flex justify-between items-center">
            <h2 class="text-xl font-bold">Recent Transactions</h2>
            <a href="transactions.php" class="text-white hover:text-indigo-200 text-sm">
                View All <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        <div class="p-6">
            <?php if (empty($recentTransactions)): ?>
            <p class="text-gray-500 text-center py-4">No transactions found.</p>
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
                                Amount</th>
                            <th
                                class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($recentTransactions as $transaction): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo formatDate($transaction['created_at']); ?>
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
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php if (in_array($transaction['type'], ['sale', 'deposit'])): ?>
                                <span
                                    class="text-green-600">+<?php echo formatCurrency($transaction['amount']); ?></span>
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
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Monthly earnings chart
    var ctx = document.getElementById('earningsChart').getContext('2d');
    var earningsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [
                <?php foreach ($monthlyEarnings as $data): ?> '<?php echo $data['month']; ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                    label: 'Sales',
                    backgroundColor: 'rgba(79, 70, 229, 0.8)',
                    data: [
                        <?php foreach ($monthlyEarnings as $data): ?>
                        <?php echo $data['sales']; ?>,
                        <?php endforeach; ?>
                    ]
                },
                {
                    label: 'Fees',
                    backgroundColor: 'rgba(239, 68, 68, 0.8)',
                    data: [
                        <?php foreach ($monthlyEarnings as $data): ?>
                        <?php echo $data['fees']; ?>,
                        <?php endforeach; ?>
                    ]
                },
                {
                    label: 'Net Earnings',
                    backgroundColor: 'rgba(16, 185, 129, 0.8)',
                    data: [
                        <?php foreach ($monthlyEarnings as $data): ?>
                        <?php echo $data['net']; ?>,
                        <?php endforeach; ?>
                    ]
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '<?php echo CURRENCY_SYMBOL; ?>' + value;
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php  ?>