<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

init_cart();

// Get cart items
$cart_items = [];
$total = 0;

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
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'image_url' => $product['image_url'],
                'stock' => $product['stock'],
                'quantity' => $quantity,
                'subtotal' => $product['price'] * $quantity
            ];
            $total += $product['price'] * $quantity;
        }
    } catch (PDOException $e) {
        error_log("Error fetching cart items: " . $e->getMessage());
        set_flash_message('danger', 'Error loading cart items. Please try again later.');
    }
}
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
                                        <img src="assets/images/<?php echo htmlspecialchars($item['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                                             class="img-thumbnail me-3" style="width: 50px;">
                                        <div>
                                            <h6 class="mb-0">
                                                <a href="product.php?id=<?php echo $item['id']; ?>" class="text-decoration-none">
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
                                                onclick="updateCartQuantity(<?php echo $item['id']; ?>, -1)">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" class="form-control form-control-sm text-center" 
                                               value="<?php echo $item['quantity']; ?>"
                                               min="1" max="<?php echo $item['stock']; ?>"
                                               onchange="updateCartQuantity(<?php echo $item['id']; ?>, this.value, true)">
                                        <button class="btn btn-outline-secondary btn-sm" 
                                                onclick="updateCartQuantity(<?php echo $item['id']; ?>, 1)">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </td>
                                <td><?php echo format_price($item['subtotal']); ?></td>
                                <td>
                                    <button class="btn btn-link text-danger btn-sm" 
                                            onclick="removeFromCart(<?php echo $item['id']; ?>)">
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
                    <span><?php echo $total >= 50 ? 'Free' : format_price(10); ?></span>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-3">
                    <strong>Total</strong>
                    <strong><?php echo format_price($total >= 50 ? $total : $total + 10); ?></strong>
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
function updateCartQuantity(productId, quantity, absolute = false) {
    let currentQuantity = parseInt(document.querySelector(`tr[data-product="${productId}"] input`).value);
    let newQuantity = absolute ? parseInt(quantity) : currentQuantity + parseInt(quantity);
    
    if (newQuantity < 1) newQuantity = 1;
    
    updateCart(productId, newQuantity);
}

function removeFromCart(productId) {
    if (confirm('Are you sure you want to remove this item from your cart?')) {
        updateCart(productId, 0);
    }
}

// Override the updateCart function from script.js to handle cart page specifics
function updateCart(productId, quantity) {
    $.ajax({
        url: '/ecomfinal/api/cart.php',
        type: 'POST',
        data: {
            action: quantity === 0 ? 'remove' : 'update',
            product_id: productId,
            quantity: quantity,
            csrf_token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            if (response.success) {
                location.reload(); // Reload to update all totals
            } else {
                showAlert('danger', response.message || 'Error updating cart');
            }
        },
        error: function() {
            showAlert('danger', 'Error updating cart');
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
