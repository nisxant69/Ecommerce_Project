<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['token']) || !isset($data['amount'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit();
}

// Verify Khalti payment
$url = "https://khalti.com/api/v2/payment/verify/";
$payload = [
    'token' => $data['token'],
    'amount' => $data['amount']
];

// Replace with your actual Khalti secret key
$secret_key = "test_secret_key_f59e8b7d18b4499ca40f68195a846e9b";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Key ' . $secret_key,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$response_data = json_decode($response, true);

if ($status_code === 200 && isset($response_data['idx'])) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Create shipping address string
        $shipping_address = implode(', ', [
            $data['shipping_details']['address'],
            $data['shipping_details']['city'],
            $data['shipping_details']['state'],
            $data['shipping_details']['zip']
        ]);
        
        // Create order
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                user_id, 
                total_amount, 
                shipping_address,
                shipping_name,
                shipping_email,
                shipping_phone,
                payment_method,
                payment_id,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $data['amount'] / 100, // Convert from paisa to rupees
            $shipping_address,
            $data['shipping_details']['name'],
            $data['shipping_details']['email'],
            $data['shipping_details']['phone'],
            'khalti',
            $response_data['idx'],
            'processing'
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        // Create order items
        foreach ($_SESSION['cart'] as $product_id => $quantity) {
            $stmt = $pdo->prepare("SELECT price, stock FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if (!$product || $product['stock'] < $quantity) {
                throw new Exception('Product out of stock');
            }
            
            // Create order item
            $stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$order_id, $product_id, $quantity, $product['price']]);
            
            // Update stock
            $stmt = $pdo->prepare("
                UPDATE products 
                SET stock = stock - ? 
                WHERE id = ?
            ");
            $stmt->execute([$quantity, $product_id]);
        }
        
        // Clear cart
        $_SESSION['cart'] = [];
        unset($_SESSION['shipping_details']);
        
        // Commit transaction
        $pdo->commit();
        
        // Log successful order
        error_log("Order created successfully: ID=$order_id, Payment ID=" . $response_data['idx']);
        
        echo json_encode([
            'success' => true,
            'order_id' => $order_id,
            'message' => 'Payment successful'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Order creation failed: " . $e->getMessage());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error creating order: ' . $e->getMessage()
        ]);
    }
} else {
    // Payment verification failed
    error_log("Khalti payment verification failed: " . $response);
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Payment verification failed'
    ]);
}
?> 