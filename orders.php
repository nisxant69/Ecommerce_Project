<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Require login
require_login();

try {
    // Get user's orders with pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    // Get total orders count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM orders 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $total_orders = $stmt->fetchColumn();
    $total_pages = ceil($total_orders / $per_page);
    
    // Get orders for current page
    $stmt = $pdo->prepare("
        SELECT o.*, 
               COUNT(oi.id) as total_items 
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        WHERE o.user_id = ? 
        GROUP BY o.id 
        ORDER BY o.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$_SESSION['user_id'], $per_page, $offset]);
    $orders = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    $orders = [];
    $total_pages = 0;
    $page = 1;
}
?>

<div class="container py-5">
    <h1 class="mb-4">My Orders</h1>
    
    <?php if ($flash = get_flash_message()): ?>
    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
        <?php echo $flash['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    
    <?php if (empty($orders)): ?>
    <div class="text-center py-5">
        <i class="fas fa-shopping-bag text-muted display-1 mb-3"></i>
        <h3>No orders found</h3>
        <p class="text-muted">You haven't placed any orders yet.</p>
        <a href="products.php" class="btn btn-primary">Start Shopping</a>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Order Number</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['order_number'] ?? '#' . str_pad($order['id'], 8, '0', STR_PAD_LEFT)); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                            <td><?php echo $order['total_items']; ?> items</td>
                            <td><?php echo format_price($order['total_amount']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo match($order['status']) {
                                        'pending' => 'warning',
                                        'processing' => 'info',
                                        'shipped' => 'primary',
                                        'delivered' => 'success',
                                        'cancelled' => 'danger',
                                        'refunded' => 'secondary',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="order.php?id=<?php echo $order['id']; ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    View Details
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
