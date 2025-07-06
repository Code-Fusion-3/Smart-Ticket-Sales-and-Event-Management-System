<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/pdf_generator.php';

// Check if user has agent permission
checkPermission('agent');

$agentId = getCurrentUserId();
$type = $_GET['type'] ?? '';
$filters = $_GET;

// Remove type from filters
unset($filters['type']);

switch ($type) {
    case 'scans':
        $pdfGenerator->generateScanReport($agentId, $filters);
        break;
    case 'verifications':
        generateAgentVerificationReportPDF($agentId, $filters);
        break;
    default:
        http_response_code(400);
        echo 'Invalid report type';
        exit;
}

function generateAgentVerificationReportPDF($agentId, $filters)
{
    global $db, $pdfGenerator;

    // Build query with filters
    $whereConditions = ["tv.agent_id = $agentId"];

    if (!empty($filters['status'])) {
        $whereConditions[] = "tv.status = '" . $db->escape($filters['status']) . "'";
    }
    if (!empty($filters['start_date'])) {
        $whereConditions[] = "DATE(tv.verification_time) >= '" . $db->escape($filters['start_date']) . "'";
    }
    if (!empty($filters['end_date'])) {
        $whereConditions[] = "DATE(tv.verification_time) <= '" . $db->escape($filters['end_date']) . "'";
    }

    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

    $sql = "SELECT 
                DATE(tv.verification_time) as scan_date,
                COUNT(*) as total_scans,
                COUNT(CASE WHEN tv.status = 'verified' THEN 1 END) as successful_scans,
                COUNT(CASE WHEN tv.status = 'rejected' THEN 1 END) as failed_scans,
                COUNT(CASE WHEN tv.status = 'duplicate' THEN 1 END) as duplicate_scans,
                ROUND((COUNT(CASE WHEN tv.status = 'verified' THEN 1 END) / COUNT(*)) * 100, 2) as success_rate
            FROM ticket_verifications tv
            $whereClause
            GROUP BY DATE(tv.verification_time)
            ORDER BY scan_date DESC";

    $verifications = $db->fetchAll($sql);

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Verification Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
            .title { font-size: 24px; font-weight: bold; color: #333; }
            .subtitle { font-size: 14px; color: #666; margin-top: 5px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .success-rate-high { color: green; }
            .success-rate-medium { color: orange; }
            .success-rate-low { color: red; }
            .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="title">Verification Report</div>
            <div class="subtitle">Generated on ' . date('F j, Y \a\t g:i A') . '</div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Total Scans</th>
                    <th>Successful</th>
                    <th>Failed</th>
                    <th>Duplicate</th>
                    <th>Success Rate</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($verifications as $verification) {
        $successRateClass = '';
        if ($verification['success_rate'] >= 80) {
            $successRateClass = 'success-rate-high';
        } elseif ($verification['success_rate'] >= 60) {
            $successRateClass = 'success-rate-medium';
        } else {
            $successRateClass = 'success-rate-low';
        }

        $html .= '
                <tr>
                    <td>' . date('M j, Y', strtotime($verification['scan_date'])) . '</td>
                    <td>' . $verification['total_scans'] . '</td>
                    <td>' . $verification['successful_scans'] . '</td>
                    <td>' . $verification['failed_scans'] . '</td>
                    <td>' . $verification['duplicate_scans'] . '</td>
                    <td class="' . $successRateClass . '">' . $verification['success_rate'] . '%</td>
                </tr>';
    }

    $html .= '
            </tbody>
        </table>
        
        <div class="footer">
            <p>Smart Ticket System - Verification Report</p>
        </div>
    </body>
    </html>';

    $pdfGenerator->convertHTMLToPDF($html, 'verification_report_' . date('Y-m-d') . '.pdf');
}
?>