<?php
$pageTitle = "Agent Dashboard";
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user has agent permission
checkPermission('agent');

$agentId = getCurrentUserId();

// Get agent information
$agentSql = "SELECT username, email, phone_number, profile_image FROM users WHERE id = $agentId";
$agent = $db->fetchOne($agentSql);

// Get today's statistics
$today = date('Y-m-d');
$todayStatsSql = "SELECT 
                    COUNT(*) as total_scans,
                    COUNT(CASE WHEN status = 'verified' THEN 1 END) as valid_scans,
                    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as invalid_scans,
                    COUNT(CASE WHEN status = 'duplicate' THEN 1 END) as duplicate_scans
                  FROM ticket_verifications 
                  WHERE agent_id = $agentId AND DATE(verification_time) = '$today'";
$todayStats = $db->fetchOne($todayStatsSql);

// Get this week's statistics
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));
$weekStatsSql = "SELECT 
                    COUNT(*) as total_scans,
                    COUNT(CASE WHEN status = 'verified' THEN 1 END) as valid_scans,
                    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as invalid_scans,
                    COUNT(CASE WHEN status = 'duplicate' THEN 1 END) as duplicate_scans
                  FROM ticket_verifications 
                  WHERE agent_id = $agentId AND DATE(verification_time) BETWEEN '$weekStart' AND '$weekEnd'";
$weekStats = $db->fetchOne($weekStatsSql);

// Get recent scans
$recentScansSql = "SELECT 
                    tv.*,
                    t.recipient_name,
                    t.recipient_email,
                    e.title as event_title,
                    e.venue,
                    e.start_date,
                    e.start_time
                  FROM ticket_verifications tv
                  JOIN tickets t ON tv.ticket_id = t.id
                  JOIN events e ON t.event_id = e.id
                  WHERE tv.agent_id = $agentId
                  ORDER BY tv.verification_time DESC
                  LIMIT 10";
$recentScans = $db->fetchAll($recentScansSql);

// Get upcoming events for today
$upcomingEventsSql = "SELECT 
                        e.*,
                        COUNT(t.id) as total_tickets,
                        COUNT(CASE WHEN t.status = 'sold' THEN 1 END) as sold_tickets
                      FROM events e
                      LEFT JOIN tickets t ON e.id = t.event_id
                      WHERE e.start_date = '$today' AND e.status = 'active'
                      GROUP BY e.id
                      ORDER BY e.start_time ASC";
$upcomingEvents = $db->fetchAll($upcomingEventsSql);

include '../includes/agent_header.php';
?>

