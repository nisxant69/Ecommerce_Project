<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Require admin access
require_admin();

// Handle stock updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Invalid form submission.');
        header('Location: inventory.php');
        exit();
    }
    
    $product_id = (int)$_POST['product_id'];
    $stock = (int)$_POST['stock'];
    $low_stock_threshold = (int)$_POST['low_stock_threshold'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE products 
            SET stock = ?, 
                low_stock_threshold = ?
            WHERE id = ?
        ");
        $stmt->execute([$stock, $low_stock_threshold, $product_id]);
        
        set_flash_message('success', 'Stock updated successfully.');
        header('Location: inventory.php');
        exit();
        
    } catch (PDOException $e) {
        error_log("Stock update error: " . $e->getMessage());
        set_flash_message('danger', 'Error updating stock.');
    }
}

// Get inventory data with filters
try {
    $where = "1=1";
    $params = [];
    
    // Stock level filter
    $stock_filter = $_GET['stock_level'] ?? '';
    if ($stock_filter === 'low') {
        $where .= " AND stock <= low_stock_threshold";
    } elseif ($stock_filter === 'out') {
        $where .= " AND stock = 0";
    }
    
    // Category filter
    $category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
    if ($category_id > 0) {
        $where .= " AND category_id = ?";
        $params[] = $category_id;
    }
    
    // Search filter
    $search = trim($_GET['search'] ?? '');
    if ($search) {
        $where .= " AND (name LIKE ? OR sku LIKE ?)";
        $search_param = "%{$search}%";
        $params = array_merge($params, [$search_param, $search_param]);
    }
    
    // Get categories for filter
    $cat_stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
    $categories = $cat_stmt->fetchAll();
    
    // Get inventory items with pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = 20;
    
    // Get total count
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE {$where}");
    $count_stmt->execute($params);
    $total_items = $count_stmt->fetchColumn();
    $total_pages = ceil($total_items / $per_page);
    $offset = ($page - 1) * $per_page;
    
    // Get items for current page
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name,
               (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) as times_ordered
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE {$where}
        ORDER BY p.stock ASC, p.name ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$per_page, $offset]));
    $inventory = $stmt->fetchAll();
    
    // Get low stock count for notification
    $low_stock_stmt = $pdo->query("
        SELECT COUNT(*) FROM products 
        WHERE stock <= low_stock_threshold 
        AND stock > 0
    ");
    $low_stock_count = $low_stock_stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("Inventory error: " . $e->getMessage());
    $inventory = [];
    $categories = [];
    $total_pages = 0;
    $page = 1;
    $low_stock_count = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                        <a class="nav-link active text-white" href="inventory.php">
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
                        <a class="nav-link text-white" href="users.php">
                            <i class="fas fa-users me-2"></i>
                            Users
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                <h1 class="h2">Inventory Management</h1>
                
                <?php if ($low_stock_count > 0): ?>
                <div class="alert alert-warning mb-0 d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $low_stock_count; ?> products are low on stock
                </div>
                <?php endif; ?>
            </div>

            <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Stock Level</label>
                            <div class="btn-group w-100">
                                <a href="?stock_level=all" class="btn btn-<?php 
                                    echo $stock_filter === '' ? 'primary' : 'outline-primary'; 
                                ?>">All</a>
                                <a href="?stock_level=low" class="btn btn-<?php 
                                    echo $stock_filter === 'low' ? 'warning' : 'outline-warning'; 
                                ?>">Low Stock</a>
                                <a href="?stock_level=out" class="btn btn-<?php 
                                    echo $stock_filter === 'out' ? 'danger' : 'outline-danger'; 
                                ?>">Out of Stock</a>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php 
                                    echo $category_id === $cat['id'] ? 'selected' : ''; 
                                ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Search by name or SKU"
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Inventory Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Category</th>
                                    <th>Current Stock</th>
                                    <th>Low Stock Threshold</th>
                                    <th>Times Ordered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventory as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($item['image_url']): ?>
                                            <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                 class="me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php endif; ?>
                                            <div>
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $item['stock'] === 0 ? 'danger' : 
                                                ($item['stock'] <= $item['low_stock_threshold'] ? 'warning' : 'success'); 
                                        ?>">
                                            <?php echo $item['stock']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $item['low_stock_threshold']; ?></td>
                                    <td><?php echo $item['times_ordered']; ?></td>
                                    <td>
                                        <button type="button" 
                                                class="btn btn-sm btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#updateStock<?php echo $item['id']; ?>">
                                            Update Stock
                                        </button>
                                        
                                        <!-- Stock Update Modal -->
                                        <div class="modal fade" id="updateStock<?php echo $item['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <input type="hidden" name="csrf_token" 
                                                               value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="product_id" 
                                                               value="<?php echo $item['id']; ?>">
                                                               
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">
                                                                Update Stock: <?php echo htmlspecialchars($item['name']); ?>
                                                            </h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        
                                                        <div class="modal-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Current Stock</label>
                                                                <input type="number" class="form-control" name="stock" 
                                                                       value="<?php echo $item['stock']; ?>" 
                                                                       min="0" required>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Low Stock Threshold</label>
                                                                <input type="number" class="form-control" 
                                                                       name="low_stock_threshold"
                                                                       value="<?php echo $item['low_stock_threshold']; ?>" 
                                                                       min="0" required>
                                                                <div class="form-text">
                                                                    You'll be notified when stock falls below this number
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" 
                                                                    data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Update Stock</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($inventory)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        No products found matching your criteria
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php 
                                    echo $stock_filter ? '&stock_level=' . urlencode($stock_filter) : '';
                                    echo $category_id ? '&category=' . $category_id : '';
                                    echo $search ? '&search=' . urlencode($search) : '';
                                ?>">Previous</a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php 
                                    echo $stock_filter ? '&stock_level=' . urlencode($stock_filter) : '';
                                    echo $category_id ? '&category=' . $category_id : '';
                                    echo $search ? '&search=' . urlencode($search) : '';
                                ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php 
                                    echo $stock_filter ? '&stock_level=' . urlencode($stock_filter) : '';
                                    echo $category_id ? '&category=' . $category_id : '';
                                    echo $search ? '&search=' . urlencode($search) : '';
                                ?>">Next</a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
