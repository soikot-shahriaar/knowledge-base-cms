<?php
/**
 * Utility Functions
 * Common helper functions for the Knowledge Base CMS
 */

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate and sanitize text input
 */
function validateText($text, $minLength = 1, $maxLength = 255) {
    $text = trim($text);
    $length = strlen($text);
    
    if ($length < $minLength || $length > $maxLength) {
        return false;
    }
    
    return sanitizeInput($text);
}

/**
 * Generate URL-friendly slug from text
 */
function generateSlug($text) {
    // Convert to lowercase
    $slug = strtolower($text);
    
    // Replace non-alphanumeric characters with hyphens
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    
    // Remove leading/trailing hyphens
    $slug = trim($slug, '-');
    
    // Limit length
    $slug = substr($slug, 0, 100);
    
    return $slug;
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

/**
 * Format date with time
 */
function formatDateTime($date, $format = 'M j, Y g:i A') {
    return date($format, strtotime($date));
}

/**
 * Truncate text with ellipsis
 */
function truncateText($text, $length = 150, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $suffix;
}

/**
 * Generate excerpt from HTML content
 */
function generateExcerpt($content, $length = 200) {
    // Strip HTML tags
    $text = strip_tags($content);
    
    // Remove extra whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    
    return truncateText(trim($text), $length);
}

/**
 * Redirect to URL
 */
function redirect($url, $statusCode = 302) {
    header("Location: $url", true, $statusCode);
    exit;
}

/**
 * Get current page URL
 */
function getCurrentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    
    return "$protocol://$host$uri";
}

/**
 * Build URL with query parameters
 */
function buildUrl($baseUrl, $params = []) {
    if (empty($params)) {
        return $baseUrl;
    }
    
    $queryString = http_build_query($params);
    $separator = strpos($baseUrl, '?') !== false ? '&' : '?';
    
    return $baseUrl . $separator . $queryString;
}

/**
 * Get pagination data
 */
function getPagination($currentPage, $totalItems, $itemsPerPage) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    return [
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'total_items' => $totalItems,
        'items_per_page' => $itemsPerPage,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
        'previous_page' => $currentPage > 1 ? $currentPage - 1 : null,
        'next_page' => $currentPage < $totalPages ? $currentPage + 1 : null
    ];
}

/**
 * Display flash messages
 */
function setFlashMessage($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash messages
 */
function getFlashMessages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Display flash messages HTML
 */
function displayFlashMessages() {
    $messages = getFlashMessages();
    $html = '';
    
    foreach ($messages as $message) {
        $type = $message['type'];
        $text = sanitizeInput($message['message']);
        
        // Map message types to Bootstrap alert classes
        $alertClass = match($type) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info',
            default => 'alert-info'
        };
        
        $html .= "<div class='alert $alertClass alert-dismissible fade show' role='alert'>";
        $html .= $text;
        $html .= "<button type='button' class='btn-close' data-bs-dismiss='alert'></button>";
        $html .= "</div>";
    }
    
    return $html;
}

/**
 * Check if string contains search term
 */
function containsSearchTerm($haystack, $needle) {
    return stripos($haystack, $needle) !== false;
}

/**
 * Highlight search terms in text
 */
function highlightSearchTerms($text, $searchTerms) {
    if (empty($searchTerms)) {
        return $text;
    }
    
    $terms = is_array($searchTerms) ? $searchTerms : [$searchTerms];
    
    foreach ($terms as $term) {
        $term = preg_quote($term, '/');
        $text = preg_replace("/($term)/i", '<mark>$1</mark>', $text);
    }
    
    return $text;
}

/**
 * Validate email address
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Check if request is POST
 */
function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Check if request is GET
 */
function isGet() {
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

/**
 * Get POST data with default value
 */
function getPost($key, $default = null) {
    return $_POST[$key] ?? $default;
}

/**
 * Get GET data with default value
 */
function getGet($key, $default = null) {
    return $_GET[$key] ?? $default;
}

/**
 * Include template with variables
 */
function includeTemplate($templatePath, $variables = []) {
    extract($variables);
    include $templatePath;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && 
           hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}
?>

