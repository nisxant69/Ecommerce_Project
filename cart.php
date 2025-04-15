<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Debug session
error_log("Cart page - Session status: " . session_status());
error_log("Cart page - User ID: " . ($_SESSION['user_id'] ?? 'not set'));
error_log("Cart page - Session data: " . print_r($_SESSION, true));

// Initialize variables
$cart_items = [];
$total = 0;
$shipping_fee = 0; // Set shipping fee to 0

// Get cart items
if (is_logged_in()) {
    try {
        // Get cart items with product details for logged-in users
        $cart_items = get_cart_items();
        
        // Calculate total
        foreach ($cart_items as $item) {
            $total += $item['price'] * $item['quantity'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching cart items: " . $e->getMessage());
        set_flash_message('danger', 'Error loading cart items. Please try again later.');
    }
} else {
    // Get cart items from session for non-logged-in users
    if (!empty($_SESSION['cart'])) {
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
                $cart_items[] = [
                    'product_id' => $product['id'],
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'image_url' => $product['image_url'],
                    'quantity' => $quantity,
                    'stock' => $product['stock']
                ];
                $total += $product['price'] * $quantity;
            }
        } catch (PDOException $e) {
            error_log("Error fetching session cart items: " . $e->getMessage());
            set_flash_message('danger', 'Error loading cart items. Please try again later.');
        }
    }
}

// Calculate final total with shipping
$final_total = $total + $shipping_fee;
?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <h1 class="card-title h3 mb-4">Shopping Cart</h1>
                
                <?php if (empty($cart_items)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                    <h2 class="h4">Your cart is empty</h2>
                    <p class="text-muted">Browse our products and add items to your cart.</p>
                    <a href="products.php" class="btn btn-primary">Continue Shopping</a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="assets/images/products/<?php echo htmlspecialchars($item['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                             class="img-thumbnail me-3" style="width: 50px;">
                                        <div>
                                            <h6 class="mb-0">
                                                <a href="product.php?id=<?php echo $item['product_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($item['name']); ?>
                                                </a>
                                            </h6>
                                            <?php if ($item['quantity'] > $item['stock']): ?>
                                            <small class="text-danger">Only <?php echo $item['stock']; ?> available</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo format_price($item['price']); ?></td>
                                <td>
                                    <div class="input-group" style="width: 120px;">
                                        <button class="btn btn-outline-secondary btn-sm" 
                                                onclick="updateCart(<?php echo $item['product_id']; ?>, -1)">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" 
                                               class="form-control form-control-sm text-center cart-quantity" 
                                               value="<?php echo $item['quantity']; ?>"
                                               min="1" 
                                               max="<?php echo $item['stock']; ?>"
                                               data-product-id="<?php echo $item['product_id']; ?>">
                                        <button class="btn btn-outline-secondary btn-sm" 
                                                onclick="updateCart(<?php echo $item['product_id']; ?>, 1)">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="cart-item-total"><?php echo format_price($item['price'] * $item['quantity']); ?></td>
                                <td>
                                    <button class="btn btn-link text-danger btn-sm" 
                                            onclick="updateCart(<?php echo $item['product_id']; ?>, 0)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Order Summary</h5>
                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal</span>
                    <span><?php echo format_price($total); ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Shipping</span>
                    <span><?php echo format_price($shipping_fee); ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-3">
                    <strong>Total</strong>
                    <strong><?php echo format_price($final_total); ?></strong>
                </div>
                
                <?php if (!empty($cart_items)): ?>
                <div class="d-grid gap-2">
                    <?php if (is_logged_in()): ?>
                    <a href="checkout.php" class="btn btn-primary">Proceed to Checkout</a>
                    <?php else: ?>
                    <a href="login.php?redirect=checkout.php" class="btn btn-primary">Login to Checkout</a>
                    <?php endif; ?>
                    <a href="products.php" class="btn btn-outline-primary">Continue Shopping</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
let updateInProgress = false;

function updateCart(productId, quantity, absolute = false) {
    if (updateInProgress) {
        console.log('Update already in progress');
        return;
    }
    
    updateInProgress = true;
    
    const formData = new FormData();
    formData.append('action', quantity === 0 ? 'remove' : (absolute ? 'update' : 'add'));
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    formData.append('absolute', absolute ? '1' : '0');
    formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');
    
    fetch('api/cart.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart count in header
            const cartCount = document.getElementById('cartCount');
            if (cartCount) {
                cartCount.textContent = data.cart_count || '0';
            }
            // Reload page to show updated cart
            window.location.reload();
        } else {
            showAlert('danger', data.message || 'Error updating cart');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'Error updating cart. Please try again.');
    })
    .finally(() => {
        updateInProgress = false;
    });
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
    alertDiv.style.zIndex = '1050';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    document.body.appendChild(alertDiv);
    
    // Auto dismiss after 3 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 3000);
}
</script>

<?php require_once 'includes/footer.php'; ?>
