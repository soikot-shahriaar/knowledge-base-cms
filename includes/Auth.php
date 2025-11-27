<?php
/**
 * Authentication Class
 * Handles user authentication, sessions, and security
 */

require_once 'Database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Authenticate user login
     */
    public function login($username, $password) {
        // Input validation
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required'];
        }
        
        try {
            // Find user by username or email
            $sql = "SELECT id, username, email, password_hash, role FROM users 
                    WHERE username = ? OR email = ? LIMIT 1";
            $user = $this->db->fetchOne($sql, [$username, $username]);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            // Create session
            $this->createSession($user);
            
            return ['success' => true, 'message' => 'Login successful', 'user' => $user];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Create user session
     */
    private function createSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Check if user has admin role
     */
    public function isAdmin() {
        return $this->isLoggedIn() && 
               isset($_SESSION['role']) && 
               ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'editor');
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'role' => $_SESSION['role']
        ];
    }
    
    /**
     * Logout user
     */
    public function logout() {
        // Clear session data
        $_SESSION = [];
        
        // Delete session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Destroy session
        session_destroy();
        
        return true;
    }
    
    /**
     * Require login (redirect if not logged in)
     */
    public function requireLogin($redirectUrl = 'login.php') {
        if (!$this->isLoggedIn()) {
            header("Location: $redirectUrl");
            exit;
        }
    }
    
    /**
     * Require admin access
     */
    public function requireAdmin($redirectUrl = 'login.php') {
        if (!$this->isAdmin()) {
            header("Location: $redirectUrl");
            exit;
        }
    }
    
    /**
     * Hash password
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRF($token) {
        return validateCSRFToken($token);
    }
    
    /**
     * Get CSRF token
     */
    public function getCSRFToken() {
        return generateCSRFToken();
    }
    
    /**
     * Generate CSRF token HTML input
     */
    public function getCSRFInput() {
        $token = $this->getCSRFToken();
        return "<input type='hidden' name='csrf_token' value='$token'>";
    }
    
    /**
     * Create new user (for admin use)
     */
    public function createUser($username, $email, $password, $role = 'admin') {
        // Validation
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        try {
            // Check if username or email already exists
            $sql = "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1";
            $existing = $this->db->fetchOne($sql, [$username, $email]);
            
            if ($existing) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }
            
            // Create user
            $passwordHash = $this->hashPassword($password);
            $sql = "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)";
            $userId = $this->db->insert($sql, [$username, $email, $passwordHash, $role]);
            
            return ['success' => true, 'message' => 'User created successfully', 'user_id' => $userId];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to create user: ' . $e->getMessage()];
        }
    }
}
?>

