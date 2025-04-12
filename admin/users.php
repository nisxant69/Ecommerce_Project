<?php
require_once '../includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
require_admin_login();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        set_flash_message('danger', 'Invalid form submission.');
        header('Location: users.php');
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    $user_id = (int)$_POST['user_id'];
    
    try {
        switch ($action) {
            case 'toggle_ban':
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET is_banned = NOT is_banned 
                    WHERE id = ? AND role != 'admin'
                ");
                $stmt->execute([$user_id]);
                set_flash_message('success', 'User status updated successfully.');
                break;
                
            case 'delete':
                // Check if user has orders
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
                $stmt->execute([$user_id]);
                if ($stmt->fetchColumn() > 0) {
                    set_flash_message('danger', 'Cannot delete user with existing orders.');
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                    $stmt->execute([$user_id]);
                    set_flash_message('success', 'User deleted successfully.');
                }
                break;

            case 'update_role':
                $new_role = $_POST['new_role'];
                if (!in_array($new_role, ['user', 'admin'])) {
                    set_flash_message('danger', 'Invalid role specified.');
                    break;
                }
                
                // Count number of admins
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                $stmt->execute();
                $admin_count = $stmt->fetchColumn();
                
                // Get current user role
                $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $current_role = $stmt->fetchColumn();
                
                // Prevent removing last admin
                if ($admin_count <= 1 && $current_role === 'admin' && $new_role === 'user') {
                    set_flash_message('danger', 'Cannot remove the last admin account.');
                    break;
                }
                
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_role, $user_id]);
                set_flash_message('success', 'User role updated successfully.');
                break;

            case 'reset_password':
                // Generate a random password
                $new_password = bin2hex(random_bytes(8));
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                // Store the new password temporarily to display to admin
                $_SESSION['temp_password'] = $new_password;
                set_flash_message('success', 'Password has been reset. New password: ' . $new_password);
                break;

            case 'update_user':
                $name = trim($_POST['name']);
                $email = trim($_POST['email']);
                
                if (empty($name) || empty($email)) {
                    set_flash_message('danger', 'Name and email are required.');
                    break;
                }
                
                // Check if email exists for other users
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetch()) {
                    set_flash_message('danger', 'Email already exists.');
                    break;
                }
                
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmt->execute([$name, $email, $user_id]);
                set_flash_message('success', 'User information updated successfully.');
                break;
        }
    } catch (PDOException $e) {
        error_log("User action error: " . $e->getMessage());
        set_flash_message('danger', 'Error processing request.');
    }
    
    header('Location: ' . ($_POST['redirect_url'] ?? 'users.php'));
    exit();
}