<div class="container mx-auto px-4 py-6">
    <!-- Welcome Section -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-lg shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between">
            <div class="text-white">
                <h1 class="text-3xl font-bold mb-2">Welcome back, <?php echo htmlspecialchars($agent['username']); ?>!
                </h1>
                <p class="text-indigo-100">Ready to verify some tickets today?</p>
            </div>
            <div class="text-right text-white">
                <div class="text-sm text-indigo-100">Today's Date</div>
                <div class="text-xl font-bold"><?php echo date('l, F j, Y'); ?></div>
                <div class="text-sm text-indigo-100"><?php echo date('g:i A'); ?></div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <a href="verify_ticket.php"
            class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow duration-200">
            <div class="flex items-center">
                <div class="bg-green-100 p-3 rounded-full mr-4">
                    <i class="fas fa-qrcode text-2xl text-green-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Verify Ticket</h3>
                    <p class="text-gray-600">Scan or enter ticket details</p>
                </div>
            </div>
        </a>

        <a href="scan_history.php"
            class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow duration-200">
            <div class="flex items-center">
                <div class="bg-blue-100 p-3 rounded-full mr-4">
                    <i class="fas fa-history text-2xl text-blue-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Scan History</h3>
                    <p class="text-gray-600">View verification records</p>
                </div>
            </div>
        </a>

        <a href="export.php?type=verifications"
            class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow duration-200">
            <div class="flex items-center">
                <div class="bg-purple-100 p-3 rounded-full mr-4">
                    <i class="fas fa-download text-2xl text-purple-600"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Export Reports</h3>
                    <p class="text-gray-600">Download verification data</p>
                </div>
            </div>
        </a>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- Today's Statistics -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Today's Statistics</h2>
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center">
                    <div class="text-3xl font-bold text-blue-600"><?php echo $todayStats['total_scans']; ?></div>
                    <div class="text-sm text-gray-500">Total Scans</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-green-600"><?php echo $todayStats['valid_scans']; ?></div>
                    <div class="text-sm text-gray-500">Valid Tickets</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-red-600"><?php echo $todayStats['invalid_scans']; ?></div>
                    <div class="text-sm text-gray-500">Invalid Tickets</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-yellow-600"><?php echo $todayStats['duplicate_scans']; ?></div>
                    <div class="text-sm text-gray-500">Duplicate Scans</div>
                </div>
            </div>
        </div>

        <!-- This Week's Statistics -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">This Week's Statistics</h2>
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center">
                    <div class="text-3xl font-bold text-blue-600"><?php echo $weekStats['total_scans']; ?></div>
                    <div class="text-sm text-gray-500">Total Scans</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-green-600"><?php echo $weekStats['valid_scans']; ?></div>
                    <div class="text-sm text-gray-500">Valid Tickets</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-red-600"><?php echo $weekStats['invalid_scans']; ?></div>
                    <div class="text-sm text-gray-500">Invalid Tickets</div>
                </div>
                <div class="text-center">
                    <div class="text-3xl font-bold text-yellow-600"><?php echo $weekStats['duplicate_scans']; ?></div>
                    <div class="text-sm text-gray-500">Duplicate Scans</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Scans and Upcoming Events -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Scans -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Recent Scans</h2>
            </div>
            <div class="p-6">
                <?php if (empty($recentScans)): ?>
                    <div class="text-center text-gray-500 py-8">
                        <i class="fas fa-history text-4xl text-gray-300 mb-4 block"></i>
                        <p>No recent scans found</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($recentScans as $scan): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex-1">
                                    <div class="flex items-center mb-1">
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
                                        <span class="text-xs text-gray-500 ml-2">
                                            <?php echo formatDateTime($scan['verification_time']); ?>
                                        </span>
                                    </div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($scan['event_title']); ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($scan['recipient_name'] ?? 'N/A'); ?> •
                                        <?php echo htmlspecialchars($scan['venue']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="scan_history.php" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                            View All Scans <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Events -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Today's Events</h2>
            </div>
            <div class="p-6">
                <?php if (empty($upcomingEvents)): ?>
                    <div class="text-center text-gray-500 py-8">
                        <i class="fas fa-calendar text-4xl text-gray-300 mb-4 block"></i>
                        <p>No events scheduled for today</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($upcomingEvents as $event): ?>
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-lg font-medium text-gray-900">
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </h3>
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Active
                                    </span>
                                </div>
                                <div class="text-sm text-gray-600 mb-2">
                                    <i class="fas fa-clock mr-1"></i>
                                    <?php echo formatTime($event['start_time']); ?> -
                                    <?php echo formatTime($event['end_time']); ?>
                                </div>
                                <div class="text-sm text-gray-600 mb-3">
                                    <i class="fas fa-map-marker-alt mr-1"></i>
                                    <?php echo htmlspecialchars($event['venue']); ?>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>Tickets: <?php echo $event['sold_tickets']; ?>/<?php echo $event['total_tickets']; ?>
                                        sold</span>
                                    <span><?php echo formatCurrency($event['ticket_price']); ?> per ticket</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Agent Profile Card -->
    <div class="mt-6 bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Agent Profile</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="flex items-center">
                <div class="bg-indigo-100 p-3 rounded-full mr-4">
                    <i class="fas fa-user text-2xl text-indigo-600"></i>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Username</div>
                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($agent['username']); ?></div>
                </div>
            </div>
            <div class="flex items-center">
                <div class="bg-green-100 p-3 rounded-full mr-4">
                    <i class="fas fa-envelope text-2xl text-green-600"></i>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Email</div>
                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($agent['email']); ?></div>
                </div>
            </div>
            <div class="flex items-center">
                <div class="bg-blue-100 p-3 rounded-full mr-4">
                    <i class="fas fa-phone text-2xl text-blue-600"></i>
                </div>
                <div>
                    <div class="text-sm text-gray-500">Phone</div>
                    <div class="font-medium text-gray-900"><?php echo htmlspecialchars($agent['phone_number']); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/agent_footer.php'; ?>