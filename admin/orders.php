<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Require admin access
require_admin();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Invalid form submission.');
        header('Location: orders.php');
        exit();
    }
    
    $order_id = (int)$_POST['order_id'];
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);
        
        set_flash_message('success', 'Order status updated successfully.');
    } catch (PDOException $e) {
        error_log("Error updating order status: " . $e->getMessage());
        set_flash_message('danger', 'Error updating order status.');
    }
    
    header('Location: orders.php');
    exit();
}

// Get order details if ID is provided
$order = null;
$order_items = [];
if (isset($_GET['id'])) {
    $order_id = (int)$_GET['id'];
    
    try {
        // Get order details
        $stmt = $pdo->prepare("
            SELECT o.*, u.name as customer_name, u.email as customer_email 
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if ($order) {
            // Get order items
            $stmt = $pdo->prepare("
                SELECT oi.*, p.name, p.image_url 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$order_id]);
            $order_items = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Error fetching order details: " . $e->getMessage());
        set_flash_message('danger', 'Error loading order details.');
    }
}

// Get orders list
try {
    $where = "1=1";
    $params = [];
    
    // Handle filters
    $status_filter = $_GET['status'] ?? '';
    if ($status_filter) {
        $where .= " AND o.status = ?";
        $params[] = $status_filter;
    }
    
    // Handle search
    $search = trim($_GET['search'] ?? '');
    if ($search) {
        $where .= " AND (o.id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
        $search_param = "%{$search}%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    // Get total orders count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE {$where}
    ");
    $stmt->execute($params);
    $total_orders = $stmt->fetchColumn();
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = 20;
    $total_pages = ceil($total_orders / $per_page);
    $offset = ($page - 1) * $per_page;
    
    // Get orders
    $stmt = $pdo->prepare("
        SELECT o.*, u.name as customer_name, u.email as customer_email 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE {$where} 
        ORDER BY o.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$per_page, $offset]));
    $orders = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching orders: " . $e->getMessage());
    $orders = [];
    $total_pages = 0;
    $page = 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,.75);
        }
        .sidebar .nav-link:hover {
            color: rgba(255,255,255,.95);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,.1);
        }
        .order-item-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
            <div class="position-sticky pt-3">
                <div class="mb-4 px-3">
                    <span class="fs-5 text-white">E-Commerce Admin</span>
                </div>
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">
                            <i class="fas fa-box me-2"></i>
                            Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="orders.php">
                            <i class="fas fa-shopping-cart me-2"></i>
                            Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-2"></i>
                            Users
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link" href="../index.php">
                            <i class="fas fa-store me-2"></i>
                            View Store
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>
                            Logout
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                <h1 class="h2">
                    <?php echo $order ? 'Order Details #' . str_pad($order['id'], 8, '0', STR_PAD_LEFT) : 'Orders'; ?>
                </h1>
            </div>

            <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($order): ?>
            <!-- Order Details -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>Order Information</h5>
                            <p class="mb-1">Order #: <?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?></p>
                            <p class="mb-1">Date: <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></p>
                            <p class="mb-1">Payment Method: <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></p>
                            <p class="mb-1">
                                Status: 
                                <form method="POST" class="d-inline-block" id="statusForm">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <select name="status" class="form-select form-select-sm d-inline-block w-auto" 
                                            onchange="document.getElementById('statusForm').submit();">
                                        <?php foreach (['pending', 'processing', 'completed', 'cancelled'] as $status): ?>
                                        <option value="<?php echo $status; ?>" <?php 
                                            echo $order['status'] === $status ? 'selected' : ''; 
                                        ?>>
                                            <?php echo ucfirst($status); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h5>Customer Information</h5>
                            <p class="mb-1">Name: <?php echo htmlspecialchars($order['customer_name']); ?></p>
                            <p class="mb-1">Email: <?php echo htmlspecialchars($order['customer_email']); ?></p>
                            <p class="mb-1">Shipping Address: <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                        </div>
                    </div>

                    <h5>Order Items</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="../assets/images/products/<?php echo $item['image_url']; ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                 class="order-item-image me-2">
                                            <?php echo htmlspecialchars($item['name']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo format_price($item['price']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
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

                    <div class="mt-3">
                        <a href="orders.php" class="btn btn-secondary">Back to Orders</a>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- Orders List -->
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <form class="d-flex gap-2">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search orders..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <?php if ($search || $status_filter): ?>
                                <a href="orders.php" class="btn btn-secondary">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <?php foreach (['pending', 'processing', 'completed', 'cancelled'] as $status): ?>
                            <a href="?status=<?php echo $status; ?>" 
                               class="btn btn-<?php 
                                   echo $status_filter === $status ? 'primary' : 'outline-primary'; 
                               ?>">
                                <?php echo ucfirst($status); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <a href="?id=<?php echo $order['id']; ?>" class="text-decoration-none">
                                            #<?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($order['customer_name']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                    </td>
                                    <td><?php echo format_price($order['total_amount']); ?></td>
                                    <td>
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
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <a href="?id=<?php echo $order['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <?php echo $search ? 'No orders found matching your search.' : 'No orders found.'; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                    echo $status_filter ? '&status=' . urlencode($status_filter) : ''; 
                                    echo $search ? '&search=' . urlencode($search) : ''; 
                                ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
