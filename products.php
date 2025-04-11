<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Get filter parameters
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
$brand_id = isset($_GET['brand']) ? (int)$_GET['brand'] : null;
$min_price = isset($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) ? (float)$_GET['max_price'] : null;
$min_rating = isset($_GET['min_rating']) ? (int)$_GET['min_rating'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;

// Build query
$query = "SELECT p.*, c.name as category_name 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          WHERE p.is_deleted = 0";
$params = [];

if ($category_id) {
    $query .= " AND p.category_id = ?";
    $params[] = $category_id;
}

if ($brand_id) {
    $query .= " AND p.brand_id = ?";
    $params[] = $brand_id;
}

if ($min_price !== null) {
    $query .= " AND p.price >= ?";
    $params[] = $min_price;
}

if ($max_price !== null) {
    $query .= " AND p.price <= ?";
    $params[] = $max_price;
}

if ($min_rating !== null) {
    $query .= " AND p.avg_rating >= ?";
    $params[] = $min_rating;
}

if ($search) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
}

// Add sorting
switch ($sort) {
    case 'price_asc':
        $query .= " ORDER BY p.price ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY p.price DESC";
        break;
    case 'rating':
        $query .= " ORDER BY p.avg_rating DESC, p.total_reviews DESC";
        break;
    case 'popularity':
        $query .= " ORDER BY p.total_sales DESC, p.avg_rating DESC";
        break;
    default: // newest
        $query .= " ORDER BY p.created_at DESC";
}

// Get total count for pagination
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM (" . $query . ") as count_table");
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetchColumn();
    $total_pages = ceil($total_products / $per_page);
    
    // Ensure page is within valid range
    $page = max(1, min($page, $total_pages));
    
    // Add pagination
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = ($page - 1) * $per_page;
    
    // Get products
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
    // Get categories for filter
    $cat_stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $cat_stmt->fetchAll();
    
    // Get brands for filter
    $brand_stmt = $pdo->query("SELECT * FROM brands ORDER BY name");
    $brands = $brand_stmt->fetchAll();
    
    // Get price range
    $price_stmt = $pdo->query("SELECT MIN(price) as min_price, MAX(price) as max_price FROM products WHERE is_deleted = 0");
    $price_range = $price_stmt->fetch();
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    set_flash_message('danger', 'Error loading products. Please try again later.');
    $products = [];
    $categories = [];
    $total_pages = 0;
}
?>

