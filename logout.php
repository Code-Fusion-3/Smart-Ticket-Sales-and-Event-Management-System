<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (isLoggedIn()) {
    // Clear remember me cookie if it exists
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        
        // Delete token from database
        $sql = "DELETE FROM user_tokens WHERE token = '" . $db->escape($token) . "'";
        $db->query($sql);
        
        // Expire the cookie
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    
    // Destroy session
    session_unset();
    session_destroy();
    
    // Set success message in a temporary cookie
    setcookie('logout_message', 'You have been successfully logged out.', time() + 60, '/');
}

// Redirect to home page
redirect('index.php');