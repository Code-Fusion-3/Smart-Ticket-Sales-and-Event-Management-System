<?php
$pageTitle = "View Agent";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

checkPermission('admin');

$agentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($agentId <= 0) {
    $_SESSION['error_message'] = "Invalid agent ID.";
    header('Location: index.php');
    exit;
}

$agent = $db->fetchOne("SELECT * FROM users WHERE id = $agentId AND role = 'agent'");
if (!$agent) {
    $_SESSION['error_message'] = "Agent not found.";
    header('Location: index.php');
    exit;
}

include '../../includes/admin_header.php';
?>
<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6 max-w-2xl">
    <div class="bg-white rounded-lg shadow-md p-6">
        <h1 class="text-2xl font-bold mb-4 flex items-center">
            <i class="fas fa-user-secret mr-2 text-indigo-600"></i>Agent Details
        </h1>
        <div class="mb-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <div class="text-gray-600 text-sm">Full Name</div>
                    <div class="font-semibold text-lg text-gray-900"><?php echo htmlspecialchars($agent['username']); ?>
                    </div>
                </div>
                <div>
                    <div class="text-gray-600 text-sm">Email</div>
                    <div class="font-semibold text-lg text-gray-900"><?php echo htmlspecialchars($agent['email']); ?>
                    </div>
                </div>
                <div>
                    <div class="text-gray-600 text-sm">Phone</div>
                    <div class="font-semibold text-lg text-gray-900">
                        <?php echo htmlspecialchars($agent['phone_number']); ?></div>
                </div>
                <div>
                    <div class="text-gray-600 text-sm">Status</div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        <?php
                        switch ($agent['status']) {
                            case 'active':
                                echo 'bg-green-100 text-green-800';
                                break;
                            case 'suspended':
                                echo 'bg-red-100 text-red-800';
                                break;
                            case 'inactive':
                                echo 'bg-gray-100 text-gray-800';
                                break;
                            default:
                                echo 'bg-gray-100 text-gray-800';
                        }
                        ?>">
                        <?php echo ucfirst($agent['status']); ?>
                    </span>
                </div>
                <div>
                    <div class="text-gray-600 text-sm">Created At</div>
                    <div class="text-gray-900"><?php echo formatDateTime($agent['created_at']); ?></div>
                </div>
                <div>
                    <div class="text-gray-600 text-sm">Last Updated</div>
                    <div class="text-gray-900"><?php echo formatDateTime($agent['updated_at']); ?></div>
                </div>
            </div>
        </div>
        <div class="flex gap-3 mt-6">
            <a href="edit.php?id=<?php echo $agent['id']; ?>"
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200">
                <i class="fas fa-edit mr-1"></i>Edit
            </a>
            <?php if ($agent['status'] !== 'suspended'): ?>
                <a href="update_status.php?id=<?php echo $agent['id']; ?>&status=suspended"
                    class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200"
                    onclick="return confirm('Suspend this agent?');">
                    <i class="fas fa-ban mr-1"></i>Suspend
                </a>
            <?php else: ?>
                <a href="update_status.php?id=<?php echo $agent['id']; ?>&status=active"
                    class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200"
                    onclick="return confirm('Activate this agent?');">
                    <i class="fas fa-check mr-1"></i>Activate
                </a>
            <?php endif; ?>
            <a href="index.php"
                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200">Back</a>
        </div>
    </div>
</div>
<?php include '../../includes/admin_footer.php'; ?>