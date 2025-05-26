<?php
require_once 'config.php';
require_once 'db.php';

// Redirect to a specific page
function redirect($page) {
    header("Location: " . SITE_URL . "/" . $page);
    exit;
}

// Display error message
function displayError($message) {
    return "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'>{$message}</div>";
}

// Display success message
function displaySuccess($message) {
    return "<div class='bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4'>{$message}</div>";
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check user role
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    return $_SESSION['user_role'] === $role;
}

// Get current user ID
function getCurrentUserId() {
    return isLoggedIn() ? $_SESSION['user_id'] : null;
}

// Format date
function formatDate($date) {
    return date("F j, Y", strtotime($date));
}

// Format time
function formatTime($time) {
    return date("g:i A", strtotime($time));
}

// Format currency
function formatCurrency($amount) {
    return 'Rwf ' . number_format($amount, 2);
}

// Generate random string
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $randomString;
}

// Generate QR code (placeholder - we'll implement this later)
function generateQRCode($data) {
    // This is a placeholder - we'll implement actual QR code generation later
    return "QR_" . md5($data);
}

// Get system setting
function getSystemSetting($key) {
    global $db;
    
    $sql = "SELECT setting_value FROM system_settings WHERE setting_key = '" . $db->escape($key) . "'";
    $result = $db->fetchOne($sql);
    
    return $result ? $result['setting_value'] : null;
}

// Get system fee percentage
function getSystemFee($type) {
    global $db;
    
    $sql = "SELECT percentage FROM system_fees WHERE fee_type = '" . $db->escape($type) . "'";
    $result = $db->fetchOne($sql);
    
    return $result ? $result['percentage'] : 0;
}

// Calculate system fee amount
function calculateFee($amount, $feeType) {
    $percentage = getSystemFee($feeType);
    return ($amount * $percentage) / 100;
}

// Upload file
function uploadFile($file, $directory = 'uploads') {
    $targetDir = ROOT_PATH . "assets/" . $directory . "/";
    
    // Create directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = basename($file["name"]);
    $targetFile = $targetDir . time() . "_" . $fileName;
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    
    // Check if file already exists
    if (file_exists($targetFile)) {
        return ["success" => false, "message" => "File already exists."];
    }
    
    // Check file size (limit to 5MB)
    if ($file["size"] > 5000000) {
        return ["success" => false, "message" => "File is too large."];
    }
    
    // Allow certain file formats
    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
        return ["success" => false, "message" => "Only JPG, JPEG, PNG & GIF files are allowed."];
    }
    
    // Upload file
    if (move_uploaded_file($file["tmp_name"], $targetFile)) {
        return [
            "success" => true, 
            "file_path" => "assets/" . $directory . "/" . time() . "_" . $fileName
        ];
    } else {
        return ["success" => false, "message" => "There was an error uploading your file."];
    }
}
/**
 * Convert a timestamp to a human-readable "time ago" format
 * 
 * @param string $datetime The datetime string to convert
 * @return string The formatted "time ago" string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    // Define time periods in seconds
    $minute = 60;
    $hour = $minute * 60;
    $day = $hour * 24;
    $week = $day * 7;
    $month = $day * 30;
    $year = $day * 365;
    
    // Calculate the time ago
    if ($diff < $minute) {
        return $diff == 1 ? "1 second ago" : "$diff seconds ago";
    } elseif ($diff < $hour) {
        $minutes = floor($diff / $minute);
        return $minutes == 1 ? "1 minute ago" : "$minutes minutes ago";
    } elseif ($diff < $day) {
        $hours = floor($diff / $hour);
        return $hours == 1 ? "1 hour ago" : "$hours hours ago";
    } elseif ($diff < $week) {
        $days = floor($diff / $day);
        return $days == 1 ? "1 day ago" : "$days days ago";
    } elseif ($diff < $month) {
        $weeks = floor($diff / $week);
        return $weeks == 1 ? "1 week ago" : "$weeks weeks ago";
    } elseif ($diff < $year) {
        $months = floor($diff / $month);
        return $months == 1 ? "1 month ago" : "$months months ago";
    } else {
        $years = floor($diff / $year);
        return $years == 1 ? "1 year ago" : "$years years ago";
    }
}
/**
 * Get the user's profile image URL
 * 
 * @return string The URL to the user's profile image or a default image
 */
function getUserProfileImage() {
    global $db;
    
    if (!isLoggedIn()) {
        return 'https://www.gravatar.com/avatar/205e460b479e2e5b48aec07710ed50cb?s=200';
    }
    
    $userId = getCurrentUserId();
    $sql = "SELECT profile_image FROM users WHERE id = $userId";
    $user = $db->fetchOne($sql);
    
    if ($user && !empty($user['profile_image'])) {
        return SITE_URL . '/' . $user['profile_image'];
    }
    
    return SITE_URL . '/assets/images/default-avatar.png';
}
/**
 * Format a datetime string
 * 
 * @param string $datetime The datetime string to format
 * @return string The formatted datetime
 */
function formatDateTime($datetime) {
    $timestamp = strtotime($datetime);
    return date('F j, Y, g:i a', $timestamp);
}

?>