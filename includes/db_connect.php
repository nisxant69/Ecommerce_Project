<?php
require_once 'env.php';

// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'ecommerce_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// Global PDO object
$pdo = null;

/**
 * Get database connection
 * @return PDO
 * @throws PDOException
 */
function get_db_connection() {
    global $pdo;
    
    // If connection exists, check if it's still alive
    if ($pdo instanceof PDO) {
        try {
            $pdo->query('SELECT 1');
            return $pdo;
        } catch (PDOException $e) {
            // Connection lost, will recreate it
            $pdo = null;
        }
    }
    
    try {
        // Create new connection
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Test connection
        $pdo->query('SELECT 1');
        
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        throw new PDOException("Could not connect to the database. Please try again later.");
    }
}

// Initialize connection
try {
    $pdo = get_db_connection();
} catch (PDOException $e) {
    // Log the detailed error
    error_log("Fatal database error: " . $e->getMessage());
    
    // If this is an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database connection error']);
        exit;
    }
    
    // For normal requests
    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 300');
    }
    die("We're experiencing technical difficulties. Please try again later.");
}
?>
