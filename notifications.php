<?php
$pageTitle = "Notifications";
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

if (!isLoggedIn()) {
    $_SESSION['error_message'] = "Please login to view your notifications.";
    redirect('login.php');
}

$userId = getCurrentUserId();

// Fetch notifications for the user
$notifications = $db->fetchAll("SELECT * FROM notifications WHERE user_id = $userId ORDER BY created_at DESC LIMIT 100");

// Mark all as read
$db->query("UPDATE notifications SET is_read = 1 WHERE user_id = $userId AND is_read = 0");

include 'includes/header.php';
?>
<div class="max-w-2xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-4">Notifications</h1>
    <?php if (empty($notifications)): ?>
        <div class="bg-yellow-100 text-yellow-800 p-4 rounded">You have no notifications yet.</div>
    <?php else: ?>
        <ul class="space-y-4">
            <?php foreach ($notifications as $notification): ?>
                <li class="bg-white shadow p-4 rounded border-l-4 <?php
                switch ($notification['type']) {
                    case 'payment':
                        echo 'border-green-500';
                        break;
                    case 'ticket':
                        echo 'border-blue-500';
                        break;
                    case 'event_reminder':
                        echo 'border-yellow-500';
                        break;
                    case 'system':
                    default:
                        echo 'border-gray-400';
                        break;
                }
                ?>">
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="font-semibold text-lg"><?php echo htmlspecialchars($notification['title']); ?></div>
                            <div class="text-gray-700 mt-1"><?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                            </div>
                        </div>
                        <div class="text-xs text-gray-500 ml-4 whitespace-nowrap">
                            <?php echo date('M j, Y H:i', strtotime($notification['created_at'])); ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>