<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// Require login
require_login();

// Get wishlist items
try {
    $stmt = $pdo->prepare("
        SELECT p.*, w.created_at as added_on
        FROM wishlist w
        JOIN products p ON w.product_id = p.id
        WHERE w.user_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $wishlist_items = $stmt->fetchAll();
    error_log("Wishlist items for user " . $_SESSION['user_id'] . ": " . count($wishlist_items));
} catch (PDOException $e) {
    error_log("Error fetching wishlist: " . $e->getMessage());
    $wishlist_items = [];
}
?>

<div class="container py-5">
    <h1 class="mb-4">My Wishlist</h1>
    <!-- Flash messages handled in header.php -->
    
    <?php if (empty($wishlist_items)): ?>
    <div class="text-center py-5">
        <i class="fas fa-heart text-muted display-1 mb-3"></i>
        <h3>Your wishlist is empty</h3>
        <p class="text-muted">Browse our products and add items to your wishlist!</p>
        <a href="products.php" class="btn btn-primary">Browse Products</a>
    </div>
    <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($wishlist_items as $item): ?>
        <div class="col">
            <div class="card h-100">
                <?php if ($item['image_url']): ?>
                <img src="assets/images/products/<?php echo htmlspecialchars($item['image_url']); ?>" 
                     class="card-img-top" 
                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                     style="height: 200px; object-fit: cover;">
                <?php endif; ?>
                
                <div class="card-body">
                    <h5 class="card-title">
                        <a href="product.php?id=<?php echo $item['id']; ?>" 
                           class="text-decoration-none text-dark">
                            <?php echo htmlspecialchars($item['name']); ?>
                        </a>
                    </h5>
                    
                    <p class="card-text text-truncate">
                        <?php echo htmlspecialchars($item['description']); ?>
                    </p>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="h5 mb-0"><?php echo format_price($item['price']); ?></span>
                        <div class="text-muted small">
                            Added <?php echo date('M d, Y', strtotime($item['added_on'])); ?>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <?php if ($item['stock'] > 0): ?>
                        <div class="d-flex gap-2 mb-2">
                            <select class="form-select form-select-sm w-auto" id="quantity-<?php echo $item['id']; ?>">
                                <?php for ($i = 1; $i <= min(10, $item['stock']); $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                            <button onclick="addToCartFromWishlist(<?php echo $item['id']; ?>)" class="btn btn-primary">
                                <i class="fas fa-shopping-cart me-1"></i> Add to Cart
                            </button>
                        </div>
                        <?php else: ?>
                        <button class="btn btn-secondary" disabled>Out of Stock</button>
                        <?php endif; ?>
                        
                        <button onclick="removeFromWishlist(<?php echo $item['id']; ?>)" 
                                class="btn btn-outline-danger">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function removeFromWishlist(productId) {
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('action', 'remove');
    formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');
    
    fetch('api/wishlist.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Error removing from wishlist');
        }
    })
    .catch(error => {
        alert('Error removing from wishlist');
    });
}

function addToCartFromWishlist(productId) {
    const quantity = parseInt(document.getElementById(`quantity-${productId}`).value);
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('quantity', quantity);
    formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');
    
    fetch('api/cart.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart count in header
            const cartCount = document.getElementById('cartCount');
            if (cartCount) {
                cartCount.textContent = data.cart_count || '0';
            }
            // Show success message
            alert('Product added to cart successfully!');
        } else {
            alert(data.error || 'Error adding to cart');
        }
    })
    .catch(error => {
        alert('Error adding to cart');
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>