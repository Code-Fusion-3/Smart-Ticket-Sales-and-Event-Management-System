<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// For AfricasTalking SDK
use AfricasTalking\SDK\AfricasTalking;

/**
 * Send email using PHPMailer with better error handling
 */
function sendEmail($to, $subject, $body, $plainText = '', $attachments = [])
{
    global $db;

    // Validate email address
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address: $to");
        return false;
    }

    // Log the email attempt
    // $sql = "INSERT INTO email_logs (recipient_email, subject, message, status, created_at) 
    //         VALUES ('" . $db->escape($to) . "', '" . $db->escape($subject) . "', 
    //         '" . $db->escape(substr($body, 0, 1000)) . "', 'pending', NOW())";

    // $emailLogId = $db->insert($sql);

    $mail = new PHPMailer(true);

    try {
        // Enable debug mode if configured
        if (defined('EMAIL_DEBUG') && EMAIL_DEBUG) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function ($str, $level) {
                error_log("PHPMailer Debug: $str");
            };
        }

        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        // Additional SMTP settings for Gmail
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME ?? SITE_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME ?? SITE_NAME);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->CharSet = 'UTF-8';

        // Add plain text alternative if provided
        if (!empty($plainText)) {
            $mail->AltBody = $plainText;
        }

        // Add attachments if any
        if (!empty($attachments)) {
            foreach ($attachments as $path => $name) {
                if (file_exists($path)) {
                    $mail->addAttachment($path, $name);
                }
            }
        }

        $result = $mail->send();

        if ($result) {
            // Update email log status
            // $db->query("UPDATE email_logs SET status = 'sent', sent_at = NOW() WHERE id = $emailLogId");
            // error_log("Email sent successfully to: $to");
            return true;
        } else {
            throw new Exception("Mail send returned false");
        }

    } catch (Exception $e) {
        // Log error
        $errorMessage = "Email could not be sent. Mailer Error: {$mail->ErrorInfo}. Exception: {$e->getMessage()}";
        error_log($errorMessage);

        // Update email log status
        $db->query("UPDATE email_logs SET status = 'failed', error_message = '" . $db->escape($errorMessage) . "' WHERE id = $emailLogId");

        return false;
    }
}

/**
 * Send SMS using AfricasTalking with better error handling
 */
function sendSMS($phoneNumber, $message)
{
    global $db;

    // Clean and validate phone number - remove all non-digit characters except +
    $cleanPhone = preg_replace('/[^0-9+]/', '', $phoneNumber);

    // Remove any spaces, dashes, or other formatting
    $cleanPhone = str_replace([' ', '-', '(', ')', '.'], '', $cleanPhone);

    // Format phone number for Rwanda (+250)
    if (preg_match('/^0[0-9]{8}$/', $cleanPhone)) {
        // Convert 0XXXXXXXX to +250XXXXXXXX
        $cleanPhone = '+250' . substr($cleanPhone, 1);
    } elseif (preg_match('/^[0-9]{9}$/', $cleanPhone)) {
        // Convert XXXXXXXXX to +250XXXXXXXXX
        $cleanPhone = '+250' . $cleanPhone;
    } elseif (preg_match('/^250[0-9]{9}$/', $cleanPhone)) {
        // Convert 250XXXXXXXXX to +250XXXXXXXXX
        $cleanPhone = '+' . $cleanPhone;
    } elseif (!preg_match('/^\+250[0-9]{9}$/', $cleanPhone)) {
        error_log("Invalid phone number format: $phoneNumber (cleaned: $cleanPhone)");
        return false;
    }

    // Log the SMS attempt
    $sql = "INSERT INTO sms_logs (recipient_phone, message, status, created_at) 
            VALUES ('" . $db->escape($cleanPhone) . "', '" . $db->escape($message) . "', 'pending', NOW())";

    $smsLogId = $db->insert($sql);

    // Truncate message if longer than 160 characters
    if (strlen($message) > 160) {
        $message = substr($message, 0, 157) . '...';
    }

    // AfricasTalking credentials
    $username = SMS_USERNAME;
    $apiKey = SMS_API_KEY;

    try {
        // Initialize the SDK
        $AT = new AfricasTalking($username, $apiKey);

        // Get the SMS service
        $sms = $AT->sms();

        // Send the message
        $result = $sms->send([
            'to' => $cleanPhone,
            'message' => $message
        ]);

        if (defined('SMS_DEBUG') && SMS_DEBUG) {
            error_log("SMS API Response: " . json_encode($result));
        }

        // Check if the message was sent successfully
        if (isset($result['status']) && $result['status'] == 'success' && !empty($result['data']->SMSMessageData->Recipients)) {
            $recipient = $result['data']->SMSMessageData->Recipients[0];
            if (isset($recipient->status) && $recipient->status == 'Success') {
                // Update SMS log status
                $db->query("UPDATE sms_logs SET status = 'sent', sent_at = NOW() WHERE id = $smsLogId");
                error_log("SMS sent successfully to: $cleanPhone");
                return true;
            } else {
                $errorMsg = isset($recipient->statusCode) ? "Status Code: {$recipient->statusCode}" : "Unknown error";
                throw new Exception($errorMsg);
            }
        } else {
            throw new Exception("Invalid API response: " . json_encode($result));
        }

    } catch (Exception $e) {
        // Log error
        $errorMessage = "SMS could not be sent. Error: " . $e->getMessage();
        error_log($errorMessage);

        // Update SMS log status
        $db->query("UPDATE sms_logs SET status = 'failed', error_message = '" . $db->escape($errorMessage) . "' WHERE id = $smsLogId");

        return false;
    }
}

