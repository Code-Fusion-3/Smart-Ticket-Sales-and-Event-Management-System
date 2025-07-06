<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/paypack.php';

if (!isset($_GET['transaction_id'])) {
    die('Missing transaction ID');
}
$transactionId = intval($_GET['transaction_id']);

// AJAX endpoint for polling
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $paypack = getPaypackService();
    global $db;
    if ($paypack) {
        // Actively check Paypack and update DB
        $paypack->checkPaymentStatus($transactionId);
    }
    $row = $db->fetchOne("SELECT * FROM payment_transactions WHERE transaction_id = $transactionId");
    $status = $row['status'] ?? 'unknown';
    echo json_encode([
        'status' => $status,
        'amount' => $row['amount'] ?? '',
        'number' => $row['number'] ?? '',
        'created_at' => $row['created_at'] ?? '',
        'updated_at' => $row['updated_at'] ?? '',
        'payment_method' => $row['payment_method'] ?? '',
        'failure_reason' => $row['failure_reason'] ?? '',
        'gateway_response' => $row['gateway_response'] ?? '',
        'gateway_reference' => $row['gateway_reference'] ?? '',
        'gateway_transaction_id' => $row['gateway_transaction_id'] ?? '',
    ]);
    exit;
}

// Fetch transaction for display
global $db;
$transaction = $db->fetchOne("SELECT * FROM payment_transactions WHERE transaction_id = $transactionId");
if (!$transaction)
    die('Transaction not found');
?>
<!DOCTYPE html>
<html>

<head>
    <title>Paypack Payment Status</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-center">
    <div class="max-w-lg w-full bg-white rounded-lg shadow-lg p-8 mt-10">
        <h1 class="text-2xl font-bold mb-4 text-center">Payment Status</h1>
        <div id="notification-area"></div>
        <div class="mb-4 text-center">
            <span id="status-icon"></span>
            <span id="status-text" class="text-lg font-semibold"><?= htmlspecialchars($transaction['status']) ?></span>
        </div>
        <div class="grid grid-cols-1 gap-2 mb-4">
            <div><strong>Transaction ID:</strong> <?= htmlspecialchars($transaction['transaction_id']) ?></div>
            <div><strong>Amount:</strong> <?= htmlspecialchars($transaction['amount']) ?> RWF</div>
            <div><strong>Phone:</strong> <?= htmlspecialchars($transaction['number'] ?? '') ?></div>
            <div><strong>Payment Method:</strong> <?= htmlspecialchars($transaction['payment_method'] ?? '') ?></div>
            <div><strong>Reference:</strong>
                <?= htmlspecialchars($transaction['gateway_transaction_id'] ?? $transaction['gateway_reference'] ?? '') ?>
            </div>
            <div><strong>Date Created:</strong> <?= htmlspecialchars($transaction['created_at']) ?></div>
            <div><strong>Last Updated:</strong> <span
                    id="updated-at"><?= htmlspecialchars($transaction['updated_at']) ?></span></div>
        </div>
        <div id="progress-area" class="mb-4"></div>
        <div id="failure-area" class="mb-4 text-red-600"></div>
        <div id="debug-area" class="mb-4 text-xs text-gray-500"></div>
        <div class="flex gap-2 justify-center">
            <button onclick="manualCheck()"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">Check Status</button>
            <a href="test-paypack-cashin.php"
                class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">Back
                to
                Home</a>
        </div>
    </div>
    <script>
    let lastStatus = '<?= htmlspecialchars($transaction['status']) ?>';
    let transactionId = '<?= $transactionId ?>';
    let pollInterval = null;

    function showNotification(type, message) {
        let color = type === 'success' ? 'green' : (type === 'error' ? 'red' : 'yellow');
        document.getElementById('notification-area').innerHTML =
            `<div class="mb-4 p-3 rounded bg-${color}-100 border border-${color}-300 text-${color}-800 flex items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-times-circle' : 'fa-info-circle')} mr-2"></i>
                    <span>${message}</span>
                </div>`;
    }

    function updateStatusUI(data) {
        let statusText = document.getElementById('status-text');
        let statusIcon = document.getElementById('status-icon');
        let updatedAt = document.getElementById('updated-at');
        let progressArea = document.getElementById('progress-area');
        let failureArea = document.getElementById('failure-area');
        let debugArea = document.getElementById('debug-area');

        statusText.textContent = data.status;
        updatedAt.textContent = data.updated_at || '';
        failureArea.textContent = '';
        debugArea.textContent = '';

        // Status icon and color
        if (data.status === 'completed' || data.status === 'successful') {
            statusText.className = 'text-green-700 text-lg font-semibold';
            statusIcon.innerHTML = '<i class="fas fa-check-circle text-green-500 text-2xl mr-2"></i>';
            showNotification('success', 'Payment Successful!');
            progressArea.innerHTML = '';
        } else if (data.status === 'failed') {
            statusText.className = 'text-red-700 text-lg font-semibold';
            statusIcon.innerHTML = '<i class="fas fa-times-circle text-red-500 text-2xl mr-2"></i>';
            showNotification('error', 'Payment Failed.');
            if (data.failure_reason) {
                failureArea.textContent = 'Reason: ' + data.failure_reason;
            }
            progressArea.innerHTML = '';
        } else if (data.status === 'processing' || data.status === 'pending') {
            statusText.className = 'text-yellow-700 text-lg font-semibold';
            statusIcon.innerHTML = '<i class="fas fa-clock text-yellow-500 text-2xl mr-2 animate-spin"></i>';
            showNotification('info', 'Waiting for payment confirmation...');
            progressArea.innerHTML =
                '<div class="flex items-center justify-center"><span class="animate-spin mr-2"><i class="fas fa-spinner"></i></span>Processing...</div>';
        } else {
            statusText.className = 'text-gray-700 text-lg font-semibold';
            statusIcon.innerHTML = '<i class="fas fa-question-circle text-gray-500 text-2xl mr-2"></i>';
            showNotification('info', 'Unknown status.');
            progressArea.innerHTML = '';
        }

        // Debug info (for developers)
        if (data.gateway_response) {
            debugArea.innerHTML = '<b>Gateway Response:</b><br><pre>' + JSON.stringify(data.gateway_response, null, 2) +
                '</pre>';
        }
    }

    function pollStatus() {
        fetch('test-paypack-status.php?ajax=1&transaction_id=' + transactionId)
            .then(r => r.json())
            .then(data => {
                if (data.status && data.status !== lastStatus) {
                    lastStatus = data.status;
                    updateStatusUI(data);
                    if (data.status === 'completed' || data.status === 'failed' || data.status === 'successful') {
                        clearInterval(pollInterval);
                    }
                } else if (data.status) {
                    updateStatusUI(data);
                }
            });
    }

    function manualCheck() {
        pollStatus();
    }

    // Initial UI update
    updateStatusUI({
        status: '<?= htmlspecialchars($transaction['status']) ?>',
        updated_at: '<?= htmlspecialchars($transaction['updated_at']) ?>',
        failure_reason: '<?= htmlspecialchars($transaction['failure_reason'] ?? '') ?>',
        gateway_response: '<?= htmlspecialchars($transaction['gateway_response'] ?? '') ?>'
    });

    if (lastStatus === 'processing' || lastStatus === 'pending') {
        pollInterval = setInterval(pollStatus, 4000);
    }
    </script>
</body>

</html>