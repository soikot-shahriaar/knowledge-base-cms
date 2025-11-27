<?php
/**
 * Application Bootstrap
 * Initialize the Knowledge Base CMS application
 */

// Session Configuration (must be set before session starts)
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration
require_once __DIR__ . '/../config.php';

// Include core classes
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/functions.php';

// Initialize global objects
$db = new Database();
$auth = new Auth();

// Set error handler for production
if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
    set_error_handler(function($severity, $message, $file, $line) {
        if (error_reporting() & $severity) {
            throw new ErrorException($message, 0, $severity, $file, $line);
        }
    });
}

// Helper function to include header
function includeHeader($title = '', $additionalCSS = []) {
    $pageTitle = !empty($title) ? $title . ' - ' . SITE_NAME : SITE_NAME;
    include __DIR__ . '/../templates/header.php';
}

// Helper function to include footer
function includeFooter($additionalJS = []) {
    include __DIR__ . '/../templates/footer.php';
}

// Helper function to check database connection
function checkDatabaseConnection() {
    global $db;
    try {
        $db->connect();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>

