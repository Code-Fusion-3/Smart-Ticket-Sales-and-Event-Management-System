<?php
session_start();
if (session_destroy()) {
    echo "Session destroyed successfully.";
} else {
    echo "Failed to destroy session.";
}
?>