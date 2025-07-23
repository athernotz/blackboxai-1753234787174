<?php
/**
 * =====================================================
 * AUTHENTICATION CHECK MIDDLEWARE
 * =====================================================
 * Middleware to check user authentication and authorization
 * Include this file at the top of protected pages
 * 
 * @author Village Admin System
 * @version 1.0.0
 * @created 2025
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/../../api/config/database.php';

/**
 * Authentication and Authorization Class
 */
class AuthCheck {
    
    private $db;
    private $sessionTimeout;
    private $currentUser;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->sessionTimeout = $_ENV['SESSION_LIFETIME'] ?? 3600; // 1 hour default
    }
    
    /**
     * Check if user is authenticated
     * 
     * @param array $requiredPermissions Optional permissions required
     * @param bool $redirectOnFail Whether to redirect on authentication failure
     * @return bool|array Returns user data if authenticated, false otherwise
     */
    public function checkAuth($requiredPermissions = [], $redirectOnFail = true) {
        try {
            // Check session authentication
            if (!$this->isSessionValid()) {
                // Try remember me authentication
                if (!$this->checkRememberMe()) {
                    if ($redirectOnFail) {
                        $this->redirectToLogin('Session expired. Please login again.');
                    }
                    return false;
                }
            }
            
            // Get current user data
            $this->currentUser = $this->getCurrentUser();
            
            if (!$this->currentUser) {
                if ($redirectOnFail) {
                    $this->redirectToLogin('User not found. Please login again.');
                }
                return false;
            }
            
            // Check if user account is still active
            if ($this->currentUser['status'] !== 'active') {
                $this->destroySession();
                if ($redirectOnFail) {
                    $this->redirectToLogin('Account is inactive. Please contact administrator.');
                }
                return false;
            }
            
            // Check permissions if required
            if (!empty($requiredPermissions)) {
                if (!$this->hasPermissions($requiredPermissions)) {
                    if ($redirectOnFail) {
                        $this->redirectToLogin('Access denied. Insufficient permissions.');
                    }
                    return false;
                }
            }
            
            // Update last activity
            $this->updateLastActivity();
            
            return $this->currentUser;
            
        } catch (Exception $e) {
            $this->logError('Authentication check failed', $e);
            
            if ($redirectOnFail) {
                $this->redirectToLogin('Authentication error. Please try again.');
            }
            return false;
        }
    }
    
    /**
     * Check if current session is valid
     */
    private function isSessionValid() {
        // Check if user session exists
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }
        
        // Check session timeout
        $lastActivity = $_SESSION['last_activity'] ?? $_SESSION['login_time'];
        if ((time() - $lastActivity) > $this->sessionTimeout) {
            $this->destroySession();
            return false;
        }
        
        // Check if session is hijacked (basic check)
        if (!$this->validateSessionIntegrity()) {
            $this->destroySession();
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate session integrity (basic anti-hijacking)
     */
    private function validateSessionIntegrity() {
        // Check user agent consistency (basic check)
        $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $sessionUserAgent = $_SESSION['user_agent'] ?? '';
        
        // If user agent was stored and doesn't match, possible hijacking
        if (!empty($sessionUserAgent) && $sessionUserAgent !== $currentUserAgent) {
            $this->logSecurity('Possible session hijacking detected', [
                'user_id' => $_SESSION['user_id'] ?? 'unknown',
                'stored_user_agent' => $sessionUserAgent,
                'current_user_agent' => $currentUserAgent
            ]);
            return false;
        }
        
        // Store user agent if not already stored
        if (empty($sessionUserAgent)) {
            $_SESSION['user_agent'] = $currentUserAgent;
        }
        
        return true;
    }
    
    /**
     * Check remember me authentication
     */
    private function checkRememberMe() {
        if (!isset($_COOKIE['remember_token'])) {
            return false;
        }
        
        $rememberToken = $_COOKIE['remember_token'];
        
        // Find user with matching remember token
        $user = $this->db->fetchOne(
            "SELECT * FROM users 
             WHERE remember_token = ? AND status = 'active' AND deleted_at IS NULL",
            [$rememberToken]
        );
        
        if (!$user) {
            // Clear invalid cookie
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            return false;
        }
        
        // Regenerate session
        session_regenerate_id(true);
        
        // Set session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_uuid'] = $user['uuid'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['remember_login'] = true;
        
        // Generate new CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        // Log remember me login
        $this->logActivity($user['id'], 'remember_login', 'User logged in via remember me');
        
        return true;
    }
    
    /**
     * Get current user data from database
     */
    private function getCurrentUser() {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        return $this->db->fetchOne(
            "SELECT id, uuid, username, email, full_name, role, status, last_login, created_at 
             FROM users 
             WHERE id = ? AND deleted_at IS NULL",
            [$_SESSION['user_id']]
        );
    }
    
    /**
     * Check if user has required permissions
     */
    private function hasPermissions($requiredPermissions) {
        if (!$this->currentUser) {
            return false;
        }
        
        $userPermissions = $this->getUserPermissions($this->currentUser['role']);
        
        // Check each required permission
        foreach ($requiredPermissions as $permission) {
            if (!in_array($permission, $userPermissions)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get user permissions based on role
     */
    private function getUserPermissions($role) {
        $permissions = [
            'super_admin' => [
                'users.create', 'users.read', 'users.update', 'users.delete',
                'surat.create', 'surat.read', 'surat.update', 'surat.delete', 'surat.approve',
                'penduduk.create', 'penduduk.read', 'penduduk.update', 'penduduk.delete',
                'settings.read', 'settings.update',
                'reports.read', 'logs.read'
            ],
            'admin' => [
                'surat.create', 'surat.read', 'surat.update', 'surat.approve',
                'penduduk.create', 'penduduk.read', 'penduduk.update',
                'reports.read'
            ],
            'operator' => [
                'surat.create', 'surat.read', 'surat.update',
                'penduduk.read', 'penduduk.update'
            ],
            'user' => [
                'surat.create', 'surat.read'
            ]
        ];
        
        return $permissions[$role] ?? [];
    }
    
    /**
     * Update last activity timestamp
     */
    private function updateLastActivity() {
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Destroy session completely
     */
    private function destroySession() {
        // Unset all session variables
        $_SESSION = array();
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Redirect to login page with message
     */
    private function redirectToLogin($message = '') {
        $loginUrl = '/desa-admin/admin/login.php';
        
        if (!empty($message)) {
            $loginUrl .= '?message=' . urlencode($message);
        }
        
        // If it's an AJAX request, return JSON response
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => $message ?: 'Authentication required',
                'redirect' => $loginUrl,
                'error_code' => 'AUTHENTICATION_REQUIRED'
            ]);
            exit();
        }
        
        // Regular redirect
        header("Location: $loginUrl");
        exit();
    }
    
    /**
     * Get current user data (public method)
     */
    public function getCurrentUserData() {
        return $this->currentUser;
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($role) {
        return $this->currentUser && $this->currentUser['role'] === $role;
    }
    
    /**
     * Check if user has any of the specified roles
     */
    public function hasAnyRole($roles) {
        if (!$this->currentUser) {
            return false;
        }
        
        return in_array($this->currentUser['role'], $roles);
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Log user activity
     */
    private function logActivity($userId, $action, $description) {
        try {
            // Create table if not exists
            $this->createUserActivitiesTable();
            
            $this->db->insert(
                "INSERT INTO user_activities (user_id, action, description, ip_address, user_agent, created_at) 
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [
                    $userId,
                    $action,
                    $description,
                    $this->getClientIP(),
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]
            );
        } catch (Exception $e) {
            $this->logError('Failed to log activity', $e);
        }
    }
    
    /**
     * Log security events
     */
    private function logSecurity($message, $context = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => 'SECURITY',
            'message' => $message,
            'context' => $context,
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? ''
        ];
        
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/security_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, json_encode($logData) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Create user activities table if not exists
     */
    private function createUserActivitiesTable() {
        $this->db->executeQuery("
            CREATE TABLE IF NOT EXISTS user_activities (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                action VARCHAR(50) NOT NULL,
                description TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB
        ");
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Log error messages
     */
    private function logError($message, $exception) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => 'ERROR',
            'message' => $message,
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'ip' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/auth_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, json_encode($logData) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

/**
 * =====================================================
 * HELPER FUNCTIONS
 * =====================================================
 */

/**
 * Quick authentication check (shorthand function)
 * 
 * @param array $requiredPermissions
 * @param bool $redirectOnFail
 * @return bool|array
 */
function requireAuth($requiredPermissions = [], $redirectOnFail = true) {
    $auth = new AuthCheck();
    return $auth->checkAuth($requiredPermissions, $redirectOnFail);
}

/**
 * Check if user has specific permission
 * 
 * @param string $permission
 * @return bool
 */
function hasPermission($permission) {
    $auth = new AuthCheck();
    $user = $auth->checkAuth([], false);
    
    if (!$user) {
        return false;
    }
    
    return $auth->hasPermissions([$permission]);
}

/**
 * Check if user has specific role
 * 
 * @param string $role
 * @return bool
 */
function hasRole($role) {
    $auth = new AuthCheck();
    $user = $auth->checkAuth([], false);
    
    if (!$user) {
        return false;
    }
    
    return $auth->hasRole($role);
}

/**
 * Get current authenticated user
 * 
 * @return array|null
 */
function getCurrentUser() {
    $auth = new AuthCheck();
    return $auth->checkAuth([], false);
}

/**
 * Generate CSRF token for forms
 * 
 * @return string
 */
function csrfToken() {
    $auth = new AuthCheck();
    return $auth->generateCSRFToken();
}

/**
 * =====================================================
 * USAGE EXAMPLES
 * =====================================================
 * 
 * // Basic authentication check (redirect if not authenticated)
 * require_once 'includes/auth_check.php';
 * $user = requireAuth();
 * 
 * // Check with specific permissions
 * $user = requireAuth(['surat.create', 'surat.update']);
 * 
 * // Check without redirect (for AJAX endpoints)
 * $user = requireAuth([], false);
 * if (!$user) {
 *     // Handle unauthenticated user
 * }
 * 
 * // Check specific permission
 * if (hasPermission('users.delete')) {
 *     // Show delete button
 * }
 * 
 * // Check role
 * if (hasRole('super_admin')) {
 *     // Show admin features
 * }
 * 
 * // Use in forms
 * <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
 */
?>
