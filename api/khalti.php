<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/khalti_config.php';

header('Content-Type: application/json');

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to continue']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'verify':
        // Verify Khalti payment
        $token = $_POST['token'] ?? '';
        $amount = $_POST['amount'] ?? '';
        $order_id = $_POST['order_id'] ?? '';

        if (!$token || !$amount || !$order_id) {
            echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
            exit;
        }

        // Verify with Khalti Test Environment
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, KHALTI_VERIFY_URL);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'return_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/ecomfinal/order_confirmation.php',
            'website_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/ecomfinal',
            'amount' => $amount,
            'purchase_order_id' => $order_id,
            'purchase_order_name' => 'TechHub Order #' . $order_id,
            'customer_info' => [
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'phone' => $_SESSION['user_phone'] ?? ''
            ]
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Key ' . KHALTI_SECRET_KEY,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status_code == 200) {
            $response = json_decode($response, true);
            
            try {
                // Begin transaction
                $pdo->beginTransaction();

                // Update order status
                $stmt = $pdo->prepare("UPDATE orders SET status = 'paid', payment_method = 'khalti', payment_ref = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$token, $order_id, $_SESSION['user_id']]);

                // Clear cart
                $_SESSION['cart'] = [];

                // Commit transaction
                $pdo->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Payment successful',
                    'redirect' => '/ecomfinal/order_confirmation.php?id=' . $order_id
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Payment verification error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error processing payment']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Payment verification failed']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
