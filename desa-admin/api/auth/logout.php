<?php
/**
 * =====================================================
 * LOGOUT API ENDPOINT
 * =====================================================
 * Handles user logout with session cleanup
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
 * Logout Controller
 */
class LogoutController {
    
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Handle logout request
     */
    public function logout() {
        try {
            // Check if user is logged in
            if (!isset($_SESSION['user_id'])) {
                return $this->sendResponse(false, 'User not logged in', null, 401);
            }
            
            $userId = $_SESSION['user_id'];
            $username = $_SESSION['username'] ?? 'Unknown';
            
            // Log logout activity
            $this->logActivity($userId, 'logout', 'User logged out successfully');
            
            // Clear remember token from database
            $this->clearRememberToken($userId);
            
            // Clear remember me cookie
            $this->clearRememberCookie();
            
            // Destroy session
            $this->destroySession();
            
            return $this->sendResponse(true, 'Logout successful', [
                'logged_out_at' => date('c'),
                'redirect_url' => '/admin/login.php'
            ]);
            
        } catch (Exception $e) {
            $this->logError('Logout error', $e);
            return $this->sendResponse(false, 'An error occurred during logout', null, 500);
        }
    }
    
    /**
     * Clear remember token from database
     */
    private function clearRememberToken($userId) {
        try {
            $this->db->update(
                "UPDATE users SET remember_token = NULL WHERE id = ?",
                [$userId]
            );
        } catch (Exception $e) {
            // Log error but don't fail logout
            $this->logError('Failed to clear remember token', $e);
        }
    }
    
    /**
     * Clear remember me cookie
     */
    private function clearRememberCookie() {
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    }
    
    /**
     * Destroy user session completely
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

// Initialize and handle logout
try {
    $logout = new LogoutController();
    $logout->logout();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error_code' => 'INTERNAL_ERROR'
    ]);
}
?>
