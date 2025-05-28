<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// For AfricasTalking SDK
use AfricasTalking\SDK\AfricasTalking;

/**
 * Send email using PHPMailer
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param string $plainText Plain text version of email
 * @param array $attachments Optional array of attachments [path => name]
 * @return bool True if email sent successfully, false otherwise
 */
function sendEmail($to, $subject, $body, $plainText = '', $attachments = []) {
    global $db;
    
    // Log the email attempt
    $sql = "INSERT INTO email_logs (recipient_email, subject, message, status) 
            VALUES ('" . $db->escape($to) . "', '" . $db->escape($subject) . "', 
            '" . $db->escape($body) . "', 'pending')";
    
    $emailLogId = $db->insert($sql);
    
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SITE_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        // Add plain text alternative if provided
        if (!empty($plainText)) {
            $mail->AltBody = $plainText;
        }
        
        // Add attachments if any
        if (!empty($attachments)) {
            foreach ($attachments as $path => $name) {
                $mail->addAttachment($path, $name);
            }
        }
        
        $mail->send();
        
        // Update email log status
        $db->query("UPDATE email_logs SET status = 'sent' WHERE id = $emailLogId");
        
        return true;
    } catch (Exception $e) {
        // Log error
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        
        // Update email log status
        $db->query("UPDATE email_logs SET status = 'failed' WHERE id = $emailLogId");
        
        return false;
    }
}

/**
 * Send SMS using AfricasTalking
 * 
 * @param string $phoneNumber Recipient phone number
 * @param string $message SMS message
 * @return bool True if SMS sent successfully, false otherwise
 */
function sendSMS($phoneNumber, $message) {
    global $db;
    
    // Log the SMS attempt
    $sql = "INSERT INTO sms_logs (recipient_phone, message, status) 
            VALUES ('" . $db->escape($phoneNumber) . "', '" . $db->escape($message) . "', 'pending')";
    
    $smsLogId = $db->insert($sql);
    
    // Format phone number (ensure it has country code)
    if (!preg_match('/^\+/', $phoneNumber)) {
        // Add country code if not present (adjust as needed for your region)
        $phoneNumber = '+' . ltrim($phoneNumber, '+0');
    }
    
    // Truncate message if longer than 160 characters
    if (strlen($message) > 160) {
        $message = substr($message, 0, 157) . '...';
    }
    
    // AfricasTalking credentials
    $username = SMS_USERNAME;
    $apiKey = SMS_API_KEY;
    
    // Initialize the SDK
    $AT = new AfricasTalking($username, $apiKey);
    
    // Get the SMS service
    $sms = $AT->sms();
    
    try {
        // Send the message
        $result = $sms->send([
            'to' => $phoneNumber,
            'message' => $message
        ]);
        
        // Check if the message was sent successfully
        if ($result['status'] == 'success' && !empty($result['data']->SMSMessageData->Recipients)) {
            $recipient = $result['data']->SMSMessageData->Recipients[0];
            if ($recipient->status == 'Success') {
                // Update SMS log status
                $db->query("UPDATE sms_logs SET status = 'sent' WHERE id = $smsLogId");
                return true;
            }
        }
        
        // Log error
        error_log("SMS could not be sent. Status: " . json_encode($result));
        
        // Update SMS log status
        $db->query("UPDATE sms_logs SET status = 'failed' WHERE id = $smsLogId");
        
        return false;
    } catch (Exception $e) {
        // Log error
        error_log("SMS could not be sent. Error: " . $e->getMessage());
        
        // Update SMS log status
        $db->query("UPDATE sms_logs SET status = 'failed' WHERE id = $smsLogId");
        
        return false;
    }
}

/**
 * Generate ticket email template
 * 
 * @param array $ticket Ticket information
 * @param array $event Event information
 * @param string $qrCodeUrl URL to the QR code image
 * @return string HTML email template
 */
