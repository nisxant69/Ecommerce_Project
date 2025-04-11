<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Require admin access
require_admin();

// Get date range
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

try {
    // Sales Overview
    $sales_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_order_value,
            COUNT(DISTINCT user_id) as unique_customers
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND status != 'cancelled'
    ");
    $sales_stmt->execute([$start_date, $end_date]);
    $sales_overview = $sales_stmt->fetch();
    
    // Daily Sales
    $daily_sales_stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as orders,
            SUM(total_amount) as revenue
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND status != 'cancelled'
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $daily_sales_stmt->execute([$start_date, $end_date]);
    $daily_sales = $daily_sales_stmt->fetchAll();
    
    // Top Products
    $top_products_stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.name,
            p.sku,
            COUNT(oi.id) as times_ordered,
            SUM(oi.quantity) as units_sold,
            SUM(oi.quantity * oi.price) as revenue
        FROM products p
        JOIN order_items oi ON p.id = oi.product_id
        JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        AND o.status != 'cancelled'
        GROUP BY p.id, p.name, p.sku
        ORDER BY revenue DESC
        LIMIT 10
    ");
    $top_products_stmt->execute([$start_date, $end_date]);
    $top_products = $top_products_stmt->fetchAll();
    
    // Category Performance
    $category_stmt = $pdo->prepare("
        SELECT 
            c.name as category_name,
            COUNT(DISTINCT o.id) as total_orders,
            SUM(oi.quantity) as units_sold,
            SUM(oi.quantity * oi.price) as revenue
        FROM categories c
        JOIN products p ON c.id = p.category_id
        JOIN order_items oi ON p.id = oi.product_id
        JOIN orders o ON oi.order_id = o.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        AND o.status != 'cancelled'
        GROUP BY c.id, c.name
        ORDER BY revenue DESC
    ");
    $category_stmt->execute([$start_date, $end_date]);
    $category_performance = $category_stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Reports error: " . $e->getMessage());
    set_flash_message('danger', 'Error generating reports.');
    $sales_overview = $daily_sales = $top_products = $category_performance = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="products.php">
                            <i class="fas fa-box me-2"></i>
                            Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="inventory.php">
                            <i class="fas fa-warehouse me-2"></i>
                            Inventory
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="orders.php">
                            <i class="fas fa-shopping-cart me-2"></i>
                            Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active text-white" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i>
                            Reports
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                <h1 class="h2">Sales Reports</h1>
            </div>

            <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Date Range Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form class="row g-3">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                   value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                   value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Update Report</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sales Overview -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted">Total Orders</h6>
                            <h3 class="card-text">
                                <?php echo number_format($sales_overview['total_orders']); ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted">Total Revenue</h6>
                            <h3 class="card-text">
                                <?php echo format_price($sales_overview['total_revenue']); ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted">Average Order Value</h6>
                            <h3 class="card-text">
                                <?php echo format_price($sales_overview['avg_order_value']); ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title text-muted">Unique Customers</h6>
                            <h3 class="card-text">
                                <?php echo number_format($sales_overview['unique_customers']); ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Sales Chart -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Daily Sales</h5>
                    <canvas id="dailySalesChart"></canvas>
                </div>
            </div>

            <div class="row">
                <!-- Top Products -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Top Products</h5>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Units Sold</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_products as $product): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($product['name']); ?>
                                                <br>
                                                <small class="text-muted">
                                                    SKU: <?php echo htmlspecialchars($product['sku']); ?>
                                                </small>
                                            </td>
                                            <td><?php echo number_format($product['units_sold']); ?></td>
                                            <td><?php echo format_price($product['revenue']); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category Performance -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Category Performance</h5>
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Daily Sales Chart
const dailySalesCtx = document.getElementById('dailySalesChart').getContext('2d');
new Chart(dailySalesCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column(array_reverse($daily_sales), 'date')); ?>,
        datasets: [{
            label: 'Revenue',
            data: <?php echo json_encode(array_column(array_reverse($daily_sales), 'revenue')); ?>,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Category Performance Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode(array_column($category_performance, 'category_name')); ?>,
        datasets: [{
            data: <?php echo json_encode(array_column($category_performance, 'revenue')); ?>,
            backgroundColor: [
                'rgb(255, 99, 132)',
                'rgb(54, 162, 235)',
                'rgb(255, 206, 86)',
                'rgb(75, 192, 192)',
                'rgb(153, 102, 255)',
                'rgb(255, 159, 64)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'right'
            }
        }
    }
});
</script>

</body>
</html>
