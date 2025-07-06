<?php
$pageTitle = "Test Paypack Authentication";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/paypack.php';
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$result = null;
$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Create a fresh instance instead of using cached one
        $paypackService = new PaypackService();
        $result = $paypackService->testConnection();
        // Debug: Log the result
        error_log("Paypack test result: " . json_encode($result));
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Paypack test error: " . $e->getMessage());
    }
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-indigo-600 text-white px-6 py-4">
                <h1 class="text-2xl font-bold">Test Paypack Authentication</h1>
                <p class="text-indigo-100">Verify Paypack API connection and authentication</p>
            </div>

            <div class="p-6">
                <!-- Environment Variables Check -->
                <div class="mb-8">
                    <h2 class="text-lg font-semibold mb-4">Environment Variables</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-sm font-medium text-gray-600">PAYPACK_CLIENT_ID</div>
                            <div class="text-sm text-gray-900">
                                <?php
                                $clientId = $_ENV['PAYPACK_CLIENT_ID'] ?? '';
                                echo $clientId ? (strlen($clientId) > 20 ? substr($clientId, 0, 20) . '...' : $clientId) : '<span class="text-red-600">Not set</span>';
                                ?>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-sm font-medium text-gray-600">PAYPACK_CLIENT_SECRET</div>
                            <div class="text-sm text-gray-900">
                                <?php
                                $clientSecret = $_ENV['PAYPACK_CLIENT_SECRET'] ?? '';
                                echo $clientSecret ? (strlen($clientSecret) > 20 ? substr($clientSecret, 0, 20) . '...' : $clientSecret) : '<span class="text-red-600">Not set</span>';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Test Connection Form -->
                <div class="mb-8">
                    <h2 class="text-lg font-semibold mb-4">Test Connection</h2>
                    <form method="POST" class="space-y-4">
                        <button type="submit"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition duration-300">
                            <i class="fas fa-plug mr-2"></i>
                            Test Paypack Authentication
                        </button>
                    </form>
                </div>

                <!-- Results -->
                <?php if ($result): ?>
                <div class="mb-8">
                    <h2 class="text-lg font-semibold mb-4">Test Results</h2>

                    <!-- Debug Information -->
                    <div class="bg-gray-50 p-4 rounded-lg mb-4">
                        <h3 class="font-semibold text-gray-900 mb-2">Debug Information</h3>
                        <pre
                            class="text-xs text-gray-600"><?php echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)); ?></pre>

                        <?php if (isset($result['token_length'])): ?>
                        <div class="mt-2 p-2 bg-blue-50 rounded">
                            <strong>Token Length:</strong> <?php echo $result['token_length']; ?> characters<br>
                            <strong>Token Preview:</strong>
                            <?php echo htmlspecialchars(substr($_SESSION['paypack_access_token'] ?? '', 0, 50)) . '...'; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($result['success']) && $result['success']): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-2"></i>
                            <div>
                                <p class="font-bold">Success!</p>
                                <p><?php echo htmlspecialchars($result['message'] ?? 'Authentication successful'); ?>
                                </p>
                                <?php if (isset($result['token_expires'])): ?>
                                <p class="text-sm mt-1">
                                    Token expires: <?php echo date('Y-m-d H:i:s', $result['token_expires']); ?>
                                    (<?php echo round(($result['token_expires'] - time()) / 60, 1); ?> minutes from now)
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <div>
                                <p class="font-bold">Error!</p>
                                <p><?php echo htmlspecialchars($result['message'] ?? 'Unknown error occurred'); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="mb-8">
                    <h2 class="text-lg font-semibold mb-4">Error</h2>
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

                <!-- Current Token Info -->
                <?php
                try {
                    $paypackService = new PaypackService();
                    $tokenInfo = $paypackService->getTokenInfo();
                } catch (Exception $e) {
                    $tokenInfo = null;
                }
                ?>

                <?php if (isset($tokenInfo)): ?>
                <div class="mb-8">
                    <h2 class="text-lg font-semibold mb-4">Current Token Status</h2>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm font-medium text-gray-600">Has Token</div>
                                <div class="text-sm text-gray-900">
                                    <?php echo $tokenInfo['has_token'] ? '<span class="text-green-600">Yes</span>' : '<span class="text-red-600">No</span>'; ?>
                                </div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-600">Token Valid</div>
                                <div class="text-sm text-gray-900">
                                    <?php echo $tokenInfo['is_valid'] ? '<span class="text-green-600">Yes</span>' : '<span class="text-red-600">No</span>'; ?>
                                </div>
                            </div>
                            <?php if ($tokenInfo['expires']): ?>
                            <div>
                                <div class="text-sm font-medium text-gray-600">Expires At</div>
                                <div class="text-sm text-gray-900">
                                    <?php echo date('Y-m-d H:i:s', $tokenInfo['expires']); ?>
                                </div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-600">Expires In</div>
                                <div class="text-sm text-gray-900">
                                    <?php echo round($tokenInfo['expires_in'] / 60, 1); ?> minutes
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Instructions -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="font-semibold text-blue-900 mb-2">Setup Instructions</h3>
                    <ol class="list-decimal list-inside space-y-1 text-sm text-blue-800">
                        <li>Create a Paypack application in your Paypack dashboard</li>
                        <li>Copy the client_id and client_secret</li>
                        <li>Add them to your .env file:
                            <pre class="bg-blue-100 p-2 rounded mt-1 text-xs">PAYPACK_CLIENT_ID=your_client_id_here
PAYPACK_CLIENT_SECRET=your_client_secret_here</pre>
                        </li>
                        <li>Click "Test Paypack Authentication" to verify the connection</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>