<?php
require_once '../includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_admin_login();

// Handle review deletion
if (isset($_GET['delete'])) {
    $review_id = (int)$_GET['delete'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->execute([$review_id]);
        set_flash_message('success', 'Review deleted successfully.');
        
        header('Location: reviews.php');
        exit();
        
    } catch (PDOException $e) {
        error_log("Review delete error: " . $e->getMessage());
        set_flash_message('danger', 'Error deleting review.');
    }
}

// Handle review status update
if (isset($_GET['toggle_status'])) {
    $review_id = (int)$_GET['toggle_status'];
    
    try {
        $stmt = $pdo->prepare("
            UPDATE reviews 
            SET is_approved = NOT is_approved 
            WHERE id = ?
        ");
        $stmt->execute([$review_id]);
        set_flash_message('success', 'Review status updated successfully.');
        
        header('Location: reviews.php');
        exit();
        
    } catch (PDOException $e) {
        error_log("Review status update error: " . $e->getMessage());
        set_flash_message('danger', 'Error updating review status.');
    }
}

// Get all reviews with user and product information
try {
    $stmt = $pdo->query("
        SELECT r.*, u.name as user_name, p.name as product_name 
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        JOIN products p ON r.product_id = p.id 
        ORDER BY r.created_at DESC
    ");
    $reviews = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching reviews: " . $e->getMessage());
    $reviews = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews - Admin Dashboard</title>
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
        .star-rating {
            color: #ffc107;
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
                        <a class="nav-link" href="categories.php">
                            <i class="fas fa-tags me-2"></i>
                            Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="reviews.php">
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
                <h1 class="h2">Product Reviews</h1>
            </div>

            <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Reviews List -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>User</th>
                                    <th>Rating</th>
                                    <th>Review</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($review['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($review['user_name']); ?></td>
                                    <td>
                                        <div class="star-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($review['comment']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($review['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $review['is_approved'] ? 'success' : 'warning'; ?>">
                                            <?php echo $review['is_approved'] ? 'Approved' : 'Pending'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="?toggle_status=<?php echo $review['id']; ?>" 
                                           class="btn btn-sm btn-<?php echo $review['is_approved'] ? 'warning' : 'success'; ?>"
                                           title="<?php echo $review['is_approved'] ? 'Unapprove' : 'Approve'; ?>">
                                            <i class="fas fa-<?php echo $review['is_approved'] ? 'times' : 'check'; ?>"></i>
                                        </a>
                                        <a href="?delete=<?php echo $review['id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this review?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($reviews)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">No reviews found.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 