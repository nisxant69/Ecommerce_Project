<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Require login
require_login();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid form submission']);
    exit();
}

$product_id = (int)$_POST['product_id'];
$action = $_POST['action'];

try {
    // Check if product exists
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$product_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit();
    }
    
    if ($action === 'add') {
        // Check if already in wishlist
        $stmt = $pdo->prepare("
            SELECT id FROM wishlist 
            WHERE user_id = ? AND product_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        
        if (!$stmt->fetch()) {
            // Add to wishlist
            $stmt = $pdo->prepare("
                INSERT INTO wishlist (user_id, product_id) 
                VALUES (?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $product_id]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Product added to wishlist'
        ]);
        
    } elseif ($action === 'remove') {
        // Remove from wishlist
        $stmt = $pdo->prepare("
            DELETE FROM wishlist 
            WHERE user_id = ? AND product_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Product removed from wishlist'
        ]);
        
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log("Wishlist API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
