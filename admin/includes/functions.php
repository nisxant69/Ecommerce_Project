<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require database connection
require_once __DIR__ . '/../../includes/db_connect.php';

// Check if user is logged in and is an admin
function require_admin_login() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        error_log("Admin access denied - User ID: " . ($_SESSION['user_id'] ?? 'not set') . ", Role: " . ($_SESSION['user_role'] ?? 'not set'));
        set_flash_message('danger', 'You must be an administrator to access this area.');
        header('Location: ../login.php');
        exit();
    }
    
    // Double check admin status in database
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? AND role = 'admin'");
        $stmt->execute([$_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            error_log("Admin validation failed for user ID: " . $_SESSION['user_id']);
            session_destroy();
            header('Location: ../login.php');
            exit();
        }
    } catch (PDOException $e) {
        error_log("Database error during admin check: " . $e->getMessage());
        header('Location: ../login.php');
        exit();
    }
}

// Set flash message
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

// Get flash message
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

// Display flash message
function display_flash_message() {
    $message = get_flash_message();
    if ($message) {
        echo '<div class="alert alert-' . htmlspecialchars($message['type']) . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($message['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
    }
}

// Sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Generate CSRF token
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Format price with currency from settings
 * @param float $price The price to format
 * @return string Formatted price with currency symbol
 */
function format_price($price) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency_symbol'");
        $stmt->execute();
        $currency_symbol = $stmt->fetchColumn();
        
        // Default to 'Rs.' if not found in settings
        if (!$currency_symbol) {
            $currency_symbol = 'Rs.';
        }
        
        return $currency_symbol . ' ' . number_format($price, 2);
    } catch (PDOException $e) {
        // Fallback to default if there's an error
        return 'Rs. ' . number_format($price, 2);
    }
}

// Format date
function format_date($date) {
    return date('M d, Y H:i', strtotime($date));
}

// Get order status name
function get_order_status_name($status_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT name FROM order_status WHERE id = ?");
    $stmt->execute([$status_id]);
    return $stmt->fetchColumn() ?: 'Unknown';
}

// Get user name
function get_user_name($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: 'Unknown';
}

// Get product name
function get_product_name($product_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    return $stmt->fetchColumn() ?: 'Unknown';
}

// Log admin action
function log_admin_action($action, $details = '') {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO admin_logs (user_id, action, details, created_at) 
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $action, $details]);
}

/**
 * Get a site setting value
 * @param string $key The setting key
 * @param mixed $default The default value if setting not found
 * @return mixed The setting value or default
 */
function get_setting($key, $default = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        
        return $result !== false ? $result : $default;
    } catch (PDOException $e) {
        error_log("Error getting setting: " . $e->getMessage());
        return $default;
    }
}
?> 