<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if product is in user's wishlist
$in_wishlist = false;
if (is_logged_in()) {
    try {
        $wishlist_stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $wishlist_stmt->execute([$_SESSION['user_id'], $product_id]);
        $in_wishlist = $wishlist_stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log("Error checking wishlist: " . $e->getMessage());
    }
}

try {
    // Fetch product details
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.id = ? AND p.is_deleted = 0
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        set_flash_message('danger', 'Product not found.');
        header('Location: products.php');
        exit();
    }
    
    // Fetch product reviews
    $review_stmt = $pdo->prepare("
        SELECT r.*, u.name as user_name 
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.product_id = ? 
        ORDER BY r.created_at DESC
    ");
    $review_stmt->execute([$product_id]);
    $reviews = $review_stmt->fetchAll();
    
    // Fetch related products
    $related_stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE category_id = ? 
        AND id != ? 
        AND is_deleted = 0 
        ORDER BY created_at DESC 
        LIMIT 4
    ");
    $related_stmt->execute([$product['category_id'], $product_id]);
    $related_products = $related_stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    set_flash_message('danger', 'Error loading product details. Please try again later.');
    header('Location: products.php');
    exit();
}

// Check if user has already reviewed
$has_reviewed = false;
if (is_logged_in()) {
    try {
        $check_review = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
        $check_review->execute([$_SESSION['user_id'], $product_id]);
        $has_reviewed = $check_review->fetch() !== false;
    } catch (PDOException $e) {
        error_log("Error checking review: " . $e->getMessage());
    }
}
?>

<div class="row">
    <!-- Product Images and Details -->
    <div class="col-md-8">
        <div class="card">
            <div class="row g-0">
                <div class="col-md-6">
                    <img src="/ecomfinal/assets/images/products/<?php echo htmlspecialchars($product['image_url']); ?>" 
                         class="img-fluid rounded-start product-image" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                </div>
                <div class="col-md-6">
                    <div class="card-body">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                                <li class="breadcrumb-item"><a href="products.php?category=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a></li>
                                <li class="breadcrumb-item active"><?php echo htmlspecialchars($product['name']); ?></li>
                            </ol>
                        </nav>
                        
                        <h1 class="card-title h2"><?php echo htmlspecialchars($product['name']); ?></h1>
                        <p class="text-muted"><?php echo htmlspecialchars($product['category_name']); ?></p>
                        
                        <div class="mb-3">
                            <span class="h3 text-primary"><?php echo format_price($product['price']); ?></span>
                            <?php if ($product['stock'] > 0): ?>
                                <span class="badge bg-success ms-2">In Stock</span>
                            <?php else: ?>
                                <span class="badge bg-danger ms-2">Out of Stock</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="rating mb-3">
                            <?php
                            $rating = round($product['avg_rating']);
                            for ($i = 1; $i <= 5; $i++) {
                                echo '<i class="' . ($i <= $rating ? 'fas' : 'far') . ' fa-star"></i>';
                            }
                            echo " <span class='text-muted'>(" . count($reviews) . " reviews)</span>";
                            ?>
                        </div>
                        
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                        
                        <?php if ($product['stock'] > 0): ?>
                        <div class="d-flex align-items-center mb-3">
                            <label class="me-2">Quantity:</label>
                            <select class="form-select w-auto" id="quantity">
                                <?php for ($i = 1; $i <= min(10, $product['stock']); $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button onclick="updateCart(<?php echo $product['id']; ?>, document.getElementById('quantity').value)" 
                                    class="btn btn-primary">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                            <?php if (is_logged_in()): ?>
                            <button onclick="toggleWishlist(<?php echo $product['id']; ?>)" 
                                    class="btn btn-outline-<?php echo $in_wishlist ? 'danger' : 'primary'; ?>" 
                                    id="wishlistBtn">
                                <i class="fa<?php echo $in_wishlist ? 's' : 'r'; ?> fa-heart"></i>
                                <?php echo $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reviews Section -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h3 class="card-title h4">Customer Reviews</h3>
                
                <?php if (is_logged_in() && !$has_reviewed): ?>
                <form action="api/reviews.php" method="POST" class="mb-4" id="reviewForm">
                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Your Rating</label>
                        <div class="rating-input">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" required>
                            <label for="star<?php echo $i; ?>"><i class="far fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Your Review</label>
                        <textarea name="comment" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </form>
                <?php endif; ?>
                
                <div class="reviews-list">
                    <?php if (empty($reviews)): ?>
                        <p class="text-muted">No reviews yet. Be the first to review this product!</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                        <div class="review-card">
                            <div class="d-flex justify-content-between">
                                <strong><?php echo htmlspecialchars($review['user_name']); ?></strong>
                                <small class="text-muted">
                                    <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                </small>
                            </div>
                            <div class="rating">
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    echo '<i class="' . ($i <= $review['rating'] ? 'fas' : 'far') . ' fa-star"></i>';
                                }
                                ?>
                            </div>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Related Products -->
