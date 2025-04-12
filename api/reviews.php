<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Set JSON response header
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Please log in to submit a review']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid form submission']);
    exit();
}

$product_id = (int)($_POST['product_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

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
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND is_deleted = 0");
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
        INSERT INTO reviews (user_id, product_id, rating, comment, created_at) 
        VALUES (?, ?, ?, ?, NOW())
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
    
    // Get updated review data
    $stmt = $pdo->prepare("
        SELECT r.*, u.name as user_name 
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.id = LAST_INSERT_ID()
    ");
    $stmt->execute();
    $review = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Review submitted successfully',
        'review' => [
            'user_name' => $review['user_name'],
            'rating' => $review['rating'],
            'comment' => $review['comment'],
            'created_at' => date('M d, Y', strtotime($review['created_at']))
        ]
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
