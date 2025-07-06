<?php
$pageTitle = "Test Paypack Cashin";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/paypack.php';
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

if (!isLoggedIn()) {
    redirect('login.php');
}

$result = null;
$error = false;
$trackResult = null;
$trackError = false;
$eventResult = null;
$eventError = false;
$findResult = null;
$findError = false;
$lastRawResponse = null;
$lastHttpCode = null;

function getLastPaypackDebug()
{
    $logFile = ini_get('error_log');
    if (!$logFile || !file_exists($logFile))
        return null;
    $lines = array_reverse(file($logFile));
    foreach ($lines as $line) {
        if (strpos($line, 'Paypack HTTP') !== false) {
            // Example: Paypack HTTP 200 response: { ... }
            return trim($line);
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);
    $phone = trim($_POST['number'] ?? '');
    $paypack = getPaypackService();
    if (!$paypack) {
        $error = 'Paypack service not available.';
    } else {
        global $db;
        $sql = "INSERT INTO payment_transactions (amount, payment_method, status, number, currency) VALUES ($amount, 'paypack', 'pending', '$phone', 'RWF')";
        $transactionId = $db->insert($sql);

        // Initiate Paypack cashin
        try {
            $paypack->cashin($amount, $phone);
        } catch (Exception $e) {
            $error = 'Failed to initiate payment: ' . $e->getMessage();
        }

        // Redirect to status page
        if (!$error) {
            header('Location: test-paypack-status.php?transaction_id=' . $transactionId);
            exit;
        }
    }
}

// --- AJAX ENDPOINT: Find transaction reference by phone & amount ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'find_ref') {
    $phone = trim($_GET['phone'] ?? '');
    $amount = floatval($_GET['amount'] ?? 0);
    $paypackService = new PaypackService();
    $findResult = $paypackService->findTransactionsByPhoneAndAmount($phone, $amount, 'CASHIN', 1);
    $ref = null;
    $status = null;
    if (isset($findResult['transactions'][0]['data']['ref'])) {
        $ref = $findResult['transactions'][0]['data']['ref'];
        $status = $findResult['transactions'][0]['data']['status'] ?? null;
    }
    header('Content-Type: application/json');
    echo json_encode([
        'ref' => $ref,
        'status' => $status,
        'raw' => $findResult
    ]);
    exit;
}
// --- AJAX ENDPOINT: Check transaction status by reference ---
if (isset($_GET['ajax']) && $_GET['ajax'] === 'check_status') {
    $ref = trim($_GET['ref'] ?? '');
    $paypackService = new PaypackService();
    $result = $paypackService->findTransaction($ref);
    $status = $result['status'] ?? null;
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'raw' => $result
    ]);
    exit;
}

