<?php
// Test session functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session before any output
if (session_start()) {
    echo "Session started successfully\n";
    echo "Session ID: " . session_id() . "\n";
    echo "Session status: " . session_status() . "\n";

    // Test setting a value
    $_SESSION['test'] = 'Hello World';
    echo "Set session value: " . $_SESSION['test'] . "\n";

    // Test session file
    $sessionFile = ini_get('session.save_path') . '/sess_' . session_id();
    echo "Session file path: " . $sessionFile . "\n";
    echo "Session file exists: " . (file_exists($sessionFile) ? 'Yes' : 'No') . "\n";
    if (file_exists($sessionFile)) {
        echo "Session file permissions: " . substr(sprintf('%o', fileperms($sessionFile)), -4) . "\n";
        echo "Session file owner: " . posix_getpwuid(fileowner($sessionFile))['name'] . "\n";
    }

    session_write_close();
    echo "Session closed\n";
} else {
    echo "Failed to start session\n";
    echo "Error: " . error_get_last()['message'] . "\n";
}
?>