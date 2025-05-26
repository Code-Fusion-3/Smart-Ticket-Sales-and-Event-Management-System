<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user has permission
checkPermission('event_planner');

// Get planner ID
$plannerId = getCurrentUserId();

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
    
    if ($eventId <= 0) {
        $_SESSION['error_message'] = "Invalid event ID.";
        redirect('events.php');
    }
    
    // Verify event belongs to this planner
    $sql = "SELECT id FROM events WHERE id = $eventId AND planner_id = $plannerId";
    $event = $db->fetchOne($sql);
    
    if (!$event) {
        $_SESSION['error_message'] = "Event not found or you don't have permission to modify it.";
        redirect('events.php');
    }
    
    // Process action
    switch ($action) {
        case 'suspend':
            $sql = "UPDATE events SET status = 'suspended', updated_at = NOW() WHERE id = $eventId AND planner_id = $plannerId";
            $db->query($sql);
            $_SESSION['success_message'] = "Event has been suspended. Ticket sales are now paused.";
            break;
            
        case 'activate':
            $sql = "UPDATE events SET status = 'active', updated_at = NOW() WHERE id = $eventId AND planner_id = $plannerId";
            $db->query($sql);
            $_SESSION['success_message'] = "Event has been activated. Ticket sales are now resumed.";
            break;
            
        case 'cancel':
            $sql = "UPDATE events SET status = 'canceled', updated_at = NOW() WHERE id = $eventId AND planner_id = $plannerId";
            $db->query($sql);
            $_SESSION['success_message'] = "Event has been canceled.";
            break;
            
        default:
            $_SESSION['error_message'] = "Invalid action.";
            break;
    }
    
    // Redirect back to event details
    redirect("event-details.php?id=$eventId");
} else {
    // If not a POST request, redirect to events page
    redirect('events.php');
}
