<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Check if user has agent permission
checkPermission('agent');

$agentId = getCurrentUserId();
$type = $_GET['type'] ?? 'verifications';
$format = $_GET['format'] ?? 'csv';

// Set default type if not provided
if (empty($type)) {
    $type = 'verifications';
}

// Validate type
$validTypes = ['verifications', 'scans'];
if (!in_array($type, $validTypes)) {
    header('Location: index.php?error=invalid_export_type');
    exit;
}

// Build WHERE clause for agent's data
$whereConditions = ["tv.agent_id = $agentId"];

// Add filters
if (!empty($_GET['status'])) {
    $whereConditions[] = "tv.status = '" . $db->escape($_GET['status']) . "'";
}
if (!empty($_GET['start_date'])) {
    $whereConditions[] = "DATE(tv.verification_time) >= '" . $db->escape($_GET['start_date']) . "'";
}
if (!empty($_GET['end_date'])) {
    $whereConditions[] = "DATE(tv.verification_time) <= '" . $db->escape($_GET['end_date']) . "'";
}
if (!empty($_GET['event'])) {
    $whereConditions[] = "e.title LIKE '%" . $db->escape($_GET['event']) . "%'";
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

switch ($type) {
    case 'verifications':
        exportVerifications($agentId, $whereClause, $format);
        break;
    case 'scans':
        exportScans($agentId, $whereClause, $format);
        break;
    default:
        header('Location: index.php?error=invalid_export_type');
        exit;
}

function exportVerifications($agentId, $whereClause, $format)
{
    global $db;

    $sql = "SELECT 
                tv.verification_time,
                tv.status,
                tv.notes,
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
                tt.name as ticket_type
            FROM ticket_verifications tv
            JOIN tickets t ON tv.ticket_id = t.id
            JOIN events e ON t.event_id = e.id
            LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
            $whereClause
            ORDER BY tv.verification_time DESC";

    $data = $db->fetchAll($sql);

    $filename = 'verification_report_' . date('Y-m-d_H-i-s');

    if ($format === 'json') {
        exportAsJSON($data, $filename);
    } else {
        exportAsCSV($data, $filename);
    }
}

function exportScans($agentId, $whereClause, $format)
{
    global $db;

    $sql = "SELECT 
                DATE(tv.verification_time) as scan_date,
                COUNT(*) as total_scans,
                COUNT(CASE WHEN tv.status = 'verified' THEN 1 END) as successful_scans,
                COUNT(CASE WHEN tv.status = 'rejected' THEN 1 END) as failed_scans,
                COUNT(CASE WHEN tv.status = 'duplicate' THEN 1 END) as duplicate_scans,
                ROUND((COUNT(CASE WHEN tv.status = 'verified' THEN 1 END) / COUNT(*)) * 100, 2) as success_rate
            FROM ticket_verifications tv
            JOIN tickets t ON tv.ticket_id = t.id
            JOIN events e ON t.event_id = e.id
            $whereClause
            GROUP BY DATE(tv.verification_time)
            ORDER BY scan_date DESC";

    $data = $db->fetchAll($sql);

    $filename = 'scan_summary_' . date('Y-m-d_H-i-s');

    if ($format === 'json') {
        exportAsJSON($data, $filename);
    } else {
        exportAsCSV($data, $filename);
    }
}

function exportAsCSV($data, $filename)
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for proper encoding
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    if (!empty($data)) {
        // Write headers
        fputcsv($output, array_keys($data[0]), ',', '"', '\\');

        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row, ',', '"', '\\');
        }
    }

    fclose($output);
    exit;
}

function exportAsJSON($data, $filename)
{
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

    $exportData = [
        'export_info' => [
            'generated_at' => date('Y-m-d H:i:s'),
            'total_records' => count($data),
            'export_type' => 'agent_verifications'
        ],
        'data' => $data
    ];

    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>