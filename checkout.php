<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';
require_once 'includes/khalti_config.php';

// Require login for checkout
require_login();

// Initialize cart
init_cart();

// Redirect if cart is empty
if (empty($_SESSION['cart'])) {
    set_flash_message('warning', 'Your cart is empty.');
    header('Location: cart.php');
    exit();
}

// Get user details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Error fetching user details: " . $e->getMessage());
    set_flash_message('danger', 'Error loading user details. Please try again later.');
    header('Location: cart.php');
    exit();
}

// Get cart items
$cart_items = [];
$total = 0;
$all_items_available = true;

try {
    $placeholders = str_repeat('?,', count($_SESSION['cart']) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE id IN ($placeholders) 
        AND is_deleted = 0
    ");
    $stmt->execute(array_keys($_SESSION['cart']));
    $products = $stmt->fetchAll();
    
    foreach ($products as $product) {
        $quantity = $_SESSION['cart'][$product['id']];
        
        // Check stock availability
        if ($quantity > $product['stock']) {
            $all_items_available = false;
        }
        
        $cart_items[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $quantity,
            'subtotal' => $product['price'] * $quantity,
            'stock' => $product['stock']
        ];
        $total += $product['price'] * $quantity;
    }
} catch (PDOException $e) {
    error_log("Error fetching cart items: " . $e->getMessage());
    set_flash_message('danger', 'Error loading cart items. Please try again later.');
    header('Location: cart.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Invalid form submission.');
        header('Location: checkout.php');
        exit();
    }
    
    if (!$all_items_available) {
        set_flash_message('danger', 'Some items in your cart are out of stock.');
        header('Location: cart.php');
        exit();
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Create order
        $stmt = $pdo->prepare("
            INSERT INTO orders (user_id, total_amount, shipping_address, payment_method) 
            VALUES (?, ?, ?, ?)
        ");
        $shipping_address = $_POST['address'] . ', ' . $_POST['city'] . ', ' . $_POST['postal_code'];
        $stmt->execute([
            $_SESSION['user_id'],
            $total >= 50 ? $total : $total + 10,
            $shipping_address,
            $_POST['payment_method']
        ]);
        $order_id = $pdo->lastInsertId();
        
        // Create order items and update stock
        foreach ($cart_items as $item) {
            // Add order item
            $stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $order_id,
                $item['id'],
                $item['quantity'],
                $item['price']
            ]);
            
            // Update stock
            $stmt = $pdo->prepare("
                UPDATE products 
                SET stock = stock - ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $item['quantity'],
                $item['id']
            ]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Clear cart
        $_SESSION['cart'] = [];
        
        // Redirect to order confirmation
        header("Location: order_confirmation.php?order_id=" . $order_id);
        exit();
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        error_log("Error processing order: " . $e->getMessage());
        set_flash_message('danger', 'Error processing your order. Please try again later.');
    }
}
?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <h1 class="card-title h3 mb-4">Checkout</h1>
                
                <form method="POST" id="checkoutForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <!-- Shipping Information -->
                    <h5 class="mb-3">Shipping Information</h5>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" name="address" 
                                   value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Postal Code</label>
                            <input type="text" class="form-control" name="postal_code" required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <!-- Payment Method -->
                    <h5 class="mb-3">Payment Method</h5>
                    <div class="form-check mb-2">
                        <input type="radio" class="form-check-input" name="payment_method" 
                               value="cash_on_delivery" id="cod" checked required>
                        <label class="form-check-label" for="cod">Cash on Delivery</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="radio" class="form-check-input" name="payment_method" 
                               value="bank_transfer" id="bank" required>
                        <label class="form-check-label" for="bank">Bank Transfer</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="radio" class="form-check-input" name="payment_method" 
                               value="khalti" id="khalti" required>
                        <label class="form-check-label" for="khalti">Pay with Khalti</label>
                    </div>
                    
                    <?php if (!$all_items_available): ?>
                    <div class="alert alert-danger mt-4">
                        Some items in your cart are out of stock. Please update your cart before proceeding.
                    </div>
                    <?php endif; ?>
                    
                    <button class="btn btn-primary w-100 mt-4" type="submit" 
                            <?php echo !$all_items_available ? 'disabled' : ''; ?>>
                        Place Order
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Order Summary</h5>
                
                <!-- Order Items -->
                <?php foreach ($cart_items as $item): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span>
                        <?php echo htmlspecialchars($item['name']); ?> 
                        <small class="text-muted">x<?php echo $item['quantity']; ?></small>
                    </span>
                    <span><?php echo format_price($item['subtotal']); ?></span>
                </div>
                <?php if ($item['quantity'] > $item['stock']): ?>
                <div class="text-danger small mb-2">
                    Only <?php echo $item['stock']; ?> available
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
                
                <hr>
                
                <!-- Order Totals -->
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal</span>
                    <span><?php echo format_price($total); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Shipping</span>
                    <span><?php echo $total >= 50 ? 'Free' : format_price(10); ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-3">
                    <strong>Total</strong>
                    <strong><?php echo format_price($total >= 50 ? $total : $total + 10); ?></strong>
                </div>
                
                <a href="cart.php" class="btn btn-outline-primary w-100">
                    <i class="fas fa-arrow-left"></i> Back to Cart
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Khalti test configuration
const config = {
    publicKey: khaltiConfig.publicKey,
    env: khaltiConfig.env,
    productIdentity: 'order',
    productName: 'TechHub Order',
    productUrl: window.location.origin + '/ecomfinal',
    eventHandler: {
        onSuccess(payload) {
            // Payment initiated successfully
            window.location.href = payload.payment_url;
        },
        onError(error) {
            showAlert('danger', 'Payment failed: ' + error.message);
        },
        onClose() {
            console.log('Khalti widget is closing');
        }
    },
    paymentPreference: ["KHALTI", "EBANKING", "MOBILE_BANKING", "CONNECT_IPS", "SCT"]
};

// Handle form submission
document.getElementById('checkoutForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!this.checkValidity()) {
        e.stopPropagation();
        this.classList.add('was-validated');
        return;
    }

    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    
    if (paymentMethod === 'khalti') {
        // Submit form to create order first
        const formData = new FormData(this);
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.success) {
                // Store order ID for Khalti callback
                window.orderId = data.order_id;
                
                // Configure Khalti payment
                config.amount = data.total * 100; // Convert to paisa
                config.productIdentity = 'order_' + data.order_id;
                
                // Load Khalti widget
                const checkout = new KhaltiCheckout(config);
                checkout.show({popups: false});
            } else {
                showAlert('danger', data.message || 'Error creating order');
            }
        } catch (error) {
            showAlert('danger', 'Error processing order');
        }
    } else {
        // Normal form submission for other payment methods
        this.submit();
    }
});

// Alert function
function showAlert(type, message) {
    const alert = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('.alert-container').html(alert);
}
</script>

<?php require_once 'includes/footer.php'; ?>
