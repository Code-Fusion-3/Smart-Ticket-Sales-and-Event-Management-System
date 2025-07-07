<?php
$pageTitle = "Agent Management";
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

// Filters
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build WHERE clause
$whereConditions = ["role = 'agent'"];
if (!empty($statusFilter)) {
    $whereConditions[] = "status = '" . $db->escape($statusFilter) . "'";
}
if (!empty($searchQuery)) {
    $whereConditions[] = "(username LIKE '%" . $db->escape($searchQuery) . "%' OR email LIKE '%" . $db->escape($searchQuery) . "%')";
}
$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Get total count
$countSql = "SELECT COUNT(*) as total FROM users $whereClause";
$totalResult = $db->fetchOne($countSql);
$totalAgents = $totalResult['total'];
$totalPages = ceil($totalAgents / $perPage);

// Get agents
$sql = "SELECT * FROM users $whereClause ORDER BY created_at DESC LIMIT $offset, $perPage";
$agents = $db->fetchAll($sql);

include '../../includes/admin_header.php';
?>
<div class="container mx-auto px-2 sm:px-4 lg:px-6 py-4 sm:py-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Agent Management</h1>
            <p class="text-gray-600 mt-2 text-sm sm:text-base">Create, view, and manage system agents</p>
        </div>
        <div class="flex gap-2">
            <a href="create.php"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm sm:text-base">
                <i class="fas fa-user-plus mr-2"></i>New Agent
            </a>
            <a href="../index.php"
                class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm sm:text-base">
                <i class="fas fa-arrow-left mr-2"></i>Dashboard
            </a>
        </div>
    </div>
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 alert-auto-hide">
            <i
                class="fas fa-check-circle mr-2"></i><?php echo $_SESSION['success_message'];
                unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 alert-auto-hide">
            <i
                class="fas fa-exclamation-circle mr-2"></i><?php echo $_SESSION['error_message'];
                unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-4 sm:p-6 mb-6">
        <form method="GET" action="" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>"
                        placeholder="Name or email..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                        <option value="">All Statuses</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="suspended" <?php echo $statusFilter === 'suspended' ? 'selected' : ''; ?>>Suspended
                        </option>
                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive
                        </option>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm">
                        <i class="fas fa-search mr-1"></i>Filter
                    </button>
                    <a href="index.php"
                        class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg transition duration-200 text-sm">
                        <i class="fas fa-times mr-1"></i>Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
    <!-- Agents Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($agents)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                <i class="fas fa-user-secret text-4xl text-gray-300 mb-4 block"></i>
                                No agents found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($agents as $agent): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    <?php echo htmlspecialchars($agent['username']); ?>
                                </td>
                                <td class="px-4 py-3 text-gray-900">
                                    <?php echo htmlspecialchars($agent['email']); ?>
                                </td>
                                <td class="px-4 py-3 text-gray-900">
                                    <?php echo htmlspecialchars($agent['phone_number']); ?>
                                </td>
                                <td class="px-4 py-3">
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
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center space-x-2">
                                        <a href="view.php?id=<?php echo $agent['id']; ?>"
                                            class="text-indigo-600 hover:text-indigo-900 text-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $agent['id']; ?>"
                                            class="text-blue-600 hover:text-blue-900 text-sm" title="Edit Agent">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($agent['status'] !== 'suspended'): ?>
                                            <a href="update_status.php?id=<?php echo $agent['id']; ?>&status=suspended"
                                                class="text-red-600 hover:text-red-900 text-sm" title="Suspend Agent"
                                                onclick="return confirm('Suspend this agent?');">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="update_status.php?id=<?php echo $agent['id']; ?>&status=active"
                                                class="text-green-600 hover:text-green-900 text-sm" title="Activate Agent"
                                                onclick="return confirm('Activate this agent?');">
                                                <i class="fas fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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
<?php include '../../includes/admin_footer.php'; ?>