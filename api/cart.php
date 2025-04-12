<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Initialize session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize cart session if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Check if it's an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

if (!isset($_POST['action']) || !isset($_POST['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$action = $_POST['action'];
$product_id = (int)$_POST['product_id'];
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$absolute = isset($_POST['absolute']) && $_POST['absolute'] === '1';

if ($quantity < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid quantity']);
    exit;
}

try {
    // Begin transaction if user is logged in
    if (is_logged_in()) {
        $pdo->beginTransaction();
    }
    
    // Get product details
    $stmt = $pdo->prepare("SELECT id, name, price, stock FROM products WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        if (is_logged_in()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    
    $success = false;
    $message = '';
    $cart_count = 0;
    $cart_total = 0;
    
    if (is_logged_in()) {
        // Handle database cart
        switch ($action) {
            case 'add':
                // Check if product already in cart
                $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$_SESSION['user_id'], $product_id]);
                $cart_item = $stmt->fetch();
                
                $new_quantity = $cart_item ? $cart_item['quantity'] + $quantity : $quantity;
                
                if ($new_quantity > $product['stock']) {
                    $pdo->rollBack();
                    echo json_encode([
                        'success' => false,
                        'message' => 'Cannot add more items than available in stock'
                    ]);
                    exit;
                }
                
                if ($cart_item) {
                    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                    $stmt->execute([$new_quantity, $_SESSION['user_id'], $product_id]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $product_id, $quantity]);
                }
                
                $success = true;
                $message = 'Item added to cart successfully';
                break;
                
            case 'update':
                if ($absolute) {
                    if ($quantity === 0) {
                        // Remove item from cart
                        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                        $stmt->execute([$_SESSION['user_id'], $product_id]);
                        $success = true;
                        $message = 'Item removed from cart';
                    } else {
                        // Update to specific quantity
                        if ($quantity > $product['stock']) {
                            $pdo->rollBack();
                            echo json_encode(['success' => false, 'message' => 'Insufficient stock available']);
                            exit;
                        }
                        
                        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                        $stmt->execute([$quantity, $_SESSION['user_id'], $product_id]);
                        $success = true;
                        $message = 'Cart updated successfully';
                    }
                } else {
                    // Get current quantity
                    $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
                    $stmt->execute([$_SESSION['user_id'], $product_id]);
                    $cart_item = $stmt->fetch();
                    
                    if (!$cart_item) {
                        if ($quantity > 0) {
                            // Add new item
                            $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                            $stmt->execute([$_SESSION['user_id'], $product_id, $quantity]);
                            $success = true;
                            $message = 'Item added to cart';
                        }
                    } else {
                        $new_quantity = $cart_item['quantity'] + $quantity;
                        
                        if ($new_quantity <= 0) {
                            // Remove item
                            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                            $stmt->execute([$_SESSION['user_id'], $product_id]);
                            $success = true;
                            $message = 'Item removed from cart';
                        } else if ($new_quantity <= $product['stock']) {
                            // Update quantity
                            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                            $stmt->execute([$new_quantity, $_SESSION['user_id'], $product_id]);
                            $success = true;
                            $message = 'Cart updated successfully';
                        } else {
                            $pdo->rollBack();
                            echo json_encode(['success' => false, 'message' => 'Cannot add more items than available in stock']);
                            exit;
                        }
                    }
                }
                break;
                
            case 'remove':
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$_SESSION['user_id'], $product_id]);
                $success = true;
                $message = 'Item removed from cart';
                break;
                
            default:
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit;
        }
        
        // Get updated cart totals
        $stmt = $pdo->prepare("
            SELECT SUM(c.quantity) as cart_count,
                   SUM(c.quantity * p.price) as cart_total
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $totals = $stmt->fetch();
        $cart_count = (int)($totals['cart_count'] ?? 0);
        $cart_total = number_format(($totals['cart_total'] ?? 0), 2);
        
        $pdo->commit();
    } else {
        // Handle session cart
        switch ($action) {
            case 'add':
                if (isset($_SESSION['cart'][$product_id])) {
                    $new_quantity = $_SESSION['cart'][$product_id] + $quantity;
                } else {
                    $new_quantity = $quantity;
                }
                
                if ($new_quantity > $product['stock']) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Cannot add more items than available in stock'
                    ]);
                    exit;
                }
                
                $_SESSION['cart'][$product_id] = $new_quantity;
                $success = true;
                $message = 'Item added to cart successfully';
                break;
                
            case 'update':
                if ($absolute) {
                    if ($quantity === 0) {
                        unset($_SESSION['cart'][$product_id]);
                        $success = true;
                        $message = 'Item removed from cart';
                    } else {
                        if ($quantity > $product['stock']) {
                            echo json_encode(['success' => false, 'message' => 'Insufficient stock available']);
                            exit;
                        }
                        $_SESSION['cart'][$product_id] = $quantity;
                        $success = true;
                        $message = 'Cart updated successfully';
                    }
                } else {
                    $current_quantity = $_SESSION['cart'][$product_id] ?? 0;
                    $new_quantity = $current_quantity + $quantity;
                    
                    if ($new_quantity <= 0) {
                        unset($_SESSION['cart'][$product_id]);
                        $success = true;
                        $message = 'Item removed from cart';
                    } else if ($new_quantity <= $product['stock']) {
                        $_SESSION['cart'][$product_id] = $new_quantity;
                        $success = true;
                        $message = 'Cart updated successfully';
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Cannot add more items than available in stock']);
                        exit;
                    }
                }
                break;
                
            case 'remove':
                unset($_SESSION['cart'][$product_id]);
                $success = true;
                $message = 'Item removed from cart';
                break;
        }
        
        // Calculate session cart totals
        $cart_count = 0;
        $cart_total = 0;
        foreach ($_SESSION['cart'] as $pid => $qty) {
            $cart_count += $qty;
            $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
            $stmt->execute([$pid]);
            $price = $stmt->fetchColumn();
            $cart_total += $price * $qty;
        }
        $cart_total = number_format($cart_total, 2);
    }
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'cart_count' => $cart_count,
        'cart_total' => $cart_total
    ]);
    
} catch (Exception $e) {
    if (is_logged_in()) {
        $pdo->rollBack();
    }
    error_log("Cart error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing your request'
    ]);
}
