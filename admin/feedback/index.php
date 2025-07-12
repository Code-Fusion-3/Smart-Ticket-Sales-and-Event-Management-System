<?php
$pageTitle = "Feedback Management";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

// Handle status update
if (isset($_POST['update_status']) && isset($_POST['feedback_id'])) {
    $feedbackId = (int) $_POST['feedback_id'];
    $newStatus = $_POST['update_status'];
    if (in_array($newStatus, ['pending', 'reviewed', 'resolved'])) {
        $db->query("UPDATE feedback SET status = '" . $db->escape($newStatus) . "', updated_at = NOW() WHERE id = $feedbackId");
        $_SESSION['success_message'] = "Feedback status updated.";
    }
    header('Location: index.php');
    exit;
}

// Handle delete
if (isset($_POST['delete_feedback']) && isset($_POST['feedback_id'])) {
    $feedbackId = (int) $_POST['feedback_id'];
    $db->query("DELETE FROM feedback WHERE id = $feedbackId");
    $_SESSION['success_message'] = "Feedback deleted.";
    header('Location: index.php');
    exit;
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

$whereConditions = [];
if (!empty($statusFilter)) {
    $whereConditions[] = "status = '" . $db->escape($statusFilter) . "'";
}
if (!empty($searchQuery)) {
    $search = $db->escape($searchQuery);
    $whereConditions[] = "(name LIKE '%$search%' OR email LIKE '%$search%' OR subject LIKE '%$search%' OR message LIKE '%$search%')";
}
$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$countSql = "SELECT COUNT(*) as total FROM feedback $whereClause";
$totalResult = $db->fetchOne($countSql);
$totalFeedback = $totalResult['total'];
$totalPages = ceil($totalFeedback / $perPage);

// Get feedback
$sql = "SELECT * FROM feedback $whereClause ORDER BY created_at DESC LIMIT $offset, $perPage";
$feedbackList = $db->fetchAll($sql);

include '../../includes/admin_header.php';
?>
<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Feedback Management</h1>
            <p class="text-gray-600 mt-2 text-sm sm:text-base">View and manage user feedback</p>
        </div>
    </div>
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 alert-auto-hide">
        <i class="fas fa-check-circle mr-2"></i><?php echo $_SESSION['success_message'];
            unset($_SESSION['success_message']); ?>
    </div>
    <?php endif; ?>

    <!-- Feedback Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <?php if (empty($feedbackList)): ?>
            <div class="px-6 py-8 text-center text-gray-500">
                <i class="fas fa-comment-dots text-4xl text-gray-300 mb-4 block"></i>
                No feedback found
            </div>
            <?php else: ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($feedbackList as $feedback): ?>
                <div class="p-6 hover:bg-gray-50">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-2">
                        <div class="flex items-center space-x-4 mb-2 md:mb-0">
                            <div class="text-lg font-bold text-gray-900">
                                <?php echo htmlspecialchars($feedback['name']); ?>
                            </div>
                            <div class="text-sm text-gray-500"><i
                                    class="fas fa-envelope mr-1"></i><?php echo htmlspecialchars($feedback['email']); ?>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">

                            <span class="text-xs text-gray-400 ml-2">
                                <?php echo date('Y-m-d H:i', strtotime($feedback['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                    <div class="mb-2">
                        <span class="font-semibold text-gray-700">Subject:</span>
                        <span class="text-gray-900"><?php echo htmlspecialchars($feedback['subject']); ?></span>
                    </div>
                    <div class="mb-2">
                        <span class="font-semibold text-gray-700">Rating:</span>
                        <span
                            class="text-yellow-500 text-lg"><?php echo str_repeat('★', (int) $feedback['rating']); ?></span>
                    </div>
                    <div class="mb-2">
                        <span class="font-semibold text-gray-700">Message:</span>
                        <div class="text-gray-800 bg-gray-50 rounded p-3 mt-1 whitespace-pre-line">
                            <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="mt-6 flex justify-center">
        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
            <?php if ($page > 1): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                <span class="sr-only">Previous</span>
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                    $isCurrentPage = $i === $page;
                    $pageClass = $isCurrentPage
                        ? 'relative inline-flex items-center px-4 py-2 border border-indigo-500 bg-indigo-50 text-sm font-medium text-indigo-600'
                        : 'relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50';
                    ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"
                class="<?php echo $pageClass; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                <span class="sr-only">Next</span>
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
</div>
<!-- Feedback Modal (hidden by default, shown with JS) -->
<div id="feedbackModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900" id="modalTitle">Feedback Details</h3>
                <button onclick="closeFeedbackModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="modalContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>
<script>
function showFeedbackModal(feedbackId) {
    fetch('view.php?id=' + feedbackId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('modalContent').innerHTML = data;
            document.getElementById('feedbackModal').classList.remove('hidden');
        })
        .catch(error => {
            alert('Failed to load feedback details');
        });
}

function closeFeedbackModal() {
    document.getElementById('feedbackModal').classList.add('hidden');
}
// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('feedbackModal');
    if (event.target === modal) {
        closeFeedbackModal();
    }
}
</script>
<?php include '../../includes/admin_footer.php'; ?>