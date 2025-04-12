<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Save the redirect URL
    $_SESSION['redirect_after_login'] = 'checkout.php';
    set_flash_message('info', 'Please login to continue checkout.');
    header('Location: login.php');
    exit();
}

// Initialize variables
$cart_items = [];
$subtotal = 0;
$shipping_cost = 10.00; // Default shipping cost - This could come from settings table
$free_shipping_threshold = 100; // Free shipping over this amount - This could come from settings table

// Get user details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Error fetching user: " . $e->getMessage());
    $user = null;
}

// Get cart items from database
try {
    $cart_items = get_cart_items();
    foreach ($cart_items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching cart items: " . $e->getMessage());
        set_flash_message('danger', 'Error loading cart items. Please try again later.');
    }

// Calculate totals
$shipping_amount = ($subtotal >= $free_shipping_threshold) ? 0 : $shipping_cost;
$total_amount = $subtotal + $shipping_amount;

// If cart is empty, redirect to cart page
if (empty($cart_items)) {
    set_flash_message('warning', 'Your cart is empty. Please add items to your cart before checkout.');
    header('Location: cart.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $shipping_address = sanitize_input($_POST['shipping_address'] ?? '');
    $shipping_city = sanitize_input($_POST['shipping_city'] ?? '');
    $shipping_state = sanitize_input($_POST['shipping_state'] ?? '');
    $shipping_zip = sanitize_input($_POST['shipping_zip'] ?? '');
    $shipping_country = sanitize_input($_POST['shipping_country'] ?? '');
    $payment_method = sanitize_input($_POST['payment_method'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');
    
    if (empty($full_name) || empty($email) || empty($phone) || empty($shipping_address) || empty($payment_method)) {
        set_flash_message('danger', 'Please fill in all required fields.');
    
        try {
            // Generate order number (format: ORD-YEAR-RANDOM)
            $order_number = 'ORD-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
            
            // Begin transaction
            $pdo->beginTransaction();
            
            // Insert order
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    order_number, user_id, full_name, email, phone, 
                    total_amount, subtotal, shipping_amount,
                    status, shipping_address, shipping_city, shipping_state, 
                    shipping_zip, shipping_country, payment_method, notes
                ) VALUES (
                    ?, ?, ?, ?, ?, 
                    ?, ?, ?,
                    'pending', ?, ?, ?, 
                    ?, ?, ?, ?
                )
            ");
            
            $stmt->execute([
                $order_number, $_SESSION['user_id'], $full_name, $email, $phone,
                $total_amount, $subtotal, $shipping_amount,
                $shipping_address, $shipping_city, $shipping_state,
                $shipping_zip, $shipping_country, $payment_method, $notes
            ]);
            
            $order_id = $pdo->lastInsertId();
            
            // Insert order items
            $stmt = $pdo->prepare("
                INSERT INTO order_items (
                    order_id, product_id, product_name, quantity, price
                ) VALUES (
                    ?, ?, ?, ?, ?
                )
            ");
            
            foreach ($cart_items as $item) {
                $stmt->execute([
                    $order_id, 
                    $item['product_id'],
                    $item['name'],
                    $item['quantity'],
                    $item['price']
                ]);
            }
            
            // Insert order status history
            $stmt = $pdo->prepare("
                INSERT INTO order_status_history (
                    order_id, status, notes
                ) VALUES (
                    ?, 'pending', 'Order created'
                )
            ");
            $stmt->execute([$order_id]);
            
            // Commit transaction
            $pdo->commit();
            
            // Clear cart
            clear_cart();
            
            // Redirect to order confirmation
            header('Location: order_confirmation.php?order_id=' . $order_id);
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Error creating order: " . $e->getMessage());
            set_flash_message('danger', 'Error creating order. Please try again later.');
        }
    }
}

// Include header
require_once 'includes/header.php';
?>

<div class="container py-5">
    <h1 class="h3 mb-4">Checkout</h1>
    
    <?php if (isset($_SESSION['flash'])): ?>
        <?php foreach (get_flash_messages() as $flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4">Shipping Information</h5>
                    
                    <form method="POST" action="checkout.php" id="checkout-form">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required 
                                       value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="shipping_address" class="form-label">Shipping Address *</label>
                            <textarea class="form-control" id="shipping_address" name="shipping_address" rows="3" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="shipping_city" class="form-label">City</label>
                                <input type="text" class="form-control" id="shipping_city" name="shipping_city">
                            </div>
                            <div class="col-md-6">
                                <label for="shipping_state" class="form-label">State/Province</label>
                                <input type="text" class="form-control" id="shipping_state" name="shipping_state">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="shipping_zip" class="form-label">Postal/Zip Code</label>
                                <input type="text" class="form-control" id="shipping_zip" name="shipping_zip">
                            </div>
                            <div class="col-md-6">
                                <label for="shipping_country" class="form-label">Country</label>
                                <input type="text" class="form-control" id="shipping_country" name="shipping_country" value="Nepal">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Order Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Special notes for delivery"></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Payment Method *</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_method_cod" value="cod" checked>
                                <label class="form-check-label" for="payment_method_cod">Cash on Delivery</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_method_khalti" value="khalti">
                                <label class="form-check-label" for="payment_method_khalti">Pay with Khalti</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="payment_method" id="payment_method_esewa" value="esewa">
                                <label class="form-check-label" for="payment_method_esewa">Pay with eSewa</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" id="place-order-btn">Place Order</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">Order Summary</h5>
                    
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th class="text-end">Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td class="text-end"><?php echo format_price($item['price'] * $item['quantity']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2">Subtotal</td>
                                    <td class="text-end"><?php echo format_price($subtotal); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="2">Shipping</td>
                                    <td class="text-end">
                                        <?php if ($shipping_amount > 0): ?>
                                            <?php echo format_price($shipping_amount); ?>
                                        <?php else: ?>
                                            <span class="text-success">Free</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr class="fw-bold">
                                    <td colspan="2">Total</td>
                                    <td class="text-end"><?php echo format_price($total_amount); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethodKhalti = document.getElementById('payment_method_khalti');
    const paymentMethodEsewa = document.getElementById('payment_method_esewa');
    const placeOrderBtn = document.getElementById('place-order-btn');
    const checkoutForm = document.getElementById('checkout-form');
    
    paymentMethodKhalti.addEventListener('change', function() {
        if (this.checked) {
            placeOrderBtn.innerText = 'Continue to Payment';
        }
    });

    paymentMethodEsewa.addEventListener('change', function() {
        if (this.checked) {
            placeOrderBtn.innerText = 'Continue to Payment';
        }
    });
    
    document.getElementById('payment_method_cod').addEventListener('change', function() {
        if (this.checked) {
            placeOrderBtn.innerText = 'Place Order';
        }
    });
    
    checkoutForm.addEventListener('submit', function(e) {
        if (paymentMethodKhalti.checked) {
            e.preventDefault();
            
            // Get form data
            const formData = new FormData(checkoutForm);
            const orderData = {
                amount: <?php echo $total_amount * 100; ?>, // Convert to paisa
                purchase_order_id: 'ORD-' + Date.now(),
                purchase_order_name: 'Order #' + 'ORD-' + new Date().getFullYear() + '-' + Math.random().toString(36).substr(2, 6).toUpperCase(),
                customer_info: {
                    name: formData.get('full_name'),
                    email: formData.get('email'),
                    phone: formData.get('phone')
                },
                amount_breakdown: {
                    subtotal: <?php echo $subtotal * 100; ?>,
                    shipping: <?php echo $shipping_amount * 100; ?>
                },
                product_details: <?php echo json_encode(array_map(function($item) {
                    return [
                        'identity' => $item['product_id'],
                        'name' => $item['name'],
                        'total_price' => $item['price'] * $item['quantity'] * 100,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'] * 100
                    ];
                }, $cart_items)); ?>,
                return_url: window.location.origin + '/order_confirmation.php',
                website_url: window.location.origin
            };

            // Initialize Khalti Checkout
            var config = {
                publicKey: "<?php echo $_ENV['KHALTI_PUBLIC_KEY']; ?>",
                productIdentity: orderData.purchase_order_id,
                productName: orderData.purchase_order_name,
                productUrl: orderData.website_url,
                paymentPreference: ["KHALTI"],
                eventHandler: {
                    onSuccess: function(payload) {
                        // Handle successful payment
                        console.log('Payment successful', payload);
                        // Submit the form with payment details
                        const paymentForm = document.createElement('form');
                        paymentForm.method = 'POST';
                        paymentForm.action = 'save-order.php';
                        
                        // Add payment details
                        const paymentData = {
                            ...Object.fromEntries(formData),
                            paymentMethod: 'khalti',
                            transactionId: payload.idx,
                            amount: orderData.amount / 100
                        };
                        
                        // Add all fields to form
                        Object.entries(paymentData).forEach(([key, value]) => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key;
                            input.value = value;
                            paymentForm.appendChild(input);
                        });
                        
                        document.body.appendChild(paymentForm);
                        paymentForm.submit();
                    },
                    onError: function(error) {
                        // Handle payment error
                        console.error('Payment error', error);
                        alert('Payment failed. Please try again or choose a different payment method.');
                    },
                    onClose: function() {
                        // Handle modal close
                        console.log('Payment modal closed');
                    }
                }
            };

            // Open Khalti Checkout with detailed amount breakdown
            var checkout = new KhaltiCheckout(config);
            checkout.show({
                amount: orderData.amount,
                amount_breakdown: orderData.amount_breakdown,
                product_details: orderData.product_details,
                customer_info: orderData.customer_info
            });
        } else if (paymentMethodEsewa.checked) {
            e.preventDefault();
            
            // Get form data
            const formData = new FormData(checkoutForm);
            const orderData = {
                amount: <?php echo $total_amount; ?>,
                tax_amount: 0,
                total_amount: <?php echo $total_amount; ?>,
                transaction_uuid: 'ORD-' + Date.now(),
                product_code: 'EPAYTEST',
                product_service_charge: 0,
                product_delivery_charge: <?php echo $shipping_amount; ?>,
                success_url: window.location.origin + '/order_confirmation.php',
                failure_url: window.location.origin + '/checkout.php',
                signed_field_names: 'total_amount,transaction_uuid,product_code',
                signature: '' // This will be generated by the server
            };

            // Create eSewa form
            const esewaForm = document.createElement('form');
            esewaForm.method = 'POST';
            esewaForm.action = 'https://uat.esewa.com.np/epay/main';

            // Add all required fields
            Object.entries(orderData).forEach(([key, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                esewaForm.appendChild(input);
            });

            // Add form to document and submit
            document.body.appendChild(esewaForm);
            esewaForm.submit();
        }
    });
});
</script>

<!-- Add Khalti Checkout Script -->
<script src="https://test-admin.khalti.com/static/khalti-checkout.js"></script>

<?php require_once 'includes/footer.php'; ?>
