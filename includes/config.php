<?php
// Start session
session_start();


// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ticket_management_system');

// Website configuration
define('SITE_NAME', 'Smart Ticket Sales and Event Management System');
define('SITE_URL', 'http://192.168.137.73:3000');

// File paths
define('ROOT_PATH', dirname(__DIR__) . '/');
define('INCLUDE_PATH', ROOT_PATH . 'includes/');
define('UPLOAD_PATH', ROOT_PATH . 'assets/uploads/');

// Email settings - Updated for better compatibility
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'infofonepo@gmail.com');
define('SMTP_PASSWORD', 'zaoxwuezfjpglwjb');
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_PORT', 587);
define('SMTP_FROM_EMAIL', 'infofonepo@gmail.com'); // Use same as username
define('SMTP_FROM_NAME', 'Smart Ticket System');

// SMS settings - Updated
define('SMS_USERNAME', 'Iot_project');
define('SMS_API_KEY', 'atsk_6ccbe2174a56e50490d59c73c1f7177fc02e47c2cdecb5343b67e6680bc321677b10c4bd');
// define('SMS_SENDER_ID', 'SmartTicket'); // Commented out for sandbox compatibility

// Currency settings
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', 'Rwf');
    define('CURRENCY_CODE', 'RWF');
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Time zone
date_default_timezone_set('Africa/Kigali'); // Set to Rwanda timezone

// Email debug mode (set to false in production)
define('EMAIL_DEBUG', true);
define('SMS_DEBUG', true);
?>