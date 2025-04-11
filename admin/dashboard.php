<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Require admin access
require_admin();

try {
    // Get statistics
    $stats = [
        'total_orders' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'pending_orders' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
        'total_products' => $pdo->query("SELECT COUNT(*) FROM products WHERE is_deleted = 0")->fetchColumn(),
        'low_stock' => $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 10 AND is_deleted = 0")->fetchColumn(),
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
        'total_revenue' => $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status != 'cancelled'")->fetchColumn()
    ];
    
    // Get recent orders
    $stmt = $pdo->query("
        SELECT o.*, u.name as customer_name 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $recent_orders = $stmt->fetchAll();
    
    // Get low stock products
    $stmt = $pdo->query("
        SELECT * FROM products 
        WHERE stock < 10 AND is_deleted = 0 
        ORDER BY stock ASC 
        LIMIT 5
    ");
    $low_stock_products = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    set_flash_message('danger', 'Error loading dashboard data.');
    $stats = [];
    $recent_orders = [];
    $low_stock_products = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - E-Commerce</title>
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
        .stat-card {
            border-left: 4px solid;
        }
        .stat-card.primary { border-left-color: #0d6efd; }
        .stat-card.success { border-left-color: #198754; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.info { border-left-color: #0dcaf0; }
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
                        <a class="nav-link active" href="dashboard.php">
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
                        <a class="nav-link" href="orders.php">
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
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="products.php?action=new" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus"></i> New Product
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3 mb-4">
                    <div class="card stat-card primary h-100">
                        <div class="card-body">
                            <h6 class="card-title text-muted">Total Orders</h6>
                            <h3 class="card-text"><?php echo number_format($stats['total_orders']); ?></h3>
                            <small class="text-muted">
                                <?php echo number_format($stats['pending_orders']); ?> pending
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card stat-card success h-100">
                        <div class="card-body">
                            <h6 class="card-title text-muted">Total Revenue</h6>
                            <h3 class="card-text"><?php echo format_price($stats['total_revenue']); ?></h3>
                            <small class="text-muted">All time</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card stat-card warning h-100">
                        <div class="card-body">
                            <h6 class="card-title text-muted">Products</h6>
                            <h3 class="card-text"><?php echo number_format($stats['total_products']); ?></h3>
                            <small class="text-muted">
                                <?php echo number_format($stats['low_stock']); ?> low stock
                            </small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card stat-card info h-100">
                        <div class="card-body">
                            <h6 class="card-title text-muted">Users</h6>
                            <h3 class="card-text"><?php echo number_format($stats['total_users']); ?></h3>
                            <small class="text-muted">Registered customers</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Orders -->
                <div class="col-md-8 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Recent Orders</h5>
                            <a href="orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>
                                                <a href="orders.php?id=<?php echo $order['id']; ?>" class="text-decoration-none">
                                                    #<?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
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
                                            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Products -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Low Stock Products</h5>
                            <a href="products.php?filter=low_stock" class="btn btn-sm btn-outline-warning">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Stock</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($low_stock_products as $product): ?>
                                        <tr>
                                            <td>
                                                <a href="products.php?id=<?php echo $product['id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($product['name']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $product['stock'] <= 5 ? 'danger' : 'warning'; ?>">
                                                    <?php echo $product['stock']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
