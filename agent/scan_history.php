<?php
$pageTitle = "Scan History";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user has agent permission
checkPermission('agent');

$agentId = getCurrentUserId();

// Pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Filters
$statusFilter = $_GET['status'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$eventFilter = $_GET['event'] ?? '';

// Build WHERE clause
$whereConditions = ["tv.agent_id = $agentId"];

if (!empty($statusFilter)) {
    $whereConditions[] = "tv.status = '" . $db->escape($statusFilter) . "'";
}

if (!empty($startDate)) {
    $whereConditions[] = "DATE(tv.verification_time) >= '" . $db->escape($startDate) . "'";
}

if (!empty($endDate)) {
    $whereConditions[] = "DATE(tv.verification_time) <= '" . $db->escape($endDate) . "'";
}

if (!empty($eventFilter)) {
    $whereConditions[] = "e.title LIKE '%" . $db->escape($eventFilter) . "%'";
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total 
             FROM ticket_verifications tv
             JOIN tickets t ON tv.ticket_id = t.id
             JOIN events e ON t.event_id = e.id
             $whereClause";
$totalResult = $db->fetchOne($countSql);
$totalRecords = $totalResult['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get scan history
$sql = "SELECT 
            tv.*,
            t.qr_code,
            t.recipient_name,
            t.recipient_email,
            t.recipient_phone,
            t.purchase_price,
            e.title as event_title,
            e.start_date,
            e.start_time,
            e.venue,
            e.address,
            e.city,
            tt.name as ticket_type,
            u.username as agent_name
        FROM ticket_verifications tv
        JOIN tickets t ON tv.ticket_id = t.id
        JOIN events e ON t.event_id = e.id
        LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
        JOIN users u ON tv.agent_id = u.id
        $whereClause
        ORDER BY tv.verification_time DESC
        LIMIT $offset, $perPage";
$scanHistory = $db->fetchAll($sql);

// Get scan statistics
$statsSql = "SELECT 
                COUNT(*) as total_scans,
                COUNT(CASE WHEN tv.status = 'verified' THEN 1 END) as valid_scans,
                COUNT(CASE WHEN tv.status = 'rejected' THEN 1 END) as invalid_scans,
                COUNT(CASE WHEN tv.status = 'duplicate' THEN 1 END) as duplicate_scans
             FROM ticket_verifications tv
             JOIN tickets t ON tv.ticket_id = t.id
             JOIN events e ON t.event_id = e.id
             $whereClause";
$stats = $db->fetchOne($statsSql);

include '../includes/agent_header.php';
?>

<div class="container mx-auto px-4 py-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Scan History</h1>
            <p class="text-gray-600 mt-2">View your ticket verification history</p>
        </div>
        <div class="flex gap-2">
            <a href="index.php" class="text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
            </a>
            <a href="export.php?type=scans&<?php echo http_build_query($_GET); ?>"
                class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                <i class="fas fa-download mr-2"></i> Export
            </a>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-600"><?php echo $stats['total_scans']; ?></div>
                <div class="text-sm text-gray-500">Total Scans</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-green-600"><?php echo $stats['valid_scans']; ?></div>
                <div class="text-sm text-gray-500">Valid Tickets</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-red-600"><?php echo $stats['invalid_scans']; ?></div>
                <div class="text-sm text-gray-500">Invalid Tickets</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-yellow-600"><?php echo $stats['duplicate_scans']; ?></div>
                <div class="text-sm text-gray-500">Duplicate Scans</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form method="GET" action="" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status" name="status"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                        <option value="">All Statuses</option>
                        <option value="verified" <?php echo $statusFilter === 'verified' ? 'selected' : ''; ?>>Verified
                        </option>
                        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected
                        </option>
                        <option value="duplicate" <?php echo $statusFilter === 'duplicate' ? 'selected' : ''; ?>>Duplicate
                        </option>
                    </select>
                </div>

                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" id="start_date" name="start_date"
                        value="<?php echo htmlspecialchars($startDate); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                </div>

                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm">
                </div>

                <div>
                    <label for="event" class="block text-sm font-medium text-gray-700 mb-1">Event</label>
                    <input type="text" id="event" name="event" value="<?php echo htmlspecialchars($eventFilter); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:border-indigo-500 text-sm"
                        placeholder="Search by event name">
                </div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-search mr-2"></i> Filter
                </button>
                <a href="scan_history.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-times mr-2"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Scan History Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Recipient</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($scanHistory)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                <i class="fas fa-history text-4xl text-gray-300 mb-4 block"></i>
                                No scan history found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($scanHistory as $scan): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo formatDateTime($scan['verification_time']); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($scan['event_title']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo formatDate($scan['start_date']); ?> at
                                        <?php echo formatTime($scan['start_time']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($scan['venue']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($scan['ticket_type'] ?? 'General'); ?> •
                                        <?php echo formatCurrency($scan['purchase_price']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        <?php echo htmlspecialchars($scan['recipient_name'] ?? 'N/A'); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($scan['recipient_email'] ?? 'N/A'); ?>
                                    </div>
                                    <?php if (!empty($scan['recipient_phone'])): ?>
                                        <div class="text-xs text-gray-500">
                                            <?php echo htmlspecialchars($scan['recipient_phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                <?php
                                switch ($scan['status']) {
                                    case 'verified':
                                        echo 'bg-green-100 text-green-800';
                                        break;
                                    case 'rejected':
                                        echo 'bg-red-100 text-red-800';
                                        break;
                                    case 'duplicate':
                                        echo 'bg-yellow-100 text-yellow-800';
                                        break;
                                    default:
                                        echo 'bg-gray-100 text-gray-800';
                                }
                                ?>">
                                        <?php echo ucfirst($scan['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo htmlspecialchars($scan['notes'] ?? 'N/A'); ?>
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

<?php include '../includes/agent_footer.php'; ?>