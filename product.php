<?php
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
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
    
    $review_stmt = $pdo->prepare("
        SELECT r.*, u.name as user_name 
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.product_id = ? 
        ORDER BY r.created_at DESC
    ");
    $review_stmt->execute([$product_id]);
    $reviews = $review_stmt->fetchAll();
    
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
    set_flash_message('danger', 'Error loading product details.');
    header('Location: products.php');
    exit();
}

// Check if user has reviewed
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
    <div class="col-md-8">
        <div class="card">
            <div class="row g-0">
                <div class="col-md-6">
                    <img src="assets/images/products/<?php echo htmlspecialchars($product['image_url']); ?>" 
                         class="img-fluid rounded-start" 
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
                            <button class="btn btn-primary add-to-cart" data-product-id="<?php echo $product['id']; ?>">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                            <?php if (is_logged_in()): ?>
                            <button class="btn btn-outline-<?php echo $in_wishlist ? 'danger' : 'primary'; ?> toggle-wishlist" 
                                    data-product-id="<?php echo $product['id']; ?>"
                                    data-in-wishlist="<?php echo $in_wishlist ? '1' : '0'; ?>"
                                    id="wishlist-<?php echo $product['id']; ?>">
                                <i class="fa<?php echo $in_wishlist ? 's' : 'r'; ?> fa-heart"></i>
                                <span><?php echo $in_wishlist ? ' Remove from Wishlist' : ' Add to Wishlist'; ?></span>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h3 class="card-title h4">Customer Reviews</h3>
                
                <?php if (is_logged_in() && !$has_reviewed): ?>
                <form id="reviewForm" class="mb-4">
                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Your Rating</label>
                        <div class="rating-input">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" value="<?php echo $i; ?>" id="star<?php echo $i; ?>" required>
                            <label for="star<?php echo $i; ?>"><i class="far fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                        <div class="invalid-feedback">Please select a rating</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Your Review</label>
                        <textarea name="comment" class="form-control" rows="3" required minlength="10" maxlength="1000"></textarea>
                        <div class="invalid-feedback">Please enter your review (minimum 10 characters)</div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </form>

                <script>
                document.getElementById('reviewForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Clear previous error messages
                    document.querySelectorAll('.alert').forEach(alert => alert.remove());
                    
                    const formData = new FormData(this);
                    
                    // Validate rating
                    if (!formData.get('rating')) {
                        const alert = createAlert('danger', 'Please select a rating');
                        this.insertBefore(alert, this.firstChild);
                        return;
                    }
                    
                    // Validate comment
                    const comment = formData.get('comment').trim();
                    if (!comment || comment.length < 10) {
                        const alert = createAlert('danger', 'Please enter a review of at least 10 characters');
                        this.insertBefore(alert, this.firstChild);
                        return;
                    }
                    
                    fetch('api/reviews.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Create new review element
                            const reviewHtml = `
                                <div class="review-card mb-3">
                                    <div class="d-flex justify-content-between">
                                        <strong>${data.review.user_name}</strong>
                                        <small class="text-muted">${data.review.created_at}</small>
                                    </div>
                                    <div class="rating">
                                        ${Array(5).fill(0).map((_, i) => 
                                            `<i class="${i < data.review.rating ? 'fas' : 'far'} fa-star"></i>`
                                        ).join('')}
                                    </div>
                                    <p class="mb-0">${data.review.comment.replace(/\n/g, '<br>')}</p>
                                </div>
                            `;
                            
                            // Remove "no reviews" message if it exists
                            const noReviews = document.getElementById('noReviews');
                            if (noReviews) {
                                noReviews.remove();
                            }
                            
                            // Add new review to the list
                            document.querySelector('.reviews-list').insertAdjacentHTML('afterbegin', reviewHtml);
                            
                            // Hide the review form
                            this.style.display = 'none';
                            
                            // Show success message
                            const alert = createAlert('success', data.message);
                            document.querySelector('.reviews-list').insertBefore(alert, document.querySelector('.reviews-list').firstChild);
                            
                            // Update product rating display
                            updateProductRating(data.review.rating);
                        } else {
                            // Show error message
                            const alert = createAlert('danger', data.error);
                            this.insertBefore(alert, this.firstChild);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        const alert = createAlert('danger', 'Error submitting review. Please try again.');
                        this.insertBefore(alert, this.firstChild);
                    });
                });

                function createAlert(type, message) {
                    const alert = document.createElement('div');
                    alert.className = `alert alert-${type} alert-dismissible fade show`;
                    alert.innerHTML = `
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    return alert;
                }

                function updateProductRating(newRating) {
                    const ratingContainer = document.querySelector('.product-rating');
                    if (ratingContainer) {
                        const stars = ratingContainer.querySelectorAll('i');
                        stars.forEach((star, index) => {
                            if (index < newRating) {
                                star.classList.remove('far');
                                star.classList.add('fas');
                            } else {
                                star.classList.remove('fas');
                                star.classList.add('far');
                            }
                        });
                    }
                }
                </script>
                <?php endif; ?>
                
                <div class="reviews-list">
                    <?php if (empty($reviews)): ?>
                        <p class="text-muted" id="noReviews">No reviews yet.</p>
                    <?php else: ?>
                        <?php foreach ($reviews as $review): ?>
                        <div class="review-card mb-3">
                            <div class="d-flex justify-content-between">
                                <strong><?php echo htmlspecialchars($review['user_name']); ?></strong>
                                <small class="text-muted"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></small>
                            </div>
                            <div class="rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="<?php echo $i <= $review['rating'] ? 'fas' : 'far'; ?> fa-star"></i>
                                <?php endfor; ?>
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

<?php if (!empty($related_products)): ?>
<section class="related-products mt-5">
    <h3 class="mb-4">Related Products</h3>
    <div class="row">
        <?php foreach ($related_products as $related): ?>
        <div class="col-md-3 col-6 mb-4">
            <div class="card h-100">
                <img src="assets/images/products/<?php echo htmlspecialchars($related['image_url']); ?>" 
                     class="card-img-top" 
                     alt="<?php echo htmlspecialchars($related['name']); ?>">
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($related['name']); ?></h5>
                    <div class="d-flex justify-content-between align-items-center">
                        <span><?php echo format_price($related['price']); ?></span>
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

<style>
.rating-input {
    display: flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
}

.rating-input input {
    display: none;
}

.rating-input label {
    cursor: pointer;
    font-size: 1.5rem;
    color: #ddd;
    padding: 0 0.1em;
}

.rating-input label:hover,
.rating-input label:hover ~ label,
.rating-input input:checked ~ label {
    color: #ffc107;
}

.rating-input i {
    transition: color 0.2s;
}
</style>

<script>
// Star rating functionality
document.addEventListener('DOMContentLoaded', function() {
    // Star rating hover effect
    const ratingInputs = document.querySelectorAll('.rating-input input');
    const ratingLabels = document.querySelectorAll('.rating-input label');

    ratingLabels.forEach(label => {
        // Mouseover effect
        label.addEventListener('mouseover', function() {
            const rating = this.previousElementSibling.value;
            updateStars(rating);
        });

        // Click effect
        label.addEventListener('click', function() {
            const rating = this.previousElementSibling.value;
            updateStars(rating, true);
        });
    });

    // Reset stars when mouse leaves the rating container
    const ratingContainer = document.querySelector('.rating-input');
    if (ratingContainer) {
        ratingContainer.addEventListener('mouseleave', function() {
            const checkedInput = document.querySelector('.rating-input input:checked');
            const rating = checkedInput ? checkedInput.value : 0;
            updateStars(rating);
        });
    }

    function updateStars(rating, permanent = false) {
        ratingLabels.forEach(label => {
            const star = label.querySelector('i');
            const value = label.previousElementSibling.value;
            
            if (value <= rating) {
                star.classList.remove('far');
                star.classList.add('fas');
            } else {
                star.classList.remove('fas');
                star.classList.add('far');
            }
            
            if (permanent) {
                label.previousElementSibling.checked = (value == rating);
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>