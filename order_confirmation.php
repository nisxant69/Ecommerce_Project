<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Require login
require_login();

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

try {
    // Fetch order details
    $stmt = $pdo->prepare("
        SELECT o.*, u.name, u.email 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.id = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        set_flash_message('danger', 'Order not found.');
        header('Location: account.php');
        exit();
    }
    
    // Fetch order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name, p.image_url 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    set_flash_message('danger', 'Error loading order details. Please try again later.');
    header('Location: account.php');
    exit();
}

// Calculate estimated delivery date (5-7 business days)
$delivery_date = date('Y-m-d', strtotime($order['created_at'] . ' +7 weekdays'));
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                        <h1 class="h3">Order Confirmed!</h1>
                        <p class="text-muted">Thank you for your purchase. Your order has been received.</p>
                    </div>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>Order Details</h5>
                            <p class="mb-1">Order #: <?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?></p>
                            <p class="mb-1">Date: <?php echo date('M d, Y', strtotime($order['created_at'])); ?></p>
                            <p class="mb-1">Status: 
                                <span class="badge bg-primary"><?php echo ucfirst($order['status']); ?></span>
                            </p>
                            <p class="mb-1">Payment Method: <?php echo str_replace('_', ' ', ucfirst($order['payment_method'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h5>Shipping Details</h5>
                            <p class="mb-1">Name: <?php echo htmlspecialchars($order['name']); ?></p>
                            <p class="mb-1">Address: <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                            <p class="mb-1">Estimated Delivery: <?php echo date('M d, Y', strtotime($delivery_date)); ?></p>
                        </div>
                    </div>
                    
                    <h5>Order Items</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="assets/images/<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                 class="img-thumbnail me-3" style="width: 50px;">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo format_price($item['price']); ?></td>
                                    <td><?php echo format_price($item['price'] * $item['quantity']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                    <td><?php echo format_price($order['total_amount']); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Shipping:</strong></td>
                                    <td><?php echo $order['total_amount'] >= 50 ? 'Free' : format_price(10); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                    <td><strong><?php echo format_price($order['total_amount']); ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <hr>
                    
                    <div class="text-center mt-4">
                        <p>Questions about your order? <a href="contact.php">Contact our support team</a></p>
                        <div class="mt-3">
                            <a href="products.php" class="btn btn-primary">Continue Shopping</a>
                            <a href="account.php" class="btn btn-outline-primary">View Order History</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
