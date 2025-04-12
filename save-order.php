<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get order details from POST data
    $fullName = sanitize_input($_POST['fullName'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');
    $city = sanitize_input($_POST['city'] ?? '');
    $state = sanitize_input($_POST['state'] ?? '');
    $zip = sanitize_input($_POST['zip'] ?? '');
    $country = sanitize_input($_POST['country'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $transactionId = sanitize_input($_POST['transactionId'] ?? '');
    $paymentMethod = sanitize_input($_POST['paymentMethod'] ?? 'khalti');
    
    // Validate required fields
    if (empty($fullName) || empty($email) || empty($phone) || empty($address) || $amount <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Please provide all required fields'
        ]);
        exit;
    }
    
    try {
        // Generate order number (format: ORD-YEAR-RANDOM)
        $order_number = 'ORD-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        
        // Default values for tax and shipping
        $subtotal = $amount * 0.9; // Assuming tax+shipping is 10% of total for this example
        $tax = $amount * 0.07;
        $shipping = $amount * 0.03;
        
        // Get user ID if logged in
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        // Start transaction
        $pdo->beginTransaction();

        // Insert order into orders table
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                order_number, user_id, full_name, email, phone, 
                total_amount, subtotal, tax_amount, shipping_amount,
                status, shipping_address, shipping_city, shipping_state, 
                shipping_zip, shipping_country, payment_method, transaction_id,
                created_at
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?,
                'processing', ?, ?, ?, 
                ?, ?, ?, ?,
                NOW()
            )
        ");
        
        $stmt->execute([
            $order_number, $userId, $fullName, $email, $phone,
            $amount, $subtotal, $tax, $shipping,
            $address, $city, $state,
            $zip, $country, $paymentMethod, $transactionId
        ]);
        
        $orderId = $pdo->lastInsertId();
        
        // Record payment transaction
        $stmt = $pdo->prepare("
            INSERT INTO payment_transactions (
                order_id, transaction_id, payment_method, amount, status, created_at
            ) VALUES (
                ?, ?, ?, ?, 'completed', NOW()
            )
        ");
        $stmt->execute([$orderId, $transactionId, $paymentMethod, $amount]);

        // Insert order items from cart
        if (is_logged_in()) {
            // Get cart items from database
            $cart_items = get_cart_items();
        } else if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
            // Get cart items from session
            $product_ids = array_keys($_SESSION['cart']);
            $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
            
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
            $stmt->execute($product_ids);
            $products = $stmt->fetchAll();
            
            $cart_items = [];
            foreach ($products as $product) {
                if (isset($_SESSION['cart'][$product['id']])) {
                    $quantity = $_SESSION['cart'][$product['id']]['quantity'] ?? 1;
                    $cart_items[] = [
                        'product_id' => $product['id'],
                        'name' => $product['name'],
                        'price' => $product['price'],
                        'quantity' => $quantity
                    ];
                }
            }
        } else {
            $cart_items = [];
        }
        
        // Insert order items
        if (!empty($cart_items)) {
            $stmt = $pdo->prepare("
                INSERT INTO order_items (
                    order_id, product_id, product_name, quantity, price
                ) VALUES (
                    ?, ?, ?, ?, ?
                )
            ");
            
            foreach ($cart_items as $item) {
                $stmt->execute([
                    $orderId, 
                    $item['product_id'],
                    $item['name'],
                    $item['quantity'],
                    $item['price']
                ]);
            }
        }
        
        // Add order status history
        $stmt = $pdo->prepare("
            INSERT INTO order_status_history (
                order_id, status, comment, created_at
            ) VALUES (
                ?, 'processing', 'Payment received via " . $paymentMethod . "', NOW()
            )
        ");
        $stmt->execute([$orderId]);

        // Clear the cart
        if (is_logged_in()) {
            clear_cart();
        } else {
            unset($_SESSION['cart']);
        }

        // Commit transaction
        $pdo->commit();
        
        // Store order ID in session for order confirmation page
        $_SESSION['order_id'] = $orderId;

        echo json_encode([
            'success' => true,
            'message' => 'Order saved successfully',
            'orderId' => $orderId,
            'orderNumber' => $order_number,
            'redirect' => 'order_confirmation.php?id=' . $orderId
        ]);

    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Order processing error: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while processing your order. Please try again later.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
} 