// Get user details if ID is provided
$user = null;
$user_orders = [];
if (isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    try {
        // Get user details
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Get user's orders
            $stmt = $pdo->prepare("
                SELECT * FROM orders 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([$user_id]);
            $user_orders = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Error fetching user details: " . $e->getMessage());
        set_flash_message('danger', 'Error loading user details.');
    }
}

// Get users list
try {
    $where = "1=1";
    $params = [];
    
    // Handle filters
    $role_filter = $_GET['role'] ?? '';
    if ($role_filter) {
        $where .= " AND role = ?";
        $params[] = $role_filter;
    }
    
    $status_filter = $_GET['status'] ?? '';
    if ($status_filter === 'banned') {
        $where .= " AND is_banned = 1";
    } elseif ($status_filter === 'active') {
        $where .= " AND is_banned = 0";
    }
    
    // Handle search
    $search = trim($_GET['search'] ?? '');
    if ($search) {
        $where .= " AND (name LIKE ? OR email LIKE ?)";
        $search_param = "%{$search}%";
        $params = array_merge($params, [$search_param, $search_param]);
    }
    
    // Get total users count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE {$where}");
    $stmt->execute($params);
    $total_users = $stmt->fetchColumn();
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $per_page = 20;
    $total_pages = ceil($total_users / $per_page);
    $offset = ($page - 1) * $per_page;
    
    // Get users
    $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE {$where} 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$per_page, $offset]));
    $users = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
    $total_pages = 0;
    $page = 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Admin Dashboard</title>
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
                        <a class="nav-link active" href="users.php">
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
                    <?php echo $user ? 'User Details: ' . htmlspecialchars($user['name']) : 'Users'; ?>
                </h1>
            </div>

            <?php if ($flash = get_flash_message()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <?php if ($user): ?>
            <!-- User Details -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h5>User Information</h5>
                            <form method="POST" class="mb-4">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="action" value="update_user">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <input type="hidden" name="redirect_url" value="<?php echo $_SERVER['REQUEST_URI']; ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label">Name</label>
                                    <input type="text" class="form-control" name="name" 
                                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <div class="form-control bg-light">Admin</div>
                                    <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="update_role">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="redirect_url" value="<?php echo $_SERVER['REQUEST_URI']; ?>">
                                            <select name="new_role" class="form-select" onchange="this.form.submit()">
                                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Update Information</button>
                            </form>

                            <div class="mt-4">
                                <h5>Password Management</h5>
                                <form method="POST" class="mb-3" onsubmit="return confirm('Are you sure you want to reset this user\'s password?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="redirect_url" value="<?php echo $_SERVER['REQUEST_URI']; ?>">
                                    <button type="submit" class="btn btn-warning">Reset Password</button>
                                </form>
                            </div>

                            <?php if ($user['role'] !== 'admin'): ?>
                            <div class="mt-4">
                                <h5>Account Actions</h5>
                                <form method="POST" class="d-inline-block">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="toggle_ban">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="redirect_url" value="<?php echo $_SERVER['REQUEST_URI']; ?>">
                                    <button type="submit" class="btn btn-<?php echo $user['is_banned'] ? 'success' : 'warning'; ?>">
                                        <?php echo $user['is_banned'] ? 'Unban User' : 'Ban User'; ?>
                                    </button>
                                </form>
                                
                                <form method="POST" class="d-inline-block ms-2" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="redirect_url" value="users.php">
                                    <button type="submit" class="btn btn-danger">Delete User</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($user_orders)): ?>
                    <h5>Recent Orders</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_orders as $order): ?>
                                <tr>
                                    <td>#<?php echo str_pad($order['id'], 8, '0', STR_PAD_LEFT); ?></td>
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
                                        <a href="orders.php?id=<?php echo $order['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <a href="users.php" class="btn btn-secondary">Back to Users</a>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- Users List -->
            <div class="card">
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <form class="d-flex gap-2">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search users..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <?php if ($search || $role_filter || $status_filter): ?>
                                <a href="users.php" class="btn btn-secondary">Clear</a>
                                <?php endif; ?>
                            </form>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <div class="btn-group">
                                <a href="?status=active" class="btn btn-<?php 
                                    echo $status_filter === 'active' ? 'success' : 'outline-success'; 
                                ?>">Active</a>
                                <a href="?status=banned" class="btn btn-<?php 
                                    echo $status_filter === 'banned' ? 'danger' : 'outline-danger'; 
                                ?>">Banned</a>
                            </div>
                            
                            <div class="btn-group ms-2">
                                <a href="?role=user" class="btn btn-<?php 
                                    echo $role_filter === 'user' ? 'primary' : 'outline-primary'; 
                                ?>">Users</a>
                                <a href="?role=admin" class="btn btn-<?php 
                                    echo $role_filter === 'admin' ? 'primary' : 'outline-primary'; 
                                ?>">Admins</a>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo ucfirst($user['role']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['is_banned'] ? 'danger' : 'success'; ?>">
                                            <?php echo $user['is_banned'] ? 'Banned' : 'Active'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <a href="?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <?php if ($user['role'] !== 'admin'): ?>
                                        <form method="POST" class="d-inline-block">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="toggle_ban">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php 
                                                echo $user['is_banned'] ? 'success' : 'warning'; 
                                            ?>">
                                                <i class="fas fa-<?php 
                                                    echo $user['is_banned'] ? 'unlock' : 'ban'; 
                                                ?>"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <?php echo $search ? 'No users found matching your search.' : 'No users found.'; ?>
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
                                    echo $role_filter ? '&role=' . urlencode($role_filter) : ''; 
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
