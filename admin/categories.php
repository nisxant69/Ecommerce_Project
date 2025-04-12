<?php
require_once '../includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_admin_login();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Invalid form submission.');
        header('Location: categories.php');
        exit();
    }
    
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    
    $errors = [];
    
    // Validate input
    if (empty($name)) $errors[] = 'Category name is required.';
    
    if (empty($errors)) {
        try {
            if ($category_id) {
                // Update existing category
                $stmt = $pdo->prepare("
                    UPDATE categories 
                    SET name = ?, description = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$name, $description, $category_id]);
                set_flash_message('success', 'Category updated successfully.');
            } else {
                // Create new category
                $stmt = $pdo->prepare("
                    INSERT INTO categories (name, description) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$name, $description]);
                set_flash_message('success', 'Category created successfully.');
            }
            
            header('Location: categories.php');
            exit();
            
        } catch (PDOException $e) {
            error_log("Category save error: " . $e->getMessage());
            $errors[] = 'Error saving category. Please try again later.';
        }
    }
}

// Handle category deletion
if (isset($_GET['delete'])) {
    $category_id = (int)$_GET['delete'];
    
    try {
        // Check if category has products
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmt->execute([$category_id]);
        $product_count = $stmt->fetchColumn();
        
        if ($product_count > 0) {
            set_flash_message('danger', 'Cannot delete category with existing products.');
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$category_id]);
            set_flash_message('success', 'Category deleted successfully.');
        }
        
        header('Location: categories.php');
        exit();
        
    } catch (PDOException $e) {
        error_log("Category delete error: " . $e->getMessage());
        set_flash_message('danger', 'Error deleting category.');
    }
}

// Get category for editing
$category = null;
if (isset($_GET['edit'])) {
    $category_id = (int)$_GET['edit'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$category_id]);
        $category = $stmt->fetch();
        
        if (!$category) {
            set_flash_message('danger', 'Category not found.');
            header('Location: categories.php');
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error fetching category: " . $e->getMessage());
        set_flash_message('danger', 'Error loading category.');
    }
}

// Get all categories
try {
    $stmt = $pdo->query("
        SELECT c.*, COUNT(p.id) as product_count 
        FROM categories c 
        LEFT JOIN products p ON c.id = p.category_id 
        GROUP BY c.id 
        ORDER BY c.name
    ");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Admin Dashboard</title>
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
                    <li class="nav-item">
                        <a class="nav-link active" href="categories.php">
                            <i class="fas fa-tags me-2"></i>
                            Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reviews.php">
                            <i class="fas fa-star me-2"></i>
                            Reviews
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
                    <?php echo $category ? 'Edit Category' : 'Categories'; ?>
                </h1>
                <?php if (!$category): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                    <i class="fas fa-plus"></i> Add Category
                </button>
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

            <?php if ($category): ?>
            <!-- Category Form -->
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="name" name="name" required
                                   value="<?php echo htmlspecialchars($category['name']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php 
                                echo htmlspecialchars($category['description']); 
                            ?></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="categories.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Category</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Categories List -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Products</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cat['name']); ?></td>
                                    <td><?php echo htmlspecialchars($cat['description']); ?></td>
                                    <td><?php echo $cat['product_count']; ?></td>
                                    <td>
                                        <a href="?edit=<?php echo $cat['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?delete=<?php echo $cat['id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this category?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4">No categories found.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Add Category Modal -->
            <div class="modal fade" id="categoryModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add New Category</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                
                                <div class="mb-3">
                                    <label for="modal_name" class="form-label">Category Name</label>
                                    <input type="text" class="form-control" id="modal_name" name="name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="modal_description" class="form-label">Description</label>
                                    <textarea class="form-control" id="modal_description" name="description" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Add Category</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 