<?php if (!empty($related_products)): ?>
<section class="related-products mt-5">
    <h3 class="mb-4">Related Products</h3>
    <div class="row">
        <?php foreach ($related_products as $related): ?>
        <div class="col-md-3 col-6 mb-4">
            <div class="card h-100 product-card">
                <img src="assets/images/<?php echo htmlspecialchars($related['image_url']); ?>" 
                     class="card-img-top" 
                     alt="<?php echo htmlspecialchars($related['name']); ?>">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($related['name']); ?></h5>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="product-price"><?php echo format_price($related['price']); ?></span>
                        <div class="rating">
                            <?php
                            $rating = round($related['avg_rating']);
                            for ($i = 1; $i <= 5; $i++) {
                                echo '<i class="' . ($i <= $rating ? 'fas' : 'far') . ' fa-star"></i>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="product.php?id=<?php echo $related['id']; ?>" class="btn btn-outline-primary btn-sm">View Details</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<script>
// Wishlist functionality
function toggleWishlist(productId) {
    const btn = document.getElementById('wishlistBtn');
    const isInWishlist = btn.classList.contains('btn-outline-danger');
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('action', isInWishlist ? 'remove' : 'add');
    formData.append('csrf_token', '<?php echo generate_csrf_token(); ?>');
    
    fetch('api/wishlist.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Toggle button appearance
            btn.classList.toggle('btn-outline-primary');
            btn.classList.toggle('btn-outline-danger');
            
            // Toggle heart icon
            const icon = btn.querySelector('i');
            icon.classList.toggle('far');
            icon.classList.toggle('fas');
            
            // Update button text
            btn.innerHTML = btn.innerHTML.replace(
                isInWishlist ? 'Remove from Wishlist' : 'Add to Wishlist',
                isInWishlist ? 'Add to Wishlist' : 'Remove from Wishlist'
            );
            
            showAlert('success', data.message);
        } else {
            showAlert('danger', data.error || 'Error updating wishlist');
        }
    })
    .catch(error => {
        showAlert('danger', 'Error updating wishlist');
    });
}

// Rating input interactivity
document.querySelectorAll('.rating-input label').forEach(label => {
    label.addEventListener('mouseover', function() {
        const stars = this.parentElement.querySelectorAll('label i');
        const value = this.previousElementSibling.value;
        stars.forEach((star, index) => {
            star.className = index < value ? 'fas fa-star' : 'far fa-star';
        });
    });
    
    label.addEventListener('mouseout', function() {
        const container = this.parentElement;
        const selected = container.querySelector('input:checked');
        const stars = container.querySelectorAll('label i');
        stars.forEach((star, index) => {
            star.className = selected && index < selected.value ? 'fas fa-star' : 'far fa-star';
        });
    });
});

// Review form submission
document.getElementById('reviewForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch(this.action, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            showAlert('danger', data.message || 'Error submitting review');
        }
    })
    .catch(error => {
        showAlert('danger', 'Error submitting review');
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
