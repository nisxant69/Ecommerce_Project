<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Require login
require_login();

// Handle add/remove from wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Invalid form submission.');
        header('Location: wishlist.php');
        exit();
    }
    
    $product_id = (int)$_POST['product_id'];
    $action = $_POST['action'];
    
    try {
        if ($action === 'remove') {
            $stmt = $pdo->prepare("
                DELETE FROM wishlist 
                WHERE user_id = ? AND product_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $product_id]);
            
            set_flash_message('success', 'Product removed from wishlist.');
        }
        
        header('Location: wishlist.php');
        exit();
        
    } catch (PDOException $e) {
        error_log("Wishlist error: " . $e->getMessage());
        set_flash_message('danger', 'Error updating wishlist.');
    }
}

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
    
} catch (PDOException $e) {
    error_log("Error fetching wishlist: " . $e->getMessage());
    $wishlist_items = [];
}
?>

<div class="container py-5">
    <h1 class="mb-4">My Wishlist</h1>
    
    <?php if ($flash = get_flash_message()): ?>
    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
        <?php echo $flash['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
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
                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
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
                            Added <?php echo time_ago($item['added_on']); ?>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <?php if ($item['stock'] > 0): ?>
                        <form action="cart.php" method="POST" class="d-inline-block">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-shopping-cart me-1"></i> Add to Cart
                            </button>
                        </form>
                        <?php else: ?>
                        <button class="btn btn-secondary" disabled>Out of Stock</button>
                        <?php endif; ?>
                        
                        <form method="POST" class="d-inline-block">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                            <input type="hidden" name="action" value="remove">
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
