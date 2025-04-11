<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

// Get the search query
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$products = [];

if (!empty($query)) {
    try {
        // Search for products by name or description
        $stmt = $pdo->prepare("
            SELECT * FROM products 
            WHERE (name LIKE ? OR description LIKE ?) 
            AND is_deleted = 0
        ");
        $search_term = "%$query%";
        $stmt->execute([$search_term, $search_term]);
        $products = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Search error: " . $e->getMessage());
        set_flash_message('danger', 'Error performing search. Please try again later.');
    }
}
?>

<div class="container my-5">
    <h2>Search Results for "<?php echo htmlspecialchars($query); ?>"</h2>
    <?php if (empty($query)): ?>
        <p class="text-muted">Please enter a search term.</p>
    <?php elseif (empty($products)): ?>
        <p class="text-muted">No products found matching your search.</p>
    <?php else: ?>
        <div class="row">
            <?php foreach ($products as $product): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <?php
                        $image_path = __DIR__ . '/assets/images/products/' . $product['image_url'];
                        $image_url = (!empty($product['image_url']) && file_exists($image_path)) 
                            ? 'assets/images/products/' . $product['image_url'] 
                            : 'https://placehold.co/400x300?text=' . urlencode($product['name']);
                        ?>
                        <img src="<?php echo htmlspecialchars($image_url); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>" style="height: 200px; object-fit: cover;">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <p class="card-text"><?php echo format_price($product['price']); ?></p>
                            <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary btn-sm">View Product</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>