<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

// Check if user has admin permission
checkPermission('admin');

// Get date range
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$format = $_GET['format'] ?? 'csv';

// Set headers for download
if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="analytics_report_' . $startDate . '_to_' . $endDate . '.csv"');
} else {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="analytics_report_' . $startDate . '_to_' . $endDate . '.json"');
}

// Get comprehensive report data
$reportData = generateReportData($startDate, $endDate);

if ($format === 'csv') {
    exportToCSV($reportData);
} else {
    exportToJSON($reportData);
}

function generateReportData($startDate, $endDate)
{
    global $db;

    $data = [];

    // Summary Statistics
    $summarySql = "SELECT 
                        (SELECT COUNT(*) FROM users WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate') as new_users,
                        (SELECT COUNT(*) FROM events WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate') as new_events,
                        (SELECT COUNT(*) FROM tickets WHERE status = 'sold' AND DATE(created_at) BETWEEN '$startDate' AND '$endDate') as tickets_sold,
                        (SELECT SUM(amount) FROM transactions WHERE type = 'purchase' AND status = 'completed' AND DATE(created_at) BETWEEN '$startDate' AND '$endDate') as ticket_revenue,
                        (SELECT SUM(amount) FROM transactions WHERE type = 'system_fee' AND status = 'completed' AND DATE(created_at) BETWEEN '$startDate' AND '$endDate') as system_fees,
                        (SELECT COUNT(*) FROM withdrawals WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate') as withdrawal_requests,
                        (SELECT SUM(amount) FROM withdrawals WHERE status = 'completed' AND DATE(created_at) BETWEEN '$startDate' AND '$endDate') as withdrawals_completed";
    $data['summary'] = $db->fetchOne($summarySql);

    // Daily Revenue Data
    $revenueSql = "SELECT 
                        DATE(t.created_at) as date,
                        SUM(CASE WHEN t.type = 'purchase' THEN t.amount ELSE 0 END) as ticket_sales,
                        SUM(CASE WHEN t.type = 'system_fee' THEN t.amount ELSE 0 END) as system_fees,
                        COUNT(CASE WHEN t.type = 'purchase' THEN 1 END) as ticket_count
                    FROM transactions t
                    WHERE t.status = 'completed'
                    AND DATE(t.created_at) BETWEEN '$startDate' AND '$endDate'
                    GROUP BY DATE(t.created_at)
                    ORDER BY date ASC";
    $data['daily_revenue'] = $db->fetchAll($revenueSql);

    // Top Events
    $topEventsSql = "SELECT 
                        e.id,
                        e.title,
                        e.start_date,
                        u.username as planner,
                        COUNT(t.id) as tickets_sold,
                        SUM(t.purchase_price) as total_revenue,
                        AVG(t.purchase_price) as avg_ticket_price
                    FROM events e
                    LEFT JOIN tickets t ON e.id = t.event_id AND t.status = 'sold'
                    JOIN users u ON e.planner_id = u.id
                    WHERE DATE(e.created_at) BETWEEN '$startDate' AND '$endDate'
                    GROUP BY e.id
                    HAVING tickets_sold > 0
                    ORDER BY total_revenue DESC
                    LIMIT 20";
    $data['top_events'] = $db->fetchAll($topEventsSql);

    // Top Planners
    $topPlannersSql = "SELECT 
                            u.id,
                            u.username,
                            u.email,
                            COUNT(DISTINCT e.id) as events_created,
                            COUNT(t.id) as tickets_sold,
                            SUM(t.purchase_price) as total_revenue
                        FROM users u
                        JOIN events e ON u.id = e.planner_id
                        LEFT JOIN tickets t ON e.id = t.event_id AND t.status = 'sold'
                        WHERE u.role = 'event_planner'
                        AND DATE(e.created_at) BETWEEN '$startDate' AND '$endDate'
                        GROUP BY u.id
                        HAVING tickets_sold > 0
                        ORDER BY total_revenue DESC
                        LIMIT 20";
    $data['top_planners'] = $db->fetchAll($topPlannersSql);

    // User Registration Data
    $userSql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as new_users,
                    SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as customers,
                    SUM(CASE WHEN role = 'event_planner' THEN 1 ELSE 0 END) as planners,
                    SUM(CASE WHEN role = 'agent' THEN 1 ELSE 0 END) as agents
                FROM users
                WHERE DATE(created_at) BETWEEN '$startDate' AND '$endDate'
                GROUP BY DATE(created_at)
                ORDER BY date ASC";
    $data['user_registrations'] = $db->fetchAll($userSql);

    return $data;
}

function exportToCSV($data)
{
    $output = fopen('php://output', 'w');

    // Report Header
    fputcsv($output, ['Analytics Report'], ',', '"', '\\');
    fputcsv($output, ['Generated on: ' . date('Y-m-d H:i:s')], ',', '"', '\\');
    fputcsv($output, [], ',', '"', '\\');

    // Summary Section
    fputcsv($output, ['SUMMARY STATISTICS'], ',', '"', '\\');
    fputcsv($output, ['Metric', 'Value'], ',', '"', '\\');
    fputcsv($output, ['New Users', $data['summary']['new_users'] ?? 0], ',', '"', '\\');
    fputcsv($output, ['New Events', $data['summary']['new_events'] ?? 0], ',', '"', '\\');
    fputcsv($output, ['Tickets Sold', $data['summary']['tickets_sold'] ?? 0], ',', '"', '\\');
    fputcsv($output, ['Ticket Revenue', $data['summary']['ticket_revenue'] ?? 0], ',', '"', '\\');
    fputcsv($output, ['System Fees', $data['summary']['system_fees'] ?? 0], ',', '"', '\\');
    fputcsv($output, ['Withdrawal Requests', $data['summary']['withdrawal_requests'] ?? 0], ',', '"', '\\');
    fputcsv($output, ['Withdrawals Completed', $data['summary']['withdrawals_completed'] ?? 0], ',', '"', '\\');
    fputcsv($output, [], ',', '"', '\\');

    // Daily Revenue Section
    fputcsv($output, ['DAILY REVENUE BREAKDOWN'], ',', '"', '\\');
    fputcsv($output, ['Date', 'Tickets Sold', 'Ticket Revenue', 'System Fees', 'Total Revenue'], ',', '"', '\\');

    foreach ($data['daily_revenue'] as $revenue) {
        $totalRevenue = ($revenue['ticket_sales'] ?? 0) + ($revenue['system_fees'] ?? 0);
        fputcsv($output, [
            $revenue['date'],
            $revenue['ticket_count'] ?? 0,
            $revenue['ticket_sales'] ?? 0,
            $revenue['system_fees'] ?? 0,
            $totalRevenue
        ], ',', '"', '\\');
    }
    fputcsv($output, [], ',', '"', '\\');

    // Top Events Section
    fputcsv($output, ['TOP PERFORMING EVENTS'], ',', '"', '\\');
    fputcsv($output, ['Event ID', 'Event Title', 'Start Date', 'Planner', 'Tickets Sold', 'Total Revenue', 'Avg Ticket Price'], ',', '"', '\\');

    foreach ($data['top_events'] as $event) {
        fputcsv($output, [
            $event['id'],
            $event['title'],
            $event['start_date'],
            $event['planner'],
            $event['tickets_sold'] ?? 0,
            $event['total_revenue'] ?? 0,
            $event['avg_ticket_price'] ?? 0
        ], ',', '"', '\\');
    }
    fputcsv($output, [], ',', '"', '\\');

    // Top Planners Section
    fputcsv($output, ['TOP EVENT PLANNERS'], ',', '"', '\\');
    fputcsv($output, ['Planner ID', 'Username', 'Email', 'Events Created', 'Tickets Sold', 'Total Revenue'], ',', '"', '\\');

    foreach ($data['top_planners'] as $planner) {
        fputcsv($output, [
            $planner['id'],
            $planner['username'],
            $planner['email'],
            $planner['events_created'] ?? 0,
            $planner['tickets_sold'] ?? 0,
            $planner['total_revenue'] ?? 0
        ], ',', '"', '\\');
    }
    fputcsv($output, [], ',', '"', '\\');

    // User Registration Section
    fputcsv($output, ['DAILY USER REGISTRATIONS'], ',', '"', '\\');
    fputcsv($output, ['Date', 'Total New Users', 'Customers', 'Event Planners', 'Agents'], ',', '"', '\\');

    foreach ($data['user_registrations'] as $userReg) {
        fputcsv($output, [
            $userReg['date'],
            $userReg['new_users'] ?? 0,
            $userReg['customers'] ?? 0,
            $userReg['planners'] ?? 0,
            $userReg['agents'] ?? 0
        ], ',', '"', '\\');
    }

    fclose($output);
}

function exportToJSON($data)
{
    // Add metadata
    $export = [
        'report_metadata' => [
            'generated_at' => date('Y-m-d H:i:s'),
            'report_type' => 'analytics_report',
            'date_range' => [
                'start_date' => $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
                'end_date' => $_GET['end_date'] ?? date('Y-m-d')
            ]
        ],
        'data' => $data
    ];

    echo json_encode($export, JSON_PRETTY_PRINT);
}
?>