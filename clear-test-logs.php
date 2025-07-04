<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action']) && $input['action'] === 'clear_test_logs') {
        try {
            // Clear test logs (keep only last 10 of each type)
            $db->query("DELETE FROM email_logs WHERE id NOT IN (SELECT id FROM (SELECT id FROM email_logs ORDER BY created_at DESC LIMIT 10) as temp)");
            $db->query("DELETE FROM sms_logs WHERE id NOT IN (SELECT id FROM (SELECT id FROM sms_logs ORDER BY created_at DESC LIMIT 10) as temp)");
            
            echo json_encode(['success' => true, 'message' => 'Test logs cleared successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>