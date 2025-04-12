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
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ? AND (o.user_id = ? OR o.email = ?)
    ");
    $stmt->execute([$order_id, $_SESSION['user_id'] ?? null, $_SESSION['user_email'] ?? '']);
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
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
    
    // Get order status history
    $stmt = $pdo->prepare("
        SELECT h.*, u.name as updated_by_name
        FROM order_status_history h
        LEFT JOIN users u ON h.created_by = u.id
        WHERE h.order_id = ?
        ORDER BY h.created_at DESC
    ");
    $stmt->execute([$order_id]);
    $status_history = $stmt->fetchAll();
    
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
                            Order #<?php echo htmlspecialchars($order['order_number']); ?>
                        </h5>
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
                            <?php if (!empty($order['tracking_number'])): ?>
                            <p class="mb-1">Tracking Number: <?php echo htmlspecialchars($order['tracking_number']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($order['transaction_id'])): ?>
                            <p class="mb-1">Transaction ID: <?php echo htmlspecialchars($order['transaction_id']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <h6>Shipping Address</h6>
                            <p class="mb-1"><?php echo htmlspecialchars($order['full_name']); ?></p>
                            <p class="mb-1"><?php echo htmlspecialchars($order['shipping_address']); ?></p>
                            <?php if (!empty($order['shipping_city'])): ?>
                            <p class="mb-1">
                                <?php echo htmlspecialchars($order['shipping_city']); ?>, 
                                <?php echo htmlspecialchars($order['shipping_state'] ?? ''); ?> 
                                <?php echo htmlspecialchars($order['shipping_zip'] ?? ''); ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($order['shipping_country'])): ?>
                            <p class="mb-1"><?php echo htmlspecialchars($order['shipping_country']); ?></p>
                            <?php endif; ?>
                            <p class="mb-1">Email: <?php echo htmlspecialchars($order['email']); ?></p>
                            <p class="mb-1">Phone: <?php echo htmlspecialchars($order['phone']); ?></p>
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
                                            <img src="assets/images/products/<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['product_name'] ?? $item['product_name']); ?>"
                                                 class="me-2" style="width: 50px; height: 50px; object-fit: cover;">
                                            <?php endif; ?>
                                            <div>
                                                <?php if ($item['product_id']): ?>
                                                <a href="product.php?id=<?php echo $item['product_id']; ?>" 
                                                   class="text-decoration-none">
                                                    <?php echo htmlspecialchars($item['product_name'] ?? $item['product_name']); ?>
                                                </a>
                                                <?php else: ?>
                                                <?php echo htmlspecialchars($item['product_name']); ?>
                                                <?php endif; ?>
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
                                    <td class="text-end"><?php echo format_price($order['subtotal'] ?? $order['total_amount']); ?></td>
                                </tr>
                                <?php if (!empty($order['discount_amount']) && $order['discount_amount'] > 0): ?>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Discount:</strong></td>
                                    <td class="text-end">-<?php echo format_price($order['discount_amount']); ?></td>
                                </tr>
                                <?php if (!empty($order['coupon_code'])): ?>
                                <tr>
                                    <td colspan="3" class="text-end"><small>Coupon: <?php echo htmlspecialchars($order['coupon_code']); ?></small></td>
                                    <td></td>
                                </tr>
                                <?php endif; ?>
                                <?php endif; ?>
                                <?php if (!empty($order['tax_amount']) && $order['tax_amount'] > 0): ?>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Tax:</strong></td>
                                    <td class="text-end"><?php echo format_price($order['tax_amount']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Shipping:</strong></td>
                                    <td class="text-end"><?php echo format_price($order['shipping_amount'] ?? 0); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                    <td class="text-end"><strong><?php echo format_price($order['total_amount']); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <?php if (!empty($status_history)): ?>
                    <!-- Order Status History -->
                    <h6 class="mt-4">Order History</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Comment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($status_history as $history): ?>
                                <tr>
                                    <td><?php echo date('M d, Y H:i', strtotime($history['created_at'])); ?></td>
                                    <td><?php echo ucfirst($history['status']); ?></td>
                                    <td><?php echo htmlspecialchars($history['comment'] ?? ''); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($order['notes'])): ?>
                    <div class="mt-3">
                        <h6>Order Notes</h6>
                        <p><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="text-center mt-4">
                        <a href="profile.php" class="btn btn-secondary">Back to Profile</a>
                        
                        <?php if ($order['status'] === 'delivered'): ?>
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
