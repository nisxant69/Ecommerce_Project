<?php
session_start();

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
        header('Location: /ecomfinal/login.php');
        exit();
    }
}

function require_admin() {
    if (!is_admin()) {
        header('Location: /ecomfinal/index.php');
        exit();
    }
}

// Flash messages
function set_flash_message($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash_message() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Cart functions
function init_cart() {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
}

function add_to_cart($product_id, $quantity) {
    init_cart();
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = $quantity;
    }
}

function update_cart($product_id, $quantity) {
    init_cart();
    if ($quantity > 0) {
        $_SESSION['cart'][$product_id] = $quantity;
    } else {
        unset($_SESSION['cart'][$product_id]);
    }
}

function get_cart_total() {
    init_cart();
    return count($_SESSION['cart']);
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

// Format currency
function format_price($price) {
    return '$' . number_format($price, 2);
}
?>
