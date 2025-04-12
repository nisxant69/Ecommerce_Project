<?php
// Start session at the very beginning
session_start();

// Debug session
error_log("=== Wishlist API Debug ===");
error_log("Session started");
error_log("Session status: " . session_status());
error_log("Session ID: " . session_id());
error_log("Session data: " . print_r($_SESSION, true));
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));

// Set JSON content type
header('Content-Type: application/json');

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Debug session
error_log("Session status: " . session_status());
error_log("Session ID: " . session_id());
error_log("User ID in session: " . ($_SESSION['user_id'] ?? 'not set'));

// Require login
if (!is_logged_in()) {
    error_log("User not logged in - Session data: " . print_r($_SESSION, true));
    echo json_encode(['success' => false, 'message' => 'Please log in to manage your wishlist']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid form submission']);
    exit();
}

$product_id = (int)($_POST['product_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit();
}

try {
    // Check if product exists and is not deleted
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND is_deleted = 0");
    $stmt->execute([$product_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit();
    }
    
    if ($action === 'add') {
        // First try to add, if it fails due to duplicate, then it's already in wishlist
        try {
            $stmt = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], $product_id]);
            echo json_encode(['success' => true, 'message' => 'Product added to wishlist']);
        } catch (PDOException $e) {
            // Check if it's a duplicate entry error
            if ($e->getCode() == 23000) { // Duplicate entry error code
                echo json_encode(['success' => true, 'message' => 'Product is already in wishlist']);
            } else {
                throw $e; // Re-throw if it's a different error
            }
        }
    } elseif ($action === 'remove') {
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Product removed from wishlist']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Product is not in wishlist']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log("Wishlist API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}