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