function getTicketEmailTemplate($ticket, $event, $qrCodeUrl) {
    $eventDate = formatDate($event['start_date']);
    $eventTime = formatTime($event['start_time']);
    $ticketPrice = formatCurrency($ticket['purchase_price']);
    
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Ticket for {$event['title']}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #4f46e5;
            padding: 20px;
            color: white;
            text-align: center;
        }
        .content {
            padding: 20px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }
        .ticket {
            background-color: white;
            padding: 15px;
            border-left: 4px solid #4f46e5;
            margin-bottom: 20px;
        }
        .qr-code {
            text-align: center;
            margin: 20px 0;
        }
        .qr-code img {
            max-width: 200px;
            height: auto;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
        }
        .button {
            display: inline-block;
            background-color: #4f46e5;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Your Ticket is Confirmed!</h2>
        </div>
        <div class="content">
            <h3>Thank you for your purchase</h3>
            <p>Your ticket for <strong>{$event['title']}</strong> has been confirmed. Please find your ticket details below:</p>
            
            <div class="ticket">
                <h4>{$event['title']}</h4>
                <p><strong>Date:</strong> {$eventDate}</p>
                <p><strong>Time:</strong> {$eventTime}</p>
                <p><strong>Venue:</strong> {$event['venue']}, {$event['city']}</p>
                <p><strong>Ticket Holder:</strong> {$ticket['recipient_name']}</p>
                <p><strong>Ticket Price:</strong> {$ticketPrice}</p>
                <p><strong>Ticket ID:</strong> {$ticket['id']}</p>
            </div>
            
            <div class="qr-code">
                <img src="{$qrCodeUrl}" alt="Ticket QR Code">
                <p>Scan this QR code at the venue for entry</p>
            </div>
            
            <p>You can also view and download your ticket from your account dashboard.</p>
            <div style="text-align: center;">
                <a href="http://localhost/utb/smart_ticket_system//view-ticket.php?id={$ticket['id']}" class="button">View Ticket</a>
            </div>
        </div>
        <div class="footer">
            <p>This is an automated message from {SITE_NAME}.</p>
            <p>If you have any questions, please contact our support team.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Generate SMS template for ticket confirmation
 * 
 * @param array $ticket Ticket information
 * @param array $event Event information
 * @return string SMS message
 */
function getTicketSMSTemplate($ticket, $event) {
    $eventDate = formatDate($event['start_date']);
    $eventTime = formatTime($event['start_time']);
    
    $message = "Your ticket for {$event['title']} is confirmed!\n";
    $message .= "Date: {$eventDate}\n";
    $message .= "Time: {$eventTime}\n";
    $message .= "Venue: {$event['venue']}\n";
    $message .= "Ticket ID: {$ticket['id']}\n";
    $message .= "View your ticket at: " . SITE_URL . "/view-ticket.php?id={$ticket['id']}";
    
    return $message;
}

/**
 * Enhanced notification functions for ticket system
 */

/**
 * Get enhanced ticket email template
 */
function getEnhancedTicketEmailTemplate($ticket, $eventDetails, $qrCodeUrl) {
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Your Ticket - ' . htmlspecialchars($eventDetails['title']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: white; padding: 30px; border: 1px solid #ddd; }
            .ticket-card { background: #f8f9fa; border: 2px dashed #6366f1; border-radius: 10px; padding: 20px; margin: 20px 0; }
            .qr-code { text-align: center; margin: 20px 0; }
            .event-details { background: #e0e7ff; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .footer { background: #f1f5f9; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; }
            .btn { display: inline-block; background: #6366f1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 10px 0; }
            .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 15px 0; }
            .info-item { padding: 10px; background: white; border-radius: 6px; }
            .info-label { font-weight: bold; color: #6366f1; font-size: 12px; text-transform: uppercase; }
            .info-value { font-size: 14px; margin-top: 5px; }
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
                        <img src="' . $qrCodeUrl . '" alt="QR Code" style="max-width: 200px; height: auto;">
                                               <p style="font-size: 12px; color: #666;">Show this QR code at the event entrance</p>
                        <p style="font-size: 10px; color: #999;">Verification Code: ' . htmlspecialchars($ticket['qr_code']) . '</p>
                    </div>
                </div>
                
                <div class="event-details">
                    <h3 style="color: #4338ca; margin-top: 0;">📍 Event Information</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <strong>📅 Date:</strong><br>
                            ' . formatDate($eventDetails['start_date']) . '
                        </div>
                        <div>
                            <strong>🕐 Time:</strong><br>
                            ' . formatTime($eventDetails['start_time']) . ' - ' . formatTime($eventDetails['end_time']) . '
                        </div>
                        <div style="grid-column: span 2;">
                            <strong>📍 Location:</strong><br>
                            ' . htmlspecialchars($eventDetails['venue']) . '<br>
                            ' . htmlspecialchars($eventDetails['city']) . '
                        </div>
                    </div>
                </div>
                
                <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 15px; margin: 20px 0;">
                    <h4 style="color: #92400e; margin-top: 0;">⚠️ Important Reminders</h4>
                    <ul style="color: #92400e; margin: 0; padding-left: 20px;">
                        <li>Arrive at least 30 minutes before the event starts</li>
                        <li>Bring a valid ID for verification</li>
                        <li>This ticket is non-transferable and non-refundable</li>
                        <li>Screenshots of QR codes are accepted</li>
                    </ul>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . SITE_URL . '/customer/tickets.php" class="btn">View My Tickets</a>
                    <a href="' . SITE_URL . '/events.php" class="btn" style="background: #10b981;">Browse More Events</a>
                </div>
            </div>
            
            <div class="footer">
                <h4 style="color: #6366f1; margin-top: 0;">Need Help?</h4>
                <p style="margin: 10px 0;">
                    📧 Email: <a href="mailto:support@' . str_replace(['http://', 'https://'], '', SITE_URL) . '">support@' . str_replace(['http://', 'https://'], '', SITE_URL) . '</a><br>
                    📞 Phone: +250 123 456 789<br>
                    🌐 Website: <a href="' . SITE_URL . '">' . SITE_NAME . '</a>
                </p>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #d1d5db;">
                    <p style="font-size: 12px; color: #6b7280; margin: 0;">
                        This email was sent to ' . htmlspecialchars($ticket['recipient_email']) . '<br>
                        © ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Get enhanced SMS template for tickets
 */
function getEnhancedTicketSMSTemplate($ticket, $eventDetails) {
    $message = "🎫 TICKET CONFIRMED!\n\n";
    $message .= "Event: " . $eventDetails['title'] . "\n";
    $message .= "Date: " . formatDate($eventDetails['start_date']) . "\n";
    $message .= "Time: " . formatTime($eventDetails['start_time']) . "\n";
    $message .= "Venue: " . $eventDetails['venue'] . "\n";
    $message .= "Ticket ID: #" . $ticket['id'] . "\n\n";
    $message .= "Show your email QR code at entrance.\n";
    $message .= "Arrive 30 mins early with valid ID.\n\n";
    $message .= "View tickets: " . SITE_URL . "/customer/tickets.php\n";
    $message .= "Support: +250 123 456 789";
    
    return $message;
}

/**
 * Send enhanced email with better error handling
 */
function sendEnhancedEmail($to, $subject, $htmlBody, $plainTextBody = '') {
    global $db;
    
    // Log email attempt
    $logSql = "INSERT INTO email_logs (recipient_email, subject, message, status, created_at) 
               VALUES ('" . $db->escape($to) . "', '" . $db->escape($subject) . "', '" . $db->escape($htmlBody) . "', 'pending', NOW())";
    $emailLogId = $db->insert($logSql);
    
    try {
        // Set up email headers
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . SITE_NAME . ' <noreply@' . str_replace(['http://', 'https://'], '', SITE_URL) . '>',
            'Reply-To: support@' . str_replace(['http://', 'https://'], '', SITE_URL),
            'X-Mailer: PHP/' . phpversion(),
            'X-Priority: 1',
            'Importance: High'
        ];
        
        // Add plain text alternative if provided
        if (!empty($plainTextBody)) {
            $boundary = md5(time());
            $headers[1] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            
            $body = "--$boundary\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $body .= $plainTextBody . "\r\n\r\n";
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n";
            $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
            $body .= $htmlBody . "\r\n\r\n";
            $body .= "--$boundary--";
            
            $htmlBody = $body;
        }
        
        // Send email
        $result = mail($to, $subject, $htmlBody, implode("\r\n", $headers));
        
        if ($result) {
            // Update log status
            $db->query("UPDATE email_logs SET status = 'sent' WHERE id = $emailLogId");
            return true;
        } else {
            // Update log status
            $db->query("UPDATE email_logs SET status = 'failed' WHERE id = $emailLogId");
            error_log("Failed to send email to: $to");
            return false;
        }
        
    } catch (Exception $e) {
        // Update log status
        $db->query("UPDATE email_logs SET status = 'failed' WHERE id = $emailLogId");
        error_log("Email sending error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send enhanced SMS with better formatting
 */
function sendEnhancedSMS($phoneNumber, $message) {
    global $db;
    
    // Clean phone number
    $cleanPhone = preg_replace('/[^0-9+]/', '', $phoneNumber);
    
    // Log SMS attempt
    $logSql = "INSERT INTO sms_logs (recipient_phone, message, status, created_at) 
               VALUES ('" . $db->escape($cleanPhone) . "', '" . $db->escape($message) . "', 'pending', NOW())";
    $smsLogId = $db->insert($logSql);
    
    try {
        // For demo purposes, we'll simulate SMS sending
        // In production, integrate with SMS gateway like Twilio, Nexmo, etc.
        
        // Simulate API call delay
        usleep(500000); // 0.5 second delay
        
        // Simulate success/failure (90% success rate for demo)
        $success = (rand(1, 10) <= 9);
        
        if ($success) {
            $db->query("UPDATE sms_logs SET status = 'sent' WHERE id = $smsLogId");
            return true;
        } else {
            $db->query("UPDATE sms_logs SET status = 'failed' WHERE id = $smsLogId");
            error_log("SMS simulation failed for: $cleanPhone");
            return false;
        }
        
    } catch (Exception $e) {
        $db->query("UPDATE sms_logs SET status = 'failed' WHERE id = $smsLogId");
        error_log("SMS sending error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send event reminder notifications
 */
function sendEventReminders() {
    global $db;
    
    // Get events starting in 24 hours
    $sql = "SELECT DISTINCT e.*, t.user_id, t.recipient_email, t.recipient_name, u.phone_number
            FROM events e
            JOIN tickets t ON e.id = t.event_id
            JOIN users u ON t.user_id = u.id
            WHERE e.start_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
            AND t.status = 'sold'
            AND e.status = 'active'";
    
    $reminders = $db->fetchAll($sql);
    
    foreach ($reminders as $reminder) {
        // Send email reminder
        $subject = "Reminder: " . $reminder['title'] . " is tomorrow!";
        $htmlBody = getEventReminderEmailTemplate($reminder);
        $plainText = "Don't forget! " . $reminder['title'] . " is tomorrow at " . formatTime($reminder['start_time']) . " at " . $reminder['venue'] . ".";
        
        sendEnhancedEmail($reminder['recipient_email'], $subject, $htmlBody, $plainText);
        
        // Send SMS reminder if phone number available
        if (!empty($reminder['phone_number'])) {
            $smsMessage = "Reminder: " . $reminder['title'] . " is TOMORROW at " . formatTime($reminder['start_time']) . " at " . $reminder['venue'] . ". Don't forget your ticket!";
            sendEnhancedSMS($reminder['phone_number'], $smsMessage);
        }
    }
}

/**
 * Get event reminder email template
 */
function getEventReminderEmailTemplate($eventDetails) {
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Event Reminder - ' . htmlspecialchars($eventDetails['title']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: white; padding: 30px; border: 1px solid #ddd; }
            .reminder-card { background: #fef3c7; border: 2px solid #f59e0b; border-radius: 10px; padding: 20px; margin: 20px 0; }
            .countdown { background: #dc2626; color: white; padding: 15px; border-radius: 8px; text-align: center; font-size: 18px; font-weight: bold; }
            .btn { display: inline-block; background: #f59e0b; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>⏰ Event Reminder</h1>
                <p>Don\'t miss your upcoming event!</p>
            </div>
            
            <div class="content">
                <div class="countdown">
                    🚨 TOMORROW: ' . htmlspecialchars($eventDetails['title']) . ' 🚨
                </div>
                
                <div class="reminder-card">
                    <h2 style="color: #d97706; margin-top: 0;">📅 Event Details</h2>
                    <p><strong>Date:</strong> ' . formatDate($eventDetails['start_date']) . '</p>
                    <p><strong>Time:</strong> ' . formatTime($eventDetails['start_time']) . '</p>
                    <p><strong>Venue:</strong> ' . htmlspecialchars($eventDetails['venue']) . '</p>
                    <p><strong>Location:</strong> ' . htmlspecialchars($eventDetails['city']) . '</p>
                </div>
                
                <div style="background: #dbeafe; border: 1px solid #3b82f6; border-radius: 8px; padding: 15px; margin: 20px 0;">
                    <h4 style="color: #1e40af; margin-top: 0;">📋 Checklist for Tomorrow</h4>
                    <ul style="color: #1e40af;">
                        <li>✅ Have your ticket ready (digital or printed)</li>
                        <li>✅ Bring a valid photo ID</li>
                        <li>✅ Arrive 30 minutes early</li>
                        <li>✅ Check traffic and parking options</li>
                    </ul>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . SITE_URL . '/customer/tickets.php" class="btn">View My Tickets</a>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Create notification in database
 */
function createNotification($userId, $title, $message, $type = 'system') {
    global $db;
    
    $sql = "INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
            VALUES ($userId, '" . $db->escape($title) . "', '" . $db->escape($message) . "', '$type', 0, NOW())";
    
    return $db->insert($sql);
}