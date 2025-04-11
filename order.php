<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Require login
require_login();

// Get order details
$order_id = (int)$_GET['id'];

try {
    // Get order with customer details
    $stmt = $pdo->prepare("
        SELECT o.*, u.name as customer_name, u.email as customer_email
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        set_flash_message('danger', 'Order not found.');
        header('Location: profile.php');
        exit();
    }
    
    // Get order items with product details
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name, p.image_url
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    set_flash_message('danger', 'Error loading order details.');
    header('Location: profile.php');
    exit();
}
?>

<div class="container py-5">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="card-title mb-0">
                            Order #<?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?>
                        </h5>
                        <span class="badge bg-<?php 
                            echo match($order['status']) {
                                'pending' => 'warning',
                                'processing' => 'info',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'secondary'
                            };
                        ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                    
                    <?php if ($flash = get_flash_message()): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                        <?php echo $flash['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Order Information -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Order Information</h6>
                            <p class="mb-1">Date: <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></p>
                            <p class="mb-1">Status: <?php echo ucfirst($order['status']); ?></p>
                            <p class="mb-1">Payment Method: <?php echo ucfirst($order['payment_method']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Shipping Address</h6>
                            <p class="mb-1"><?php echo htmlspecialchars($order['shipping_address']); ?></p>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <h6>Order Items</h6>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($item['image_url']): ?>
                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                 class="me-2" style="width: 50px; height: 50px; object-fit: cover;">
                                            <?php endif; ?>
                                            <div>
                                                <a href="product.php?id=<?php echo $item['product_id']; ?>" 
                                                   class="text-decoration-none">
                                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo format_price($item['price']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td class="text-end">
                                        <?php echo format_price($item['price'] * $item['quantity']); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                    <td class="text-end"><?php echo format_price($order['subtotal']); ?></td>
                                </tr>
                                <?php if ($order['discount_amount'] > 0): ?>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Discount:</strong></td>
                                    <td class="text-end">-<?php echo format_price($order['discount_amount']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Shipping:</strong></td>
                                    <td class="text-end"><?php echo format_price($order['shipping_fee']); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                    <td class="text-end"><strong><?php echo format_price($order['total_amount']); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="profile.php" class="btn btn-secondary">Back to Profile</a>
                        
                        <?php if ($order['status'] === 'completed'): ?>
                        <button type="button" class="btn btn-primary" onclick="window.print();">
                            <i class="fas fa-print me-1"></i> Print Order
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
