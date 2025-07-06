<?php
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

// Simple PDF generation using HTML to PDF conversion
class PDFGenerator
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function generateTransactionReport($userId, $userType, $filters = [])
    {
        $html = $this->getTransactionReportHTML($userId, $userType, $filters);
        return $this->convertHTMLToPDF($html, 'transaction_report_' . date('Y-m-d') . '.pdf');
    }

    public function generateTicketReport($userId, $userType, $filters = [])
    {
        $html = $this->getTicketReportHTML($userId, $userType, $filters);
        return $this->convertHTMLToPDF($html, 'ticket_report_' . date('Y-m-d') . '.pdf');
    }

    public function generateFinancialReport($userId, $userType, $filters = [])
    {
        $html = $this->getFinancialReportHTML($userId, $userType, $filters);
        return $this->convertHTMLToPDF($html, 'financial_report_' . date('Y-m-d') . '.pdf');
    }

    public function generateScanReport($agentId, $filters = [])
    {
        $html = $this->getScanReportHTML($agentId, $filters);
        return $this->convertHTMLToPDF($html, 'scan_report_' . date('Y-m-d') . '.pdf');
    }

    private function getTransactionReportHTML($userId, $userType, $filters)
    {
        // Build query based on user type
        $whereConditions = [];
        $joinClause = "";

        switch ($userType) {
            case 'admin':
                $joinClause = "LEFT JOIN users u ON t.user_id = u.id";
                break;
            case 'planner':
                $whereConditions[] = "t.user_id = $userId";
                break;
            case 'customer':
                $whereConditions[] = "t.user_id = $userId";
                break;
        }

        // Add filters
        if (!empty($filters['type'])) {
            $whereConditions[] = "t.type = '" . $this->db->escape($filters['type']) . "'";
        }
        if (!empty($filters['status'])) {
            $whereConditions[] = "t.status = '" . $this->db->escape($filters['status']) . "'";
        }
        if (!empty($filters['start_date'])) {
            $whereConditions[] = "DATE(t.created_at) >= '" . $this->db->escape($filters['start_date']) . "'";
        }
        if (!empty($filters['end_date'])) {
            $whereConditions[] = "DATE(t.created_at) <= '" . $this->db->escape($filters['end_date']) . "'";
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "SELECT 
                    t.*,
                    e.title as event_title,
                    u.username as user_name
                FROM transactions t
                LEFT JOIN tickets tk ON t.reference_id = tk.id AND t.type = 'purchase'
                LEFT JOIN events e ON tk.event_id = e.id
                $joinClause
                $whereClause
                ORDER BY t.created_at DESC
                LIMIT 100";

        $transactions = $this->db->fetchAll($sql);

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Transaction Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .title { font-size: 24px; font-weight: bold; color: #333; }
                .subtitle { font-size: 14px; color: #666; margin-top: 5px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .status-verified { color: green; }
                .status-pending { color: orange; }
                .status-failed { color: red; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">Transaction Report</div>
                <div class="subtitle">Generated on ' . date('F j, Y \a\t g:i A') . '</div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Event</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($transactions as $transaction) {
            $html .= '
                    <tr>
                        <td>' . date('M j, Y', strtotime($transaction['created_at'])) . '</td>
                        <td>' . htmlspecialchars($transaction['description'] ?? 'Transaction') . '</td>
                        <td>' . ucfirst($transaction['type']) . '</td>
                        <td>' . formatCurrency($transaction['amount']) . '</td>
                        <td class="status-' . $transaction['status'] . '">' . ucfirst($transaction['status']) . '</td>
                        <td>' . htmlspecialchars($transaction['event_title'] ?? 'N/A') . '</td>
                    </tr>';
        }

        $html .= '
                </tbody>
            </table>
            
            <div class="footer">
                <p>Smart Ticket System - Transaction Report</p>
            </div>
        </body>
        </html>';

        return $html;
    }

    private function getTicketReportHTML($userId, $userType, $filters)
    {
        // Build query based on user type
        $whereConditions = [];

        switch ($userType) {
            case 'admin':
                // Admin sees all tickets
                break;
            case 'planner':
                $whereConditions[] = "e.planner_id = $userId";
                break;
            case 'customer':
                $whereConditions[] = "t.user_id = $userId";
                break;
        }

        // Add filters
        if (!empty($filters['status'])) {
            $whereConditions[] = "t.status = '" . $this->db->escape($filters['status']) . "'";
        }
        if (!empty($filters['start_date'])) {
            $whereConditions[] = "DATE(t.created_at) >= '" . $this->db->escape($filters['start_date']) . "'";
        }
        if (!empty($filters['end_date'])) {
            $whereConditions[] = "DATE(t.created_at) <= '" . $this->db->escape($filters['end_date']) . "'";
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "SELECT 
                    t.*,
                    e.title as event_title,
                    e.start_date,
                    e.start_time,
                    e.venue,
                    tt.name as ticket_type,
                    u.username as planner_name
                FROM tickets t
                JOIN events e ON t.event_id = e.id
                LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
                LEFT JOIN users u ON e.planner_id = u.id
                $whereClause
                ORDER BY t.created_at DESC
                LIMIT 100";

        $tickets = $this->db->fetchAll($sql);

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Ticket Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .title { font-size: 24px; font-weight: bold; color: #333; }
                .subtitle { font-size: 14px; color: #666; margin-top: 5px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .status-sold { color: green; }
                .status-available { color: blue; }
                .status-used { color: orange; }
                .status-cancelled { color: red; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">Ticket Report</div>
                <div class="subtitle">Generated on ' . date('F j, Y \a\t g:i A') . '</div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Event</th>
                        <th>Type</th>
                        <th>Recipient</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Purchase Date</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($tickets as $ticket) {
            $html .= '
                    <tr>
                        <td>' . $ticket['id'] . '</td>
                        <td>' . htmlspecialchars($ticket['event_title']) . '</td>
                        <td>' . htmlspecialchars($ticket['ticket_type'] ?? 'General') . '</td>
                        <td>' . htmlspecialchars($ticket['recipient_name'] ?? 'N/A') . '</td>
                        <td>' . formatCurrency($ticket['purchase_price']) . '</td>
                        <td class="status-' . $ticket['status'] . '">' . ucfirst($ticket['status']) . '</td>
                        <td>' . date('M j, Y', strtotime($ticket['created_at'])) . '</td>
                    </tr>';
        }

        $html .= '
                </tbody>
            </table>
            
            <div class="footer">
                <p>Smart Ticket System - Ticket Report</p>
            </div>
        </body>
        </html>';

        return $html;
    }

    private function getFinancialReportHTML($userId, $userType, $filters)
    {
        // Get financial summary
        $whereConditions = [];

        switch ($userType) {
            case 'admin':
                // Admin sees all financial data
                break;
            case 'planner':
                $whereConditions[] = "t.user_id = $userId";
                break;
            case 'customer':
                $whereConditions[] = "t.user_id = $userId";
                break;
        }

        // Add filters
        if (!empty($filters['start_date'])) {
            $whereConditions[] = "DATE(t.created_at) >= '" . $this->db->escape($filters['start_date']) . "'";
        }
        if (!empty($filters['end_date'])) {
            $whereConditions[] = "DATE(t.created_at) <= '" . $this->db->escape($filters['end_date']) . "'";
        }

        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "SELECT 
                    COUNT(*) as total_transactions,
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
                    SUM(CASE WHEN status = 'failed' THEN amount ELSE 0 END) as failed_amount,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_count
                FROM transactions t
                $whereClause";

        $summary = $this->db->fetchOne($sql);

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Financial Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .title { font-size: 24px; font-weight: bold; color: #333; }
                .subtitle { font-size: 14px; color: #666; margin-top: 5px; }
                .summary { margin: 20px 0; }
                .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
                .summary-card { border: 1px solid #ddd; padding: 15px; text-align: center; }
                .summary-card h3 { margin: 0 0 10px 0; color: #333; }
                .summary-card .amount { font-size: 24px; font-weight: bold; color: #2c5aa0; }
                .summary-card .count { font-size: 18px; color: #666; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">Financial Report</div>
                <div class="subtitle">Generated on ' . date('F j, Y \a\t g:i A') . '</div>
            </div>
            
            <div class="summary">
                <h2>Financial Summary</h2>
                <div class="summary-grid">
                    <div class="summary-card">
                        <h3>Total Revenue</h3>
                        <div class="amount">' . formatCurrency($summary['total_revenue']) . '</div>
                        <div class="count">' . $summary['completed_count'] . ' transactions</div>
                    </div>
                    <div class="summary-card">
                        <h3>Pending Amount</h3>
                        <div class="amount">' . formatCurrency($summary['pending_amount']) . '</div>
                        <div class="count">' . $summary['pending_count'] . ' transactions</div>
                    </div>
                    <div class="summary-card">
                        <h3>Failed Amount</h3>
                        <div class="amount">' . formatCurrency($summary['failed_amount']) . '</div>
                        <div class="count">' . $summary['failed_count'] . ' transactions</div>
                    </div>
                    <div class="summary-card">
                        <h3>Total Transactions</h3>
                        <div class="amount">' . $summary['total_transactions'] . '</div>
                        <div class="count">All time</div>
                    </div>
                </div>
            </div>
            
            <div class="footer">
                <p>Smart Ticket System - Financial Report</p>
            </div>
        </body>
        </html>';

        return $html;
    }

    private function getScanReportHTML($agentId, $filters)
    {
        // Build query with filters
        $whereConditions = ["tv.agent_id = $agentId"];

        if (!empty($filters['status'])) {
            $whereConditions[] = "tv.status = '" . $this->db->escape($filters['status']) . "'";
        }
        if (!empty($filters['start_date'])) {
            $whereConditions[] = "DATE(tv.verification_time) >= '" . $this->db->escape($filters['start_date']) . "'";
        }
        if (!empty($filters['end_date'])) {
            $whereConditions[] = "DATE(tv.verification_time) <= '" . $this->db->escape($filters['end_date']) . "'";
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        $sql = "SELECT 
                    tv.*,
                    t.recipient_name,
                    t.recipient_email,
                    e.title as event_title,
                    e.start_date,
                    e.venue,
                    tt.name as ticket_type
                FROM ticket_verifications tv
                JOIN tickets t ON tv.ticket_id = t.id
                JOIN events e ON t.event_id = e.id
                LEFT JOIN ticket_types tt ON t.ticket_type_id = tt.id
                $whereClause
                ORDER BY tv.verification_time DESC
                LIMIT 100";

        $scans = $this->db->fetchAll($sql);

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Scan Report</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
                .title { font-size: 24px; font-weight: bold; color: #333; }
                .subtitle { font-size: 14px; color: #666; margin-top: 5px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .status-verified { color: green; }
                .status-rejected { color: red; }
                .status-duplicate { color: orange; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">Scan Report</div>
                <div class="subtitle">Generated on ' . date('F j, Y \a\t g:i A') . '</div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Event</th>
                        <th>Recipient</th>
                        <th>Ticket Type</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($scans as $scan) {
            $html .= '
                    <tr>
                        <td>' . date('M j, Y g:i A', strtotime($scan['verification_time'])) . '</td>
                        <td>' . htmlspecialchars($scan['event_title']) . '</td>
                        <td>' . htmlspecialchars($scan['recipient_name'] ?? 'N/A') . '</td>
                        <td>' . htmlspecialchars($scan['ticket_type'] ?? 'General') . '</td>
                        <td class="status-' . $scan['status'] . '">' . ucfirst($scan['status']) . '</td>
                        <td>' . htmlspecialchars($scan['notes'] ?? 'N/A') . '</td>
                    </tr>';
        }

        $html .= '
                </tbody>
            </table>
            
            <div class="footer">
                <p>Smart Ticket System - Scan Report</p>
            </div>
        </body>
        </html>';

        return $html;
    }

    private function convertHTMLToPDF($html, $filename)
    {
        // For now, we'll use a simple approach with wkhtmltopdf if available
        // Otherwise, we'll return the HTML content for manual conversion

        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_') . '.html';
        file_put_contents($tempFile, $html);

        // Try to use wkhtmltopdf if available
        $outputFile = tempnam(sys_get_temp_dir(), 'pdf_output_') . '.pdf';
        $command = "wkhtmltopdf --quiet --encoding utf-8 '$tempFile' '$outputFile' 2>/dev/null";

        if (exec($command) !== false && file_exists($outputFile)) {
            // Set headers for PDF download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($outputFile));

            // Output PDF content
            readfile($outputFile);

            // Clean up
            unlink($tempFile);
            unlink($outputFile);

            return true;
        } else {
            // Fallback: return HTML for manual conversion
            header('Content-Type: text/html');
            header('Content-Disposition: attachment; filename="' . str_replace('.pdf', '.html', $filename) . '"');

            echo $html;

            // Clean up
            unlink($tempFile);

            return false;
        }
    }
}

// Initialize PDF generator
$pdfGenerator = new PDFGenerator($db);
?>