<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// Require admin access
require_admin();

// Set JSON response header
header('Content-Type: application/json');

// Initialize response array
$response = [
    'success' => false,
    'message' => ''
];

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit();
}

// Verify CSRF token
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || 
    !verify_csrf_token($_SERVER['HTTP_X_CSRF_TOKEN'])) {
    $response['message'] = 'Invalid token';
    echo json_encode($response);
    exit();
}

// Get product ID
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$product_id) {
    $response['message'] = 'Invalid product ID';
    echo json_encode($response);
    exit();
}

try {
    // Soft delete the product
    $stmt = $pdo->prepare("
        UPDATE products 
        SET is_deleted = 1 
        WHERE id = ?
    ");
    $stmt->execute([$product_id]);
    
    if ($stmt->rowCount() > 0) {
        $response['success'] = true;
        $response['message'] = 'Product deleted successfully';
    } else {
        $response['message'] = 'Product not found';
    }
    
} catch (PDOException $e) {
    error_log("Error deleting product: " . $e->getMessage());
    $response['message'] = 'Error deleting product';
}

echo json_encode($response);
