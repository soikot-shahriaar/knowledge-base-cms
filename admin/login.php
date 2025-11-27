<?php
/**
 * Admin Login Page
 * Handles admin authentication
 */

require_once '../includes/bootstrap.php';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$success = '';

// Handle login form submission
if (isPost()) {
    $username = getPost('username', '');
    $password = getPost('password', '');
    $csrfToken = getPost('csrf_token', '');
    
    // Validate CSRF token
    if (!$auth->validateCSRF($csrfToken)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Attempt login
        $result = $auth->login($username, $password);
        
        if ($result['success']) {
            setFlashMessage('success', 'Welcome back, ' . htmlspecialchars($result['user']['username']) . '!');
            redirect('index.php');
        } else {
            $error = $result['message'];
        }
    }
}

$pageTitle = 'Admin Login';
includeHeader($pageTitle);
?>

<div class="login-container" style="margin-top: 0;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card">
                    <div class="card-header text-center">
                        <div class="mb-3">
                            <i class="bi bi-shield-lock fs-1 text-primary"></i>
                        </div>
                        <h4 class="mb-0 fw-bold">Admin Login</h4>
                        <p class="text-muted mb-0">Access your knowledge base dashboard</p>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger d-flex align-items-center" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <div><?php echo htmlspecialchars($error); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success d-flex align-items-center" role="alert">
                                <i class="bi bi-check-circle me-2"></i>
                                <div><?php echo htmlspecialchars($success); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" id="loginForm">
                            <?php echo $auth->getCSRFInput(); ?>
                            
                            <div class="mb-3">
                                <label for="username" class="form-label fw-semibold">Username or Email</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-person text-muted"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control border-start-0" 
                                           id="username" 
                                           name="username" 
                                           value="<?php echo htmlspecialchars(getPost('username', '')); ?>"
                                           required 
                                           autofocus
                                           placeholder="Enter username or email">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label fw-semibold">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="bi bi-lock text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           class="form-control border-start-0" 
                                           id="password" 
                                           name="password" 
                                           required
                                           placeholder="Enter password">
                                    <button class="btn btn-outline-secondary border-start-0" 
                                            type="button" 
                                            id="togglePassword"
                                            title="Toggle password visibility">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-box-arrow-in-right me-2"></i>
                                    Sign In
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Default credentials: admin / admin123
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <a href="../index.php" class="text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i>
                        Back to Knowledge Base
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordField = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        passwordField.type = 'password';
        icon.className = 'bi bi-eye';
    }
});

// Form validation
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();
    
    if (!username || !password) {
        e.preventDefault();
        alert('Please fill in all required fields.');
        return false;
    }
    
    if (password.length < <?php echo PASSWORD_MIN_LENGTH; ?>) {
        e.preventDefault();
        alert('Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long.');
        return false;
    }
});

// Auto-focus on username field
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('username').focus();
});
</script>

<?php includeFooter(); ?>

