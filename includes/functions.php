<?php
// Security functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

// Authentication functions
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        set_flash_message('warning', 'Please log in to continue.');
        header('Location: /ecomfinal/login.php');
        exit();
    }
}

function require_admin() {
    if (!is_admin()) {
        set_flash_message('danger', 'Access denied.');
        header('Location: /ecomfinal/index.php');
        exit();
    }
}

// Flash messages
function set_flash_message($type, $message) {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash_messages() {
    if (isset($_SESSION['flash'])) {
        $messages = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $messages;
    }
    return [];
}

// Cart functions
function init_cart() {
    // No need to initialize anything for database cart
    return;
}

function add_to_cart($product_id, $quantity) {
    global $pdo;
    
    if (!is_logged_in()) {
        return false;
    }
    
    try {
        // Get product details and check stock
        $stmt = $pdo->prepare("SELECT price, stock FROM products WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            error_log("Product not found or deleted");
            return false;
        }
        
        if ($quantity > $product['stock']) {
            error_log("Requested quantity exceeds stock");
            return false;
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Check if product already in cart
        $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        $cart_item = $stmt->fetch();
        
        if ($cart_item) {
            // Update existing cart item
            $new_quantity = $cart_item['quantity'] + $quantity;
            if ($new_quantity > $product['stock']) {
                $pdo->rollBack();
                error_log("New quantity would exceed stock");
                return false;
            }
            
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$new_quantity, $_SESSION['user_id'], $product_id]);
        } else {
            // Insert new cart item
            $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $product_id, $quantity]);
        }
        
        $pdo->commit();
        return true;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error adding to cart: " . $e->getMessage());
        return false;
    }
}

function update_cart($product_id, $quantity) {
    global $pdo;
    
    if (!is_logged_in()) {
        return false;
    }
    
    try {
        if ($quantity > 0) {
            // Check product stock
            $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if (!$product || $quantity > $product['stock']) {
                return false;
            }
            
            // Update cart quantity
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$quantity, $_SESSION['user_id'], $product_id]);
        } else {
            // Remove item from cart
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$_SESSION['user_id'], $product_id]);
        }
        return true;
    } catch (PDOException $e) {
        error_log("Error updating cart: " . $e->getMessage());
        return false;
    }
}

function get_cart_total() {
    global $pdo;
    
    if (!is_logged_in()) {
        return 0;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return (int)$stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        error_log("Error getting cart total: " . $e->getMessage());
        return 0;
    }
}

function get_cart_items() {
    global $pdo;
    
    if (!is_logged_in()) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, p.name, p.price, p.image_url, p.stock 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ? AND p.is_deleted = 0
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting cart items: " . $e->getMessage());
        return [];
    }
}

function clear_cart() {
    global $pdo;
    
    if (!is_logged_in()) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return true;
    } catch (PDOException $e) {
        error_log("Error clearing cart: " . $e->getMessage());
        return false;
    }
}

// Image handling
function upload_image($file, $target_dir = '../assets/images/') {
    $target_file = $target_dir . basename($file["name"]);
    $image_file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is actual image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        throw new Exception("File is not an image.");
    }
    
    // Check file size (2MB max)
    if ($file["size"] > 2000000) {
        throw new Exception("File is too large. Maximum size is 2MB.");
    }
    
    // Allow certain file formats
    if ($image_file_type != "jpg" && $image_file_type != "png" && $image_file_type != "jpeg") {
        throw new Exception("Only JPG, JPEG & PNG files are allowed.");
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '.' . $image_file_type;
    $target_file = $target_dir . $new_filename;
    
    // Upload file
    if (!move_uploaded_file($file["tmp_name"], $target_file)) {
        throw new Exception("Error uploading file.");
    }
    
    return $new_filename;
}

// Settings functions
function get_setting($key, $default = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value, setting_type FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $setting = $stmt->fetch();
        
        if (!$setting) {
            return $default;
        }
        
        // Convert value based on type
        switch ($setting['setting_type']) {
            case 'boolean':
                return (bool) $setting['setting_value'];
            case 'number':
                return is_numeric($setting['setting_value']) ? (float) $setting['setting_value'] : $default;
            case 'json':
                $value = json_decode($setting['setting_value'], true);
                return ($value === null) ? $default : $value;
            default:
                return $setting['setting_value'];
        }
    } catch (PDOException $e) {
        error_log("Error getting setting: " . $e->getMessage());
        return $default;
    }
}

