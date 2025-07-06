<?php
// Simple debug script for Paypack authentication
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/paypack.php';
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

echo "<h1>Paypack Debug Test</h1>";

try {
    echo "<h2>1. Creating Paypack Service</h2>";
    $paypackService = new PaypackService();
    echo "✅ Paypack service created successfully<br>";

    echo "<h2>2. Testing Authentication</h2>";
    $result = $paypackService->testConnection();
    echo "Test result: <pre>" . json_encode($result, JSON_PRETTY_PRINT) . "</pre>";

    echo "<h2>3. Token Info</h2>";
    $tokenInfo = $paypackService->getTokenInfo();
    echo "Token info: <pre>" . json_encode($tokenInfo, JSON_PRETTY_PRINT) . "</pre>";

    echo "<h2>4. Session Data</h2>";
    echo "Session data: <pre>" . json_encode($_SESSION, JSON_PRETTY_PRINT) . "</pre>";

} catch (Exception $e) {
    echo "<h2>❌ Error</h2>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "Stack trace: <pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>5. Environment Variables</h2>";
echo "PAYPACK_CLIENT_ID: " . ($_ENV['PAYPACK_CLIENT_ID'] ?? 'NOT SET') . "<br>";
echo "PAYPACK_CLIENT_SECRET: " . (strlen($_ENV['PAYPACK_CLIENT_SECRET'] ?? '') > 10 ? substr($_ENV['PAYPACK_CLIENT_SECRET'], 0, 10) . '...' : 'NOT SET') . "<br>";
?>