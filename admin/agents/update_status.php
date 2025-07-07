<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/notifications.php';

checkPermission('admin');

$agentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$newStatus = isset($_GET['status']) ? $_GET['status'] : '';
$returnUrl = 'index.php';

if ($agentId <= 0) {
    $_SESSION['error_message'] = "Invalid agent ID.";
    header('Location: ' . $returnUrl);
    exit;
}

if (!in_array($newStatus, ['active', 'suspended'])) {
    $_SESSION['error_message'] = "Invalid status.";
    header('Location: ' . $returnUrl);
    exit;
}

$agent = $db->fetchOne("SELECT * FROM users WHERE id = $agentId AND role = 'agent'");
if (!$agent) {
    $_SESSION['error_message'] = "Agent not found.";
    header('Location: ' . $returnUrl);
    exit;
}

if ($agent['status'] === $newStatus) {
    $_SESSION['info_message'] = "Agent is already $newStatus.";
    header('Location: ' . $returnUrl);
    exit;
}

$sql = "UPDATE users SET status = '" . $db->escape($newStatus) . "', updated_at = NOW() WHERE id = $agentId";
if ($db->query($sql)) {
    // Send notification email
    $subject = ($newStatus === 'suspended') ? "Your Agent Account Has Been Suspended" : "Your Agent Account Has Been Activated";
    $body = ($newStatus === 'suspended')
        ? "<h2>Account Suspended</h2><p>Dear {$agent['username']},<br>Your agent account has been suspended by the admin. You will not be able to log in or perform any actions until reactivated. Contact support for more information.</p>"
        : "<h2>Account Activated</h2><p>Dear {$agent['username']},<br>Your agent account has been activated. You can now log in and continue your activities on the platform.</p>";
    sendEmail($agent['email'], $subject, $body);
    $_SESSION['success_message'] = "Agent status updated and notification sent.";
} else {
    $_SESSION['error_message'] = "Failed to update agent status.";
}
header('Location: ' . $returnUrl);
exit;