<div class="row">
    <!-- Filters Sidebar -->
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Filters</h5>
                <!-- Active Filters -->
                <div class="active-filters mb-3" id="activeFilters"></div>
                
                <form action="products.php" method="GET" id="filterForm">
                    <!-- Category Filter -->
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" 
                                    <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Brand Filter -->
                    <div class="mb-3">
                        <label class="form-label">Brand</label>
                        <select name="brand" class="form-select">
                            <option value="">All Brands</option>
                            <?php foreach ($brands as $brand): ?>
                            <option value="<?php echo $brand['id']; ?>" 
                                    <?php echo $brand_id == $brand['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($brand['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Price Range Filter -->
                    <div class="mb-3">
                        <label class="form-label">Price Range</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="number" class="form-control" name="min_price" 
                                       placeholder="Min" step="0.01" min="<?php echo $price_range['min_price']; ?>" 
                                       value="<?php echo $min_price; ?>">
                            </div>
                            <div class="col-6">
                                <input type="number" class="form-control" name="max_price" 
                                       placeholder="Max" step="0.01" max="<?php echo $price_range['max_price']; ?>" 
                                       value="<?php echo $max_price; ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Rating Filter -->
                    <div class="mb-3">
                        <label class="form-label">Minimum Rating</label>
                        <div class="rating-filter">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="min_rating" 
                                       value="<?php echo $i; ?>" id="rating<?php echo $i; ?>" 
                                       <?php echo $min_rating === $i ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="rating<?php echo $i; ?>">
                                    <?php for ($j = 1; $j <= 5; $j++): ?>
                                    <i class="<?php echo $j <= $i ? 'fas' : 'far'; ?> fa-star"></i>
                                    <?php endfor; ?>
                                    & Up
                                </label>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <!-- Sort Filter -->
                    <div class="mb-3">
                        <label class="form-label">Sort By</label>
                        <select name="sort" class="form-select">
                            <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest</option>
                            <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="rating" <?php echo $sort == 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                            <option value="popularity" <?php echo $sort == 'popularity' ? 'selected' : ''; ?>>Most Popular</option>
                        </select>
                    </div>
                    
                    <!-- Apply Filters Button -->
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                    
                    <!-- Search Filter -->
                    <div class="mb-3">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Search products...">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Products Grid -->
    <div class="col-md-9">
        <!-- Results count and view options -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <p class="mb-0">Showing <?php echo count($products); ?> of <?php echo $total_products; ?> products</p>
            <div class="btn-group">
                <button class="btn btn-outline-secondary btn-sm" data-view="grid">
                    <i class="fas fa-th-large"></i>
                </button>
                <button class="btn btn-outline-secondary btn-sm" data-view="list">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>
        
        <!-- Products -->
        <div class="row" id="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="col-md-4 col-6 mb-4">
                <div class="card h-100 product-card">
                    <?php if (is_logged_in()): ?>
                    <div class="wishlist-icon" onclick="toggleWishlist(<?php echo $product['id']; ?>)" id="wishlist-<?php echo $product['id']; ?>">
                        <i class="far fa-heart"></i>
                    </div>
                    <?php endif; ?>
                    <img src="assets/images/<?php echo htmlspecialchars($product['image_url']); ?>" 
                         class="card-img-top" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                        <p class="card-text text-muted"><?php echo htmlspecialchars($product['category_name']); ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="product-price"><?php echo format_price($product['price']); ?></span>
                            <div class="rating">
                                <?php
                                $rating = round($product['avg_rating']);
                                for ($i = 1; $i <= 5; $i++) {
                                    echo '<i class="' . ($i <= $rating ? 'fas' : 'far') . ' fa-star"></i>';
                                }
                                ?>
                            </div>
                        </div>
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
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Product pagination" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page-1; ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page+1; ?>&category=<?php echo $category_id; ?>&sort=<?php echo $sort; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
// View switcher
document.querySelectorAll('[data-view]').forEach(button => {
    button.addEventListener('click', function() {
        const view = this.dataset.view;
        const productsContainer = document.getElementById('products-grid');
        
        if (view === 'list') {
            productsContainer.classList.remove('row');
            productsContainer.querySelectorAll('.col-md-4').forEach(col => {
                col.classList.remove('col-md-4', 'col-6');
                col.classList.add('col-12', 'mb-3');
            });
        } else {
            productsContainer.classList.add('row');
            productsContainer.querySelectorAll('.col-12').forEach(col => {
                col.classList.remove('col-12', 'mb-3');
                col.classList.add('col-md-4', 'col-6');
            });
        }
        
        // Update active state
        document.querySelectorAll('[data-view]').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
    });
});
// Active Filters
function updateActiveFilters() {
    const activeFilters = document.getElementById('activeFilters');
    activeFilters.innerHTML = '';
    
    // Category filter
    const category = document.querySelector('select[name="category"]');
    if (category.value) {
        const categoryText = category.options[category.selectedIndex].text;
        addFilterTag('Category', categoryText, () => {
            category.value = '';
            document.getElementById('filterForm').submit();
        });
    }
    
    // Brand filter
    const brand = document.querySelector('select[name="brand"]');
    if (brand.value) {
        const brandText = brand.options[brand.selectedIndex].text;
        addFilterTag('Brand', brandText, () => {
            brand.value = '';
            document.getElementById('filterForm').submit();
        });
    }
    
    // Price range filter
    const minPrice = document.querySelector('input[name="min_price"]').value;
    const maxPrice = document.querySelector('input[name="max_price"]').value;
    if (minPrice || maxPrice) {
        const priceText = minPrice && maxPrice ? 
            `$${minPrice} - $${maxPrice}` : 
            minPrice ? `Min: $${minPrice}` : `Max: $${maxPrice}`;
        addFilterTag('Price', priceText, () => {
            document.querySelector('input[name="min_price"]').value = '';
            document.querySelector('input[name="max_price"]').value = '';
            document.getElementById('filterForm').submit();
        });
    }
    
    // Rating filter
    const rating = document.querySelector('input[name="min_rating"]:checked');
    if (rating) {
        const stars = '★'.repeat(rating.value) + '☆'.repeat(5 - rating.value);
        addFilterTag('Rating', `${stars} & Up`, () => {
            rating.checked = false;
            document.getElementById('filterForm').submit();
        });
    }
    
    // Sort filter
    const sort = document.querySelector('select[name="sort"]');
    if (sort.value !== 'newest') {
        const sortText = sort.options[sort.selectedIndex].text;
        addFilterTag('Sort', sortText, () => {
            sort.value = 'newest';
            document.getElementById('filterForm').submit();
        });
    }
}

function addFilterTag(label, value, onRemove) {
    const activeFilters = document.getElementById('activeFilters');
    const tag = document.createElement('div');
    tag.className = 'filter-tag';
    tag.innerHTML = `
        <small class="text-muted">${label}:</small> 
        ${value}
        <i class="fas fa-times remove-filter"></i>
    `;
    tag.querySelector('.remove-filter').addEventListener('click', onRemove);
    activeFilters.appendChild(tag);
}

// Initialize active filters
document.addEventListener('DOMContentLoaded', () => {
    updateActiveFilters();
});
</script>

<?php require_once 'includes/footer.php'; ?>
