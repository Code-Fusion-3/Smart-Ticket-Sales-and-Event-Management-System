<?php
require_once 'config.php';
require_once 'db.php';
require_once 'functions.php';

// Register new user
function registerUser($username, $email, $password, $phone, $role = 'customer') {
    global $db;
    
    // Check if email already exists
    $sql = "SELECT id FROM users WHERE email = '" . $db->escape($email) . "'";
    $user = $db->fetchOne($sql);
    
    if ($user) {
        return ["success" => false, "message" => "Email already exists."];
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $sql = "INSERT INTO users (username, email, password_hash, phone_number, role, status) 
            VALUES ('" . $db->escape($username) . "', '" . $db->escape($email) . "', 
            '" . $db->escape($passwordHash) . "', '" . $db->escape($phone) . "', 
            '" . $db->escape($role) . "', 'active')";
    
    $userId = $db->insert($sql);
    
    if ($userId) {
        // Create cart for customer
        if ($role === 'customer') {
            $sql = "INSERT INTO cart (user_id) VALUES ($userId)";
            $db->query($sql);
        }
        
        return ["success" => true, "user_id" => $userId];
    }
    
    return ["success" => false, "message" => "Registration failed."];
}

// Login user
function loginUser($email, $password) {
    global $db;
    
    $sql = "SELECT id, username, email, password_hash, role, status 
            FROM users WHERE email = '" . $db->escape($email) . "'";
    
    $user = $db->fetchOne($sql);
    
    if (!$user) {
        return ["success" => false, "message" => "User not found."];
    }
    
    if ($user['status'] !== 'active') {
        return ["success" => false, "message" => "Account is not active."];
    }
    
    if (password_verify($password, $user['password_hash'])) {
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        
        return ["success" => true, "user" => $user];
    }
    
    return ["success" => false, "message" => "Invalid password."];
}

// Logout user
function logoutUser() {
    // Unset all session variables
    $_SESSION = [];
    
    // Destroy the session
    session_destroy();
    
    return true;
}

// Update user profile
function updateUserProfile($userId, $username, $phone, $profileImage = null) {
    global $db;
    
    $updateFields = "username = '" . $db->escape($username) . "', 
                    phone_number = '" . $db->escape($phone) . "'";
    
    if ($profileImage) {
        $updateFields .= ", profile_image = '" . $db->escape($profileImage) . "'";
    }
    
    $sql = "UPDATE users SET $updateFields WHERE id = $userId";
    
    $result = $db->update($sql);
    
    return $result > 0;
}

// Change password
function changePassword($userId, $currentPassword, $newPassword) {
    global $db;
    
    // Get current password hash
    $sql = "SELECT password_hash FROM users WHERE id = $userId";
    $user = $db->fetchOne($sql);
    
    if (!$user) {
        return ["success" => false, "message" => "User not found."];
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password_hash'])) {
        return ["success" => false, "message" => "Current password is incorrect."];
    }
    
    // Hash new password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $sql = "UPDATE users SET password_hash = '" . $db->escape($newPasswordHash) . "' WHERE id = $userId";
    $result = $db->update($sql);
    
    return ["success" => ($result > 0), "message" => ($result > 0 ? "Password updated successfully." : "Failed to update password.")];
}

// Get user by ID
function getUserById($userId) {
    global $db;
    
    $sql = "SELECT id, username, email, phone_number, role, profile_image, balance, status, created_at 
            FROM users WHERE id = $userId";
    
    return $db->fetchOne($sql);
}

// Check if user has permission
function checkPermission($requiredRole) {
    if (!isLoggedIn()) {
        redirect("login.php");
    }
    
    if (!hasRole($requiredRole)) {
        redirect("index.php?error=unauthorized");
        exit;
    }
    
    return true;
}