function update_setting($key, $value, $type = 'text') {
    global $pdo;
    
    // Format value based on type
    if ($type === 'json' && !is_string($value)) {
        $value = json_encode($value);
    } elseif ($type === 'boolean') {
        $value = $value ? '1' : '0';
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, setting_type) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = ?, setting_type = ?
        ");
        return $stmt->execute([$key, $value, $type, $value, $type]);
    } catch (PDOException $e) {
        error_log("Error updating setting: " . $e->getMessage());
        return false;
    }
}

// Currency functions
function get_currencies() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM currencies WHERE is_active = 1 ORDER BY is_default DESC, name ASC");
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting currencies: " . $e->getMessage());
        return [];
    }
}

function get_default_currency() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM currencies WHERE is_default = 1 LIMIT 1");
        $stmt->execute();
        $currency = $stmt->fetch();
        
        if (!$currency) {
            // Fallback to USD if no default is set
            $stmt = $pdo->prepare("SELECT * FROM currencies WHERE code = 'USD' LIMIT 1");
            $stmt->execute();
            $currency = $stmt->fetch();
            
            if (!$currency) {
                // Create a default USD currency if none exists
                $stmt = $pdo->prepare("
                    INSERT INTO currencies (code, name, symbol, exchange_rate, is_default, is_active)
                    VALUES ('USD', 'US Dollar', '$', 1, 1, 1)
                ");
                $stmt->execute();
                
                $stmt = $pdo->prepare("SELECT * FROM currencies WHERE code = 'USD' LIMIT 1");
                $stmt->execute();
                $currency = $stmt->fetch();
            }
        }
        
        return $currency;
    } catch (PDOException $e) {
        error_log("Error getting default currency: " . $e->getMessage());
        
        // Return a basic USD currency as fallback
        return [
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate' => 1,
            'is_default' => 1
        ];
    }
}

function get_active_currency() {
    $currency_code = $_SESSION['currency'] ?? null;
    
    if (!$currency_code) {
        return get_default_currency();
    }
    
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM currencies WHERE code = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$currency_code]);
        $currency = $stmt->fetch();
        
        if (!$currency) {
            return get_default_currency();
        }
        
        return $currency;
    } catch (PDOException $e) {
        error_log("Error getting active currency: " . $e->getMessage());
        return get_default_currency();
    }
}

function convert_price($price, $from_currency = null, $to_currency = null) {
    if ($from_currency === null) {
        $from_currency = get_default_currency();
    } elseif (is_string($from_currency)) {
        $from_currency = get_currency_by_code($from_currency);
    }
    
    if ($to_currency === null) {
        $to_currency = get_active_currency();
    } elseif (is_string($to_currency)) {
        $to_currency = get_currency_by_code($to_currency);
    }
    
    if (!$from_currency || !$to_currency) {
        return $price;
    }
    
    // Convert to USD first if neither currency is USD
    if ($from_currency['code'] !== 'USD' && $to_currency['code'] !== 'USD') {
        $price_in_usd = $price / $from_currency['exchange_rate'];
        return $price_in_usd * $to_currency['exchange_rate'];
    }
    
    // Direct conversion
    return $price * ($to_currency['exchange_rate'] / $from_currency['exchange_rate']);
}

function get_currency_by_code($code) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM currencies WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error getting currency by code: " . $e->getMessage());
        return false;
    }
}

function format_price($price, $currency = null) {
    if ($currency === null) {
        $currency = get_active_currency();
    } elseif (is_string($currency)) {
        $currency = get_currency_by_code($currency);
    }
    
    if (!$currency) {
        // Fallback to simple formatting
        return '$' . number_format($price, 2);
    }
    
    $price = convert_price($price, null, $currency);
    
    return $currency['symbol'] . number_format($price, 2);
}

function display_flash_message() {
    if (!empty($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_message']['type'];
        $message = $_SESSION['flash_message']['message'];
        echo "<div class='alert alert-$type'>$message</div>";
        unset($_SESSION['flash_message']);
    }
}
?>
