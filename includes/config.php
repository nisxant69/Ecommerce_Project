<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auto-login using remember token if not already logged in
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.* 
            FROM users u 
            JOIN remember_tokens rt ON u.id = rt.user_id 
            WHERE rt.token = ? AND rt.expires_at > NOW() AND u.is_banned = 0
            LIMIT 1
        ");
        $stmt->execute([$_COOKIE['remember_token']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            session_regenerate_id(true);
            
            // Refresh remember token
            $new_token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            // Update token in database
            $stmt = $pdo->prepare("
                UPDATE remember_tokens 
                SET token = ?, expires_at = ? 
                WHERE user_id = ? AND token = ?
            ");
            $stmt->execute([$new_token, $expiry, $user['id'], $_COOKIE['remember_token']]);
            
            // Set new cookie
            setcookie('remember_token', $new_token, strtotime('+30 days'), '/', '', true, true);
        } else {
            // Invalid or expired token - remove cookie
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    } catch (PDOException $e) {
        error_log("Auto-login error: " . $e->getMessage());
    }
}

// Error reporting based on environment
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Load environment variables
require_once __DIR__ . '/env.php';

// Load database connection
require_once __DIR__ . '/db_connect.php';

// Load helper functions
require_once __DIR__ . '/functions.php';

// Load Khalti config
require_once __DIR__ . '/khalti_config.php';

// Set default timezone
date_default_timezone_set('UTC');

// Initialize CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Special handling for API endpoints
if (strpos($_SERVER['SCRIPT_NAME'], '/api/') !== false) {
    // Ensure clean output for JSON responses
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
}

// Prevent redirect loops
if (isset($_SESSION['last_url'])) {
    $current_url = $_SERVER['REQUEST_URI'];
    if ($_SESSION['last_url'] === $current_url && strpos($current_url, 'login.php') !== false) {
        unset($_SESSION['last_url']);
        header('Location: /ecomfinal/index.php');
        exit();
    }
    $_SESSION['last_url'] = $current_url;
} else {
    $_SESSION['last_url'] = $_SERVER['REQUEST_URI'];
} 