/**
 * Enhanced ticket email template with better formatting
 */
function getEnhancedTicketEmailTemplate($ticket, $eventDetails, $qrCodeUrl)
{
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Your Ticket - ' . htmlspecialchars($eventDetails['title']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
            .container { max-width: 600px; margin: 0 auto; background-color: white; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
            .content { padding: 30px; }
            .ticket-card { background: #f8f9fa; border: 2px dashed #6366f1; border-radius: 10px; padding: 20px; margin: 20px 0; }
            .qr-code { text-align: center; margin: 20px 0; }
            .qr-code img { max-width: 200px; height: auto; border: 2px solid #ddd; border-radius: 8px; }
            .event-details { background: #e0e7ff; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .footer { background: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .btn { display: inline-block; background: #6366f1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 10px 5px; }
            .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 15px 0; }
            .info-item { padding: 10px; background: white; border-radius: 6px; border: 1px solid #e5e7eb; }
            .info-label { font-weight: bold; color: #6366f1; font-size: 12px; text-transform: uppercase; margin-bottom: 5px; }
            .info-value { font-size: 14px; }
            .highlight { background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 15px; margin: 20px 0; }
            .highlight h4 { color: #92400e; margin-top: 0; }
            .highlight ul { color: #92400e; margin: 0; padding-left: 20px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🎫 Your Ticket is Ready!</h1>
                <p>Thank you for your purchase</p>
            </div>
            
            <div class="content">
                <div class="ticket-card">
                    <h2 style="color: #6366f1; margin-top: 0;">🎉 ' . htmlspecialchars($eventDetails['title']) . '</h2>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Ticket Type</div>
                            <div class="info-value">' . htmlspecialchars($ticket['ticket_name']) . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Ticket ID</div>
                            <div class="info-value">#' . $ticket['id'] . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Date & Time</div>
                            <div class="info-value">' . formatDate($eventDetails['start_date']) . '<br>' . formatTime($eventDetails['start_time']) . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Venue</div>
                            <div class="info-value">' . htmlspecialchars($eventDetails['venue']) . '<br>' . htmlspecialchars($eventDetails['city']) . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Recipient</div>
                            <div class="info-value">' . htmlspecialchars($ticket['recipient_name']) . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Price Paid</div>
                            <div class="info-value">' . formatCurrency($ticket['purchase_price']) . '</div>
                        </div>
                    </div>
                    
                    <div class="qr-code">
                        <p><strong>Your Entry QR Code:</strong></p>
                        <img src="' . $qrCodeUrl . '" alt="QR Code" />
                        <p style="font-size: 12px; color: #666; margin-top: 10px;">Show this QR code at the event entrance</p>
                        <p style="font-size: 10px; color: #999;">Verification Code: ' . htmlspecialchars($ticket['qr_code']) . '</p>
                    </div>
                </div>
                
                               <div class="event-details">
                    <h3 style="color: #4338ca; margin-top: 0;">📍 Event Information</h3>
                    <p><strong>Event:</strong> ' . htmlspecialchars($eventDetails['title']) . '</p>
                    <p><strong>Date:</strong> ' . formatDate($eventDetails['start_date']) . '</p>
                    <p><strong>Time:</strong> ' . formatTime($eventDetails['start_time']) . '</p>
                    <p><strong>Venue:</strong> ' . htmlspecialchars($eventDetails['venue']) . ', ' . htmlspecialchars($eventDetails['city']) . '</p>
                    <p><strong>Organizer:</strong> ' . htmlspecialchars($ticket['planner_name'] ?? 'Event Organizer') . '</p>
                </div>
                
                <div class="highlight">
                    <h4>📋 Important Instructions</h4>
                    <ul>
                        <li>Arrive 30 minutes before the event starts</li>
                        <li>Bring a valid ID that matches the ticket holder\'s name</li>
                        <li>Show the QR code above at the entrance</li>
                        <li>Keep this email or save the QR code to your phone</li>
                        <li>Contact support if you have any issues</li>
                    </ul>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . SITE_URL . '/view-ticket.php?id=' . $ticket['id'] . '" class="btn">View Full Ticket</a>
                    <a href="' . SITE_URL . '/download-ticket.php?id=' . $ticket['id'] . '" class="btn">Download Ticket</a>
                </div>
                
                <div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h4 style="color: #374151; margin-top: 0;">Need Help?</h4>
                    <p style="margin: 5px 0;"><strong>Website:</strong> <a href="' . SITE_URL . '">' . SITE_NAME . '</a></p>
                    <p style="margin: 5px 0;"><strong>Email:</strong> ' . SMTP_FROM_EMAIL . '</p>
                    <p style="margin: 5px 0;"><strong>Phone:</strong> +250 123 456 789</p>
                </div>
            </div>
            
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
                <p>This is an automated email. Please do not reply to this message.</p>
                <p>If you did not purchase this ticket, please contact us immediately.</p>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}

/**
 * Simple ticket email template for fallback
 */
function getSimpleTicketEmailTemplate($ticket, $eventDetails)
{
    $html = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #6366f1;">Your Ticket Confirmation</h2>
        
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3>' . htmlspecialchars($eventDetails['title']) . '</h3>
            <p><strong>Ticket Type:</strong> ' . htmlspecialchars($ticket['ticket_name']) . '</p>
            <p><strong>Ticket ID:</strong> #' . $ticket['id'] . '</p>
            <p><strong>Date:</strong> ' . formatDate($eventDetails['start_date']) . '</p>
            <p><strong>Time:</strong> ' . formatTime($eventDetails['start_time']) . '</p>
            <p><strong>Venue:</strong> ' . htmlspecialchars($eventDetails['venue']) . ', ' . htmlspecialchars($eventDetails['city']) . '</p>
            <p><strong>Recipient:</strong> ' . htmlspecialchars($ticket['recipient_name']) . '</p>
            <p><strong>Price:</strong> ' . formatCurrency($ticket['purchase_price']) . '</p>
        </div>
        
        <div style="background: #e0e7ff; padding: 15px; border-radius: 8px;">
            <p><strong>Verification Code:</strong> ' . htmlspecialchars($ticket['qr_code']) . '</p>
            <p>Show this code at the event entrance along with a valid ID.</p>
        </div>
        
        <p style="margin-top: 20px;">
            <a href="' . SITE_URL . '/view-ticket.php?id=' . $ticket['id'] . '" 
               style="background: #6366f1; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
               View Full Ticket
            </a>
        </p>
        
        <hr style="margin: 30px 0;">
        <p style="font-size: 12px; color: #666;">
            This is an automated email from ' . SITE_NAME . '. 
            If you have any questions, please contact us at ' . SMTP_FROM_EMAIL . '.
        </p>
    </div>';

    return $html;
}

/**
 * Create database tables for logging if they don't exist
 */
function createNotificationTables()
{
    global $db;

    // Create email logs table
    $emailLogsSql = "CREATE TABLE IF NOT EXISTS email_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipient_email VARCHAR(255) NOT NULL,
        subject VARCHAR(500) NOT NULL,
        message TEXT,
        status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
        error_message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        sent_at TIMESTAMP NULL,
        INDEX idx_recipient (recipient_email),
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

    // Create SMS logs table
    $smsLogsSql = "CREATE TABLE IF NOT EXISTS sms_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipient_phone VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
        error_message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        sent_at TIMESTAMP NULL,
        INDEX idx_recipient (recipient_phone),
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

    $db->query($emailLogsSql);
    $db->query($smsLogsSql);
}

// Create tables when this file is included
createNotificationTables();

/**
 * Test email function
 */
function testEmail($testEmail = null)
{
    $testEmail = $testEmail ?: SMTP_FROM_EMAIL;

    $subject = "Test Email from " . SITE_NAME;
    $body = "
    <h2>Email Test Successful!</h2>
    <p>This is a test email to verify that your email configuration is working correctly.</p>
    <p><strong>Sent at:</strong> " . date('Y-m-d H:i:s') . "</p>
    <p><strong>From:</strong> " . SITE_NAME . "</p>
    ";

    $result = sendEmail($testEmail, $subject, $body);

    if ($result) {
        error_log("Test email sent successfully to: $testEmail");
        return true;
    } else {
        error_log("Test email failed to: $testEmail");
        return false;
    }
}

/**
 * Test SMS function
 */
function testSMS($testPhone = null)
{
    $testPhone = $testPhone ?: '+250123456789'; // Default test number

    $message = "Test SMS from " . SITE_NAME . ". Your SMS configuration is working! Sent at " . date('H:i');

    $result = sendSMS($testPhone, $message);

    if ($result) {
        error_log("Test SMS sent successfully to: $testPhone");
        return true;
    } else {
        error_log("Test SMS failed to: $testPhone");
        return false;
    }
}

/**
 * Send notification (combines email and SMS)
 */
function sendNotification($email, $phone, $subject, $emailBody, $smsMessage, $plainTextEmail = '')
{
    $emailResult = false;
    $smsResult = false;

    // Send email
    if (!empty($email)) {
        $emailResult = sendEmail($email, $subject, $emailBody, $plainTextEmail);
    }

    // Send SMS
    if (!empty($phone)) {
        $smsResult = sendSMS($phone, $smsMessage);
    }

    return [
        'email' => $emailResult,
        'sms' => $smsResult
    ];
}

/**
 * Get notification statistics
 */
function getNotificationStats()
{
    global $db;

    $stats = [
        'emails' => [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'pending' => 0
        ],
        'sms' => [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'pending' => 0
        ]
    ];

    // Email stats
    $emailStats = $db->fetchAll("SELECT status, COUNT(*) as count FROM email_logs GROUP BY status");
    foreach ($emailStats as $stat) {
        $stats['emails'][$stat['status']] = $stat['count'];
        $stats['emails']['total'] += $stat['count'];
    }

    // SMS stats
    $smsStats = $db->fetchAll("SELECT status, COUNT(*) as count FROM sms_logs GROUP BY status");
    foreach ($smsStats as $stat) {
        $stats['sms'][$stat['status']] = $stat['count'];
        $stats['sms']['total'] += $stat['count'];
    }

    return $stats;
}

/**
 * Send ticket scan notification to ticket owner
 */
function sendTicketScanNotification($ticketId, $agentName = '', $scanTime = null)
{
    global $db;
    
    if (!$scanTime) {
        $scanTime = date('Y-m-d H:i:s');
    }
    
    // Get ticket and owner details
    $sql = "SELECT 
                t.*,
                e.title as event_title,
                e.venue,
                e.city,
                e.start_date,
                e.start_time,
                u.email as owner_email,
                u.username as owner_name
            FROM tickets t
            JOIN events e ON t.event_id = e.id
            JOIN users u ON t.user_id = u.id
            WHERE t.id = " . intval($ticketId);
    
    $ticket = $db->fetchOne($sql);
    
    if (!$ticket || empty($ticket['owner_email'])) {
        error_log("Could not send scan notification: Ticket not found or no owner email for ticket ID: $ticketId");
        return false;
    }
    
    // Prepare email content
    $emailSubject = "🎫 Your Ticket Was Scanned - " . $ticket['event_title'];
    
    $emailBody = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ticket Scanned - ' . htmlspecialchars($ticket['event_title']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
            .container { max-width: 600px; margin: 0 auto; background-color: white; }
            .header { background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: white; padding: 30px; text-align: center; }
            .content { padding: 30px; }
            .scan-info { background: #f0fdf4; border: 2px solid #10B981; border-radius: 10px; padding: 20px; margin: 20px 0; }
            .ticket-details { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .footer { background: #f1f5f9; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 15px 0; }
            .info-item { padding: 10px; background: white; border-radius: 6px; border: 1px solid #e5e7eb; }
            .info-label { font-weight: bold; color: #10B981; font-size: 12px; text-transform: uppercase; margin-bottom: 5px; }
            .info-value { font-size: 14px; }
            .success-icon { font-size: 48px; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="success-icon">✅</div>
                <h1>Ticket Successfully Scanned!</h1>
                <p>Your ticket has been verified and you have entered the event</p>
            </div>
            
            <div class="content">
                <div class="scan-info">
                    <h2 style="color: #10B981; margin-top: 0;">🎉 Welcome to ' . htmlspecialchars($ticket['event_title']) . '!</h2>
                    <p>Your ticket was successfully scanned and verified. Enjoy your event!</p>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Scan Time</div>
                            <div class="info-value">' . formatDateTime($scanTime) . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Scanned By</div>
                            <div class="info-value">' . htmlspecialchars($agentName ?: 'Event Staff') . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Ticket ID</div>
                            <div class="info-value">#' . $ticket['id'] . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Ticket Holder</div>
                            <div class="info-value">' . htmlspecialchars($ticket['recipient_name'] ?: $ticket['owner_name']) . '</div>
                        </div>
                    </div>
                </div>
                
                <div class="ticket-details">
                    <h3 style="color: #374151; margin-top: 0;">Event Details</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Event</div>
                            <div class="info-value">' . htmlspecialchars($ticket['event_title']) . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Date & Time</div>
                            <div class="info-value">' . formatDate($ticket['start_date']) . '<br>' . formatTime($ticket['start_time']) . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Venue</div>
                            <div class="info-value">' . htmlspecialchars($ticket['venue']) . '<br>' . htmlspecialchars($ticket['city']) . '</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Ticket Type</div>
                            <div class="info-value">' . htmlspecialchars($ticket['ticket_type'] ?? 'General') . '</div>
                        </div>
                    </div>
                </div>
                
                <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 15px; margin: 20px 0;">
                    <h4 style="color: #92400e; margin-top: 0;">📋 Important Reminders</h4>
                    <ul style="color: #92400e; margin: 0; padding-left: 20px;">
                        <li>Keep your ticket safe throughout the event</li>
                        <li>Follow all event rules and guidelines</li>
                        <li>Have a great time!</li>
                    </ul>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . SITE_URL . '/my-tickets.php" style="background: #10B981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block;">
                        View My Tickets
                    </a>
                </div>
                
                <div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <h4 style="color: #374151; margin-top: 0;">Need Help?</h4>
                    <p style="margin: 5px 0;"><strong>Website:</strong> <a href="' . SITE_URL . '">' . SITE_NAME . '</a></p>
                    <p style="margin: 5px 0;"><strong>Email:</strong> ' . SMTP_FROM_EMAIL . '</p>
                    <p style="margin: 5px 0;"><strong>Phone:</strong> +250 123 456 789</p>
                </div>
            </div>
            
            <div class="footer">
                <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
                <p>This is an automated notification. Please do not reply to this message.</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Create plain text version
    $plainText = "Your ticket for " . $ticket['event_title'] . " has been successfully scanned!\n\n" .
                 "Scan Time: " . formatDateTime($scanTime) . "\n" .
                 "Scanned By: " . ($agentName ?: 'Event Staff') . "\n" .
                 "Ticket ID: #" . $ticket['id'] . "\n" .
                 "Event: " . $ticket['event_title'] . "\n" .
                 "Date: " . formatDate($ticket['start_date']) . " at " . formatTime($ticket['start_time']) . "\n" .
                 "Venue: " . $ticket['venue'] . ", " . $ticket['city'] . "\n\n" .
                 "Enjoy your event!\n\n" .
                 SITE_NAME;
    
    // Send the email
    $emailResult = sendEmail($ticket['owner_email'], $emailSubject, $emailBody, $plainText);
    
    if ($emailResult) {
        error_log("Ticket scan notification sent successfully to: " . $ticket['owner_email'] . " for ticket ID: " . $ticketId);
        
        // Create in-app notification
        $notificationTitle = "Ticket Scanned: " . $ticket['event_title'];
        $notificationMessage = "Your ticket for " . $ticket['event_title'] . " was successfully scanned at " . formatDateTime($scanTime) . ".";
        
        $db->query("INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
                    VALUES (" . $ticket['user_id'] . ", '" . $db->escape($notificationTitle) . "', 
                    '" . $db->escape($notificationMessage) . "', 'ticket', 0, NOW())");
        
        return true;
    } else {
        error_log("Failed to send ticket scan notification to: " . $ticket['owner_email'] . " for ticket ID: " . $ticketId);
        return false;
    }
}
?>