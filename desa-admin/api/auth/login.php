<?php
/**
 * =====================================================
 * LOGIN API ENDPOINT
 * =====================================================
 * Handles user authentication with security measures
 * 
 * @author Village Admin System
 * @version 1.0.0
 * @created 2025
 */

// Set headers for API response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST method.',
        'error_code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit();
}

// Start session
session_start();

// Include required files
require_once '../config/database.php';

/**
 * Authentication class for handling login operations
 */
class AuthController {
    
    private $db;
    private $maxLoginAttempts = 5;
    private $lockoutDuration = 900; // 15 minutes in seconds
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Handle login request
     */
    public function login() {
        try {
            // Get and validate input
            $input = $this->getInput();
            $validationResult = $this->validateInput($input);
            
            if (!$validationResult['valid']) {
                return $this->sendResponse(false, $validationResult['message'], null, 400);
            }
            
            // Check CSRF token
            if (!$this->validateCSRFToken($input['csrf_token'] ?? '')) {
                return $this->sendResponse(false, 'Invalid CSRF token', null, 403);
            }
            
            // Check rate limiting
            if (!$this->checkRateLimit($input['username'])) {
                return $this->sendResponse(false, 'Too many login attempts. Please try again later.', null, 429);
            }
            
            // Authenticate user
            $user = $this->authenticateUser($input['username'], $input['password']);
            
            if (!$user) {
                $this->recordFailedAttempt($input['username']);
                return $this->sendResponse(false, 'Invalid username or password', null, 401);
            }
            
            // Check if user account is active
            if ($user['status'] !== 'active') {
                return $this->sendResponse(false, 'Account is inactive. Please contact administrator.', null, 403);
            }
            
            // Check if account is locked
            if ($this->isAccountLocked($user)) {
                return $this->sendResponse(false, 'Account is temporarily locked due to multiple failed attempts.', null, 423);
            }
            
            // Generate session and tokens
            $sessionData = $this->createUserSession($user, $input['remember_me'] ?? false);
            
            // Update user login information
            $this->updateUserLogin($user['id']);
            
            // Reset failed attempts
            $this->resetFailedAttempts($user['id']);
            
            // Log successful login
            $this->logActivity($user['id'], 'login_success', 'User logged in successfully');
            
            return $this->sendResponse(true, 'Login successful', [
                'user' => [
                    'id' => $user['id'],
                    'uuid' => $user['uuid'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ],
                'session' => $sessionData,
                'permissions' => $this->getUserPermissions($user['role'])
            ]);
            
        } catch (Exception $e) {
            $this->logError('Login error', $e);
            return $this->sendResponse(false, 'An error occurred during login. Please try again.', null, 500);
        }
    }
    
    /**
     * Get and sanitize input data
     */
    private function getInput() {
        $rawInput = file_get_contents('php://input');
        $input = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input');
        }
        
        // Sanitize input
        return [
            'username' => $this->db->sanitizeInput($input['username'] ?? ''),
            'password' => $input['password'] ?? '',
            'remember_me' => filter_var($input['remember_me'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'csrf_token' => $this->db->sanitizeInput($input['csrf_token'] ?? '')
        ];
    }
    
    /**
     * Validate input data
     */
    private function validateInput($input) {
        $errors = [];
        
        // Validate username
        if (empty($input['username'])) {
            $errors[] = 'Username is required';
        } elseif (strlen($input['username']) < 3) {
            $errors[] = 'Username must be at least 3 characters';
        }
        
        // Validate password
        if (empty($input['password'])) {
            $errors[] = 'Password is required';
        } elseif (strlen($input['password']) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        }
        
        return [
            'valid' => empty($errors),
            'message' => empty($errors) ? 'Valid' : implode(', ', $errors)
        ];
    }
    
    /**
     * Validate CSRF token
     */
    private function validateCSRFToken($token) {
        // In development, skip CSRF validation if not set
        if (($_ENV['APP_ENV'] ?? 'production') === 'development' && empty($token)) {
            return true;
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Check rate limiting for login attempts
     */
    private function checkRateLimit($username) {
        $ip = $this->getClientIP();
        $timeWindow = time() - 3600; // 1 hour window
        
        // Check attempts by IP
        $ipAttempts = $this->db->fetchOne(
            "SELECT COUNT(*) as attempts FROM login_attempts 
             WHERE ip_address = ? AND created_at > FROM_UNIXTIME(?) AND success = 0",
            [$ip, $timeWindow]
        );
        
        // Check attempts by username
        $usernameAttempts = $this->db->fetchOne(
            "SELECT COUNT(*) as attempts FROM login_attempts 
             WHERE username = ? AND created_at > FROM_UNIXTIME(?) AND success = 0",
            [$username, $timeWindow]
        );
        
        $maxAttempts = $_ENV['RATE_LIMIT_LOGIN'] ?? 5;
        
        return ($ipAttempts['attempts'] ?? 0) < $maxAttempts && 
               ($usernameAttempts['attempts'] ?? 0) < $maxAttempts;
    }
    
    /**
     * Authenticate user credentials
     */
    private function authenticateUser($username, $password) {
        // Find user by username or email
        $user = $this->db->fetchOne(
            "SELECT * FROM users 
             WHERE (username = ? OR email = ?) AND deleted_at IS NULL",
            [$username, $username]
        );
        
        if (!$user) {
            return false;
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            return false;
        }
        
        return $user;
    }
    
    /**
     * Check if account is locked
     */
    private function isAccountLocked($user) {
        if (empty($user['locked_until'])) {
            return false;
        }
        
        $lockedUntil = strtotime($user['locked_until']);
        return $lockedUntil > time();
    }
    
    /**
     * Create user session
     */
    private function createUserSession($user, $rememberMe = false) {
        // Generate session ID
        session_regenerate_id(true);
        $sessionId = session_id();
        
        // Set session data
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_uuid'] = $user['uuid'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Generate CSRF token for future requests
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        $sessionData = [
            'session_id' => $sessionId,
            'csrf_token' => $_SESSION['csrf_token'],
            'expires_at' => time() + ($_ENV['SESSION_LIFETIME'] ?? 3600)
        ];
        
        // Handle remember me functionality
        if ($rememberMe) {
            $rememberToken = bin2hex(random_bytes(32));
            $expiresAt = time() + ($_ENV['REMEMBER_TOKEN_LIFETIME'] ?? 604800); // 7 days
            
            // Store remember token in database
            $this->db->update(
                "UPDATE users SET remember_token = ? WHERE id = ?",
                [$rememberToken, $user['id']]
            );
            
            // Set remember me cookie
            setcookie('remember_token', $rememberToken, $expiresAt, '/', '', true, true);
            
            $sessionData['remember_token'] = $rememberToken;
        }
        
        return $sessionData;
    }
    
    /**
     * Update user login information
     */
    private function updateUserLogin($userId) {
        $this->db->update(
            "UPDATE users SET 
             last_login = NOW(), 
             login_attempts = 0, 
             locked_until = NULL 
             WHERE id = ?",
            [$userId]
        );
    }
    
    /**
     * Record failed login attempt
     */
    private function recordFailedAttempt($username) {
        $ip = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Create login_attempts table if not exists
        $this->createLoginAttemptsTable();
        
        // Record attempt
        $this->db->insert(
            "INSERT INTO login_attempts (username, ip_address, user_agent, success, created_at) 
             VALUES (?, ?, ?, 0, NOW())",
            [$username, $ip, $userAgent]
        );
        
        // Check if user should be locked
        $this->checkAndLockUser($username);
    }
    
    /**
     * Reset failed attempts for user
     */
    private function resetFailedAttempts($userId) {
        $this->db->update(
            "UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?",
            [$userId]
        );
    }
    
    /**
     * Check and lock user if too many failed attempts
     */
    private function checkAndLockUser($username) {
        $user = $this->db->fetchOne(
            "SELECT id, login_attempts FROM users WHERE username = ? OR email = ?",
            [$username, $username]
        );
        
        if ($user) {
            $attempts = $user['login_attempts'] + 1;
            
            if ($attempts >= $this->maxLoginAttempts) {
                $lockUntil = date('Y-m-d H:i:s', time() + $this->lockoutDuration);
                
                $this->db->update(
                    "UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?",
                    [$attempts, $lockUntil, $user['id']]
                );
                
                $this->logActivity($user['id'], 'account_locked', 'Account locked due to multiple failed login attempts');
            } else {
                $this->db->update(
                    "UPDATE users SET login_attempts = ? WHERE id = ?",
                    [$attempts, $user['id']]
                );
            }
        }
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
     * Create login attempts table if not exists
     */
    private function createLoginAttemptsTable() {
        $this->db->executeQuery("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                username VARCHAR(100) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT,
                success BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_username (username),
                INDEX idx_ip_address (ip_address),
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
     * Log user activity
     */
    private function logActivity($userId, $action, $description) {
        try {
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
            // Create table if not exists
            $this->createUserActivitiesTable();
            
            // Try again
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
        }
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
    
    /**
     * Send JSON response
     */
    private function sendResponse($success, $message, $data = null, $httpCode = 200) {
        http_response_code($httpCode);
        
        $response = [
            'success' => $success,
            'message' => $message,
            'timestamp' => date('c')
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit();
    }
}

// Initialize and handle login
try {
    $auth = new AuthController();
    $auth->login();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error_code' => 'INTERNAL_ERROR'
    ]);
}
?>
