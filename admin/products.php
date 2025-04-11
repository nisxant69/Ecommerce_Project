<?php
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Require admin access
require_admin();

// Handle actions
$action = $_GET['action'] ?? '';
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Invalid form submission.');
        header('Location: products.php');
        exit();
    }
    
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $category_id = (int)$_POST['category_id'];
    
    $errors = [];
    
    // Validate input
    if (empty($name)) $errors[] = 'Product name is required.';
    if (empty($description)) $errors[] = 'Description is required.';
    if ($price <= 0) $errors[] = 'Price must be greater than 0.';
    if ($stock < 0) $errors[] = 'Stock cannot be negative.';
    if ($category_id <= 0) $errors[] = 'Please select a category.';
    
    // Handle image upload
    $image_url = '';
    if (!empty($_FILES['image']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['image']['type'], $allowed_types)) {
            $errors[] = 'Invalid image type. Please upload JPG, PNG, or WebP.';
        } elseif ($_FILES['image']['size'] > $max_size) {
            $errors[] = 'Image size must be less than 5MB.';
        } else {
            $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image_url = 'product_' . time() . '.' . $extension;
            $target_path = "../assets/images/products/" . $image_url;
            
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $errors[] = 'Error uploading image.';
            }
        }
    }
    
    if (empty($errors)) {
        try {
            if ($action === 'edit' && $product_id) {
                // Update existing product
                $stmt = $pdo->prepare("
                    UPDATE products 
                    SET name = ?, description = ?, price = ?, 
                        stock = ?, category_id = ?
                        " . ($image_url ? ", image_url = ?" : "") . "
                    WHERE id = ?
                ");
                
                $params = [$name, $description, $price, $stock, $category_id];
                if ($image_url) $params[] = $image_url;
                $params[] = $product_id;
                
                $stmt->execute($params);
                set_flash_message('success', 'Product updated successfully.');
                
            } else {
                // Create new product
                if (empty($image_url)) {
                    $errors[] = 'Product image is required.';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO products (name, description, price, stock, 
                                           category_id, image_url) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $name, $description, $price, $stock, 
                        $category_id, $image_url
                    ]);
                    set_flash_message('success', 'Product created successfully.');
                }
            }
            
            if (empty($errors)) {
                header('Location: products.php');
                exit();
            }
            
        } catch (PDOException $e) {
            error_log("Product save error: " . $e->getMessage());
            $errors[] = 'Error saving product. Please try again later.';
        }
    }
}

// Get categories for form
try {
    $categories = $pdo->query("
        SELECT * FROM categories 
        ORDER BY name
    ")->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $categories = [];
}

// Get product for editing
$product = null;
if ($action === 'edit' && $product_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM products 
            WHERE id = ? AND is_deleted = 0
        ");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if (!$product) {
            set_flash_message('danger', 'Product not found.');
            header('Location: products.php');
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error fetching product: " . $e->getMessage());
        set_flash_message('danger', 'Error loading product.');
        header('Location: products.php');
        exit();
    }
}

// Get products list
try {
    $where = "is_deleted = 0";
    $params = [];
    
    // Handle filters
    $filter = $_GET['filter'] ?? '';
    if ($filter === 'low_stock') {
        $where .= " AND stock < 10";
    }
    
    // Handle search
    $search = trim($_GET['search'] ?? '');
    if ($search) {
        $where .= " AND (name LIKE ? OR description LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }
    
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE {$where} 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching products: " . $e->getMessage());
    $products = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Admin Dashboard</title>
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
        .product-image {
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
                        <a class="nav-link active" href="products.php">
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
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3">
                <h1 class="h2">
                    <?php echo $action === 'edit' ? 'Edit Product' : ($action === 'new' ? 'New Product' : 'Products'); ?>
                </h1>
                <?php if (!in_array($action, ['new', 'edit'])): ?>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="?action=new" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Product
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (in_array($action, ['new', 'edit'])): ?>
            <!-- Product Form -->
            <div class="card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Product Name</label>
                                    <input type="text" class="form-control" id="name" name="name" required
                                           value="<?php echo $product ? htmlspecialchars($product['name']) : ''; ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="5" required><?php 
                                        echo $product ? htmlspecialchars($product['description']) : ''; 
                                    ?></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php 
                                            echo $product && $product['category_id'] == $category['id'] ? 'selected' : ''; 
                                        ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="price" class="form-label">Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="price" name="price" 
                                               step="0.01" min="0" required
                                               value="<?php echo $product ? $product['price'] : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="stock" class="form-label">Stock</label>
                                    <input type="number" class="form-control" id="stock" name="stock" 
                                           min="0" required
                                           value="<?php echo $product ? $product['stock'] : ''; ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="image" class="form-label">
                                        <?php echo $product ? 'Update Image' : 'Product Image'; ?>
                                    </label>
                                    <input type="file" class="form-control" id="image" name="image" 
                                           accept="image/jpeg,image/png,image/webp"
                                           <?php echo $product ? '' : 'required'; ?>>
                                    <?php if ($product && $product['image_url']): ?>
                                    <div class="mt-2">
                                        <img src="../assets/images/products/<?php echo $product['image_url']; ?>" 
                                             alt="Current image" class="img-thumbnail" style="height: 100px;">
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="products.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <?php echo $action === 'edit' ? 'Update' : 'Create'; ?> Product
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Products List -->
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <form class="d-flex gap-2">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search products..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <?php if ($search || $filter): ?>
                                <a href="products.php" class="btn btn-secondary">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <a href="?filter=low_stock" class="btn btn-warning <?php 
                                echo $filter === 'low_stock' ? 'active' : ''; 
                            ?>">
                                Low Stock
                            </a>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <img src="/ecomfinal/assets/images/products/<?php echo $product['image_url']; ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             class="product-image">
                                    </td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    <td><?php echo format_price($product['price']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $product['stock'] <= 5 ? 'danger' : 
                                                 ($product['stock'] < 10 ? 'warning' : 'success'); 
                                        ?>">
                                            <?php echo $product['stock']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?action=edit&id=<?php echo $product['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <?php echo $search ? 'No products found matching your search.' : 'No products found.'; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function deleteProduct(productId) {
    if (confirm('Are you sure you want to delete this product?')) {
        fetch(`api/delete_product.php?id=${productId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?php echo generate_csrf_token(); ?>'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Error deleting product');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error deleting product');
        });
    }
}
</script>
</body>
</html>
