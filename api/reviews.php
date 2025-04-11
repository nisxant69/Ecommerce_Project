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
$rating = (int)$_POST['rating'];
$comment = trim($_POST['comment']);

// Validate input
$errors = [];

if ($rating < 1 || $rating > 5) {
    $errors[] = 'Rating must be between 1 and 5';
}

if (empty($comment)) {
    $errors[] = 'Review comment is required';
} elseif (strlen($comment) > 1000) {
    $errors[] = 'Review comment must not exceed 1000 characters';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['error' => implode(', ', $errors)]);
    exit();
}

try {
    // Check if product exists
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND deleted = 0");
    $stmt->execute([$product_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit();
    }
    
    // Check if user has already reviewed this product
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$_SESSION['user_id'], $product_id]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'You have already reviewed this product']);
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Add review
    $stmt = $pdo->prepare("
        INSERT INTO reviews (user_id, product_id, rating, comment) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $product_id,
        $rating,
        $comment
    ]);
    
    // Update product average rating
    $stmt = $pdo->prepare("
        UPDATE products 
        SET avg_rating = (
            SELECT AVG(rating) 
            FROM reviews 
            WHERE product_id = ?
        ),
        total_reviews = (
            SELECT COUNT(*) 
            FROM reviews 
            WHERE product_id = ?
        )
        WHERE id = ?
    ");
    $stmt->execute([$product_id, $product_id, $product_id]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Review submitted successfully'
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Review submission error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error submitting review']);
}
