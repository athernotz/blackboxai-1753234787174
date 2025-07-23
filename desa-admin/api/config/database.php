<?php
/**
 * =====================================================
 * DATABASE CONNECTION CLASS
 * =====================================================
 * Secure PDO database connection with error handling
 * and prepared statement support
 * 
 * @author Village Admin System
 * @version 1.0.0
 * @created 2025
 */

class Database {
    
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    private $port;
    private $connection;
    private static $instance = null;
    
    /**
     * Constructor - Initialize database configuration
     */
    public function __construct() {
        $this->loadEnvironmentConfig();
    }
    
    /**
     * Load configuration from environment or default values
     */
    private function loadEnvironmentConfig() {
        // Load .env file if exists
        $this->loadEnvFile();
        
        // Set database configuration with fallback values
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->port = $_ENV['DB_PORT'] ?? '3306';
        $this->db_name = $_ENV['DB_NAME'] ?? 'desa_admin';
        $this->username = $_ENV['DB_USERNAME'] ?? 'root';
        $this->password = $_ENV['DB_PASSWORD'] ?? '';
        $this->charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
    }
    
    /**
     * Load environment variables from .env file
     */
    private function loadEnvFile() {
        $envFile = __DIR__ . '/../../.env';
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                // Parse key=value pairs
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    
                    if (!empty($key)) {
                        $_ENV[$key] = $value;
                        putenv("$key=$value");
                    }
                }
            }
        }
    }
    
    /**
     * Get singleton instance of database connection
     * 
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Create PDO database connection
     * 
     * @return PDO
     * @throws Exception
     */
    public function getConnection() {
        if ($this->connection === null) {
            try {
                // Build DSN (Data Source Name)
                $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->db_name};charset={$this->charset}";
                
                // PDO options for security and performance
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset} COLLATE {$this->charset}_unicode_ci",
                    PDO::ATTR_TIMEOUT => 30,
                    PDO::ATTR_PERSISTENT => false
                ];
                
                // Create PDO connection
                $this->connection = new PDO($dsn, $this->username, $this->password, $options);
                
                // Log successful connection (only in development)
                if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
                    $this->logMessage('Database connection established successfully', 'info');
                }
                
            } catch (PDOException $e) {
                // Log error securely
                $this->logError('Database connection failed', $e);
                
                // Throw user-friendly error
                throw new Exception('Database connection failed. Please try again later.');
            }
        }
        
        return $this->connection;
    }
    
    /**
     * Execute a prepared statement with parameters
     * 
     * @param string $query SQL query with placeholders
     * @param array $params Parameters for prepared statement
     * @return PDOStatement
     * @throws Exception
     */
    public function executeQuery($query, $params = []) {
        try {
            $connection = $this->getConnection();
            $stmt = $connection->prepare($query);
            
            // Bind parameters with proper types
            foreach ($params as $key => $value) {
                $type = $this->getPDOType($value);
                if (is_int($key)) {
                    $stmt->bindValue($key + 1, $value, $type);
                } else {
                    $stmt->bindValue($key, $value, $type);
                }
            }
            
            $stmt->execute();
            
            // Log query in development mode
            if (($_ENV['DEV_LOG_QUERIES'] ?? 'false') === 'true') {
                $this->logMessage("Query executed: $query", 'debug');
            }
            
            return $stmt;
            
        } catch (PDOException $e) {
            $this->logError('Query execution failed', $e, ['query' => $query]);
            throw new Exception('Database query failed. Please try again.');
        }
    }
    
    /**
     * Get single record from database
     * 
     * @param string $query SQL query
     * @param array $params Parameters
     * @return array|null
     */
    public function fetchOne($query, $params = []) {
        $stmt = $this->executeQuery($query, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Get multiple records from database
     * 
     * @param string $query SQL query
     * @param array $params Parameters
     * @return array
     */
    public function fetchAll($query, $params = []) {
        $stmt = $this->executeQuery($query, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Insert record and return last insert ID
     * 
     * @param string $query SQL insert query
     * @param array $params Parameters
     * @return string Last insert ID
     */
    public function insert($query, $params = []) {
        $this->executeQuery($query, $params);
        return $this->getConnection()->lastInsertId();
    }
    
    /**
     * Update records and return affected rows count
     * 
     * @param string $query SQL update query
     * @param array $params Parameters
     * @return int Affected rows count
     */
    public function update($query, $params = []) {
        $stmt = $this->executeQuery($query, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Delete records and return affected rows count
     * 
     * @param string $query SQL delete query
     * @param array $params Parameters
     * @return int Affected rows count
     */
    public function delete($query, $params = []) {
        $stmt = $this->executeQuery($query, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Begin database transaction
     */
    public function beginTransaction() {
        $this->getConnection()->beginTransaction();
    }
    
    /**
     * Commit database transaction
     */
    public function commit() {
        $this->getConnection()->commit();
    }
    
    /**
     * Rollback database transaction
     */
    public function rollback() {
        $this->getConnection()->rollBack();
    }
    
    /**
     * Check if currently in transaction
     * 
     * @return bool
     */
    public function inTransaction() {
        return $this->getConnection()->inTransaction();
    }
    
    /**
     * Get appropriate PDO parameter type
     * 
     * @param mixed $value
     * @return int PDO parameter type
     */
    private function getPDOType($value) {
        switch (true) {
            case is_int($value):
                return PDO::PARAM_INT;
            case is_bool($value):
                return PDO::PARAM_BOOL;
            case is_null($value):
                return PDO::PARAM_NULL;
            default:
                return PDO::PARAM_STR;
        }
    }
    
    /**
     * Test database connection
     * 
     * @return bool
     */
    public function testConnection() {
        try {
            $this->getConnection();
            $stmt = $this->executeQuery("SELECT 1 as test");
            $result = $stmt->fetch();
            return $result['test'] === 1;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get database information
     * 
     * @return array
     */
    public function getDatabaseInfo() {
        try {
            $connection = $this->getConnection();
            
            return [
                'server_version' => $connection->getAttribute(PDO::ATTR_SERVER_VERSION),
                'client_version' => $connection->getAttribute(PDO::ATTR_CLIENT_VERSION),
                'connection_status' => $connection->getAttribute(PDO::ATTR_CONNECTION_STATUS),
                'server_info' => $connection->getAttribute(PDO::ATTR_SERVER_INFO),
                'database_name' => $this->db_name,
                'charset' => $this->charset
            ];
        } catch (Exception $e) {
            return ['error' => 'Unable to retrieve database information'];
        }
    }
    
    /**
     * Sanitize input to prevent XSS
     * 
     * @param string $input
     * @return string
     */
    public function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate and sanitize email
     * 
     * @param string $email
     * @return string|false
     */
    public function sanitizeEmail($email) {
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
    }
    
    /**
     * Log error messages securely
     * 
     * @param string $message
     * @param Exception $exception
     * @param array $context
     */
    private function logError($message, $exception, $context = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => 'ERROR',
            'message' => $message,
            'error' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'context' => $context
        ];
        
        $this->writeLog($logData);
    }
    
    /**
     * Log informational messages
     * 
     * @param string $message
     * @param string $level
     */
    private function logMessage($message, $level = 'info') {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message
        ];
        
        $this->writeLog($logData);
    }
    
    /**
     * Write log to file
     * 
     * @param array $logData
     */
    private function writeLog($logData) {
        $logDir = __DIR__ . '/../../logs';
        
        // Create logs directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/database_' . date('Y-m-d') . '.log';
        $logEntry = json_encode($logData) . PHP_EOL;
        
        // Write log entry
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Close database connection
     */
    public function closeConnection() {
        $this->connection = null;
    }
    
    /**
     * Destructor - Clean up connection
     */
    public function __destruct() {
        $this->closeConnection();
    }
}

/**
 * =====================================================
 * HELPER FUNCTIONS
 * =====================================================
 */

/**
 * Get database instance (shorthand function)
 * 
 * @return Database
 */
function db() {
    return Database::getInstance();
}

/**
 * Execute query with error handling (shorthand function)
 * 
 * @param string $query
 * @param array $params
 * @return PDOStatement
 */
function query($query, $params = []) {
    return db()->executeQuery($query, $params);
}

/**
 * Fetch single record (shorthand function)
 * 
 * @param string $query
 * @param array $params
 * @return array|null
 */
function fetchOne($query, $params = []) {
    return db()->fetchOne($query, $params);
}

/**
 * Fetch multiple records (shorthand function)
 * 
 * @param string $query
 * @param array $params
 * @return array
 */
function fetchAll($query, $params = []) {
    return db()->fetchAll($query, $params);
}

/**
 * =====================================================
 * USAGE EXAMPLES
 * =====================================================
 * 
 * // Basic usage
 * $db = Database::getInstance();
 * $users = $db->fetchAll("SELECT * FROM users WHERE status = ?", ['active']);
 * 
 * // Using shorthand functions
 * $user = fetchOne("SELECT * FROM users WHERE id = ?", [1]);
 * 
 * // Transaction example
 * $db->beginTransaction();
 * try {
 *     $db->insert("INSERT INTO users (name, email) VALUES (?, ?)", ['John', 'john@example.com']);
 *     $db->update("UPDATE settings SET value = ? WHERE key = ?", ['updated', 'last_update']);
 *     $db->commit();
 * } catch (Exception $e) {
 *     $db->rollback();
 *     throw $e;
 * }
 */
?>
