<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON content type
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'errors' => ['Method not allowed']]);
    exit;
}

// Check if required POST data exists
if (empty($_POST)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['No form data received']]);
    exit;
}

// Get form data
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$rating = (int) ($_POST['rating'] ?? 0);
$userId = isLoggedIn() ? getCurrentUserId() : null;

// Debug logging
error_log("Feedback submission - Name: $name, Email: $email, Subject: $subject, Rating: $rating");

// Validate inputs
$errors = [];

if (empty($name)) {
    $errors[] = "Name is required";
} elseif (strlen($name) < 2) {
    $errors[] = "Name must be at least 2 characters";
}

if (empty($email)) {
    $errors[] = "Email is required";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format";
}

if (empty($subject)) {
    $errors[] = "Subject is required";
} elseif (strlen($subject) < 5) {
    $errors[] = "Subject must be at least 5 characters";
}

if (empty($message)) {
    $errors[] = "Message is required";
} elseif (strlen($message) < 10) {
    $errors[] = "Message must be at least 10 characters";
}

if ($rating < 1 || $rating > 5) {
    $errors[] = "Please select a valid rating";
}

// If there are errors, return them
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    // Check if feedback table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'feedback'");
    if ($tableCheck->num_rows == 0) {
        // Create the feedback table if it doesn't exist
        $createTableSql = "CREATE TABLE IF NOT EXISTS feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            rating TINYINT CHECK (rating >= 1 AND rating <= 5),
            status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_status (status),
            INDEX idx_created (created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

        $db->query($createTableSql);
    }

    // Insert feedback into database
    $sql = "INSERT INTO feedback (user_id, name, email, subject, message, rating, status) 
            VALUES (" . ($userId ? $userId : 'NULL') . ", 
                    '" . $db->escape($name) . "', 
                    '" . $db->escape($email) . "', 
                    '" . $db->escape($subject) . "', 
                    '" . $db->escape($message) . "', 
                    $rating, 
                    'pending')";

    $feedbackId = $db->insert($sql);

    if ($feedbackId) {
        // Send notification email to admin (actual admin user)
        $adminUser = $db->fetchOne("SELECT email, username FROM users WHERE role = 'admin' AND status = 'active' LIMIT 1");
        $adminEmail = $adminUser && !empty($adminUser['email']) ? $adminUser['email'] : (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : null);
        $adminName = $adminUser && !empty($adminUser['username']) ? $adminUser['username'] : 'Admin';
        if ($adminEmail && function_exists('sendEmail')) {
            require_once 'includes/notifications.php';

            $emailSubject = "New Feedback Received - " . SITE_NAME;
            $emailBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #333;'>New Feedback Received</h2>
                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <p><strong>From:</strong> " . htmlspecialchars($name) . " (" . htmlspecialchars($email) . ")</p>
                    <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                    <p><strong>Rating:</strong> " . str_repeat('⭐', $rating) . "</p>
                    <p><strong>Message:</strong></p>
                    <p style='background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #667eea;'>" . nl2br(htmlspecialchars($message)) . "</p>
                </div>
                <p style='color: #666; font-size: 14px;'>Feedback ID: #$feedbackId</p>
            </div>";

            sendEmail($adminEmail, $emailSubject, $emailBody);
        }

        echo json_encode(['success' => true, 'message' => 'Thank you for your feedback! We will review it shortly.']);
    } else {
        throw new Exception("Failed to save feedback");
    }

} catch (Exception $e) {
    error_log("Feedback submission error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'errors' => ['An error occurred while submitting your feedback. Please try again.']]);
}
?>