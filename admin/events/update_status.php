<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

// Get parameters
$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$newStatus = isset($_GET['status']) ? $_GET['status'] : '';
$returnUrl = isset($_GET['return']) ? $_GET['return'] : '';

// Validate parameters
if ($eventId <= 0) {
    $_SESSION['error_message'] = "Invalid event ID";
    redirect('index.php');
}

if (!in_array($newStatus, ['active', 'suspended', 'canceled', 'completed'])) {
    $_SESSION['error_message'] = "Invalid status";
    redirect('index.php');
}

// Clean and validate return URL
$validReturnUrl = 'admin/index.php'; // Default fallback

if (!empty($returnUrl)) {
    // Decode the URL
    $decodedUrl = urldecode($returnUrl);
    
    // Remove any duplicate site URL parts
    $decodedUrl = str_replace(SITE_URL . '/', '', $decodedUrl);
    $decodedUrl = str_replace(SITE_URL, '', $decodedUrl);
    
    // Ensure it's a relative URL within admin/events/
    if (strpos($decodedUrl, 'admin/events/') === 0) {
        // Remove the admin/events/ part since we're already in that directory
        $validReturnUrl = str_replace('admin/events/', '', $decodedUrl);
    } elseif (strpos($decodedUrl, '/admin/events/') !== false) {
        // Extract just the filename and query string
        $parts = explode('/admin/events/', $decodedUrl);
        if (isset($parts[1])) {
            $validReturnUrl = $parts[1];
        }
    } elseif (preg_match('/^(index|view)\.php/', $decodedUrl)) {
        // If it starts with a valid filename, use it
        $validReturnUrl = "admin/events/".$decodedUrl;
    }
}

// Get event details for confirmation
$sql = "SELECT id, title, status FROM events WHERE id = $eventId";
$event = $db->fetchOne($sql);

if (!$event) {
    $_SESSION['error_message'] = "Event not found";
    redirect($validReturnUrl);
}

// Check if status is already the same
if ($event['status'] === $newStatus) {
    $_SESSION['info_message'] = "Event is already " . $newStatus;
    redirect($validReturnUrl);
}

// Update the status
$sql = "UPDATE events SET status = '" . $db->escape($newStatus) . "', updated_at = NOW() WHERE id = $eventId";

if ($db->query($sql)) {
    $statusMessages = [
        'active' => 'activated',
        'suspended' => 'suspended',
        'canceled' => 'canceled',
        'completed' => 'marked as completed'
    ];
    
    $_SESSION['success_message'] = "Event '" . htmlspecialchars($event['title']) . "' has been " . $statusMessages[$newStatus] . " successfully";
} else {
    $_SESSION['error_message'] = "Failed to update event status";
}

// Redirect back
redirect($validReturnUrl);
?>