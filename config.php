<?php
/**
 * Knowledge Base CMS Configuration
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'knowledge_base');
define('DB_USER', 'kb_user');
define('DB_PASS', 'kb_password');
define('DB_CHARSET', 'utf8mb4');

// Site Configuration
define('SITE_NAME', 'Knowledge Base CMS');
define('SITE_URL', 'http://localhost/knowledge-base-cms');
define('ADMIN_EMAIL', 'admin@example.com');

// Security Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MIN_PASSWORD_LENGTH', 6);
define('PASSWORD_MIN_LENGTH', 6); // Alias for compatibility
define('CSRF_TOKEN_NAME', '_token');

// Pagination
define('ARTICLES_PER_PAGE', 12);
define('SEARCH_RESULTS_PER_PAGE', 10);

// Environment
define('DEBUG_MODE', true); // Set to false for production

// Error Reporting (for development)
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}
?>

