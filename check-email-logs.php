<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

echo "=== Recent Email Logs ===\n";

$sql = "SELECT * FROM email_logs ORDER BY created_at DESC LIMIT 10";
$logs = $db->fetchAll($sql);

foreach ($logs as $log) {
    echo "Email: " . $log['recipient_email'] . "\n";
    echo "Status: " . $log['status'] . "\n";
    echo "Created: " . $log['created_at'] . "\n";

    if ($log['status'] === 'failed' && !empty($log['error_message'])) {
        echo "Error: " . $log['error_message'] . "\n";
    }

    echo "---\n";
}

echo "=== End of Logs ===\n";
?>