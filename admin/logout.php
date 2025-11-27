<?php
/**
 * Admin Logout
 * Handles user logout and session cleanup
 */

require_once '../includes/bootstrap.php';

// Perform logout
if ($auth->isLoggedIn()) {
    $username = $auth->getCurrentUser()['username'];
    $auth->logout();
    
    // Set flash message for next page load
    session_start();
    setFlashMessage('success', 'You have been successfully logged out. Goodbye, ' . htmlspecialchars($username) . '!');
}

// Redirect to login page
redirect('login.php');
?>

