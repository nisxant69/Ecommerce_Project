<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Test database connection
try {
    $test_stmt = $pdo->query("SELECT 1");
    error_log("Database connection test in index.php: Success");
} catch (PDOException $e) {
    error_log("Database connection test failed in index.php: " . $e->getMessage());
    set_flash_message('danger', 'Database connection failed. Please try again later.');
    $featured_products = [];
    $categories = [];
    // Skip further queries if connection fails
    goto render_page;
}

// Fetch featured products and categories
try {
    // Fetch featured products
    $featured_query = "
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_deleted = 0
        ORDER BY p.created_at DESC 
        LIMIT 8";
    
    error_log("Executing featured query: " . $featured_query);
    $stmt = $pdo->prepare($featured_query);
    $stmt->execute();
    $featured_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($featured_products) . " featured products");
    
    // Fetch all categories
    // Removed 'active' condition to avoid potential column mismatch; adjust based on actual table structure
    $categories_query = "SELECT * FROM categories ORDER BY name";
    error_log("Executing categories query: " . $categories_query);
    $stmt = $pdo->prepare($categories_query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($categories) . " categories");
} catch (PDOException $e) {
    error_log("Error in index.php: " . $e->getMessage());
    set_flash_message('danger', 'Error loading products. Please try again later.');
    $featured_products = [];
    $categories = [];
}

render_page:
?>

<!-- Hero Section -->
<div id="heroCarousel" class="carousel slide mb-4" data-bs-ride="carousel">
    <div class="carousel-indicators">
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active"></button>
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1"></button>
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2"></button>
    </div>
    <div class="carousel-inner">
        <div class="carousel-item active">
            <div class="bg-primary p-5 text-white text-center" style="height: 400px;">
                <div class="d-flex flex-column justify-content-center h-100">
                    <h2 class="display-4">Welcome to Our Store</h2>
                    <p class="lead">Discover amazing tech products at great prices</p>
                    <a href="products.php" class="btn btn-light btn-lg mt-3">Shop Now</a>
                </div>
            </div>
        </div>
        <div class="carousel-item">
            <div class="bg-success p-5 text-white text-center" style="height: 400px;">
                <div class="d-flex flex-column justify-content-center h-100">
                    <h2 class="display-4">New Arrivals</h2>
                    <p class="lead">Check out our latest gaming laptops and accessories</p>
                    <a href="products.php?category=1" class="btn btn-light btn-lg mt-3">View Gaming Laptops</a>
                </div>
            </div>
        </div>
        <div class="carousel-item">
            <div class="bg-danger p-5 text-white text-center" style="height: 400px;">
                <div class="d-flex flex-column justify-content-center h-100">
                    <h2 class="display-4">Special Deals</h2>
                    <p class="lead">Limited time offers on selected items</p>
                    <a href="products.php?sort=discount" class="btn btn-light btn-lg mt-3">View Deals</a>
                </div>
            </div>
        </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
    </button>
</div>

<!-- Categories Section -->
<section class="categories-section mb-5">
    <h2 class="mb-4">Shop by Category</h2>
    <div class="row">
        <?php if (!empty($categories)): ?>
            <?php foreach ($categories as $category): ?>
            <div class="col-md-3 col-6 mb-4">
                <a href="products.php?category=<?php echo $category['id']; ?>" class="text-decoration-none">
                    <div class="card h-100 category-card">
                        <div class="card-body text-center">
                            <i class="fas fa-folder fa-3x mb-3 text-primary"></i>
                            <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info text-center">No categories available.</div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Featured Products Section -->
<section class="featured-products mb-5">
    <h2 class="text-center mb-4">Featured Products</h2>
    <div class="row row-cols-1 row-cols-md-4 g-4 mb-4">
        <?php if (!empty($featured_products)): ?>
            <?php foreach ($featured_products as $product): ?>
            <div class="col">
                <div class="card h-100 product-card">
                    <?php if (is_logged_in()): ?>
                    <div class="wishlist-icon" onclick="toggleWishlist(<?php echo $product['id']; ?>)" id="wishlist-<?php echo $product['id']; ?>">
                        <i class="far fa-heart"></i>
                    </div>
                    <?php endif; ?>
                    <?php
                    $image_path = __DIR__ . '/assets/images/products/' . $product['image_url'];
                    error_log("Checking image path for product {$product['id']}: $image_path");
                    $image_url = (!empty($product['image_url']) && file_exists($image_path)) ? 
                        'assets/images/products/' . $product['image_url'] : 
                        'https://placehold.co/400x300?text=' . urlencode($product['name']);
                    ?>
                    <img src="<?php echo htmlspecialchars($image_url); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>" style="height: 200px; object-fit: cover;">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                        <p class="card-text text-muted"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></p>
                        <p class="product-price mb-0"><?php echo format_price($product['price']); ?></p>
                        <div class="mt-3">
                            <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-outline-primary btn-sm">View Details</a>
                            <button onclick="updateCart(<?php echo $product['id']; ?>, 1)" 
                                    class="btn btn-primary btn-sm">
                                Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info text-center">No featured products available at the moment. Check back later!</div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Features Section -->
<section class="features-section mb-5">
    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-truck fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">Free Shipping</h5>
                    <p class="card-text">On orders over $50</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-undo fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">Easy Returns</h5>
                    <p class="card-text">30-day return policy</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card text-center h-100">
                <div class="card-body">
                    <i class="fas fa-lock fa-3x text-primary mb-3"></i>
                    <h5 class="card-title">Secure Payment</h5>
                    <p class="card-text">Safe & secure checkout</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>