include 'includes/header.php';
$lastRawResponse = getLastPaypackDebug();
?>
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-indigo-600 text-white px-6 py-4">
                <h1 class="text-2xl font-bold">Test Paypack Cashin</h1>
                <p class="text-indigo-100">Send a cashin (deposit) request to Paypack API</p>
            </div>
            <div class="p-6">
                <?php if ($lastRawResponse): ?>
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold mb-2">Paypack Raw HTTP Response (Debug)</h2>
                        <pre
                            class="bg-yellow-100 p-3 rounded text-xs text-gray-800"><?php echo htmlspecialchars($lastRawResponse); ?></pre>
                    </div>
                <?php endif; ?>
                <!-- Cashin Form -->
                <form method="POST" class="space-y-4 mb-8">
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">Amount (RWF)</label>
                        <input type="number" name="amount" step="1" min="100" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
                    </div>
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">Phone Number</label>
                        <input type="text" name="number" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            placeholder="07XXXXXXXX" value="<?php echo htmlspecialchars($_POST['number'] ?? ''); ?>">
                    </div>
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                        <i class="fas fa-coins mr-2"></i> Test Cashin
                    </button>
                </form>
                <?php if ($result): ?>
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold mb-2">API Response</h2>
                        <pre
                            class="bg-gray-100 p-3 rounded text-xs text-gray-800"><?php echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)); ?></pre>

                        <?php
                        // Check if we have a transaction reference
                        $hasRef = isset($result['ref']);
                        $hasCount = isset($result['count']);
                        $paypackService = new PaypackService();
                        $extractedRef = $paypackService->extractTransactionReference($result);
                        $isInitiated = $paypackService->isTransactionInitiated($result);
                        $foundViaEvents = isset($result['found_via_events']) && $result['found_via_events'];
                        ?>

                        <!-- Response Analysis -->
                        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-md">
                            <h3 class="font-semibold text-blue-900 mb-2">Response Analysis</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p><strong>Has Reference:</strong>
                                        <span class="<?php echo $hasRef ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $hasRef ? 'Yes' : 'No'; ?>
                                        </span>
                                    </p>
                                    <p><strong>Has Count:</strong>
                                        <span class="<?php echo $hasCount ? 'text-blue-600' : 'text-gray-600'; ?>">
                                            <?php echo $hasCount ? 'Yes (' . $result['count'] . ')' : 'No'; ?>
                                        </span>
                                    </p>
                                    <p><strong>Transaction Initiated:</strong>
                                        <span class="<?php echo $isInitiated ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $isInitiated ? 'Yes' : 'No'; ?>
                                        </span>
                                    </p>
                                </div>
                                <div>
                                    <p><strong>Extracted Reference:</strong>
                                        <span class="<?php echo $extractedRef ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $extractedRef ?: 'None found'; ?>
                                        </span>
                                    </p>
                                    <?php if ($foundViaEvents): ?>
                                        <p><strong>Found Via Events:</strong> <span class="text-green-600">Yes</span></p>
                                    <?php endif; ?>
                                    <?php if (isset($result['_note'])): ?>
                                        <p><strong>Note:</strong> <span
                                                class="text-orange-600"><?php echo htmlspecialchars($result['_note']); ?></span>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($extractedRef): ?>
                            <form method="POST" class="mt-4">
                                <input type="hidden" name="track_ref" value="<?php echo htmlspecialchars($extractedRef); ?>">
                                <button type="submit"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                                    <i class="fas fa-search mr-2"></i> Track This Transaction
                                    (<?php echo htmlspecialchars($extractedRef); ?>)
                                </button>
                            </form>
                            <form method="POST" class="mt-2">
                                <input type="hidden" name="event_ref" value="<?php echo htmlspecialchars($extractedRef); ?>">
                                <input type="hidden" name="event_kind" value="CASHIN">
                                <input type="hidden" name="event_client"
                                    value="<?php echo htmlspecialchars($_POST['number'] ?? ''); ?>">
                                <button type="submit"
                                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                                    <i class="fas fa-history mr-2"></i> Track Events (All States)
                                </button>
                            </form>
                        <?php elseif ($isInitiated): ?>
                            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>
                                    <div>
                                        <p class="font-bold text-yellow-800">Transaction Initiated but No Reference Found</p>
                                        <p class="text-yellow-700 text-sm">The payment was initiated successfully, but no
                                            transaction reference was returned.</p>
                                        <p class="text-yellow-700 text-sm mt-1">You can try finding the transaction using the
                                            phone number and amount below.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Auto-fill the find form with current values -->
                            <form method="POST" class="mt-4">
                                <input type="hidden" name="find_phone"
                                    value="<?php echo htmlspecialchars($_POST['number'] ?? ''); ?>">
                                <input type="hidden" name="find_amount"
                                    value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>">
                                <input type="hidden" name="find_kind" value="CASHIN">
                                <button type="submit" name="find_by_phone"
                                    class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                                    <i class="fas fa-search mr-2"></i> Find This Transaction by Phone & Amount
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="mt-4 p-3 bg-orange-50 border border-orange-200 rounded-md">
                                <div class="flex items-center">
                                    <i class="fas fa-info-circle text-orange-500 mr-2"></i>
                                    <div>
                                        <p class="font-bold text-orange-800">No Transaction Reference</p>
                                        <p class="text-orange-700 text-sm">The response does not contain a transaction reference
                                            (ref) for tracking.</p>
                                        <p class="text-orange-700 text-sm mt-1">You can still manually track transactions using
                                            the forms below.</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold mb-2">Error</h2>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <div>
                                    <p class="font-bold">Error!</p>
                                    <p><?php echo htmlspecialchars($error); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- Track Transaction Form -->
                <form method="POST" class="space-y-4 mb-8">
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">Track Transaction by Reference</label>
                        <input type="text" name="track_ref"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            placeholder="Enter transaction reference..."
                            value="<?php echo htmlspecialchars($_POST['track_ref'] ?? ''); ?>">
                    </div>
                    <button type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                        <i class="fas fa-search mr-2"></i> Track Transaction
                    </button>
                </form>
                <?php if ($trackResult): ?>
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold mb-2">Track Result</h2>
                        <pre
                            class="bg-gray-100 p-3 rounded text-xs text-gray-800"><?php echo htmlspecialchars(json_encode($trackResult, JSON_PRETTY_PRINT)); ?></pre>
                    </div>
                <?php endif; ?>
                <?php if ($trackError): ?>
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold mb-2">Track Error</h2>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <div>
                                    <p class="font-bold">Error!</p>
                                    <p><?php echo htmlspecialchars($trackError); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- Track Transaction Events Form -->
                <form method="POST" class="space-y-4 mb-8">
                    <div>
                        <label class="block text-gray-700 font-bold mb-2">Track Transaction Events (All States)</label>
                        <input type="text" name="event_ref"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                            placeholder="Enter transaction reference..."
                            value="<?php echo htmlspecialchars($_POST['event_ref'] ?? ''); ?>">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1">Kind</label>
                            <select name="event_kind" class="w-full px-2 py-1 border border-gray-300 rounded-md">
                                <option value="CASHIN" <?php if (($_POST['event_kind'] ?? '') === 'CASHIN')
                                    echo 'selected'; ?>>CASHIN</option>
                                <option value="CASHOUT" <?php if (($_POST['event_kind'] ?? '') === 'CASHOUT')
                                    echo 'selected'; ?>>CASHOUT</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1">Client (Phone)</label>
                            <input type="text" name="event_client"
                                class="w-full px-2 py-1 border border-gray-300 rounded-md" placeholder="07XXXXXXXX"
                                value="<?php echo htmlspecialchars($_POST['event_client'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1">Status</label>
                            <select name="event_status" class="w-full px-2 py-1 border border-gray-300 rounded-md">
                                <option value="">Any</option>
                                <option value="pending" <?php if (($_POST['event_status'] ?? '') === 'pending')
                                    echo 'selected'; ?>>pending</option>
                                <option value="failed" <?php if (($_POST['event_status'] ?? '') === 'failed')
                                    echo 'selected'; ?>>failed</option>
                                <option value="successful" <?php if (($_POST['event_status'] ?? '') === 'successful')
                                    echo 'selected'; ?>>successful</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                        <i class="fas fa-history mr-2"></i> Track Events
                    </button>
                </form>
                <?php if ($eventResult): ?>
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold mb-2">Event Result</h2>
                        <pre
                            class="bg-gray-100 p-3 rounded text-xs text-gray-800"><?php echo htmlspecialchars(json_encode($eventResult, JSON_PRETTY_PRINT)); ?></pre>
                    </div>
                <?php endif; ?>
                <?php if ($eventError): ?>
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold mb-2">Event Error</h2>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <div>
                                    <p class="font-bold">Error!</p>
                                    <p><?php echo htmlspecialchars($eventError); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Find Transactions by Phone and Amount Form -->
                <form method="POST" class="space-y-4 mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Find Transactions by Phone & Amount</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1">Phone Number</label>
                            <input type="text" name="find_phone"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                placeholder="07XXXXXXXX"
                                value="<?php echo htmlspecialchars($_POST['find_phone'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1">Amount (RWF)</label>
                            <input type="number" name="find_amount" step="1" min="100"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500"
                                value="<?php echo htmlspecialchars($_POST['find_amount'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-1">Kind</label>
                            <select name="find_kind" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <option value="CASHIN" <?php if (($_POST['find_kind'] ?? '') === 'CASHIN')
                                    echo 'selected'; ?>>CASHIN</option>
                                <option value="CASHOUT" <?php if (($_POST['find_kind'] ?? '') === 'CASHOUT')
                                    echo 'selected'; ?>>CASHOUT</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="find_by_phone"
                        class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                        <i class="fas fa-search mr-2"></i> Find Transactions
                    </button>
                </form>

                <?php if ($findResult): ?>
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold mb-2">Find Result</h2>
                        <pre
                            class="bg-gray-100 p-3 rounded text-xs text-gray-800"><?php echo htmlspecialchars(json_encode($findResult, JSON_PRETTY_PRINT)); ?></pre>

                        <?php
                        // Extract the most recent transaction
                        $mostRecentTransaction = null;
                        $mostRecentRef = null;

                        if (isset($findResult['transactions']) && is_array($findResult['transactions']) && count($findResult['transactions']) > 0) {
                            // Find the most recent transaction (first in the list)
                            $mostRecentTransaction = $findResult['transactions'][0];
                            $mostRecentRef = $mostRecentTransaction['data']['ref'] ?? null;
                        }
                        ?>

                        <?php if ($mostRecentRef): ?>
                            <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-md">
                                <h3 class="font-semibold text-green-900 mb-2">Most Recent Transaction Found</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p><strong>Reference:</strong> <span
                                                class="font-mono text-green-700"><?php echo htmlspecialchars($mostRecentRef); ?></span>
                                        </p>
                                        <p><strong>Status:</strong>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                <?php
                                                $status = $mostRecentTransaction['data']['status'] ?? 'unknown';
                                                switch ($status) {
                                                    case 'pending':
                                                        echo 'bg-yellow-100 text-yellow-800';
                                                        break;
                                                    case 'successful':
                                                        echo 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'failed':
                                                        echo 'bg-red-100 text-red-800';
                                                        break;
                                                    default:
                                                        echo 'bg-gray-100 text-gray-800';
                                                }
                                                ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </p>
                                        <p><strong>Amount:</strong>
                                            <?php echo number_format($mostRecentTransaction['data']['amount'] ?? 0); ?> RWF</p>
                                    </div>
                                    <div>
                                        <p><strong>Created:</strong>
                                            <?php echo date('M d, Y h:i A', strtotime($mostRecentTransaction['data']['created_at'] ?? '')); ?>
                                        </p>
                                        <p><strong>Provider:</strong>
                                            <?php echo strtoupper($mostRecentTransaction['data']['provider'] ?? 'unknown'); ?>
                                        </p>
                                        <p><strong>Event Type:</strong>
                                            <?php echo htmlspecialchars($mostRecentTransaction['event_kind'] ?? 'unknown'); ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Tracking buttons for the most recent transaction -->
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="track_ref"
                                            value="<?php echo htmlspecialchars($mostRecentRef); ?>">
                                        <button type="submit"
                                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                                            <i class="fas fa-search mr-2"></i> Track This Transaction
                                        </button>
                                    </form>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="event_ref"
                                            value="<?php echo htmlspecialchars($mostRecentRef); ?>">
                                        <input type="hidden" name="event_kind" value="CASHIN">
                                        <input type="hidden" name="event_client"
                                            value="<?php echo htmlspecialchars($_POST['find_phone'] ?? ''); ?>">
                                        <button type="submit"
                                            class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                                            <i class="fas fa-history mr-2"></i> Track Events
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($findResult['total']) && $findResult['total'] > 1): ?>
                            <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                                <div class="flex items-center">
                                    <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                    <div>
                                        <p class="font-bold text-blue-800">Multiple Transactions Found</p>
                                        <p class="text-blue-700 text-sm">Found <?php echo $findResult['total']; ?> total
                                            transactions for this phone number.</p>
                                        <p class="text-blue-700 text-sm mt-1">Showing the most recent
                                            <?php echo count($findResult['transactions']); ?> transactions.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($findError): ?>
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold mb-2">Find Error</h2>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <div>
                                    <p class="font-bold">Error!</p>
                                    <p><?php echo htmlspecialchars($findError); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-8">
                    <h3 class="font-semibold text-blue-900 mb-2">Instructions</h3>
                    <ol class="list-decimal list-inside space-y-1 text-sm text-blue-800">
                        <li>Enter an amount and a valid phone number (e.g. 07XXXXXXXX)</li>
                        <li>Click "Test Cashin" to send a deposit request</li>
                        <li>If the response contains a reference (ref), use it to track the transaction</li>
                        <li>If the response only shows "count: 1" without a ref, use "Find Transactions by Phone &
                            Amount"</li>
                        <li>You can also manually track any transaction by reference using the forms below</li>
                        <li>The "Response Analysis" section helps understand what the API returned</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    let pollingRef = null;
    let pollingStatus = null;
    let foundRef = null;
    let lastStatus = null;

    function startRefPolling(phone, amount) {
        clearInterval(pollingRef);
        pollingRef = setInterval(() => {
            fetch(`?ajax=find_ref&phone=${encodeURIComponent(phone)}&amount=${encodeURIComponent(amount)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.ref) {
                        foundRef = data.ref;
                        clearInterval(pollingRef);
                        document.getElementById('paypack-status-area').innerHTML =
                            `<div class='bg-blue-50 border border-blue-200 rounded-md p-3 my-3'><b>Transaction Reference Found:</b> <span class='font-mono text-green-700'>${foundRef}</span></div>`;
                        startStatusPolling(foundRef);
                    }
                });
        }, 4000);
    }

    function startStatusPolling(ref) {
        clearInterval(pollingStatus);
        pollingStatus = setInterval(() => {
            fetch(`?ajax=check_status&ref=${encodeURIComponent(ref)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.status) {
                        lastStatus = data.status;
                        let color = 'gray';
                        if (data.status === 'successful') color = 'green';
                        else if (data.status === 'failed') color = 'red';
                        else if (data.status === 'pending') color = 'orange';
                        document.getElementById('paypack-status-area').innerHTML =
                            `<div class='bg-${color}-50 border border-${color}-200 rounded-md p-3 my-3'><b>Status:</b> <span class='font-mono text-${color}-700'>${data.status}</span></div>`;
                        if (data.status === 'successful' || data.status === 'failed') {
                            clearInterval(pollingStatus);
                        }
                    }
                });
        }, 4000);
    }

    function manualCheckStatus() {
        if (foundRef) {
            startStatusPolling(foundRef);
        }
    }

    // Hook into form submit to start polling automatically
    const cashinForm = document.querySelector('form[method="POST"]');
    if (cashinForm) {
        cashinForm.addEventListener('submit', function (e) {
            setTimeout(() => {
                // After form submit, check if no ref and transaction initiated
                const apiResp = document.querySelector('.bg-gray-100');
                if (apiResp && apiResp.textContent.includes('count')) {
                    const phone = document.querySelector('input[name="number"]').value;
                    const amount = document.querySelector('input[name="amount"]').value;
                    startRefPolling(phone, amount);
                }
            }, 1500);
        });
    }
</script>
<div id="paypack-status-area"></div>
<button type="button" onclick="manualCheckStatus()"
    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded my-2">Check Status</button>
<?php include 'includes/footer.php'; ?>