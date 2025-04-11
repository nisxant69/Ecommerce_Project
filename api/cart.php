<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'cart_total' => 0,
    'cart_price_total' => 0
];

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit();
}

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'])) {
    $response['message'] = 'Invalid token';
    echo json_encode($response);
    exit();
}

// Get action and validate parameters
$action = $_POST['action'] ?? '';
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;

if (!$product_id) {
    $response['message'] = 'Invalid product';
    echo json_encode($response);
    exit();
}

try {
    // Initialize cart if needed
    init_cart();
    
    // Get product details
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        $response['message'] = 'Product not found';
        echo json_encode($response);
        exit();
    }
    
    switch ($action) {
        case 'add':
        case 'update':
            // Validate quantity
            if ($quantity <= 0) {
                if ($action === 'update') {
                    // Remove item if quantity is 0 in update action
                    unset($_SESSION['cart'][$product_id]);
                    $response['success'] = true;
                    $response['message'] = 'Item removed from cart';
                } else {
                    $response['message'] = 'Invalid quantity';
                }
                break;
            }
            
            // Check stock
            if ($quantity > $product['stock']) {
                $response['message'] = 'Not enough stock available';
                break;
            }
            
            // Add/Update cart
            $_SESSION['cart'][$product_id] = $quantity;
            $response['success'] = true;
            $response['message'] = 'Cart updated successfully';
            break;
            
        case 'remove':
            // Remove item from cart
            unset($_SESSION['cart'][$product_id]);
            $response['success'] = true;
            $response['message'] = 'Item removed from cart';
            break;
            
        default:
            $response['message'] = 'Invalid action';
            break;
    }
    
    // Calculate cart totals if operation was successful
    if ($response['success']) {
        $cart_total = 0;
        $cart_price_total = 0;
        
        if (!empty($_SESSION['cart'])) {
            // Get all cart products
            $placeholders = str_repeat('?,', count($_SESSION['cart']) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT id, price FROM products 
                WHERE id IN ($placeholders) 
                AND is_deleted = 0
            ");
            $stmt->execute(array_keys($_SESSION['cart']));
            $cart_products = $stmt->fetchAll();
            
            foreach ($cart_products as $cart_product) {
                $cart_total += $_SESSION['cart'][$cart_product['id']];
                $cart_price_total += $cart_product['price'] * $_SESSION['cart'][$cart_product['id']];
            }
        }
        
        $response['cart_total'] = $cart_total;
        $response['cart_price_total'] = $cart_price_total;
    }
    
} catch (PDOException $e) {
    error_log("Cart error: " . $e->getMessage());
    $response['message'] = 'Error updating cart';
}

echo json_encode($response);
